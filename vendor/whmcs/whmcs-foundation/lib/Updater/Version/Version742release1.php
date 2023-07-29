<?php

namespace WHMCS\Updater\Version;

class Version742release1 extends IncrementalVersion
{
    protected $updateActions = ["removeComposerInstallUpdateHooks"];

    protected function removeComposerInstallUpdateHooks()
    {
        $directoryToClean = ROOTDIR . str_replace("/", DIRECTORY_SEPARATOR, "/vendor/whmcs/whmcs-foundation/lib/Installer/Composer/Hooks");
        if (is_dir($directoryToClean)) {
            \WHMCS\Utility\File::recursiveDelete($directoryToClean);
        }
    }
}
