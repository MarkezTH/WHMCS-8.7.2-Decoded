<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class DeleteWebFwd extends AbstractCommand
{
    protected $command = "DeleteWebFwd";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain, $hostName)
    {
        $this->setParam("source", $hostName == "@" ? $domain : $hostName . "." . $domain);
        parent::__construct($api);
    }
}
