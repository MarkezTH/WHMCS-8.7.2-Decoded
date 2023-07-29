<?php

namespace WHMCS\Updater\Version;

class Version800rc3 extends IncrementalVersion
{
    protected $updateActions = ["updateOXEmailTemplate", "removeUnusedLegacyModules"];

    public function getUnusedLegacyModules()
    {
        return ["servers" => ["mediacp"]];
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }

    protected function updateOXEmailTemplate()
    {
        $oxMails = \WHMCS\Mail\Template::master()->where("name", "Open-Xchange Welcome Email")->get();
        foreach ($oxMails as $oxMail) {
            $oxMail->message = str_replace("migration_tool_link", "migration_tool_url", $oxMail->message);
            $oxMail->save();
        }
        return $this;
    }
}
