<?php

namespace WHMCS\Backups;

class Backups
{
    public function getActiveProviders()
    {
        $activeBackupSystems = \WHMCS\Config\Setting::getValue("ActiveBackupSystems");
        $activeBackupSystems = explode(",", $activeBackupSystems);
        $activeBackupSystems = array_filter($activeBackupSystems);
        return $activeBackupSystems;
    }
}
