<?php

namespace WHMCS\Environment\Ioncube\Loader;

class LocalLoader implements \WHMCS\Environment\Ioncube\Contracts\LoaderInterface
{
    private static $loader = NULL;
    private static $version = NULL;

    public function __construct(\WHMCS\Version\SemanticVersion $version = NULL)
    {
        if (!$version) {
            $version = static::getVersion();
        }
        if ($version) {
            self::$version = $version;
            self::$loader = $this->factoryLoader($version);
        } else {
            self::$version = NULL;
            self::$loader = NULL;
        }
    }

    public static function getVersion()
    {
        if (static::$version) {
            return static::$version;
        }
        if (!function_exists("ioncube_loader_version") || !function_exists("ioncube_loader_iversion")) {
            return NULL;
        }
        $versionAsNumber = ioncube_loader_iversion();
        $release = (int) ($versionAsNumber % 100);
        $versionAsNumber = floor($versionAsNumber / 100);
        $minor = (int) ($versionAsNumber % 100);
        $versionAsNumber = floor($versionAsNumber / 100);
        $major = $versionAsNumber;
        try {
            $fullVersionAsString = sprintf("%d.%d.%d", $major, $minor, $release);
            $reportedVersionAsString = ioncube_loader_version();
            if (substr_count($reportedVersionAsString, ".") === 1) {
                $reportedVersionAsString .= ".";
            }
            if (strpos($fullVersionAsString, $reportedVersionAsString) === 0) {
                static::$version = new \WHMCS\Version\SemanticVersion($fullVersionAsString);
            }
        } catch (\Exception $e) {
        }
        return static::$version;
    }

    public static function factoryLoader(\WHMCS\Version\SemanticVersion $version)
    {
        if (version_compare($version->getRelease(), "10.1.0", ">=")) {
            $loader = new Loader100100();
        } else {
            $loader = NULL;
        }
        return $loader;
    }

    public function compatAssessment($phpVersion, \WHMCS\Environment\Ioncube\Contracts\InspectedFileInterface $file)
    {
        $loader = $this->getInternalLoader();
        if (is_null($loader)) {
            return \WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_UNLIKELY;
        }
        return $loader->compatAssessment($phpVersion, $file);
    }

    public function supportsBundledEncoding()
    {
        $loader = $this->getInternalLoader();
        if (is_null($loader)) {
            return false;
        }
        return $loader->supportsBundledEncoding();
    }

    public function getInternalLoader()
    {
        return self::$loader;
    }

    public static function clearInstanceCaches()
    {
        $wereSetAndClearedCount = 0;
        if (!is_null(static::$version)) {
            static::$version = NULL;
            $wereSetAndClearedCount++;
        }
        if (!is_null(static::$loader)) {
            static::$loader = NULL;
            $wereSetAndClearedCount++;
        }
        return $wereSetAndClearedCount;
    }
}
