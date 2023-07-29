<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class CheckDNSZone extends AbstractCommand
{
    protected $command = "CheckDNSZone";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("dnszone", $domain);
        parent::__construct($api);
    }

    public function handleResponse($Response, $response)
    {
        if (200 <= $response->getCode() && $response->getCode() <= 300) {
            return $response;
        }
        throw new \Exception($response->getDescription(), $response->getCode());
    }
}
