<?php

namespace WHMCS\MarketConnect\Services;

class XoviNow extends AbstractService
{
    const WELCOME_EMAIL_TEMPLATE = "XOVI NOW Welcome Email";

    public function configure($model, $params = NULL)
    {
        if (!\App::getSystemURL(false)) {
            throw new \WHMCS\Exception("Please configure your WHMCS System URL before configuring XOVI NOW.");
        }
        $orderNumber = $model->serviceProperties->get("Order Number");
        if (empty($orderNumber)) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it");
        }
        if ($model instanceof \WHMCS\Service\Addon) {
            $domainName = $model->service->domain;
        } else {
            $domainName = $model->domain;
        }
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "customer_first_name" => $model->client->firstName, "customer_last_name" => $model->client->lastName, "customer_email" => $model->client->email, "customer_company" => $model->client->companyName, "upgrade_url" => fqdnRoutePath("upgrade-redirect", $model->id, $model instanceof \WHMCS\Service\Service)];
        (new \WHMCS\MarketConnect\Api())->configure($configure);
        $this->sendWelcomeEmail($model, $this->emailMergeData($params));
    }

    public function getServiceIdent()
    {
        return \WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW;
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
        $formHtml = "<img src=\"" . $webRoot . "/assets/img/marketconnect/xovinow/logo.png\" style=\"max-width:300px;\">\n<br><br>\n<form style=\"display:inline;\">\n    <div class=\"login-feedback alert alert-warning hidden w-hidden\"></div>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <button class=\"btn btn-default btn-service-sso\">\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageText . "</span>\n    </button>\n</form>";
        if ($this->isEligibleForUpgrade()) {
            $isProduct = (int) ($addonId == 0);
            $upgradeLabel = \Lang::trans("upgrade");
            $upgradeRoute = routePath("upgrade");
            $upgradeServiceId = 0 < $addonId ? $addonId : $serviceId;
            $formHtml .= "<form method=\"post\" action=\"" . $upgradeRoute . "\" style=\"display:inline;\">\n    <input type=\"hidden\" name=\"isproduct\" value=\"" . $isProduct . "\">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n    <button type=\"submit\" class=\"btn btn-default\">\n        " . $upgradeLabel . "\n    </button>\n</form>";
        }
        return $formHtml;
    }

    public function adminServicesTabOutput($params = NULL, \WHMCS\MarketConnect\OrderInformation $orderInfo = NULL, $actionBtns)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $actionBtns = [["icon" => "fa-sign-in", "label" => "Login to XOVI NOW", "class" => "btn-default", "moduleCommand" => "admin_sso", "applicableStatuses" => ["Active"]]];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionBtns);
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
    }
}
