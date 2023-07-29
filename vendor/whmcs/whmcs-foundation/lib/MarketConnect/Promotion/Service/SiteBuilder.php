<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class SiteBuilder extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_SITEBUILDER;
    protected $friendlyName = "Site Builder";
    protected $primaryIcon = "assets/img/marketconnect/sitebuilder/logo-sml.png";
    protected $promoteToNewClients = true;
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $upsells = NULL;
    protected $loginPanel = ["label" => "marketConnect.siteBuilder.buildWebsite", "icon" => "fa-desktop", "image" => "assets/img/marketconnect/sitebuilder/logo-sml.png", "color" => "magenta", "dropdownReplacementText" => ""];
    protected $settings = [["name" => "include-site-builder-trial-by-default", "label" => "Include Site Builder Open Trial by Default", "description" => "Automatically pre-select Site Builder Open Trial by default for new orders of all applicable products", "default" => true]];
    protected $upsellPromoContent = NULL;
    protected $idealFor = NULL;
    protected $features = ["Professional Quality Website Templates", "User-First Design for All Skill Levels", "Easy Drag & Drop Editing", "Responsive to Mobile Devices", "Free Image Gallery", "Component Based Building Blocks", "Blog", "Auto Layouts for Proportional Spacing", "Contact Form Builder", "Restore Websites", "Theme Inheritance", "Social Media Integration", "SEO Friendly", "Built-In Analytics", "Pages", "E-Commerce Products"];
    protected $planFeatures = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $promotionalContent = NULL;
    protected $recommendedUpgradePaths = NULL;
    const SITEBUILDER_TRIAL = NULL;
    const SITEBUILDER_ONE_PAGE = NULL;
    const SITEBUILDER_UNLIMITED = NULL;
    const SITEBUILDER_STORE = NULL;
    const SITEBUILDER_STORE_PLUS = NULL;
    const SITEBUILDER_STORE_PREMIUM = NULL;
    const SITEBUILDER_PAID = NULL;

    public function getModel()
    {
        return \WHMCS\MarketConnect\Service::where("name", \WHMCS\MarketConnect\MarketConnect::SERVICE_SITEBUILDER)->first();
    }

    public function getFeatures()
    {
        return $this->features;
    }

    protected function getAddonToSelectByDefault()
    {
        if ($this->getModel()->setting("general.include-site-builder-trial-by-default")) {
            $freePlan = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", self::SITEBUILDER_TRIAL)->get()->where("productAddon.module", "marketconnect")->first();
            return $freePlan->productAddon->id;
        }
        return NULL;
    }
}
