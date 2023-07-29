<?php

namespace WHMCS\Updater\Version;

class Version630rc1 extends IncrementalVersion
{
    protected $updateActions = ["insertUpgradeTimeForMDE"];

    public function insertUpgradeTimeForMDE()
    {
        \WHMCS\Config\Setting::setValue("MDEFromTime", \WHMCS\Carbon::now());
        return $this;
    }
}
