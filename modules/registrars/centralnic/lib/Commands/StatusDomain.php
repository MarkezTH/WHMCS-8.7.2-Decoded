<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class StatusDomain extends AbstractCommand
{
    protected $command = "StatusDomain";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("domain", $domain);
        parent::__construct($api);
    }
}
