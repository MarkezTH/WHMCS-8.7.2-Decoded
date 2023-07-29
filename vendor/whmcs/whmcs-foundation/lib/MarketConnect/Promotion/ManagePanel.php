<?php

namespace WHMCS\MarketConnect\Promotion;

class ManagePanel extends LoginPanel
{
    public function getBodyHtml()
    {
        $replacementText = "";
        if ($this->requiresDomain) {
            $serviceSelect = \Lang::trans("store.chooseDomain") . ":" . "<select name=\"service-id\" class=\"form-control\">" . $this->buildServicesDropdown() . "</select>";
        } else {
            $firstService = $this->services[0];
            $firstId = $firstService["id"];
            if ($firstService["type"] == "addon") {
                $firstId = "a" . $firstId;
            }
            $serviceSelect = "<input type=\"hidden\" name=\"service-id\" value=\"" . $firstId . "\">";
            $replacementText = $this->dropdownReplacementText;
        }
        $postUrl = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . DIRECTORY_SEPARATOR . "clientarea.php";
        $poweredBy = \Lang::trans("store.poweredBy", [":service" => $this->poweredBy]);
        return "<div class=\"panel-mc-sso\">\n    <div class=\"row\">\n        <div class=\"col-sm-6 text-center\">\n            <img src=\"" . $this->image . "\">\n        </div>\n        <div class=\"col-sm-6\">\n            <form action=\"" . $postUrl . "\" method=\"post\">\n                <input type=\"hidden\" name=\"action\" value=\"manage-service\" />\n                <input type=\"hidden\" name=\"sub\" value=\"manage\" />\n                " . $replacementText . "\n                " . $serviceSelect . "\n                <button class=\"btn btn-default btn-sidebar-form-submit\">\n                    <span class=\"loading hidden w-hidden\">\n                        <i class=\"fas fa-spinner fa-spin\"></i>\n                    </span>\n                    <span class=\"text\">" . \Lang::trans("manage") . "</span>\n                </button>\n                <div class=\"login-feedback\"></div>\n            </form>\n            <small>" . $poweredBy . "&trade;</small>\n        </div>\n    </div>\n</div>";
    }

    public function toHtml()
    {
        $serviceType = $this->services[0]["type"];
        $upgradeServiceId = $serviceId = $this->services[0]["id"];
        $addonId = NULL;
        if ($serviceType == "addon") {
            $addonId = $serviceId;
            $addon = \WHMCS\Service\Addon::find($addonId);
            $serviceId = $addon->serviceId;
        }
        if ($addonId) {
            $redirectUrl = routePath("module-custom-action-addon", $serviceId, $addonId, "manage");
        } else {
            $redirectUrl = routePath("module-custom-action", $serviceId, "manage");
        }
        return "<div class=\"mc-promo-manage\" id=\"" . $this->name . "\">\n            <div class=\"content panel panel-default\">\n                <span class=\"logo\"><img src=\"" . $this->image . "\"></span>\n                <div>\n                    <div class=\"panel-heading\">\n                        <h3 class=\"panel-title\">" . $this->label . "</h3>\n                    </div>\n                    <div class=\"panel-body\">\n                        <form action=\"" . routePath("upgrade") . "\" method=\"post\">\n                            <input type=\"hidden\" name=\"isproduct\" value=\"" . (int) ($serviceType == "service") . "\">\n                            <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n                            <span class=\"actions\">\n                                <a href=\"" . $redirectUrl . "\" class=\"btn btn-default\">\n                                    <span class=\"loading hidden w-hidden\">\n                                        <i class=\"fas fa-spinner fa-spin\"></i>\n                                    </span>\n                                    <span class=\"text\">" . \Lang::trans("manage") . "</span>\n                                </a>\n                                <button type=\"submit\" class=\"btn btn-default btn-sidebar-form-submit\">\n                                    " . \Lang::trans("upgrade") . "\n                                </button>\n                            </span>\n                            <div class=\"login-feedback\"></div>\n                        </form>\n                    </div>\n                </div>\n            </div>\n        </div>";
    }
}
