<?php

namespace WHMCS\Module\Fraud\FraudLabs;

class Response extends \WHMCS\Module\Fraud\AbstractResponse implements \WHMCS\Module\Fraud\ResponseInterface
{
    protected $failureErrorCodes = [101, 102, 103, 104, 203, 204, 210, 211];

    public function isSuccessful()
    {
        $errorCode = $this->get("fraudlabspro_error_code");
        return $this->httpCode == 200 && (!$errorCode || !in_array($errorCode, $this->failureErrorCodes));
    }
}
