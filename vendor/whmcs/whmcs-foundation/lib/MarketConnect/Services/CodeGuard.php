<?php

namespace WHMCS\MarketConnect\Services;

class CodeGuard extends AbstractService
{
    const WELCOME_EMAIL_TEMPLATE = "CodeGuard Welcome Email";

    public function getServiceIdent()
    {
        return "codeguard";
    }

    private function getPanelSpecificConfigurationSettings($configure, $panel, \WHMCS\Module\Server $serverInterface, $domainName)
    {
        $excludeRules = [];
        switch ($panel) {
            case "cpanel":
                if ($configure["use_sftp"]) {
                    $docRoot = $serverInterface->call("GetDocRoot", ["domain" => $domainName]);
                    $homeDir = dirname($docRoot);
                } else {
                    $homeDir = "";
                }
                $excludeRules = [$homeDir . "/www/*"];
                break;
            case "directadmin":
                if ($configure["use_sftp"]) {
                    $username = str_replace(["/", "\\", ".", "\0"], "", $configure["username"]);
                    $excludeRules = ["*/" . $username . "/public_html/*"];
                } else {
                    $excludeRules = ["/public_html/*"];
                }
                break;
            default:
                if (!empty($excludeRules)) {
                    $configure["exclude_rules"] = json_encode($excludeRules);
                }
                return $configure;
        }
    }

    public function configure($model, $params = NULL)
    {
        $serviceProperties = $model->serviceProperties;
        $orderNumber = $serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it");
        }
        $relatedHostingService = NULL;
        if ($model instanceof \WHMCS\Service\Service) {
            $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
        }
        if ($model instanceof \WHMCS\Service\Addon) {
            $domainName = $model->service->domain;
            $emailRelatedId = $model->service->id;
        } else {
            $domainName = $model->domain;
            $emailRelatedId = $model->id;
        }
        $client = $model->client;
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "reseller_company_name" => \WHMCS\Config\Setting::getValue("CompanyName"), "reseller_whmcs_url" => \App::getSystemURL(), "reseller_support_email" => \WHMCS\Config\Setting::getValue("Email"), "customer_name" => $client->fullName, "customer_email" => $client->email, "username" => $params["username"], "password" => $params["password"], "use_sftp" => true];
        $connectionHostname = $configure["domain"];
        if (array_key_exists("service", $params) && !empty($params["service"])) {
            $serviceParams = $params["service"];
            if (array_key_exists("serverip", $serviceParams) && $serviceParams["serverip"]) {
                $connectionHostname = $serviceParams["serverip"];
            } else {
                if (array_key_exists("serverhostname", $serviceParams) && $serviceParams["serverhostname"]) {
                    $connectionHostname = $serviceParams["serverhostname"];
                }
            }
        }
        $testConnectionConfiguration = ["order_number" => $configure["order_number"], "domain" => $connectionHostname, "username" => $configure["username"]];
        $configure["connection_hostname"] = $connectionHostname;
        $api = new \WHMCS\MarketConnect\Api();
        if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
            $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
            $serverInterface = \WHMCS\Module\Server::factoryFromModel($parentModel);
            $key = NULL;
            $keyData = NULL;
            if ($serverInterface->functionExists("list_ssh_keys")) {
                $callParams = ["key_name" => "code_guard" . $orderNumber];
                try {
                    $returnedKeys = $serverInterface->call("list_ssh_keys", $callParams);
                    $key = $returnedKeys[0]["name"];
                } catch (\Exception $e) {
                }
            }
            if (is_null($key) && $serverInterface->functionExists("generate_ssh_key")) {
                $callParams = ["key_name" => "code_guard" . $orderNumber, "bits" => "2048"];
                try {
                    $serverInterface->call("generate_ssh_key", $callParams);
                    $key = "code_guard" . $orderNumber;
                } catch (\Exception $e) {
                    $key = NULL;
                }
            }
            if ($key && $serverInterface->functionExists("fetch_ssh_key")) {
                $callParams = ["key_name" => $key];
                try {
                    $keyData = $serverInterface->call("fetch_ssh_key", $callParams);
                } catch (\Exception $e) {
                    throw new \WHMCS\Exception($serverInterface->getDisplayName() . " Error: " . $e->getMessage());
                }
            }
            if ($keyData) {
                $sshPort = 22;
                if ($serverInterface->functionExists("get_ssh_port")) {
                    try {
                        $sshPort = $serverInterface->call("get_ssh_port");
                    } catch (\Exception $e) {
                        throw new \WHMCS\Exception($serverInterface->getDisplayName() . " Error: " . $e->getMessage());
                    }
                }
                $configure["ssh_key"] = $keyData["key"];
                if ($sshPort != 22) {
                    $configure["connection_port"] = $sshPort;
                }
                $testConnectionConfiguration["ssh_key"] = $configure["ssh_key"];
                if (array_key_exists("connection_port", $configure)) {
                    $testConnectionConfiguration["connection_port"] = $configure["connection_port"];
                }
                try {
                    $response = $api->testCodeGuardWebsiteConnection($testConnectionConfiguration);
                    if ($response["useSftp"] !== true) {
                        $keyData = NULL;
                    }
                } catch (\Exception $e) {
                    $keyData = NULL;
                }
            }
            if (is_null($keyData)) {
                try {
                    $configure["use_sftp"] = false;
                    if ($this->provisionFtp("codeguarda", $model)) {
                        $serviceProperties = $model->serviceProperties;
                        $configure["ftpUsername"] = $serviceProperties->get("FTP Username");
                        $configure["ftpPassword"] = $serviceProperties->get("FTP Password");
                    }
                } catch (\Exception $e) {
                }
            }
            $configure = $this->getPanelSpecificConfigurationSettings($configure, $parentModel->product->module, $serverInterface, $domainName);
        }
        $response = $api->configure($configure);
        $manualConfigCompletionRequired = $response["data"]["manualBackupConfigurationRequired"];
        $this->sendWelcomeEmail($model, $this->emailMergeData($params, ["configuration_required" => $manualConfigCompletionRequired]));
    }

    public function clientAreaOutput($params)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber || $params["status"] != "Active") {
            return "";
        }
        $serviceId = $params["serviceid"];
        $addonId = array_key_exists("addonId", $params) ? $params["addonId"] : 0;
        $manageText = $this->cLang("manage");
        $ftpLink = "clientarea.php?action=productdetails&id=" . $serviceId;
        if ($addonId) {
            $ftpLink .= "&addonId=" . $addonId;
        }
        $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        $formHtml = "<img src=\"" . $webRoot . "/assets/img/marketconnect/codeguard/logo.png\" style=\"max-width:300px;\">\n<br><br>\n<form style=\"display:inline;\">\n    <div class=\"login-feedback alert alert-warning hidden w-hidden\"></div>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <button class=\"btn btn-default btn-service-sso\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageText . "</span>\n    </button>\n</form>";
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
        $actionBtns = [["icon" => "fa-cog", "label" => "Attempt Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-sign-in", "label" => "Login to CodeGuard Control Panel", "class" => "btn-default", "moduleCommand" => "admin_sso", "applicableStatuses" => ["Active"]]];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionBtns);
    }

    public function emailMergeData($params, $preCalculatedMergeData = [])
    {
        $configurationRequired = true;
        if (array_key_exists("configuration_required", $preCalculatedMergeData)) {
            $configurationRequired = $preCalculatedMergeData["configuration_required"];
        }
        return ["configuration_required" => $configurationRequired];
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
    }
}
