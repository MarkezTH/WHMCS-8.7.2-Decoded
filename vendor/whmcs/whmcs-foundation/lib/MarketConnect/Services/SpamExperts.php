<?php

namespace WHMCS\MarketConnect\Services;

class SpamExperts extends AbstractService
{
    const WELCOME_EMAIL_TEMPLATE = "SpamExperts Welcome Email";

    public function getServiceIdent()
    {
        return "spamexperts";
    }

    public function configure($model, $params = NULL)
    {
        $serviceProperties = $model->serviceProperties;
        $orderNumber = $serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it");
        }
        if ($model instanceof \WHMCS\Service\Service) {
            $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
            $serverModule = $relatedHostingService instanceof \WHMCS\Service\Service ? $relatedHostingService->product->module : "";
            $domainName = $model->domain;
        } else {
            if ($model instanceof \WHMCS\Service\Addon) {
                $serverModule = $model->service->product->module;
                $domainName = $model->service->domain;
            } else {
                $serverModule = "";
                $domainName = $model->domain;
            }
        }
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "server_module" => $serverModule];
        $api = new \WHMCS\MarketConnect\Api();
        $response = $api->configure($configure);
        if (array_key_exists("error", $response)) {
            throw new \WHMCS\Exception($response["error"]);
        }
        $mxRecords = $response["data"]["mxRecords"];
        $dataToAdd = [];
        foreach ($mxRecords as $mxRecord) {
            $dataToAdd[$mxRecord["host"]] = $mxRecord["priority"];
        }
        $configurationRequired = true;
        $emailRelatedId = $model->id;
        if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
            $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
            switch ($parentModel->product->module) {
                case "cpanel":
                case "directadmin":
                case "plesk":
                    try {
                        $currentMxRecords = $parentModel->getMxRecords()["mxRecords"];
                        $parentModel->addMxRecords(["mxDomain" => $domainName, "mxRecords" => $dataToAdd, "alwaysAccept" => "local", "internal" => "no"])->removeMxRecords($currentMxRecords, $serviceProperties);
                    } catch (\Exception $e) {
                        throw new \WHMCS\Exception($parentModel->moduleInterface()->getDisplayName() . " Error: " . $e->getMessage());
                    }
                    $emailRelatedId = $model instanceof \WHMCS\Service\Addon ? $parentModel->id : $model->id;
                    $configurationRequired = false;
                    break;
            }
        }
        $this->sendWelcomeEmail($model, $this->emailMergeData($params, ["required_mx_records" => $dataToAdd, "configuration_required" => $configurationRequired]));
    }

    public function cancel($model, $params = NULL)
    {
        try {
            $serviceProperties = $model->serviceProperties;
            $orderNumber = $serviceProperties->get("Order Number");
            if (!$orderNumber) {
                throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to manage it");
            }
            $relatedHostingService = NULL;
            if ($model instanceof \WHMCS\Service\Service) {
                $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
            }
            $domainName = $model instanceof \WHMCS\Service\Addon ? $model->service->domain : $model->domain;
            $api = new \WHMCS\MarketConnect\Api();
            $response = $api->cancel($orderNumber);
            if ($response["success"]) {
                if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
                    $existingMxRecords = $serviceProperties->get("Original MX Records");
                    $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
                    switch ($parentModel->product->module) {
                        case "cpanel":
                        case "directadmin":
                        case "plesk":
                            if ($existingMxRecords) {
                                $existingMxRecords = explode("\r\n", $existingMxRecords);
                                $dataToAdd = [];
                                foreach ($existingMxRecords as $existingMxRecord) {
                                    $existingMxRecord = explode(":", $existingMxRecord);
                                    if (isset($existingMxRecord[1])) {
                                        $dataToAdd[$existingMxRecord[1]] = $existingMxRecord[0];
                                    }
                                }
                                $currentMxRecords = $parentModel->getMxRecords()["mxRecords"];
                                $parentModel->addMxRecords(["mxDomain" => $domainName, "mxRecords" => $dataToAdd, "alwaysAccept" => "auto"])->removeMxRecords($currentMxRecords, $serviceProperties);
                                $serviceProperties->save(["Original MX Records" => ["type" => "textarea", "value" => ""]]);
                            }
                            break;
                    }
                }
                return NULL;
            }
            throw new \WHMCS\Exception("Cancellation Failed");
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function clientAreaOutput($params)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber) {
            return "";
        }
        $serviceId = $params["serviceid"];
        $addonId = array_key_exists("addonId", $params) ? $params["addonId"] : 0;
        $manageText = $this->cLang("manage");
        $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        $formHtml = "<img src=\"" . $webRoot . "/assets/img/marketconnect/spamexperts/logo-sml.png\">\n<br><br>\n<form style=\"display:inline;\">\n    <div class=\"login-feedback alert alert-warning hidden w-hidden\"></div>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <button class=\"btn btn-default btn-service-sso\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageText . "</span>\n    </button>\n</form>";
        if ($this->isEligibleForUpgrade()) {
            $isProduct = (int) ($addonId == 0);
            $upgradeLabel = \Lang::trans("upgrade");
            $upgradeRoute = routePath("upgrade");
            $upgradeServiceId = 0 < $addonId ? $addonId : $serviceId;
            $formHtml .= "<form method=\"post\" action=\"" . $upgradeRoute . "\" style=\"display:inline;\">\n    <input type=\"hidden\" name=\"isproduct\" value=\"" . $isProduct . "\">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n    <button type=\"submit\" class=\"btn btn-default\">\n        " . $upgradeLabel . "\n    </button>\n</form>";
        }
        return $formHtml;
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInformation = NULL, $actionButtons = NULL)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $actionBtns = [["icon" => "fa-cog", "label" => "Attempt Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-sign-in", "label" => "Login to SpamExperts Control Panel", "class" => "btn-default", "moduleCommand" => "admin_sso", "applicableStatuses" => ["Active"]]];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionBtns);
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
    }

    public function emailMergeData($params, $preCalculatedMergeData = [])
    {
        $package = $params["configoption1"];
        $configurationRequired = true;
        if (array_key_exists("configuration_required", $preCalculatedMergeData)) {
            $configurationRequired = $preCalculatedMergeData["configuration_required"];
        }
        $mxRecords = ["mx.spamexperts.com." => 10, "fallbackmx.spamexperts.eu." => 20, "lastmx.spamexperts.net." => 30];
        if (array_key_exists("required_mx_records", $preCalculatedMergeData)) {
            $mxRecords = $preCalculatedMergeData["required_mx_records"];
        }
        if (stristr($package, "incoming") === false) {
            $configurationRequired = false;
        }
        return ["required_mx_records" => $mxRecords, "configuration_required" => $configurationRequired, "outgoing_service" => stristr($package, "outgoing") !== false, "archiving_service" => stristr($package, "archiving") !== false];
    }
}
