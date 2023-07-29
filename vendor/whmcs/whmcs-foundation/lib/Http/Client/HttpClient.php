<?php

namespace WHMCS\Http\Client;

class HttpClient extends \GuzzleHttp\Client
{
    const DEFAULT_TIMEOUT_SEC = 30;
    const DEFAULT_CONNECTION_TEST_TIMEOUT_SEC = 10;

    public function __construct($config = [])
    {
        $config = array_merge(static::getLocalDefaults(), $config);
        parent::__construct($config);
    }

    public static function createConnectionTester($config = [])
    {
        $config = array_merge(static::getConnectionTestDefaults(), $config);
        return new static($config);
    }

    protected static function getLocalDefaults()
    {
        return [\GuzzleHttp\RequestOptions::TIMEOUT => static::DEFAULT_TIMEOUT_SEC];
    }

    protected static function getConnectionTestDefaults()
    {
        return [\GuzzleHttp\RequestOptions::TIMEOUT => static::DEFAULT_CONNECTION_TEST_TIMEOUT_SEC];
    }
}
