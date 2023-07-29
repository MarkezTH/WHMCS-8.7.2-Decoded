<?php

namespace WHMCS\MarketConnect;

class MarketConnect
{
    public static $sidebarNameOverrides = NULL;
    protected static $promotionServices = NULL;
    public static $services = NULL;
    public static $controllers = NULL;
    public static $routeMap = NULL;
    const PRICING_TERM_FREE = 100;
    const MARKETCONNECT = "marketconnect";
    const SERVICE_SYMANTEC = "symantec";
    const SERVICE_WEEBLY = "weebly";
    const SERVICE_SPAMEXPERTS = "spamexperts";
    const SERVICE_SITEBUILDER = "sitebuilder";
    const SERVICE_SITELOCK = "sitelock";
    const SERVICE_SITELOCKVPN = "sitelockvpn";
    const SERVICE_NORDVPN = "nordvpn";
    const SERVICE_CODEGUARD = "codeguard";
    const SERVICE_MARKETGOO = "marketgoo";
    const SERVICE_OX = "ox";
    const SERVICE_XOVINOW = "xovinow";
    const SERVICE_THREESIXTYMONITORING = "threesixtymonitoring";
    const SERVICE_SYMANTEC_GROUP_NAME = "SSL Certificates";
    const SERVICE_WEEBLY_GROUP_NAME = "Weebly Website Builder";
    const SERVICE_SPAMEXPERTS_GROUP_NAME = "Email Spam Filtering";
    const SERVICE_SITEBUILDER_GROUP_NAME = "Site Builder";
    const SERVICE_SITELOCK_GROUP_NAME = "SiteLock";
    const SERVICE_SITELOCKVPN_GROUP_NAME = "VPN";
    const SERVICE_NORDVPN_GROUP_NAME = "NordVPN";
    const SERVICE_CODEGUARD_GROUP_NAME = "CodeGuard";
    const SERVICE_MARKETGOO_GROUP_NAME = "Marketgoo";
    const SERVICE_OX_GROUP_NAME = "OX App Suite";
    const SERVICE_XOVINOW_GROUP_NAME = "XOVI NOW";
    const SERVICE_THREESIXTYMONITORING_GROUP_NAME = "360 Monitoring";
    const SERVICE_SLUGS = NULL;
    const SERVICE_GROUP_NAMES = NULL;
    const SERVICES = NULL;
    const FTP_CUSTOM_FIELDS = [["fieldName" => "FTP Host", "fieldType" => "text", "adminOnly" => true, "sortOrder" => 1], ["fieldName" => "FTP Username", "fieldType" => "text", "adminOnly" => true, "sortOrder" => 2], ["fieldName" => "FTP Password", "fieldType" => "password", "adminOnly" => true, "sortOrder" => 3], ["fieldName" => "FTP Path", "fieldType" => "text", "adminOnly" => true, "sortOrder" => 4]];

    public static function getPromotionServices()
    {
        $feed = new ServicesFeed(true);
        $services = [];
        foreach (static::$promotionServices as $serviceKey => $class) {
            if (!(static::isSunset($serviceKey) && !static::isActive($serviceKey) || $feed->hasServiceData() && !$feed->isGroupIdInFeed($serviceKey))) {
                $services[$serviceKey] = static::SERVICES[$serviceKey];
            }
        }
        return $services;
    }

    public static function servicesInDeclarationOrder($services)
    {
        if (empty($services)) {
            return [];
        }
        $sorter = function ($Generator, $services) {
            foreach (static::serviceKeysInDeclarationOrder(array_keys($services)) as $serviceKey) {
                static::SERVICES[$serviceKey];
            }
        };
        return iterator_to_array($sorter($services));
    }

    public static function serviceKeysInDeclarationOrder($serviceKeys)
    {
        if (empty($serviceKeys)) {
            return [];
        }
        $sorter = function ($Generator, $serviceKeys) {
            foreach (static::SERVICES as $serviceKey => $service) {
                if (in_array($serviceKey, $serviceKeys)) {
                }
            }
        };
        return iterator_to_array($sorter($serviceKeys));
    }

    public static function getServicesToPromote()
    {
        $services = [];
        foreach (static::$promotionServices as $serviceKey => $class) {
            if (!static::isActive($serviceKey)) {
                if (!static::isSunset($serviceKey)) {
                    $services[$serviceKey] = static::SERVICES[$serviceKey];
                }
            }
        }
        return $services;
    }

    public static function getServices()
    {
        return array_keys(self::$services);
    }

    public static function hasActiveServices()
    {
        return 0 < count(static::getActiveServices());
    }

    public static function getActiveServices()
    {
        return static::serviceKeysInDeclarationOrder(Service::active()->pluck("name")->intersect(array_keys(static::$promotionServices))->toArray());
    }

    public static function getServicesStateMap($serviceKeys = [])
    {
        if (empty($serviceKeys)) {
            $serviceKeys = array_keys(static::$promotionServices);
        }
        $serviceKeys = array_flip($serviceKeys);
        $activeServices = static::getActiveServices();
        foreach ($serviceKeys as $serviceKey => $ignore) {
            $serviceKeys[$serviceKey] = in_array($serviceKey, $activeServices);
        }
        return $serviceKeys;
    }

    public static function isActive($service)
    {
        return !is_null(Service::active()->where("name", $service)->first());
    }

    public static function isSunset($serviceKey)
    {
        return !empty(static::SERVICES[$serviceKey]["sunset"]);
    }

    public static function getProductKeys()
    {
        $services = Service::pluck("product_ids", "name");
        return $services->map(function ($item, $key) {
            return collect(explode(",", $item));
        });
    }

    public static function getProductKeysToServices()
    {
        $productKeys = self::getProductKeys();
        $return = [];
        foreach ($productKeys as $service => $productIds) {
            foreach ($productIds as $productId) {
                $return[$productId] = $service;
            }
        }
        return $return;
    }

    public static function factoryPromotionalHelperByProductKey($productKey)
    {
        $productKeys = self::getProductKeysToServices();
        if (array_key_exists($productKey, $productKeys)) {
            return self::factoryPromotionalHelper($productKeys[$productKey]);
        }
        return NULL;
    }

    public static function getPromotionClassByService($service)
    {
        if (isset(self::$promotionServices[$service])) {
            return self::$promotionServices[$service];
        }
        throw new \Exception("Invalid service name");
    }

    public static function getClassByService($service)
    {
        if (isset(self::$services[$service])) {
            return self::$services[$service];
        }
        throw new \Exception("Invalid service name");
    }

    public static function getControllerClassByService($service)
    {
        if (stristr($service, "_") !== false) {
            list($service) = explode("_", $service);
        }
        if (isset(self::$controllers[$service])) {
            return self::$controllers[$service];
        }
        throw new \Exception("Invalid service name");
    }

    public static function factoryPromotionalHelper($service)
    {
        $class = self::getPromotionClassByService(strtolower($service));
        return new $class();
    }

    public static function factoryServiceHelper($service)
    {
        $class = self::getClassByService(strtolower($service));
        return new $class();
    }

    public static function getMenuItems($loggedIn = false)
    {
        $children = self::getMenuItemsChildren();
        if ($loggedIn && self::isActive(self::SERVICE_SYMANTEC)) {
            if (!empty($children)) {
                $children[] = ["name" => "Website Security Divider", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 2000];
            }
            $children[] = ["name" => "Manage SSL Certificates", "label" => \Lang::trans("navManageSsl"), "uri" => routePath("clientarea-ssl-certificates-manage"), "order" => 2100];
        }
        return $children;
    }

    protected static function getMenuItemsChildren(&$i = 0, $order = 1000)
    {
        $children = [];
        foreach (Service::active()->get() as $service) {
            if ($service->setting("general.activate-landing-page") !== false) {
                $iVar = $i;
                if ($service->productGroup) {
                    $iVar = $service->productGroup->displayOrder;
                }
                $name = self::getVendorSystemName($service->name);
                if (!empty(self::$sidebarNameOverrides[$name])) {
                    $name = self::$sidebarNameOverrides[$name];
                }
                $children[] = ["name" => $name, "label" => \Lang::trans("navMarketConnectService." . $name), "uri" => routePath("store-product-group", $service->productGroup->slug), "order" => $order + $iVar * 10];
                $i = $iVar + 1;
            }
        }
        return $children;
    }

    public static function getSidebarMenuItems(&$i, $order = 0)
    {
        return self::getMenuItemsChildren($i, $order);
    }

    public function activate($service)
    {
        $activateService = $service;
        if ($activateService === self::SERVICE_SITEBUILDER) {
            $activateService = "siteplus";
        }
        try {
            $api = new Api();
            $response = $api->activate($activateService);
            $postActivationMsg = array_key_exists("postActivationMessage", $response) ? $response["postActivationMessage"] : NULL;
        } catch (Exception\AuthError $e) {
            throw new \Exception("Unable to login to the Marketplace. Please check your account and try again.");
        } catch (Exception\AuthNotConfigured $e) {
            throw new \Exception("Before you can activate a service, you must first login or create an account with WHMCS MarketConnect");
        } catch (Exception\ConnectionError $e) {
            throw new \Exception("Unable to connect to the Marketplace. Please try again later.");
        } catch (Exception\GeneralError $e) {
            throw new \Exception($e->getMessage());
        }
        $productsAndAddons = $this->createProductsFromApiResponse($response["productsCreationParameters"]);
        $productIdNames = $productsAndAddons["products"]->keys()->all();
        Service::activate($service, $productIdNames);
        if (!function_exists("rebuildModuleHookCache")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "modulefunctions.php";
        }
        rebuildModuleHookCache();
        return $postActivationMsg;
    }

    public function predeactivate($service)
    {
        $deactivateService = $service;
        if ($deactivateService === self::SERVICE_SITEBUILDER) {
            $deactivateService = "siteplus";
        }
        try {
            $api = new Api();
            $response = $api->predeactivate($deactivateService);
            if (array_key_exists("preDeactivationMessage", $response)) {
                return $response["preDeactivationMessage"];
            }
        } catch (\Exception $e) {
        }
    }

    public function deactivate($service)
    {
        $deactivateService = $service;
        if ($deactivateService === self::SERVICE_SITEBUILDER) {
            $deactivateService = "siteplus";
        }
        $service = Service::firstOrNew(["name" => $service]);
        $service->deactivate();
        try {
            $api = new Api();
            $api->deactivate($deactivateService);
        } catch (\Exception $e) {
        }
    }

    public function createProductsFromApiResponse($products)
    {
        $usdCurrency = \WHMCS\Billing\Currency::where("code", "USD")->first();
        if (is_null($usdCurrency)) {
            $exchangeRates = \WHMCS\Utility\CurrencyExchange::fetchCurrentRates();
            $defaultCurrency = \WHMCS\Billing\Currency::defaultCurrency()->first();
            if (!$exchangeRates->hasCurrencyCode($defaultCurrency->code)) {
                throw new \Exception("We are not able to obtain a USD exchange rate for the default currency in your WHMCS installation. Please add the USD currency and try again.");
            }
            $usdCurrency = new \WHMCS\Billing\Currency();
            $usdCurrency->code = "USD";
            $usdCurrency->rate = $exchangeRates->getUsdExchangeRate($defaultCurrency->code);
        }
        $currencies = \WHMCS\Billing\Currency::all();
        $resultingProducts = new \Illuminate\Support\Collection();
        $resultingAddons = new \Illuminate\Support\Collection();
        foreach ($products as $group) {
            $originalSlug = $group["slug"];
            if ($group["slug"] === "sitebuilder") {
                $group["slug"] = "site-builder";
            }
            $groupModel = \WHMCS\Product\Group::where("name", $group["name"])->first();
            if (is_null($groupModel)) {
                $groupModel = new \WHMCS\Product\Group();
                $groupModel->name = $group["name"];
                do {
                    $i = 0;
                    $i++;
                    $slug = $group["slug"];
                    if (1 < $i) {
                        $slug .= $i;
                    }
                    $slugUnique = \WHMCS\Product\Group::slug($slug)->count() === 0;
                } while ($slugUnique);
                $groupModel->slug = $slug;
                $groupModel->headline = $group["headline"];
                $groupModel->tagline = $group["tagline"];
                $groupModel->isHidden = true;
                $groupModel->displayOrder = \WHMCS\Product\Group::orderBy("order", "desc")->pluck("order")->first() + 1;
                $groupModel->save();
            }
            $groupWeighting = $group["weighting"] ?? NULL;
            foreach ($group["products"] as $product) {
                if (!empty(self::SERVICES[$originalSlug]["service_replace_prefix"])) {
                    $replaceWhat = self::SERVICES[$originalSlug]["service_replace_prefix"];
                    $replaceWith = $originalSlug;
                    $values = explode("_", $product["moduleConfigOptions"][1]);
                    if ($values[0] === $replaceWhat) {
                        $product["moduleConfigOptions"][1] = $replaceWith . "_" . $values[1];
                    }
                }
                $productType = $product["moduleConfigOptions"][1];
                $productType = explode("_", $productType);
                $productType = $productType[0];
                $emailTemplateId = 0;
                if ($product["welcomeEmailName"]) {
                    $emailTemplateId = \WHMCS\Mail\Template::where("name", "=", $product["welcomeEmailName"])->where("language", "=", "")->orWhere("language", "=", NULL)->pluck("id")->first();
                }
                $newProduct = false;
                $newAddon = false;
                $productModel = \WHMCS\Product\Product::where("servertype", $product["module"])->where("configoption1", $product["moduleConfigOptions"][1])->first();
                $allowMultipleQuantities = 0;
                if (!empty($product["quantity"])) {
                    $allowMultipleQuantities = $product["quantity"];
                }
                if (is_null($productModel)) {
                    $productModel = new \WHMCS\Product\Product();
                    $productModel->type = $product["type"];
                    $productModel->name = $product["name"];
                    $productModel->description = $product["description"];
                    $productModel->welcomeEmailTemplateId = $emailTemplateId;
                    $productModel->paymentType = $product["paymentType"];
                    $productModel->autoSetup = $product["autoSetup"];
                    $productModel->module = $product["module"];
                    $productModel->allowMultipleQuantities = $allowMultipleQuantities;
                    foreach ($product["moduleConfigOptions"] as $key => $value) {
                        $keyName = "moduleConfigOption" . $key;
                        $productModel->{$keyName} = $value;
                    }
                    $productModel->displayOrder = $product["displayOrder"];
                    $productModel->applyTax = true;
                    $productModel->isFeatured = (bool) $product["isFeatured"];
                    $groupModel->products()->save($productModel);
                    $slug = new \WHMCS\Product\Product\Slug(["group_id" => $groupModel->id, "group_slug" => $groupModel->slug, "slug" => $productModel->autoGenerateUniqueSlug(), "active" => true]);
                    $productModel->slugs()->save($slug);
                    $resultingProducts->put($product["moduleConfigOptions"][1], $productModel);
                    $newProduct = true;
                } else {
                    $productModel->allowMultipleQuantities = $allowMultipleQuantities;
                    $productModel->isHidden = false;
                    $productModel->quantityInStock = 0;
                    $productModel->stockControlEnabled = false;
                    $productModel->displayOrder = $product["displayOrder"];
                    $productModel->save();
                    $resultingProducts->put($productModel->moduleConfigOption1, $productModel);
                }
                $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $product["moduleConfigOptions"][1])->get()->where("productAddon.module", $product["module"])->first();
                if (is_null($addonModel)) {
                    if (!$groupWeighting) {
                        if (in_array($productType, Services\Symantec::SSL_TYPES)) {
                            $productType = self::SERVICE_SYMANTEC;
                        }
                        $groupWeighting = self::SERVICES[$productType]["weighting"];
                    }
                    $addonProducts = \WHMCS\Product\Product::where("id", "!=", 0);
                    foreach ($product["addonLinkCriteria"] as $field => $value) {
                        if (is_array($value)) {
                            $addonProducts->whereIn($field, $value);
                        } else {
                            $addonProducts->where($field, $value);
                        }
                    }
                    $addonProductIds = $addonProducts->pluck("id")->toArray();
                    $addonModel = new \WHMCS\Product\Addon();
                    $addonModel->name = $group["name"] . " - " . $product["name"];
                    $addonModel->description = $product["description"];
                    $addonModel->billingCycle = $product["paymentType"];
                    $addonModel->showOnOrderForm = true;
                    $addonModel->applyTax = true;
                    $addonModel->autoActivate = $product["autoSetup"];
                    $addonModel->welcomeEmailTemplateId = $emailTemplateId;
                    $addonModel->packages = $addonProductIds;
                    $addonModel->type = $product["type"];
                    $addonModel->module = $product["module"];
                    $addonModel->weight = $product["displayOrder"] + $groupWeighting;
                    $addonModel->allowMultipleQuantities = $allowMultipleQuantities;
                    if (!empty($product["addonLinkCriteria"]) && is_array($product["addonLinkCriteria"])) {
                        $addonModel->autoLinkCriteria = $product["addonLinkCriteria"];
                    }
                    $addonModel->save();
                    $newAddon = true;
                    foreach ($product["moduleConfigOptions"] as $key => $value) {
                        $moduleConfigModel = new \WHMCS\Config\Module\ModuleConfiguration();
                        $moduleConfigModel->entityType = "addon";
                        $moduleConfigModel->settingName = "configoption" . $key;
                        $moduleConfigModel->friendlyName = "";
                        $moduleConfigModel->value = $value;
                        $addonModel->moduleConfiguration()->save($moduleConfigModel);
                    }
                    $resultingAddons->push($addonModel);
                } else {
                    $productAddon = $addonModel->productAddon;
                    $productAddon->showOnOrderForm = true;
                    $addonProducts = \WHMCS\Product\Product::where("id", "!=", 0);
                    foreach ($product["addonLinkCriteria"] as $field => $value) {
                        if (is_array($value)) {
                            $addonProducts->whereIn($field, $value);
                        } else {
                            $addonProducts->where($field, $value);
                        }
                    }
                    $productAddon->allowMultipleQuantities = $allowMultipleQuantities;
                    $productAddon->packages = $addonProducts->pluck("id")->toArray();
                    $productAddon->save();
                    $resultingAddons->push($productAddon);
                }
                if ($newProduct || $newAddon) {
                    foreach ($currencies as $currency) {
                        $pricingArray = ["type" => "product", "currency" => $currency["id"], "relid" => $productModel->id, "monthly" => "-1", "quarterly" => "-1", "semiannually" => "-1", "annually" => "-1", "biennially" => "-1", "triennially" => "-1"];
                        foreach ($product["pricing"] as $cycle => $price) {
                            if ($cycle == "onetime") {
                                $cycle = "monthly";
                            }
                            if (array_key_exists($cycle, $pricingArray)) {
                                $pricingArray[substr($cycle, 0, 1) . "setupfee"] = convertCurrency($price["setup"], NULL, $currency->id, $usdCurrency->rate);
                                $pricingArray[$cycle] = convertCurrency($price["price"] ?? NULL, NULL, $currency->id, $usdCurrency->rate);
                            }
                        }
                        if ($newProduct) {
                            \WHMCS\Database\Capsule::table("tblpricing")->insert($pricingArray);
                        }
                        if ($newAddon) {
                            $pricingArray["type"] = "addon";
                            $pricingArray["relid"] = $addonModel->id;
                            \WHMCS\Database\Capsule::table("tblpricing")->insert($pricingArray);
                        }
                    }
                    if ($newProduct && !empty(self::SERVICES[$productType]["ftpCustomFields"])) {
                        foreach (self::FTP_CUSTOM_FIELDS as $field) {
                            $customField = \WHMCS\CustomField::firstOrNew(["type" => \WHMCS\CustomField::TYPE_PRODUCT, "relid" => $productModel->id, "fieldName" => $field["fieldName"], "fieldType" => $field["fieldType"]]);
                            if (!$customField->exists) {
                                $customField->adminOnly = $field["adminOnly"];
                                $customField->sortOrder = $field["sortOrder"];
                                $customField->save();
                            }
                        }
                    }
                    if ($newAddon && !empty(self::SERVICES[$productType]["ftpCustomFields"])) {
                        foreach (self::FTP_CUSTOM_FIELDS as $field) {
                            $customField = \WHMCS\CustomField::firstOrNew(["type" => \WHMCS\CustomField::TYPE_ADDON, "relid" => $addonModel->id, "fieldName" => $field["fieldName"], "fieldType" => $field["fieldType"]]);
                            if (!$customField->exists) {
                                $customField->adminOnly = $field["adminOnly"];
                                $customField->sortOrder = $field["sortOrder"];
                                $customField->save();
                            }
                        }
                    }
                }
            }
        }
        return ["products" => $resultingProducts, "addons" => $resultingAddons];
    }

    public static function isAccountConfigured()
    {
        return self::accountEmail() && 0 < strlen(self::getApiBearerToken());
    }

    public static function accountEmail()
    {
        return \WHMCS\Config\Setting::getValue("MarketConnectEmail");
    }

    public static function getApiBearerToken()
    {
        return decrypt(\WHMCS\Config\Setting::getValue("MarketConnectApiToken"));
    }

    public function removeMarketplaceAddons($addons)
    {
        $marketConnectAddonIds = \WHMCS\Product\Addon::where("module", "marketconnect")->pluck("id");
        foreach ($addons as $key => $addonData) {
            if ($marketConnectAddonIds->contains($addonData["id"])) {
                unset($addons[$key]);
            }
        }
        return $addons;
    }

    protected function getAddonsByGroup($addons)
    {
        $marketConnectAddonIds = \WHMCS\Product\Addon::where("module", "marketconnect")->pluck("id");
        $addonsGroupMap = \WHMCS\Config\Module\ModuleConfiguration::where("entity_type", "addon")->whereIn("entity_id", $marketConnectAddonIds)->whereIn("entity_id", $addons->pluck("id"))->where("setting_name", "configoption1")->pluck("value", "entity_id")->toArray();
        $orderedAddonGroupMap = [];
        foreach ($marketConnectAddonIds as $addonId) {
            if (array_key_exists($addonId, $addonsGroupMap)) {
                $orderedAddonGroupMap[$addonId] = $addonsGroupMap[$addonId];
            }
        }
        $addonsByGroup = [];
        foreach ($orderedAddonGroupMap as $addonId => $addonKey) {
            $addonKey = explode("_", $addonKey);
            $addonsByGroup[$addonKey[0]][] = $addonId;
        }
        return $addonsByGroup;
    }

    public function getAdminMarketplaceAddonPromo($addons, $billingCycle, $orderItemId)
    {
        $addons = collect($addons);
        $addonsByGroup = $this->getAddonsByGroup($addons);
        $addonPromoHtml = [];
        foreach (Service::active()->get() as $service) {
            $promoter = $service->factoryPromoter();
            $addonPromoHtml[] = $promoter->adminCartConfigureProductAddon($addonsByGroup, $addons, $billingCycle, $orderItemId);
        }
        return $addonPromoHtml;
    }

    public function getMarketplaceConfigureProductAddonPromoHtml($addons, $billingCycle)
    {
        if (!$addons) {
            return [];
        }
        $addons = collect($addons);
        $addonsByGroup = $this->getAddonsByGroup($addons);
        $addonPromoHtml = $service_weight = [];
        foreach (Service::active()->get() as $service) {
            $service_weight[] = self::SERVICES[$service->name]["weighting"] ?? 0;
            $promoter = $service->factoryPromoter();
            $addonPromoHtml[] = $promoter->cartConfigureProductAddon($addonsByGroup, $addons, $billingCycle);
        }
        array_multisort($service_weight, $addonPromoHtml);
        return $addonPromoHtml;
    }

    public static function getStoreRoutePath($service)
    {
        $service = strtolower($service);
        return routePath(isset(self::$routeMap[$service]) ? "store-" . self::$routeMap[$service] . "-index" : "store-" . $service . "-index");
    }

    public static function getServiceProductGroupSlug($service)
    {
        if (empty($slugs[$service])) {
            try {
                $productIds = Service::name($service)->firstOrFail()->productIds;
                $group = \WHMCS\Product\Group::whereHas("products", function (\Illuminate\Database\Eloquent\Builder $query) use($productIds) {
                    $query->whereIn("configoption1", $productIds);
                })->firstOrFail();
                $slugs[$service] = $group->slug;
            } catch (\Throwable $t) {
                $slugs[$service] = MarketConnect::SERVICE_SLUGS[MarketConnect::SERVICE_GROUP_NAMES[$service]];
            }
        }
        return $slugs[$service];
    }

    public static function getServiceProductGroupName($service)
    {
        if (array_key_exists($service, self::SERVICE_GROUP_NAMES)) {
            return self::SERVICE_GROUP_NAMES[$service];
        }
        $productKeys = self::getProductKeysToServices();
        if (array_key_exists($service, $productKeys)) {
            return self::getServiceProductGroupName($productKeys[$service]);
        }
        return NULL;
    }

    public static function addPricingForNewCurrency(\WHMCS\Billing\Currency $currency)
    {
        $usdCurrency = \WHMCS\Billing\Currency::where("code", "=", "USD")->first();
        if (is_null($usdCurrency)) {
            return NULL;
        }
        $products = collect(\WHMCS\Product\Product::marketConnect()->get());
        $addons = collect(\WHMCS\Product\Addon::marketConnect()->get());
        $services = $products->merge($addons);
        foreach ($services as $productModel) {
            try {
                $pricing = $productModel->pricing($usdCurrency);
            } catch (\Throwable $e) {
            }
            $pricingArray = ["monthly" => "-1", "quarterly" => "-1", "semiannually" => "-1", "annually" => "-1", "biennially" => "-1", "triennially" => "-1"];
            foreach ($pricingArray as $cycle => $price) {
                $cyclePrice = $pricing->{$cycle}();
                if (!is_null($cyclePrice)) {
                    if ($cycle === "onetime") {
                        $cycle = "monthly";
                    }
                    if (array_key_exists($cycle, $pricingArray)) {
                        $pricingArray[substr($cycle, 0, 1) . "setupfee"] = convertCurrency($cyclePrice->setup()->toNumeric(), NULL, $currency->id, $usdCurrency->rate);
                        $pricingArray[$cycle] = convertCurrency($cyclePrice->price()->toNumeric(), NULL, $currency->id, $usdCurrency->rate);
                    }
                }
            }
            $pricingArrayExtra = ["type" => "product", "currency" => $currency->id, "relid" => $productModel->id];
            $pricingArray = array_merge($pricingArray, $pricingArrayExtra);
            if ($productModel instanceof \WHMCS\Product\Product) {
                \WHMCS\Database\Capsule::table("tblpricing")->insert($pricingArray);
            } else {
                if ($productModel instanceof \WHMCS\Product\Addon) {
                    $pricingArray["type"] = "addon";
                    $pricingArray["relid"] = $productModel->id;
                    \WHMCS\Database\Capsule::table("tblpricing")->insert($pricingArray);
                }
            }
        }
    }

    public static function getVendorSystemName($serviceName)
    {
        return self::SERVICES[$serviceName]["vendorSystemName"] ?? $serviceName;
    }
}
