<?php

namespace WHMCS\Module\Fraud\MaxMind;

class Request extends \WHMCS\Module\Fraud\AbstractRequest implements \WHMCS\Module\Fraud\RequestInterface
{
    protected $accountId = NULL;
    protected $licenseKey = NULL;
    protected $serviceType = NULL;
    const URL = "https://minfraud.maxmind.com/minfraud/v2.0/";

    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
        return $this;
    }

    public function setLicenseKey($licenseKey)
    {
        $this->licenseKey = $licenseKey;
        return $this;
    }

    public function setServiceType($serviceType)
    {
        $serviceType = strtolower($serviceType);
        if (!in_array($serviceType, ["score", "insights", "factors"])) {
            throw new \Exception("Invalid service type: " . $serviceType);
        }
        $this->serviceType = $serviceType;
        return $this;
    }

    public function call($data)
    {
        $client = $this->getClient();
        $response = $client->post($this->getApiEndpointUrl(), ["auth" => [$this->accountId, $this->licenseKey], \GuzzleHttp\RequestOptions::HTTP_ERRORS => false, "json" => $data]);
        $maxmindResponse = new Response($response->getBody(), $response->getStatusCode());
        $this->log("check", $data, $response, $maxmindResponse->toArray());
        if ($maxmindResponse->isEmpty()) {
            throw new \WHMCS\Exception\Http\ConnectionError($response->getBody());
        }
        return $maxmindResponse;
    }

    protected function getApiEndpointUrl()
    {
        return self::URL . $this->serviceType;
    }
}
