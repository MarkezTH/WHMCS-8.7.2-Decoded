<?php

namespace WHMCS\Updater\Version;

class Version841release1 extends IncrementalVersion
{
    protected $updateActions = ["updateEmailTemplatesToUseRfcDomains", "removeUnusedLegacyModules"];

    public function updateEmailTemplatesToUseRfcDomains()
    {
        $oldTemplateMd5Hash = "12fb842b63f3b541ea6938b920e72ea8";
        $newTemplateContent = "<p align=\"center\">\n<strong>PLEASE PRINT THIS MESSAGE FOR YOUR RECORDS - PLEASE READ THIS EMAIL IN FULL.</strong>\n</p>\n<p>\nIf you have requested a domain name during sign up then this will not be visible on the internet for between 24 and 72 hours. This process is called Propagation. Until your domain has Propagated your website and email will not function, we have provided a temporary url which you may use to view your website and upload files in the meantime.\n</p>\n<p>\nDear {\$client_name},\n</p>\n<p>\nThe reseller hosting account for {\$service_domain} has been set up. The username and password below are for both cPanel to manage the website at {\$service_domain} and WebHostManager to manage your Reseller Account.\n</p>\n<p>\n<strong>New Account Info</strong>\n</p>\n<p>\nDomain: {\$service_domain}<br />\nUsername: {\$service_username}<br />\nPassword: {\$service_password}<br />\nHosting Package: {\$service_product_name}\n</p>\n<p>\nControl Panel: <a href=\"http://{\$service_server_ip}:2082/\">http://{\$service_server_ip}:2082/</a><br />\nWeb Host Manager: <a href=\"http://{\$service_server_ip}:2086/\">http://{\$service_server_ip}:2086/</a>\n</p>\n<p>\n-------------------------------------------------------------------------------------------- <br />\n<strong>Web Host Manager Quick Start</strong> <br />\n-------------------------------------------------------------------------------------------- <br />\n<br />\nTo access your Web Host Manager, use the following address:<br />\n<br />\n<a href=\"http://{\$service_server_ip}:2086/\">http://{\$service_server_ip}:2086/</a><br />\n<br />\nThe <strong>http://</strong> must be in the address line to connect to port :2086 <br />\nPlease use the username/password given above. <br />\n<br />\n<strong><em>To Create a New Account <br />\n</em></strong><br />\nThe first thing you need to do is scroll down on the left and click on &#39Add Package&#39 so that you can create your own hosting packages. You cannot install a domain onto your account without first creating packages.<br />\n<br />\n1. Click on &#39Create a New Account&#39 from the left hand side menu <br />\n2. Put the domain in the &#39Domain&#39 box (no www or http or spaces ? just domainname.com). After putting in the domain, hit TAB and it will automatically create a username. Also, enter a password for the account.<br />\n3. Your package selection should be one that you created earlier <br />\n4. Then press the create button <br />\n<br />\nThis will give you a confirmation page (you should print this for your records)\n</p>\n<p>\nPlease do not click on anything that you are not sure what it does. Please do not try to alter the WHM Theme from the selection box - fatal errors may occur. \n</p>\n<p>\n-------------------------------------------------------------------------------------------- \n</p>\n<p>\nTemporarily you may use one of the addresses given below manage your web site\n</p>\n<p>\nTemporary FTP Hostname: {\$service_server_ip}<br />\nTemporary Webpage URL: <a href=\"http://{\$service_server_ip}/~{\$service_username}/\">http://{\$service_server_ip}/~{\$service_username}/</a><br />\nTemporary Control Panel: <a href=\"http://{\$service_server_ip}/cpanel\">http://{\$service_server_ip}/cpanel</a>\n</p>\n<p>\nOnce your domain has Propagated\n</p>\n<p>\nFTP Hostname: www.{\$service_domain}<br />\nWebpage URL: <a href=\"http://www.{\$service_domain}\">http://www.{\$service_domain}</a><br />\nControl Panel: <a href=\"http://www.{\$service_domain}/cpanel\">http://www.{\$service_domain}/cpanel</a><br />\nWeb Host Manager: <a href=\"http://www.{\$service_domain}/whm\">http://www.{\$service_domain}/whm</a>\n</p>\n<p>\n<strong>Mail settings</strong>\n</p>\n<p>\nCatch all email with your default email account\n</p>\n<p>\nPOP3 Host Address : mail.{\$service_domain}<br />\nSMTP Host Address: mail.{\$service_domain}<br />\nUsername: {\$service_username}<br />\nPassword: {\$service_password}\n</p>\n<p>\nAdditional mail accounts that you add\n</p>\n<p>\nPOP3 Host Address : mail.{\$service_domain}<br />\nSMTP Host Address: mail.{\$service_domain}<br />\nUsername : The FULL email address that you are picking up from (e.g. info@example.com). <br />\nIf your email client cannot accept a @ symbol, then you may replace this with a backslash .<br />\nPassword : As specified in your control panel \n</p>\n<p>\nThank you for choosing us.\n</p>\n<p>\n{\$signature}\n</p>\n";
        $emailTemplate = \WHMCS\Mail\Template::whereName("Reseller Account Welcome Email")->first();
        if ($emailTemplate && md5($emailTemplate->message) === $oldTemplateMd5Hash) {
            $emailTemplate->message = $newTemplateContent;
            $emailTemplate->save();
        }
        return $this;
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }

    protected function getUnusedLegacyModules()
    {
        return ["gateways" => ["sagepaytokens"]];
    }
}
