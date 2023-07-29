<?php

namespace WHMCS\Updater\Version;

class Version770release1 extends IncrementalVersion
{
    protected $updateActions = ["registerSslStatusSyncCronTask"];

    public function registerSslStatusSyncCronTask()
    {
        \WHMCS\Cron\Task\SslStatusSync::register();
        return $this;
    }
}
