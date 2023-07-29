<?php

class Plesk_Utils
{
    public static function getAccountsCount($userId)
    {
        $hostingAccounts = WHMCS\Database\Capsule::table("tblhosting")->join("tblservers", "tblservers.id", "=", "tblhosting.server")->where("tblhosting.userid", $userId)->where("tblservers.type", "plesk")->whereIn("tblhosting.domainstatus", ["Active", "Suspended", "Pending"])->count();
        $hostingAddonAccounts = WHMCS\Database\Capsule::table("tblhostingaddons")->join("tblservers", "tblhostingaddons.server", "=", "tblservers.id")->where("tblhostingaddons.userid", $userId)->where("tblservers.type", "plesk")->whereIn("status", ["Active", "Suspended", "Pending"])->count();
        return $hostingAccounts + $hostingAddonAccounts;
    }

    public static function dnsNormaliseHostname($dnsRecord, $domain)
    {
        if (!$dnsRecord["name"] && !$dnsRecord["host"]) {
            return false;
        }
        $dnsHost = $dnsRecord["name"] ?: $dnsRecord["host"];
        $dnsHost = trim($dnsHost, ".");
        $length = -1 * strlen($domain);
        if (substr($dnsHost, $length) == $domain) {
            $dnsHost = substr($dnsHost, 0, $length);
        }
        return trim($dnsHost, ".");
    }
}
