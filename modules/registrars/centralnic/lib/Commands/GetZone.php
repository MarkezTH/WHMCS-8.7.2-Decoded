<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class GetZone extends AbstractCommand
{
    protected $command = "GetZone";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("domain", $domain);
        parent::__construct($api);
    }
}
