<?php

namespace WHMCS\MarketConnect\Services;

class Symantec extends AbstractService
{
    protected $installResult = NULL;
    const WELCOME_EMAIL_TEMPLATE = \WHMCS\Service\Ssl::EMAIL_CONFIGURATION_REQUIRED;
    const SSL_TYPE_RAPIDSSL = "rapidssl";
    const SSL_TYPE_GEOTRUST = "geotrust";
    const SSL_TYPE_DIGICERT = "digicert";
    const SSL_STATUS_INSTANT_ISSUANCE_CERT_INSTALLED = "cert_installed";
    const SSL_STATUS_INSTANT_ISSUANCE_INSTALL_FAILURE = "install_error";
    const SSL_STATUS_INSTANT_ISSUANCE_OTHER_FAILURE = "other_error";
    const SSL_STATUS_INSTANT_ISSUANCE_TOKEN_DEPLOY_FAILURE = "token_deploy_failure";
    const SSL_STATUS_INSTANT_ISSUANCE_TOKEN_REQUEST_FAILURE = "token_request_failure";
    const SSL_STATUS_INSTANT_ISSUANCE_DV_FAILURE = "dv_failure";
    const SSL_TYPES = NULL;

    public function getServiceIdent()
    {
        return "ssl";
    }

    public function provision($model, $params = NULL)
    {
        unset($params);
        $sslOrder = \WHMCS\Service\Ssl::factoryFromService($model, \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT);
        if (is_null($sslOrder)) {
            $sslOrder = \WHMCS\Service\Ssl::newFromService($model, \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT);
        }
        if (!$sslOrder->getOrderNumber()) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to manage it.");
        }
        $sslOrder->statePending()->save();
        $manualConfiguration = true;
        try {
            if ($sslOrder->canConfigure() && empty($this->configure($sslOrder->getOwningService(), [])["error"])) {
                $manualConfiguration = false;
            }
        } catch (\Exception $e) {
            logActivity(sprintf("Automatic %s certificate configuration failed (%s) - %s: %d", $sslOrder->getProduct()->name, $e->getMessage(), $sslOrder->isAddon() ? "Addon ID" : "Service ID", $sslOrder->getOwningService()->id), $sslOrder->userId);
        }
        if ($manualConfiguration) {
            $this->requestManualConfiguration($sslOrder);
        }
    }

    protected function getRequestTokenApiCall($payload, \WHMCS\Service\Ssl $ssl)
    {
        $api = new \WHMCS\MarketConnect\Api();
        try {
            $payloadData = $payload->finalize();
            $requestTokenData = $api->extra("getrequesttoken", $payloadData);
            if ($payload instanceof \WHMCS\MarketConnect\Ssl\Configuration) {
                return new \WHMCS\MarketConnect\Ssl\ApiConfigureResult($requestTokenData, $payload);
            }
            if ($payload instanceof \WHMCS\MarketConnect\Ssl\Renew) {
                return new \WHMCS\MarketConnect\Ssl\ApiRenewResult($requestTokenData);
            }
            $payloadClass = get_class($payload);
            throw new \WHMCS\Exception("Invalid payload class: " . $payloadClass);
        } catch (\Throwable $e) {
            logActivity(sprintf("SSL Instant Issuance failed for %s (%s). %s: %d", $payload->getDomain(), $e->getMessage(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
            throw $e;
        }
    }

    protected function reportInstantIssuanceInstallStatus($ssl, $installationStatus = NULL, $errorMessage)
    {
        try {
            $acceptedStatuses = [self::SSL_STATUS_INSTANT_ISSUANCE_CERT_INSTALLED, self::SSL_STATUS_INSTANT_ISSUANCE_INSTALL_FAILURE, self::SSL_STATUS_INSTANT_ISSUANCE_OTHER_FAILURE];
            if (!in_array($installationStatus, $acceptedStatuses)) {
                throw new \WHMCS\Exception("Invalid status: " . $installationStatus);
            }
            $api = new \WHMCS\MarketConnect\Api();
            $api->extra("instantissuancereport", $payload = ["order_number" => $ssl->getOrderNumber(), "status" => $installationStatus, "error_msg" => (string) $errorMessage]);
        } catch (\Throwable $e) {
            logActivity(sprintf("Failed to report SSL Instant Issuance Installation status for %s (%s). %s: %d", $ssl->getDomain(), $e->getMessage(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
        }
    }

    protected function sslSupportsInstantIssuance($ssl)
    {
        return $ssl->isProductCategory(\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_DV);
    }

    protected function configureAndAutomateInstall(\WHMCS\ServiceInterface $model, \WHMCS\MarketConnect\Ssl\Configuration $payload, \WHMCS\Service\Ssl $ssl)
    {
        $supportsInstantIssuance = $this->sslSupportsInstantIssuance($ssl);
        $attemptInstantIssuance = false;
        $isInstantIssuanceSuccessful = false;
        $instantIssuanceReportStatus = NULL;
        $instantIssuanceErrorMessage = NULL;
        $requestTokenResult = NULL;
        $certificate = NULL;
        if ($supportsInstantIssuance) {
            try {
                $requestTokenResult = $this->getRequestTokenApiCall($payload, $ssl);
            } catch (\Throwable $e) {
                $instantIssuanceErrorMessage = "Request token could not be obtained: " . $e->getMessage();
            }
        }
        if ($requestTokenResult) {
            try {
                $this->automateDomainControlValidation($ssl, $requestTokenResult);
                $attemptInstantIssuance = true;
            } catch (\Throwable $e) {
                $instantIssuanceErrorMessage = "Request token could not be deployed: " . $e->getMessage();
                $instantIssuanceReportStatus = self::SSL_STATUS_INSTANT_ISSUANCE_TOKEN_DEPLOY_FAILURE;
            }
        }
        $payload->setUseInstantIssuance($attemptInstantIssuance);
        try {
            $apiCallResult = $this->configureApiCall($payload, $model);
            if ($attemptInstantIssuance) {
                $isInstantIssuanceSuccessful = $apiCallResult->hasCertificate();
                if ($isInstantIssuanceSuccessful) {
                    $certificate = $apiCallResult->getCertificate();
                }
            }
        } catch (\Throwable $e) {
            if (!$attemptInstantIssuance) {
                throw $e;
            }
            $instantIssuanceErrorMessage = "Instant Issuance Failed: " . $e->getMessage();
            $payload->setUseInstantIssuance(false);
            $apiCallResult = $this->configureApiCall($payload, $model);
        }
        if ($attemptInstantIssuance && !$isInstantIssuanceSuccessful && is_null($instantIssuanceErrorMessage)) {
            $instantIssuanceErrorMessage = "No certificate received from DigiCert";
        }
        $ssl->configurationData = array_merge($payload->cleanForPersistence(), ["instant_issuance_eligible" => $supportsInstantIssuance, "instant_issuance_attempted" => $attemptInstantIssuance, "instant_issuance_successful" => $isInstantIssuanceSuccessful, "instant_issuance_error" => $instantIssuanceErrorMessage]);
        $ssl->status = \WHMCS\Service\Ssl::STATUS_AWAITING_ISSUANCE;
        $ssl->save();
        if ($attemptInstantIssuance) {
            logActivity(sprintf("SSL Instant Issuance %s for %s, automatic installation is pending. %s: %d", $isInstantIssuanceSuccessful ? "succeeded" : "failed", $payload->getDomain(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
        }
        if (!$attemptInstantIssuance || !$isInstantIssuanceSuccessful) {
            if ($attemptInstantIssuance) {
                $errorMessage = $instantIssuanceErrorMessage ?? "Unknown error";
            } else {
                $errorMessage = NULL;
            }
            if ($instantIssuanceReportStatus) {
                $this->reportInstantIssuanceInstallStatus($ssl, $instantIssuanceReportStatus, $errorMessage);
            }
            return $this->automateDomainControlValidation($ssl, $apiCallResult);
        }
        $sslData = $ssl->configurationData;
        $sslData["order_expiry"] = $apiCallResult->getOrderExpiry();
        $certExpiry = $apiCallResult->getCertificateExpiry();
        if ($certExpiry) {
            $certExpiry = \WHMCS\Carbon::safeCreateFromMySqlDate($certExpiry);
        }
        $ssl->configurationData = $sslData;
        $ssl->certificateExpiryDate = $certExpiry;
        $ssl->save();
        if ($this->callServerInstall($ssl, $certificate, $model) && is_null($this->installResult)) {
            $ssl->status = \WHMCS\Service\Ssl::STATUS_COMPLETED;
            $ssl->save();
            $ssl->sendEmail(\WHMCS\Service\Ssl::EMAIL_INSTALLED);
            $this->reportInstantIssuanceInstallStatus($ssl, self::SSL_STATUS_INSTANT_ISSUANCE_CERT_INSTALLED);
        } else {
            $ssl->sendEmail(\WHMCS\Service\Ssl::EMAIL_ISSUED);
            $this->reportInstantIssuanceInstallStatus($ssl, self::SSL_STATUS_INSTANT_ISSUANCE_INSTALL_FAILURE, is_string($this->installResult) ? $this->installResult : json_encode($this->installResult));
        }
        logActivity(sprintf("Instantly issued SSL certificate installation %s for %s. %s: %d", $ssl->status === \WHMCS\Service\Ssl::STATUS_COMPLETED ? "succeeded" : "failed", $payload->getDomain(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
    }

    public function configure($model, $params = NULL)
    {
        if ($model instanceof \WHMCS\Service\Addon || $model->hasServiceSurrogate()) {
            $ssl = $this->sslOrFail($model);
            $userDomainControlMethod = \WHMCS\Service\Ssl::normalizeToValidationMethod($params["configdata"]["approvalmethod"] ?? NULL);
            if ($ssl->canProvisioningModuleAutoConfigure($userDomainControlMethod)) {
                $serverInterface = $ssl->getProvisioningModule();
                $csrData = $this->generateCsr($model, $serverInterface, $params);
                $payload = $this->configurePayloadWithService($ssl, $model)->certificateSigningRequest($csrData["csr"], $serverInterface->getLoadedModule())->includeProvisioningModule($serverInterface);
                if (array_key_exists("configdata", $params)) {
                    $payload->validationMethodFromParams($params)->validateDomainEmailFromParams($params, $payload->emailUser("webmaster"));
                } else {
                    $this->defaultValidationMethod($payload, $ssl);
                }
                $validationDetails = $this->configureAndAutomateInstall($model, $payload, $ssl);
                if (!is_null($validationDetails)) {
                    return $validationDetails;
                }
                unset($validationDetails);
                return [];
            }
            unset($ssl);
            unset($userDomainControlMethod);
        }
        if (array_key_exists("isSslAutoConfigurationAttempt", $params) && $params["isSslAutoConfigurationAttempt"]) {
            throw new \WHMCS\MarketConnect\Exception\GeneralError("Unable to automatically configure SSL Certificate. Server module must be one of cPanel, Plesk or DirectAdmin for auto-configuration to be possible.");
        }
        $ssl = $this->sslOrFail($model);
        $validationDetails = $this->configureAndAutomateInstall($model, $this->configurePayloadWithParams($ssl, $params), $ssl);
        if (!is_null($validationDetails)) {
            return $validationDetails;
        }
        unset($validationDetails);
        return ["fileAuth" => false];
    }

    protected function configureApiCall($ApiConfigureResult, $payload, \WHMCS\Model\AbstractModel $model)
    {
        $rawResponse = (new \WHMCS\MarketConnect\Api())->configure($payload->finalize());
        $response = new \WHMCS\MarketConnect\Ssl\ApiConfigureResult($rawResponse, $payload);
        $this->assertApiSuccess($response);
        $this->applyCompetitiveUpgradeFreeMonths($model, $rawResponse);
        return $response;
    }

    protected function configurePayloadWithParams($Configuration, $ssl, $params)
    {
        $payload = new \WHMCS\MarketConnect\Ssl\Configuration($ssl);
        $payload->populate()->certificateSigningRequest($params["configdata"]["csr"], $params["configdata"]["servertype"])->contactsFromParams($params)->organisationFromParams($params)->validateDomainEmailFromParams($params, $payload->emailUser("webmaster"))->validationMethodFromParams($params);
        if (isset($params["configdata"]["domain"])) {
            $payload->domain($params["configdata"]["domain"]);
        }
        return $payload;
    }

    protected function configurePayloadWithService(\WHMCS\Service\Ssl $ssl, \WHMCS\ServiceInterface $service)
    {
        $payload = (new \WHMCS\MarketConnect\Ssl\Configuration($ssl))->populate();
        $serviceProperties = $service->getServiceProperties();
        $payload->contactsFromProperties($serviceProperties, $service->getServiceClient())->organisationFromProperties($serviceProperties, $service->getServiceClient());
        return $payload;
    }

    protected function renewApiCall($ApiRenewResult, $payload, \WHMCS\Service\Ssl $ssl)
    {
        $payload->finalize();
        $rawResponse = (new \WHMCS\MarketConnect\Api())->renew($payload->orderNumber, $payload->term, $payload->callbackUrl, $payload->useInstantIssuance);
        $response = new \WHMCS\MarketConnect\Ssl\ApiRenewResult($rawResponse, $this->resolveEmailForEmailValidation($ssl));
        $this->assertApiSuccess($response);
        return $response;
    }

    protected function renewPayloadWithParams($Renew, $ssl, $params)
    {
        return (new \WHMCS\MarketConnect\Ssl\Renew($ssl))->populate()->order(marketconnect_GetOrderNumber($params))->term((int) marketconnect_DetermineTerm($params));
    }

    protected function resolveEmailForEmailValidation($ssl)
    {
        $dcvEmail = NULL;
        if (!is_null($ssl->authenticationData)) {
            if ($ssl->authenticationData->is(\WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL)) {
                $dcvEmail = $ssl->authenticationData->email;
            }
        } else {
            if (!is_null($ssl->configurationData) && !empty($ssl->configurationData["approveremail"])) {
                $dcvEmail = $ssl->configurationData["approveremail"];
            }
        }
        return $dcvEmail;
    }

    protected function defaultValidationMethod(\WHMCS\MarketConnect\Ssl\Configuration $payload, \WHMCS\Service\Ssl $ssl)
    {
        $defaultUser = "webmaster";
        if ($ssl->getProductKey() == \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_WILDCARD) {
            $payload->validateDomainDns();
            $payload->validateDomainEmail($payload->emailUser($defaultUser));
        } else {
            if ($ssl->isProductCategory(\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_WILDCARD)) {
                $payload->validateDomainDns();
                $payload->validateDomainEmail($payload->emailUser($defaultUser));
            } else {
                if (!$ssl->isProductCategory(\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_DV)) {
                    $payload->validateDomainEmail($payload->emailUser($defaultUser));
                } else {
                    $payload->validateDomainFile();
                }
            }
        }
        return $payload;
    }

    protected function applyCompetitiveUpgradeFreeMonths($model, $configureResponse)
    {
        if (!isset($configureResponse["data"]["competitiveUpgradeFreeMonths"])) {
            return NULL;
        }
        $freeMonths = (int) $configureResponse["data"]["competitiveUpgradeFreeMonths"];
        if ($model->nextDueDate instanceof \WHMCS\Carbon) {
            $model->nextDueDate->addMonths($freeMonths);
        } else {
            $model->nextDueDate = \WHMCS\Carbon::createFromFormat("Y-m-d", $model->nextDueDate)->addMonths($freeMonths)->toDateString();
        }
        $model->nextInvoiceDate = $model->nextDueDate;
        $model->save();
    }

    public function cancel($model, $params = NULL)
    {
        $orderNumber = $model->serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception("No SSL Order exists for this product");
        }
        $response = (new \WHMCS\MarketConnect\Api())->cancel($orderNumber);
        if ($response["success"]) {
            $this->sslOrFail($model)->stateCancelled()->save();
        } else {
            throw new \WHMCS\Exception("Cancellation Failed");
        }
    }

    protected function callServerInstall($ssl, $certificate, \WHMCS\ServiceInterface $model)
    {
        $relatedHostingService = NULL;
        $this->installResult = NULL;
        if ($model instanceof \WHMCS\Service\Service) {
            $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
        }
        if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
            $serviceProperties = $model->serviceProperties;
            $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
            if (is_null($parentModel)) {
                throw new \WHMCS\Exception("Cannot find parent model");
            }
            switch ($parentModel->product->module) {
                case "cpanel":
                case "directadmin":
                case "plesk":
                    $serverInterface = \WHMCS\Module\Server::factoryFromModel($parentModel);
                    $this->installResult = $serverInterface->call("InstallSsl", ["certificateDomain" => $serviceProperties->get("Certificate Domain") ?: $parentModel->domain, "certificate" => $certificate, "csr" => $serviceProperties->get("Certificate Signing Request"), "key" => $serviceProperties->get("Certificate Private Key")]);
                    $ssl->status = \WHMCS\Service\Ssl::STATUS_COMPLETED;
                    $ssl->save();
                    return true;
                    break;
            }
        }
        return false;
    }

    public function install(\WHMCS\ServiceInterface $model, $params = [])
    {
        try {
            $serviceProperties = $model->serviceProperties;
            $orderNumber = $serviceProperties->get("Order Number");
            if (!$orderNumber) {
                throw new \WHMCS\Exception("No SSL Order exists for this product");
            }
            $serviceId = $model->id;
            $addonId = 0;
            if ($model instanceof \WHMCS\Service\Addon) {
                $serviceId = $model->service->id;
                $addonId = $model->id;
            }
            $sslOrder = \WHMCS\Service\Ssl::firstOrCreate(["userid" => $model->clientId, "serviceid" => $serviceId, "addon_id" => $addonId, "module" => "marketconnect"]);
            $api = new \WHMCS\MarketConnect\Api();
            $certificateData = $api->extra("getcertificate", ["order_number" => $orderNumber]);
            if (array_key_exists("error", $certificateData)) {
                throw new \WHMCS\Exception($certificateData["error"]);
            }
            $sslData = $sslOrder->configurationData;
            $sslData["order_expiry"] = $certificateData["order_expiry"] ?? NULL;
            $certExpiry = $certificateData["certificate_expiry"] ?? NULL;
            if ($certExpiry) {
                $certExpiry = \WHMCS\Carbon::safeCreateFromMySqlDate($certExpiry);
            }
            $sslOrder->configurationData = $sslData;
            $sslOrder->certificateExpiryDate = $certExpiry;
            $sslOrder->save();
            if (!$this->callServerInstall($sslOrder, $certificateData["certificate"], $model)) {
                return "unsupported";
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function clientAreaAllowedFunctions($params)
    {
        if ($params["status"] != "Active") {
            return [];
        }
        $sslOrder = \WHMCS\Service\Ssl::factoryFromService($params["model"], \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT);
        if (is_null($sslOrder)) {
            return [];
        }
        $allowedFunctions = ["client_reissue_certificate", "client_retrieve_certificate", "get_site_seal"];
        if ($sslOrder->canChangeApproverEmail()) {
            $allowedFunctions[] = "client_change_approver_email";
        }
        return $allowedFunctions;
    }

    public function clientAreaOutput($params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $sslOrderDetails = marketconnect_GetSslOrderDetails($orderNumber);
        } catch (\WHMCS\Exception $e) {
            $sslOrderDetails = NULL;
        }
        if ($sslOrderDetails) {
            $model = $params["model"];
            $provisionDate = $model->registrationDate;
            $provisionDate = in_array($provisionDate, ["0000-00-00", "1970-01-01"]) ? "-" : fromMySQLDate($provisionDate);
            $status = $sslOrderDetails->status;
            if ($status == \WHMCS\Service\Ssl::STATUS_AWAITING_CONFIGURATION) {
                $status .= " <a class=\"btn btn-default autoLinked\" href=\"configuressl.php?cert=" . md5($sslOrderDetails->id) . "\">" . \Lang::trans("sslconfigurenow") . "</a>";
            }
            $sslProvisionDate = \Lang::trans("sslprovisioningdate");
            $sslStatus = \Lang::trans("sslstatus");
            $domainValidationMarkup = $this->viewManager("client", NULL, $sslOrderDetails)->renderDomainControlValidation();
            return "<div align=\"left\">\n    <div class=\"row py-2\">\n        <div class=\"col-md-4\">" . $sslProvisionDate . "</div>\n        <div class=\"col-md-8\">" . $provisionDate . "</div>\n    </div>\n    <div class=\"row py-2\">\n        <div class=\"col-md-4\">" . $sslStatus . "</div>\n        <div class=\"col-md-8\">" . $status . "</div>\n    </div>\n    " . $domainValidationMarkup . "\n</div>";
        }
        return "";
    }

    public function renew($model = NULL, $params)
    {
        $ssl = $this->sslOrFail($model);
        $payload = $this->renewPayloadWithParams($ssl, $params);
        $supportsInstantIssuance = $this->sslSupportsInstantIssuance($ssl);
        $attemptInstantIssuance = false;
        $isInstantIssuanceSuccessful = false;
        $instantIssuanceReportStatus = NULL;
        $instantIssuanceErrorMessage = NULL;
        $requestTokenResult = NULL;
        $certificate = NULL;
        if ($supportsInstantIssuance) {
            try {
                $requestTokenResult = $this->getRequestTokenApiCall($payload, $ssl);
            } catch (\Throwable $e) {
                $instantIssuanceErrorMessage = "Request token could not be obtained: " . $e->getMessage();
            }
        }
        if ($requestTokenResult) {
            try {
                $this->automateDomainControlValidation($ssl, $requestTokenResult);
                $attemptInstantIssuance = true;
            } catch (\Throwable $e) {
                $instantIssuanceErrorMessage = "Request token could not be deployed: " . $e->getMessage();
                $instantIssuanceReportStatus = self::SSL_STATUS_INSTANT_ISSUANCE_TOKEN_DEPLOY_FAILURE;
            }
        }
        $payload->setUseInstantIssuance($attemptInstantIssuance);
        try {
            $result = $this->renewApiCall($payload, $ssl);
            if ($attemptInstantIssuance) {
                $isInstantIssuanceSuccessful = $result->hasCertificate();
                if ($isInstantIssuanceSuccessful) {
                    $certificate = $result->getCertificate();
                }
            }
        } catch (\Throwable $e) {
            if (!$attemptInstantIssuance) {
                throw $e;
            }
            $instantIssuanceErrorMessage = "Instant Issuance Failed: " . $e->getMessage();
            $payload->setUseInstantIssuance(false);
            $result = $this->renewApiCall($payload, $ssl);
        }
        if ($attemptInstantIssuance && !$isInstantIssuanceSuccessful && is_null($instantIssuanceErrorMessage)) {
            $instantIssuanceErrorMessage = "No certificate received from DigiCert";
        }
        $ssl->configurationData = array_merge($ssl->configurationData, ["instant_issuance_eligible" => $supportsInstantIssuance, "instant_issuance_attempted" => $attemptInstantIssuance, "instant_issuance_successful" => $isInstantIssuanceSuccessful, "instant_issuance_error" => $instantIssuanceErrorMessage]);
        $ssl->stateRenewed($result->orderNumber())->save();
        if ($attemptInstantIssuance) {
            logActivity(sprintf("SSL Instant Issuance renewal %s for %s, automatic installation is pending. %s: %d", $isInstantIssuanceSuccessful ? "succeeded" : "failed", $ssl->getDomain(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
        }
        if (!$attemptInstantIssuance || !$isInstantIssuanceSuccessful) {
            if ($instantIssuanceReportStatus) {
                if ($attemptInstantIssuance) {
                    $errorMessage = $instantIssuanceErrorMessage ?? "Unknown error";
                } else {
                    $errorMessage = NULL;
                }
                $this->reportInstantIssuanceInstallStatus($ssl, self::SSL_STATUS_INSTANT_ISSUANCE_OTHER_FAILURE, $errorMessage);
            }
            $this->automateDomainControlValidation($ssl, $result);
            return "success";
        }
        $autoInstallSuccessful = false;
        if ($this->callServerInstall($ssl, $certificate, $model)) {
            $autoInstallSuccessful = true;
            $ssl->sendEmail(\WHMCS\Service\Ssl::EMAIL_INSTALLED);
            $this->reportInstantIssuanceInstallStatus($ssl, self::SSL_STATUS_INSTANT_ISSUANCE_CERT_INSTALLED);
        } else {
            $ssl->sendEmail(\WHMCS\Service\Ssl::EMAIL_ISSUED);
            $this->reportInstantIssuanceInstallStatus($ssl, self::SSL_STATUS_INSTANT_ISSUANCE_INSTALL_FAILURE, is_string($this->installResult) ? $this->installResult : json_encode($this->installResult));
        }
        logActivity(sprintf("SSL Instantly Issued renewed certificate installation %s for %s. %s: %d", $autoInstallSuccessful ? "succeeded" : "failed", $ssl->getDomain(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
        return "success";
    }

    public function generateCsr($model, \WHMCS\Module\Server $serverInterface, $params = NULL)
    {
        $payload = new \WHMCS\MarketConnect\Ssl\Configuration($this->sslOrFail($model));
        $domain = $payload->getDomain();
        $serviceProperties = $model->serviceProperties;
        if (!is_null($params) && array_key_exists("configdata", $params)) {
            $csr = $params["configdata"]["csr"];
            $certificateInfo = ["domain" => ecoalesce($params["configdata"]["domain"], $domain), "country" => $params["configdata"]["country"], "state" => $params["configdata"]["state"], "city" => $params["configdata"]["city"], "orgname" => $params["configdata"]["orgname"], "orgunit" => "Technical", "email" => $params["configdata"]["email"]];
        } else {
            $certificateInfo = ["domain" => $domain, "country" => $serviceProperties->get("Certificate Country") ?: $model->client->country, "state" => $serviceProperties->get("Certificate State") ?: $model->client->state, "city" => $serviceProperties->get("Certificate City") ?: $model->client->city, "orgname" => ecoalesce($serviceProperties->get("Certificate Organisation Name"), $model->client->companyName, "N/A"), "orgunit" => $serviceProperties->get("Certificate Organisation Unit") ?: "Technical", "email" => $serviceProperties->get("Certificate Email Address") ?: $model->client->email];
            try {
                $csr = $serverInterface->call("GenerateCertificateSigningRequest", ["certificateInfo" => $certificateInfo]);
            } catch (\Exception $e) {
                throw new \WHMCS\Exception($serverInterface->getDisplayName() . " Error: " . $e->getMessage());
            }
            if (is_array($csr)) {
                $save = $csr["saveData"];
                $key = $csr["key"];
                $csr = $csr["csr"];
                if ($save) {
                    $serviceProperties->save(["Certificate Private Key" => ["type" => "textarea", "value" => $key], "Certificate Signing Request" => ["type" => "textarea", "value" => $csr]]);
                }
            }
        }
        return ["csr" => $csr, "certificateInfo" => $certificateInfo, "initialDomain" => $domain];
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInfo = NULL, $actionBtns = NULL)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $ssl = \WHMCS\Service\Ssl::factoryFromService($params["model"]);
        $actionBtns = [["icon" => "far fa-envelope", "label" => "Email Client Link to Configure", "class" => "btn-default", "moduleCommand" => "resend", "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-cog", "label" => "Manually Configure Certificate", "class" => "btn-default", "href" => "wizard.php?wizard=ConfigureSsl&serviceid=" . $params["serviceid"] . "&addonid=" . $params["addonId"], "modal" => ["title" => "Configure Certificate", "class" => "modal-wizard", "size" => "modal-lg", "submitLabel" => "Next", "submitId" => ""], "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-cogs", "label" => "Attempt Automatic Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-upload", "label" => "Change Approver Email", "class" => "btn-default", "moduleCommand" => "admin_change_approver_email", "modal" => ["title" => "Change Approver Email", "submitLabel" => "Submit", "submitId" => "btnChangeApproverEmailSubmit"], "applicableStatuses" => ["Configuration Submitted"]], ["icon" => "fa-envelope", "label" => "Re-send Approver Email", "class" => "btn-default", "moduleCommand" => "admin_resend_approver_email", "applicableStatuses" => ["Configuration Submitted"]], ["icon" => "fa-download", "label" => "Retrieve Certificate", "class" => "btn-default", "moduleCommand" => "admin_retrieve_certificate", "modal" => ["title" => "Retrieve Certificate"], "applicableStatuses" => ["Configuration Submitted", "Certificate Issued"]], ["icon" => "fa-exchange", "label" => "Attempt Certificate Auto-Installation", "class" => "btn-default", "moduleCommand" => "install_certificate", "applicableStatuses" => ["Configuration Submitted", "Certificate Issued"]]];
        $numberOfReissues = 0;
        if (!is_null($ssl)) {
            $domainAuthentication = $ssl->authenticationData;
            if (!is_null($domainAuthentication)) {
                $actionBtns[] = ["icon" => "fa-passport", "label" => \AdminLang::trans("ssl.dcv"), "class" => "btn-default", "moduleCommand" => "ssl_domain_validation", "modal" => ["title" => \AdminLang::trans("ssl.dcv"), "class" => "modal-dcv", "size" => "modal-md"], "applicableStatuses" => ["Configuration Submitted", "Awaiting Issuance", "Reissue Pending"]];
            }
            $sslData = $ssl->configurationData;
            if (!empty($sslData["reissues"])) {
                $numberOfReissues = count($sslData["reissues"]);
            }
            $orderInfo->setAdditionalInformationValue("reissueCount", $numberOfReissues);
            $instantIssuanceStatus = "-";
            $statusClass = "";
            $statusIcon = "";
            $orderInstantIssuanceStatus = $orderInfo->getAdditionalInformationValue("instantIssuance.status");
            if (!is_null($orderInstantIssuanceStatus)) {
                switch ($orderInstantIssuanceStatus) {
                    case self::SSL_STATUS_INSTANT_ISSUANCE_CERT_INSTALLED:
                    case self::SSL_STATUS_INSTANT_ISSUANCE_INSTALL_FAILURE:
                        $instantIssuanceStatus = "Success";
                        $statusClass = "success";
                        $statusIcon = "<i class=\"fas fa-bolt\"></i>&nbsp;&nbsp;";
                        break;
                    case self::SSL_STATUS_INSTANT_ISSUANCE_OTHER_FAILURE:
                        $instantIssuanceStatus = "Standard DCV Polling";
                        break;
                    case self::SSL_STATUS_INSTANT_ISSUANCE_TOKEN_DEPLOY_FAILURE:
                        $instantIssuanceStatus = "Standard DCV Polling - The Instant Issuance token is not deployable";
                        break;
                    case self::SSL_STATUS_INSTANT_ISSUANCE_TOKEN_REQUEST_FAILURE:
                        $instantIssuanceStatus = "Standard DCV Polling - The Instant Issuance token could not be acquired";
                        break;
                    case self::SSL_STATUS_INSTANT_ISSUANCE_DV_FAILURE:
                        $instantIssuanceStatus = "Standard DCV Polling - DigiCert could not validate the Instant Issuance token";
                        break;
                    default:
                        $instantIssuanceStatus = htmlspecialchars($instantIssuanceStatus);
                        $instantIssuanceStatus = "<span class=\"instant-issuance-status " . $statusClass . "\">\n    " . $statusIcon . $instantIssuanceStatus . "    \n</span>";
                        $instantIssuanceStatus = ["htmlValue" => $instantIssuanceStatus];
                }
            } else {
                if (!is_null($orderInfo->getAdditionalInformationValue("instantIssuance.digicertOrderNumber"))) {
                    $instantIssuanceStatus = "Instant Issuance Not Attempted";
                }
            }
            $orderInfo->setAdditionalInformationValue("instantIssuance", $instantIssuanceStatus);
        }
        $actionBtns[] = ["icon" => "fa-list", "label" => \AdminLang::trans("ssl.viewReissues"), "class" => "btn-default", "moduleCommand" => "list_reissues", "modal" => ["title" => \AdminLang::trans("ssl.viewReissues"), "class" => "modal-reissues", "size" => "modal-md"], "applicableStatuses" => ["Certificate Issued", "Configuration Submitted", "Awaiting Issuance", "Reissue Pending"], "disabled" => !(bool) $numberOfReissues];
        $params["serviceLearnMoreLink"] = ["url" => "https://marketplace.whmcs.com/help/connect/kb/digicert_ssl_certificates", "text" => "Learn more about this MarketConnect service."];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionBtns);
    }

    public function adminReissueSsl(\WHMCS\ServiceInterface $model, $params)
    {
        $api = new \WHMCS\MarketConnect\Api();
        try {
            $sslOrderDetails = marketconnect_GetSslOrderDetails(marketconnect_GetOrderNumber($params));
            if ($sslOrderDetails && $sslOrderDetails->status == \WHMCS\Service\Ssl::STATUS_CANCELLED) {
                throw new \WHMCS\Exception\Module\NotServicable("This certificate order has been cancelled.");
            }
            if ($sslOrderDetails) {
                $relatedHostingService = NULL;
                if ($model instanceof \WHMCS\Service\Service) {
                    $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
                }
                $dcvMethod = \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_EMAIL;
                if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
                    $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
                    if (in_array($parentModel->product->module, \WHMCS\MarketConnect\Provision::AUTO_INSTALL_PANELS)) {
                        if ($sslOrderDetails->isProductCategory(\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_WILDCARD)) {
                            $dcvMethod = \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_DNS;
                        } else {
                            $dcvMethod = \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_FILE;
                        }
                    }
                }
                if ($dcvMethod === \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_EMAIL) {
                    $originalValidationMethod = $sslOrderDetails->authenticationData->methodNameConstant();
                    if ($originalValidationMethod === \WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS) {
                        $dcvMethod = \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_DNS;
                    } else {
                        if ($originalValidationMethod === \WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE) {
                            $dcvMethod = \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_FILE;
                        }
                    }
                }
                $csr = $sslOrderDetails->configurationData["csr"] ?? "";
                $response = $api->extra("reissue", ["order_number" => $sslOrderDetails->remoteId, "csr" => $csr, "callback_url" => fqdnRoutePath("store-reissued-ssl-callback"), "dcv_method" => $dcvMethod]);
                $sslOrderDetails->status = \WHMCS\Service\Ssl::STATUS_REISSUE_PENDING;
                if ($dcvMethod !== \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_EMAIL) {
                    $dcvValue = $response["dcv_method_values"]["contents"] ?? NULL;
                    if (!$dcvValue) {
                        throw new \WHMCS\Exception\Module\NotServicable("Reissuing the certificate failed because of missing validation information.");
                    }
                    $reissue = ["date" => \WHMCS\Carbon::now()->toDateTimeString(), "validationValue" => $dcvValue];
                    $sendEmail = false;
                    $emailExtra = [];
                    if ($dcvMethod === \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_FILE) {
                        $reissue["validationMethod"] = \WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE;
                        $method = new \WHMCS\Service\Ssl\ValidationMethodFileauth();
                        $method->contents = $dcvValue;
                        $method->path = $response["dcv_method_values"]["path"];
                        $method->name = $response["dcv_method_values"]["filename"];
                        $sslOrderDetails->authenticationData = $method;
                        try {
                            $result = $this->fileAuthUpload($sslOrderDetails->getProvisioningModule(), $method, $sslOrderDetails->getDomain());
                            if ($result === \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                                throw new \WHMCS\Exception\Module\NotImplemented($result);
                            }
                        } catch (\Throwable $e) {
                            $sendEmail = true;
                            $path = $response["dcv_method_values"]["path"];
                            if (substr($path, -1) !== "/") {
                                $path .= "/";
                            }
                            $emailExtra = ["dns_validation" => false, "file_validation" => true, "file_name" => $path . $response["dcv_method_values"]["filename"], "validation_contents" => $dcvValue];
                        }
                    } else {
                        $reissue["validationMethod"] = \WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS;
                        $method = new \WHMCS\Service\Ssl\ValidationMethodDnsauth();
                        $method->value = $dcvValue;
                        $sslOrderDetails->authenticationData = $method;
                        try {
                            $result = $this->addDnsRecords($sslOrderDetails->getProvisioningModule(), [$method], $sslOrderDetails->getDomain());
                            if ($result === \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                                throw new \WHMCS\Exception\Module\NotImplemented($result);
                            }
                        } catch (\Throwable $e) {
                            $sendEmail = true;
                            $emailExtra = ["dns_validation" => true, "file_validation" => false, "validation_contents" => $dcvValue];
                        }
                    }
                    if ($sendEmail) {
                        $sslOrderDetails->sendEmail(\WHMCS\Service\Ssl::EMAIL_MANUAL_VALIDATION, $emailExtra);
                    }
                } else {
                    $dcvValue = "";
                    $method = new \WHMCS\Service\Ssl\ValidationMethodEmailauth();
                    $method->email = $dcvValue;
                    $sslOrderDetails->authenticationData = $method;
                    $reissue = ["date" => \WHMCS\Carbon::now()->toDateTimeString(), "validationValue" => $dcvValue, "validationMethod" => \WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL];
                }
                $sslOrderDetails->logReissue($reissue);
                $sslOrderDetails->save();
                return ["success" => true];
            }
            throw new \WHMCS\Exception\Module\InvalidConfiguration("The certificate configuration data is missing.");
        } catch (\Throwable $e) {
            return ["error" => $e->getMessage()];
        }
    }

    protected function fileAuthUpload(\WHMCS\Module\Server $serverInterface, \WHMCS\Service\Ssl\ValidationMethodFileauth $auth, $domain)
    {
        try {
            return $serverInterface->call("CreateFileWithinDocRoot", ["certificateDomain" => $domain, "dir" => $auth->path, "filename" => $auth->name, "fileContent" => $auth->contents]);
        } catch (\Exception $e) {
            throw new \WHMCS\Exception($serverInterface->getDisplayName() . " Error: " . $e->getMessage());
        }
    }

    protected function addDnsRecords(\WHMCS\Module\Server $serverInterface, $auths, $domain)
    {
        $records = [];
        foreach ($auths as $auth) {
            $record = ["type" => $auth->getRecordTypeWithDefault($auth::getRecordTypeDefault()), "value" => $auth->value];
            if ($auth->host != "@") {
                $record["host"] = $auth->getHostWithDefault($auth::getHostDefault());
            }
            $records[] = $record;
        }
        try {
            return $serverInterface->call("ModifyDns", ["domain" => $domain, "dnsRecordsToProvision" => $records]);
        } catch (\Exception $e) {
            throw new \WHMCS\Exception(sprintf("%s Error: %s", $serverInterface->getDisplayName(), $e->getMessage()));
        }
    }

    public function isEligibleForUpgrade()
    {
        return false;
    }

    public function isSslProduct()
    {
        return true;
    }

    public function hookSidebarActions(\WHMCS\View\Menu\Item $item)
    {
        $addon = NULL;
        $service = \Menu::context("service");
        $addonId = 0;
        if ($service->product->module != "marketconnect") {
            $addon = \Menu::context("addon");
            $addonId = $addon->id;
        }
        $serviceId = $service->id;
        $sslOrder = \WHMCS\Service\Ssl::factoryFromService(coalesce($addon, $service));
        if (is_null($sslOrder)) {
            return NULL;
        }
        if ($sslOrder->status == \WHMCS\Service\Ssl::STATUS_AWAITING_CONFIGURATION) {
            $item->getChild("Service Details Actions")->addChild("Configure SSL", ["uri" => "configuressl.php?cert=" . md5($sslOrder->id), "label" => \Lang::trans("sslconfigurenow"), "order" => 1]);
        }
        $sidebarActions = ["client_change_approver_email" => "ssl.changeApproverEmail", "client_retrieve_certificate" => "ssl.retrieveCertificate", "client_reissue_certificate" => "ssl.reissueCertificate", "get_site_seal" => "ssl.getSiteSeal"];
        if (!$sslOrder->canChangeApproverEmail()) {
            unset($sidebarActions["client_change_approver_email"]);
        }
        $i = 1;
        foreach ($sidebarActions as $a => $languageString) {
            $text = \Lang::trans($languageString);
            $postUri = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . DIRECTORY_SEPARATOR . "clientarea.php";
            $bodyHtml = "<form method=\"post\" action=\"" . $postUri . "\">\n    <input type=\"hidden\" name=\"action\" value=\"productdetails\" />\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"" . $a . "\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <span class=\"btn-sidebar-form-submit\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $text . "</span>\n    </span>\n    <span class=\"error-feedback\"></span>\n</form>";
            $cssClasses = ["btn-sidebar-form-submit"];
            if ($a == \Menu::context("menuAction")) {
                $cssClasses[] = "active";
            }
            $item->getChild("Service Details Actions")->addChild($a, ["uri" => "#" . $a, "label" => $bodyHtml, "order" => $i, "disabled" => $service->domainStatus != "Active", "attributes" => ["class" => implode(" ", $cssClasses)]]);
            $i++;
        }
    }

    public function requestManualConfiguration(\WHMCS\Service\Ssl $sslOrder)
    {
        $sslOrder->sendEmail(\WHMCS\Service\Ssl::EMAIL_CONFIGURATION_REQUIRED);
    }

    protected function assertApiSuccess(\WHMCS\MarketConnect\Ssl\ApiResult $response, $prefix = "")
    {
        if (!$response->successful()) {
            throw new \WHMCS\Exception(ltrim($prefix . " " . $response->getError()));
        }
    }

    protected function sslOrFail($Ssl, $service)
    {
        $ssl = \WHMCS\Service\Ssl::factoryFromService($service, \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT);
        if (is_null($ssl)) {
            throw new \Exception("Failed to locate related service configuration");
        }
        return $ssl;
    }

    protected function automateDomainControlValidation(\WHMCS\Service\Ssl $ssl, \WHMCS\MarketConnect\Ssl\ApiResult $result)
    {
        $method = $this->determineDomainValidation($result);
        if ($method instanceof \WHMCS\Service\Ssl\ValidationMethod) {
            $this->persistDomainValidation($ssl, $method);
            if ($ssl->canProvisioningModuleAutoConfigure($method->methodNameConstant())) {
                return $this->setupDomainValidation($ssl, $method);
            }
        }
    }

    protected function determineDomainValidation(\WHMCS\MarketConnect\Ssl\ApiResult $result)
    {
        $method = NULL;
        if ($result->hasValidationMethod(\WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS)) {
            $method = $result->getDomainValidationMethods()[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS];
        } else {
            if ($result->hasValidationMethod(\WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE)) {
                $method = $result->getDomainValidationMethods()[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE];
            } else {
                if ($result->hasValidationMethod(\WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL)) {
                    $method = $result->getDomainValidationMethods()[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL];
                }
            }
        }
        return $method;
    }

    protected function setupDomainValidation(\WHMCS\Service\Ssl $ssl, \WHMCS\Service\Ssl\ValidationMethod $method)
    {
        if ($method instanceof \WHMCS\Service\Ssl\ValidationMethodFileauth) {
            try {
                $this->fileAuthUpload($ssl->getProvisioningModule(), $method, $ssl->getDomain());
            } catch (\Exception $e) {
                return array_merge($method->toArray(), ["fileAuth" => true]);
            }
        }
        if ($method instanceof \WHMCS\Service\Ssl\ValidationMethodDnsauth) {
            try {
                $this->addDnsRecords($ssl->getProvisioningModule(), [$method], $ssl->getDomain());
            } catch (\Exception $e) {
                logActivity(sprintf("Automatic %s domain control validation setup failed (%s) - %s: %d", strtolower($method->friendlyName()), $e->getMessage(), $ssl->isAddon() ? "Addon ID" : "Service ID", $ssl->getOwningService()->id), $ssl->userId);
            }
        }
    }

    protected function persistDomainValidation(\WHMCS\Service\Ssl $ssl, \WHMCS\Service\Ssl\ValidationMethod $method)
    {
        $ssl->authenticationData = $method;
        $ssl->save();
    }

    public function viewManager($ViewManager, $area = NULL, \WHMCS\ServiceInterface $model = NULL, \WHMCS\Service\Ssl $ssl)
    {
        if ($ssl === NULL) {
            if ($model === NULL) {
                throw new \RuntimeException();
            }
            $ssl = $this->sslOrFail($model);
        }
        if ($model === NULL) {
            $model = $ssl->getOwningService();
        }
        $view = NULL;
        switch ($area) {
            case "client":
                $view = new Ssl\View\ViewManagerClient($model, $ssl);
                break;
            case "admin":
                $view = new Ssl\View\ViewManagerAdmin($model, $ssl);
                return $view;
                break;
            default:
                throw new \InvalidArgumentException("Unknown view area");
        }
    }

    public function emailMergeData($params, $preCalculatedMergeData = [])
    {
        $sslOrder = \WHMCS\Service\Ssl::factoryFromService($params["model"], \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT);
        return ["ssl_configuration_link" => $sslOrder->getConfigurationLink(), "certificate_manage_link" => $sslOrder->getManageLink()];
    }
}
