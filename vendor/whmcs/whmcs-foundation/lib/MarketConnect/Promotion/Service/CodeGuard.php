<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class CodeGuard extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_CODEGUARD;
    protected $friendlyName = "CodeGuard";
    protected $primaryIcon = "assets/img/marketconnect/codeguard/logo-sml.png";
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = ["label" => "marketConnect.codeGuard.manageBackup", "icon" => "fa-hdd", "image" => "assets/img/marketconnect/codeguard/hero-image-a.png", "color" => "lime", "dropdownReplacementText" => ""];
    protected $defaultPromotionalContent = NULL;
    protected $upsells = NULL;
    protected $recommendedUpgradePaths = NULL;
    const CODEGUARD_LITE = NULL;
    const CODEGUARD_PERSONAL = NULL;
    const CODEGUARD_PROFESSIONAL = NULL;
    const CODEGUARD_BUSINESS = NULL;
    const CODEGUARD_BUSINESSPLUS = NULL;
    const CODEGUARD_POWER = NULL;
    const CODEGUARD_POWERPLUS = NULL;

    public function __construct()
    {
        $products = \WHMCS\Product\Product::codeguard()->pluck("name", "configoption1");
        foreach ($this->upsells as $upsell) {
            $capacity = "";
            if (isset($products[$upsell[0]])) {
                $capacity = self::getDiskSpaceFromName($products[$upsell[0]]);
            }
            $this->upsellPromoContent[$upsell[0]] = ["imagePath" => $this->primaryIcon, "headline" => "Add More Storage", "tagline" => "Increase backup space to " . $capacity, "features" => ["Store more website data", "Retain more backup history"], "learnMoreRoute" => ["route" => "store-product-group", "service" => \WHMCS\MarketConnect\MarketConnect::SERVICE_CODEGUARD], "cta" => "Upgrade to"];
        }
    }

    public static function getDiskSpaceFromName($name)
    {
        if (!is_string($name)) {
            return $name;
        }
        $diskSpace = NULL;
        preg_match("/[\\d]+GB/i", $name, $diskSpace);
        if (isset($diskSpace[0])) {
            return $diskSpace[0];
        }
        return $name;
    }

    public function getFeaturesForUpgrade($key)
    {
        $standardFeatures = ["Automated Daily Backups" => true, "One-Click Restores" => true, "WordPress Plugin" => true, "WordPress Auto Updates" => true, "File Change Monitoring" => true, "Malware Detection" => true];
        $features = [];
        switch ($key) {
            case self::CODEGUARD_LITE:
                $features = ["Disk Space" => "1GB"];
                break;
            case self::CODEGUARD_PERSONAL:
                $features = ["Disk Space" => "5GB"];
                break;
            case self::CODEGUARD_PROFESSIONAL:
                $features = ["Disk Space" => "10GB"];
                break;
            case self::CODEGUARD_BUSINESS:
                $features = ["Disk Space" => "25GB"];
                break;
            case self::CODEGUARD_BUSINESSPLUS:
                $features = ["Disk Space" => "50GB"];
                break;
            case self::CODEGUARD_POWER:
                $features = ["Disk Space" => "100GB"];
                break;
            case self::CODEGUARD_POWERPLUS:
                $features = ["Disk Space" => "200GB"];
                break;
            default:
                return array_merge($features, $standardFeatures);
        }
    }
}
