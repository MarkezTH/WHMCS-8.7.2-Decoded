<?php

namespace WHMCS\Updater\Version;

class Version870beta2 extends IncrementalVersion
{
    protected $updateActions = ["removeOrphanedPendingAffiliatePayouts", "createNordVPNEmailTemplate", "updateOpenXchangeEmailTemplate", "forceSmartyPhpSettingDisplay", "queueScanForSmartyBcTags"];

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $whatsNewPath = ROOTDIR . DIRECTORY_SEPARATOR . "admin" . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "whatsnew" . DIRECTORY_SEPARATOR;
        $this->filesToRemove[] = $whatsNewPath . "bg-v86.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-ms.png";
        $this->filesToRemove[] = $whatsNewPath . "icon-php.png";
        $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ["resources", "views", "marketconnect", "services", "constantcontact"]);
    }

    public function getFeatureHighlights()
    {
        return [new \WHMCS\Notification\FeatureHighlight("CentralNic Reseller", "Take advantage of all of the features in the new CentralNic reseller platform.", NULL, "icon-centralnic.png", "The CentralNic Reseller domain registrar module lets you take advantage of all of the features in the new CentralNic reseller platform and replaces the older RRPProxy module.", "https://www.whmcs.com/members/link.php?id=1749", "Learn More"), new \WHMCS\Notification\FeatureHighlight("SSL Instant Issuance", "Secure customer websites in as little as a minute with no additional action needed.", NULL, "icon-instantissuance.png", "Selling SSL certificates through WHMCS MarketConnect is faster than ever with the new Instant Issuance feature. Your customers can secure their websites in as little as a minute with no additional action needed.", "https://www.whmcs.com/members/link.php?id=1725", "Learn More"), new \WHMCS\Notification\FeatureHighlight("NordVPN", "Enable NordVPN in MarketConnect today.", NULL, "icon-nord.png", "NordVPN's VPN services grant your customers peace of mind and security wherever they go.", "https://www.whmcs.com/members/link.php?id=1729", "Learn More")];
    }

    public function removeOrphanedPendingAffiliatePayouts()
    {
        $affiliatesPendingIds = \WHMCS\Affiliate\Pending::doesntHave("account")->get()->pluck("id");
        \WHMCS\Affiliate\Pending::doesntHave("account")->delete();
        if (0 < $affiliatesPendingIds->count()) {
            logActivity("The system removed the following orphaned pending affiliate payouts from the `tblaffiliatespending` table: " . $affiliatesPendingIds->join(","));
        }
        return $this;
    }

    public function createNordVPNEmailTemplate()
    {
        $templateTitle = \WHMCS\MarketConnect\Services\NordVPN::WELCOME_EMAIL_TEMPLATE;
        if (\WHMCS\Mail\Template::where("name", "=", $templateTitle)->exists()) {
            return $this;
        }
        $template = new \WHMCS\Mail\Template();
        $template->name = $templateTitle;
        $template->subject = "Getting Started with NordVPN";
        $template->message = "<p>Dear {\$client_name},</p>\n<p>Thank you for purchasing NordVPN to secure your personal and business data. You will receive an email from NordVPN containing your activation link soon. If you don’t see it in your inbox, check your spam folder.</p>\n<p>With 5,500+ servers across the globe, NordVPN gives you peace of mind when you use public Wi-Fi®, access personal and work accounts on the road, or just want to keep your browsing history to yourself.</p>\n<p>If you have questions about NordVPN or need technical support, contact <a href=\"https://support.nordvpn.com/\">NordVPN support</a>.</p>\n<p>If you have order or billing questions, contact our support team at <a href=\"{\$whmcs_url}/submitticket.php\">Submit a Ticket</a>.</p>\n<p>Thank you for choosing our services!</p>\n<p>{\$signature}</p>";
        $template->custom = false;
        $template->attachments = [];
        $template->type = "product";
        $template->plaintext = false;
        $template->fromEmail = "";
        $template->fromName = "";
        $template->language = "";
        $template->save();
        return $this;
    }

    public function updateOpenXchangeEmailTemplate()
    {
        $previousMd5 = "b046b0fe88e841c296a93d59edf10234";
        $newTemplateMessage = "<p>Dear {\$client_name},</p>\n<p>\n    Thank you for purchasing {\$service_product_name} from Open-Xchange.<br>\n    Your email service has been set up and is ready for you to begin creating email accounts.\n</p>\n{if \$configuration_required}\n<p>\n    <strong>Required Action</strong><br>\n    To begin using Open-Xchange mail services, you must modify the MX records for your domain to the following:<br>\n<pre>{foreach from=\$required_mx_records key=mx_host item=mx_priority}\n{\$mx_host} with a recommended priority of {\$mx_priority}\n{/foreach}</pre>\n    The following SPF record is also recommended:\n    <pre>{\$required_spf_record}</pre>\n</p>\n{/if}\n<p>\n    To create, edit and administer your email addresses and passwords, please visit the \"Email User Management\" pages in your\n    <a href=\"{\$whmcs_url}clientarea.php?action=productdetails&id={\$service_id}\">client area</a>.\n</p>\n<p>You can access OX App Suite via <a href=\"{\$webmail_link}\">the OX App Suite Cloud portal</a>.</p>\n<p>\n    <strong>Mobile Access</strong><br>\n    To configure and sync email and PIM data on your mobile device, please refer to the \"Connect Your Device\" Wizard in App Suite.\n    You can find it under your profile icon in the top right-hand corner of your App Suite Webmail interface.<br>\n    You can also download the App Suite Mobile App here: <br>\n    <a href=\"https://apps.apple.com/us/app/ox-mail-by-open-xchange/id1385582725\">iOS</a> or\n    <a href=\"https://play.google.com/store/apps/details?id=com.openxchange.mobile.oxmail\">Android</a>\n</p>\n{if \$migration_tool_url}\n<p>\n    <strong>Migrations</strong><br>\n    OX App Suite has a quick and easy self-service migration tool to help you move your users.\n    You can find it here: <a href=\"{\$migration_tool_url}\">Migration Tool</a><br>\n</p>\n{/if}\n<p>If you have any questions, please contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>Thank you for choosing us as your trusted service provider.</p>\n<p>{\$signature}</p>";
        $existingTemplates = \WHMCS\Mail\Template::master()->where("name", "Open-Xchange Welcome Email")->get();
        foreach ($existingTemplates as $existing) {
            if (md5($existing->message) === $previousMd5) {
                $existing->message = $newTemplateMessage;
                $existing->save();
            }
        }
        return $this;
    }

    public function forceSmartyPhpSettingDisplay()
    {
        if (is_null(\WHMCS\Config\Setting::find("DisplayAllowSmartyPhpSetting"))) {
            \WHMCS\Config\Setting::setValue("DisplayAllowSmartyPhpSetting", 1);
        }
    }

    public function queueScanForSmartyBcTags()
    {
        \WHMCS\Scheduling\Jobs\Queue::add("smartybc.rescan", "WHMCS\\Utility\\Smarty\\TagScanner", "findTagUsageAndRequeue", [\WHMCS\Utility\Smarty\TagScanner::DEPRECATED_SMARTY_BC_TAGS, \WHMCS\Utility\Smarty\TagScanner::DEPRECATED_SMARTY_BC_TAGS_CACHE_KEY, true], 5);
    }
}
