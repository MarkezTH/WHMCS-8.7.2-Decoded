<?php

namespace WHMCS\Updater\Version;

class Version860rc1 extends IncrementalVersion
{
    protected $updateActions = ["removeUnusedLegacyModules"];

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $whatsNewPath = ROOTDIR . DIRECTORY_SEPARATOR . "admin" . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "whatsnew" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $whatsNewPath . "icon-cpseo.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-crosssell.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-hooks.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-pleskmetric.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-ssl.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-sso.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-syssettings.png";
        $this->filesToRemove[] = $whatsNewPath . "bg-v83.png";
        $transIpPath = ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "registrars" . DIRECTORY_SEPARATOR . "transip" . DIRECTORY_SEPARATOR . "Transip" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $transIpPath . "ApiSettings.php";
        $this->filesToRemove[] = $transIpPath . "ColocationService.php";
        $this->filesToRemove[] = $transIpPath . "Cronjob.php";
        $this->filesToRemove[] = $transIpPath . "DataCenterVisitor.php";
        $this->filesToRemove[] = $transIpPath . "Db.php";
        $this->filesToRemove[] = $transIpPath . "DnsEntry.php";
        $this->filesToRemove[] = $transIpPath . "Domain.php";
        $this->filesToRemove[] = $transIpPath . "DomainAction.php";
        $this->filesToRemove[] = $transIpPath . "DomainBranding.php";
        $this->filesToRemove[] = $transIpPath . "DomainCheckResult.php";
        $this->filesToRemove[] = $transIpPath . "DomainService.php";
        $this->filesToRemove[] = $transIpPath . "Forward.php";
        $this->filesToRemove[] = $transIpPath . "ForwardService.php";
        $this->filesToRemove[] = $transIpPath . "MailBox.php";
        $this->filesToRemove[] = $transIpPath . "MailForward.php";
        $this->filesToRemove[] = $transIpPath . "Nameserver.php";
        $this->filesToRemove[] = $transIpPath . "SubDomain.php";
        $this->filesToRemove[] = $transIpPath . "Tld.php";
        $this->filesToRemove[] = $transIpPath . "WebHost.php";
        $this->filesToRemove[] = $transIpPath . "WebhostingPackage.php";
        $this->filesToRemove[] = $transIpPath . "WebhostingService.php";
        $this->filesToRemove[] = $transIpPath . "WhoisContact.php";
        $serversPath = ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "servers" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $serversPath . DIRECTORY_SEPARATOR . "enomssl" . DIRECTORY_SEPARATOR . "hooks.php";
        $this->filesToRemove[] = $serversPath . DIRECTORY_SEPARATOR . "globalsignssl" . DIRECTORY_SEPARATOR . "hooks.php";
        $this->filesToRemove[] = $serversPath . DIRECTORY_SEPARATOR . "resellerclubssl" . DIRECTORY_SEPARATOR . "hooks.php";
        $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "marketconnect" . DIRECTORY_SEPARATOR . "shared" . DIRECTORY_SEPARATOR . "header.php";
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused(["gateways" => ["paymate"]]);
        return $this;
    }

    public function getFeatureHighlights()
    {
        return [new \WHMCS\Notification\FeatureHighlight("PHP 8.1 Support", "WHMCS now supports PHP 8.1.", NULL, "icon-php.png", "Stay up-to-date to receive important PHP security fixes and enhancements.", "https://go.whmcs.com/1697/whats-new-86-php", "Learn More"), new \WHMCS\Notification\FeatureHighlight("OAuth2 Support for Microsoft® Services", "Enhanced configuration options help you comply with upcoming authentication requirements for Microsoft® email services.", NULL, "icon-ms.png", "Configure Hotmail®, Microsoft Outlook®, Microsoft 365®, and more.", "https://go.whmcs.com/1689/whats-new-86-email", "Learn More")];
    }
}
