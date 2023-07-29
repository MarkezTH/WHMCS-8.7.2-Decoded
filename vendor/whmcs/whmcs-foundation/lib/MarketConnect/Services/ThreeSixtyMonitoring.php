<?php

namespace WHMCS\MarketConnect\Services;

class ThreeSixtyMonitoring extends AbstractService
{
    const WELCOME_EMAIL_TEMPLATE = "360 Monitoring Welcome Email";

    public function getServiceIdent()
    {
        return "threesixtymonitoring";
    }

    public function configure($model, $params = NULL)
    {
        $serviceProperties = $model->serviceProperties;
        $orderNumber = $serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it.");
        }
        $domainName = "";
        if ($model instanceof \WHMCS\Service\Addon) {
            $parentModel = $model->service;
            $domainName = $parentModel->domain;
        } else {
            if ($model instanceof \WHMCS\Service\Service) {
                $parentModel = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
                if (is_null($parentModel)) {
                    $domainName = $model->domain;
                } else {
                    $domainName = $parentModel->domain;
                }
            }
        }
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "domain_email" => $model->client->email, "customer_name" => $model->client->fullName, "customer_email" => $model->client->email, "customer_country" => $model->client->country, "upgrade_url" => fqdnRoutePath("upgrade-redirect", $model->id, $model instanceof \WHMCS\Service\Service)];
        (new \WHMCS\MarketConnect\Api())->configure($configure);
        $threeSixtyPromotion = new \WHMCS\MarketConnect\Promotion\Service\ThreeSixtyMonitoring();
        $planName = $params["configoption1"] ?? "";
        $this->sendWelcomeEmail($model, ["numberOfServers" => $threeSixtyPromotion->getNumberOfServers($planName), "numberOfMonitors" => $threeSixtyPromotion->getNumberOfMonitors($planName), "domain" => $domainName]);
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInformation = NULL, $actionButtons = NULL)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $orderInfo->renameAdditionalInformationRootLevelKeys(["monitors" => "websites"]);
        return parent::adminServicesTabOutput($params, $orderInfo);
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
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
        $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        $formHtml = "<img src=\"" . $webRoot . "/assets/img/marketconnect/threesixtymonitoring/logo.png\" style=\"max-width:300px;\">\n<br><br>\n<form style=\"display:inline;\">\n    <div class=\"login-feedback alert alert-warning hidden w-hidden\"></div>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <button class=\"btn btn-default btn-service-sso\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageText . "</span>\n    </button>\n</form>";
        if ($this->isEligibleForUpgrade()) {
            $isProduct = (int) ($addonId == 0);
            $upgradeLabel = \Lang::trans("upgrade");
            $upgradeRoute = routePath("upgrade");
            $upgradeServiceId = 0 < $addonId ? $addonId : $serviceId;
            $formHtml .= "<form method=\"post\" action=\"" . $upgradeRoute . "\" style=\"display:inline;\">\n    <input type=\"hidden\" name=\"isproduct\" value=\"" . $isProduct . "\">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n    <button type=\"submit\" class=\"btn btn-default\">\n        " . $upgradeLabel . "\n    </button>\n</form>";
        }
        return $formHtml;
    }

    public function getSiteCheckProbes($forceUpdate)
    {
        $response = NULL;
        if (!$forceUpdate) {
            $cachedData = \WHMCS\TransientData::getInstance()->retrieve("360monitoring.siteCheckProbes");
            if (!is_null($cachedData)) {
                $response = json_decode($cachedData, true);
            }
        }
        if (is_null($response)) {
            $response = (new \WHMCS\MarketConnect\Api())->getThreeSixtyMonitoringSiteCheckProbes();
            \WHMCS\TransientData::getInstance()->store("MarketConnect.360monitoring.siteCheckProbes", json_encode($response), 86400);
        }
        return $response["probes"] ?? [];
    }

    public function performSiteCheck($url, $probeId)
    {
        return (new \WHMCS\MarketConnect\Api())->performThreeSixtyMonitoringSiteCheck($url, $probeId);
    }
}
