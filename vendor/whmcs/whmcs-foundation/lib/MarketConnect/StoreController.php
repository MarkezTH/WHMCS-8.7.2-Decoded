<?php

namespace WHMCS\MarketConnect;

class StoreController
{
    public function order(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = new Output\ClientArea();
        $ca->setPageTitle(\Lang::trans("store.configure.configureProduct") . " - " . \Lang::trans("navStore"));
        $ca->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $ca->addToBreadCrumb(routePath("store"), \Lang::trans("navStore"));
        $ca->addToBreadCrumb("#", \Lang::trans("store.configure.configureProduct"));
        $ca->initPage();
        $currency = \Currency::factoryForClientArea();
        $ca->assign("activeCurrency", $currency);
        $pid = $request->get("pid");
        $serviceId = $request->get("serviceid");
        $productKey = $request->get("productkey");
        if ($productKey && !$pid) {
            $pid = \WHMCS\Product\Product::where("servertype", "marketconnect")->where("configoption1", $productKey)->pluck("id")->first();
        }
        $requestBillingCycle = $request->get("billingcycle", "");
        if ($requestBillingCycle) {
            \WHMCS\Session::set("storeBillingCycle", $requestBillingCycle);
        }
        $ca->assign("requestedCycle", \WHMCS\Session::get("storeBillingCycle"));
        if ($pid) {
            \WHMCS\Session::set("storePid", $pid);
        }
        $pid = \WHMCS\Session::get("storePid");
        if (!$pid) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php");
        }
        $product = \WHMCS\Product\Product::find($pid);
        if (is_null($product)) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php");
        }
        $product->pricing($currency);
        $ca->assign("product", $product);
        $promotionalHelper = MarketConnect::factoryPromotionalHelperByProductKey($product->productKey);
        $requireDomain = $promotionalHelper->requiresDomain();
        if (!$promotionalHelper) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php");
        }
        $upsellProduct = $promotionalHelper->getBestUpsell($product->productKey);
        if (!is_null($upsellProduct)) {
            $upsellProduct->pricing($currency);
            $upsellComparison = new \WHMCS\Product\Pricing\Comparison($upsellProduct->pricing($currency), $product->pricing($currency), $currency);
        }
        $ca->assign("upsellProduct", $upsellProduct);
        $ca->assign("upsellComparison", $upsellComparison);
        $ca->assign("promotion", $promotionalHelper->getUpsellPromotionalContent($upsellProduct->productKey));
        $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $product->moduleConfigOption1)->get()->where("productAddon.module", "marketconnect")->first();
        $addonModel = $addonModel->productAddon;
        $availablePids = $addonModel->packages;
        $domains = $domainRegistrations = new \Illuminate\Support\Collection();
        if (\Auth::client()) {
            $domains = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->where("domain", "!=", "")->whereIn("packageid", $availablePids)->where("domainstatus", "Active")->pluck("domain");
            $domainRegistrations = \WHMCS\Domain\Domain::where("userid", \Auth::client()->id)->where("domain", "!=", "")->where("status", "Active")->pluck("domain");
        }
        $existingDomains = $domains->merge($domainRegistrations)->unique();
        $ca->assign("domains", $existingDomains);
        $productType = strtolower(explode("_", $product->productKey)[0]);
        $allowSubdomains = $ca->isLoggedIn() && in_array($productType, Services\Symantec::SSL_TYPES);
        $ca->assign("allowSubdomains", $allowSubdomains);
        $customDomain = "";
        $selectedDomain = \WHMCS\Session::get("storeSelectedDomain");
        if ($serviceId) {
            try {
                $selectedDomain = \WHMCS\Service\Service::findOrFail($serviceId)->domain;
                if ($serviceId) {
                    \WHMCS\Session::set("storeSelectedDomain", $selectedDomain);
                }
            } catch (\Exception $e) {
            }
        }
        $sslCompetitiveUpgradeDomain = \WHMCS\Session::get("competitiveUpgradeDomain");
        if (!empty($sslCompetitiveUpgradeDomain)) {
            if ($existingDomains->contains($sslCompetitiveUpgradeDomain)) {
                $selectedDomain = $sslCompetitiveUpgradeDomain;
            } else {
                $customDomain = $sslCompetitiveUpgradeDomain;
            }
        }
        $ca->assign("selectedDomain", $selectedDomain);
        $ca->assign("customDomain", $customDomain);
        $ca->assign("requireDomain", $requireDomain);
        $ca->setTemplate("store/order");
        $ca->skipMainBodyContainer();
        return $ca;
    }

    public function login(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = $this->order($request);
        if ($ca instanceof \Laminas\Diactoros\Response\RedirectResponse) {
            return $ca;
        }
        if ($ca->isLoggedIn()) {
            return new \Laminas\Diactoros\Response\RedirectResponse(routePath("cart-order"));
        }
        $ca->requireLogin();
    }

    public function addToCart(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.default");
        $redirectPath = "";
        $pid = $request->request()->get("pid");
        $billingcycle = $request->request()->get("billingcycle");
        $domain_type = $request->request()->get("domain_type");
        $existing_domain = $request->request()->get("existing_domain");
        $sub_domain = $request->request()->get("sub_domain");
        $existing_sld_for_subdomain = $request->request()->get("existing_sld_for_subdomain");
        $custom_domain = $request->request()->get("custom_domain");
        $continue = $request->request()->get("continue");
        $checkout = $request->request()->get("checkout");
        $ca = new Output\ClientArea();
        $product = \WHMCS\Product\Product::findOrFail($pid);
        $configOption1 = $product->moduleConfigOption1;
        $addAsProduct = false;
        $addAsAddon = false;
        $addonParentId = NULL;
        $domain = NULL;
        $addonModel = NULL;
        $availablePids = [];
        if (in_array($domain_type, ["existing-domain", "sub-domain"])) {
            $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $configOption1)->get()->where("productAddon.module", "marketconnect")->first();
            $addonModel = $addonModel->productAddon;
            $availablePids = $addonModel->packages;
        }
        if ($domain_type == "existing-domain" && $existing_domain) {
            $domains = $domainRegistrations = [];
            if (\Auth::client()) {
                $domains = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->where("domain", "!=", "")->whereIn("packageid", $availablePids)->where("domainstatus", "Active")->pluck("id", "domain");
                $domainRegistrations = \WHMCS\Domain\Domain::where("userid", \Auth::client()->id)->where("domain", "!=", "")->where("status", "Active")->pluck("domain");
            }
            if ($domains->has($existing_domain)) {
                $addAsAddon = true;
                $addonParentId = $domains[$existing_domain];
                $domain = $existing_domain;
            } else {
                if ($domainRegistrations->contains($existing_domain)) {
                    $addAsProduct = true;
                    $domain = $existing_domain;
                }
            }
        } else {
            if ($domain_type == "sub-domain" && $sub_domain && $existing_sld_for_subdomain) {
                $fullDomainName = $sub_domain . "." . $existing_sld_for_subdomain;
                $domains = [];
                if (\Auth::client()) {
                    $domains = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->where("domain", "!=", "")->whereIn("packageid", $availablePids)->where("domainstatus", "Active")->pluck("id", "domain");
                }
                if ($domains->has($fullDomainName)) {
                    $addAsAddon = true;
                    $addonParentId = $domains[$fullDomainName];
                    $domain = $fullDomainName;
                } else {
                    $addAsProduct = true;
                    $domain = $fullDomainName;
                }
            } else {
                if ($custom_domain) {
                    $addAsProduct = true;
                    $domain = $custom_domain;
                } else {
                    $promotionalHelper = MarketConnect::factoryPromotionalHelperByProductKey($product->productKey);
                    if (!$promotionalHelper->requiresDomain()) {
                        $addAsProduct = true;
                    }
                }
            }
        }
        $extra = [];
        if (!is_null($domain)) {
            if (!$this->validateDomain($domain)) {
                return new \Laminas\Diactoros\Response\RedirectResponse(routePath("cart-order"));
            }
            if ($domain == \WHMCS\Session::get("competitiveUpgradeDomain")) {
                $extra["sslCompetitiveUpgrade"] = true;
            }
        }
        if (\Auth::client()) {
            $mcType = $product->getMarketConnectType();
            if ($mcType) {
                $productIds = \WHMCS\Product\Product::$mcType()->pluck("id");
                $service = \Auth::client()->services()->where("domain", $domain)->whereIn("packageid", $productIds->toArray())->where("domainstatus", \WHMCS\Service\Status::ACTIVE)->first();
                if ($service) {
                    return new \Laminas\Diactoros\Response\RedirectResponse(routePath("upgrade-redirect", $service->id, 1));
                }
                $addonIds = \WHMCS\Product\Addon::$mcType()->pluck("id");
                $addons = \Auth::client()->addons()->whereIn("addonid", $addonIds->toArray())->where("status", \WHMCS\Service\Status::ACTIVE)->get();
                foreach ($addons as $addon) {
                    if ($addon->domain === $domain) {
                        return new \Laminas\Diactoros\Response\RedirectResponse(routePath("upgrade-redirect", $addon->id));
                    }
                }
            }
        }
        if ($addAsAddon) {
            if (is_null($addonModel)) {
                $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $configOption1)->get()->where("productAddon.module", "marketconnect")->first();
                $addonModel = $addonModel->productAddon;
            }
            if ($addonModel instanceof \WHMCS\Product\Addon) {
                $extra["qty"] = 1;
                $extra["allowsQuantity"] = $addonModel->allowMultipleQuantities === 2 ? 2 : 0;
                \WHMCS\OrderForm::addAddonToCart($addonModel->id, $addonParentId, $billingcycle, $extra);
            }
        } else {
            if ($addAsProduct) {
                $extra["qty"] = 1;
                $extra["allowsQuantity"] = $product->allowMultipleQuantities === 2 ? 2 : 0;
                \WHMCS\OrderForm::addProductToCart($product->id, $billingcycle, $domain, $extra);
            } else {
                $redirectPath = routePath("cart-order");
            }
        }
        if (!$redirectPath) {
            if ($checkout) {
                $redirectPath = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=view";
            } else {
                $redirectPath = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php";
            }
        }
        return new \Laminas\Diactoros\Response\RedirectResponse($redirectPath);
    }

    private function validateDomain($domain)
    {
        $domainParts = explode(".", $domain, 2);
        list($sld, $tld) = $domainParts;
        try {
            if (count($domainParts) == 2 && $sld != "" && $tld != "" && \WHMCS\Domains\Domain::isValidDomainName($sld, $tld, true)) {
                return true;
            }
        } catch (\WHMCS\Exception\Domains\UniqueDomainRequired $e) {
            return true;
        } catch (\WHMCS\Exception $e) {
            return false;
        }
        return false;
    }

    public function validate(\WHMCS\Http\Message\ServerRequest $request)
    {
        $domain = $request->request()->get("domain");
        $valid = $this->validateDomain($domain);
        return new \WHMCS\Http\Message\JsonResponse(["valid" => $valid]);
    }
}
