<?php

namespace WHMCS\Updater\Version;

class Version860release1 extends IncrementalVersion
{
    protected $updateActions = ["removeUnusedLegacyModules"];

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $this->filesToRemove[] = ROOTDIR . "/assets/js/tiny_mce";
        $this->filesToRemove[] = ROOTDIR . "/admin/whmimport.php";
        $this->filesToRemove[] = ROOTDIR . "/resources/views/admin/client/products";
        $this->filesToRemove[] = ROOTDIR . "/modules/social";
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused(["gateways" => ["paypalexpress"]]);
        return $this;
    }
}
