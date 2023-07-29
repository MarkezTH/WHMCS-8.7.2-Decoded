<?php

namespace WHMCS\Model\Contracts;

interface SchemaVersionAware
{
    public static function latestSchemaVersion();

    public static function activeSchemaVersion();

    public static function isSchemaVersion($version);

    public static function isAtLeastSchemaVersion($version);

    public static function useSchemaVersion($version);

    public static function useOriginSchema();

    public static function resetSchemaVersion();
}
