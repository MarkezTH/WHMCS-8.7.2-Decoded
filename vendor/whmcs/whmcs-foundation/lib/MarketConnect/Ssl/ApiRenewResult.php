<?php

namespace WHMCS\MarketConnect\Ssl;

class ApiRenewResult extends ApiResult
{
    protected $dcvEmail = NULL;

    public function __construct($raw, $dcvEmail = NULL)
    {
        parent::__construct($raw);
        if ($this->hasValidationMethod(\WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL) && parent::hasEmailAuthDetails()) {
            $this->dcvEmail = $this->getDcvValues()["email"];
        } else {
            $this->dcvEmail = $dcvEmail;
        }
    }

    protected function newValidationMethodEmail($ValidationMethodEmailauth)
    {
        $method = new \WHMCS\Service\Ssl\ValidationMethodEmailauth();
        $method->email = $this->dcvEmail;
        return $method;
    }

    protected function hasEmailAuthDetails()
    {
        return 0 < strlen($this->dcvEmail);
    }
}
