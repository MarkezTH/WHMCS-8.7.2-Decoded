<?php

namespace WHMCS\File\Provider;

interface StorageProviderInterface
{
    public static function getShortName();

    public static function getName();

    public function getConfigSummaryText();

    public function getConfigSummaryHtml();

    public function getIcon();

    public function applyConfiguration($configSettings);

    public function testConfiguration();

    public function exportConfiguration(\WHMCS\File\Configuration\StorageConfiguration $config);

    public function getConfigurationFields();

    public function getAccessCredentialFieldNames();

    public function getFieldsLockedInUse();

    public function isLocal();

    public function createFilesystemAdapterForAssetType($assetType, $subPath);

    public static function getExceptionErrorMessage(\Exception $e);
}
