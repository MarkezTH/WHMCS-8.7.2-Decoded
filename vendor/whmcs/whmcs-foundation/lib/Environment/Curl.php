<?php

namespace WHMCS\Environment;

class Curl
{
    const OPENSSL_MIN_TLS_VERSION = "1.0.1c";
    const LAST_BAD_VERSION = "7.35.0";

    protected static function compareOpenSslVersion($checkVersion, $baseVersion)
    {
        $checkVersionNum = preg_replace("/[^\\d]*\$/", "", $checkVersion);
        $baseVersionNum = preg_replace("/[^\\d]*\$/", "", $baseVersion);
        $numCompare = version_compare($checkVersionNum, $baseVersionNum);
        return $numCompare != 0 ? $numCompare : strcmp($checkVersion, $baseVersion);
    }

    public static function hasKnownGoodVersion($curlVersion)
    {
        $version = isset($curlVersion["version"]) ? $curlVersion["version"] : "";
        $lastBadVersion = self::LAST_BAD_VERSION;
        return version_compare($version, $lastBadVersion, ">");
    }

    public static function hasSslSupport($curlVersion)
    {
        if (isset($curlVersion["features"])) {
            return ($curlVersion["features"] & CURL_VERSION_SSL) != 0;
        }
        return false;
    }

    public static function hasSecureTlsSupport($curlVersion)
    {
        $sslVersion = strtolower(trim($curlVersion["ssl_version"]));
        if (strpos($sslVersion, "openssl/") === 0) {
            $sslVersion = substr($sslVersion, 8);
            return 0 <= self::compareOpenSslVersion($sslVersion, self::OPENSSL_MIN_TLS_VERSION) ? true : false;
        }
        return true;
    }

    public static function getInfo()
    {
        $curlData = curl_version();
        list($sslLibraryFamily, $sslLibraryVersion) = explode("/", $curlData["ssl_version"], 2);
        $data = ["version" => $curlData["version"], "sslLibraryFamily" => $sslLibraryFamily, "sslLibraryVersion" => $sslLibraryVersion];
        return $data;
    }
}
