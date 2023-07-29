<?php

namespace WHMCS\Cron\Task;

class SystemConfiguration extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $accessLevel = \WHMCS\Scheduling\Task\TaskInterface::ACCESS_SYSTEM;
    protected $defaultPriority = 800;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "System Configuration Check";
    protected $defaultName = "System Configuration Check";
    protected $systemName = "SystemConfiguration";
    protected $outputs = [];

    public function __invoke()
    {
        \WHMCS\Config\Setting::setValue("CronPHPVersion", \WHMCS\Environment\Php::getVersion());
        \WHMCS\Config\Setting::setValue("SystemStatsCache", $this->generateSystemStats());
        \WHMCS\Config\Setting::setValue("ComponentStatsCache", $this->getComponentReport()->toJson());
        $this->checkPopCron();
        $this->pruneActivityLog();
        $this->pruneModuleLog();
        $this->pruneStaleAsyncJobs();
        $this->syncCentralNicRegistrarZones();
        \WHMCS\Log\ErrorLog::prune();
        return $this;
    }

    private function getDbColumnCollationStats()
    {
        $whmcsEnv = new \WHMCS\Environment\WHMCS();
        $collationInfo = $whmcsEnv->getDbCollations();
        if (!is_array($collationInfo) || !isset($collationInfo["columns"]) || !is_array($collationInfo["columns"])) {
            return [];
        }
        $collationCounts = [];
        foreach ($collationInfo["columns"] as $column) {
            $collationCounts[$column->collation] = count(explode(",", $column->entity_names));
        }
        arsort($collationCounts);
        $stats = ["synced" => (bool) count($collationCounts) === 1, "collations" => array_keys($collationCounts), "stats" => $collationCounts];
        return $stats;
    }

    private function checkPopCron()
    {
        if (!\WHMCS\Support\Department::where("host", "!=", "")->exists()) {
            return NULL;
        }
        if (\WHMCS\TransientData::getInstance()->retrieve("popCronComplete")) {
            return NULL;
        }
        sendAdminNotification("system", "WHMCS Pop Cron Did Not Run", "<p>Your WHMCS install contains support departments that are configured to receive email via POP3 Import. However, WHMCS POP cron has not completed recently.</p><p>This message will be sent daily until POP cron is restored or POP3 import is disabled for all relevant support departments. <a href=\"https://go.whmcs.com/1467/pop-cron-did-not-run\">Learn more</a></p>");
    }

    private function pruneActivityLog()
    {
        $activityLog = new \WHMCS\Log\Activity();
        $activityLog->prune();
    }

    private function pruneModuleLog()
    {
        if ((bool) \WHMCS\Config\Setting::getValue("ModuleLogPruningEnabled")) {
            $daysToRetain = (int) \WHMCS\Config\Setting::getValue("ModuleLogRetentionDays") ?: 30;
            $beforeDate = \WHMCS\Carbon::now()->subDays($daysToRetain)->endOfDay()->toDateTimeString();
            \WHMCS\Database\Capsule::table("tblmodulelog")->whereDate("date", "<", $beforeDate)->delete();
        }
    }

    private function pruneStaleAsyncJobs()
    {
        (new \WHMCS\Scheduling\Jobs\Queue())->prune();
    }

    public function getComponentReport()
    {
        $report = new \WHMCS\Environment\Report(\WHMCS\Environment\Report::TYPE_STATE, [new \WHMCS\Environment\WHMCS\UsageBilling()]);
        return $report;
    }

    public function generateSystemStats()
    {
        $productModules = $productModules30 = $productModules90 = $productAddonModules = $productAddonModules30 = $productAddonModules90 = $domainModules = $domainModules30 = $domainModules90 = $invoicePaidLast30d = $invoiceModules = $addonModules = $enabledFraud = $fraudModuleOfOrders = $servers = $orderforms = $defaultOrderform = $clientTheme = $adminThemes = $wptk = [];
        $clientStatus = \WHMCS\Database\Capsule::table((new \WHMCS\User\Client())->getTable())->selectRaw("status, count(id) as c")->groupBy("status")->orderBy("status", "asc")->pluck("c", "status")->toArray();
        $result = @mysql_query("SELECT DISTINCT(tblproducts.servertype), COUNT(tblhosting.id) FROM tblhosting INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid WHERE domainstatus='Active' GROUP BY tblproducts.servertype ORDER BY tblproducts.servertype ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $productModules[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT DISTINCT(tblproducts.servertype), COUNT(tblhosting.id) FROM tblhosting INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid WHERE domainstatus='Active'  AND tblhosting.regdate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY tblproducts.servertype ORDER BY tblproducts.servertype ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $productModules30[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT DISTINCT(tblproducts.servertype), COUNT(tblhosting.id) FROM tblhosting INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid WHERE domainstatus='Active'  AND tblhosting.regdate >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY tblproducts.servertype ORDER BY tblproducts.servertype ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $productModules90[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT DISTINCT(tbladdons.module), COUNT(tblhostingaddons.id) FROM tblhostingaddons INNER JOIN tbladdons ON tbladdons.id=tblhostingaddons.addonid WHERE status='Active' GROUP BY tbladdons.module ORDER BY tbladdons.module");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $productAddonModules[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT DISTINCT(tbladdons.module), COUNT(tblhostingaddons.id) FROM tblhostingaddons INNER JOIN tbladdons ON tbladdons.id=tblhostingaddons.addonid WHERE status='Active'  AND tblhostingaddons.regdate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY tbladdons.module ORDER BY tbladdons.module");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $productAddonModules30[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT DISTINCT(tbladdons.module), COUNT(tblhostingaddons.id) FROM tblhostingaddons INNER JOIN tbladdons ON tbladdons.id=tblhostingaddons.addonid WHERE status='Active'  AND tblhostingaddons.regdate >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY tbladdons.module ORDER BY tbladdons.module");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $productAddonModules90[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT registrar, COUNT(id) FROM tbldomains WHERE status='Active' GROUP BY registrar ORDER BY registrar ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $domainModules[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT registrar, COUNT(id) FROM tbldomains WHERE status='Active'  AND tbldomains.registrationdate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY registrar ORDER BY registrar ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $domainModules30[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT registrar, COUNT(id) FROM tbldomains WHERE status='Active'  AND tbldomains.registrationdate >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY registrar ORDER BY registrar ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $domainModules90[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT paymentmethod, COUNT(id) FROM tblinvoices WHERE status='Paid' GROUP BY paymentmethod ORDER BY paymentmethod ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $invoiceModules[$data[0]] = $data[1];
                }
            }
        }
        $isTcoInline = $tcoEnabled = false;
        if (array_key_exists("tco", $invoiceModules)) {
            $tcoEnabled = true;
            $isTcoInline = \WHMCS\Module\GatewaySetting::getValue("tco", "integrationMethod") === "inline";
        }
        $result = @mysql_query("SELECT paymentmethod, COUNT(id) FROM tblinvoices WHERE status='Paid' and datepaid >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY paymentmethod ORDER BY paymentmethod ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $invoicePaidLast30d[$data[0]] = $data[1];
                }
            }
        }
        $result = @mysql_query("SELECT module, value FROM tbladdonmodules WHERE setting = 'version' ORDER BY module ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $addonModules[$data[0]] = $data[1];
                }
            }
        }
        $enabledFraud = \WHMCS\Database\Capsule::table("tblfraud")->where("setting", "Enable")->where("value", "!=", "")->value("fraud");
        if (!$enabledFraud) {
            $enabledFraud = "NONE";
        }
        $result = @mysql_query("SELECT fraudmodule, COUNT(id) FROM tblorders WHERE  date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY fraudmodule ORDER BY fraudmodule");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    if (empty($data[0])) {
                        $data[0] = "NONE";
                    }
                    $fraudModuleOfOrders[$data[0]] = $data[1];
                }
            }
        }
        $notificationModules = \WHMCS\Notification\Provider::pluck("active", "name");
        $providerKey = \WHMCS\Config\Setting::getValue("domainLookupProvider");
        if ($providerKey == "Registrar") {
            $providerKey .= "." . \WHMCS\Config\Setting::getValue("domainLookupRegistrar");
        }
        $domainLookupProvider = [$providerKey => 1];
        $result = @mysql_query("SELECT DISTINCT(type), COUNT(id) from tblservers WHERE disabled != 1 GROUP BY type");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                if (is_array($data) && count($data) == 4) {
                    $servers[$data[0]] = $data[1];
                }
            }
        }
        $appLinks = [];
        $result = @mysql_query("SELECT module_type, module_name FROM tblapplinks WHERE is_enabled = 1 ORDER BY module_type ASC, module_name ASC");
        if ($result) {
            while ($data = @mysql_fetch_array($result)) {
                $moduleType = $data["module_type"];
                $moduleName = $data["module_name"];
                $entityCount = 0;
                if ($moduleType == "servers") {
                    $entityCount = get_query_val("tblservers", "COUNT(id)", ["type" => $moduleName, "disabled" => "0"]);
                }
                $appLinks[$moduleType . "_" . $moduleName] = $entityCount;
            }
        }
        $languages = [];
        $defaultLanguage = strtolower(\WHMCS\Config\Setting::getValue("Language"));
        $languages["systemDefault"] = $defaultLanguage;
        $languages["clientUsage"] = \WHMCS\Database\Capsule::table("tblclients")->groupBy("language")->orderBy("language")->pluck(\WHMCS\Database\Capsule::raw("count(language) AS cnt"), \WHMCS\Database\Capsule::raw("IF(language='', 'default', language) AS language"))->all();
        if (!isset($languages["clientUsage"]["default"])) {
            $languages["clientUsage"]["default"] = 0;
        }
        if (isset($languages["clientUsage"][$defaultLanguage])) {
            $languages["clientUsage"]["default"] += $languages["clientUsage"][$defaultLanguage];
            unset($languages["clientUsage"][$defaultLanguage]);
        }
        $languages["clientUsage"][$defaultLanguage] = $languages["clientUsage"]["default"];
        unset($languages["clientUsage"]["default"]);
        ksort($languages["clientUsage"]);
        $languages["adminUsage"] = \WHMCS\Database\Capsule::table("tbladmins")->groupBy("language")->orderBy("language")->pluck(\WHMCS\Database\Capsule::raw("count(language) AS cnt"), \WHMCS\Database\Capsule::raw("IF(language='', 'default', language) AS language"))->all();
        if (!isset($languages["adminUsage"]["default"])) {
            $languages["adminUsage"]["default"] = 0;
        }
        if (isset($languages["adminUsage"][$defaultLanguage])) {
            $languages["adminUsage"]["default"] += $languages["adminUsage"][$defaultLanguage];
            unset($languages["adminUsage"][$defaultLanguage]);
        }
        $languages["adminUsage"][$defaultLanguage] = $languages["adminUsage"]["default"];
        unset($languages["adminUsage"]["default"]);
        ksort($languages["adminUsage"]);
        $backupSystems = (new \WHMCS\Backups\Backups())->getActiveProviders();
        $twofa = new \WHMCS\TwoFactorAuthentication();
        $duosecurity = $totp = $yubico = false;
        $twoFactorModules = ["duosecurity", "totp", "yubico"];
        foreach ($twoFactorModules as $module) {
            ${$module} = $twofa->isModuleEnabled($module);
        }
        $gracePeriods = \WHMCS\Domains\Extension::where("grace_period", ">", -1)->orWhere("grace_period_fee", ">", -1)->count();
        $redemptionPeriods = \WHMCS\Domains\Extension::where("redemption_grace_period", ">", -1)->orWhere("redemption_grace_period_fee", ">", -1)->count();
        try {
            $remoteAuth = new \WHMCS\Authentication\Remote\RemoteAuth();
            $authProviders = [];
            foreach ($remoteAuth->getEnabledProviders() as $provider) {
                $authProviders[$provider::NAME] = \WHMCS\Authentication\Remote\AccountLink::where("provider", $provider::NAME)->count();
            }
        } catch (\Exception $e) {
        }
        try {
            $dbCollationStats = $this->getDbColumnCollationStats();
        } catch (\Exception $e) {
            $dbCollationStats = [];
        }
        $captchaSettings = ["setting" => "disabled", "type" => "", "invisible" => ""];
        $captchaSetting = \WHMCS\Config\Setting::getValue("CaptchaSetting");
        if ($captchaSetting) {
            $captchaType = \WHMCS\Config\Setting::getValue("CaptchaType");
            $captchaSettings["setting"] = $captchaSetting;
            $captchaSettings["type"] = $captchaType ?: "default";
        }
        $distroForms = ["boxes", "premium_comparison", "standard_cart", "universal_slider", "cloud_slider", "modern", "pure_comparison", "supreme_comparison", "ajaxcart", "cart", "comparison", "slider", "verticalsteps", "web20cart"];
        $defaultOrderform = \WHMCS\Config\Setting::getValue("OrderFormTemplate");
        if (!in_array($defaultOrderform, $distroForms)) {
            $defaultOrderform = "CUSTOM";
        }
        $groupForms = \WHMCS\Database\Capsule::table("tblproductgroups")->where("hidden", "!=", 1)->groupBy("orderfrmtpl")->pluck("orderfrmtpl")->all();
        foreach ($groupForms as $tmpl) {
            if (!$tmpl) {
                $tmpl = $defaultOrderform;
            }
            if (!in_array($tmpl, $distroForms)) {
                $defaultOrderform = "CUSTOM";
            }
            $orderforms[$tmpl] = 1;
        }
        $distroThemes = ["six", "portal", "classic", "default"];
        $clientTheme = \WHMCS\Config\Setting::getValue("Template");
        if (!in_array($clientTheme, $distroThemes)) {
            $clientTheme = "CUSTOM";
        }
        $distroAdminThemes = ["blend", "v4", "original"];
        $themes = \WHMCS\Database\Capsule::table("tbladmins")->where("disabled", 0)->groupBy("template")->pluck("template")->all();
        foreach ($themes as $tmpl) {
            if (!in_array($tmpl, $distroAdminThemes)) {
                $tmpl = "CUSTOM";
            }
            $adminThemes[$tmpl] = 1;
        }
        $wptk = $this->getWPTKStats();
        $wpInstalls = $this->getWPInstallStats();
        $systemStats = ["clientStatus" => $clientStatus, "productModules" => $productModules, "productModules30" => $productModules30, "productModules90" => $productModules90, "productAddonModules" => $productAddonModules, "productAddonModules30" => $productAddonModules30, "productAddonModules90" => $productAddonModules90, "domainModules" => $domainModules, "domainModules30" => $domainModules30, "domainModules90" => $domainModules90, "invoiceModules" => $invoiceModules, "invoicePaidLast30d" => $invoicePaidLast30d, "addonModules" => $addonModules, "enabledFraudModule" => [$enabledFraud => 1], "fraudModuleOfOrders" => $fraudModuleOfOrders, "notificationModules" => $notificationModules, "domainLookupProvider" => $domainLookupProvider, "servers" => $servers, "appLinks" => $appLinks, "authProviders" => $authProviders, "defaultOrderform" => [$defaultOrderform => 1], "orderforms" => $orderforms, "clientTheme" => [$clientTheme => 1], "adminThemes" => $adminThemes, "backups" => ["ftp" => (bool) in_array("ftp", $backupSystems), "cpanel" => (bool) in_array("cpanel", $backupSystems), "email" => in_array("email", $backupSystems)], "twoFactorAuth" => ["duo" => $duosecurity, "totp" => $totp, "yubikey" => $yubico], "featureShowcase" => is_string(\WHMCS\Config\Setting::getValue("WhatNewLinks")) ? json_decode(\WHMCS\Config\Setting::getValue("WhatNewLinks"), true) : NULL, "autoUpdate" => ["count" => (int) \WHMCS\Config\Setting::getValue("AutoUpdateCount"), "success" => (int) \WHMCS\Config\Setting::getValue("AutoUpdateCountSuccess")], "languages" => $languages, "dbCollationStats" => $dbCollationStats, "hasSslAvailable" => \App::isSSLAvailable(), "captcha" => $captchaSettings, "domainFeatures" => ["isEnabled" => (int) (!(bool) (int) \WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")), "gracePeriods" => $gracePeriods, "redemptionPeriods" => $redemptionPeriods], "domainSyncSettings" => ["isEnabled" => (bool) \WHMCS\Config\Setting::getValue("DomainSyncEnabled"), "dueDateSyncEnabled" => (bool) \WHMCS\Config\Setting::getValue("DomainSyncNextDueDate"), "dueDateSyncDays" => (int) \WHMCS\Config\Setting::getValue("DomainSyncNextDueDateDays"), "notifyOnlyEnabled" => (bool) \WHMCS\Config\Setting::getValue("DomainSyncNotifyOnly"), "statusSyncHours" => (int) \WHMCS\Config\Setting::getValue("DomainStatusSyncFrequency"), "transferPendingSyncHours" => (int) \WHMCS\Config\Setting::getValue("DomainTransferStatusCheckFrequency")], "smartyPhpTagsAllowed" => (bool) \WHMCS\Config\Setting::getValue("AllowSmartyPhpTags"), "wptk" => $wptk, "wordpressInstallations" => $wpInstalls, "additionalModuleInfo" => []];
        if ($tcoEnabled) {
            $systemStats["additionalModuleInfo"]["tcoInline"] = $isTcoInline;
        }
        return json_encode($systemStats);
    }

    protected function getWPTKStats()
    {
        $configurations = \WHMCS\Config\Module\ModuleConfiguration::typeAddon()->provisioningType()->where("value", "feature")->with("productAddon", "productAddon.moduleConfiguration")->get();
        $cpanelAddOn = $pleskAddOn = $invoiceCount = 0;
        $serviceAddons = [];
        foreach ($configurations as $configuration) {
            $addon = $configuration->productAddon;
            switch ($addon->module) {
                case "cpanel":
                    $config = $addon->moduleConfiguration()->where("setting_name", "configoption1")->first();
                    if ($config && $config->value === "wp-toolkit-deluxe") {
                        $cpanelAddOn++;
                    }
                    break;
                case "plesk":
                    $config = $addon->moduleConfiguration()->where("setting_name", "configoption1")->first();
                    if ($config && $config->value === "Plesk WordPress Toolkit with Smart Updates") {
                        $pleskAddOn++;
                        $serviceAddons = $serviceAddons + $addon->serviceAddons()->pluck("id")->toArray();
                    }
                    break;
            }
        }
        if ($serviceAddons) {
            $invoiceCount += \WHMCS\Database\Capsule::table("tblinvoiceitems")->join("tblinvoices", "tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->where("tblinvoiceitems.type", "Addon")->where("tblinvoiceitems.invoiceid", "!=", 0)->whereIn("tblinvoiceitems.relid", $serviceAddons)->whereDate("tblinvoices.date", ">=", \WHMCS\Carbon::today()->startOfDay()->subDays(30))->count();
        }
        $clicks = $this->getLandingPageClicks();
        $wptkClicks = $clicks["wptk"];
        $wptk = $wptkClicks["landing"];
        unset($wptk["lifetime"]);
        if (30 < count($wptk)) {
            $wptk = $this->filterThirtyDays($wptk);
        }
        $landingThirtyDays = array_sum($wptk);
        $wptk["lifetime"] = $wptkClicks["landing"]["lifetime"];
        $wptkClicks["landing"] = $wptk;
        $wptk = $wptkClicks["cart"];
        unset($wptk["lifetime"]);
        if (30 < count($wptk)) {
            $wptk = $this->filterThirtyDays($wptk);
        }
        $cartThirtyDays = array_sum($wptk);
        $wptk["lifetime"] = $wptkClicks["cart"]["lifetime"];
        $wptkClicks["cart"] = $wptk;
        $clicks["wptk"] = $wptkClicks;
        \WHMCS\Config\Setting::setValue("LandingPages", json_encode($clicks));
        return ["cPanelAddOn" => $cpanelAddOn, "pleskAddOn" => $pleskAddOn, "cPanelLandingPage.hits.30Days" => $landingThirtyDays, "cPanelLandingPage.hits.Lifetime" => $wptkClicks["landing"]["lifetime"], "cPanelLandingPage.cart.30Days" => $cartThirtyDays, "cPanelLandingPage.cart.Lifetime" => $wptkClicks["cart"]["lifetime"], "activeBilledAddon" => $invoiceCount];
    }

    protected function getWPInstallStats()
    {
        $configurations = \WHMCS\Config\Module\ModuleConfiguration::typeProduct()->where("setting_name", "moduleActions")->get();
        $totalConfigured = $adminInstallAllowed = 0;
        $clientInstallAllowed = $automaticInstallation = 0;
        $totalWordpressInstances = $servicesCount = $servicesWithWPCount = 0;
        $productIds = [];
        foreach ($configurations as $configuration) {
            $info = json_decode(\WHMCS\Input\Sanitize::decode($configuration->value), true);
            $wordPressConfig = $info["InstallWordPress"] ?? NULL;
            if ($wordPressConfig) {
                if ($wordPressConfig["admin"] || $wordPressConfig["client"] || $wordPressConfig["auto"]) {
                    $totalConfigured++;
                    $productIds[] = $configuration->entityId;
                }
                if ($wordPressConfig["admin"]) {
                    $adminInstallAllowed++;
                }
                if ($wordPressConfig["client"]) {
                    $clientInstallAllowed++;
                }
                if ($wordPressConfig["auto"]) {
                    $automaticInstallation++;
                }
            }
        }
        if ($productIds) {
            $services = \WHMCS\Service\Service::isConsideredActive()->whereIn("packageid", $productIds)->get();
            $servicesCount = $services->count();
            foreach ($services as $service) {
                $wpInstances = $service->serviceProperties->get("WordPress Instances");
                if ($wpInstances) {
                    $wpInstances = json_decode(\WHMCS\Input\Sanitize::decode($wpInstances), true);
                    if ($wpInstances && json_last_error() === JSON_ERROR_NONE) {
                        $servicesWithWPCount++;
                        $totalWordpressInstances += count($wpInstances);
                    }
                }
            }
        }
        return ["total" => $totalConfigured, "total.adminInstallAllowed" => $adminInstallAllowed, "total.clientInstallAllowed" => $clientInstallAllowed, "total.automationInstallation" => $automaticInstallation, "total.services" => $servicesCount, "total.services_wp" => $servicesWithWPCount, "instances" => $totalWordpressInstances];
    }

    protected function getLandingPageClicks()
    {
        $date = \WHMCS\Carbon::today()->startOfDay();
        $clicks = json_decode(\WHMCS\Config\Setting::getValue("LandingPages") ?? "{}", true);
        if (empty($clicks) || !is_array($clicks)) {
            $clicks = [];
        }
        if (empty($clicks["wptk"])) {
            $clicks["wptk"] = [];
        }
        $wptkClicks = $clicks["wptk"];
        if (empty($wptkClicks["landing"])) {
            $wptkClicks["landing"] = ["lifetime" => 0, $date->toDateString() => 0];
        }
        if (empty($wptkClicks["cart"])) {
            $wptkClicks["cart"] = ["lifetime" => 0, $date->toDateString() => 0];
        }
        if (empty($wptkClicks["landing"]["lifetime"])) {
            $wptkClicks["landing"]["lifetime"] = 0;
        }
        if (empty($wptkClicks["cart"]["lifetime"])) {
            $wptkClicks["cart"]["lifetime"] = 0;
        }
        if (empty($wptkClicks["landing"][(string) $date->toDateString()])) {
            $wptkClicks["landing"][(string) $date->toDateString()] = 0;
        }
        if (empty($wptkClicks["cart"][(string) $date->toDateString()])) {
            $wptkClicks["cart"][(string) $date->toDateString()] = 0;
        }
        $clicks["wptk"] = $wptkClicks;
        return $clicks;
    }

    protected function filterThirtyDays($array)
    {
        return array_filter($array, function ($key) {
            $date = \WHMCS\Carbon::today()->startOfDay();
            $thirtyDaysAgo = $date->clone()->subDays(30);
            $keyDate = \WHMCS\Carbon::parse($key)->startOfDay();
            return $keyDate->lessThanOrEqualTo($date) && $keyDate->greaterThanOrEqualTo($thirtyDaysAgo);
        }, ARRAY_FILTER_USE_KEY);
    }

    protected function syncCentralNicRegistrarZones()
    {
        $registrar = new \WHMCS\Module\Registrar();
        $registrar->load("centralnic");
        if (!$registrar->isActivated()) {
            return NULL;
        }
        try {
            (new \WHMCS\Module\Registrar\CentralNic\ZonesUpdater((new \WHMCS\Module\Registrar\CentralNic\RRPProxyController($registrar->getSettings()))->getApi()))->loadZones()->update();
        } catch (\Exception $e) {
            logActivity("CentralNic registrar zones sync failed: " . $e->getMessage() . ".");
        }
    }
}
