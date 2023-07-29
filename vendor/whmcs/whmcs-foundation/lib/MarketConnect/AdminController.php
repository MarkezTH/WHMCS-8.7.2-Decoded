<?php

namespace WHMCS\MarketConnect;

class AdminController
{
    const MARKETCONNECT_INTRO_VIDEO = "https://player.vimeo.com/video/432176608";
    const MARKETCONNECT_VIDEO_DATA_KEY = "MarketConnectVideoDisplayed";

    public function dispatch(\WHMCS\Http\Message\ServerRequest $request)
    {
        $action = $request->get("action");
        if (!$action || !method_exists($this, $action)) {
            $action = "index";
        }
        try {
            return $this->{$action}($request);
        } catch (\Exception $e) {
            return ["error" => $e->getMessage(), "errorCode" => $e->getCode()];
        }
    }

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $balance = (new Balance())->setCacheTimeout(1)->loadFromCache();
        $admin = \WHMCS\User\Admin::find(\WHMCS\Session::get("adminid"));
        $showIntroVideo = true;
        if (MarketConnect::isAccountConfigured()) {
            $showIntroVideo = false;
        }
        if ($showIntroVideo) {
            $transientData = \WHMCS\TransientData::getInstance();
            $videoData = $transientData->retrieve(self::MARKETCONNECT_VIDEO_DATA_KEY);
            if ($videoData) {
                $videoData = json_decode($videoData, true);
                if (json_last_error() === JSON_ERROR_NONE && array_key_exists($admin->id, $videoData)) {
                    $date = \WHMCS\Carbon::parse($videoData[$admin->id]);
                    if ($date->lte($date->addHours(24))) {
                        $showIntroVideo = false;
                    }
                } else {
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $transientData->delete(self::MARKETCONNECT_VIDEO_DATA_KEY);
                    }
                }
            }
        }
        $showIntroVideo = is_null($showIntroVideo) || $showIntroVideo ? "true" : "false";
        return view("marketconnect.services", ["services" => MarketConnect::servicesInDeclarationOrder(MarketConnect::getPromotionServices()), "state" => MarketConnect::getServicesStateMap(), "manageService" => $request->get("manage"), "account" => ["linked" => MarketConnect::isAccountConfigured(), "balance" => $balance->getBalance(), "email" => MarketConnect::accountEmail()], "balanceLastUpdated" => $balance->getLastUpdatedDiff(), "balanceNeedsUpdate" => $balance->isExpired(), "registerInfo" => ["admin" => $admin, "companyName" => \WHMCS\Config\Setting::getValue("CompanyName"), "email" => \WHMCS\Config\Setting::getValue("Email")], "tourSteps" => $this->getIntroTourSteps(), "forceTour" => $request->get("tour"), "learnMore" => $request->get("learnmore"), "activateService" => $request->get("activate"), "showIntroVideo" => $showIntroVideo]);
    }

    public function getBalance(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        try {
            $balance = (new Balance())->updateViaApi()->saveToCache();
        } catch (Exception\AuthError $e) {
            return ["authError" => true];
        }
        return ["balance" => $balance->getBalance(), "updatedDiff" => "Just now"];
    }

    public function showLearnMore(\WHMCS\Http\Message\ServerRequest $request)
    {
        $service = $request->request()->get("service");
        if (array_key_exists($service, MarketConnect::SERVICES)) {
            $feed = new ServicesFeed();
            $serviceSystemName = MarketConnect::getVendorSystemName($service);
            $products = \WHMCS\Product\Product::$serviceSystemName()->get();
            if (!$products->count()) {
                $products = $feed->getEmulationOfConfiguredProducts($service);
            }
            $currency = \Currency::defaultCurrency()->first();
            if ($currency->code != "USD") {
                $usdCurrency = \Currency::where("code", "USD")->first();
                if (is_null($usdCurrency)) {
                    $feed->convertRecommendedRrpPrices(0);
                } else {
                    $feed->convertRecommendedRrpPrices($usdCurrency->rate);
                }
            }
            $serviceModel = Service::firstOrNew(["name" => $service]);
            return ["body" => view("marketconnect.services." . $serviceSystemName . ".learn", ["serviceOffering" => MarketConnect::SERVICES[$service], "promotionHelper" => MarketConnect::factoryPromotionalHelper($service), "feed" => $feed, "currency" => $currency->toArray(), "products" => $products, "serviceTerms" => $feed->getTerms(), "billingCycles" => new \WHMCS\Billing\Cycles(), "service" => $serviceModel, "mcServiceSlug" => $service, "generalSettings" => $serviceModel->getSettingDefinitions()])];
        }
        throw new \Exception("Invalid service requested");
    }

    public function showManage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $service = $request->request()->get("service");
        $currency = \Currency::defaultCurrency()->first();
        $feed = new ServicesFeed();
        if ($currency->code != "USD") {
            $usdCurrency = \Currency::where("code", "USD")->first();
            if (is_null($usdCurrency)) {
                $feed->convertRecommendedRrpPrices(0);
            } else {
                $feed->convertRecommendedRrpPrices($usdCurrency->rate);
            }
        }
        if (array_key_exists($service, MarketConnect::SERVICES)) {
            $serviceModel = Service::where("name", $service)->first();
            $serviceSystemName = MarketConnect::getVendorSystemName($service);
            return ["body" => view("marketconnect.services.manage", ["serviceOffering" => MarketConnect::SERVICES[$service], "currency" => $currency->toArray(), "products" => \WHMCS\Product\Product::$serviceSystemName()->get(), "serviceTerms" => $feed->getTerms(), "billingCycles" => new \WHMCS\Billing\Cycles(), "service" => $serviceModel, "mcServiceSlug" => $service, "generalSettings" => $serviceModel->getSettingDefinitions()])];
        }
        throw new \Exception("Invalid service requested");
    }

    public function sso(\WHMCS\Http\Message\ServerRequest $request)
    {
        return view("marketconnect.sso", ["ssoDestination" => $request->request()->get("destination") ? $request->request()->get("destination") : ""]);
    }

    public function doSsoRedirect(\WHMCS\Http\Message\ServerRequest $request)
    {
        $api = new Api();
        $sso = $api->sso();
        $destination = $request->request()->get("destination");
        return ["redirectUrl" => $sso["redirect_url"] . ($destination ? "?destination=" . urlencode($destination) : "")];
    }

    public function link(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $email = $request->request()->get("email");
        $password = \WHMCS\Input\Sanitize::decode($request->request()->get("password"));
        $agreetos = $request->request()->get("agreetos");
        $licenseKey = \App::getLicenseClientKey();
        try {
            $api = new Api();
            $response = $api->link($email, $password, $licenseKey, $agreetos);
            \WHMCS\Config\Setting::setValue("MarketConnectEmail", $email);
            \WHMCS\Config\Setting::setValue("MarketConnectApiToken", encrypt($response["token"]));
            $balance = (new Balance())->setBalance($response["balance"])->saveToCache();
            return ["email" => $email, "balance" => $balance->getBalance(), "updatedDiff" => "Just now"];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if ($errorMsg == "Login failed") {
                $errorMsg = "<i class=\"fas fa-times\"></i> &nbsp;Login details incorrect. Please try again.";
            }
            return ["error" => $errorMsg];
        }
    }

    public function register(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $firstname = $request->request()->get("firstname");
        $lastname = $request->request()->get("lastname");
        $company = $request->request()->get("companyname");
        $email = $request->request()->get("email");
        $password = \WHMCS\Input\Sanitize::decode($request->request()->get("password"));
        $agreetos = $request->request()->get("agreetos");
        $licenseKey = \App::getLicenseClientKey();
        try {
            $api = new Api();
            $response = $api->register($firstname, $lastname, $company, $email, $password, $licenseKey, $agreetos);
            \WHMCS\Config\Setting::setValue("MarketConnectEmail", $email);
            \WHMCS\Config\Setting::setValue("MarketConnectApiToken", encrypt($response["token"]));
            $balance = (new Balance())->setBalance($response["balance"])->saveToCache();
            return ["email" => $email, "balance" => $balance->getBalance(), "updatedDiff" => "Just now"];
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function disconnect(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        \WHMCS\Config\Setting::setValue("MarketConnectEmail", "");
        \WHMCS\Config\Setting::setValue("MarketConnectApiToken", "");
        \WHMCS\Config\Setting::setValue("MarketConnectBalance", "");
        $transientData = new \WHMCS\TransientData();
        $transientData->delete("MarketConnectServices");
    }

    public function activate(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $service = $request->request()->get("service");
        $mcInterface = new MarketConnect();
        $postActivationMsg = $mcInterface->activate($service);
        $advancedSettings = json_decode(\WHMCS\Input\Sanitize::decode($request->request()->get("advancedSettings")), true);
        if (!empty($advancedSettings["products"]["pricing"])) {
            foreach ($advancedSettings["products"]["pricing"] as $productDetails) {
                $productDetails = urldecode(\WHMCS\Input\Sanitize::decode($productDetails));
                parse_str($productDetails, $parsedDetails);
                $subRequest = (new \WHMCS\Http\Message\ServerRequest())->withParsedBody($parsedDetails);
                $this->setPricing($subRequest);
            }
        }
        if (!empty($advancedSettings["products"]["disabled"])) {
            foreach ($advancedSettings["products"]["disabled"] as $productKey) {
                $subRequest = (new \WHMCS\Http\Message\ServerRequest())->withParsedBody(["productkey" => $productKey]);
                $this->disableProduct($subRequest);
            }
        }
        if (!empty($advancedSettings["promotions"])) {
            foreach ($advancedSettings["promotions"] as $promo) {
                $subRequest = (new \WHMCS\Http\Message\ServerRequest())->withParsedBody(["promo" => $promo["name"], "service" => $service, "state" => (bool) $promo["state"]]);
                $this->setPromoStatus($subRequest);
            }
        }
        if (!empty($advancedSettings["general"])) {
            foreach ($advancedSettings["general"] as $setting) {
                if (isset($setting["name"])) {
                    if ($setting["name"] == "auto-assign-addons" && !$setting["state"]) {
                        $serviceModel = Service::name($service)->first();
                        $serviceModel->disassociateAddonsFromAllProducts();
                    }
                    $subRequest = (new \WHMCS\Http\Message\ServerRequest())->withParsedBody(["name" => $setting["name"], "service" => $service, "state" => (bool) $setting["state"]]);
                    $this->setGeneralSetting($subRequest);
                }
            }
        }
        return ["success" => true, "postActivationMsg" => $postActivationMsg];
    }

    public function predeactivate(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $service = $request->request()->get("service");
        $mcInterface = new MarketConnect();
        $preDeactivationMsg = $mcInterface->predeactivate($service);
        return ["success" => true, "preDeactivationMsg" => $preDeactivationMsg];
    }

    public function deactivate(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $service = $request->request()->get("service");
        $mcInterface = new MarketConnect();
        $mcInterface->deactivate($service);
        return ["success" => true];
    }

    public function enableProduct(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $productKey = $request->request()->get("productkey");
        $keyParts = explode("_", $productKey);
        $productModel = \WHMCS\Product\Product::where("servertype", "marketconnect")->where("configoption1", $productKey)->first();
        $productType = MarketConnect::getVendorSystemName($keyParts[0]);
        $otherProducts = \WHMCS\Product\Product::$productType()->visible()->where("configoption1", "!=", $productKey);
        if ($productModel->paymentType === "free" && $otherProducts->count() === 0) {
            return ["error" => "At least one paid product must be enabled to use the free product offering"];
        }
        $productModel->isHidden = false;
        $productModel->stockControlEnabled = false;
        $productModel->save();
        $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $productKey)->get()->where("productAddon.module", "marketconnect")->first();
        $productAddon = $addonModel->productAddon;
        $productAddon->showOnOrderForm = true;
        $productAddon->isHidden = false;
        $productAddon->save();
        return ["success" => true];
    }

    public function disableProduct(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $productKey = $request->request()->get("productkey");
        $keyParts = explode("_", $productKey);
        $productModel = \WHMCS\Product\Product::where("servertype", "marketconnect")->where("configoption1", $productKey)->first();
        $productType = MarketConnect::getVendorSystemName($keyParts[0]);
        $otherProducts = \WHMCS\Product\Product::$productType()->visible()->where("configoption1", "!=", $productKey);
        if ($productModel->paymentType !== "free" && $otherProducts->count() === 1) {
            $otherProduct = $otherProducts->first();
            if ($otherProduct->paymentType === "free") {
                return ["error" => "At least one paid product must be enabled to use the free product offering"];
            }
        }
        $productModel->isHidden = true;
        $productModel->quantityInStock = 0;
        $productModel->stockControlEnabled = true;
        $productModel->save();
        $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $productKey)->get()->where("productAddon.module", "marketconnect")->first();
        $productAddon = $addonModel->productAddon;
        $productAddon->isHidden = true;
        $productAddon->save();
        return ["success" => true];
    }

    public function setPricing(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $productKey = $request->request()->get("productkey");
        $userEnabled = $request->request()->get("enabled");
        $userPricing = $request->request()->get("price");
        $defaultCurrency = \Currency::defaultCurrency()->first();
        $currencies = \Currency::all();
        $productsToUpdate = [];
        $productModel = \WHMCS\Product\Product::where("servertype", "marketconnect")->where("configoption1", $productKey)->first();
        $productsToUpdate[] = ["type" => "product", "relid" => $productModel->id];
        $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $productKey)->get()->where("productAddon.module", "marketconnect")->first();
        $productAddon = $addonModel->productAddon;
        $productsToUpdate[] = ["type" => "addon", "relid" => $productAddon->id];
        $map = ["monthly", "monthly", "3" => "quarterly", "6" => "semiannually", "12" => "annually", "24" => "biennially", "36" => "triennially"];
        foreach ($currencies as $currency) {
            $pricingArray = ["monthly" => "-1", "quarterly" => "-1", "semiannually" => "-1", "annually" => "-1", "biennially" => "-1", "triennially" => "-1"];
            foreach (array_keys($map) as $term) {
                if (isset($userEnabled[$term]) && $userEnabled[$term]) {
                    $cycle = $map[$term];
                    $pricingArray[substr($cycle, 0, 1) . "setupfee"] = 0;
                    $pricingArray[$cycle] = convertCurrency($userPricing[$term], NULL, $currency->id, $defaultCurrency->rate);
                }
            }
            foreach ($productsToUpdate as $product) {
                \WHMCS\Database\Capsule::table("tblpricing")->updateOrInsert(["type" => $product["type"], "relid" => $product["relid"], "currency" => $currency["id"]], $pricingArray);
            }
        }
        return ["success" => true];
    }

    public function setPromoStatus(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $promo = $request->request()->get("promo");
        $service = $request->request()->get("service");
        $state = $request->request()->get("state");
        $service = Service::where("name", $service)->first();
        $settings = $service->settings;
        $settings["promotion"][$promo] = $state == "true";
        $service->settings = $settings;
        $service->save();
        return ["success" => true];
    }

    public function setGeneralSetting(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $settingName = $request->request()->get("name");
        $service = $request->request()->get("service");
        $state = $request->request()->get("state");
        $service = Service::where("name", $service)->first();
        $settings = $service->settings;
        $settings["general"][$settingName] = $state == "true";
        $service->settings = $settings;
        $service->save();
        return ["success" => true];
    }

    public function ssoForService(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $service = $request->request()->get("service");
        $api = new Api();
        $sso = $api->ssoForService($service);
        return ["redirectUrl" => $sso["redirect_url"]];
    }

    public function getServices(\WHMCS\Http\Message\ServerRequest $request)
    {
        $query = $request->request()->get("query");
        $service = $request->request()->get("service");
        $searchResults = [];
        $marketConnectServices = Service::where("name", $service)->first();
        if (!$marketConnectServices) {
            return [];
        }
        $products = \WHMCS\Product\Product::with("services", "services.client")->marketConnect()->whereIn("configoption1", $marketConnectServices->productIds)->get();
        foreach ($products as $product) {
            foreach ($product->services as $clientService) {
                if (!($clientService->domainStatus !== "Active" || !$query || stristr($clientService->domain, $query) === false && stristr($clientService->client->fullName, $query) === false)) {
                    $searchResults[] = ["id" => $clientService->id, "name" => $clientService->client->fullName, "domain" => $clientService->domain, "order" => $clientService->serviceProperties->get("Order Number"), "date" => fromMySQLDate($clientService->registrationDate)];
                }
            }
        }
        $moduleConfigurations = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon", "productAddon.serviceAddons", "productAddon.serviceAddons.client", "productAddon.serviceAddons.service")->where("entity_type", "=", "addon")->where("setting_name", "=", "configoption1")->whereIn("value", $marketConnectServices->productIds)->get();
        foreach ($moduleConfigurations as $moduleConfiguration) {
            $productAddon = $moduleConfiguration->productAddon;
            if ($productAddon && $productAddon->module === "marketconnect") {
                foreach ($productAddon->serviceAddons as $addon) {
                    if (!($addon->status !== "Active" || $query && stristr($addon->service->domain, $query) === false && stristr($addon->client->fullName, $query) === false)) {
                        $searchResults[] = ["id" => "a" . $addon->id, "name" => $addon->client->fullName, "domain" => $addon->service->domain, "order" => $addon->serviceProperties->get("Order Number"), "date" => fromMySQLDate($addon->registrationDate)];
                    }
                }
            }
        }
        return $searchResults;
    }

    public function ssoForServiceId(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        try {
            $serviceId = $request->request()->get("serviceId");
            if (!$serviceId) {
                throw new \Exception("You must select an active service for Single Sign-On.");
            }
            $server = new \WHMCS\Module\Server();
            if (substr($serviceId, 0, 1) == "a") {
                if (!$server->loadByAddonId(substr($serviceId, 1))) {
                    throw new \Exception("Invalid addon ID requested for Single Sign-On.");
                }
            } else {
                if (!$server->loadByServiceID((int) $serviceId)) {
                    throw new \Exception("Invalid service ID requested for Single Sign-On.");
                }
            }
            if (!$server->functionExists("manage_order")) {
                throw new \Exception("Single Sign-On is not supported for this service.");
            }
            $response = $server->call("manage_order");
            if ($response["jsonResponse"]["success"] !== true) {
                $error = "";
                if (array_key_exists("error", $response["jsonResponse"])) {
                    $error = $response["jsonResponse"]["error"];
                } else {
                    if (array_key_exists("error", $response["jsonResponse"])) {
                        $error = $response["jsonResponse"]["errorMsg"];
                    }
                }
                if (!$error) {
                    $error = "An Unexpected Error Occurred";
                }
                throw new Exception\GeneralError($error);
            }
            return ["redirectUrl" => substr($response["jsonResponse"]["redirect"], 7)];
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    protected function getIntroTourSteps()
    {
        $year = \WHMCS\Carbon::now()->year;
        return [["element" => "#marketconnectLogo", "title" => "Introducing MarketConnect", "content" => "MarketConnect provides better integration than ever with service providers, one single account and one balance, no lengthy signup process, and ready to go marketing materials. We've done all the hard work so you don't have to.", "backdrop" => false, "placement" => "auto right"], ["element" => "#mpItemsymantec", "title" => "DigiCert SSL Certificates", "content" => "SSL presents a huge opportunity in " . $year . " and MarketConnect makes it easy and fully automated to sell SSL from the Internet's leading security brand", "backdrop" => true, "placement" => "auto right"], ["element" => "#mpItemweebly", "title" => "Weebly Website Builder", "content" => "Make it easier for customers to get started and build a stunning website with Weebly's Website Builder solution. All sites are published to your servers via FTP and hosted by you.", "backdrop" => true, "placement" => "auto right"], ["element" => "#mpItemspamexperts", "title" => "Email Spam Filtering", "content" => "<em><strong>Did you know?</strong></em> 7 out of 10 emails are spam?<br><br>We all get it and everybody hates it, but now you can offer your clients a solution with Spam Filtering from SpamExperts. Email Archiving also available.", "backdrop" => true, "placement" => "auto left"], ["element" => "#panelAccount", "title" => "Get Started Today", "content" => "MarketConnect uses your existing WHMCS.com account. Simply login/register and you can begin offering any of these services in minutes. No deposit necessary.", "backdrop" => true, "placement" => "auto left"]];
    }

    public function introVideo(\WHMCS\Http\Message\ServerRequest $request)
    {
        $video = self::MARKETCONNECT_INTRO_VIDEO;
        $admin = \WHMCS\User\Admin::getAuthenticatedUser();
        $transientData = \WHMCS\TransientData::getInstance();
        $data = $transientData->retrieve(self::MARKETCONNECT_VIDEO_DATA_KEY);
        $data = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$data) {
            $data = [$admin->id => \WHMCS\Carbon::now()->toDateTimeString()];
        } else {
            $data[$admin->id] = \WHMCS\Carbon::now()->toDateTimeString();
        }
        $data = json_encode($data);
        $transientData->store(self::MARKETCONNECT_VIDEO_DATA_KEY, $data, 86400);
        $langClose = \AdminLang::trans("global.close");
        $body = "<div class=\"col-md-12 text-center\">\n<button id=\"modalAjaxCloseSmall\" type=\"button\" class=\"close\" data-dismiss=\"modal\">\n    <span aria-hidden=\"true\">&times;</span>\n    <span class=\"sr-only\">" . $langClose . "</span>\n</button>\n<iframe src=\"" . $video . "?autoplay=1\" width=\"100%\" height=\"480\" frameborder=\"0\" allow=\"autoplay; fullscreen\" allowfullscreen></iframe>\n</div>";
        return ["body" => $body];
    }
}
