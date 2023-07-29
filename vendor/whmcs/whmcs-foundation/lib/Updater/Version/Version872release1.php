<?php

namespace WHMCS\Updater\Version;

class Version872release1 extends IncrementalVersion
{
    protected $updateActions = ["update360MonitoringWelcomeEmail"];
    const THREESIXTYMONITORING_WELCOME_MESSAGE = "<p>Hi, {\$client_first_name}!</p>\n<p>Welcome to 360 Monitoring! Your 360 Monitoring account is registered, your specified domain ({\$domain}) is configured, and you are ready to perform real-time monitoring.</p>\n<p>To access the 360 Monitoring dashboard and view results, make adjustments, or include additional websites based on your chosen plan, log in to our Client Area and click Login under 360 Monitoring.</p>\n<p>For more information on 360 Monitoring features, including{if \$numberOfMonitors > 1} adding websites,{if \$numberOfServers > 0} servers,{/if}{/if} dashboards, reports, and more, visit the <a href=\"https://docs.360monitoring.com/docs\"><strong>360 Monitoring documentation</strong></a>.</p>\n<p>{\$signature}</p>";

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
    }

    public function getFeatureHighlights()
    {
        return [new \WHMCS\Notification\FeatureHighlight("360 Monitoring", "Enable 360 Monitoring in WHMCS MarketConnect today.", NULL, "icon-360monitoring.png", "With 360 Monitoring for websites and servers, your customers can optimize performance and detect downtime immediately.", "https://go.whmcs.com/1757/360-Monitoring", "Learn More"), new \WHMCS\Notification\FeatureHighlight("CentralNic Reseller", "Take advantage of all of the features in the new CentralNic reseller platform.", NULL, "icon-centralnic.png", "The CentralNic Reseller domain registrar module lets you take advantage of all of the features in the new CentralNic reseller platform and replaces the older RRPProxy module.", "https://www.whmcs.com/members/link.php?id=1749", "Learn More"), new \WHMCS\Notification\FeatureHighlight("SSL Instant Issuance", "Secure customer websites in as little as a minute with no additional action needed.", NULL, "icon-instantissuance.png", "Selling SSL certificates through WHMCS MarketConnect is faster than ever with the new Instant Issuance feature. Your customers can secure their websites in as little as a minute with no additional action needed.", "https://www.whmcs.com/members/link.php?id=1725", "Learn More"), new \WHMCS\Notification\FeatureHighlight("NordVPN", "Enable NordVPN in MarketConnect today.", NULL, "icon-nord.png", "NordVPN's VPN services grant your customers peace of mind and security wherever they go.", "https://www.whmcs.com/members/link.php?id=1729", "Learn More")];
    }

    public function update360MonitoringWelcomeEmail()
    {
        $emailMD5Values = ["a9c437794bcb30c8835643dc3e051781", "4668a2891f31fbae88eae1ebfec4e0bd"];
        $templateTitle = \WHMCS\MarketConnect\Services\ThreeSixtyMonitoring::WELCOME_EMAIL_TEMPLATE;
        $template = \WHMCS\Mail\Template::master()->where("name", $templateTitle)->where("language", "")->first();
        if (is_null($template)) {
            $template = new \WHMCS\Mail\Template();
            $template->type = "product";
            $template->name = $templateTitle;
            $template->subject = "Welcome to 360 Monitoring. Get started with monitoring now!";
            $template->message = self::THREESIXTYMONITORING_WELCOME_MESSAGE;
            $template->save();
        } else {
            if (in_array(md5($template->message), $emailMD5Values)) {
                $template->message = self::THREESIXTYMONITORING_WELCOME_MESSAGE;
                $template->save();
            }
        }
        return $this;
    }
}
