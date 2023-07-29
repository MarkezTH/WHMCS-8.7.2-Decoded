<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class Marketgoo extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_MARKETGOO;
    protected $friendlyName = "Marketgoo";
    protected $primaryIcon = "assets/img/marketconnect/marketgoo/logo.png";
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = ["label" => "marketConnect.marketgoo.manageSEO", "icon" => "fa-search", "image" => "assets/img/marketconnect/marketgoo/logo-sml.svg", "color" => "blue", "dropdownReplacementText" => ""];
    protected $recommendedUpgradePaths = NULL;
    protected $upsells = NULL;
    protected $upsellPromoContent = NULL;
    protected $promotionalContent = NULL;
    protected $defaultPromotionalContent = NULL;
    const MARKETGOO_LITE = NULL;
    const MARKETGOO_PRO = NULL;

    public function getPlanFeatures($key)
    {
        $planFeatures = [self::MARKETGOO_LITE => ["Search engine submission" => true, "Connect Google Analytics" => true, "Download SEO report as PDF" => true, "Pages scanned" => \Lang::trans("upTo", [":num" => 50]), "Competitor tracking" => \Lang::trans("upTo", [":num" => 2]), "Keyword tracking & optimization" => \Lang::trans("upTo", [":num" => 5]), "Updated report & plan" => \Lang::trans("weekly"), "Custom SEO Plan" => \Lang::trans("limited"), "Monthly progress report" => true], self::MARKETGOO_PRO => ["Search engine submission" => true, "Connect Google Analytics" => true, "Download SEO report as PDF" => true, "Pages scanned" => \Lang::trans("upTo", [":num" => 1000]), "Competitor tracking" => \Lang::trans("upTo", [":num" => 4]), "Keyword tracking & optimization" => \Lang::trans("upTo", [":num" => 20]), "Updated report & plan" => \Lang::trans("daily"), "Custom SEO Plan" => \Lang::trans("store.marketgoo.completeStepByStep"), "Monthly progress report" => true]];
        return isset($planFeatures[$key]) ? $planFeatures[$key] : [];
    }

    protected function getAddonToSelectByDefault()
    {
        if ($this->getModel()->setting("general.include-marketgoo-basic-by-default")) {
            $litePlan = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", self::MARKETGOO_LITE)->get()->where("productAddon.module", "marketconnect")->first();
            return $litePlan->productAddon->id;
        }
        return NULL;
    }
}
