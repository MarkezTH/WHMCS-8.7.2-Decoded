<?php

namespace WHMCS\Updater\Version;

class Version840release1 extends IncrementalVersion
{
    protected $updateActions = ["ensureProtxDatabaseTableExistsIfEnabled"];

    protected function ensureProtxDatabaseTableExistsIfEnabled()
    {
        $protxActive = \WHMCS\Database\Capsule::table("tblpaymentgateways")->where("gateway", "protx")->where("setting", "name")->count();
        if ($protxActive) {
            (new \WHMCS\Module\Gateway\Protx\Protx())->createTable();
        }
        return $this;
    }
}
