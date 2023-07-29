<?php

namespace WHMCS\Module\Gateway\GoCardless\Resources;

class AbstractResource
{
    protected $params = [];
    protected $client = NULL;

    public function __construct($gatewayParams)
    {
        $this->params = $gatewayParams;
        $this->client = \WHMCS\Module\Gateway\GoCardless\Client::factory($gatewayParams["accessToken"]);
    }
}
