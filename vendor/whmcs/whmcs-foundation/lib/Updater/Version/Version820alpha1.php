<?php

namespace WHMCS\Updater\Version;

class Version820alpha1 extends IncrementalVersion
{
    protected $updateActions = ["removeDeprecatedModules", "removeHMRCAccessTokens", "updateOpenXchangeEmailTemplate", "addSiteBuilderWelcomeEmail", "createNewUserValidationTable", "createNewUserIdentityVerificationTemplate", "createFreeDomainReminderEmailTemplate"];

    private function getDeprecatedModules()
    {
        return ["addons" => ["newtlds"], "gateways" => ["worldpayinvisible", "worldpayinvisiblexml", "pagseguro"]];
    }

    protected function removeDeprecatedModules()
    {
        try {
            (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getDeprecatedModules());
        } catch (\WHMCS\Exception $e) {
        }
        return $this;
    }

    public function removeHMRCAccessTokens()
    {
        try {
            \WHMCS\TransientData::getInstance()->delete("HMRCAccessToken");
            \WHMCS\Config\Setting::where("setting", "=", "HMRCClientId")->orWhere("setting", "=", "HMRCSecretId")->delete();
        } catch (\Throwable $e) {
        }
        return $this;
    }

    protected function updateOpenXchangeEmailTemplate()
    {
        $newTemplateMessage = "<p>Dear {\$client_name},</p>\n<p>\n    Thank you for purchasing {\$service_product_name} from Open-Xchange.<br>\n    Your email service has been set up and is ready for you to begin creating email accounts.\n</p>\n{if \$configuration_required}\n<p>\n    <strong>Required Action</strong><br>\n    To begin using Open-Xchange mail services, you must modify the MX records for your domain to the following:<br>\n<pre>{foreach from=\$required_mx_records key=mx_host item=mx_priority}\n{\$mx_host} with a recommended priority of {\$mx_priority}\n{/foreach}</pre>\n    The following SPF record is also recommended:\n    <pre>{\$required_spf_record}</pre>\n</p>\n{/if}\n<p>\n    To create, edit and administer your email addresses and passwords, please visit the \"Email User Management\" pages in your\n    <a href=\"{\$whmcs_url}clientarea.php?action=productdetails&id={\$service_id}\">client area</a>.\n</p>\n<p><a href=\"{\$webmail_link}\">Webmail Access</a></p>\n<p>\n    <strong>Mobile Access</strong><br>\n    To configure and sync email and PIM data on your mobile device, please refer to the \"Connect Your Device\" Wizard in App Suite.\n    You can find it under your profile icon in the top right-hand corner of your App Suite Webmail interface.<br>\n    You can also download the App Suite Mobile App here: <br>\n    <a href=\"https://apps.apple.com/us/app/ox-mail-by-open-xchange/id1385582725\">iOS</a> or\n    <a href=\"https://play.google.com/store/apps/details?id=com.openxchange.mobile.oxmail\">Android</a>\n</p>\n{if \$migration_tool_url}\n<p>\n    <strong>Migrations</strong><br>\n    OX App Suite has a quick and easy self-service migration tool to help you move your users.\n    You can find it here: <a href=\"{\$migration_tool_url}\">Migration Tool</a><br>\n</p>\n{/if}\n<p>If you have any questions, please contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>Thank you for choosing us as your trusted service provider.</p>\n<p>{\$signature}</p>";
        $previousMd5 = "6606a61382a785a0f20870e5cca0b0ac";
        $existingTemplates = \WHMCS\Mail\Template::master()->where("name", "Open-Xchange Welcome Email")->get();
        foreach ($existingTemplates as $existing) {
            if (md5($existing->message) === $previousMd5) {
                $existing->message = $newTemplateMessage;
                $existing->save();
            }
        }
        return $this;
    }

    public function createNewUserValidationTable()
    {
        (new \WHMCS\User\User\UserValidation())->createTable();
        return $this;
    }

    public function createNewUserIdentityVerificationTemplate()
    {
        $templateTitle = "User Identity Verification";
        if (!\WHMCS\Mail\Template::where("name", $templateTitle)->exists()) {
            $message = "<p>Dear {\$user_first_name},</p>\n<p>You have been requested to provide additional verification for your user account {\$user_email} at {\$company_name}.</p>\n<p>Log in to your account, and then locate and click the Submit Documents button to follow the steps for the secure submission process.</p>\n<p>Please complete this verification process to avoid possible interruptions with your account.</p>\n<p>Click on the link below to be taken to the Client Area:</p>\n<p><a href=\"{\$whmcs_url}\">{\$whmcs_url}</a></p>\n<p>{\$signature}</p>";
            $template = new \WHMCS\Mail\Template();
            $template->type = "user";
            $template->name = $templateTitle;
            $template->subject = "Identity Verification Required";
            $template->message = $message;
            $template->save();
        }
        return $this;
    }

    protected function createFreeDomainReminderEmailTemplate()
    {
        $exists = \WHMCS\Mail\Template::master()->where("name", "Upcoming Free Domain Renewal Notice")->first();
        if (!$exists) {
            $template = new \WHMCS\Mail\Template();
            $template->type = "domain";
            $template->name = "Upcoming Free Domain Renewal Notice";
            $template->subject = "Upcoming Domain Renewal Notice";
            $template->message = "<p>Dear {\$client_name},</p>\n<p>This is a reminder that the domain listed below is scheduled to expire soon.</p>\n<p>Domain Name - Expiry Date - Description</p>\n<p>--------------------------------------------------------------</p>\n<p>{\$domain_name} - {\$domain_next_due_date} - Expires in {\$domain_days_until_nextdue} Days</p>\n{if \$autoRenewalDisabled || (!\$freeDomainWithService && \$freeDomainAutoRenewRequiresProduct)}\n<p>Please be aware that if your domain name expires, any website or email services associated with it will stop working.</p>\n<p><a href=\"{\$domain_renewal_url}\">Renew your domain now</a> to avoid an interruption in service.</p>\n{elseif \$freeDomainWithService}\n<p><strong>This is an informational notice</strong>. Because this domain name is associated with a service including a free domain renewal, no action is needed to renew this domain automatically.</p>\n{else}\n<p><strong>This is an informational notice</strong>. This domain will automatically renew and you do not need to take any further action.</p>\n{/if}\n<p>To view and manage your domains, you can log in to our client area here: <a href=\"{\$domains_manage_url}\">Client Area</a></p>\n<p>If you have any questions, please reply to this email. Thank you for using our domain name services.</p>\n<p>{\$signature}</p>";
            $template->save();
        }
        return $this;
    }

    protected function addSiteBuilderWelcomeEmail()
    {
        $templateTitle = "Site Builder Welcome Email";
        if (!\WHMCS\Mail\Template::where("name", $templateTitle)->exists()) {
            $message = "<p>Dear {\$client_name},</p>\n<p>Congratulations!</p>\n<p>Your account has been set up and you are ready to begin building your website.</p>\n<p>{if \$configuration_required}</p>\n<p>To allow automatic publishing of your site, an FTP account is required. You can provide FTP details in the client area.</p>\n<p>{/if}</p>\n<p>To access the site builder and begin building your website, please <a href=\"{\$whmcs_url}clientarea.php?action=productdetails&amp;id={\$service_id}\">click here</a>.</p>\n<p>If you need any further assistance, please contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>{\$signature}</p>";
            $template = new \WHMCS\Mail\Template();
            $template->type = "product";
            $template->name = $templateTitle;
            $template->subject = "Welcome to Your New Website Builder";
            $template->message = $message;
            $template->save();
        }
        return $this;
    }

    public function getFeatureHighlights()
    {
        $utmString = "?utm_source=in-product&utm_medium=whatsnew82";
        return [new \WHMCS\Notification\FeatureHighlight("<span>Site</span> Builder", "Un-branded Site Builder with Open Free Trial, powered by Siteplus and Web.com", "marketconnect.png", "icon-builder.png", "A powerful drag & drop Site Builder solution with Open Free Trial enabling you to offer it with all your hosting plans.", "marketconnect.php?learnmore=sitebuilder", "Learn More", "marketconnect.php?activate=sitebuilder", "Start selling"), new \WHMCS\Notification\FeatureHighlight("<span>WP Toolkit</span> Automation", "Fully automated provisioning for any new or existing cPanel or Plesk hosting account", NULL, "icon-wordpress.png", "Offer Wordpress Management quickly and easily with our pre-made landing page, two-way SSO + tailored ordering experience.", "https://docs.whmcs.com/WordPress_Toolkit" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("User Identity <span>Verification</span>", "Automate the process of dealing with failed fraud checks and user verification", NULL, "icon-id.png", "New integration now available with Validation.com with support for both automatic and on-demand verification.", "https://docs.whmcs.com/User_Identity_Verification" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>Prorata</span> for Product Addons", "Synchronize billing of addons and services for increased customer convenience", NULL, "icon-prorata.png", "Offer add-ons that can be purchased at any time without resulting in additional invoices and complexity for customers.", "https://docs.whmcs.com/Prorata_Billing" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>Balance</span> & Transaction Insights", "View payment gateway balances and detailed transaction information within WHMCS", NULL, "icon-gateway.png", "Integrated for PayPal and Stripe at launch with more coming soon and accessible to 3rd party module developers.", "https://docs.whmcs.com/Payment_Gateway_Balances_and_Transactions" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>Stripe</span> Dashboard Widget", "View your Stripe Balances directly from the WHMCS dashboard", NULL, "icon-stripe.png", "A new dedicated Dashboard widget will show your Pending and Available Stripe balance information for all currencies.", "https://docs.whmcs.com/Stripe_Balance_Widget" . $utmString, "Learn More")];
    }
}
