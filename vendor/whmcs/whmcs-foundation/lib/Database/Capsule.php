<?php

namespace WHMCS\Database;

class Capsule extends \Illuminate\Database\Capsule\Manager
{
    public static function getInstance()
    {
        return static::$instance;
    }

    public static function applyCollationIfCompatible($columnName)
    {
        if (is_null($dbCharacterSet)) {
            $db = \DI::make("db");
            $dbCharacterSet = $db->getCharacterSet();
        }
        $columnName = preg_replace("/[^a-z0-9\\_\\.]+/i", "", $columnName);
        if (strlen($columnName) === 0) {
            throw new \WHMCS\Exception("Invalid column name");
        }
        if (strcasecmp($dbCharacterSet, "utf8") === 0) {
            return Capsule::raw("concat(\"\" COLLATE utf8_unicode_ci, " . $columnName . ")");
        }
        return $columnName;
    }
}
