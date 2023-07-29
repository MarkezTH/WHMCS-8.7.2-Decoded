<?php

namespace WHMCS\Admin\Utilities\Tools\TldSync;

class TldSyncController
{
    public function importStart(\WHMCS\Http\Message\ServerRequest $request)
    {
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("setup.tldImport"))->setSidebarName("config")->setFavicon("domains")->setHelpLink("TLD Importing");
        $supportedRegistrars = [];
        $registrarController = new \WHMCS\Module\Registrar();
        $activeRegistrars = $registrarController->getActiveModules();
        foreach ($activeRegistrars as $registrar) {
            $loadedRegistrar = $registrarController->load($registrar);
            if ($loadedRegistrar && $registrarController->functionExists("GetTldPricing")) {
                $supportedRegistrars[$registrar] = ["logo" => $registrarController->getLogoFilename(), "name" => $registrarController->getDisplayName()];
            }
        }
        $basePath = \WHMCS\Utility\Environment\WebHelper::getBaseUrl(ROOTDIR);
        $content = view("admin.utilities.tools.tld-sync.tld-import-index", ["basePath" => $basePath, "supportedRegistrars" => $supportedRegistrars]);
        $view->setBodyContent($content);
        return $view;
    }

    private function loadExistingTldModels(\WHMCS\Results\ResultsList $tldResults)
    {
        $existingTlds = \WHMCS\Domains\Extension::all();
        foreach ($tldResults as $tldResult) {
            $existingTld = $existingTlds->where("extension", $tldResult->getExtension())->first();
            $tldResult->setExisting($existingTld);
        }
    }

    public function importLoad(\WHMCS\Http\Message\ServerRequest $request)
    {
        \WHMCS\Session::release();
        $registrar = $request->get("registrar");
        try {
            $registrarInterface = new \WHMCS\Module\Registrar();
            if (!$registrarInterface->load($registrar)) {
                throw new \UnexpectedValueException("Unable to load Registrar: " . $registrar);
            }
            $displayName = $registrarInterface->getDisplayName();
            if (!$registrarInterface->isActive($registrar)) {
                throw new \UnexpectedValueException("Registrar is not active: " . $displayName);
            }
            if (!$registrarInterface->functionExists("GetTldPricing")) {
                throw new \UnexpectedValueException("Unsupported Registrar: " . $displayName);
            }
            $tldResults = $registrarInterface->call("GetTldPricing");
            if (is_array($tldResults) && array_key_exists("error", $tldResults)) {
                throw new \WHMCS\Exception\Module\MalformedResponseException($displayName . ": " . $tldResults["error"]);
            }
            if (!$tldResults instanceof \WHMCS\Results\ResultsList) {
                throw new \WHMCS\Exception\Module\MalformedResponseException("Invalid GetTldPricing Response from Module: " . $displayName);
            }
            \WHMCS\TransientData::getInstance()->store($registrar . "GetTldPricing", json_encode($tldResults->toArray()), 3600);
            $returnedTlds = [];
            $systemCurrency = getCurrency();
            $tldCurrencies = [];
            $showGrace = false;
            foreach ($tldResults as $tldResult) {
                if (!$tldResult->isUnsupportedTld()) {
                    $returnedTlds[$tldResult->getExtension()] = $tldResult;
                    if (!$showGrace && !is_null($tldResult->getGraceFeePrice())) {
                        $showGrace = true;
                    }
                    $thisCurrency = $tldResult->getCurrency();
                    if ($thisCurrency != $systemCurrency["code"] && !in_array($thisCurrency, $tldCurrencies)) {
                        $tldCurrencies[] = $thisCurrency;
                    }
                }
            }
            $returnedTlds = collect($returnedTlds);
            $categories = [];
            foreach ((new \WHMCS\Domain\TopLevel\Categories())->getCategoriesByTlds($returnedTlds->keys()->toArray()) as $category => $tlds) {
                foreach ($tlds as $tld) {
                    $categories[$category][] = $returnedTlds[$tld];
                }
            }
            $categories["All"] = $returnedTlds->toArray();
            $existingTlds = \WHMCS\Domains\Extension::all();
            $existingTldsOrderMap = $existingTlds->pluck("order", "extension");
            foreach (array_keys($categories) as $category) {
                usort($categories[$category], function (\WHMCS\Domain\TopLevel\ImportItem $first, \WHMCS\Domain\TopLevel\ImportItem $second) use($existingTldsOrderMap) {
                    $firstExists = $existingTldsOrderMap->has($first->getExtension());
                    $secondExists = $existingTldsOrderMap->has($second->getExtension());
                    if ($firstExists && $secondExists) {
                        return $existingTldsOrderMap[$second->getExtension()] < $existingTldsOrderMap[$first->getExtension()];
                    }
                    if (!$firstExists && !$secondExists) {
                        return strcmp($first->getExtension(), $second->getExtension());
                    }
                    if (!$firstExists && $secondExists) {
                        return 1;
                    }
                    return -1;
                });
            }
            $currencies = \WHMCS\Billing\Currency::pluck("id", "code");
            $response = ["success" => true, "body" => view("admin.utilities.tools.tld-sync.import-list", ["categories" => $categories, "currency" => $systemCurrency, "registrar" => $registrar, "pricing" => $this->calculatePricing($existingTlds, $tldResults, $currencies), "currencies" => $currencies, "tldCurrencies" => $tldCurrencies, "existingTldMap" => $existingTlds->pluck("id", "extension"), "registrarMap" => $existingTlds->pluck("autoreg", "extension"), "showGrace" => $showGrace])];
        } catch (\Exception $e) {
            $response = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    protected function calculatePricing($existingTlds, $tldImportItems, $currencies)
    {
        $pricingKeys = [\WHMCS\Billing\PricingInterface::TYPE_DOMAIN_REGISTER, \WHMCS\Billing\PricingInterface::TYPE_DOMAIN_TRANSFER, \WHMCS\Billing\PricingInterface::TYPE_DOMAIN_RENEW];
        $pricingData = \WHMCS\Billing\Pricing::whereIn("type", $pricingKeys)->where("currency", 1)->get();
        $pricingFields = ["msetupfee", "qsetupfee", "ssetupfee", "asetupfee", "bsetupfee", "monthly", "quarterly", "semiannually", "annually", "biennially"];
        $sellingPricing = [];
        foreach ($pricingData as $data) {
            $price = NULL;
            foreach ($pricingFields as $field) {
                if (0 <= $data->{$field}) {
                    $price = $data->{$field};
                    $sellingPricing[$data->type . "_" . $data->relid] = $price;
                }
            }
        }
        $pricingPrefixes = ["register" => \WHMCS\Billing\PricingInterface::TYPE_DOMAIN_REGISTER . "_", "transfer" => \WHMCS\Billing\PricingInterface::TYPE_DOMAIN_TRANSFER . "_", "renew" => \WHMCS\Billing\PricingInterface::TYPE_DOMAIN_RENEW . "_"];
        $existingTldMap = $existingTlds->pluck("id", "extension");
        $graceSelling = $existingTlds->pluck("grace_period_fee", "id");
        $redemptionSelling = $existingTlds->pluck("redemption_grace_period_fee", "id");
        $tlds = [];
        foreach ($tldImportItems as $tld) {
            $extension = $tld->getExtension();
            $extId = NULL;
            if ($existingTldMap->has($extension)) {
                $extId = $existingTldMap[$extension];
            }
            $isSelling = isset($sellingPricing[$pricingPrefixes["register"] . $extId]);
            $currencyId = $currencies->has($tld->getCurrency()) ? $currencies[$tld->getCurrency()] : NULL;
            $pricing = [];
            foreach ($pricingPrefixes as $type => $prefix) {
                $selling = $isSelling ? $sellingPricing[$prefix . $extId] : NULL;
                $methodName = "get" . ucfirst($type) . "Price";
                $cost = $tld->{$methodName}();
                if ($currencyId !== 1) {
                    $cost = convertCurrency($cost, $currencyId, 1);
                }
                $pricing[$type] = ["selling" => $selling, "cost" => format_as_currency($cost), "margin" => $isSelling ? ["absolute" => format_as_currency($selling - $cost), "percentage" => (new \WHMCS\Billing\Pricing\Markup())->amount($cost)->percentageDifference($selling)] : NULL];
            }
            $selling = $isSelling && 0 <= $graceSelling[$extId] ? $graceSelling[$extId] : NULL;
            $cost = $tld->getGraceFeePrice();
            if ($currencyId !== 1) {
                $cost = convertCurrency($cost, $currencyId, 1);
            }
            $pricing["grace"] = ["selling" => $selling, "cost" => format_as_currency($cost), "margin" => $isSelling && 0 <= $graceSelling[$extId] ? ["absolute" => format_as_currency($selling - $cost), "percentage" => (new \WHMCS\Billing\Pricing\Markup())->amount($cost)->percentageDifference($selling)] : NULL];
            $selling = $isSelling && 0 <= $redemptionSelling[$extId] ? $redemptionSelling[$extId] : NULL;
            $cost = $tld->getRedemptionFeePrice();
            if ($currencyId !== 1) {
                $cost = convertCurrency($cost, $currencyId, 1);
            }
            $pricing["redemption"] = ["selling" => $selling, "cost" => format_as_currency($cost), "margin" => $isSelling && 0 <= $redemptionSelling[$extId] ? ["absolute" => format_as_currency($selling - $cost), "percentage" => (new \WHMCS\Billing\Pricing\Markup())->amount($cost)->percentageDifference($selling)] : NULL];
            $tlds[$extension] = $pricing;
        }
        return $tlds;
    }

    public function importTlds(\WHMCS\Http\Message\ServerRequest $request)
    {
        \WHMCS\Session::release();
        $registrar = $request->get("registrar");
        $tlds = array_filter(explode(",", $request->get("tld")));
        $profitMargin = $request->get("margin", 0);
        $marginType = $request->get("margin_type");
        $roundingValue = $request->get("rounding_value", -1);
        $syncRedemptionPricing = $request->get("sync_redemption", 0);
        $setAutoRegister = $request->get("set_auto_register", 0);
        $method = "factoryPercentage";
        $importedItems = [];
        $errors = [];
        if ($marginType === "fixed") {
            $method = "factoryFixed";
        }
        try {
            $registrarInterface = new \WHMCS\Module\Registrar();
            if (!$registrarInterface->load($registrar)) {
                throw new \UnexpectedValueException("Unable to load Registrar: " . $registrar);
            }
            if (!$registrarInterface->isActive($registrar)) {
                throw new \UnexpectedValueException("Registrar is not active: " . $registrar);
            }
            if (!$registrarInterface->functionExists("GetTldPricing")) {
                throw new \UnexpectedValueException("Unsupported Registrar: " . $registrar);
            }
            $tldResults = \WHMCS\TransientData::getInstance()->retrieve($registrar . "GetTldPricing");
            if ($tldResults) {
                $tldResults = json_decode($tldResults, true);
                if ($tldResults && is_array($tldResults)) {
                    $tldResults = collect($tldResults);
                }
            }
            if (!$tldResults) {
                $tldResults = $registrarInterface->call("GetTldPricing");
                if (is_array($tldResults) && array_key_exists("error", $tldResults)) {
                    throw new \WHMCS\Exception\Module\MalformedResponseException($tldResults["error"]);
                }
                if (!$tldResults instanceof \WHMCS\Results\ResultsList) {
                    throw new \WHMCS\Exception\Module\MalformedResponseException("Invalid GetTldPricing Response from Module: " . $registrar);
                }
                $tldResults = collect($tldResults->toArray());
            }
            $maxOrder = \WHMCS\Domains\Extension::max("order");
            if (!is_numeric($maxOrder)) {
                $maxOrder = 0;
            }
            $nonLinearRegistrationPricing = (bool) $registrarInterface->getMetaDataValue("NonLinearRegistrationPricing");
            $existingTlds = \WHMCS\Domains\Extension::all();
            $existingTldsOrderMap = $existingTlds->pluck("order", "extension");
            $currencies = \WHMCS\Billing\Currency::pluck("id", "code");
            foreach ($tlds as $tld) {
                try {
                    $tldData = $tldResults->where("extension", $tld)->first();
                    if ($tldData) {
                        $tldData = \WHMCS\Domain\TopLevel\ImportItem::fromArray($tldData);
                    }
                    if ($tldData) {
                        $localRecord = $existingTlds->where("extension", $tldData->getExtension())->first();
                        if (!$localRecord) {
                            $localRecord = new \WHMCS\Domains\Extension();
                            $localRecord->extension = $tldData->getExtension();
                        }
                        if ($setAutoRegister) {
                            $localRecord->autoRegistrationRegistrar = $registrar;
                        }
                        if (!$localRecord->exists) {
                            $localRecord->order = $maxOrder++;
                            $localRecord->supportsDnsManagement = 0;
                            $localRecord->supportsEmailForwarding = 0;
                            $localRecord->supportsIdProtection = 0;
                            $localRecord->requiresEppCode = $tldData->getRequiresEpp();
                            $localRecord->group = "";
                        }
                        if ($localRecord->isDirty()) {
                            $localRecord->save();
                        }
                        $years = $tldData->getYears();
                        $minYears = min($years);
                        $basePricing = ["register" => $tldData->getRegisterPrice() ?: 0, "renew" => $tldData->getRenewPrice() ?: 0, "transfer" => $tldData->getTransferPrice() ?: 0];
                        if (!is_null($tldData->getGraceFeePrice())) {
                            $basePricing["grace"] = $tldData->getGraceFeePrice() ?: 0;
                        }
                        if (!is_null($tldData->getRedemptionFeePrice())) {
                            $basePricing["restore"] = $tldData->getRedemptionFeePrice() ?: 0;
                        }
                        $convertCurrencyId = $currencies->has($tldData->getCurrency()) ? $currencies[$tldData->getCurrency()] : NULL;
                        if (is_null($convertCurrencyId)) {
                            throw new \UnexpectedValueException("Currency Not Found: " . $tldData->getCurrency());
                        }
                        foreach ($currencies as $currencyId) {
                            $convertedPrices = [];
                            foreach ($basePricing as $key => $value) {
                                if (0 < $value) {
                                    $value = convertCurrency($value, $convertCurrencyId, $currencyId);
                                }
                                $convertedPrices[$key] = $value;
                            }
                            foreach (["register", "renew", "transfer"] as $type) {
                                $price = \WHMCS\Domains\Extension\Pricing::firstOrNew(["type" => "domain" . $type, "relid" => $localRecord->id, "currency" => $currencyId, "tsetupfee" => 0]);
                                $price->year1 = -1;
                                $price->year2 = -1;
                                $price->year3 = -1;
                                $price->year4 = -1;
                                $price->year5 = -1;
                                $price->year6 = -1;
                                $price->year7 = -1;
                                $price->year8 = -1;
                                $price->year9 = -1;
                                $price->year10 = -1;
                                $tldPrice = $convertedPrices[$type];
                                if ($tldPrice === "-") {
                                    $tldPrice = -1;
                                }
                                $renewPrice = $tldPrice;
                                if ($type === "register" && $nonLinearRegistrationPricing) {
                                    $renewPrice = $convertedPrices["renew"];
                                    if ($renewPrice === "-") {
                                        $renewPrice = -1;
                                    }
                                }
                                $i = 1;
                                foreach ($years as $year) {
                                    if (!($year < 1 || 10 < $year)) {
                                        if (!($type == "renew" && 10 < $year)) {
                                            if (!($type == "transfer" && 1 < $i || $type == "renew" && $i == 10)) {
                                                $yearVar = "year" . $year;
                                                $setPrice = $tldPrice;
                                                if (0 < $setPrice) {
                                                    if ($minYears < $year && $nonLinearRegistrationPricing && $renewPrice < 0) {
                                                        $setPrice = -1;
                                                    } else {
                                                        if ($minYears < $year && $nonLinearRegistrationPricing) {
                                                            $setPrice = $setPrice / $minYears + $renewPrice / $minYears * ($year - $minYears);
                                                        } else {
                                                            $setPrice = $setPrice / $minYears * $year;
                                                        }
                                                        $setPrice = \WHMCS\Billing\Pricing\Markup::$method($setPrice, $profitMargin, $roundingValue);
                                                    }
                                                }
                                                $price->{$yearVar} = $setPrice;
                                                $i++;
                                            }
                                        }
                                    }
                                    $price->save();
                                }
                            }
                            if ($currencyId === 1 && $syncRedemptionPricing) {
                                if (!empty($convertedPrices["grace"])) {
                                    $setPrice = \WHMCS\Billing\Pricing\Markup::$method($convertedPrices["grace"], $profitMargin, $roundingValue);
                                    if ($tldData->getGraceFeeDays()) {
                                        $localRecord->gracePeriod = $tldData->getGraceFeeDays();
                                    }
                                    $localRecord->gracePeriodFee = $setPrice;
                                }
                                if (!empty($convertedPrices["restore"])) {
                                    $setPrice = \WHMCS\Billing\Pricing\Markup::$method($convertedPrices["restore"], $profitMargin, $roundingValue);
                                    if ($tldData->getRedemptionFeeDays()) {
                                        $localRecord->redemptionGracePeriod = $tldData->getRedemptionFeeDays();
                                    }
                                    $localRecord->redemptionGracePeriodFee = $setPrice;
                                }
                            }
                        }
                        if ($localRecord->isDirty()) {
                            $localRecord->save();
                        }
                        $importedItems[] = $tld;
                    }
                } catch (\Exception $e) {
                    $errors[] = ["tld" => $tld, "error" => $e->getMessage()];
                }
            }
            \WHMCS\Config\Setting::setValue("LastTldSync", \WHMCS\Carbon::now()->toDateTimeString());
            $response = ["success" => true, "imported" => $importedItems, "failed" => $errors];
        } catch (\Exception $e) {
            $response = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
