<?php

namespace WHMCS\Updater\Version;

class Version870release1 extends IncrementalVersion
{
    protected $updateActions = ["removeCPanelSEOWelcomeEmailTemplate", "createXoviNowWelcomeEmailTemplate", "updateCpanelSEOToXoviNow", "addConfigurationFileMysqlCharsetDefaultIfMissing", "create360MonitoringWelcomeEmailTemplate"];
    const THREESIXTYMONITORING_WELCOME_MESSAGE = "<p>Hi, {\$client_first_name}!</p>\n<p>Welcome to 360 Monitoring! Your 360 Monitoring account is registered, your specified domain ({\$domain}) is configured, and you are ready to perform real-time monitoring.</p>\n<p>To access the 360 Monitoring dashboard and view results, make adjustments, or include additional monitors based on your chosen plan, log in to our Client Area and click Login under 360 Monitoring.</p>\n<p>For more information on 360 Monitoring features, including{if \$numberOfMonitors > 1} adding monitors,{if \$numberOfServers > 0} servers,{/if}{/if} dashboards, reports, and more, visit the <a href=\"https://docs.360monitoring.com/docs\"><strong>360 Monitoring documentation</strong></a>.</p>\n<p>{\$signature}</p>\n";

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "assets", "img", "marketconnect", "cpanelseo"]);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "resources", "views", "marketconnect", "services", "cpanelseo"]);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "vendor", "whmcs", "whmcs-foundation", "lib", "MarketConnect", "CPanelSEOController.php"]);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "vendor", "whmcs", "whmcs-foundation", "lib", "MarketConnect", "Services", "CPanelSEO.php"]);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "vendor", "whmcs", "whmcs-foundation", "lib", "MarketConnect", "Promotion", "Service", "CPanelSEO.php"]);
    }

    public function removeCPanelSEOWelcomeEmailTemplate()
    {
        $cPanelSEOTemplateName = "cPanel SEO Welcome Email";
        $cPanelTemplate = \WHMCS\Mail\Template::master()->where("name", $cPanelSEOTemplateName)->first();
        if ($cPanelTemplate) {
            $cPanelTemplate->delete();
        }
    }

    public function createXoviNowWelcomeEmailTemplate()
    {
        $templateTitle = \WHMCS\MarketConnect\Services\XoviNow::WELCOME_EMAIL_TEMPLATE;
        if (\WHMCS\Mail\Template::where("name", "=", $templateTitle)->exists()) {
            return $this;
        }
        $template = new \WHMCS\Mail\Template();
        $template->type = "product";
        $template->name = $templateTitle;
        $template->subject = "Get Started with XOVI NOW";
        $template->message = "<p>Dear {\$client_name},</p>\n<p>Welcome to XOVI NOW! You're ready to find relevant keywords, optimize your content, and get to the top of the Google® search results.</p>\n<p>Log in now to complete the setup wizard so that XOVI NOW can begin analyzing your website. After it finishes, you'll be able to view your rankings, keywords, and visibility and get started improving your position in the XOVI NOW Advisor.</p>\n<p>\nTo get started, log in to our Client Area and follow the link to access XOVI NOW:<br>\n{\$whmcs_url}clientarea.php\n</p>\n<p>If you need any further assistance, you may contact our support team at any time.</p>\n<p>{\$signature}</p>";
        $template->save();
        return $this;
    }

    public function updateCpanelSEOToXoviNow()
    {
        $oldService = "cpanelseo";
        $cPanelSEOService = \WHMCS\MarketConnect\Service::name($oldService)->first();
        if (is_null($cPanelSEOService)) {
            return NULL;
        }
        $newService = \WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW;
        $replacementPlans = ["cpanelseo_starter" => "xovinow_starter", "cpanelseo_pro" => "xovinow_pro"];
        $oldName = "cPanel SEO";
        $newName = "XOVI NOW";
        $newProductSlugs = ["xovinow_starter" => "starter", "xovinow_pro" => "professional"];
        $productGroup = $cPanelSEOService->productGroup;
        if (!is_null($productGroup)) {
            $productGroup->slug = $newService;
            $productGroup->name = str_ireplace($oldName, $newName, $productGroup->name);
            $productGroup->save();
        }
        \WHMCS\Config\Module\ModuleConfiguration::typeAddon()->where("setting_name", "configoption1")->whereIn("value", array_keys($replacementPlans))->each(function (\WHMCS\Config\Module\ModuleConfiguration $mod) use($replacementPlans, $oldName, $newName) {
            $mod->value = $replacementPlans[$mod->value];
            $mod->save();
            $addon = $mod->productAddon;
            if (!empty($addon)) {
                $addon->name = str_ireplace($oldName, $newName, $addon->name);
                $addon->save();
            }
        });
        $cPanelSEOService->getAssociatedProducts()->each(function (\WHMCS\Product\Product $product) use($replacementPlans, $newProductSlugs) {
            if (array_key_exists($product->moduleConfigOption1, $replacementPlans)) {
                $product->moduleConfigOption1 = $replacementPlans[$product->moduleConfigOption1];
                $product->save();
                $product->slugs()->update(["active" => false]);
                $slug = $newProductSlugs[$product->moduleConfigOption1];
                $activeSlug = $product->slugs()->where("group_id", $product->productGroup->id)->where("group_slug", $product->productGroup->slug)->where("slug", $slug)->first();
                if (empty($activeSlug)) {
                    $product->slugs()->create(["group_id" => $product->productGroup->id, "group_slug" => $product->productGroup->slug, "slug" => $slug, "active" => true]);
                } else {
                    $activeSlug->active = true;
                    $activeSlug->save();
                }
                unset($slug);
            }
        });
        $cPanelSEOService->name = $newService;
        $cPanelSEOService->productIds = array_values($replacementPlans);
        $cPanelSEOService->save();
    }

    public function addConfigurationFileMysqlCharsetDefaultIfMissing()
    {
        $defaultCharset = "latin1";
        try {
            $configFile = ROOTDIR . DIRECTORY_SEPARATOR . \WHMCS\Config\Application::WHMCS_DEFAULT_CONFIG_FILE;
            $currentCharset = (new \WHMCS\Config\Application())->loadConfigFile($configFile)->getDatabaseCharset();
            if (empty($currentCharset)) {
                (new \WHMCS\Config\ApplicationWriter())->setValue("mysql_charset", $defaultCharset);
            }
        } catch (\Throwable $e) {
            $msg = sprintf("Updater unable to add default '%s' mysql_charset to configuration file: %s", $defaultCharset, $e->getMessage());
            logActivity($msg);
        }
    }

    public function create360MonitoringWelcomeEmailTemplate()
    {
        $templateTitle = \WHMCS\MarketConnect\Services\ThreeSixtyMonitoring::WELCOME_EMAIL_TEMPLATE;
        if (\WHMCS\Mail\Template::where("name", "=", $templateTitle)->exists()) {
            return $this;
        }
        $template = new \WHMCS\Mail\Template();
        $template->type = "product";
        $template->name = $templateTitle;
        $template->subject = "Welcome to 360 Monitoring. Get started with monitoring now!";
        $template->message = self::THREESIXTYMONITORING_WELCOME_MESSAGE;
        $template->save();
        return $this;
    }

    public function getFeatureHighlights()
    {
        return [new \WHMCS\Notification\FeatureHighlight("CentralNic Reseller", "Take advantage of all of the features in the new CentralNic reseller platform.", NULL, "icon-centralnic.png", "The CentralNic Reseller domain registrar module lets you take advantage of all of the features in the new CentralNic reseller platform and replaces the older RRPProxy module.", "https://www.whmcs.com/members/link.php?id=1749", "Learn More"), new \WHMCS\Notification\FeatureHighlight("SSL Instant Issuance", "Secure customer websites in as little as a minute with no additional action needed.", NULL, "icon-instantissuance.png", "Selling SSL certificates through WHMCS MarketConnect is faster than ever with the new Instant Issuance feature. Your customers can secure their websites in as little as a minute with no additional action needed.", "https://www.whmcs.com/members/link.php?id=1725", "Learn More"), new \WHMCS\Notification\FeatureHighlight("NordVPN", "Enable NordVPN in MarketConnect today.", NULL, "icon-nord.png", "NordVPN's VPN services grant your customers peace of mind and security wherever they go.", "https://www.whmcs.com/members/link.php?id=1729", "Learn More")];
    }
}
