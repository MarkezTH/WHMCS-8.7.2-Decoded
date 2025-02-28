<?php

namespace WHMCS\Updater\Version;

class Version7100rc1 extends IncrementalVersion
{
    protected $updateActions = ["setWeeblyFreeDescription", "addSsoCustomRedirectScope", "createEmailImageTable", "createEmailFileAssetSetting", "removeTldPivotTables"];

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "whmcs" . DIRECTORY_SEPARATOR . "whmcs-foundation" . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "Admin" . DIRECTORY_SEPARATOR . "Support" . DIRECTORY_SEPARATOR . "Knowledgebase";
    }

    protected function setWeeblyFreeDescription()
    {
        \WHMCS\Database\Capsule::table("tblproducts")->where("servertype", "marketconnect")->where("configoption1", "weebly_free")->update(["description" => "Build a website and get online in minutes with the Weebly Free Plan. With no limits on pages + contact forms and basic SEO, it has everything you need to get started."]);
        $configurations = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", "weebly_free")->get()->all();
        foreach ($configurations as $configuration) {
            if ($configuration->productAddon && $configuration->productAddon->module == "marketconnect") {
                \WHMCS\Database\Capsule::table("tbladdons")->where("id", $configuration->entityId)->update(["description" => "Build a website and get online in minutes with the Weebly Free Plan. With no limits on pages + contact forms and basic SEO, it has everything you need to get started."]);
            }
        }
        return $this;
    }

    protected function addSsoCustomRedirectScope()
    {
        $newScopeDetails = ["scope" => "sso:custom_redirect", "description" => "Scope required for arbitrary path redirect on token creation", "isDefault" => 0];
        $storedScope = \WHMCS\ApplicationLink\Scope::where("scope", $newScopeDetails["scope"])->first();
        if (!$storedScope) {
            $newScope = new \WHMCS\ApplicationLink\Scope();
            foreach ($newScopeDetails as $attribute => $value) {
                $newScope->{$attribute} = $value;
            }
            $newScope->save();
        }
        return $this;
    }

    protected function createEmailImageTable()
    {
        (new \WHMCS\Mail\Image())->createTable();
        return $this;
    }

    protected function createEmailFileAssetSetting()
    {
        $existingSetting = \WHMCS\File\Configuration\FileAssetSetting::where("asset_type", \WHMCS\File\FileAsset::TYPE_EMAIL_IMAGES)->first();
        if (!$existingSetting) {
            $existingConfigs = [\WHMCS\File\FileAsset::TYPE_EMAIL_TEMPLATE_ATTACHMENTS, \WHMCS\File\FileAsset::TYPE_DOWNLOADS, \WHMCS\File\FileAsset::TYPE_TICKET_ATTACHMENTS, \WHMCS\File\FileAsset::TYPE_CLIENT_FILES, \WHMCS\File\FileAsset::TYPE_EMAIL_ATTACHMENTS];
            $existingConfigSetting = NULL;
            foreach ($existingConfigs as $existingConfig) {
                $existingConfigSetting = \WHMCS\File\Configuration\FileAssetSetting::where("asset_type", $existingConfig)->first();
                if ($existingConfigSetting) {
                    $setting = new \WHMCS\File\Configuration\FileAssetSetting();
                    $setting->asset_type = \WHMCS\File\FileAsset::TYPE_EMAIL_IMAGES;
                    $setting->storageconfiguration_id = $existingConfigSetting->configuration->id;
                    $setting->migratetoconfiguration_id = NULL;
                    $setting->save();
                }
            }
        }
        return $this;
    }

    public function removeTldPivotTables()
    {
        \WHMCS\Database\Capsule::schema()->dropIfExists("tbltlds");
        \WHMCS\Database\Capsule::schema()->dropIfExists("tbltld_categories");
        \WHMCS\Database\Capsule::schema()->dropIfExists("tbltld_category_pivot");
        return $this;
    }
}
