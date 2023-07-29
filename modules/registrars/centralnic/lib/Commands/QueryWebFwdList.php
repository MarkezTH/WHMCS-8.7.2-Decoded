<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class QueryWebFwdList extends AbstractCommand
{
    protected $command = "QueryWebFwdList";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain, $hostName)
    {
        $this->setParam("dnszone", $domain)->setParam("source", $hostName == "@" ? $domain : $hostName . "." . $domain)->setParam("wide", 1);
        parent::__construct($api);
    }
}
