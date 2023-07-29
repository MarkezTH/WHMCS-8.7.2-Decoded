<?php

namespace WHMCS\Utility\Environment;

class WebHelper
{
    public static function getBaseUrl($root = ROOTDIR, $scriptName = NULL, $systemUrl = NULL)
    {
        $serverScriptName = isset($_SERVER["SCRIPT_NAME"]) && !is_null($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "";
        $scriptName = is_null($scriptName) ? $serverScriptName : $scriptName;
        if (\WHMCS\Environment\Php::isCli() && strpos($scriptName, $root) === 0) {
            if (is_null($systemUrl)) {
                $systemUrl = \App::getSystemURL();
            }
            if (is_string($systemUrl) && $systemUrl !== "") {
                return rtrim(parse_url($systemUrl, PHP_URL_PATH), "/");
            }
        }
        $root_cache_key = $root;
        if (isset($cache[$root_cache_key][$scriptName])) {
            return $cache[$root_cache_key][$scriptName];
        }
        $root = str_replace("\\", "/", $root);
        $segments = explode("/", trim($root, "/"));
        $segments = array_reverse($segments);
        $index = 0;
        $last = count($segments);
        $baseUrl = "";
        $found = true;
        while ($found && $index < $last) {
            $segment = $segments[$index];
            $baseUrl = "/" . $segment . $baseUrl;
            $index++;
            $found = strpos($scriptName, $baseUrl . "/") !== false;
        }
        $baseUrlSegments = explode("/", trim($baseUrl, "/"));
        array_shift($baseUrlSegments);
        $adminDir = \WHMCS\Config\Application::DEFAULT_ADMIN_FOLDER;
        $config = \DI::make("config");
        if (!empty($config->customadminpath)) {
            $adminDir = $config->customadminpath;
        }
        if (isset($baseUrlSegments[0]) && $baseUrlSegments[0] == $adminDir) {
            array_shift($baseUrlSegments);
        }
        $baseUrl = "/" . implode("/", $baseUrlSegments);
        if ($baseUrl == "/") {
            $baseUrl = "";
        }
        $cache[$root_cache_key][$scriptName] = $baseUrl;
        return $baseUrl;
    }

    public static function getAdminBaseUrl($root = ROOTDIR, $scriptName = NULL)
    {
        $basePath = static::getBaseUrl($root, $scriptName);
        $adminBase = \WHMCS\Admin\AdminServiceProvider::getAdminRouteBase();
        return $basePath . $adminBase;
    }

    public static function isUsingNonStandardWebPort()
    {
        $port = $_SERVER["SERVER_PORT"];
        $standardPorts = [80, 443];
        if (empty($port) || in_array($port, $standardPorts)) {
            return false;
        }
        return true;
    }

    public static function getWebPortInUse()
    {
        $port = $_SERVER["SERVER_PORT"];
        if (!empty($port) && is_numeric($port)) {
            return (int) $port;
        }
        return !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off" || $_SERVER["SERVER_PORT"] == 443 ? 443 : 80;
    }

    public static function getRelativePath($path, $serverScriptName)
    {
        $path = preg_replace("/\\/+/", "/", $path);
        $baseUrl = self::getBaseUrl(ROOTDIR, $serverScriptName);
        $baseInUrlPattern = "#^" . preg_quote($baseUrl . "/") . "#";
        if ($path !== $serverScriptName && strpos($path, "detect-route-environment") === false) {
            $scriptLessPath = preg_replace("#^" . preg_quote($serverScriptName) . "#", "", $path);
            if (is_null($serverScriptName) || $scriptLessPath !== $path) {
                $path = $scriptLessPath;
            } else {
                if (1 < strlen($path) && substr($path, -1) == "/") {
                    $path = substr($path, 0, -1);
                }
                if ($path === $baseUrl) {
                    $path = "/";
                } else {
                    if ($path !== $baseUrl && preg_match($baseInUrlPattern, $path)) {
                        $path = preg_replace("#^" . preg_quote($baseUrl) . "#", "", $path);
                    }
                }
            }
        } else {
            if (1 < strlen($path) && substr($path, -1) == "/") {
                $path = substr($path, 0, -1);
            }
            if ($path === $baseUrl) {
                $path = "/";
            } else {
                if ($path !== $baseUrl && preg_match($baseInUrlPattern, $path)) {
                    $path = preg_replace("#^" . preg_quote($baseUrl) . "#", "", $path);
                }
            }
        }
        if (1 < strlen($path) && substr($path, -1) == "/") {
            $path = substr($path, 0, -1);
        }
        return $path;
    }
}
