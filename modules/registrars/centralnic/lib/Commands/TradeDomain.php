<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class TradeDomain extends AbstractCommand
{
    protected $command = "TradeDomain";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain, $newOwnerContact)
    {
        $this->setParam("domain", $domain)->setParam("ownercontact0", $newOwnerContact);
        parent::__construct($api);
    }
}
