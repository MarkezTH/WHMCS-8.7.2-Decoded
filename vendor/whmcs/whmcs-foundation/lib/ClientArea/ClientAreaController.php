<?php

namespace WHMCS\ClientArea;

class ClientAreaController
{
    protected $addonModel = NULL;
    protected $serviceModel = NULL;

    public function clientHome(\WHMCS\Http\Message\ServerRequest $request)
    {
        return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php");
    }

    public function homePage(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $query = $request->getQueryParams();
        if (!empty($query["rp"]) && strpos($query["rp"], "/detect-route-environment") !== false) {
            $controller = new \WHMCS\Admin\Setup\General\UriManagement\ConfigurationController(\WHMCS\Admin\Setup\General\UriManagement\ConfigurationController::PATH_COMPARISON_INDEX);
            return $controller->detectRouteEnvironment($request);
        }
        if (\WHMCS\Config\Setting::getValue("DefaultToClientArea")) {
            return new \WHMCS\Http\RedirectResponse("clientarea.php");
        }
        if (!function_exists("ticketsummary")) {
            include_once ROOTDIR . "/includes/ticketfunctions.php";
        }
        if (!function_exists("getSpotlightTldsWithPricing")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "domainfunctions.php";
        }
        $view = new \WHMCS\ClientArea();
        $view->setTemplate("homepage");
        $view->addOutputHookFunction("ClientAreaPageHome");
        $view->setPageTitle(\Lang::trans("globalsystemname"));
        $view->setDisplayTitle(\Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $data = [];
        $data["announcements"] = $this->getAnnouncements();
        $routeSetting = \WHMCS\Config\Setting::getValue("RouteUriPathMode");
        $seoSetting = $routeSetting == \WHMCS\Route\UriPath::MODE_REWRITE ? 1 : 0;
        $data["seofriendlyurls"] = $seoSetting;
        if (\WHMCS\Config\Setting::getValue("AllowRegister")) {
            $data["registerdomainenabled"] = true;
        }
        if (\WHMCS\Config\Setting::getValue("AllowTransfer")) {
            $data["transferdomainenabled"] = true;
        }
        if (\WHMCS\Config\Setting::getValue("AllowOwnDomain")) {
            $data["owndomainenabled"] = true;
        }
        $captcha = new \WHMCS\Utility\Captcha();
        $data["captcha"] = $captcha;
        $data["captchaForm"] = \WHMCS\Utility\Captcha::FORM_DOMAIN_CHECKER;
        $data["recaptchahtml"] = clientAreaReCaptchaHTML();
        $data["capatacha"] = $captcha;
        $data["recapatchahtml"] = clientAreaReCaptchaHTML();
        $data["productGroups"] = \WHMCS\Product\Group::notHidden()->sorted()->get();
        $currency = \WHMCS\Billing\Currency::factoryForClientArea();
        $tldPricing = localAPI("GetTldPricing", ["clientid" => \Auth::user()->id ?? NULL, "currencyid" => $currency["id"]]);
        $data["tldPricing"] = (array) $tldPricing["pricing"];
        foreach ($data["tldPricing"] as $tld => &$priceData) {
            foreach (["register", "transfer", "renew"] as $action) {
                if (isset($priceData[$action]) && is_array($priceData[$action])) {
                    foreach ($priceData[$action] as $term => &$price) {
                        $price = new \WHMCS\View\Formatter\Price($price, $currency);
                    }
                }
            }
        }
        unset($price);
        unset($priceData);
        $extensions = array_keys((array) $tldPricing["pricing"]) ?: [];
        $featuredTlds = [];
        $spotlights = getSpotlightTldsWithPricing();
        foreach ($spotlights as $spotlight) {
            if (file_exists(ROOTDIR . "/assets/img/tld_logos/" . $spotlight["tldNoDots"] . ".png")) {
                $featuredTlds[] = $spotlight;
            }
        }
        $data["featuredTlds"] = $featuredTlds;
        $data["numberOfDomains"] = \Auth::client() ? \Auth::client()->domains()->count() : 0;
        $view->setTemplateVariables($data);
        return $view;
    }

    protected function getAnnouncements()
    {
        $activeLanguage = \WHMCS\Session::get("Language");
        if (!$activeLanguage) {
            $activeLanguage = \WHMCS\Config\Setting::getValue("Language");
        }
        $announcements = [];
        $result = select_query("tblannouncements", "", ["published" => "1"], "date", "DESC", "0,3");
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $date = $data["date"];
            $title = $data["title"];
            $announcement = $data["announcement"];
            if ($activeLanguage) {
                $result2 = select_query("tblannouncements", "", ["parentid" => $id, "language" => $activeLanguage]);
                $data = mysql_fetch_array($result2);
                if (is_array($data)) {
                    $title = $data["title"] ?? NULL;
                    $announcement = $data["announcement"] ?? NULL;
                }
            }
            $formattedDate = fromMySQLDate($date, "", true);
            $announcements[] = ["id" => $id, "date" => $formattedDate, "rawDate" => $date, "title" => $title, "urlfriendlytitle" => getModRewriteFriendlyString($title), "summary" => ticketsummary(strip_tags($announcement), 350), "text" => $announcement];
        }
        return $announcements;
    }

    public function sslPurchase(\WHMCS\Http\Message\ServerRequest $request)
    {
        if (\WHMCS\MarketConnect\MarketConnect::isActive("symantec")) {
            \App::redirectToRoutePath("store-product-group", [\WHMCS\MarketConnect\MarketConnect::getServiceProductGroupSlug(\WHMCS\MarketConnect\MarketConnect::SERVICE_SYMANTEC)]);
        }
        \App::redirect("cart.php");
    }

    public function displayImage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $type = $request->get("type");
        switch ($type) {
            case \WHMCS\Utility\Image::IMAGE_EMAIL:
                $method = "displayEMailImage";
                break;
            case \WHMCS\Utility\Image::IMAGE_KNOWLEDGEBASE:
            default:
                $method = "displayKbImage";
                (new \WHMCS\Utility\Image())->{$method}($request->get("id"));
        }
    }

    public function dismissEmailVerification(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin();
        User\EmailVerification::dismiss();
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }

    public function dismissUserValidation(\WHMCS\Http\Message\ServerRequest $request)
    {
        $userValidation = \DI::make("userValidation");
        $userValidation->dismissClientBanner();
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }

    public function runCustomModuleAction(\WHMCS\Http\Message\ServerRequest $request)
    {
        \WHMCS\Session::release();
        $serviceId = $request->get("serviceId");
        try {
            $action = $request->get("method");
            $model = $this->retrieveModel($request);
            $passedParams = [];
            foreach ($request->request()->all() as $key => $value) {
                $passedParams[$key] = $value;
            }
            foreach ($request->query()->all() as $key => $value) {
                $passedParams[$key] = $value;
            }
            foreach ($request->attributes()->all() as $key => $value) {
                $passedParams[$key] = $value;
            }
            $serverInterface = $model->moduleInterface();
            $response = $serverInterface->call("ClientAreaAllowedFunctions");
            if (!is_array($response) || !in_array($action, $response)) {
                throw new \WHMCS\Exception\Module\NotServicable();
            }
            $response = $serverInterface->call($action);
            if ($response === \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                $response = $serverInterface->call("custom", $passedParams);
                if ($response === \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                    throw new \WHMCS\Exception\Module\NotServicable();
                }
            }
            if ($response instanceof \WHMCS\Http\Message\JsonResponse) {
                return $response;
            }
            if (!is_array($response)) {
                throw new \WHMCS\Exception\Module\NotServicable();
            }
            if (!empty($response["jsonResponse"])) {
                return new \WHMCS\Http\Message\JsonResponse($response["jsonResponse"]);
            }
            return $this->clientAreaOutputFromResponse($request, $response);
        } catch (\Throwable $throwable) {
            return $this->badResponse($serviceId);
        }
    }

    protected function clientAreaOutputFromResponse($ClientArea, $request, $response)
    {
        $serviceId = $request->get("serviceId");
        $addonId = $request->get("addonId");
        $model = $this->serviceModel;
        if ($addonId) {
            $model = $this->addonModel;
        }
        if ($response === \WHMCS\Module\Server::FUNCTIONDOESNTEXIST || !is_array($response) || empty($response["displayTitle"]) || empty($response["template"])) {
            throw new \WHMCS\Exception\Module\NotServicable();
        }
        $ca = new \WHMCS\ClientArea();
        $title = \Lang::trans($response["displayTitle"]);
        $ca->setDisplayTitle($title);
        $ca->setPageTitle($title);
        $ca->setTemplate($response["template"]);
        $ca->addToBreadCrumb("clientarea.php?action=products", \Lang::trans("clientareaproducts"));
        $ca->addToBreadCrumb("clientarea.php?action=productdetails&id=" . $serviceId, \Lang::trans("clientareaproductdetails"));
        if ($addonId) {
            $ca->addToBreadCrumb(routePath("module-custom-action-addon", $serviceId, $addonId, "manage"), $title);
        } else {
            $ca->addToBreadCrumb(routePath("module-custom-action", $serviceId, "manage"), $title);
        }
        $ca->assign("model", $model);
        if (!empty($response["variables"]) && is_array($response["variables"])) {
            foreach ($response["variables"] as $key => $value) {
                $ca->assign($key, $value);
            }
        }
        \Menu::addContext("service", $this->serviceModel);
        \Menu::addContext("addon", $this->addonModel);
        $sidebarName = "serviceView";
        if (!empty($response["sidebar"])) {
            $sidebarName = $response["sidebar"];
        }
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        return $ca;
    }

    protected function retrieveModel(\WHMCS\Http\Message\ServerRequest $request)
    {
        $serviceId = $request->get("serviceId");
        $addonId = $request->get("addonId");
        if (!\Auth::client()) {
            throw new \WHMCS\Exception\Module\NotServicable();
        }
        $this->serviceModel = $model = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->findOrFail($serviceId);
        if ($addonId) {
            $this->addonModel = $model = $this->serviceModel->loadMissing("addons")->addons()->findOrFail($addonId);
        }
        if ($model instanceof \WHMCS\Service\Service && $model->product->module !== "marketconnect" || $model instanceof \WHMCS\Service\Addon && $model->productAddon->module !== "marketconnect") {
            throw new \WHMCS\Exception\Module\NotServicable();
        }
        return $model;
    }

    protected function badResponse($RedirectResponse, $serviceId)
    {
        return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php?action=productdetails&id=" . $serviceId)->withError("Invalid Request");
    }

    public function parseMarkdown($JsonResponse, $request)
    {
        $markup = new \WHMCS\View\Markup\Markup();
        $response = ["body" => $markup->transform($request->get("content"), "markdown")];
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function performCustomAction($JsonResponse, $request)
    {
        $identifier = $request->get("identifier");
        $serviceId = $request->get("serviceId");
        $hasException = false;
        try {
            $serverObj = new \WHMCS\Module\Server();
            if (!$serverObj->loadByServiceID($serviceId)) {
                throw new \WHMCS\Exception\Module\ModuleNotFound("Unable to find server module for Service ID: " . $serviceId);
            }
            if ($serverObj->functionExists("CustomActions")) {
                $customActionCollection = $serverObj->call("CustomActions", $serverObj->getServerParams(\WHMCS\Service\Service::find($serviceId)->serverModel));
                $callableResult = $customActionCollection->first(function ($object) use($identifier) {
                    return $object->getIdentifier() === $identifier;
                });
                if (is_null($callableResult)) {
                    throw new \WHMCS\Exception("The Custom Action identifier is not present in the module - Service ID: " . $serviceId . " - Module: " . $serverObj->getLoadedModule());
                }
                $callableResult = $callableResult->invokeCallable();
            } else {
                throw new \WHMCS\Exception\Module\FunctionNotFound("The module does not support Custom Action - Service ID: " . $serviceId . " - Module: " . $serverObj->getLoadedModule());
            }
        } catch (\Throwable $e) {
            $hasException = true;
            $callableResult = ["success" => false, "errorMsg" => $e->getMessage()];
        }
        if (!$callableResult["success"]) {
            logActivity("Custom Action Failed: " . $callableResult["errorMsg"]);
            \WHMCS\Session::set("customaction_error", $hasException ? \Lang::trans("customActionException") : \Lang::trans("customActionGenericError"));
        }
        return new \WHMCS\Http\Message\JsonResponse($callableResult);
    }

    public static function forceRedirect2faEnrollment()
    {
        if (!\Auth::user() || \WHMCS\User\Admin::getAuthenticatedUser()) {
            return false;
        }
        if (!(new \WHMCS\TwoFactorAuthentication())->setUser(\Auth::user())->isEnrollmentNeeded()) {
            return false;
        }
        if (static::isForced2faEnrollmentBypassed()) {
            return false;
        }
        \App::redirectToRoutePath("user-security");
    }

    protected static function isForced2faEnrollmentBypassed()
    {
        $whmcs = \App::self();
        $fileName = $whmcs->get_filename();
        $originalUri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        if (strpos($originalUri, "/account/security/two-factor/") !== false) {
            return true;
        }
        if ($fileName == "logout") {
            return true;
        }
        if ($fileName == "clientarea" && $whmcs->get_req_var("action") == "security" || strpos($originalUri, "/user/security") !== false) {
            return true;
        }
        if ($fileName == "cart" && ($whmcs->getFromRequest("a") == "complete" || $whmcs->getFromRequest("a") == "fraudcheck") || strpos($originalUri, "/cart/") !== false || strpos($originalUri, "/invoice/") !== false && strpos($originalUri, "/process") !== false || $fileName == "viewinvoice") {
            return true;
        }
        return false;
    }
}
