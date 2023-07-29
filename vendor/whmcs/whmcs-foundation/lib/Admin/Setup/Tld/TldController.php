<?php

namespace WHMCS\Admin\Setup\Tld;

class TldController
{
    private $domainPricingTypes = ["register" => "domainregister", "renew" => "domainrenew", "transfer" => "domaintransfer"];
    private $currencies = NULL;
    private $registerPricing = [];
    private $renewPricing = [];
    private $transferPricing = [];
    private $copyToYears = false;
    private $graceFee = NULL;
    private $graceDuration = NULL;
    private $redemptionFee = NULL;
    private $redemptionDuration = NULL;
    private $tldIds = [];

    protected function buildPricingForCurrency($pricingData, \WHMCS\Billing\Currency $currency)
    {
        if (isset($pricingData[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID]) && ($pricingData[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] || $pricingData[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] === "0")) {
            if ($currency->id == \WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID) {
                if ($pricingData[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] < 0) {
                    $pricingData[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] = -1;
                }
            } else {
                $value = $pricingData[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID];
                if (0 < $value) {
                    $value = convertCurrency($value, \WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID, $currency->id);
                }
                $pricingData[$currency->id] = $value;
            }
        }
        return $pricingData;
    }

    protected function buildPricingArraysUsingCurrencyConversion()
    {
        foreach ($this->currencies as $currency) {
            $this->registerPricing = $this->buildPricingForCurrency($this->registerPricing, $currency);
            $this->renewPricing = $this->buildPricingForCurrency($this->renewPricing, $currency);
            $this->transferPricing = $this->buildPricingForCurrency($this->transferPricing, $currency);
        }
    }

    protected function getUpdateArraysFromBuiltPricing()
    {
        $updatePricing = [];
        $multipliers = ["qsetupfee" => 2, "ssetupfee" => 3, "asetupfee" => 4, "bsetupfee" => 5, "monthly" => 6, "quarterly" => 7, "semiannually" => 8, "annually" => 9, "biennially" => 10];
        foreach ($this->domainPricingTypes as $pricingType => $databaseField) {
            $varName = $pricingType . "Pricing";
            $value = $this->{$varName};
            foreach ($this->currencies as $currency) {
                $valueToUse = $value[$currency->id];
                if ($valueToUse) {
                    $updatePricing[$databaseField][$currency->id]["msetupfee"] = $valueToUse;
                    if ($pricingType != "transfer" && $this->copyToYears) {
                        foreach ($multipliers as $field => $multiplier) {
                            $updatePricing[$databaseField][$currency->id][$field] = $valueToUse * $multiplier;
                            if ($multiplier == 10 && $pricingType == "renew") {
                                $updatePricing[$databaseField][$currency->id][$field] = -1;
                            }
                        }
                    } else {
                        if ($this->copyToYears) {
                            foreach (array_keys($multipliers) as $field) {
                                $updatePricing[$databaseField][$currency->id][$field] = -1;
                            }
                        }
                    }
                }
            }
        }
        return $updatePricing;
    }

    protected function savePricing($updatePricing)
    {
        if ($updatePricing) {
            foreach ($updatePricing as $pricingType => $currencyBasedValues) {
                foreach ($currencyBasedValues as $currencyId => $pricingValues) {
                    foreach ($this->tldIds as $tldId) {
                        \WHMCS\Database\Capsule::table("tblpricing")->where("currency", $currencyId)->where("relid", $tldId)->where("type", $pricingType)->where("tsetupfee", 0)->update($pricingValues);
                    }
                }
            }
        }
    }

    protected function conditionallySaveGraceAndRedemptionData()
    {
        foreach ($this->tldIds as $tldId) {
            if ($this->graceDuration || $this->graceDuration === "0" || $this->graceFee || $this->graceFee === "0" || $this->redemptionDuration || $this->redemptionDuration === "0" || $this->redemptionFee || $this->redemptionFee === "0") {
                $extensionToUpdate = \WHMCS\Domains\Extension::find($tldId);
                if ($this->graceDuration || $this->graceDuration === "0") {
                    $extensionToUpdate->gracePeriod = (int) $this->graceDuration;
                }
                if ($this->graceFee || $this->graceFee === "0") {
                    $extensionToUpdate->gracePeriodFee = (double) $this->graceFee;
                }
                if ($this->redemptionDuration || $this->redemptionDuration === "0") {
                    $extensionToUpdate->redemptionGracePeriod = (int) $this->redemptionDuration;
                }
                if ($this->redemptionFee || $this->redemptionFee === "0") {
                    $extensionToUpdate->redemptionGracePeriodFee = (double) $this->redemptionFee;
                }
                $extensionToUpdate->save();
            }
        }
    }

    public function massConfiguration(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->tldIds = $request->request()->get("tldIds");
        $pricing = $request->request()->get("pricing");
        $this->registerPricing[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] = $pricing["register"];
        $this->renewPricing[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] = $pricing["renew"];
        $this->transferPricing[\WHMCS\Billing\Currency::DEFAULT_CURRENCY_ID] = $pricing["transfer"];
        $this->copyToYears = $pricing["copyToYears"] != "false";
        $this->graceFee = $pricing["grace"]["fee"];
        $this->graceDuration = $pricing["grace"]["duration"];
        $this->redemptionFee = $pricing["redemption"]["fee"];
        $this->redemptionDuration = $pricing["redemption"]["duration"];
        $this->currencies = \WHMCS\Billing\Currency::all();
        $this->buildPricingArraysUsingCurrencyConversion();
        $this->savePricing($this->getUpdateArraysFromBuiltPricing());
        $this->conditionallySaveGraceAndRedemptionData();
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }

    public function manageSpotlight($JsonResponse, $request)
    {
        $tld = $request->get("tld");
        $action = $request->get("action");
        $spotlightTlds = explode(",", \WHMCS\Config\Setting::getValue("SpotlightTLDs"));
        $items = [];
        switch ($action) {
            case "add":
                if (!in_array($tld, $spotlightTlds)) {
                    $spotlightTlds[] = $tld;
                }
                foreach ($spotlightTlds as $savedTld) {
                    if ($savedTld) {
                        $items[] = $savedTld;
                    }
                }
                break;
            case "sort":
            case "remove":
                $spotlightTlds = $request->get("order") ?: $spotlightTlds;
                foreach ($spotlightTlds as $spotlight) {
                    if ($spotlight && $spotlight != $tld) {
                        $items[] = $spotlight;
                    }
                }
                break;
            default:
                $outputItems = array_pad($items, 8, "0");
                $items = implode(",", $items);
                \WHMCS\Config\Setting::setValue("SpotlightTLDs", $items);
                $items = implode(",", $outputItems);
                return new \WHMCS\Http\Message\JsonResponse(["items" => $items]);
        }
    }
}
