<?php

namespace WHMCS\Utility;

class SafeInclude
{
    public static function file($filename, $failureCallback = NULL)
    {
        $config = \DI::make("config");
        if (is_null($config->enable_safe_include)) {
            $safeIncludeEnabled = (bool) \WHMCS\Config\Setting::getValue("EnableSafeInclude");
        } else {
            $safeIncludeEnabled = (bool) $config->enable_safe_include;
        }
        try {
            if ($safeIncludeEnabled) {
                $ioncubeFile = new \WHMCS\Environment\Ioncube\EncodedFile($filename);
                if (!$ioncubeFile->canRunOnInstalledPhpVersion()) {
                    if (!is_null($failureCallback)) {
                        $failureCallback("This file cannot be used with installed PHP " . PHP_VERSION . ": " . $filename);
                    }
                    return false;
                }
                if (!$ioncubeFile->canRunOnInstalledIoncubeLoader()) {
                    if (!is_null($failureCallback)) {
                        $loaderVersion = \WHMCS\Environment\Ioncube\Loader\LocalLoader::getVersion();
                        if ($loaderVersion) {
                            $errorMessage = "This file cannot be used with installed ionCube Loader " . $loaderVersion->getVersion();
                        } else {
                            $errorMessage = "This file cannot be used without a supported ionCube Loader extension";
                        }
                        $errorMessage .= ": " . $filename;
                        $failureCallback($errorMessage);
                    }
                    return false;
                }
            }
        } catch (\Exception $e) {
        }
        if (version_compare(PHP_VERSION, "7.0", ">=")) {
            try {
                include_once $filename;
            } catch (\Exception $e) {
                if (!is_null($failureCallback)) {
                    $failureCallback("Could not include file: " . $filename . ". Error: " . $e->getMessage());
                }
            } catch (\Error $e) {
                if (!is_null($failureCallback)) {
                    $failureCallback("Could not include file: " . $filename . ". Error: " . $e->getMessage());
                }
            }
        } else {
            include_once $filename;
        }
        return true;
    }

    public static function criticalFile($filename, $failureCallback = NULL)
    {
        self::file($filename, function ($errorMessage) use($failureCallback) {
            if (!is_null($failureCallback)) {
                $failureCallback($errorMessage);
            }
            throw new \WHMCS\Exception\Fatal($errorMessage);
        });
    }
}
