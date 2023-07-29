<?php

namespace WHMCS\Updater\Version;

class Version870rc1 extends IncrementalVersion
{
    protected $updateActions = ["updateGocardlessTypeSetting"];

    public function updateGocardlessTypeSetting()
    {
        \WHMCS\Database\Capsule::table("tblpaymentgateways")->where("gateway", "gocardless")->where("setting", "type")->update(["value" => \WHMCS\Module\Gateway::GATEWAY_BANK]);
        return $this;
    }
}
