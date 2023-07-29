<?php

namespace WHMCS\Admin\ApplicationSupport\View\Html\Helper;

class GlobalWarning
{
    const GLOBAL_WARNING_COOKIE_NAME = "DismissGlobalWarning";

    public function getWarningScopes()
    {
        return ["transientWarnings" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 14400], "nonStrictMode" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 14400], "hookDebugMode" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 600], "ssl" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 1209600], "customAdminPath" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 14400], "emailSendingMode" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 600], "maintenanceMode" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 600], "legacySmartyTags" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 1209600], "2CheckoutInline" => ["dismissed" => false, "lastChecked" => NULL, "frequency" => 1209600]];
    }

    public function getNotifications()
    {
        $dismissed = $this->getDismissalTracker();
        $warnings = $this->getWarningScopes();
        $notification = "";
        foreach ($warnings as $alert => $details) {
            if (isset($dismissed[$alert])) {
                $details["dismissed"] = true;
                if (!empty($dismissed[$alert]["lastChecked"])) {
                    $details["lastChecked"] = $dismissed[$alert]["lastChecked"];
                }
            }
            $cutOffTime = \WHMCS\Carbon::now()->subSeconds($details["frequency"])->getTimestamp();
            if (!$details["dismissed"] || is_null($details["lastChecked"]) || $details["lastChecked"] < $cutOffTime) {
                $checkAction = "checkWarning" . ucfirst($alert);
                if (method_exists($this, $checkAction) && !$this->{$checkAction}()) {
                    $htmlAction = "getWarningHTML" . ucfirst($alert);
                    $notification = $this->{$htmlAction}() . $this->getGlobalWarningDismissalHTML($alert);
                    return $notification;
                }
            }
        }
    }

    public function getDismissalTracker()
    {
        $dismissed = $this->getCookie();
        if (!$dismissed || !is_array($dismissed)) {
            $dismissed = [];
        }
        return $dismissed;
    }

    public function updateDismissalTracker($alertToDismiss = "")
    {
        $scopes = $this->getWarningScopes();
        if ($alertToDismiss && array_key_exists($alertToDismiss, $scopes)) {
            if ($alertToDismiss == "transientWarnings") {
                $transientData = new \WHMCS\TransientData();
                $transientWarnings = json_decode($transientData->retrieve("transientWarnings"), true);
                unset($transientWarnings[0]);
                if (0 < count($transientWarnings)) {
                    $transientData->store("transientWarnings", json_encode(array_values($transientWarnings)), \Carbon\CarbonInterval::days(30)->totalSeconds);
                } else {
                    $transientData->delete("transientWarnings");
                }
            } else {
                $dismissed = $this->getDismissalTracker();
                $dismissed[$alertToDismiss]["dismissed"] = true;
                $dismissed[$alertToDismiss]["lastChecked"] = time();
                $this->setCookie($dismissed);
            }
        }
        return $this;
    }

    protected function setCookie($data = [])
    {
        return \WHMCS\Cookie::set(static::GLOBAL_WARNING_COOKIE_NAME, $data);
    }

    protected function getCookie()
    {
        return \WHMCS\Cookie::get(static::GLOBAL_WARNING_COOKIE_NAME, true);
    }

    protected function getGlobalWarningDismissalHTML($alert)
    {
        if (!is_string($alert)) {
            $alert = "";
        }
        $globalAdminWarningDismissUrl = routePath("admin-dismiss-global-warning");
        $csrfToken = generate_token("plain");
        $alertLabel = ucfirst($alert);
        $html = "<script>\njQuery(document).ready(function(){\n    \$('#btnGlobalWarning" . $alertLabel . "').click(function () {\n        WHMCS.http.jqClient.post(\n            '" . $globalAdminWarningDismissUrl . "', \n            'token=" . $csrfToken . "&alert=" . $alert . "'\n        );\n    })\n});\n</script>\n<button type=\"button\" \n    id=\"btnGlobalWarning" . $alertLabel . "\"\n    class=\"close\" \n    data-dismiss=\"alert\" \n    aria-label=\"Close\"\n    >\n        <span aria-hidden=\"true\">&times;</span>\n    </button>";
        return $html;
    }

    protected function checkWarningSsl()
    {
        return \App::in_ssl();
    }

    protected function getWarningHTMLSsl()
    {
        $linkText = \AdminLang::trans("ssl_warning.buy_link");
        $link = "<a href=\"https://go.whmcs.com/1345/get-ssl\" \ntarget=\"_blank\" \nclass=\"alert-link\">" . $linkText . "</a>";
        $msg = \AdminLang::trans("ssl_warning.insecure_connection") . PHP_EOL . \AdminLang::trans("ssl_warning.dont_have_ssl", [":buyLink" => $link]);
        $html = "<i class=\"far fa-exclamation-triangle fa-fw\"></i>" . PHP_EOL . $msg;
        return $html;
    }

    protected function checkWarningNonStrictMode()
    {
        $lastSqlModeCheck = \WHMCS\Session::get("adminSqlStrictModeCheck");
        $justUnderFourHours = \WHMCS\Carbon::now()->subHours(4)->subMinute(1)->getTimestamp();
        if (!is_numeric($lastSqlModeCheck) || $lastSqlModeCheck < $justUnderFourHours) {
            if ($this->getDatabase()->isSqlStrictMode()) {
                return false;
            }
            \WHMCS\Session::setAndRelease("adminSqlStrictModeCheck", \WHMCS\Carbon::now()->getTimestamp());
        }
        return true;
    }

    protected function getDatabase()
    {
        return \DI::make("db");
    }

    protected function getWarningHTMLNonStrictMode()
    {
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    MySQL Strict Mode Detected:\n</span>\nMySQL strict mode must be disabled to ensure error free operation of WHMCS.\n<a href=\"https://docs.whmcs.com/Database_Setup\" target=\"_blank\">\n    Learn more &raquo;\n</a>";
    }

    protected function checkWarningHookDebugMode()
    {
        return !(bool) \WHMCS\Config\Setting::getValue("HooksDebugMode");
    }

    protected function getWarningHTMLHookDebugMode()
    {
        $title = \AdminLang::trans("hooksDebugModeWarning.title");
        $description = \AdminLang::trans("hooksDebugModeWarning.description");
        $learnMore = \AdminLang::trans("global.learnMore");
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description . "\n<a href=\"https://docs.whmcs.com/Other_Tab#Hooks_Debug_Mode\" target=\"_blank\">\n    " . $learnMore . " &raquo;\n</a>";
    }

    protected function checkWarningCustomAdminPath()
    {
        return !\WHMCS\Admin\AdminServiceProvider::hasCustomAdminPathCollisionWithRoutes();
    }

    protected function getWarningHTMLCustomAdminPath()
    {
        $title = \AdminLang::trans("customAdminPathWarning.title");
        $description = \AdminLang::trans("customAdminPathWarning.description");
        $learnMore = \AdminLang::trans("global.learnMore");
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description . "\n<a href=\"https://docs.whmcs.com/Customising_the_Admin_Directory\" target=\"_blank\">\n    " . $learnMore . " &raquo;\n</a>";
    }

    public function checkWarningEmailSendingMode()
    {
        return !(bool) \WHMCS\Config\Setting::getValue("DisableEmailSending");
    }

    public function getWarningHTMLEmailSendingMode()
    {
        $title = \AdminLang::trans("emailSendingModeWarning.title");
        $description = \AdminLang::trans("emailSendingModeWarning.description");
        $learnMore = \AdminLang::trans("global.learnMore");
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description . "\n<a href=\"https://docs.whmcs.com/Mail_Tab#Disable_Email_Sending\" target=\"_blank\">\n    " . $learnMore . " &raquo;\n</a>";
    }

    public function checkWarningMaintenanceMode()
    {
        return !(bool) \WHMCS\Config\Setting::getValue("MaintenanceMode");
    }

    public function getWarningHTMLMaintenanceMode()
    {
        $title = \AdminLang::trans("general.maintmode");
        $disableHref = \App::getSystemURL(true) . \App::get_admin_folder_name() . "/configgeneral.php";
        $disableLinkText = \AdminLang::trans("maintenanceModeWarning.disableLinkText");
        $learnMore = \AdminLang::trans("global.learnMore");
        $disableLink = "<a href=\"" . $disableHref . "\">" . $disableLinkText . "</a>";
        $description = \AdminLang::trans("maintenanceModeWarning.description", [":link" => $disableLink]);
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description . "\n<a href=\"https://docs.whmcs.com/General_Tab#Maintenance_Mode\" target=\"_blank\">\n    (" . $learnMore . ")\n</a>";
    }

    public function checkWarningTransientWarnings()
    {
        $transientWarnings = (new \WHMCS\TransientData())->retrieve("transientWarnings");
        return empty($transientWarnings);
    }

    public function getWarningHTMLTransientWarnings()
    {
        $transientWarnings = json_decode((new \WHMCS\TransientData())->retrieve("transientWarnings"), true);
        $firstWarning = $transientWarnings[0];
        $title = \WHMCS\Input\Sanitize::encode($firstWarning["title"]);
        $description = \WHMCS\Input\Sanitize::encode($firstWarning["description"]);
        $htmlOutput = "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description;
        if ($firstWarning["learnMore"]) {
            $linkHref = \WHMCS\Input\Sanitize::encode($firstWarning["learnMore"]["href"]);
            $linkText = \WHMCS\Input\Sanitize::encode($firstWarning["learnMore"]["text"]);
            $htmlOutput .= "<a href=\"" . $linkHref . "\" target=\"_blank\">\n    " . $linkText . " &raquo;\n</a>";
        }
        return $htmlOutput;
    }

    public function checkWarningLegacySmartyTags()
    {
        $tagScanner = new \WHMCS\Utility\Smarty\TagScanner();
        $scanResultCount = $tagScanner->getScanResultCount(\WHMCS\Utility\Smarty\TagScanner::DEPRECATED_SMARTY_BC_TAGS_CACHE_KEY);
        $isAllowSmartyPhpTagsEnabled = (bool) \WHMCS\Config\Setting::getValue("AllowSmartyPhpTags");
        return !($isAllowSmartyPhpTagsEnabled || 0 < $scanResultCount);
    }

    public function getWarningHTMLLegacySmartyTags()
    {
        $title = \AdminLang::trans("legacySmartyTagsWarning.title");
        $adminBaseUrl = \WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl();
        $isAllowSmartyPhpTagsEnabled = (bool) \WHMCS\Config\Setting::getValue("AllowSmartyPhpTags");
        $scanResultCount = (new \WHMCS\Utility\Smarty\TagScanner())->getScanResultCount(\WHMCS\Utility\Smarty\TagScanner::DEPRECATED_SMARTY_BC_TAGS_CACHE_KEY);
        $transParams = [":anchorOpen" => "<a href=\"" . $adminBaseUrl . "/systemhealthandupdates.php#legacySmartyTagsCheck\" target=\"_blank\">", ":anchorClose" => "</a>"];
        if ($isAllowSmartyPhpTagsEnabled && 0 < $scanResultCount) {
            $transKey = "legacySmartyTagsWarning.description.tagsAndSetting";
        } else {
            if (0 < $scanResultCount) {
                $transKey = "legacySmartyTagsWarning.description.tagsOnly";
            } else {
                $transKey = "legacySmartyTagsWarning.description.settingOnly";
            }
        }
        $description = \AdminLang::trans($transKey, $transParams);
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description;
    }

    public function checkWarning2CheckoutInline()
    {
        $gatewayIdentifier = "tco";
        $gateway = new \WHMCS\Module\Gateway();
        if (!$gateway->isActiveGateway($gatewayIdentifier)) {
            return true;
        }
        $gateway->load($gatewayIdentifier);
        $settings = $gateway->getParams();
        return !(isset($settings["integrationMethod"]) && $settings["integrationMethod"] == "inline");
    }

    public function getWarningHTML2CheckoutInline()
    {
        $title = \AdminLang::trans("globalWarning.2CheckoutInline.title");
        $description = \AdminLang::trans("globalWarning.2CheckoutInline.description");
        $learnMore = \AdminLang::trans("global.learnMore");
        return "<span>\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    " . $title . ":\n</span>\n" . $description . "\n<a href=\"https://go.whmcs.com/1753/2Checkout-legacy-inline-deprecation\" target=\"_blank\">\n    " . $learnMore . " &raquo;\n</a>";
    }
}
