<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class PushDomain extends AbstractCommand
{
    protected $command = "PushDomain";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("domain", $domain);
        parent::__construct($api);
    }
}
