<?php

namespace WHMCS\MarketConnect;

class SslController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_SYMANTEC;
    protected $module = "marketconnect";
    protected $showBrandLicenses = true;

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $isAdminPreview = $this->isAdminPreview();
        $sslAdminPreviewSession = \WHMCS\Session::get("sslAdminPreview");
        if (!($isAdminPreview || $sslAdminPreviewSession) && !$this->isActiveService()) {
            return new \Laminas\Diactoros\Response\RedirectResponse("index.php");
        }
        $ca = $this->certInfoView();
        return $ca;
    }

    public function viewDv(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->showBrandLicenses = false;
        $isAdminPreview = $this->isAdminPreview();
        $sslAdminPreviewSession = \WHMCS\Session::get("sslAdminPreview");
        if (!($isAdminPreview || $sslAdminPreviewSession) && !$this->isActiveService()) {
            return new \Laminas\Diactoros\Response\RedirectResponse("index.php");
        }
        $ca = $this->certInfoView();
        $ca->setPageTitle(\Lang::trans("store.ssl.dv.title") . " - " . \Lang::trans("store.ssl.title"));
        $ca->addToBreadCrumb(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug($this->serviceName), Promotion\Service\Symantec::SSL_TYPE_DV), \Lang::trans("store.ssl.dv.title"));
        $ca->setTemplate("store/ssl/dv");
        return $ca;
    }

    public function viewOv(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->showBrandLicenses = false;
        $isAdminPreview = $this->isAdminPreview();
        $sslAdminPreviewSession = \WHMCS\Session::get("sslAdminPreview");
        if (!($isAdminPreview || $sslAdminPreviewSession) && !$this->isActiveService()) {
            return new \Laminas\Diactoros\Response\RedirectResponse("index.php");
        }
        $ca = $this->certInfoView();
        $ca->setPageTitle(\Lang::trans("store.ssl.ov.title") . " - " . \Lang::trans("store.ssl.title"));
        $ca->addToBreadCrumb(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug($this->serviceName), Promotion\Service\Symantec::SSL_TYPE_OV), \Lang::trans("store.ssl.ov.title"));
        $ca->setTemplate("store/ssl/ov");
        return $ca;
    }

    public function viewEv(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->showBrandLicenses = false;
        $isAdminPreview = $this->isAdminPreview();
        $sslAdminPreviewSession = \WHMCS\Session::get("sslAdminPreview");
        if (!($isAdminPreview || $sslAdminPreviewSession) && !$this->isActiveService()) {
            return new \Laminas\Diactoros\Response\RedirectResponse("index.php");
        }
        $ca = $this->certInfoView();
        $ca->setPageTitle(\Lang::trans("store.ssl.ev.title") . " - " . \Lang::trans("store.ssl.title"));
        $ca->addToBreadCrumb(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug($this->serviceName), Promotion\Service\Symantec::SSL_TYPE_EV), \Lang::trans("store.ssl.ev.title"));
        $ca->setTemplate("store/ssl/ev");
        return $ca;
    }

    public function viewWildcard(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->showBrandLicenses = false;
        $isAdminPreview = $this->isAdminPreview();
        $sslAdminPreviewSession = \WHMCS\Session::get("sslAdminPreview");
        if (!($isAdminPreview || $sslAdminPreviewSession) && !$this->isActiveService()) {
            return new \Laminas\Diactoros\Response\RedirectResponse("index.php");
        }
        $ca = $this->certInfoView();
        $ca->setPageTitle(\Lang::trans("store.ssl.wildcard.title") . " - " . \Lang::trans("store.ssl.title"));
        $ca->addToBreadCrumb(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug($this->serviceName), Promotion\Service\Symantec::SSL_TYPE_WILDCARD), \Lang::trans("store.ssl.wildcard.title"));
        $ca->setTemplate("store/ssl/wildcard");
        return $ca;
    }

    protected function certInfoView()
    {
        $ca = new Output\ClientArea();
        $ca->setPageTitle(\Lang::trans("store.ssl.title"));
        $ca->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $ca->addToBreadCrumb(routePath("store"), \Lang::trans("navStore"));
        $ca->addToBreadCrumb(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug($this->serviceName)), \Lang::trans("store.ssl.title"));
        $ca->initPage();
        $all = \WHMCS\Product\Product::ssl()->visible()->get();
        $currency = \Currency::factoryForClientArea();
        $ca->assign("activeCurrency", $currency);
        $symantecPromoHelper = MarketConnect::factoryPromotionalHelper("symantec");
        $certificates = [];
        $availableCerts = [];
        $hasFeatured = [];
        $sslTypes = $symantecPromoHelper->getSslTypes();
        $namesToType = [];
        foreach ($sslTypes as $type => $names) {
            foreach ($names as $name) {
                $namesToType[$name] = $type;
            }
        }
        foreach ($symantecPromoHelper->getSslTypes($this->showBrandLicenses) as $type => $names) {
            foreach ($names as $name) {
                $cert = $all->where("configoption1", $name)->first();
                if (!is_null($cert)) {
                    $availableCerts[] = $name;
                    $pricing = $cert->pricing($currency);
                    if ($pricing->best()) {
                        $certificates[$namesToType[$name]][] = $cert;
                        if (empty($hasFeatured[$type]) && $cert->isFeatured) {
                            $hasFeatured[$type] = $cert->isFeatured ? $cert->isFeatured : false;
                        }
                    }
                }
            }
        }
        $certTypes = [Promotion\Service\Symantec::SSL_TYPE_OV => 0, Promotion\Service\Symantec::SSL_TYPE_EV => 0, Promotion\Service\Symantec::SSL_TYPE_DV => 0, Promotion\Service\Symantec::SSL_TYPE_WILDCARD => 0];
        foreach ($sslTypes as $type => $names) {
            foreach ($names as $name) {
                if (in_array($name, $availableCerts)) {
                    $certTypes[$type]++;
                }
            }
        }
        $ca->assign("hasFeatured", $hasFeatured);
        $ca->assign("certTypes", $certTypes);
        $ca->assign("certificates", $certificates);
        $ca->assign("certificateFeatures", $symantecPromoHelper->getCertificateFeatures());
        $sslAdminPreviewSession = \WHMCS\Session::get("sslAdminPreview");
        if ($sslAdminPreviewSession && !$this->isActiveService()) {
            $sslAdminPreviewSession = false;
            \WHMCS\Session::set("sslAdminPreview", false);
        }
        $isAdminPreview = $this->isAdminPreview();
        if ($isAdminPreview) {
            \WHMCS\Session::set("sslAdminPreview", true);
        }
        $ca->assign("inPreview", $isAdminPreview || $sslAdminPreviewSession);
        $competitiveUpgradeDomain = \WHMCS\Session::get("competitiveUpgradeDomain");
        $ca->assign("inCompetitiveUpgrade", !empty($competitiveUpgradeDomain));
        $ca->assign("competitiveUpgradeDomain", $competitiveUpgradeDomain);
        $ca->assign("routePathSlug", MarketConnect::getServiceProductGroupSlug($this->serviceName));
        $ca->setTemplate("store/ssl/index");
        $ca->skipMainBodyContainer();
        return $ca;
    }

    public function handleReissuedSslCallback(\WHMCS\Http\Message\ServerRequest $request)
    {
        return $this->handleSslCallback($request, true);
    }

    public function handleSslCallback(\WHMCS\Http\Message\ServerRequest $request, $reissue = false)
    {
        @set_time_limit(0);
        $orderNumber = $request->get("order_number");
        $attempt = $request->get("attempt", 1);
        $customFieldValueCollection = \WHMCS\CustomField\CustomFieldValue::whereHas("customField", function ($query) {
            $query->where("fieldname", "=", "Order Number");
        })->with("customField", "addon", "service")->where("value", "=", $orderNumber)->get();
        foreach ($customFieldValueCollection as $customFieldValue) {
            if ($customFieldValue->customField) {
                switch ($customFieldValue->customField->type) {
                    case "addon":
                        $model = $customFieldValue->addon;
                        $addonId = $model->id;
                        $serviceId = $model->serviceId;
                        break;
                    case "product":
                        $model = $customFieldValue->service;
                        $addonId = 0;
                        $serviceId = $model->id;
                        break;
                    default:
                        $model = NULL;
                        $sslRecord = NULL;
                        $serviceId = $addonId = 0;
                        if ($model) {
                            $sslRecord = \WHMCS\Service\Ssl::where("remoteid", "=", $orderNumber)->where("serviceid", "=", $serviceId)->where("addon_id", "=", $addonId)->where("module", "=", $this->module)->orderBy("id", "desc")->first();
                            if (!is_null($sslRecord)) {
                                if (!$reissue && $sslRecord->status == \WHMCS\Service\Ssl::STATUS_COMPLETED) {
                                    return new \WHMCS\Http\Message\JsonResponse(["status" => "cert_already_installed"]);
                                }
                                if ($sslRecord->status == \WHMCS\Service\Ssl::STATUS_CANCELLED) {
                                    return new \WHMCS\Http\Message\JsonResponse(["status" => "not_awaiting_notification"]);
                                }
                                $server = \WHMCS\Module\Server::factoryFromModel($model);
                                $params = ["isReissue" => $reissue];
                                try {
                                    $installResponse = $server->call("install_certificate", $params);
                                    if (is_array($installResponse) && !empty($installResponse["success"]) && empty($installResponse["response"])) {
                                        if ($installResponse["success"] === true) {
                                            $sslRecord->refresh();
                                            $sslRecord->status = \WHMCS\Service\Ssl::STATUS_COMPLETED;
                                            $sslRecord->save();
                                            $sslRecord->sendEmail(\WHMCS\Service\Ssl::EMAIL_INSTALLED);
                                            return new \WHMCS\Http\Message\JsonResponse(["status" => "cert_installed"]);
                                        }
                                        return new \WHMCS\Http\Message\JsonResponse(["status" => "order_not_found"]);
                                    }
                                    if ($installResponse === \WHMCS\Module\Server::FUNCTIONDOESNTEXIST || is_array($installResponse) && !empty($installResponse["success"]) && !empty($installResponse["response"])) {
                                        $sslRecord->status = \WHMCS\Service\Ssl::STATUS_REISSUED;
                                        $sslRecord->save();
                                        $sslRecord->sendEmail(\WHMCS\Service\Ssl::EMAIL_ISSUED);
                                        return new \WHMCS\Http\Message\JsonResponse(["status" => "auto_install_not_possible"]);
                                    }
                                    if (is_array($installResponse) && !empty($installResponse["growl"]["message"])) {
                                        $message = $installResponse["growl"]["message"];
                                    } else {
                                        if (is_array($installResponse) && !empty($installResponse["error"])) {
                                            $message = $installResponse["error"];
                                        } else {
                                            if (is_string($installResponse)) {
                                                $message = $installResponse;
                                            } else {
                                                $message = "An unknown error occurred.";
                                            }
                                        }
                                    }
                                    if (\WHMCS\Service\Ssl::MC_MAX_CALLBACK_ATTEMPTS <= $attempt) {
                                        $sslRecord->sendEmail(\WHMCS\Service\Ssl::EMAIL_ISSUED);
                                    }
                                    return new \WHMCS\Http\Message\JsonResponse(["status" => "install_error:" . $message]);
                                } catch (\Throwable $e) {
                                    return new \WHMCS\Http\Message\JsonResponse(["status" => "install_error:" . $e->getMessage()]);
                                }
                            }
                        }
                }
            }
        }
    }

    public function manage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = new Output\ClientArea();
        $ca->setPageTitle(\Lang::trans("navManageSsl"));
        $ca->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $ca->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"));
        $ca->addToBreadCrumb(routePath("clientarea-ssl-certificates-manage"), \Lang::trans("store.ssl.title"));
        $ca->initPage();
        $ca->requireLogin();
        $sslProducts = \WHMCS\Service\Ssl::with("service", "service.product", "addon", "addon.productAddon", "addon.productAddon.moduleConfiguration", "addon.service", "addon.service.product")->where("userid", "=", \Auth::client()->id)->where(function ($query) {
            $query->has("service")->orHas("addon");
        })->get()->all();
        $ca->assign("sslProducts", $sslProducts);
        $ca->assign("sslStatusAwaitingIssuance", \WHMCS\Service\Ssl::STATUS_AWAITING_ISSUANCE);
        $ca->assign("sslStatusAwaitingConfiguration", \WHMCS\Service\Ssl::STATUS_AWAITING_CONFIGURATION);
        $ca->setTemplate("managessl");
        return $ca;
    }

    public function resendApproverEmail(\WHMCS\Http\Message\ServerRequest $request)
    {
        $serviceId = $request->get("serviceId");
        $addonId = $request->get("addonId");
        \Auth::requireLoginAndClient();
        $loggedInUserId = \Auth::client()->id;
        $moduleInterface = new \WHMCS\Module\Server();
        if ($addonId) {
            $ownerId = \WHMCS\Service\Addon::find($addonId)->clientId;
            $moduleInterface->loadByAddonId($addonId);
        } else {
            $ownerId = \WHMCS\Service\Service::find($serviceId)->clientId;
            $moduleInterface->loadByServiceID($serviceId);
        }
        if ($loggedInUserId != $ownerId) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "message" => "Access Denied"], 403);
        }
        try {
            $result = $moduleInterface->call("resendApproverEmail");
            if ($result == "success") {
                return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
            }
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "message" => "Unable to resend approver email"], 500);
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "message" => "Unable to resend approver email"], 500);
        }
    }

    public function competitiveUpgrade(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \Laminas\Diactoros\Response\RedirectResponse(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug(MarketConnect::SERVICE_SYMANTEC)));
    }

    public function validateCompetitiveUpgrade(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \Laminas\Diactoros\Response\RedirectResponse(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug(MarketConnect::SERVICE_SYMANTEC)));
    }
}
