<?php

function marketconnect_MetaData()
{
    return ["DisplayName" => "WHMCS MarketConnect", "APIVersion" => "1.1", "RequiresServer" => false, "NoEditModuleSettings" => true, "NoEditPricing" => true, "ConfigurationLink" => "marketconnect.php?manage=:service", "ChangePackageLabel" => "Upgrade", "AutoGenerateUsernameAndPassword" => false];
}

function marketconnect_ConfigOptions()
{
    return ["Service" => ["Type" => "dropdown", "Options" => marketconnect_GetServices()], "Term" => ["Type" => "dropdown", "Options" => "Use Billing Cycle,1,3,6,12,24,36"]];
}

function marketconnect_CreateAccount($params)
{
    marketconnect_processParamsForReplacements($params);
    try {
        $term = marketconnect_DetermineTerm($params);
        $status = $params["status"];
        $existingOrder = $params["model"]->serviceProperties->get("Order Number");
        if (!empty($existingOrder) && $status != "Pending") {
            throw new WHMCS\Exception("This service has already been provisioned. Please cancel the existing order before attempting to re-provision it.");
        }
        if (!$existingOrder) {
            $api = new WHMCS\MarketConnect\Api();
            $order = $api->purchase($params["configoption1"], $term, $params["qty"]);
            $orderNumber = $order["order_number"];
            $params["model"]->serviceProperties->save(["Order Number" => $orderNumber]);
        }
        $model = $params["model"];
        try {
            WHMCS\MarketConnect\Provision::factoryFromModel($model)->provision($params);
        } catch (Exception $e) {
            $serviceId = $model->id;
            $addonId = 0;
            if ($model instanceof WHMCS\Service\Addon) {
                $serviceId = $model->serviceId;
                $addonId = $model->id;
            }
            $serviceOrAddon = "Service ID: " . $serviceId;
            if ($addonId) {
                $serviceOrAddon = "Addon ID: " . $addonId . " - " . $serviceOrAddon;
            }
            $error = $e->getMessage();
            logActivity("MarketConnect Configuration Failed - " . $serviceOrAddon . " - Error: " . $error, $params["userid"]);
        }
        return "success";
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function marketconnect_TerminateAccount($params)
{
    marketconnect_processParamsForReplacements($params);
    try {
        WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->cancel($params);
        $params["model"]->serviceProperties->save(["Order Number" => ""]);
        return "success";
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function marketconnect_Renew($params)
{
    marketconnect_processParamsForReplacements($params);
    try {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber) {
            throw new WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to renew it.");
        }
        WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->renew($params);
        return "success";
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function marketconnect_SSLStepOne($params)
{
    marketconnect_processParamsForReplacements($params);
    $productParts = explode("_", $params["configoption1"]);
    if (!in_array($productParts[0], WHMCS\MarketConnect\Services\Symantec::SSL_TYPES)) {
        return NULL;
    }
}

function marketconnect_SSLStepTwo($params)
{
    marketconnect_processParamsForReplacements($params);
    $productParts = explode("_", $params["configoption1"]);
    if (!in_array($productParts[0], WHMCS\MarketConnect\Services\Symantec::SSL_TYPES)) {
        return [];
    }
    try {
        $orderNumber = marketconnect_GetOrderNumber($params);
        $api = new WHMCS\MarketConnect\Api();
        $data = $api->extra("getapprovalmethods", ["order_number" => $orderNumber, "csr" => $params["csr"]]);
        $csr = $data["csrData"];
        return ["approvalmethods" => $data["approvalmethods"], "approveremails" => $data["approveremails"], "displaydata" => ["Domain Name" => $csr["DomainName"] ?? $csr["CN"], "Organization" => $csr["Organization"] ?? $csr["O"], "Organization Unit" => $csr["OrganizationUnit"] ?? $csr["OU"], "Locality" => $csr["Locality"] ?? $csr["L"], "Country" => $csr["Country"] ?? $csr["C"]]];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function marketconnect_SSLStepThree($params)
{
    marketconnect_processParamsForReplacements($params);
    $productParts = explode("_", $params["configoption1"]);
    if (!in_array($productParts[0], WHMCS\MarketConnect\Services\Symantec::SSL_TYPES)) {
        return [];
    }
    try {
        $response = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->configure($params);
        return array_merge(["success" => true], $response);
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function marketconnect_AdminCustomButtonArray($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->adminManagementButtons($params);
}

function marketconnect_AdminServicesTabFields($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->adminServicesTabOutput($params);
}

function marketconnect_ChangePackage($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->upgrade($params);
}

function marketconnect_resend($params)
{
    try {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber) {
            throw new WHMCS\Exception("This order has not yet been provisioned.");
        }
        $sslOrderDetails = marketconnect_GetSslOrderDetails($orderNumber);
        if (!$sslOrderDetails) {
            throw new WHMCS\Exception("SSL configuration profile not found.");
        }
        $sslOrderDetails->sendEmail(WHMCS\Service\Ssl::EMAIL_CONFIGURATION_REQUIRED);
        return ["growl" => ["message" => "Configuration email sent successfully"]];
    } catch (Exception $e) {
        return ["growl" => ["type" => "error", "message" => $e->getMessage()]];
    }
}

function marketconnect_resendApproverEmail($params)
{
    try {
        $orderNumber = marketconnect_GetOrderNumber($params);
        $api = new WHMCS\MarketConnect\Api();
        $api->extra("resendapproveremail", ["order_number" => $orderNumber]);
        return "success";
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function marketconnect_admin_resend_approver_email($params)
{
    $response = marketconnect_resendapproveremail($params);
    if ($response == "success") {
        return ["growl" => ["message" => "Approver email re-sent successfully!"]];
    }
    return ["growl" => ["type" => "error", "message" => $response]];
}

function marketconnect_refreshStatus($params)
{
    try {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber) {
            throw new WHMCS\Exception();
        }
        $api = new WHMCS\MarketConnect\Api();
        $response = $api->status($orderNumber);
        WHMCS\MarketConnect\OrderInformation::cache($orderNumber, $response);
    } catch (Exception $e) {
        $response = ["status" => "Order not found", "statusDescription" => "Order not found. Please check order number and try again."];
        WHMCS\MarketConnect\OrderInformation::cache($orderNumber, $response);
    }
    $data = marketconnect_adminservicestabfields($params);
    return ["statusOutput" => current($data)];
}

function marketconnect_RetrieveCertificate($params)
{
    $orderNumber = marketconnect_GetOrderNumber($params);
    $sslOrderDetails = marketconnect_GetSslOrderDetails($orderNumber);
    if (!is_null($sslOrderDetails) && $sslOrderDetails->status == WHMCS\Service\Ssl::STATUS_CANCELLED) {
        throw new WHMCS\Exception("This certificate order has been cancelled.");
    }
    $api = new WHMCS\MarketConnect\Api();
    $certificateData = $api->extra("getcertificate", ["order_number" => $orderNumber]);
    if ($sslOrderDetails->status !== WHMCS\Service\Ssl::STATUS_COMPLETED) {
        $sslOrderDetails->status = WHMCS\Service\Ssl::STATUS_COMPLETED;
        $sslOrderDetails->save();
    }
    return $certificateData["certificate"];
}

function marketconnect_admin_retrieve_certificate($params)
{
    try {
        $certificate = marketconnect_retrievecertificate($params);
        return ["body" => "<div class=\"alert alert-success\">\n                <i class=\"fas fa-check fa-fw\"></i> The certificate has been successfully retrieved.\n            </div>\n            <textarea class=\"form-control\" rows=\"15\">" . $certificate . "</textarea>"];
    } catch (WHMCS\Exception $e) {
        return ["body" => "<div class=\"alert alert-danger\" role=\"alert\">\n                <i class=\"fas fa-times fa-fw\"></i> " . $e->getMessage() . "\n            </div>"];
    }
}

function marketconnect_client_retrieve_certificate($params)
{
    $certificate = "";
    $errorMessage = "";
    try {
        $certificate = marketconnect_retrievecertificate($params);
    } catch (WHMCS\Exception $e) {
        $errorMessage = $e->getMessage();
    }
    return ["overrideDisplayTitle" => Lang::trans("ssl.retrieveCertificate"), "appendToBreadcrumb" => [["#", Lang::trans("ssl.retrieveCertificate")]], "outputTemplateFile" => "ssl/retrieve", "templateVariables" => ["actionName" => "client_retrieve_certificate", "certificate" => $certificate, "errorMessage" => $errorMessage]];
}

function marketconnect_ChangeApproverEmail($params, $newApproverEmail)
{
    $approverEmails = [];
    $approverEmailChangeSuccess = false;
    $errorMessage = "";
    $orderNumber = marketconnect_GetOrderNumber($params);
    $sslOrderDetails = marketconnect_GetSslOrderDetails($orderNumber);
    if (!is_null($sslOrderDetails) && $sslOrderDetails->status == WHMCS\Service\Ssl::STATUS_CANCELLED) {
        $errorMessage = "This certificate order has been cancelled.";
    }
    if (!$errorMessage) {
        try {
            if ($newApproverEmail) {
                check_token();
                $api = new WHMCS\MarketConnect\Api();
                $data = $api->extra("changeapproveremail", ["approveremail" => $newApproverEmail, "order_number" => $orderNumber]);
                $approverEmailChangeSuccess = true;
                $data = $sslOrderDetails->configurationData;
                $data["approveremail"] = $newApproverEmail;
                $sslOrderDetails->configurationData = $data;
                $sslOrderDetails->save();
            }
        } catch (WHMCS\Exception $e) {
            $errorMessage = $e->getMessage();
        }
        try {
            if (!$approverEmailChangeSuccess) {
                $api = new WHMCS\MarketConnect\Api();
                $data = $api->extra("getapproveremails", ["order_number" => $orderNumber, "domain" => $params["domain"]]);
                if (isset($data["approveremails"]) && is_array($data["approveremails"])) {
                    $approverEmails = $data["approveremails"];
                } else {
                    $errorMessage = "An error occurred while attempting to retrieve the approver emails.";
                }
            }
        } catch (WHMCS\Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
    return ["approverEmails" => $approverEmails, "approverEmailChangeSuccess" => $approverEmailChangeSuccess, "newApproverEmail" => $newApproverEmail, "errorMessage" => $errorMessage];
}

function marketconnect_admin_change_approver_email($params)
{
    $newApproverEmail = App::getFromRequest("approver_email");
    $response = marketconnect_changeapproveremail($params, $newApproverEmail);
    $return = [];
    if ($response["errorMessage"]) {
        $return["body"] = "<div class=\"alert alert-danger\" role=\"alert\">" . $response["errorMessage"] . "</div>";
    } else {
        if ($response["approverEmailChangeSuccess"]) {
            $return["dismiss"] = true;
            $return["successMsgTitle"] = "Success!";
            $return["successMsg"] = "Approver email changed successfully";
        } else {
            $emailsList = "";
            foreach ($response["approverEmails"] as $approverEmail) {
                $emailsList .= "<label class=\"radio-inline\">\n    <input type=\"radio\" name=\"approver_email\" value=\"" . $approverEmail . "\">\n    " . $approverEmail . "\n</label>\n<br>";
            }
            if ($emailsList) {
                $userId = $params["userid"];
                $serviceId = $params["serviceid"];
                $addonId = $params["addonId"];
                $emailsList = "<p>Choose an approver email from below to switch to email based validation and update the approver email.</p>\n            <form method=\"post\" action=\"?userid=" . $userId . "&id=" . $serviceId . ($addonId ? "&aid=" . $addonId : "") . "&modop=custom&ac=admin_change_approver_email\">\n                " . generate_token() . "\n                <input type=\"hidden\" name=\"changeemail\" value=\"1\">\n                <blockquote>" . $emailsList . "</blockquote>\n            </form>";
            }
            $return["body"] = $emailsList;
        }
    }
    return $return;
}

function marketconnect_client_change_approver_email($params)
{
    $newApproverEmail = App::getFromRequest("approver_email");
    $response = marketconnect_changeapproveremail($params, $newApproverEmail);
    return ["overrideDisplayTitle" => Lang::trans("ssl.changeApproverEmail"), "appendToBreadcrumb" => [["#", Lang::trans("ssl.changeApproverEmail")]], "outputTemplateFile" => "ssl/changeapproveremail", "templateVariables" => array_merge($response, ["actionName" => "client_change_approver_email", "addonId" => $params["addonId"] ?? 0])];
}

function marketconnect_client_reissue_certificate($params)
{
    $template = "ssl/reissue";
    $approverEmails = [];
    $errorMessage = "";
    $csr = App::getFromRequest("csr");
    $approverEmail = "";
    $csrData = [];
    $sslOrderDetails = marketconnect_GetSslOrderDetails(marketconnect_GetOrderNumber($params));
    if ($sslOrderDetails && $sslOrderDetails->status == WHMCS\Service\Ssl::STATUS_CANCELLED) {
        $errorMessage = "This certificate order has been cancelled.";
    } else {
        if ($sslOrderDetails) {
            if ($csr) {
                check_token();
                try {
                    $api = new WHMCS\MarketConnect\Api();
                    $data = $api->extra("reissue", ["order_number" => $sslOrderDetails->remoteId, "csr" => $csr, "callback_url" => fqdnRoutePath("store-reissued-ssl-callback")]);
                    $template = "ssl/reissue-complete";
                    $sslOrderDetails->status = WHMCS\Service\Ssl::STATUS_REISSUE_PENDING;
                    $reissue = ["date" => WHMCS\Carbon::now()->toDateTimeString(), "validationValue" => $approverEmail, "validationMethod" => WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL];
                    $method = new WHMCS\Service\Ssl\ValidationMethodEmailauth();
                    $method->email = $approverEmail;
                    $sslOrderDetails->authenticationData = $method;
                    $sslOrderDetails->logReissue($reissue);
                    $sslOrderDetails->resetReissueAttempts();
                    $sslOrderDetails->save();
                } catch (WHMCS\Exception $e) {
                    $errorMessage = $e->getMessage();
                }
            }
        } else {
            $errorMessage = "This certificate is not in a status that allows it to be reissued.";
        }
    }
    return ["overrideDisplayTitle" => Lang::trans("ssl.reissueCertificate"), "appendToBreadcrumb" => [["#", Lang::trans("ssl.reissueCertificate")]], "outputTemplateFile" => $template, "templateVariables" => ["actionName" => "client_reissue_certificate", "csr" => $csr, "approverEmails" => $approverEmails, "approverEmail" => $approverEmail, "csrData" => $csrData, "errorMessage" => $errorMessage, "addonid" => $params["addonId"], "serviceid" => $params["serviceid"]]];
}

function marketconnect_install_certificate($params)
{
    try {
        $response = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->install($params);
        return ["growl" => ["message" => "Certificate installed successfully!"], "success" => true, "response" => $response];
    } catch (Exception $e) {
        return ["growl" => ["type" => "error", "message" => $e->getMessage()]];
    }
}

function marketconnect_GetOrderNumber($params)
{
    if (isset($params["customfields"]["Order Number"]) && 0 < strlen($params["customfields"]["Order Number"]) && is_numeric($params["customfields"]["Order Number"])) {
        return $params["customfields"]["Order Number"];
    }
    $orderNumber = $params["model"]->serviceProperties;
    if (is_null($orderNumber)) {
        return "";
    }
    if ($orderNumber->get("Order Number")) {
        return $orderNumber->get("Order Number");
    }
    if (!$orderNumber && $params["model"]->status != "Pending") {
        throw new WHMCS\Exception("You must provision this service before attempting to manage it.");
    }
    return "";
}

function marketconnect_DetermineTerm($params)
{
    $term = $params["configoption2"];
    if (is_numeric($term)) {
        return $term;
    }
    $billingCycle = str_replace("-", "", strtolower($params["model"]->billingCycle));
    $terms = ["one time" => "0", "monthly" => "1", "quarterly" => "3", "semiannually" => "6", "annually" => "12", "biennially" => "24", "triennially" => "36", "free account" => "100"];
    if (!array_key_exists($billingCycle, $terms)) {
        throw new WHMCS\Exception("Non-recurring billing cycle selected. Unable to convert to valid term.");
    }
    return $terms[$billingCycle];
}

function marketconnect_GetServices()
{
    $services = [];
    try {
        $api = new WHMCS\MarketConnect\Api();
        foreach ($api->servicesOnly() as $service) {
            if (isset($service["services"])) {
                foreach ($service["services"] as $subservice) {
                    $services[$subservice["id"]] = $subservice["display_name"];
                }
            }
        }
    } catch (Exception $e) {
        $services[] = "Error: " . $e->getMessage();
    }
    return $services;
}

function marketconnect_GetSslOrderDetails($orderNumber)
{
    if (empty($orderNumber)) {
        return NULL;
    }
    return WHMCS\Service\Ssl::findForMarketConnectOrder($orderNumber);
}

function marketconnect_ssl_domain_validation($params)
{
    $view = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->customArgs("viewManager", defined("CLIENTAREA") ? "client" : "admin", $params["model"]);
    return ["jsonResponse" => ["success" => true, "body" => $view->renderDomainControlValidation()]];
}

function marketconnect_manage_order($params)
{
    marketconnect_processParamsForReplacements($params);
    try {
        $orderNumber = marketconnect_getordernumber($params);
        if (!$orderNumber) {
            throw new WHMCS\Exception("Unable to perform single sign-on.");
        }
        $api = new WHMCS\MarketConnect\Api();
        $response = $api->ssoForOrder($orderNumber);
        return ["jsonResponse" => ["success" => true, "redirect" => "window|" . $response["redirect_url"]]];
    } catch (Exception $e) {
        return ["jsonResponse" => ["success" => false, "error" => "Unable to login: " . $e->getMessage()]];
    }
}

function marketconnect_admin_sso($params)
{
    marketconnect_processParamsForReplacements($params);
    try {
        $orderNumber = marketconnect_getordernumber($params);
        if (!$orderNumber) {
            throw new WHMCS\Exception("You must provision this service before attempting to login to it.");
        }
        $api = new WHMCS\MarketConnect\Api();
        $response = $api->ssoForOrder($orderNumber);
        return ["redirectUrl" => $response["redirect_url"]];
    } catch (Exception $e) {
        return ["growl" => ["type" => "error", "message" => $e->getMessage()]];
    }
}

function marketconnect_ClientAreaAllowedFunctions($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->clientAreaAllowedFunctions($params);
}

function marketconnect_ClientArea($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->clientAreaOutput($params);
}

function marketconnect_get_configuration_link($params)
{
    marketconnect_processParamsForReplacements($params);
    $metaData = marketconnect_metadata();
    $link = $metaData["ConfigurationLink"];
    $type = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->getServiceType();
    switch ($type) {
        case WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_RAPIDSSL:
        case WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_GEOTRUST:
        case WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_DIGICERT:
            $type = WHMCS\MarketConnect\MarketConnect::SERVICE_SYMANTEC;
            break;
        default:
            return str_replace(":service", $type, $link);
    }
}

function marketconnect_update_ftp_details($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->updateFtpDetails($params);
}

function marketconnect_update_ftp_details_form($params)
{
    marketconnect_processParamsForReplacements($params);
    $modal = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->getFtpDetailsForm($params);
    if ($modal === NULL) {
        return new WHMCS\Http\Message\JsonResponse([]);
    }
    return new WHMCS\Http\Message\JsonResponse(["body" => $modal->body, "title" => $modal->title]);
}

function marketconnect_resend_configuration_data($params)
{
    marketconnect_processParamsForReplacements($params);
    try {
        $orderNumber = marketconnect_getordernumber($params);
        if (!$orderNumber) {
            throw new WHMCS\Exception("This order has not yet been provisioned.");
        }
        $provision = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"]);
        $params["isSslAutoConfigurationAttempt"] = $provision->isSslProduct();
        $provision->configure($params);
        return ["growl" => ["message" => "Auto-configuration completed successfully!"]];
    } catch (Exception $e) {
        return ["growl" => ["type" => "error", "message" => $e->getMessage()]];
    }
}

function marketconnect_check_auto_install_panels($params)
{
    marketconnect_processParamsForReplacements($params);
    $supportedParentModules = WHMCS\MarketConnect\Provision::AUTO_INSTALL_PANELS;
    $model = $params["model"];
    $checkIfSupported = false;
    $module = $service = NULL;
    if ($model instanceof WHMCS\Service\Addon) {
        $module = $model->service->product->module;
        $service = $model->service;
        $checkIfSupported = true;
    } else {
        $relatedHostingService = WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
        if ($relatedHostingService) {
            $checkIfSupported = true;
            $module = $relatedHostingService->product->module;
            $service = $relatedHostingService;
        }
    }
    if ($checkIfSupported) {
        return ["supported" => in_array($module, $supportedParentModules), "panel" => WHMCS\Module\Server::factoryFromModel($service)->getDisplayName()];
    }
    return ["supported" => false];
}

function marketconnect_generate_csr($params)
{
    marketconnect_processParamsForReplacements($params);
    $supportedParentModules = WHMCS\MarketConnect\Provision::AUTO_INSTALL_PANELS;
    $model = $params["model"];
    $relatedHostingService = NULL;
    if ($model instanceof WHMCS\Service\Service) {
        $relatedHostingService = WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
    }
    if ($model instanceof WHMCS\Service\Addon && in_array($params["model"]->service->product->module, $supportedParentModules) || $relatedHostingService instanceof WHMCS\Service\Service && in_array($relatedHostingService->product->module, $supportedParentModules)) {
        return ["body" => WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->generateCsr()];
    }
    return ["body" => ["csr" => false]];
}

function marketconnect_entity_specific_merge_data($params)
{
    marketconnect_processParamsForReplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->emailMergeData($params);
}

function marketconnect_custom($params)
{
    marketconnect_processParamsForReplacements($params);
    $response = WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->custom($params);
    if (is_array($response) && !empty($response["error"])) {
        return $response["error"];
    }
    return $response;
}

function marketconnect_getUsedQuantity($params)
{
    marketconnect_processParamsForReplacements($params);
    return (int) WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->getUsedQuantity($params);
}

function marketconnect_processParamsForReplacements(&$params)
{
    $productInfo = explode("_", $params["configoption1"]);
    if (!empty(WHMCS\MarketConnect\MarketConnect::SERVICES[$productInfo[0]]["service_replace_prefix"])) {
        $replaceWith = WHMCS\MarketConnect\MarketConnect::SERVICES[$productInfo[0]]["service_replace_prefix"];
        $params["configoption1"] = $replaceWith . "_" . $productInfo[1];
    }
}

function marketconnect_get_site_seal($params)
{
    $api = new WHMCS\MarketConnect\Api();
    $errorMessage = "";
    $seal = "";
    try {
        $orderNumber = $params["model"]->serviceProperties->get("Order Number");
        $sslOrderDetails = marketconnect_getsslorderdetails($orderNumber);
        if (!is_null($sslOrderDetails) && $sslOrderDetails->status == WHMCS\Service\Ssl::STATUS_CANCELLED) {
            throw new WHMCS\Exception("This certificate order has been cancelled.");
        }
        $seal = $api->extra("get_seal", ["order_number" => $orderNumber]);
        $seal = $seal["seal_code"];
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
    return ["overrideDisplayTitle" => Lang::trans("ssl.siteSeal"), "appendToBreadcrumb" => [["#", Lang::trans("ssl.getSiteSeal")]], "outputTemplateFile" => "ssl/seal", "templateVariables" => ["actionName" => "seal", "seal" => $seal, "rapidCertificate" => substr($params["configoption1"], 0, 9) === "rapidssl_", "errorMessage" => $errorMessage]];
}

function marketconnect_reissue_certificate($params)
{
    marketconnect_processparamsforreplacements($params);
    return WHMCS\MarketConnect\Provision::factoryFromModel($params["model"])->adminReissueSsl($params);
}

function marketconnect_list_reissues($params)
{
    marketconnect_processparamsforreplacements($params);
    $sslOrder = WHMCS\Service\Ssl::factoryFromService($params["model"]);
    return ["jsonResponse" => ["success" => true, "body" => moduleView("marketconnect", "list_reissues", ["sslOrder" => $sslOrder], "servers")]];
}
