<?php

namespace WHMCS\MarketConnect\Ssl;

abstract class ApiResult implements \ArrayAccess
{
    protected $raw = NULL;
    protected $data = NULL;

    public function __construct($raw)
    {
        $this->raw = $raw;
        $this->data = $raw["data"] ?? [];
    }

    public function getDomainValidationMethods()
    {
        $methods = [];
        foreach ($this->domainValidationMethods() as $methodConstant) {
            switch ($methodConstant) {
                case \WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE:
                    $this->assertFileAuthDetails();
                    $methods[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE] = $this->newValidationMethodFile();
                    break;
                case \WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL:
                    $this->assertEmailAuthDetails();
                    $methods[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL] = $this->newValidationMethodEmail();
                    break;
                case \WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS:
                    $this->assertDnsAuthDetails();
                    $methods[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS] = $this->newValidationMethodDns();
                    break;
            }
        }
        return $methods;
    }

    public function domainValidationMethods()
    {
        $active = [];
        if ($this->hasFileAuth()) {
            $active[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE] = \WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE;
        }
        if ($this->hasEmailAuth()) {
            $active[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL] = \WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL;
        }
        if ($this->hasDnsAuth()) {
            $active[\WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS] = \WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS;
        }
        return $active;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->raw);
    }

    public function offsetGet($offset)
    {
        return $this->raw[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->raw[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->raw[$offset]);
    }

    public function successful()
    {
        return !array_key_exists("error", $this->raw);
    }

    public function getError()
    {
        return $this["error"] ?? "";
    }

    public function orderNumber()
    {
        return $this["order_number"] ?? "";
    }

    public function trackingId()
    {
        return $this["trackingId"] ?? "";
    }

    public function hasValidationMethod($method)
    {
        return isset($this->domainValidationMethods()[$method]);
    }

    protected function getDcvMethod()
    {
        return $this->data["dcv_method"] ?? "";
    }

    protected function getDcvValues()
    {
        return $this->data["dcv_method_values"] ?? [];
    }

    protected function getDcvMethodValue($key)
    {
        return $this->getDcvValues()[$key] ?? NULL;
    }

    protected function is83Response()
    {
        return isset($this->data["dcv_method"]);
    }

    protected function hasEmailAuth()
    {
        if (!$this->is83Response()) {
            return $this->hasEmailAuthPre83();
        }
        return $this->getDcvMethod() == Configuration::MARKETCONNECT_DCV_EMAIL;
    }

    protected function hasEmailAuthPre83()
    {
        return !$this->hasFileAuthPre83() && $this->hasEmailAuthDetails();
    }

    protected function newValidationMethodEmail($ValidationMethodEmailauth)
    {
        $method = new \WHMCS\Service\Ssl\ValidationMethodEmailauth();
        $method->email = $this->getDcvValues()["email"];
        return $method;
    }

    protected function assertEmailAuthDetails()
    {
        if (!$this->hasEmailAuthDetails()) {
            throw new \WHMCS\Exception("Expecting email based validation, but no configuration found");
        }
    }

    protected function hasEmailAuthDetails()
    {
        $values = $this->getDcvValues();
        return isset($values["email"]);
    }

    protected function hasFileAuth()
    {
        if (!$this->is83Response()) {
            return $this->hasFileAuthPre83();
        }
        return $this->getDcvMethod() == Configuration::MARKETCONNECT_DCV_FILE;
    }

    protected function hasFileAuthPre83()
    {
        return isset($this->data["fileAuth"]) && $this->data["fileAuth"];
    }

    protected function hasFileAuthDetails()
    {
        if (!$this->is83Response()) {
            return $this->hasFileAuthDetailsPre83();
        }
        $values = $this->getDcvValues();
        return isset($values["path"]) && isset($values["filename"]) && isset($values["contents"]);
    }

    protected function hasFileAuthDetailsPre83()
    {
        return isset($this->data["fileAuthPath"]) && isset($this->data["fileAuthFilename"]) && isset($this->data["fileAuthContents"]);
    }

    protected function newValidationMethodFile()
    {
        if (!$this->is83Response()) {
            return $this->newValidationMethodFilePre83();
        }
        $values = $this->getDcvValues();
        $method = new \WHMCS\Service\Ssl\ValidationMethodFileauth();
        $method->name = $values["filename"] ?? NULL;
        $method->path = $values["path"] ?? NULL;
        $method->contents = $values["contents"] ?? NULL;
        return $method;
    }

    protected function newValidationMethodFilePre83()
    {
        $method = new \WHMCS\Service\Ssl\ValidationMethodFileauth();
        $method->name = $this->data["fileAuthFilename"] ?? NULL;
        $method->path = $this->data["fileAuthPath"] ?? NULL;
        $method->contents = $this->data["fileAuthContents"] ?? NULL;
        return $method;
    }

    protected function assertFileAuthDetails()
    {
        if (!$this->hasFileAuthDetails()) {
            throw new \WHMCS\Exception("Expecting file based validation, but no configuration found");
        }
    }

    protected function hasDnsAuth()
    {
        return $this->getDcvMethod() == Configuration::MARKETCONNECT_DCV_DNS;
    }

    protected function newValidationMethodDns()
    {
        $values = $this->getDcvValues();
        $method = new \WHMCS\Service\Ssl\ValidationMethodDnsauth();
        $method->host = $values["host"] ?? NULL;
        $method->value = $values["contents"] ?? NULL;
        return $method;
    }

    protected function hasDnsAuthDetails()
    {
        return !is_null($this->getDcvMethodValue("contents"));
    }

    protected function assertDnsAuthDetails()
    {
        if (!$this->hasDnsAuthDetails()) {
            throw new \WHMCS\Exception("Expecting DNS based validation, but no configuration found");
        }
    }

    public function getCertificate()
    {
        return $this->data["certificate"]["certificate"] ?? NULL;
    }

    public function hasCertificate()
    {
        return !is_null($this->getCertificate());
    }

    public function getOrderExpiry()
    {
        return $this->data["certificate"]["order_expiry"] ?? NULL;
    }

    public function getCertificateExpiry()
    {
        return $this->data["certificate"]["certificate_expiry"] ?? NULL;
    }
}
