<?php

namespace WHMCS\Updater\Version;

class Version770rc1 extends IncrementalVersion
{
    protected $updateActions = ["removeUnusedLegacyModules"];

    private function getUnusedLegacyModules()
    {
        return ["gateways" => ["eeecurrency"], "servers" => ["lxadmin", "veportal", "xpanel"], "registrars" => ["ovh", "resellone", "dotdns"]];
    }

    protected function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }
}
