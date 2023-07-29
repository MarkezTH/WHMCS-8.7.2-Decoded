<?php

namespace WHMCS\Updater\Version;

class Version761release1 extends IncrementalVersion
{
    protected $updateActions = ["correctWhmcsWhoisToWhmcsDomains"];

    protected function correctWhmcsWhoisToWhmcsDomains()
    {
        $query = \WHMCS\Database\Capsule::table("tblconfiguration")->where("setting", "domainLookupProvider");
        if (!$query->count()) {
            \WHMCS\Config\Setting::setValue("domainLookupProvider", "WhmcsDomains");
        } else {
            $settingNotConverted = \WHMCS\Database\Capsule::table("tblconfiguration")->where("setting", "domainLookupProvider")->whereIn("value", ["BasicWhois", "WhmcsWhois", ""])->where("updated_at", "<", "2018-06-28 00:00:00")->first();
            if ($settingNotConverted) {
                \WHMCS\Config\Setting::setValue("domainLookupProvider", "WhmcsDomains");
            }
        }
        return $this;
    }
}
