<?php

namespace WHMCS\MarketConnect\Services;

class SiteBuilder extends AbstractService
{
    use FtpServiceTrait;
    const WELCOME_EMAIL_TEMPLATE = "Site Builder Welcome Email";

    public function getServiceIdent()
    {
        return \WHMCS\MarketConnect\MarketConnect::SERVICE_SITEBUILDER;
    }

    public function configure($model, $params = NULL)
    {
        if (!\App::getSystemURL(false)) {
            throw new \WHMCS\Exception("Please configure your WHMCS System URL before configuring Site Builder.");
        }
        $serviceProperties = $model->serviceProperties;
        $orderNumber = $serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it");
        }
        $relatedHostingService = NULL;
        if ($model instanceof \WHMCS\Service\Service) {
            $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
        }
        $domainName = $model instanceof \WHMCS\Service\Addon ? $model->service->domain : $model->domain;
        $client = $model->client;
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "companyname" => \WHMCS\Config\Setting::getValue("CompanyName"), "companyurl" => \WHMCS\Config\Setting::getValue("Domain"), "email" => $client->email, "upgrade_url" => fqdnRoutePath("cart-site-builder-upgrade"), "first_name" => $client->firstName, "last_name" => $client->lastName];
        if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
            $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
            $configure["server_module"] = $parentModel->product->module;
        }
        $api = new \WHMCS\MarketConnect\Api();
        $response = $api->configure($configure);
        if (array_key_exists("error", $response)) {
            throw new \WHMCS\Exception($response["error"]);
        }
        $ftpRequired = $response["data"]["requires_ftp"];
        $ftpConfigured = false;
        if ($ftpRequired && $this->provisionFtp("site-buildera", $model)) {
            $serviceProperties = $model->serviceProperties;
            $response2 = $this->setFtpDetailsRemotely($serviceProperties);
            if (empty($response2["error"])) {
                $ftpConfigured = true;
            }
        }
        $this->sendWelcomeEmail($model, ["configuration_required" => !$ftpConfigured && $ftpRequired]);
    }

    public function clientAreaAllowedFunctions($params)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber || $params["status"] != "Active") {
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
        $formHtml = "<img src=\"" . $webRoot . "/assets/img/marketconnect/sitebuilder/logo.png\"\n    style=\"max-width:300px;margin-bottom:1em;display:block;\"\n    alt=\"logo\"\n    >\n<form style=\"display:inline;\">\n    <div class=\"login-feedback alert alert-warning hidden w-hidden\"></div>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <button class=\"btn btn-default btn-service-sso\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageLabel . "</span>\n    </button>\n    <a href=\"" . $ftpLink . "\"\n        class=\"btn btn-default open-modal\"\n        data-btn-submit-id=\"" . $ident . "FtpUpdate\"\n        data-btn-submit-label=\"" . $updateLabel . "\"\n        >" . $updateLabel . "</a>\n</form>";
        if ($this->isEligibleForUpgrade()) {
            $isProduct = (int) $params->isProduct();
            $upgradeLabel = \Lang::trans("upgrade");
            $upgradeRoute = routePath("upgrade");
            $upgradeServiceId = $params->getUpgradeServiceId();
            $formHtml .= "<form method=\"post\" action=\"" . $upgradeRoute . "\" style=\"display:inline;\">\n    <input type=\"hidden\" name=\"isproduct\" value=\"" . $isProduct . "\">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n    <button type=\"submit\" class=\"btn btn-default\">\n        " . $upgradeLabel . "\n    </button>\n</form>";
        }
        return $formHtml;
    }

    public function upgrade($model, $params = [])
    {
        $result = parent::upgrade($model, $params);
        if ($result === "success") {
            $serviceProperties = $model->serviceProperties;
            $ftpUsername = $serviceProperties->get("FTP Username");
            $ftpPassword = $serviceProperties->get("FTP Password");
            if (!($ftpUsername || $ftpPassword)) {
                $ftpProvisioned = $this->provisionFtp("site-builderu", $model);
                if ($ftpProvisioned) {
                    $serviceProperties = $model->serviceProperties;
                }
            }
            $ftpUpdated = $this->setFtpDetailsRemotely($serviceProperties);
            if (!empty($ftpUpdated["error"])) {
                $ftpUpdated = false;
            }
            $this->sendWelcomeEmail($model, ["configuration_required" => !$ftpUpdated]);
        }
        return $result;
    }

    public function adminServicesTabOutput($params = NULL, \WHMCS\MarketConnect\OrderInformation $orderInfo = NULL, $actionButtons)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $actionButtons = [["icon" => "fa-cog", "label" => "Attempt Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]], ["icon" => "fa-sign-in", "label" => "Login to Site Builder", "class" => "btn-default", "moduleCommand" => "admin_sso", "applicableStatuses" => ["Active"]], ["icon" => "fa-upload", "label" => "Update FTP Publishing Credentials", "class" => "btn-default", "moduleCommand" => "update_ftp_details", "applicableStatuses" => ["Active"]]];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionButtons);
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
    }

    public function hookSidebarActions(\WHMCS\View\Menu\Item $item)
    {
        parent::hookSidebarActions($item);
        $service = \Menu::context("service");
        if ($service->product->module != "marketconnect") {
            return NULL;
        }
        $serviceId = $service->id;
        $disabled = false;
        $lastSiteBuilder = \WHMCS\Product\Product::sitebuilder()->visible()->orderBy("order", "desc")->first();
        if ($service->domainStatus != "Active" || !$lastSiteBuilder || $lastSiteBuilder->moduleConfigOption1 == $service->product->moduleConfigOption1) {
            $disabled = true;
        }
        if ($this->isEligibleForUpgrade()) {
            $uri = routePath("cart-site-builder-upgrade");
            $upgradeText = \Lang::trans("upgrade");
            $formClass = $disabled ? " class=\"disabled\"" : "";
            $bodyHtml = "<form action=\"" . $uri . "\" method=\"post\"" . $formClass . ">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"0\" />\n    <span class=\"btn-sidebar-form-submit\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $upgradeText . "</span>\n    </span>\n</form>";
            $item->getChild("Service Details Actions")->addChild("Upgrade Site Builder", ["uri" => "#", "label" => $bodyHtml, "order" => 2, "disabled" => $disabled, "attributes" => ["class" => "btn-sidebar-form-submit"]]);
        }
    }
}
