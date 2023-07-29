<?php

namespace WHMCS\Environment;

class WebServer
{
    private static $serverVersion = "";
    private static $phpInfoOutput = NULL;
    private static $serverFamily = "";
    const SERVER_NAME_APACHE = "Apache";
    const HTACCESS_FILE = ".htaccess";

    private static function getPhpInfo()
    {
        if (!is_null(static::$phpInfoOutput)) {
            return static::$phpInfoOutput;
        }
        $phpInfo = Php::info();
        if (is_null($phpInfo)) {
            static::$phpInfoOutput = "";
        }
        $phpInfo = preg_replace("/.*<body\\s*>/is", "", $phpInfo);
        $phpInfo = preg_replace("/<\\/body>.*/is", "", $phpInfo);
        $phpInfo = preg_replace("/<br\\s*(\\/)?>/i", "\n", $phpInfo);
        $phpInfo = strip_tags($phpInfo);
        static::$phpInfoOutput = trim($phpInfo);
        return static::$phpInfoOutput;
    }

    private static function parseServerInfo()
    {
        if (isset($_SERVER["SERVER_SOFTWARE"])) {
            $serverSoftware = $_SERVER["SERVER_SOFTWARE"];
            if (preg_match("/^([^\\s]+)\\/([a-z\\d\\.]+)/i", $serverSoftware, $matches)) {
                list(static::$serverFamily, static::$serverVersion) = $matches;
            } else {
                if (preg_match("/^([^\\s]+)\\s/i", $serverSoftware, $matches)) {
                    $serverFamily = $matches[1];
                    $serverVersion = "Unknown";
                    $aliasedServerFamilies = ["centos webpanel: protected by mod security" => "Apache"];
                    $serverSoftwareKey = trim(strtolower($serverSoftware));
                    if (array_key_exists($serverSoftwareKey, $aliasedServerFamilies)) {
                        $serverFamily = $aliasedServerFamilies[$serverSoftwareKey];
                        if (empty($serverFamily)) {
                            $serverFamily = "Other";
                        }
                        $serverVersion = $serverSoftware;
                    }
                    static::$serverFamily = $serverFamily;
                    static::$serverVersion = $serverVersion;
                } else {
                    $knownServers = ["Apache", "LiteSpeed", "lighttpd", "Microsoft-IIS", "nginx"];
                    static::$serverFamily = "";
                    foreach ($knownServers as $knownServer) {
                        if (stripos($serverSoftware, $knownServer) !== false) {
                            static::$serverFamily = $knownServer;
                            static::$serverVersion = "Unknown";
                        }
                    }
                    if (!static::$serverFamily) {
                        static::$serverFamily = "Other";
                        static::$serverVersion = $serverSoftware;
                    }
                }
            }
            return true;
        }
        return false;
    }

    public static function getServerFamily()
    {
        if (!static::$serverFamily) {
            static::parseServerInfo();
        }
        return static::$serverFamily;
    }

    public static function getServerVersion()
    {
        if (!static::$serverVersion) {
            static::parseServerInfo();
        }
        return static::$serverVersion;
    }

    public static function getControlPanelInfo()
    {
        $cpFlags = ["/usr/local/cpanel" => ["family" => "cPanel", "versionFile" => "/usr/local/cpanel/version"], "/usr/local/psa" => ["family" => "Plesk", "versionFile" => "/usr/local/psa/version"], "/usr/local/directadmin" => ["family" => "DirectAdmin", "versionFile" => "/usr/local/directadmin/custombuild/versions.txt", "versionFileRegex" => "/^[\\s]*directadmin:([^:\\r\\n]+):[\\s]*\$/im"]];
        $panelFamily = "Unknown";
        $panelVersion = "";
        foreach ($cpFlags as $flagFile => $cpInfo) {
            if (file_exists($flagFile)) {
                $panelVersionFileContent = @file_get_contents($cpInfo["versionFile"]);
                if (isset($cpInfo["versionFileRegex"])) {
                    if (preg_match($cpInfo["versionFileRegex"], $panelVersionFileContent, $matches)) {
                        $panelVersion = $matches[1];
                    }
                } else {
                    $panelVersion = $panelVersionFileContent;
                }
                $panelVersion = trim(preg_replace("/[^A-Z\\d\\.\\- ]+/i", " ", $panelVersion));
                $panelFamily = $cpInfo["family"];
                return ["family" => $panelFamily, "version" => $panelVersion];
            }
        }
    }

    public static function isApache()
    {
        return in_array(strtolower(static::getServerFamily()), ["apache", "httpd"]);
    }

    public static function isLiteSpeed()
    {
        return strtolower(static::getServerFamily()) === "litespeed";
    }

    public static function isIis()
    {
        return strtolower(static::getServerFamily()) === "microsoft-iis";
    }

    public static function supportsHtaccess()
    {
        return static::isApache() || static::isLiteSpeed();
    }

    public static function hasModRewrite()
    {
        $controlPanelInfo = static::getControlPanelInfo();
        if (strcasecmp("cpanel", $controlPanelInfo["family"]) === 0) {
            return true;
        }
        return stripos(static::getPhpInfo(), "mod_rewrite") !== false;
    }

    public static function hasRootHtaccess()
    {
        return (bool) file_exists(ROOTDIR . DIRECTORY_SEPARATOR . static::HTACCESS_FILE);
    }

    public static function hasAdminHtaccess()
    {
        $adminPath = \DI::make("config")->customadminpath;
        if (!$adminPath) {
            $adminPath = \WHMCS\Config\Application::DEFAULT_ADMIN_FOLDER;
        }
        return (bool) file_exists(ROOTDIR . DIRECTORY_SEPARATOR . $adminPath . DIRECTORY_SEPARATOR . static::HTACCESS_FILE);
    }

    public static function getExternalCommunicationIp()
    {
        if (is_null($ip)) {
            $contents = curlCall("https://api1.whmcs.com/ip/get", "", ["CURLOPT_RETURNTRANSFER" => 1, "CURLOPT_FOLLOWLOCATION" => 1]);
            if (!empty($contents)) {
                $data = json_decode($contents, true);
                if (is_array($data) && isset($data["ip"])) {
                    $ip = $data["ip"];
                }
            }
        }
        return $ip;
    }
}
