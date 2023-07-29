<?php

namespace WHMCS\Updater\Version;

class Version850rc1 extends IncrementalVersion
{
    protected $updateActions = ["removeUnusedLegacyModules", "forceDeprecateBuycPanelModule"];

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $whatsNewPath = ROOTDIR . DIRECTORY_SEPARATOR . "admin" . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "whatsnew" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $whatsNewPath . "icon-aliases.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-seals.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-wordpress.png";
        $this->filesToRemove[] = $whatsNewPath . "v8.png";
        $symantecAssets = ROOTDIR . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "marketconnect" . DIRECTORY_SEPARATOR . "symantec" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $symantecAssets . "logo-lrg.png";
    }

    public function getUnusedLegacyModules()
    {
        return ["registrars" => ["gmointernet"]];
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }

    public function getFeatureHighlights()
    {
        $utmString = "?utm_source=in-product&utm_medium=whatsnew85";
        return [new \WHMCS\Notification\FeatureHighlight("cPanel SEO now in MarketConnect", "You can now offer two plans for powerful SEO tools through MarketConnect.", NULL, "icon-cpseo.png", "Help your customers reach the top of Google®'s search results easily.", "https://docs.whmcs.com/cPanel_SEO_via_WHMCS_MarketConnect" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Offer Multi-Year SSL Orders with DigiCert", "You can now offer two-year or three-year DigiCert SSL certificate orders through MarketConnect.", NULL, "icon-ssl.png", "WHMCS handles everything, including reissuance, reinstallation, and renewals.", "https://docs.whmcs.com/SSL_Certificates_via_WHMCS_MarketConnect" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("SSO from the Client Area Home Page", "Clients can now use single sign-on to access services from the Client Area Home Page.", NULL, "icon-sso.png", "One click after login can access all SSO-compatible services."), new \WHMCS\Notification\FeatureHighlight("Cross-sell with Product Recommendations", "Configure cross-selling to offer product recommendations for related items in the shopping cart.", NULL, "icon-crosssell.png", "You control location, content, appearance, and product associations.", "https://docs.whmcs.com/Cross-selling_and_Product_Recommendations_In_WHMCS" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Metric Billing for Plesk", "You can now configure usage billing for your Plesk servers.", NULL, "icon-pleskmetric.png", "Bill clients for the resources they use, including email addresses, disk space, and bandwidth.", "https://docs.whmcs.com/Plesk" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Improved System Settings and Setup Tasks", "We improved the System Settings page and its list of setup tasks for fast, easy use with a clean, modern feel.", NULL, "icon-syssettings.png", "Search for the feature you need, sort them by name or popularity, and view them in popular categories.", "https://docs.whmcs.com/Setup_Tasks" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Hook System Improvements", "You can now easily exclude hook files from execution.", NULL, "icon-hooks.png", "A new function makes it possible to exclude hook files from execution by prefixing their names with underscores (_).", "https://developers.whmcs.com/hooks/" . $utmString, "Learn More")];
    }

    public function forceDeprecateBuycPanelModule()
    {
        $moduleType = \WHMCS\Module\AbstractModule::TYPE_SERVER;
        $moduleName = "buycpanel";
        $where = \WHMCS\Database\Capsule::table("tblproducts")->where("servertype", $moduleName);
        $productIds = $where->get()->pluck("id");
        $where->update(["servertype" => "autorelease"]);
        $where = \WHMCS\Database\Capsule::table("tbladdons")->where("module", $moduleName);
        $addonIds = $where->get()->pluck("id");
        $where->update(["module" => "autorelease"]);
        if (!empty($productIds)) {
            logActivity("The system updated the following products using the \"Buy cPanel\" module to the \"Auto Release\" module: " . $productIds->join(","));
        }
        if (!empty($addonIds)) {
            logActivity("The system updated the following addons using the \"Buy cPanel\" module to the \"Auto Release\" module: " . $addonIds->join(","));
        }
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused([$moduleType => [$moduleName]]);
        return $this;
    }
}
