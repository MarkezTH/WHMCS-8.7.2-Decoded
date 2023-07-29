<?php

namespace WHMCS\Updater\Version;

class Version771release1 extends IncrementalVersion
{
    protected $updateActions = ["addIndexToSslSyncTable"];

    public function addIndexToSslSyncTable()
    {
        $domainNameIndex = \WHMCS\Database\Capsule::connection()->select("SHOW INDEX FROM `tblsslstatus` WHERE Column_name=\"domain_name\"");
        if (empty($domainNameIndex)) {
            \WHMCS\Database\Capsule::schema()->table((new \WHMCS\Domain\Ssl\Status())->getTable(), function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->index("domain_name", "domain_name");
            });
        }
        return $this;
    }
}
