<?php

namespace WHMCS\Module\Fraud;

interface RequestInterface
{
    public function setLicenseKey($licenseKey);

    public function call($data);
}
