<?php

namespace WHMCS\Updater\Version;

class Version810alpha1 extends IncrementalVersion
{
    protected $updateActions = ["removeAutoAuthConfigurationValue", "removeUnusedLegacyModules", "updateNewOrderNotificationEmailTemplate", "addDeleteUsersPermission", "removeUnnecessaryStripePlaidPublicKey", "markPreExistingAdminsDuoConfig"];

    public function __construct(\WHMCS\Version\SemanticVersion $version = NULL)
    {
        if ($version) {
            parent::__construct($version);
            $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, ["vendor", "whmcs", "whmcs-foundation", "lib", "Authentication", "AutoAuth.php"]);
        }
    }

    public function getUnusedLegacyModules()
    {
        return ["registrars" => ["realtimeregister"]];
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }

    public function updateNewOrderNotificationEmailTemplate()
    {
        $oldTemplateMd5Hash = "4826bd49005f252a3322d369ac82ab59";
        $newTemplateContent = "<p><strong>Order Information</strong></p>\n<p>Order ID: {\$order_id}<br />\nOrder Number: {\$order_number}<br />\nDate/Time: {\$order_date}<br />\nInvoice Number: {if \$custom_invoice_number}{\$custom_invoice_number}{else}{\$invoice_id}{/if}<br />\nPayment Method: {\$order_payment_method}</p>\n<p><strong>Customer Information</strong></p>\n<p>Customer ID: {\$client_id}<br />\nName: {\$client_first_name} {\$client_last_name}<br />\nEmail: {\$client_email}<br />\nCompany: {\$client_company_name}<br />\nAddress 1: {\$client_address1}<br />\nAddress 2: {\$client_address2}<br />\nCity: {\$client_city}<br />\nState: {\$client_state}<br />\nPostcode: {\$client_postcode}<br />\nCountry: {\$client_country}<br />\nPhone Number: {\$client_phonenumber}</p>\n<p><strong>Order Items</strong></p>\n<p>{\$order_items}</p>\n{if \$order_notes}<p><strong>Order Notes</strong></p>\n<p>{\$order_notes}</p>{/if}\n<p><strong>ISP Information</strong></p>\n<p>IP: {\$client_ip}<br />\nHost: {\$client_hostname}</p><p><a href=\"{\$whmcs_admin_url}orders.php?action=view&id={\$order_id}\">{\$whmcs_admin_url}orders.php?action=view&id={\$order_id}</a></p>";
        $emailTemplate = \WHMCS\Mail\Template::whereName("New Order Notification")->first();
        if ($emailTemplate && md5($emailTemplate->message) === $oldTemplateMd5Hash) {
            $emailTemplate->message = $newTemplateContent;
            $emailTemplate->save();
        }
    }

    protected function addDeleteUsersPermission()
    {
        $exists = \WHMCS\Database\Capsule::table("tbladminperms")->where("roleid", 1)->where("permid", 153)->first();
        if (!$exists) {
            \WHMCS\Database\Capsule::table("tbladminperms")->insert(["roleid" => 1, "permid" => 153]);
        }
        return $this;
    }

    public function removeAutoAuthConfigurationValue()
    {
        \WHMCS\Config\Setting::where("setting", "=", \WHMCS\Authentication\Client::SETTING_ALLOW_AUTOAUTH)->delete();
        return $this;
    }

    public function removeUnnecessaryStripePlaidPublicKey()
    {
        \WHMCS\Module\GatewaySetting::where("gateway", "=", "stripe_ach")->where("setting", "=", "plaidPublicKey")->delete();
        return $this;
    }

    public function migrateTwitterUsername()
    {
        $twitterUsername = \WHMCS\Config\Setting::getValue("TwitterUsername");
        if ($twitterUsername) {
            (new \WHMCS\Social\SocialAccounts())->save(["twitter" => $twitterUsername]);
        }
        return $this;
    }

    public function markPreExistingAdminsDuoConfig()
    {
        $duoAdmins = \WHMCS\User\Admin::where("authmodule", "duosecurity")->get();
        if (static::$startVersion) {
            $existingAdminIdIsUsername = \WHMCS\Version\SemanticVersion::compare(static::$startVersion, new \WHMCS\Version\SemanticVersion("8.1.0-alpha.1"), "<");
        }
        foreach ($duoAdmins as $admin) {
            $config = $admin->getSecondFactorConfig();
            if (!isset($config["duo_auth_identifier"])) {
                $config["duo_auth_identifier"] = $existingAdminIdIsUsername ? "username" : "email";
                $admin->setSecondFactorConfig($config);
                $admin->save();
            }
        }
        return $this;
    }
}
