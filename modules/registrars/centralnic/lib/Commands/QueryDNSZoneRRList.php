<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class QueryDNSZoneRRList extends AbstractCommand
{
    protected $command = "QueryDNSZoneRRList";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("dnszone", $domain)->setParam("orderby", "type")->setParam("wide", 1);
        parent::__construct($api);
    }
}
