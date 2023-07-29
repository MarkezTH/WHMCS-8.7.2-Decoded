<?php

namespace WHMCS\Updater\Version;

class Version840rc1 extends IncrementalVersion
{
    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $whatsNewPath = ROOTDIR . DIRECTORY_SEPARATOR . "admin" . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "whatsnew" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $whatsNewPath . "icon-enhance.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-gateway.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-reverse.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-seo.png";
    }

    public function getFeatureHighlights()
    {
        $utmString = "?utm_source=in-product&utm_medium=whatsnew84";
        return [new \WHMCS\Notification\FeatureHighlight("SSL Certificate Site Seals", "It's easy for clients to find site seals for DigiCert, GeoTrust, and RapidSSL certificates from MarketConnect.", NULL, "icon-seals.png", "Clients can quickly access certificate site seals to add to their websites.", "https://marketplace.whmcs.com/help/connect/kb/digicert_ssl_certificates/managing_orders/how_do_i_get_my_site_seal" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Optimized Merchandising for SSL Products", "We're adding recommendation labels and highlighting to your chosen SSL products to help clients find those SSL products first.", NULL, "icon-ssl.png", "Put your highest-margin SSL products in the spotlight."), new \WHMCS\Notification\FeatureHighlight("Email Aliases for OX App Suite", "Email alias creation and management in the Client Area lets your clients send and receive with multiple addresses using a single OX App Suite account.", NULL, "icon-aliases.png", "Just one mailbox can send and receive for many addresses.", "https://marketplace.whmcs.com/help/connect/kb/ox_app_suite/getting_started/what_is_an_email_alias" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Faster WordPress® Hosting Checkout", "A new setting for concurrent event handling reduces the required time for provisioning WordPress hosting products.", NULL, "icon-wordpress.png", "Give a faster checkout experience to your customers.", "https://docs.whmcs.com/Other_Tab#Event_Handling" . $utmString, "Learn More")];
    }
}
