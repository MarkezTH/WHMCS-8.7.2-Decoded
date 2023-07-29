<?php

namespace WHMCS\Module\Registrar\GoDaddy;

class Client
{
    protected $apiKey = "";
    protected $apiSecret = "";
    protected $sandboxMode = false;
    protected $apiVersion = 1;
    const URLS = ["sandbox" => "https://api.ote-godaddy.com/v", "live" => "https://api.godaddy.com/v"];
    const DATE_FORMAT = "Y-m-d\\TH:i:s.u\\Z";

    public function __construct($apiKey, $apiSecret, $sandbox = false)
    {
        $this->setSandboxMode($sandbox);
        $this->setApiKey($apiKey);
        $this->setApiSecret($apiSecret);
    }

    public static function factory($apiKey, $apiSecret, $sandbox = false)
    {
        $client = new static($apiKey, $apiSecret, $sandbox);
        return new Api\Client($client->getDefaultOptions());
    }

    protected function getApiKey()
    {
        return $this->apiKey;
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function getApiSecret()
    {
        return $this->apiSecret;
    }

    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    public function isSandboxMode()
    {
        return $this->sandboxMode;
    }

    public function setSandboxMode($sandboxMode)
    {
        $this->sandboxMode = $sandboxMode;
    }

    protected function getUrl()
    {
        $type = "live";
        if ($this->isSandboxMode()) {
            $type = "sandbox";
        }
        return self::URLS[$type];
    }

    protected function getAuth()
    {
        return $this->getApiKey() . ":" . $this->getApiSecret();
    }

    protected function getDefaultOptions()
    {
        return ["base_uri" => $this->getUrl() . $this->apiVersion . "/", "headers" => ["Accept" => "application/json", "Content-Type" => "application/json", "Authorization" => "sso-key " . $this->getAuth()], \GuzzleHttp\RequestOptions::HTTP_ERRORS => false];
    }

    public function setApiVersion($version = 1)
    {
        $this->apiVersion = $version;
        return $this;
    }
}
