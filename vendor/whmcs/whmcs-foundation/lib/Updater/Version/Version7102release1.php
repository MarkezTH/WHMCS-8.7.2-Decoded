<?php

namespace WHMCS\Updater\Version;

class Version7102release1 extends IncrementalVersion
{
    protected $updateActions = ["checkForInvalidFileStorageLocation", "ensure2CheckOutDisabled"];

    protected function checkForInvalidFileStorageLocation()
    {
        $settingsToFix = [\WHMCS\File\FileAsset::TYPE_KB_IMAGES, \WHMCS\File\FileAsset::TYPE_EMAIL_IMAGES];
        foreach ($settingsToFix as $setting) {
            $existingSetting = \WHMCS\File\Configuration\FileAssetSetting::forAssetType($setting)->first();
            switch ($setting) {
                case \WHMCS\File\FileAsset::TYPE_EMAIL_IMAGES:
                    $existingConfigs = [\WHMCS\File\FileAsset::TYPE_EMAIL_TEMPLATE_ATTACHMENTS, \WHMCS\File\FileAsset::TYPE_DOWNLOADS, \WHMCS\File\FileAsset::TYPE_TICKET_ATTACHMENTS, \WHMCS\File\FileAsset::TYPE_CLIENT_FILES, \WHMCS\File\FileAsset::TYPE_EMAIL_ATTACHMENTS];
                    break;
                default:
                    $existingConfigs = [\WHMCS\File\FileAsset::TYPE_DOWNLOADS, \WHMCS\File\FileAsset::TYPE_TICKET_ATTACHMENTS, \WHMCS\File\FileAsset::TYPE_CLIENT_FILES, \WHMCS\File\FileAsset::TYPE_EMAIL_ATTACHMENTS, \WHMCS\File\FileAsset::TYPE_EMAIL_TEMPLATE_ATTACHMENTS];
                    if (!$existingSetting) {
                        $existingConfigSetting = NULL;
                        foreach ($existingConfigs as $existingConfig) {
                            $existingConfigSetting = \WHMCS\File\Configuration\FileAssetSetting::where("asset_type", $existingConfig)->first();
                            if ($existingConfigSetting) {
                                $fileAssetSetting = new \WHMCS\File\Configuration\FileAssetSetting();
                                $fileAssetSetting->asset_type = $setting;
                                $fileAssetSetting->storageconfiguration_id = $existingConfigSetting->configuration->id;
                                $fileAssetSetting->migratetoconfiguration_id = NULL;
                                $fileAssetSetting->save();
                            }
                        }
                    } else {
                        $storageConfiguration = \WHMCS\File\Configuration\StorageConfiguration::find($existingSetting->storageconfiguration_id);
                        if (is_null($storageConfiguration)) {
                            $existingConfigSetting = NULL;
                            foreach ($existingConfigs as $existingConfig) {
                                $existingConfigSetting = \WHMCS\File\Configuration\FileAssetSetting::where("asset_type", $existingConfig)->first();
                                if ($existingConfigSetting) {
                                    $existingSetting->storageconfiguration_id = $existingConfigSetting->configuration->id;
                                    $existingSetting->save();
                                }
                            }
                        }
                    }
            }
        }
        return $this;
    }

    protected function ensure2CheckOutDisabled()
    {
        $isGatewayActive = \WHMCS\Database\Capsule::table("tblpaymentgateways")->where("gateway", "=", "tco")->whereNotIn("setting", ["recurringBilling", "integrationMethod"])->first();
        if (!$isGatewayActive) {
            \WHMCS\Database\Capsule::table("tblpaymentgateways")->where("gateway", "=", "tco")->whereIn("setting", ["recurringBilling", "integrationMethod"])->delete();
        }
        return $this;
    }
}
