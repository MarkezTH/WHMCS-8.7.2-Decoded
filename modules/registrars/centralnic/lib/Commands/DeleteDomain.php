<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class DeleteDomain extends AbstractCommand
{
    protected $command = "DeleteDomain";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("domain", $domain);
        parent::__construct($api);
    }
}
