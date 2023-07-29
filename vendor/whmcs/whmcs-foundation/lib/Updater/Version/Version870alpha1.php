<?php

namespace WHMCS\Updater\Version;

class Version870alpha1 extends IncrementalVersion
{
    protected $updateActions = ["removeUnusedLegacyModules", "forceDeprecateRrpProxyModule"];
    protected $numberOfDomains = 25;

    public function forceDeprecateRrpProxyModule()
    {
        $oldModule = "rrpproxy";
        $newModule = "centralnic";
        $registrarObj = new \WHMCS\Module\Registrar();
        if ($registrarObj->isActive($oldModule)) {
            $rrpProxySettings = \WHMCS\Database\Capsule::table("tblregistrars")->select("setting", "value")->where("registrar", $oldModule)->pluck("value", "setting")->toArray();
            $expectedSettings = ["Username", "Password", "TestMode", "ProxyServer", "DNSSEC"];
            foreach ($expectedSettings as $expectedSetting) {
                $rrpProxySettings[$expectedSetting] = isset($rrpProxySettings[$expectedSetting]) ? decrypt($rrpProxySettings[$expectedSetting]) : "";
            }
            foreach ($rrpProxySettings as $settingName => $settingValue) {
                $registrarSetting = new \WHMCS\Module\RegistrarSetting();
                $registrarSetting->registrar = $newModule;
                $registrarSetting->setting = $settingName;
                $registrarSetting->value = $settingValue;
                $registrarSetting->save();
            }
            $clientDomains = \WHMCS\Domain\Domain::query()->where("registrar", $oldModule);
            $updatedDomains = $clientDomains->pluck("domain");
            $clientDomains->update(["registrar" => $newModule]);
            if (0 < $updatedDomains->count()) {
                $updatedDomains->chunk($this->numberOfDomains)->each(function ($domains) {
                    logActivity("The system updated the following client domains using the \"RRPProxy\" module to the \"CentralNic Reseller\" module: " . $domains->join(", "));
                });
            }
            $domainExtensions = \WHMCS\Domains\Extension::query()->where("autoreg", $oldModule);
            $updatedExtensionNames = $domainExtensions->pluck("extension");
            $domainExtensions->update(["autoreg" => $newModule]);
            if (0 < $updatedExtensionNames->count()) {
                logActivity("The system updated the following TLDs using the \"RRPProxy\" module to the \"CentralNic Reseller\" module: " . $updatedExtensionNames->join(", "));
            }
            \WHMCS\Database\Capsule::table("tblregistrars")->select("setting", "value")->where("registrar", $oldModule)->delete();
        }
        if (in_array($oldModule, $registrarObj->getList())) {
            (new \WHMCS\Module\LegacyModuleCleanup())->removeModule("rrpproxy", \WHMCS\Module\AbstractModule::TYPE_REGISTRAR);
        }
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused(["gateways" => ["payson"]]);
        return $this;
    }
}
