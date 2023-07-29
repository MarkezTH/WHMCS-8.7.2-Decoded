<?php

namespace WHMCS\Updater\Version;

class Version700release1 extends IncrementalVersion
{
    protected $updateActions = ["mutateDailyCronConfigurations"];

    public function mutateDailyCronConfigurations()
    {
        $transientData = \WHMCS\TransientData::getInstance();
        $lastCronInvocationTime = $transientData->retrieve("lastCronInvocationTime");
        if (!$lastCronInvocationTime) {
            $runEntry = \WHMCS\Database\Capsule::table("tblactivitylog")->where("description", "like", "%Cron Job: Starting%")->orderBy("id", "desc")->first();
            if ($runEntry) {
                $lastRun = new \WHMCS\Carbon($runEntry->date);
                \WHMCS\Cron\Status::setDailyCronExecutionHour($lastRun->format("H"));
                (new \WHMCS\Cron\Status())->setLastDailyCronInvocationTime($lastRun);
            }
            return $this;
        }
        $lastRun = new \WHMCS\Carbon($lastCronInvocationTime);
        Cron::setDailyCronExecutionHour($lastRun->format("H"));
        $cron->setLastDailyCronInvocationTime($lastRun);
        return $this;
    }
}
