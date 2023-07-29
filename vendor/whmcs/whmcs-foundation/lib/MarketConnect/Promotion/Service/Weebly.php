<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class Weebly extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_WEEBLY;
    protected $friendlyName = "Weebly";
    protected $primaryIcon = "assets/img/marketconnect/weebly/logo.png";
    protected $promoteToNewClients = true;
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $upsells = NULL;
    protected $loginPanel = ["label" => "marketConnect.websiteBuilder.buildWebsite", "icon" => "fa-desktop", "image" => "assets/img/marketconnect/weebly/dragdropeditor.png", "color" => "blue", "dropdownReplacementText" => ""];
    protected $settings = [["name" => "include-weebly-free-by-default", "label" => "Include Weebly Free by Default", "description" => "Automatically pre-select Weebly Free by default for new orders of all applicable products", "default" => true]];
    protected $upsellPromoContent = NULL;
    protected $idealFor = NULL;
    protected $siteFeatures = NULL;
    protected $ecommerceFeatures = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $promotionalContent = NULL;
    protected $recommendedUpgradePaths = NULL;
    const WEEBLY_LITE = NULL;
    const WEEBLY_FREE = NULL;
    const WEEBLY_STARTER = NULL;
    const WEEBLY_PRO = NULL;
    const WEEBLY_BUSINESS = NULL;
    const WEEBLY_PAID = NULL;

    public function getIdealFor($key)
    {
        return isset($this->idealFor[$key]) ? $this->idealFor[$key] : "";
    }

    public function getSiteFeatures($key)
    {
        return isset($this->siteFeatures[$key]) ? $this->siteFeatures[$key] : [];
    }

    public function getEcommerceFeatures($key)
    {
        return isset($this->ecommerceFeatures[$key]) ? $this->ecommerceFeatures[$key] : [];
    }

    public function getFeaturesForUpgrade($key)
    {
        $features = [];
        foreach ($this->getSiteFeatures($key) as $feature) {
            $features[$feature] = true;
        }
        foreach ($this->getEcommerceFeatures($key) as $feature) {
            $features[$feature] = true;
        }
        return $features;
    }

    protected function getAddonToSelectByDefault()
    {
        if ($this->getModel()->setting("general.include-weebly-free-by-default")) {
            $freePlan = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", self::WEEBLY_FREE)->get()->where("productAddon.module", "marketconnect")->first();
            if ($freePlan) {
                return $freePlan->productAddon->id;
            }
        }
    }
}
