<?php

namespace WHMCS\Cron\Task;

class CheckForWhmcsUpdate extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 2000;
    protected $defaultFrequency = 480;
    protected $defaultDescription = "Check for WHMCS Software Updates";
    protected $defaultName = "WHMCS Updates";
    protected $systemName = "CheckForWhmcsUpdate";
    protected $outputs = ["update.checked" => ["defaultValue" => 0, "identifier" => "update.checked", "name" => "Update Check Performed"], "update.available" => ["defaultValue" => 0, "identifier" => "update.available", "name" => "Update Available"], "update.version" => ["defaultValue" => 0, "identifier" => "update.version", "name" => "Update Version"]];
    protected $icon = "fas fa-download";
    protected $isBooleanStatus = true;
    protected $successCountIdentifier = "update.checked";

    public function __invoke()
    {
        $updateAvailable = 0;
        $updateVersion = "";
        try {
            $updater = new \WHMCS\Installer\Update\Updater();
            $response = $updater->fetchComposerLatestVersion();
            if ($response["canUpdate"]) {
                $updateAvailable = 1;
                $updateVersion = $response["latestVersion"]["number"] . " " . $response["latestVersion"]["label"];
            }
            $this->output("update.checked")->write(1);
        } catch (\Exception $e) {
            $this->output("update.checked")->write(0);
            logActivity("Check for Updates Failed: " . $e->getMessage());
        }
        $this->output("update.available")->write($updateAvailable);
        if ($updateAvailable) {
            $this->output("update.version")->write(trim($updateVersion));
        }
        return $this;
    }
}
