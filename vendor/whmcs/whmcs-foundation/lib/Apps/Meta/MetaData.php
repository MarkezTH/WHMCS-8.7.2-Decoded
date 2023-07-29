<?php

namespace WHMCS\Apps\Meta;

class MetaData
{
    protected $localMetaData = NULL;
    protected $remoteMetaData = NULL;

    public static function factoryFromModule($moduleInterface, $moduleName)
    {
        $metaData = new self();
        try {
            $metaData->localMetaData = Sources\LocalFile::build($moduleInterface->getAppMetaDataFilePath($moduleName));
        } catch (\Exception $e) {
        }
        try {
            $metaData->remoteMetaData = (new Sources\RemoteFeed())->getAppByModuleName($moduleInterface->getType(), $moduleName);
        } catch (\Exception $e) {
        }
        return $metaData;
    }

    public static function factoryFromRemoteFeed($feed)
    {
        $metaData = new self();
        $metaData->remoteMetaData = (new Sources\RemoteFeed())->parseJson($feed);
        return $metaData;
    }

    protected function get($method)
    {
        $result = $this->getRemote($method);
        if (is_null($result)) {
            $result = $this->getLocal($method);
        }
        return $result;
    }

    public function hasRemote()
    {
        return $this->remoteMetaData instanceof Schema\AbstractVersion;
    }

    protected function getRemote($method)
    {
        $value = NULL;
        if ($this->hasRemote()) {
            $value = $this->fromSchemaVersion($this->remoteMetaData, $method);
        }
        return $value;
    }

    public function hasLocal()
    {
        return $this->localMetaData instanceof Schema\AbstractVersion;
    }

    protected function getLocal($method)
    {
        $value = NULL;
        if ($this->hasLocal()) {
            $value = $this->fromSchemaVersion($this->localMetaData, $method);
        }
        return $value;
    }

    protected function fromSchemaVersion(Schema\AbstractVersion $metaData, $method)
    {
        $value = NULL;
        if (method_exists($metaData, $method)) {
            $value = $metaData->{$method}();
        }
        return $value;
    }

    public function getType()
    {
        return $this->get("getType");
    }

    public function getName()
    {
        return $this->get("getName");
    }

    public function getVersion()
    {
        return $this->get("getVersion");
    }

    public function getLicense()
    {
        return $this->get("getLicense");
    }

    public function getCategory()
    {
        return $this->get("getCategory");
    }

    public function getDisplayName()
    {
        return $this->get("getDisplayName");
    }

    public function getTagline()
    {
        return $this->get("getTagline");
    }

    public function getShortDescription()
    {
        return $this->get("getShortDescription");
    }

    public function getLongDescription()
    {
        return $this->get("getLongDescription");
    }

    public function getFeatures()
    {
        return $this->get("getFeatures") ?? [];
    }

    public function getLogoFilename()
    {
        return $this->get("getLogoFilename");
    }

    public function getLogoBase64()
    {
        return $this->get("getLogoBase64");
    }

    public function getLogoAssetFilename()
    {
        return $this->getRemote("getLogoAssetFilename");
    }

    public function getLogoRemoteUri()
    {
        return $this->getRemote("getLogoRemoteUri");
    }

    public function getMarketplaceUrl()
    {
        return $this->get("getMarketplaceUrl");
    }

    public function getAuthors()
    {
        return $this->get("getAuthors") ?? [];
    }

    public function getHomepageUrl()
    {
        return $this->get("getHomepageUrl");
    }

    public function getLearnMoreUrl()
    {
        return $this->get("getLearnMoreUrl");
    }

    public function getSupportEmail()
    {
        return $this->get("getSupportEmail");
    }

    public function getSupportUrl()
    {
        return $this->get("getSupportUrl");
    }

    public function getDocumentationUrl()
    {
        return $this->get("getDocumentationUrl");
    }

    public function getPurchaseFreeTrialDays()
    {
        return $this->getRemote("getPurchaseFreeTrialDays");
    }

    public function getPurchasePrice()
    {
        return $this->getRemote("getPurchasePrice");
    }

    public function getPurchaseCurrency()
    {
        return $this->getRemote("getPurchaseCurrency");
    }

    public function getPurchaseTerm()
    {
        return $this->getRemote("getPurchaseTerm");
    }

    public function getPurchaseUrl()
    {
        return $this->getRemote("getPurchaseUrl");
    }

    public function isFeatured()
    {
        return (bool) $this->getRemote("isFeatured");
    }

    public function isPopular()
    {
        return (bool) $this->getRemote("isPopular");
    }

    public function isUpdated()
    {
        return (bool) $this->getRemote("isUpdated");
    }

    public function getKeywords()
    {
        return $this->getRemote("getKeywords") ?? [];
    }

    public function getWeighting()
    {
        return (int) $this->getRemote("getWeighting");
    }

    public function isHidden()
    {
        return (bool) $this->getRemote("isHidden");
    }
}
