<?php

namespace WHMCS\Admin\Setup\Automation;

class CronController
{
    public function cronStatus(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.setup.automation.cronstatus", ["cronCommand" => \WHMCS\Environment\Php::getPreferredCliBinary() . " -q " . \App::getCronDirectory() . "/cron.php", "reportData" => $this->getReport()])]);
    }

    protected function getReport()
    {
        $cronStatus = new \WHMCS\Cron\Status();
        return [["title" => "Cron Setup", "status" => $cronStatus->hasCronEverBeenInvoked(), "description" => "No cron task invocation detected. You must setup a scheduled task (cron) via your website control panel or server crontab to invoke the WHMCS cron file periodically.", "docs" => "http://go.whmcs.com/1511/cron-setup"], ["title" => "Cron Invocation Frequency", "status" => $cronStatus->hasCronBeenInvokedInLastHour(), "description" => ($cronStatus->getLastCronInvocationTime() ? "The last cron invocation time is " . $cronStatus->getLastCronInvocationTime()->toDateTimeString() . " (" . $cronStatus->getLastCronInvocationTime()->diffForHumans() . ")" : "The cron has never been invoked yet") . ". The WHMCS cron task file should be setup to run at least once per hour. We recommend configuring it to run every 5 minutes wherever possible.", "docs" => "http://go.whmcs.com/1515/cron-frequency"], ["title" => "Daily Cron Run", "status" => \App::isNewInstallation() ? NULL : $cronStatus->hasDailyCronRunInLast24Hours(), "description" => \App::isNewInstallation() ? "This is a new installation. Daily cron status will appear here after 48 hours." : ($cronStatus->getLastDailyCronInvocationTime() ? "The daily cron was last run " . $cronStatus->getLastDailyCronInvocationTime()->toDateTimeString() : "The daily cron has never been run yet") . ". Please ensure the cron is setup and running within your configured daily cron execution hour.", "docs" => "http://go.whmcs.com/1519/daily-system-cron"], ["title" => "Daily Cron Completing", "status" => \App::isNewInstallation() ? NULL : $cronStatus->hasDailyCronCompletedSuccessfullyRecently(), "description" => \App::isNewInstallation() ? "This is a new installation. Daily cron completion status will appear here after 48 hours." : ($cronStatus->getLastDailyCronEndTime() ? "The last recorded successful end time is " . $cronStatus->getLastDailyCronEndTime()->toDateTimeString() : "The daily cron has never completed") . ". Please refer to our troubleshooting guide for details regarding how to identify the problem preventing daily cron completion.", "docs" => "http://go.whmcs.com/1523/daily-system-cron-troubleshooting"]];
    }
}
