<?php

namespace WHMCS\Updater\Version;

class Version860alpha1 extends IncrementalVersion
{
    protected $updateActions = ["removeUnusedLegacyModules"];

    public function getUnusedLegacyModules()
    {
        return ["gateways" => ["chronopay", "eonlinedata"]];
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }
}
