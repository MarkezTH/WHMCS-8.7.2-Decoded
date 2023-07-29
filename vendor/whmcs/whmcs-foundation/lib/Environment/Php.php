<?php

namespace WHMCS\Environment;

class Php
{
    protected static $versionSupport = ["5.3" => ["active" => "14 Aug 2013", "security" => "14 Aug 2014"], "5.4" => ["active" => "14 Sep 2014", "security" => "14 Sep 2015"], "5.5" => ["active" => "10 Jul 2015", "security" => "10 Jul 2016"], "5.6" => ["active" => "31 Dec 2016", "security" => "31 Dec 2018"], "7.0" => ["active" => "03 Dec 2017", "security" => "03 Dec 2018"], "7.1" => ["active" => "01 Dec 2018", "security" => "01 Dec 2019"], "7.2" => ["active" => "30 Nov 2019", "security" => "30 Nov 2020"], "7.3" => ["active" => "06 Dec 2020", "security" => "06 Dec 2021"], "7.4" => ["active" => "28 Nov 2021", "security" => "28 Nov 2022"], "8.0" => ["active" => "26 Nov 2022", "security" => "26 Nov 2023"], "8.1" => ["active" => "25 Nov 2023", "security" => "25 Nov 2024"]];
    protected static $myUid = NULL;

    public static function functionEnabled($function)
    {
        $disabledFunctions = preg_split("/\\s*\\,\\s*/", trim(ini_get("disable_functions")));
        return (bool) ($function !== "" && !in_array(strtolower($function), $disabledFunctions));
    }

    public static function getIniSetting($setting)
    {
        return ini_get($setting);
    }

    public static function isIniSettingEnabled($setting)
    {
        $value = self::getIniSetting($setting);
        if (is_numeric($value)) {
            return (bool) (int) $value;
        }
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ["on", "true", "yes"])) {
                return true;
            }
            if (in_array($value, ["off", "false", "no"])) {
                return false;
            }
        }
        return (bool) $value;
    }

    public static function isFunctionAvailable($function)
    {
        return function_exists($function) && self::functionEnabled($function);
    }

    public static function isClassAvailable($class)
    {
        return class_exists($class);
    }

    public static function isModuleActive($module)
    {
        return extension_loaded($module);
    }

    public static function isCli()
    {
        php_sapi_name();
        switch (php_sapi_name()) {
            case "cli":
            case "cli-server":
                return true;
                break;
            default:
                if (!isset($_SERVER["SERVER_NAME"]) && !isset($_SERVER["HTTP_HOST"])) {
                    return true;
                }
                return false;
        }
    }

    public static function getUserRunningPhp()
    {
        if (!is_null(static::$myUid)) {
            return static::$myUid;
        }
        $tempFilename = tempnam(\App::getApplicationConfig()->templates_compiledir, "tmp");
        touch($tempFilename);
        static::$myUid = fileowner($tempFilename);
        unlink($tempFilename);
        return static::$myUid;
    }

    public static function hasValidTimezone()
    {
        $tz = ini_get("date.timezone");
        $tzOld = date_default_timezone_get();
        if ($tz) {
            $tzValid = date_default_timezone_set($tz) ? true : false;
            if ($tzOld) {
                date_default_timezone_set($tzOld);
            }
        } else {
            $tzValid = false;
        }
        return $tzValid;
    }

    public static function hasExtension($extension)
    {
        return extension_loaded($extension);
    }

    public static function isSessionAutoStartEnabled()
    {
        return (bool) ini_get("session.auto_start");
    }

    public static function isSessionSavePathWritable()
    {
        return is_writable(ini_get("session.save_path"));
    }

    public static function isSupportedByWhmcs($version = PHP_VERSION)
    {
        return version_compare($version, "7.2", ">=");
    }

    public static function hasActivePhpSupport($majorMinor)
    {
        if (!isset(static::$versionSupport[$majorMinor])) {
            return false;
        }
        return \WHMCS\Carbon::createFromFormat("d M Y", static::$versionSupport[$majorMinor]["active"])->isFuture();
    }

    public static function hasSecurityPhpSupport($majorMinor)
    {
        if (!isset(static::$versionSupport[$majorMinor])) {
            return false;
        }
        return \WHMCS\Carbon::createFromFormat("d M Y", static::$versionSupport[$majorMinor]["security"])->isFuture();
    }

    public static function convertMemoryLimitToBytes($memoryLimit)
    {
        if (is_int($memoryLimit) || is_float($memoryLimit)) {
            return $memoryLimit;
        }
        $memoryLimit = trim($memoryLimit);
        $memoryLimitModifier = $memoryLimit[strlen($memoryLimit) - 1];
        $memoryLimitNumeric = (int) $memoryLimit;
        switch ($memoryLimitModifier) {
            case "G":
                $memoryLimitNumeric *= 1024;
            case "M":
                $memoryLimitNumeric *= 1024;
            case "K":
                $memoryLimitNumeric *= 1024;
        }
        return $memoryLimitNumeric;
    }

    public static function getPhpMemoryLimitInBytes()
    {
        return static::convertMemoryLimitToBytes(ini_get("memory_limit"));
    }

    public static function hasErrorLevelEnabled($errorLevels, $checkLevel)
    {
        return (bool) ($errorLevels & $checkLevel);
    }

    public static function getVersion()
    {
        return PHP_VERSION;
    }

    public static function getLoadedExtensions()
    {
        return get_loaded_extensions();
    }

    public static function getPreferredCliBinary()
    {
        try {
            if (static::isFunctionAvailable("php_ini_loaded_file")) {
                $iniPath = php_ini_loaded_file();
                if (strpos($iniPath, "/opt/cpanel/") === 0) {
                    $phpBinary = preg_replace("/etc\\/php.ini\$/", "usr/bin/php", $iniPath);
                    if (file_exists($phpBinary)) {
                        return $phpBinary;
                    }
                }
            }
        } catch (\Error $e) {
        } catch (\Exception $e) {
        }
        $potentialPhpBinaries = ["/usr/bin/php-cli", "/usr/local/bin/php-cli", "/usr/bin/php", "/usr/local/bin/php"];
        foreach ($potentialPhpBinaries as $phpBinary) {
            if (file_exists($phpBinary)) {
                return $phpBinary;
            }
        }
        return "php";
    }

    public static function info()
    {
        if (!static::isFunctionAvailable("phpinfo")) {
            return NULL;
        }
        ob_start();
        phpinfo();
        return ob_get_clean();
    }
}
