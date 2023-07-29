<?php

namespace WHMCS\MarketConnect\Services;

class Sitelock extends AbstractService
{
    use FtpServiceTrait;
    const WELCOME_EMAIL_TEMPLATE = "SiteLock Welcome Email";

    public function getServiceIdent()
    {
        return "sitelock";
    }

    public function configure($model, $params = NULL)
    {
        $serviceProperties = $model->serviceProperties;
        $orderNumber = $serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it");
        }
        $domainName = "";
        $parentModel = NULL;
        if ($model instanceof \WHMCS\Service\Addon) {
            $parentModel = $model->service;
            $domainName = $parentModel->domain;
            $emailRelatedId = $parentModel->id;
        } else {
            if ($model instanceof \WHMCS\Service\Service) {
                $parentModel = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
                if (is_null($parentModel)) {
                    $domainName = $model->domain;
                } else {
                    $domainName = $parentModel->domain;
                }
                $emailRelatedId = $model->id;
            }
        }
        if (!$domainName) {
            throw new \WHMCS\Exception\Module\NotServicable("A domain name is required for configuration");
        }
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "domain_email" => $model->client->email, "customer_name" => $model->client->fullName, "customer_email" => $model->client->email];
        $api = new \WHMCS\MarketConnect\Api();
        $response = $api->configure($configure);
        $ftpRequired = false;
        $ftpAutoProvisioned = false;
        if ($response["data"]["requiresFtp"]) {
            $ftpRequired = true;
            $ftpAutoProvisioned = $this->provisionFtp("sitelock", $model);
            if ($ftpAutoProvisioned === true) {
                $this->setFtpDetailsRemotely($model->serviceProperties);
            }
        }
        $dnsRequired = false;
        $dnsAutoProvisioned = false;
        $dnsRecordEmailOutput = "";
        if ($response["data"]["requiresDns"]) {
            $dnsRequired = true;
            $dnsRecordsToProvision = isset($response["data"]["dnsRecords"]) ? $response["data"]["dnsRecords"] : NULL;
            if (is_array($dnsRecordsToProvision)) {
                $dnsRecordEmailOutput = [];
                foreach ($dnsRecordsToProvision as $record) {
                    $dnsRecordEmailOutput[] = "Type: " . $record["type"] . "<br>" . PHP_EOL . "Name: " . $record["name"] . "<br>" . PHP_EOL . "Value: " . $record["value"] . "<br>" . PHP_EOL;
                }
                $dnsRecordEmailOutput = str_repeat("-", 60) . implode(str_repeat("-", 60), $dnsRecordEmailOutput) . str_repeat("-", 60);
                $dnsAutoProvisioned = $this->provisionDns($model, $parentModel, $dnsRecordsToProvision);
            }
        }
        $this->sendWelcomeEmail($model, ["sitelock_requires_ftp" => $ftpRequired, "sitelock_ftp_auto_provisioned" => $ftpAutoProvisioned, "sitelock_requires_dns" => $dnsRequired, "sitelock_dns_auto_provisioned" => $dnsAutoProvisioned, "sitelock_dns_host_record_info" => $dnsRecordEmailOutput]);
    }

    protected function provisionDns($model, $parentModel, $dnsRecordsToProvision)
    {
        if (is_null($parentModel)) {
            return false;
        }
        switch ($parentModel->product->module) {
            case "cpanel":
                $serverInterface = \WHMCS\Module\Server::factoryFromModel($parentModel);
                try {
                    $serverInterface->call("ModifyDns", ["dnsRecordsToProvision" => $dnsRecordsToProvision]);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
                break;
            case "directadmin":
            case "plesk":
            default:
                return false;
        }
    }

    public function clientAreaAllowedFunctions($params)
    {
        if ($params["status"] != "Active") {
            return [];
        }
        return ["manage_order", "update_ftp_details", "update_ftp_details_form"];
    }

    public function clientAreaOutput($params)
    {
        $params = new ClientAreaOutputParameters($params);
        if (!$params->isActiveOrder()) {
            return "";
        }
        $ident = $this->getServiceIdent();
        $updateLabel = $this->cLang("updateFtp");
        $serviceId = $params->getServiceId();
        $addonId = $params->getAddonId();
        $manageLabel = $this->cLang("manage");
        $ftpLink = $this->getFtpFormUrl($params);
        $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        $updateFtpButton = "";
        if ($params->getModel()->serviceProperties->hasProperty("FTP Host")) {
            $updateFtpButton = "<a href=\"" . $ftpLink . "\"\n    class=\"btn btn-default open-modal\"\n    data-btn-submit-id=\"" . $ident . "FtpUpdate\"\n    data-btn-submit-label=\"" . $updateLabel . "\"\n    >" . $updateLabel . "</a>";
        }
        $formHtml = "<img src=\"" . $webRoot . "/assets/img/marketconnect/sitelock/logo.png\"\n    style=\"max-width:300px;margin-bottom:1em;display:block;\"\n    alt=\"logo\"\n    >\n<form style=\"display:inline;\">\n    <div class=\"login-feedback alert alert-warning hidden w-hidden\"></div>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <button class=\"btn btn-default btn-service-sso\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageLabel . "</span>\n    </button>\n    " . $updateFtpButton . "\n</form>";
        if ($this->isEligibleForUpgrade()) {
            $isProduct = (int) $params->isProduct();
            $upgradeLabel = \Lang::trans("upgrade");
            $upgradeRoute = routePath("upgrade");
            $upgradeServiceId = $params->getUpgradeServiceId();
            $formHtml .= "<form method=\"post\" action=\"" . $upgradeRoute . "\" style=\"display:inline;\">\n    <input type=\"hidden\" name=\"isproduct\" value=\"" . $isProduct . "\">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n    <button type=\"submit\" class=\"btn btn-default\">\n        " . $upgradeLabel . "\n    </button>\n</form>";
        }
        return $formHtml;
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInformation = NULL, $actionButtons = NULL)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $actionBtns = [["icon" => "fa-cog", "label" => "Attempt Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-sign-in", "label" => "Login to SiteLock Control Panel", "class" => "btn-default", "moduleCommand" => "admin_sso", "applicableStatuses" => ["Active"]]];
        $model = $params["model"];
        if ($model->serviceProperties->hasProperty("FTP Host")) {
            $actionBtns[] = ["icon" => "fa-upload", "label" => "Update FTP Access Credentials", "class" => "btn-default", "moduleCommand" => "update_ftp_details", "applicableStatuses" => ["Active"]];
        }
        return parent::adminServicesTabOutput($params, $orderInfo, $actionBtns);
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
    }
}
