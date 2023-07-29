<?php

namespace WHMCS\Updater\Version;

class Version830rc1 extends IncrementalVersion
{
    protected $updateActions = ["enableDefaultsForMailImportSettings"];

    public function enableDefaultsForMailImportSettings()
    {
        \WHMCS\Config\Setting::setValue("SupportReopenTicketOnFailedImport", "1");
        return $this;
    }
}
