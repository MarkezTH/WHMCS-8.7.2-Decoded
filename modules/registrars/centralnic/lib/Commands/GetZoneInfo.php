<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class GetZoneInfo extends AbstractCommand
{
    protected $command = "GetZoneInfo";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $tld)
    {
        $this->setParam("zone", $tld);
        parent::__construct($api);
    }
}
