<?php

namespace WHMCS\Environment;

class OperatingSystem
{
    public static function isWindows($phpOs = PHP_OS)
    {
        return in_array($phpOs, ["Windows", "WIN32", "WINNT"]);
    }

    public function isOwnedByMe($path)
    {
        return fileowner($path) == Php::getUserRunningPhp();
    }

    public function isServerCloudLinux()
    {
        return strpos(php_uname("r"), "lve") !== false;
    }
}
