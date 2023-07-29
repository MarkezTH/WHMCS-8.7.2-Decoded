<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class StatusContact extends AbstractCommand
{
    protected $command = "StatusContact";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $contactHandle)
    {
        $this->setParam("contact", $contactHandle);
        parent::__construct($api);
    }
}
