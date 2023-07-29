<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class ThreeSixtyMonitoring extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_THREESIXTYMONITORING;
    protected $friendlyName = "360 Monitoring";
    protected $primaryIcon = "assets/img/marketconnect/threesixtymonitoring/logo-sml.png";
    protected $promoteToNewClients = true;
    protected $orderInformationMinutesTTL = 1;
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = ["label" => "marketConnect.threesixtymonitoring.clientPanel.title", "icon" => "fa-monitor-heart-rate", "image" => "assets/img/marketconnect/threesixtymonitoring/logo-sml.png", "color" => "magenta", "dropdownReplacementText" => ""];
    protected $settings = [["name" => "include-threesixtymonitoring-lite-by-default", "label" => "Include Lite plan by Default", "description" => "Automatically pre-select 360 Monitoring Lite by default for new orders of all applicable products", "default" => true]];
    protected $recommendedUpgradePaths = NULL;
    protected $upsells = NULL;
    protected $upsellPromoContent = NULL;
    protected $promotionalContent = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $planFeatures = NULL;
    const THREESIXTYMONITORING_LITE = NULL;
    const THREESIXTYMONITORING_PERSONAL = NULL;
    const THREESIXTYMONITORING_PLUS = NULL;
    const THREESIXTYMONITORING_ADVANCED = NULL;
    const THREESIXTYMONITORING_PRO = NULL;
    const THREESIXTYMONITORING_BUSINESS = NULL;
    const THREESIXTYMONITORING_ENTERPRISE = NULL;

    protected function getAddonToSelectByDefault()
    {
        if ($this->getModel()->setting("general.include-threesixtymonitoring-lite-by-default")) {
            $litePlan = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", self::THREESIXTYMONITORING_LITE)->get()->where("productAddon.module", "marketconnect")->first();
            return $litePlan->productAddon->id;
        }
        return NULL;
    }

    public function getFeaturesForUpgrade($plan)
    {
        return $this->getTranslatedFeatures($plan);
    }

    public function getTranslatedFeatures($plan)
    {
        if (\App::isAdminAreaRequest()) {
            $trans = \AdminLang::self();
            $langPrefix = "marketConnect.threesixtymonitoring.pricing";
        } else {
            $trans = \Lang::self();
            $langPrefix = "store.threesixtymonitoring.comparison";
        }
        $yes = $trans->trans("yes");
        $seconds = $trans->trans("dateTime.abbr.second");
        $minutes = $trans->trans("dateTime.abbr.minute");
        $days = $trans->trans("dateTime.days");
        $hours = $trans->trans("dateTime.hours");
        $features = [];
        foreach ($this->getPlanFeatures($plan) as $feature => $value) {
            switch ($feature) {
                case "timeIntervals":
                    if ($value == 1) {
                        $value = "60 " . $seconds;
                    } else {
                        $value = $value . " " . $minutes;
                    }
                    break;
                case "dataRetention":
                    if ($value == 1) {
                        $value = "24 " . $hours;
                    } else {
                        $value = $value . " " . $days;
                    }
                    break;
                default:
                    if ($value === "" || $value === false) {
                        $value = "<i class=\"fal fa-times\"></i>";
                    } else {
                        if ($value === true) {
                            $value = "<i class=\"fas fa-check\"></i>";
                        } else {
                            if ($value === "yes") {
                                $value = strtolower($yes);
                            } else {
                                if (is_string($value)) {
                                    $value = $trans->trans($langPrefix . "." . $value, [":tagOpen" => "<strong>", ":tagClose" => "</strong>"]);
                                }
                            }
                        }
                    }
                    $features[$trans->trans($langPrefix . "." . $feature)] = $value;
            }
        }
        return $features;
    }

    private function getOrderInformation($OrderInformation, $orderNumber)
    {
        $orderInformation = new \WHMCS\MarketConnect\OrderInformation($orderNumber);
        $orderInformation->cacheExpiryTime = $this->orderInformationMinutesTTL;
        return $orderInformation;
    }

    public function getDashboardData($serviceOrAddon)
    {
        if ($serviceOrAddon["type"] == "addon") {
            $marketConnectItem = \WHMCS\Service\Addon::find($serviceOrAddon["id"]);
        } else {
            $marketConnectItem = \WHMCS\Service\Service::find($serviceOrAddon["id"]);
        }
        if (!$marketConnectItem || !$marketConnectItem->serviceProperties) {
            return NULL;
        }
        $orderNumber = $marketConnectItem->serviceProperties->get("Order Number");
        if (empty($orderNumber)) {
            return NULL;
        }
        $orderInformation = $this->getOrderInformation($orderNumber);
        if ($orderInformation->hasAdditionalInformation() && !$orderInformation->isCacheStale()) {
            $orderData = $orderInformation->getAdditionalInformation();
        } else {
            try {
                $orderStatus = (new \WHMCS\MarketConnect\Api())->status($orderNumber);
            } catch (\Throwable $e) {
                return NULL;
            }
            \WHMCS\MarketConnect\OrderInformation::cache($orderNumber, $orderStatus);
            $orderData = $orderStatus["additionalInfo"];
        }
        if (!(isset($orderData["servers"]) && isset($orderData["monitors"]) && isset($orderData["alerts"]))) {
            return NULL;
        }
        $getCount = function ($value) {
            return trim(explode("/", $value)[0]);
        };
        $servers = $getCount($orderData["servers"]);
        $monitors = $getCount($orderData["monitors"]);
        $alerts = $getCount($orderData["alerts"]);
        return ["servers" => $servers, "monitors" => $monitors, "alerts" => $alerts];
    }

    public function getLoginPanel()
    {
        $panel = parent::getLoginPanel();
        if (!$panel instanceof \WHMCS\MarketConnect\Promotion\LoginPanel) {
            return $panel;
        }
        $panel->setPoweredBy("WebPros");
        $services = $this->getServices();
        $dashboardData = $this->getDashboardData($services[0]);
        if (is_null($dashboardData)) {
            return $panel;
        }
        $panelName = ucfirst($this->name) . "Login";
        $ajaxRoute = routePath("clientarea-threesixtymonitoring-get-dashboard-data");
        $serverString = \Lang::trans("marketConnect.threesixtymonitoring.clientPanel.servers");
        $alertString = \Lang::trans("marketConnect.threesixtymonitoring.clientPanel.alerts");
        $monitorString = \Lang::trans("marketConnect.threesixtymonitoring.clientPanel.monitors");
        $serviceData = "<script>\n(function(\$) {\n\$(document).ready(function() {\n    var serviceSelect = \$('#" . $panelName . "').find('select[name=service-id]');\n\n    \$(serviceSelect).change(function() {\n        var serviceId = \$(this).val();\n        WHMCS.http.jqClient.jsonPost({\n                url: '" . $ajaxRoute . "',\n                data: {\n                    service: serviceId\n                },\n                success: function(response) {\n                    \$('.threesixtymonitoring-metric').each(function(index, element) {\n                        var metric = \$(element).data('metric');\n                        \$(element).text(response[metric]);\n                    });\n                },\n                always: function() {\n                    // disable any spinners\n                }\n            });\n    });\n});\n})(jQuery);\n</script>\n<div class=\"row threesixtymonitoring-metrics-row\" style=\"margin-bottom: 20px; font-size: 1.5em\">\n    <div class=\"col-4 col-xs-4\">\n        <div class=\"threesixtymonitoring-metric\" data-metric=\"servers\">" . $dashboardData["servers"] . "</div>\n        <div class=\"threesixtymonitoring-title\">" . $serverString . "</div>\n        <div class=\"threesixtymonitoring-highlight-servers\"></div>\n    </div>\n    <div class=\"col-4 col-xs-4\">\n        <div class=\"threesixtymonitoring-metric\" data-metric=\"monitors\">" . $dashboardData["monitors"] . "</div>\n        <div class=\"threesixtymonitoring-title\">" . $monitorString . "</div>\n        <div class=\"threesixtymonitoring-highlight-monitors\"></div>\n    </div>\n    <div class=\"col-4 col-xs-4\">\n        <div class=\"threesixtymonitoring-metric\" data-metric=\"alerts\">" . $dashboardData["alerts"] . "</div>\n        <div class=\"threesixtymonitoring-title\">" . $alertString . "</div>\n        <div class=\"threesixtymonitoring-highlight-alerts\"></div>\n    </div>\n</div>";
        $panel->setContentPrefix($serviceData);
        return $panel;
    }

    public function includeServerMonitoring($plan)
    {
        return 0 < $this->getNumberOfServers($plan);
    }

    public function getNumberOfServers($plan)
    {
        return (int) $this->getPlanFeature("servers", $plan);
    }

    public function getNumberOfMonitors($plan)
    {
        return (int) $this->getPlanFeature("monitors", $plan);
    }
}
