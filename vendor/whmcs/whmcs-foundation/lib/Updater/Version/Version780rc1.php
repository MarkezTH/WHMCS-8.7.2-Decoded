<?php

namespace WHMCS\Updater\Version;

class Version780rc1 extends IncrementalVersion
{
    protected $updateActions = ["registerServerUsageCountCronTask", "registerServerRemoteMetaDataCronTask", "correctDomainExpirySyncFrequencyNaming", "updateDomainExpirySyncCronTaskNaming", "registerCronTasks", "updateWeeblyWelcomeEmailContent"];

    protected function registerServerUsageCountCronTask()
    {
        \WHMCS\Cron\Task\ServerUsageCount::register();
        return $this;
    }

    protected function registerServerRemoteMetaDataCronTask()
    {
        \WHMCS\Cron\Task\ServerRemoteMetaData::register();
        return $this;
    }

    protected function correctDomainExpirySyncFrequencyNaming()
    {
        $query = \WHMCS\Database\Capsule::table("tblconfiguration")->where("setting", "DomainExpirySyncFrequency");
        if ($query->count()) {
            $query->update(["setting" => "DomainStatusSyncFrequency"]);
        }
        return $this;
    }

    protected function updateDomainExpirySyncCronTaskNaming()
    {
        $query = \WHMCS\Database\Capsule::table("tbltask")->where("class_name", "WHMCS\\Cron\\Task\\DomainExpirySync");
        if ($query->count()) {
            $query->update(["class_name" => "WHMCS\\Cron\\Task\\DomainStatusSync", "name" => "Domain Status Syncronisation", "description" => "Domain Status Syncing"]);
        }
        return $this;
    }

    protected function registerCronTasks()
    {
        \WHMCS\Cron\Task\DomainStatusSync::register();
        \WHMCS\Cron\Task\DomainTransferSync::register();
        return $this;
    }

    protected function updateWeeblyWelcomeEmailContent()
    {
        $emails = \WHMCS\Mail\Template::where("name", "Weebly Welcome Email")->get();
        $oldLink = "https://hc.weebly.com/hc/en-us/categories/203453908-Getting-Started";
        $newLink = "https://www.weebly.com/app/help/us/en/topics/first-steps";
        foreach ($emails as $email) {
            $email->message = str_replace($oldLink, $newLink, $email->message);
            $email->save();
        }
        return $this;
    }
}
