<?php

namespace WHMCS\Environment;

class WHMCS
{
    public function isConfigurationWritable()
    {
        return is_writable(ROOTDIR . DIRECTORY_SEPARATOR . "configuration.php");
    }

    public function hasCronCompletedInLastDay()
    {
        return !is_null(\WHMCS\Database\Capsule::table("tbltransientdata")->whereBetween("expires", [\WHMCS\Carbon::now()->timestamp, \WHMCS\Carbon::now()->addDay()->timestamp])->where("name", "cronComplete")->first(["data"]));
    }

    public function shouldPopCronRun()
    {
        return \WHMCS\Database\Capsule::table("tblticketdepartments")->where("host", "!=", "")->where("port", "!=", "")->where("login", "!=", "")->exists();
    }

    public function cronPhpVersion()
    {
        return \WHMCS\Config\Setting::getValue("CronPHPVersion");
    }

    public function hasPopCronRunInLastHour()
    {
        return !is_null(\WHMCS\Database\Capsule::table("tbltransientdata")->whereBetween("expires", [\WHMCS\Carbon::now()->timestamp, \WHMCS\Carbon::now()->addHour()->timestamp])->where("name", "popCronComplete")->first(["data"]));
    }

    public function isUsingADefaultOrderFormTemplate($template)
    {
        return in_array($template, ["boxes", "cart", "cloud_slider", "comparison", "modern", "premium_comparison", "pure_comparison", "slider", "standard_cart", "universal_slider"]);
    }

    public function isUsingADefaultSystemTemplate($template)
    {
        return in_array($template, ["classic", "five", "portal", "six"]);
    }

    public function isDisplayingErrors($databaseSetting, $configFileSetting = NULL)
    {
        if (!is_null($configFileSetting)) {
            return $configFileSetting;
        }
        return (bool) $databaseSetting;
    }

    public function getDbCollations()
    {
        $dbName = \WHMCS\Database\Capsule::schema()->getConnection()->getDatabaseName();
        $tables = \WHMCS\Database\Capsule::table("information_schema.tables")->selectRaw("GROUP_CONCAT(table_name) AS entity_names,LOWER(table_collation) AS collation")->where("table_schema", "=", $dbName)->whereNotNull("table_collation")->groupBy("collation")->get()->all();
        $columns = \WHMCS\Database\Capsule::table("information_schema.columns")->selectRaw("GROUP_CONCAT(concat(table_name, \".\", column_name)) AS entity_names,LOWER(collation_name) AS collation")->where("table_schema", "=", $dbName)->whereNotNull("collation_name")->groupBy("collation")->get()->all();
        return ["tables" => $tables, "columns" => $columns];
    }

    public function isUsingEncryptedEmailDelivery($smtpOption = "")
    {
        return in_array($smtpOption, ["ssl", "tls"]);
    }

    public function isUsingSMTP()
    {
        $mailConfig = \WHMCS\Module\Mail::getStoredConfiguration();
        return (bool) ($mailConfig["module"] == "SmtpMail");
    }

    public function isVendorWhmcsWhmcsWritable()
    {
        $vendorPath = ROOTDIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "whmcs";
        if (file_exists($vendorPath . DIRECTORY_SEPARATOR . "whmcs")) {
            return is_writable($vendorPath . DIRECTORY_SEPARATOR . "whmcs");
        }
        return is_writable($vendorPath);
    }

    public function isUpdateTmpPathSet()
    {
        $updater = new \WHMCS\Installer\Update\Updater();
        return $updater->isUpdateTempPathConfigured();
    }

    public function isUpdateTmpPathWriteable()
    {
        $updater = new \WHMCS\Installer\Update\Updater();
        return $updater->isUpdateTempPathWriteable();
    }

    public function hasEnoughMemoryForUpgrade($memoryLimitRequired = \WHMCS\View\Admin\HealthCheck\HealthCheckRepository::DEFAULT_MEMORY_LIMIT_FOR_AUTO_UPDATE)
    {
        $memoryLimit = Php::getPhpMemoryLimitInBytes();
        if ($memoryLimit < 0) {
            return true;
        }
        return $memoryLimitRequired <= $memoryLimit;
    }

    public static function systemId()
    {
        $id = \WHMCS\Config\Setting::getValue("systemUUID");
        if (!$id) {
            $id = \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_DNS, "whmcs.com"), (new \WHMCS\Security\Hash\Password())->hash(\App::get_hash()));
            \WHMCS\Config\Setting::setValue("systemUUID", $id);
        }
        return $id;
    }
}
