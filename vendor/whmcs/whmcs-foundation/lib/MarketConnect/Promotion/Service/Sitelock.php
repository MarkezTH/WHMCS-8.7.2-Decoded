<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class Sitelock extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_SITELOCK;
    protected $friendlyName = "Sitelock";
    protected $primaryIcon = "assets/img/marketconnect/sitelock/logo.png";
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = ["label" => "marketConnect.sitelock.manageSecurity", "icon" => "fa-bug", "image" => "assets/img/marketconnect/sitelock/logo-sml.png", "color" => "pomegranate", "dropdownReplacementText" => ""];
    protected $settings = [["name" => "include-sitelock-lite-by-default", "label" => "Include SiteLock Lite by Default", "description" => "Automatically pre-select SiteLock Lite by default for new orders of all applicable products", "default" => true]];
    protected $planFeatures = NULL;
    protected $recommendedUpgradePaths = NULL;
    protected $upsells = NULL;
    protected $upsellPromoContent = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $promotionalContent = NULL;
    const SITELOCK_LITE = NULL;
    const SITELOCK_FIND = NULL;
    const SITELOCK_FIX = NULL;
    const SITELOCK_DEFEND = NULL;
    const SITELOCK_EMERGENCY = NULL;
    const SITELOCK_SPECIAL = NULL;

    public function getPlanFeatures($key)
    {
        $return = [];
        if (isset($this->planFeatures[$key])) {
            foreach ($this->planFeatures[$key] as $stringToTranslate => $value) {
                $return[\Lang::trans("store.sitelock.features." . $stringToTranslate)] = $value;
            }
            return $return;
        } else {
            return $return;
        }
    }

    public function getFeaturesForUpgrade($key)
    {
        if ($key == self::SITELOCK_EMERGENCY) {
            return NULL;
        }
        return $this->getPlanFeatures($key);
    }

    protected function getAddonToSelectByDefault()
    {
        if ($this->getModel()->setting("general.include-sitelock-lite-by-default")) {
            $litePlan = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", self::SITELOCK_LITE)->get()->where("productAddon.module", "marketconnect")->first();
            return $litePlan->productAddon->id;
        }
        return NULL;
    }

    protected function getExcludedFromNewPurchaseAddonIds()
    {
        $emergencyPlan = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", self::SITELOCK_EMERGENCY)->get()->where("productAddon.module", "marketconnect")->first();
        return [$emergencyPlan->productAddon->id];
    }
}
