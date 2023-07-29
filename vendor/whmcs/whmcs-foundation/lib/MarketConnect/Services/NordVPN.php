<?php

namespace WHMCS\MarketConnect\Services;

class NordVPN extends AbstractService
{
    const WELCOME_EMAIL_TEMPLATE = "NordVPN Welcome Email";

    public function getServiceIdent()
    {
        return "nordvpn";
    }

    public function configure($model, $params = NULL)
    {
        $serviceProperties = $model->serviceProperties;
        $orderNumber = $serviceProperties->get("Order Number");
        if (!$orderNumber) {
            throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to configure it");
        }
        $configure = ["order_number" => $orderNumber, "customer_name" => $model->client->fullName, "customer_email" => $model->client->email];
        $api = new \WHMCS\MarketConnect\Api();
        $api->configure($configure);
        $this->sendWelcomeEmail($model);
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInformation = NULL, $actionButtons = NULL)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $actionBtns = [["icon" => "fa-cog", "label" => "Attempt Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]]];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionBtns);
    }

    public function isEligibleForUpgrade()
    {
        return false;
    }

    public function hookSidebarActions(\WHMCS\View\Menu\Item $item)
    {
        return [];
    }

    public function clientAreaOutput($params)
    {
        $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        $paragraphOne = \Lang::trans("marketConnect.nordvpn.clientOutput.1");
        $paragraphTwo = \Lang::trans("marketConnect.nordvpn.clientOutput.2", [":anchorOpen" => "<a href=\"https://support.nordvpn.com\"/>", ":anchorClose" => "</a>"]);
        $paragraphThree = \Lang::trans("marketConnect.nordvpn.clientOutput.3", [":anchorOpen" => "<a href=\"" . $webRoot . "/submitticket.php\">", ":anchorClose" => "</a>"]);
        $paragraphFour = \Lang::trans("marketConnect.nordvpn.clientOutput.4", [":anchorOpen" => "<a href=\"https://my.nordaccount.com/\">", ":anchorClose" => "</a>"]);
        return "<div class=\"text-left\">\n<p>" . $paragraphOne . "</p>\n<p>" . $paragraphTwo . "</p>\n<p>" . $paragraphThree . "</p>\n<p>" . $paragraphFour . "</p>\n</div>";
    }
}
