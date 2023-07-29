<?php

namespace WHMCS\Module\Registrar\Transip;

class Autoload
{
    public static function autoload($className)
    {
        $namespacePath = str_replace("Transip\\Api\\Library", "vendor" . DIRECTORY_SEPARATOR . "Transip", $className);
        $namespacePath = str_replace("\\", DIRECTORY_SEPARATOR, $namespacePath);
        $filePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $namespacePath . ".php";
        if (!file_exists($filePath)) {
            return NULL;
        }
        require_once $filePath;
    }
}
