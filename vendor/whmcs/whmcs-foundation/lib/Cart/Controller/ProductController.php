<?php

namespace WHMCS\Cart\Controller;

class ProductController extends AbstractController
{
    const ADDON_SLUGS = ["wp-toolkit-deluxe" => ["template" => "wp-toolkit-cpanel", "friendlyName" => "WP Toolkit", "featureName" => "wp-toolkit-deluxe", "languageKey" => "wptk"], "plesk-wordpress-toolkit-with-smart-updates" => ["template" => "wp-toolkit-plesk", "friendlyName" => "WP Toolkit", "featureName" => "Plesk WordPress Toolkit with Smart Updates", "languageKey" => "wptk"]];

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $productGroup = \WHMCS\Product\Group::notHidden()->sorted()->first();
        if ($productGroup) {
            return new \WHMCS\Http\RedirectResponse($productGroup->getRoutePath());
        }
        return $this->render("products", ["errormessage" => \Lang::trans("orderForm.errorNoProductGroup")]);
    }

    public function addAddonsToCart(\WHMCS\Http\Message\ServerRequest $request)
    {
        $desiredServiceToAddonMap = json_decode(\WHMCS\Input\Sanitize::decode($request->request()->get("servicemap")), true);
        $client = \Auth::client();
        $response = new \WHMCS\Http\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=view");
        if (!is_array($desiredServiceToAddonMap) || !$client) {
            return $response;
        }
        $this->trackClick("cart", "wptk");
        $ownedActiveServices = \WHMCS\Service\Service::whereIn("id", array_keys($desiredServiceToAddonMap))->where("userid", $client->id)->where("domainstatus", \WHMCS\Domain\Registrar\Domain::STATUS_ACTIVE)->get();
        $validAddons = [];
        \WHMCS\Product\Addon::whereIn("id", array_values($desiredServiceToAddonMap))->get()->map(function (\WHMCS\Product\Addon $addon) use($validAddons) {
            $validAddons[$addon->id] = $addon;
        });
        foreach ($ownedActiveServices as $service) {
            if (isset($desiredServiceToAddonMap[$service->id])) {
                $desiredAddonId = $desiredServiceToAddonMap[$service->id];
                $addon = $validAddons[$desiredAddonId] ?? NULL;
                if ($addon) {
                    if (in_array($service->product->id, $addon->packages)) {
                        \WHMCS\OrderForm::addAddonToCart($addon->id, $service->id, "", ["qty" => 1, "allowsQuantity" => $addon->allowMultipleQuantities === \WHMCS\Cart\CartCalculator::QUANTITY_SCALING ? $addon->allowMultipleQuantities : 0]);
                    }
                }
            }
        }
        return $response;
    }

    public function stageAddonForCart(\WHMCS\Http\Message\ServerRequest $request)
    {
        $addon = \WHMCS\Product\Addon::find($request->getAttribute("addonId"));
        $redirectUrl = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=view";
        if ($addon) {
            $_SESSION["cart"]["passedvariables"]["addons"][] = ["addonid" => $addon->id, "qty" => 1];
            if (0 < count($addon->packages)) {
                $product = \WHMCS\Product\Product::find($addon->packages[0]);
                if ($product) {
                    $redirectUrl = $product->productGroup->getRoutePath();
                }
            }
        }
        return new \WHMCS\Http\RedirectResponse($redirectUrl);
    }

    public function addon(\WHMCS\Http\Message\ServerRequest $request)
    {
        $addonSlug = $request->getAttribute("addonSlug");
        if (!isset(self::ADDON_SLUGS[$addonSlug])) {
            return new \WHMCS\Http\RedirectResponse("clientarea.php");
        }
        $this->trackClick("landing", "wptk");
        $slugData = self::ADDON_SLUGS[$addonSlug];
        $ca = new \WHMCS\ClientArea();
        $ca->skipMainBodyContainer();
        $ca->assign("loggedIn", (bool) \Auth::user());
        $ca->assign("productName", $slugData["friendlyName"]);
        $ca->assign("addonSlug", $addonSlug);
        $ca->assign("serviceId", $request->get("serviceId"));
        $ca->setTemplate("store/addon/" . $slugData["template"]);
        $currency = \WHMCS\Billing\Currency::factoryForClientArea();
        $ca->assign("activeCurrency", $currency);
        $translatedTitle = \Lang::trans("store.addon." . $slugData["languageKey"] . ".title");
        $ca->setPageTitle($translatedTitle);
        $matchingModuleConfigurations = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->whereIn("value", array_column(self::ADDON_SLUGS, "featureName"))->get();
        $mappedAddonsArray = $activeAddons = [];
        foreach (self::ADDON_SLUGS as $key => $value) {
            $mappedAddons = $matchingModuleConfigurations->where("value", $value["featureName"])->map(function (\WHMCS\Config\Module\ModuleConfiguration $moduleConfig) {
                return $moduleConfig->productAddon;
            });
            $mappedAddonsArray[$value["featureName"]] = $mappedAddons;
            if ($mappedAddons->count() !== 0) {
                $activeAddons[] = $key;
            }
        }
        $matchingAddons = $mappedAddonsArray[$slugData["featureName"]];
        $notFoundTemplate = "store/not-found";
        if ($matchingAddons->count() === 0) {
            $ca->setTemplate($notFoundTemplate);
            return $ca;
        }
        $firstMatchingAddon = $matchingAddons->first();
        $ca->assign("firstMatchingAddon", $firstMatchingAddon);
        $browsePackagesAction = routePath("store");
        if ($firstMatchingAddon) {
            $browsePackagesAction = routePath("store-stage-addon", $firstMatchingAddon->id);
        }
        $ca->assign("browsePackagesAction", $browsePackagesAction);
        $ca->assign("hasCpanelWptk", in_array("wp-toolkit-deluxe", $activeAddons));
        $ca->assign("hasPleskWptk", in_array("plesk-wordpress-toolkit-with-smart-updates", $activeAddons));
        $user = \Auth::user();
        $client = \Auth::client();
        if (!$user || !$client) {
            return $ca;
        }
        $ssoContextData = \WHMCS\Session::get("ssoContextData");
        $ssoServiceId = $ssoContextData["serviceId"] ?? $request->get("serviceId", NULL);
        $ssoService = NULL;
        $clientServices = [];
        $services = $client->services->filter(function (\WHMCS\Service\Service $service) {
            return in_array($service->product->type, ["hostingaccount", "reselleraccount"]);
        })->sort(function (\WHMCS\Service\Service $item1, \WHMCS\Service\Service $item2) {
            if (empty($item1->domain)) {
                return PHP_INT_MAX;
            }
            return strcasecmp($item1->domain, $item2->domain);
        })->take(500);
        foreach ($services as $service) {
            if ($service->id == $ssoServiceId) {
                $ssoService = $service;
            }
            $clientServices[$service->id] = ["service" => $service, "addon" => NULL];
            foreach ($matchingAddons as $addon) {
                if (in_array($service->product->id, $addon->packages) && !empty($service->domain)) {
                    $clientServices[$service->id]["addon"] = $addon;
                    $clientServices[$service->id]["addonPrice"] = $addon->pricing()->first();
                    if ($service->billingCycle && $addon->billingCycle == "recurring") {
                        $matchingAddonPrice = $addon->pricing()->byCycle($service->billingCycle);
                        if ($matchingAddonPrice) {
                            $clientServices[$service->id]["addonPrice"] = $matchingAddonPrice;
                        }
                    }
                }
            }
        }
        $ca->assign("clientServices", $clientServices);
        $ca->assign("ssoService", $ssoService);
        return $ca;
    }

    public function loginAndRedirectToAddonPage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $addonSlug = $request->getAttribute("addonSlug");
        $serviceId = $request->getAttribute("serviceId");
        $params = [$addonSlug];
        if ($serviceId) {
            $params[] = $serviceId;
        }
        $redirectUri = routePath("store-addon", ...$params);
        \WHMCS\Authentication\LoginHandler::setReturnUri($redirectUri);
        \App::redirectToRoutePath("login-index");
    }

    public function showGroup(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $productGroupSlug = $request->attributes()->get("product_group_slug");
            $subPageName = $request->attributes()->get("sub_page_name");
            if ($subPageName) {
                switch ($subPageName) {
                    case \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_DV:
                    case \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_EV:
                    case \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_OV:
                    case \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_WILDCARD:
                        $method = "view" . ucfirst($subPageName);
                        return (new \WHMCS\MarketConnect\SslController())->{$method}($request);
                        break;
                }
            }
            $productGroup = \WHMCS\Product\Group::slug($productGroupSlug)->first();
            if (is_null($productGroup) || $productGroup->isHidden && !$productGroup->isMarketConnectGroup()) {
                if (is_numeric($productGroupSlug)) {
                    $productGroup = \WHMCS\Product\Group::find($productGroupSlug);
                } else {
                    if (in_array($productGroupSlug, \WHMCS\MarketConnect\MarketConnect::SERVICE_SLUGS) && $request->get("preview", "0") === "1") {
                        $groupName = array_search($productGroupSlug, \WHMCS\MarketConnect\MarketConnect::SERVICE_SLUGS);
                        $service = array_search($groupName, \WHMCS\MarketConnect\MarketConnect::SERVICE_GROUP_NAMES);
                        if ($service === \WHMCS\MarketConnect\MarketConnect::SERVICE_SYMANTEC) {
                            $service = \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_RAPIDSSL;
                        }
                        $mcClass = \WHMCS\MarketConnect\MarketConnect::$controllers[$service];
                        return (new $mcClass())->index($request);
                    }
                }
                if (is_null($productGroup)) {
                    $productSlug = \WHMCS\Product\Product\Slug::where("group_slug", $productGroupSlug)->first();
                    if ($productSlug) {
                        $productGroup = \WHMCS\Product\Group::find($productSlug->groupId);
                    }
                    if (is_null($productGroup)) {
                        $productGroup = \WHMCS\Product\Group::notHidden()->sorted()->first();
                    }
                    if (is_null($productGroup)) {
                        throw new \WHMCS\Exception(\Lang::trans("orderForm.errorNoProductGroup"));
                    }
                    return new \WHMCS\Http\RedirectResponse($productGroup->getRoutePath());
                }
            } else {
                if ($productGroup->isMarketConnectGroup()) {
                    $mcClass = $productGroup->getMarketConnectControllerClass();
                    return (new $mcClass())->index($request);
                }
            }
            $pid = is_numeric($subPageName) ? $subPageName : NULL;
            $orderFormTemplateName = "";
            if ($request->has("carttpl") && $request->get("carttpl")) {
                $requestedOrderForm = \WHMCS\View\Template\OrderForm::find($request->get("carttpl"));
                if ($requestedOrderForm && (new \WHMCS\View\Template\CompatUtil())->isCompat($requestedOrderForm, \App::getClientAreaTemplate()) === true) {
                    $orderFormTemplateName = $requestedOrderForm->getName();
                }
            }
            $runtimeOrderForm = \WHMCS\View\Template\OrderForm::factory();
            if (!$orderFormTemplateName && $productGroup->orderFormTemplate) {
                $orderFormTemplateName = $productGroup->orderFormTemplate;
                $orderFormTemplate = \WHMCS\View\Template\OrderForm::find($orderFormTemplateName);
                if ($orderFormTemplate && $orderFormTemplate->getName() !== $runtimeOrderForm->getName() && (new \WHMCS\View\Template\CompatUtil())->isCompat($orderFormTemplate, \App::getClientAreaTemplate()) !== true) {
                    $orderFormTemplateName = $runtimeOrderForm->getName();
                }
            }
            $orderfrm = new \WHMCS\OrderForm();
            $errorMessage = "";
            try {
                $products = $orderfrm->getProducts($productGroup, true, true);
            } catch (\WHMCS\Exception $e) {
                $products = [];
                if (!($e->getMessage() === "NoProductGroup" && \WHMCS\MarketConnect\MarketConnect::hasActiveServices())) {
                    $errorMessage = \Lang::trans("orderForm.error" . $e->getMessage());
                }
            }
            $templateVars = ["gid" => $productGroup->id, "pid" => $pid, "groupname" => $productGroup->name, "productGroup" => $productGroup, "productgroups" => $orderfrm->getProductGroups(), "products" => $products, "productscount" => count($products), "featurePercentages" => $orderfrm->getFeaturePercentages($products), "errormessage" => $errorMessage, "registerdomainenabled" => (bool) \WHMCS\Config\Setting::getValue("AllowRegister"), "transferdomainenabled" => (bool) \WHMCS\Config\Setting::getValue("AllowTransfer"), "renewalsenabled" => (bool) \WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders")];
            if ($orderFormTemplateName) {
                $templateVars["carttpl"] = $orderFormTemplateName;
            }
            $templateVars["productRecommendations"] = $templateVars["productRecommendations"] ?? NULL;
            $templateVars["action"] = $templateVars["action"] ?? NULL;
            $templateVars["currency"] = $templateVars["currency"] ?? \WHMCS\Billing\Currency::factoryForClientArea();
            return $this->render("products", $templateVars);
        } catch (\Exception $e) {
            $templateVars = ["errormessage" => $e->getMessage()];
            return $this->render("products", $templateVars);
        }
    }

    protected function trackClick($clickType, $addon)
    {
        $date = \WHMCS\Carbon::today();
        $currentClicks = json_decode(\WHMCS\Config\Setting::getValue("LandingPages") ?? "{}", true);
        if (empty($currentClicks) || !is_array($currentClicks)) {
            $currentClicks = [];
        }
        $wptkClicks = !empty($currentClicks[$addon]) ? $currentClicks[$addon] : [];
        if (empty($wptkClicks[$clickType])) {
            $wptkClicks[$clickType] = ["lifetime" => 0, $date->toDateString() => 0];
        }
        if (empty($wptkClicks[$clickType]["lifetime"])) {
            $wptkClicks[$clickType]["lifetime"] = 0;
        }
        if (empty($wptkClicks[$clickType][(string) $date->toDateString()])) {
            $wptkClicks[$clickType][(string) $date->toDateString()] = 0;
        }
        $wptkClicks[$clickType]["lifetime"] += 1;
        $wptkClicks[$clickType][(string) $date->toDateString()] += 1;
        $currentClicks[$addon] = $wptkClicks;
        \WHMCS\Config\Setting::setValue("LandingPages", json_encode($currentClicks));
    }

    public function showProduct(\WHMCS\Http\Message\ServerRequest $request)
    {
        global $currency;
        $currency = \WHMCS\Billing\Currency::factoryForClientArea();
        \App::load_function("cart");
        \App::load_function("customfield");
        \App::load_function("domain");
        $productSlug = $request->get("product_slug");
        $groupSlug = $request->get("product_group_slug");
        $slug = \WHMCS\Product\Product\Slug::where("slug", $productSlug)->where("group_slug", $groupSlug)->first();
        if (!$slug) {
            return new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php");
        }
        $slug->clicks++;
        $slug->save();
        $tracking = $slug->tracking()->firstOrCreate(["date" => \WHMCS\Carbon::today()]);
        $tracking->clicks++;
        $tracking->save();
        $productModel = $slug->product()->withCount("recommendations")->first();
        if (!is_null($productModel) && is_null($productModel->pricing()->first())) {
            return new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php?a=view");
        }
        $orderForm = new \WHMCS\OrderForm();
        $orderFormTemplate = \WHMCS\View\Template\OrderForm::factory();
        $orderFormTemplateName = $orderFormTemplate->getName();
        $templateFile = "configureproductdomain";
        $productInfo = $orderForm->setPid($productModel->id, $productModel);
        if (!$productInfo) {
            return new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php");
        }
        if ($productInfo["orderfrmtpl"] != "") {
            $productOrderForm = \WHMCS\View\Template\OrderForm::find($productInfo["orderfrmtpl"]);
            if ($productOrderForm) {
                $runtimeOrderFormTemplate = \WHMCS\View\Template\OrderForm::factory();
                if ($runtimeOrderFormTemplate && $productOrderForm->getName() != $runtimeOrderFormTemplate->getName() && (new \WHMCS\View\Template\CompatUtil())->isCompat($productOrderForm, \App::getClientAreaTemplate())) {
                    $orderFormTemplateName = $productOrderForm->getName();
                }
            }
        }
        $ajax = $request->get("ajax");
        $addProductAjax = $request->get("addproductajax");
        $pid = $productInfo["pid"];
        $subdomains = $productInfo["subdomain"];
        $freeDomain = $productInfo["freedomain"];
        $freeDomainTlds = $productInfo["freedomaintlds"];
        $stockControl = $productInfo["stockcontrol"];
        $qty = $productInfo["qty"];
        $module = $productInfo["module"];
        $errorMessage = "";
        $_SESSION["cart"]["domainoptionspid"] = $pid;
        if ($request->has("promocode")) {
            \App::load_function("order");
            SetPromoCode($request->get("promocode"));
        }
        if ($stockControl && $qty <= 0) {
            return $this->render("error", ["carttpl" => $orderFormTemplateName, "errortitle" => \Lang::trans("outofstock"), "errormsg" => \Lang::trans("outofstockdescription"), "ajax" => (bool) $ajax]);
        }
        $passedVariables = $_SESSION["cart"]["passedvariables"] ?? NULL;
        $bundle = false;
        if (is_array($passedVariables) && (isset($passedVariables["bnum"]) || isset($passedVariables["bitem"]))) {
            $bundle = true;
            $passedVariables = [];
        }
        if ($module == "marketconnect" && !$bundle) {
            return new \WHMCS\Http\RedirectResponse(routePathWithQuery("cart-order", [], ["pid" => $pid]));
        }
        if (!isset($passedVariables)) {
            $passedVariables = [];
        }
        $skipConfig = $request->get("skipconfig");
        $billingCycle = $request->get("billingcycle");
        $configOption = $request->get("configoption");
        $customField = $request->get("customfield");
        $addons = $request->get("addons");
        $domains = $request->get("domains");
        $tld = $request->get("tld");
        $sld = $request->get("sld");
        $inCartDomain = $request->get("incartdomain");
        if ($skipConfig) {
            $passedVariables["skipconfig"] = $skipConfig;
        }
        if ($billingCycle) {
            $passedVariables["billingcycle"] = $billingCycle;
        }
        if ($configOption) {
            $passedVariables["configoption"] = $configOption;
        }
        if ($customField) {
            $passedVariables["customfield"] = $customField;
        }
        if ($addons) {
            if (!is_array($addons)) {
                $addonsToAdd = explode(",", $addons);
                foreach ($addonsToAdd as $addonId) {
                    $passedVariables["addons"][] = ["addonid" => $addonId, "qty" => 1];
                }
            } else {
                foreach ($addons as $k => $v) {
                    $passedVariables["addons"][] = ["addonid" => (int) trim($k), "qty" => 1];
                }
            }
        }
        $customFields = getCustomFields("product", $productInfo["pid"], "", true);
        foreach ($customFields as $customField) {
            $cfValue = $request->get("cf_" . $customField["textid"]);
            if ($cfValue) {
                $passedVariables["customfield"][$customField["id"]] = $cfValue;
            }
        }
        if (count($passedVariables)) {
            $_SESSION["cart"]["passedvariables"] = $passedVariables;
        }
        $domainSelect = $request->get("domainselect");
        $domainOption = $request->get("domainoption");
        if ($domainSelect && !$domains && $ajax && in_array($domainOption, \WHMCS\OrderForm::DOMAIN_REGISTER_OR_TRANSFER)) {
            return (new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php"))->withError(\Lang::trans("domains.nodomains"));
        }
        $templateVars = ["productinfo" => $productInfo, "pid" => $pid];
        $productConfig = false;
        if ($orderForm->getProductInfo("showdomainoptions") && !$domains) {
            $templateVars["idnLanguages"] = \WHMCS\Domains\Idna::getLanguages();
            $cartProducts = $orderForm->getCartDataByKey("products");
            $cartDomains = $orderForm->getCartDataByKey("domains");
            $inCartDomains = [];
            if ($cartDomains) {
                foreach ($cartDomains as $cartDomain) {
                    $domainName = $cartDomain["domain"];
                    if ($cartProducts) {
                        foreach ($cartProducts as $cartproduct) {
                            if ($cartproduct["domain"] == $domainName) {
                                $domainName = "";
                            }
                        }
                    }
                    if ($domainName) {
                        $inCartDomains[] = $domainName;
                    }
                }
            }
            if (!in_array($domainOption, \WHMCS\OrderForm::DOMAIN_ALL)) {
                $domainOption = "";
            }
            if ($inCartDomains && !$domainOption) {
                $domainOption = \WHMCS\OrderForm::TYPE_DOMAIN_INCART;
            }
            if (\WHMCS\Config\Setting::getValue("AllowRegister") && !$domainOption) {
                $domainOption = \WHMCS\OrderForm::TYPE_DOMAIN_REGISTER;
            }
            if (\WHMCS\Config\Setting::getValue("AllowTransfer") && !$domainOption) {
                $domainOption = \WHMCS\OrderForm::TYPE_DOMAIN_TRANSFER;
            }
            if (\WHMCS\Config\Setting::getValue("AllowOwnDomain") && !$domainOption) {
                $domainOption = \WHMCS\OrderForm::TYPE_DOMAIN_OWN;
            }
            if (count($subdomains) && !$domainOption) {
                $domainOption = \WHMCS\OrderForm::TYPE_DOMAIN_SUB;
            }
            $registerTlds = getTLDList();
            $transferTlds = getTLDList("transfer");
            $templateVars["listtld"] = $registerTlds;
            $templateVars["registertlds"] = $registerTlds;
            $templateVars["transfertlds"] = $transferTlds;
            $templateVars["showdomainoptions"] = true;
            $templateVars["domainoption"] = $domainOption;
            $templateVars["registerdomainenabled"] = \WHMCS\Config\Setting::getValue("AllowRegister");
            $templateVars["transferdomainenabled"] = \WHMCS\Config\Setting::getValue("AllowTransfer");
            $templateVars["owndomainenabled"] = \WHMCS\Config\Setting::getValue("AllowOwnDomain");
            $templateVars["subdomain"] = $subdomains[0] ?? "";
            $templateVars["subdomains"] = $subdomains;
            $templateVars["incartdomains"] = $inCartDomains;
            $templateVars["availabilityresults"] = [];
            $templateVars["freedomaintlds"] = $freeDomain && !empty($freeDomainTlds) ? implode(", ", $freeDomainTlds) : "";
            $templateVars["spotlightTlds"] = getSpotlightTldsWithPricing();
            if (is_array($tld)) {
                if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_REGISTER) {
                    $tld = $tld[0];
                    $sld = $sld[0];
                } else {
                    if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_TRANSFER) {
                        $tld = $tld[1];
                        $sld = $sld[1];
                    } else {
                        if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_OWN) {
                            $tld = $tld[2];
                            $sld = $sld[2];
                        } else {
                            if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_SUB) {
                                if (!$subdomains[$tld[3]]) {
                                    $tld[3] = 0;
                                }
                                $tld = $subdomains[$tld[3]];
                                $sld = $sld[3];
                            } else {
                                if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_INCART) {
                                    $inCartDomain = explode(".", $inCartDomain, 2);
                                    list($sld, $tld) = $inCartDomain;
                                }
                            }
                        }
                    }
                }
            }
            $noContinue = false;
            if (!$sld && !$tld && isset($_SESSION["cartdomain"]["sld"]) && isset($_SESSION["cartdomain"]["tld"]) && in_array($_SESSION["cartdomain"]["tld"], $registerTlds)) {
                $sld = $_SESSION["cartdomain"]["sld"];
                $tld = $_SESSION["cartdomain"]["tld"];
                $noContinue = true;
                unset($_SESSION["cartdomain"]);
            }
            $sld = cleanDomainInput($sld);
            $tld = cleanDomainInput($tld);
            if (substr($sld, -1) == ".") {
                $sld = substr($sld, 0, -1);
            }
            $isRegister = $domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_REGISTER;
            $isTransfer = $domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_TRANSFER;
            if ($sld && $tld && ($isRegister && !in_array($tld, $registerTlds) || $isTransfer && !in_array($tld, $transferTlds))) {
                $sld = "";
                $tld = "";
            }
            if ($tld && substr($tld, 0, 1) != ".") {
                $tld = "." . $tld;
            }
            $templateVars["sld"] = $sld;
            $templateVars["tld"] = $tld;
            if ($request->has($sld) || $request->has("tld") || $sld) {
                $validate = new \WHMCS\Validate();
                if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_SUB) {
                    if (empty($BannedSubdomainPrefixes) || !is_array($BannedSubdomainPrefixes)) {
                        $BannedSubdomainPrefixes = [];
                    }
                    if (\WHMCS\Config\Setting::getValue("BannedSubdomainPrefixes")) {
                        $bannedPrefixes = \WHMCS\Config\Setting::getValue("BannedSubdomainPrefixes");
                        $bannedPrefixes = explode(",", $bannedPrefixes);
                        $BannedSubdomainPrefixes = array_merge($BannedSubdomainPrefixes, $bannedPrefixes);
                    }
                    if (!\WHMCS\Domains\Domain::isValidDomainName($sld, ".com")) {
                        $errorMessage .= "<li>" . \Lang::trans("ordererrordomaininvalid");
                    } else {
                        if (in_array($sld, $BannedSubdomainPrefixes)) {
                            $errorMessage .= "<li>" . \Lang::trans("ordererrorsbudomainbanned");
                        } else {
                            $subChecks = \WHMCS\Service\Service::where("domain", $sld . $tld)->whereNotIn("domainstatus", [\WHMCS\Utility\Status::CANCELLED, \WHMCS\Utility\Status::FRAUD, \WHMCS\Utility\Status::TERMINATED])->count();
                            if ($subChecks) {
                                $errorMessage = "<li>" . \Lang::trans("ordererrorsubdomaintaken");
                            }
                        }
                    }
                    run_validate_hook($validate, "CartSubdomainValidation", ["subdomain" => $sld, "domain" => $tld]);
                } else {
                    $allowDomainsTwice = \WHMCS\Config\Setting::getValue("AllowDomainsTwice");
                    if (!\WHMCS\Domains\Domain::isValidDomainName($sld, $tld) || $domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_OWN && !\WHMCS\Domains\Domain::isSupportedTld($tld)) {
                        $errorMessage .= \Lang::trans("ordererrordomaininvalid");
                    }
                    if (in_array($domainOption, \WHMCS\OrderForm::DOMAIN_REGISTER_OR_TRANSFER) && $allowDomainsTwice) {
                        if (substr($tld, 0, 1) != ".") {
                            $tld = "." . $tld;
                        }
                        $domainObject = new \WHMCS\Domains\Domain($sld . $tld);
                        if (cartCheckIfDomainAlreadyOrdered($domainObject)) {
                            $errorMessage = "<li>" . \Lang::trans("ordererrordomainalreadyexists");
                        }
                    } else {
                        if ($domainOption == \WHMCS\OrderForm::TYPE_DOMAIN_OWN && $allowDomainsTwice) {
                            $existsCheck = \WHMCS\Service\Service::where("domain", $sld . $tld)->whereNotIn("domainstatus", [\WHMCS\Utility\Status::CANCELLED, \WHMCS\Utility\Status::FRAUD, \WHMCS\Utility\Status::TERMINATED])->pluck("domain");
                            if ($existsCheck->containsStrict($sld . $tld)) {
                                $errorMessage = "<li>" . \Lang::trans("ordererrordomainalreadyexists");
                            }
                        }
                    }
                    run_validate_hook($validate, "ShoppingCartValidateDomain", ["domainoption" => $domainOption, "sld" => $sld, "tld" => $tld]);
                }
                if ($validate->hasErrors()) {
                    $errorMessage .= $validate->getHTMLErrorOutput();
                }
                $templateVars["errormessage"] = $errorMessage;
            }
            if (!$errorMessage && !$noContinue) {
                if (in_array($domainOption, \WHMCS\OrderForm::DOMAIN_REGISTER_OR_TRANSFER) && $sld && $tld) {
                    $check = new \WHMCS\Domain\Checker();
                    $check->cartDomainCheck(new \WHMCS\Domains\Domain($sld), [$tld]);
                    $check->populateCartWithDomainSmartyVariables($domainOption, $templateVars);
                    $templateVars["domains"] = $domains;
                }
                if (!in_array($domainOption, \WHMCS\OrderForm::DOMAIN_REGISTER_OR_TRANSFER) && $sld && $tld) {
                    $templateVars["showdomainoptions"] = false;
                    $domains = [$sld . $tld];
                    $productConfig = true;
                }
            }
        } else {
            $productConfig = true;
        }
        if ($productConfig) {
            $passedVariables = $_SESSION["cart"]["passedvariables"] ?? NULL;
            unset($_SESSION["cart"]["passedvariables"]);
            \WHMCS\OrderForm::cartPreventDuplicateProduct((int) $pid, (string) ($domains[0] ?? NULL));
            $productArray = ["pid" => $pid, "domain" => $domains[0] ?? NULL, "billingcycle" => $passedVariables["billingcycle"] ?? NULL, "configoptions" => $passedVariables["configoption"] ?? NULL, "customfields" => $passedVariables["customfield"] ?? NULL, "addons" => $passedVariables["addons"] ?? NULL, "server" => "", "noconfig" => true];
            if (isset($passedVariables["bnum"])) {
                $productArray["bnum"] = $passedVariables["bnum"];
            }
            if (isset($passedVariables["bitem"])) {
                $productArray["bitem"] = $passedVariables["bitem"];
            }
            $updatedExistingQuantity = false;
            if ($productInfo["allowqty"] && !empty($_SESSION["cart"]["products"]) && is_array($_SESSION["cart"]["products"])) {
                foreach ($_SESSION["cart"]["products"] as &$cart_prod) {
                    if ($pid == $cart_prod["pid"]) {
                        if (empty($cart_prod["qty"])) {
                            $cart_prod["qty"] = 1;
                        }
                        if (empty($cart_prod["noconfig"])) {
                            $cart_prod["qty"]++;
                            if ($stockControl && $qty < $cart_prod["qty"]) {
                                $cart_prod["qty"] = $qty;
                            }
                            $updatedExistingQuantity = true;
                        }
                        unset($cart_prod);
                    }
                }
            }
            if (!$updatedExistingQuantity) {
                $_SESSION["cart"]["products"][] = $productArray;
            }
            $newProductNumber = count($_SESSION["cart"]["products"]) - 1;
            if ($_SESSION["cart"]["products"][$newProductNumber]["pid"] != $pid) {
                $newProductNumber = 0;
                $index = count($_SESSION["cart"]["products"]);
                while (0 < $index) {
                    $product = $_SESSION["cart"]["products"][--$index];
                    if ($product["pid"] == $pid) {
                        $newProductNumber = $index;
                    }
                }
            }
            if (in_array($domainOption, \WHMCS\OrderForm::DOMAIN_REGISTER_OR_TRANSFER)) {
                $domainsRegPeriod = $request->get("domainsregperiod");
                $registrationPeriods = $request->get("regperiods");
                foreach ($domains as $domainName) {
                    \WHMCS\OrderForm::cartPreventDuplicateDomain($domainName);
                    $registrationPeriod = $domainsRegPeriod[$domainName];
                    $domainParts = explode(".", $domainName, 2);
                    $tempPriceList = getTLDPriceList("." . $domainParts[1]);
                    if (!isset($tempPriceList[$registrationPeriod][$domainOption])) {
                        if (!empty($registrationPeriods) && is_array($registrationPeriods)) {
                            foreach ($registrationPeriods as $period) {
                                if (substr($period, 0, strlen($domainName)) == $domainName) {
                                    $registrationPeriod = substr($period, strlen($domainName));
                                }
                            }
                        }
                        if (!$registrationPeriod) {
                            $tldYears = array_keys($tempPriceList);
                            $registrationPeriod = $tldYears[0];
                        }
                    }
                    $domainArray = ["type" => $domainOption, "domain" => $domainName, "regperiod" => $registrationPeriod, "isPremium" => false, "idnLanguage" => $request->get("idnlanguage")];
                    if (isset($passedVariables["bnum"])) {
                        $domainArray["bnum"] = $passedVariables["bnum"];
                    }
                    if (isset($passedVariables["bitem"])) {
                        $domainArray["bitem"] = $passedVariables["bitem"];
                    }
                    $premiumData = \WHMCS\Session::get("PremiumDomains");
                    if (!is_array($premiumData)) {
                        $premiumData = [];
                    }
                    if ((int) \WHMCS\Config\Setting::getValue("PremiumDomains") && array_key_exists($domainName, $premiumData)) {
                        $premiumPrice = $premiumData[$domainName];
                        if (array_key_exists("register", $premiumPrice["cost"])) {
                            $domainArray["isPremium"] = true;
                            $domainArray["domainpriceoverride"] = $premiumPrice["markupPrice"][1]["register"];
                            $domainArray["registrarCostPrice"] = $premiumPrice["cost"]["register"];
                            $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                            $domainArray["domainpriceoverride"] = $domainArray["domainpriceoverride"]->toNumeric();
                        }
                        if (array_key_exists("renew", $premiumPrice["cost"])) {
                            $domainArray["domainrenewoverride"] = $premiumPrice["markupPrice"][1]["renew"];
                            $domainArray["registrarRenewalCostPrice"] = $premiumPrice["cost"]["renew"];
                            $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                            $domainArray["domainrenewoverride"] = $domainArray["domainrenewoverride"]->toNumeric();
                        } else {
                            $domainArray["isPremium"] = false;
                        }
                    }
                    $_SESSION["cart"]["domains"][] = $domainArray;
                }
            }
            $_SESSION["cart"]["newproduct"] = true;
            if ($ajax) {
                $ajax = "&ajax=1";
            } else {
                if ($addProductAjax) {
                    $ajax = "&addproductajax=1";
                } else {
                    if (isset($passedVariables["skipconfig"]) && $passedVariables["skipconfig"]) {
                        unset($_SESSION["cart"]["products"][$newProductNumber]["noconfig"]);
                        $_SESSION["cart"]["lastconfigured"] = ["type" => "product", "i" => $newProductNumber];
                        return new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php?a=view");
                    }
                }
            }
            return new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php?a=confproduct&i=" . $newProductNumber . $ajax);
        } else {
            if ($addProductAjax) {
                return new \WHMCS\Http\Message\JsonResponse(["sucess" => false]);
            }
            return $this->render($templateFile, $templateVars);
        }
    }
}
