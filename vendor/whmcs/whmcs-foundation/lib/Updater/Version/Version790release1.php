<?php

namespace WHMCS\Updater\Version;

class Version790release1 extends IncrementalVersion
{
    protected $updateActions = ["pruneOrphanedSslOrders"];

    public function pruneOrphanedSslOrders()
    {
        $orphanedSslOrderIds = \WHMCS\Database\Capsule::table("tblsslorders")->leftJoin("tblhostingaddons", "tblsslorders.addon_id", "=", "tblhostingaddons.id")->whereNull("tblhostingaddons.id")->where("tblsslorders.addon_id", "!=", 0)->pluck("tblsslorders.id")->all();
        \WHMCS\Database\Capsule::table("tblsslorders")->whereIn("id", $orphanedSslOrderIds)->delete();
        return $this;
    }
}
