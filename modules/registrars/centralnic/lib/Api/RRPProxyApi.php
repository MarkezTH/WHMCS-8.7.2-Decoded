<?php

namespace WHMCS\Module\Registrar\CentralNic\Api;

class RRPProxyApi extends AbstractApi
{
    protected $customHeader = "";

    public function getCustomHeader()
    {
        return $this->customHeader;
    }

    public function setCustomHeader($header)
    {
        $this->customHeader = $header;
        return $this;
    }
}
