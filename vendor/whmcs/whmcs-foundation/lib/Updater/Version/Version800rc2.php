<?php

namespace WHMCS\Updater\Version;

class Version800rc2 extends IncrementalVersion
{
    protected $updateActions = ["addOpenXchangeEmailTemplate", "setMarketConnectProductGroupSlugs"];

    public function __construct(\WHMCS\Version\SemanticVersion $version = NULL)
    {
        if ($version) {
            parent::__construct($version);
        }
        $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "whmcs" . DIRECTORY_SEPARATOR . "whmcs-foundation" . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "Utility" . DIRECTORY_SEPARATOR . "Environment" . DIRECTORY_SEPARATOR . "CurrentUser.php";
    }

    protected function addOpenXchangeEmailTemplate()
    {
        $exists = \WHMCS\Mail\Template::master()->where("name", "Open-Xchange Welcome Email")->first();
        if (!$exists) {
            $template = new \WHMCS\Mail\Template();
            $template->type = "product";
            $template->name = "Open-Xchange Welcome Email";
            $template->subject = "Your new professional email";
            $template->message = "<p>Dear {\$client_name},</p>\n<p>\n    Thank you for purchasing {\$service_product_name} from Open-Xchange.<br>\n    Your email service has been setup and is ready for you to begin creating email accounts.\n</p>\n{if \$configuration_required}\n<p>\n    <strong>Required Action</strong><br>\n    To begin using Open-Xchange mail services, you must modify the MX records for your domain to the following:<br><br>\n    {foreach from=\$required_mx_records key=mx_host item=mx_priority} {\$mx_host} with a recommended priority of {\$mx_priority}<br />{/foreach}\n    The following SPF record is also recommended: \"{\$required_spf_record}\"\n</p>\n{/if}\n<p>\n    To create, edit and administer your email addresses and passwords, please visit the \"Email User Management\" pages in your <a href=\"{\$whmcs_url}clientarea.php?action=productdetails&id={\$service_id}\">client area</a>.\n</p>\n<p><a href=\"{\$webmail_link}\">Webmail Access</a></p>\n<p>\n    <strong>Mobile Access</strong><br>\n    To configure and sync email and PIM data on your mobile device, please refer to the 'Connect Your Device' Wizard in App Suite.\n    You can find it under your profile icon in the top right-hand corner of your App Suite Webmail interface.<br>\n    You can also download the App Suite Mobile App here: <br>\n    <a href=\"https://apps.apple.com/us/app/ox-mail-by-open-xchange/id1385582725\">iOS</a> or \n    <a href=\"https://play.google.com/store/apps/details?id=com.openxchange.mobile.oxmail\">Android</a>\n</p>\n{if \$migration_tool_url}\n<p>\n    <strong>Migrations</strong><br>\n    OX App Suite has a quick and easy{if \$migration_tool_free} (and for a limited time – FREE){/if} self service migration too to help you move your users. \n    You can find it here: <a href=\"{\$migration_tool_url}\">Migration tool</a><br>\n    If you have any further questions, please contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.<br>\n    Thank you for choosing us as your trusted service provider.\n</p>\n{/if}\n<p>{\$signature}</p>";
            $template->save();
        }
        return $this;
    }

    protected function setMarketConnectProductGroupSlugs()
    {
        try {
            if (\WHMCS\MarketConnect\MarketConnect::isAccountConfigured()) {
                foreach (\WHMCS\MarketConnect\MarketConnect::getActiveServices() as $service) {
                    $groupModel = \WHMCS\Product\Group::where("name", \WHMCS\MarketConnect\MarketConnect::getServiceProductGroupName($service))->first();
                    if ($groupModel) {
                        $groupModel->slug = \WHMCS\MarketConnect\MarketConnect::getServiceProductGroupSlug($service);
                        $groupModel->save();
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }
}
