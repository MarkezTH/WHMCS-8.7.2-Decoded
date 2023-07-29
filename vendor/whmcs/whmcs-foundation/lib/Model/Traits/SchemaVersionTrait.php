<?php

namespace WHMCS\Model\Traits;

trait SchemaVersionTrait
{
    protected static $schemaVersion = NULL;

    public static function originSchemaVersion()
    {
        return 0;
    }

    public static function activeSchemaVersion()
    {
        if (is_null(static::$schemaVersion)) {
            return static::latestSchemaVersion();
        }
        return static::$schemaVersion;
    }

    public static function useSchemaVersion($version)
    {
        $previous = static::activeSchemaVersion();
        static::$schemaVersion = $version;
        return $previous;
    }

    public static function isSchemaVersion($version)
    {
        return static::activeSchemaVersion() == $version;
    }

    public static function isAtLeastSchemaVersion($version)
    {
        return $version <= static::activeSchemaVersion();
    }

    public static function useOriginSchema()
    {
        $previous = static::activeSchemaVersion();
        static::$schemaVersion = static::originSchemaVersion();
        return $previous;
    }

    public static function resetSchemaVersion()
    {
        return static::useSchemaVersion(static::latestSchemaVersion());
    }
}
