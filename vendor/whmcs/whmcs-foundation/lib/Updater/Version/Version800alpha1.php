<?php

namespace WHMCS\Updater\Version;

class Version800alpha1 extends IncrementalVersion implements \WHMCS\Scheduling\Contract\JobInterface
{
    use \WHMCS\Scheduling\Jobs\JobTrait;
    protected $updateActions = ["removeUnusedLegacyModules", "toggleAutoAuthOffForDeprecation", "createEncryptPasswordsJob", "mergeIndianStates", "enableCreateAddFundsInvoicePermission", "encryptGatewaySettings", "createUserAuthTables", "createNewUserTemplates", "createNewInviteTemplates", "dropSslProvisionDate", "migrateLastCronInvocationTime", "convertMailSettings", "autoSetProductGroupSlugs", "implementUniqueConstraintForConfiguration", "removeUnusedOrderForms", "convertPPCOTokens", "migrateV4AdminUsersToBlend", "createCampaignsTable", "registerNewEmailCronTasks", "migrateClientsToUsers", "migrateSubaccountsToUsers", "convertLegacyActivityLogAdminEntries"];
    const JOB_NAME = "customfieldvalue.password.encrypt";

    public function __construct(\WHMCS\Version\SemanticVersion $version = NULL)
    {
        if ($version) {
            parent::__construct($version);
            $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "whmcs" . DIRECTORY_SEPARATOR . "whmcs-foundation" . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "Mail.php";
            $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "php-imap";
            $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "pipe";
            $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "phlylabs";
            $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "social", "twitter"]);
            $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "vendor", "whmcs", "whmcs-foundation", "lib", "Cron", "Task", "DomainExpirySync.php"]);
            $useCountOfV4 = \WHMCS\Database\Capsule::table("tbladmins")->where("template", "v4")->count();
            if ($useCountOfV4 === 0) {
                $config = \DI::make("config");
                $adminDir = $config::DEFAULT_ADMIN_FOLDER;
                if ($config->customadminpath) {
                    $adminDir = $config->customadminpath;
                }
                $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . $adminDir . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "v4";
            }
            $config = \DI::make("config");
            $adminDir = $config::DEFAULT_ADMIN_FOLDER;
            if ($config->customadminpath) {
                $adminDir = $config->customadminpath;
            }
            $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, $adminDir, "templates", "blend", "menu.tpl"]);
            $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "vendor", "symfony", "translation", "TranslatorInterface.php"]);
        }
    }

    public function getUnusedLegacyModules()
    {
        return ["gateways" => ["paymateau", "paymatenz"], "servers" => ["gamecp"], "support" => ["kayako"]];
    }

    public function removeUnusedLegacyModules()
    {
        (new \WHMCS\Module\LegacyModuleCleanup())->removeModulesIfInstalledAndUnused($this->getUnusedLegacyModules());
        return $this;
    }

    public function toggleAutoAuthOffForDeprecation()
    {
        \WHMCS\Config\Setting::setValue(\WHMCS\Authentication\Client::SETTING_ALLOW_AUTOAUTH, 0);
        return $this;
    }

    public function createEncryptPasswordsJob()
    {
        try {
            \WHMCS\Scheduling\Jobs\Queue::useOriginSchema();
            \WHMCS\Scheduling\Jobs\Queue::addOrUpdate(static::JOB_NAME, static::class, "encryptPasswordCustomFields", [0]);
        } finally {
            \WHMCS\Scheduling\Jobs\Queue::resetSchemaVersion();
        }
    }

    public function mergeIndianStates()
    {
        \WHMCS\User\Client::whereIn("state", ["Dadra and Nagar Haveli", "Daman and Diu"])->update(["state" => "Dadra and Nagar Haveli and Daman and Diu"]);
        return $this;
    }

    public function enableCreateAddFundsInvoicePermission()
    {
        $permissionId = \WHMCS\User\Admin\Permission::findId("Create Invoice");
        $rolesToUpdate = [];
        if ($permissionId) {
            $roles = \WHMCS\Database\Capsule::table("tbladminperms")->select("roleid")->where("permid", $permissionId)->get();
            foreach ($roles as $role) {
                if (!array_key_exists($role->roleid, $rolesToUpdate)) {
                    $rolesToUpdate[$role->roleid] = ["roleid" => $role->roleid, "permid" => 149];
                }
            }
            if ($rolesToUpdate) {
                \WHMCS\Database\Capsule::table("tbladminperms")->insert($rolesToUpdate);
            }
        }
        return $this;
    }

    public function encryptGatewaySettings()
    {
        $settings = \WHMCS\Module\GatewaySetting::all();
        foreach ($settings as $setting) {
            $setting->encryptAndSavePlainTextValue();
        }
        return $this;
    }

    public function createUserAuthTables()
    {
        (new \WHMCS\User\Relations\UserClient())->createTable();
        (new \WHMCS\User\User())->createTable();
        (new \WHMCS\User\User\UserInvite())->createTable();
    }

    public function dropSslProvisionDate()
    {
        if (\WHMCS\Database\Capsule::schema()->hasColumn("tblsslorders", "provisiondate")) {
            \WHMCS\Database\Capsule::schema()->table("tblsslorders", function ($table) {
                $table->dropColumn("provisiondate");
            });
        }
        return $this;
    }

    public function createNewUserTemplates()
    {
        $templateTitle = "Email Address Verification";
        if (!\WHMCS\Mail\Template::where("name", $templateTitle)->exists()) {
            $message = "<p>Welcome to {\$company_name}</p>\n<p>Please click on the link below to verify your email address. This is required to confirm ownership of the email address.</p>\n<p><a href=\"{\$verification_url}\">Verify your email address</a></p>\n<p>If you're having trouble, try copying and pasting the following URL into your browser:<br>{\$verification_url}</p>\n<p>This link is valid for 60 minutes only. If it has expired, log in to our <a href=\"{\$whmcs_url}\">client area</a> to request a new link.</p>\n<p>{\$signature}</p>";
            $template = new \WHMCS\Mail\Template();
            $template->type = "user";
            $template->name = $templateTitle;
            $template->subject = $templateTitle;
            $template->message = $message;
            $template->save();
        }
        $templateTitle = "Password Reset Validation";
        if (!\WHMCS\Mail\Template::where("name", $templateTitle)->exists()) {
            $message = "<p>To reset your password, please click on the link below.</p>\n<p><a href=\"{\$reset_password_url}\">Reset your password</a></p>\n<p>If you're having trouble, try copying and pasting the following URL into your browser:<br>{\$reset_password_url}</p>\n<p>If you did not request this reset, you can ignore this email. It will expire in 2 hours.</p>\n<p>{\$signature}</p>";
            $template = new \WHMCS\Mail\Template();
            $template->type = "user";
            $template->name = $templateTitle;
            $template->subject = "Reset your password";
            $template->message = $message;
            $template->save();
        }
        $templateTitle = "Password Reset Confirmation";
        if (!\WHMCS\Mail\Template::where("name", $templateTitle)->exists()) {
            $message = "<p>Your password has been reset.</p>\n<p>If you did not request this reset, please <a href=\"{\$whmcs_url}\">contact us</a>.</p>\n<p>{\$signature}</p>";
            $template = new \WHMCS\Mail\Template();
            $template->type = "user";
            $template->name = $templateTitle;
            $template->subject = "Your password has been reset.";
            $template->message = $message;
            $template->save();
        }
        return $this;
    }

    public function createNewInviteTemplates()
    {
        $templateTitle = "Account Access Invitation";
        if (!\WHMCS\Mail\Template::where("name", $templateTitle)->exists()) {
            $message = "\n<h2>You've been given access to {\$invite_account_name}.</h2>\n<p>{if \$invite_sent_by_admin}An agent of {\$company_name}{else}{\$invite_sender_name}{/if} has given you access to the {\$invite_account_name} account with {\$company_name}.</p>\n<p>To accept the invite, please click on the link below.</p>\n<p><a href=\"{\$invite_accept_url}\">Accept invitation</a></p>\n<p>Invitations are valid for 7 days from the time of issue. After that time, you will need to request a new invitation from the account administrator.</p>\n<p>{\$signature}</p>";
            $template = new \WHMCS\Mail\Template();
            $template->type = "invite";
            $template->name = $templateTitle;
            $template->subject = "{if \$invite_sent_by_admin}An agent of {\$company_name} has sent you an invite{else}{\$invite_sender_name} has invited you to their account{/if}";
            $template->message = $message;
            $template->save();
        }
    }

    public static function encryptPasswordCustomFields($offset)
    {
        $limit = 100;
        $customFields = \WHMCS\CustomField::with("CustomFieldValues")->where("fieldType", "password")->skip($offset)->take($limit)->get();
        if ($customFields->count() === 0) {
            \WHMCS\Scheduling\Jobs\Queue::remove(static::JOB_NAME);
        } else {
            foreach ($customFields as $customField) {
                foreach ($customField->customFieldValues as $customFieldValue) {
                    $storedValue = $customFieldValue->getRawAttribute("value");
                    if (decrypt($customFieldValue) !== "") {
                        $customFieldValue->value = $storedValue;
                        $customFieldValue->save();
                    }
                }
            }
            \WHMCS\Scheduling\Jobs\Queue::addOrUpdate(static::JOB_NAME, static::class, "encryptPasswordCustomFields", [$offset + $limit]);
        }
    }

    public function migrateLastCronInvocationTime()
    {
        $transientData = \WHMCS\TransientData::getInstance();
        $lastCronInvocationTime = $transientData->retrieve("lastCronInvocationTime");
        if ($lastCronInvocationTime) {
            \WHMCS\Config\Setting::setValue("lastCronInvocationTime", $lastCronInvocationTime);
        }
    }

    public function convertPPCOTokens()
    {
        $gatewaySettings = \WHMCS\Module\GatewaySetting::getForGateway("paypalcheckout");
        if (!empty($gatewaySettings) && $gatewaySettings["sandbox"]) {
            if ($gatewaySettings["clientId"]) {
                \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", "sandboxClientId", $gatewaySettings["clientId"]);
                \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", "clientId", "");
                if ($gatewaySettings["accessToken-" . md5($gatewaySettings["clientId"])]) {
                    \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", "accessToken-sandbox-" . md5($gatewaySettings["clientId"]), $gatewaySettings["accessToken-" . md5($gatewaySettings["clientId"])]);
                    \WHMCS\Database\Capsule::table("tblpaymentgateways")->where("gateway", "paypalcheckout")->where("setting", "accessToken-" . md5($gatewaySettings["clientId"]))->delete();
                }
            }
            if ($gatewaySettings["clientSecret"]) {
                \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", "sandboxClientSecret", $gatewaySettings["clientSecret"]);
                \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", "clientSecret", "");
            }
            if ($webhookId = \WHMCS\Config\Setting::getValue("PayPalCheckoutWebhookId")) {
                \WHMCS\Config\Setting::setValue("PayPalCheckoutSandboxWebhookId", $webhookId);
                \WHMCS\Config\Setting::setValue("PayPalCheckoutWebhookId", "");
            }
        } else {
            if (!empty($gatewaySettings) && $gatewaySettings["accessToken-" . md5($gatewaySettings["clientId"])]) {
                \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", "accessToken-production-" . md5($gatewaySettings["clientId"]), $gatewaySettings["accessToken-" . md5($gatewaySettings["clientId"])]);
                \WHMCS\Database\Capsule::table("tblpaymentgateways")->where("gateway", "paypalcheckout")->where("setting", "accessToken-" . md5($gatewaySettings["clientId"]))->delete();
            }
        }
        return $this;
    }

    protected function convertMailSettings()
    {
        $mailType = \WHMCS\Config\Setting::getValue("MailType");
        $mailConfig = ["module" => "PhpMail", "configuration" => ["encoding" => \WHMCS\Config\Setting::getValue("MailEncoding")]];
        if ($mailType == "smtp") {
            $mailConfig["module"] = "SmtpMail";
            $mailConfig["configuration"]["host"] = \WHMCS\Input\Sanitize::decode(\WHMCS\Config\Setting::getValue("SMTPHost"));
            $mailConfig["configuration"]["port"] = \WHMCS\Input\Sanitize::decode(\WHMCS\Config\Setting::getValue("SMTPPort"));
            $mailConfig["configuration"]["username"] = \WHMCS\Input\Sanitize::decode(\WHMCS\Config\Setting::getValue("SMTPUsername"));
            $mailConfig["configuration"]["password"] = \WHMCS\Input\Sanitize::decode(decrypt(\WHMCS\Config\Setting::getValue("SMTPPassword")));
            $secure = \WHMCS\Config\Setting::getValue("SMTPSSL");
            if (!$secure) {
                $secure = "none";
            }
            $mailConfig["configuration"]["secure"] = $secure;
        }
        \WHMCS\Config\Setting::setValue("MailConfig", encrypt(json_encode($mailConfig)));
        \WHMCS\Config\Setting::whereIn("setting", ["MailEncoding", "SMTPHost", "SMTPPort", "SMTPUsername", "SMTPPassword", "SMTPSSL"])->delete();
        return $this;
    }

    public function autoSetProductGroupSlugs()
    {
        \WHMCS\Product\Group::withoutEvents(function () {
            foreach (\WHMCS\Product\Group::all() as $group) {
                if (empty($group->slug) && !empty($group->name)) {
                    $slug = $group->autoGenerateUniqueSlug();
                    try {
                        $isValidFormat = $group->validateSlugFormat($slug);
                    } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
                        $isValidFormat = false;
                    }
                    $group->slug = $isValidFormat ? $slug : "";
                    $group->save();
                }
            }
        });
    }

    protected function implementUniqueConstraintForConfiguration()
    {
        try {
            $duplicates = \WHMCS\Database\Capsule::table("tblconfiguration")->select(["setting"])->selectRaw("min(`id`) as `lowestid`, count(`setting`) as `duplicatecount`")->groupBy("setting")->having("duplicatecount", ">", 1)->get();
            foreach ($duplicates as $duplicate) {
                \WHMCS\Database\Capsule::table("tblconfiguration")->where("setting", "=", $duplicate->setting)->where("id", "!=", $duplicate->lowestid)->delete();
            }
            $config = \DI::make("config");
            $columnTypeQuery = "SELECT t.TABLE_NAME, c.COLUMN_NAME, c.DATA_TYPE, c.COLUMN_TYPE FROM information_schema.tables AS t LEFT JOIN information_schema.columns AS c ON (t.TABLE_NAME = c.TABLE_NAME) " . "WHERE t.TABLE_SCHEMA = '" . $config->getDatabaseName() . "'" . "AND t.TABLE_NAME = 'tblconfiguration'" . "AND c.COLUMN_NAME = 'setting';";
            $columnType = (string) \WHMCS\Database\Capsule::select(\WHMCS\Database\Capsule::raw($columnTypeQuery))[0]->COLUMN_TYPE;
            $indexes = collect(\WHMCS\Database\Capsule::select("SHOW INDEXES FROM tblconfiguration"))->pluck("Key_name");
            $indexExists = $indexes->contains("whmcs_setting_unique");
            $keyExists = $indexes->contains("setting");
            if ($columnType !== "varchar(64)") {
                $query = "ALTER TABLE tblconfiguration MODIFY setting VARCHAR(64) NOT NULL";
                \WHMCS\Database\Capsule::statement($query);
            }
            if ($keyExists) {
                \WHMCS\Database\Capsule::statement("DROP INDEX setting on tblconfiguration");
                \WHMCS\Database\Capsule::statement("ALTER TABLE tblconfiguration ADD KEY setting (setting(64))");
            }
            if (!$indexExists) {
                $query = "CREATE UNIQUE INDEX whmcs_setting_unique ON tblconfiguration (setting)";
                \WHMCS\Database\Capsule::statement($query);
            }
        } catch (\Error $e) {
            logActivity("Updater Error: " . $e->getMessage());
        } catch (\Exception $e) {
            logActivity("Updater Error: " . $e->getMessage());
        }
    }

    protected function removeUnusedOrderForms()
    {
        $templates = ["boxes", "modern"];
        $currentDefault = \WHMCS\Config\Setting::getValue("OrderFormTemplate");
        $boxesRemove = $modernRemove = true;
        if (in_array($currentDefault, $templates)) {
            \WHMCS\Config\Setting::setValue("OrderFormTemplate", "legacy_" . $currentDefault);
            $var = $currentDefault . "Remove";
            ${$var} = false;
        }
        $productGroups = \WHMCS\Product\Group::whereIn("orderfrmtpl", $templates)->get();
        foreach ($productGroups as $productGroup) {
            $template = $productGroup->orderFormTemplate;
            $var = $template . "Remove";
            if (${$var} === true) {
                ${$var} = false;
            }
            $productGroup->orderFormTemplate = "legacy_" . $template;
            $productGroup->save();
        }
        if ($boxesRemove) {
            $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "orderforms" . DIRECTORY_SEPARATOR . "boxes";
        }
        if ($modernRemove) {
            $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "orderforms" . DIRECTORY_SEPARATOR . "modern";
        }
    }

    protected function migrateV4AdminUsersToBlend()
    {
        $adminUsers = \WHMCS\Database\Capsule::table("tbladmins")->where("template", "v4");
        if (0 < $adminUsers->count()) {
            $admins = $adminUsers->pluck("id");
            $adminUsers->update(["template" => "blend"]);
            $adminList = [];
            $dateTime = \WHMCS\Carbon::now()->toDateTimeString();
            foreach ($admins as $adminId) {
                $adminList[] = ["id" => $adminId, "dateTime" => $dateTime, "dismissed" => false];
            }
            \WHMCS\TransientData::getInstance()->store("migratedV4AdminUsersToBlend", json_encode($adminList), 1814400);
        }
        return $this;
    }

    public function createCampaignsTable()
    {
        (new \WHMCS\Mail\Campaign())->createTable();
        return $this;
    }

    public function registerNewEmailCronTasks()
    {
        \WHMCS\Cron\Task\EmailCampaigns::register();
        \WHMCS\Cron\Task\ProcessEmailQueue::register();
        return $this;
    }

    public function migrateClientsToUsers()
    {
        try {
            $query = "INSERT IGNORE INTO tblusers (id,first_name,last_name,email,password,language,second_factor,second_factor_config,security_question_id,security_question_answer,last_ip,last_hostname,last_login,email_verified_at,created_at,updated_at) SELECT NULL,c.firstname,c.lastname,c.email,c.password,c.language,c.authmodule,c.authdata,c.securityqid,c.securityqans,c.ip,c.host,c.lastlogin,IF(c.email_verified<>0,NOW(),NULL) as email_verified_at,c.datecreated,c.updated_at FROM tblclients c LEFT JOIN tblusers u ON CONVERT(c.email USING utf8) COLLATE utf8_unicode_ci = u.email WHERE u.id IS NULL";
            \WHMCS\Database\Capsule::statement($query);
            $query = "INSERT IGNORE INTO tblusers_clients (auth_user_id,client_id,owner,last_login,created_at,updated_at) SELECT u.id,c.id,'1',lastlogin,NOW(),NOW() FROM tblclients c LEFT JOIN tblusers u ON CONVERT(c.email USING utf8) COLLATE utf8_unicode_ci = u.email WHERE u.id IS NOT NULL";
            \WHMCS\Database\Capsule::statement($query);
            $query = "UPDATE tblauthn_account_links SET user_id=client_id,client_id = NULL WHERE client_id IS NOT NULL and contact_id IS NULL";
            \WHMCS\Database\Capsule::statement($query);
        } catch (\Exception $e) {
        }
        return $this;
    }

    public function migrateSubaccountsToUsers()
    {
        \WHMCS\User\Client\Contact::legacySubaccount()->chunkById(100, function ($contacts) {
            foreach ($contacts as $contact) {
                try {
                    $firstName = $contact->firstName ?: "undefined";
                    $lastName = $contact->lastName ?: "undefined";
                    $password = $contact->passwordHash ?: hash("sha256", \Illuminate\Support\Str::random(16) . $contact->id . $contact->email);
                    $user = \WHMCS\User\User::createUser($firstName, $lastName, $contact->email, $password, $contact->client ? $contact->client->language : NULL, true, true);
                    if ($contact->passwordHash) {
                        $user->password = $contact->passwordHash;
                        $user->save();
                    }
                    if ($contact->client) {
                        $user->clients()->attach($contact->client->id, ["permissions" => $contact->getRawAttribute("permissions")]);
                    }
                    if (0 < $contact->remoteAccountLinks()->count()) {
                        $contact->remoteAccountLinks()->update(["user_id" => $user->id, "client_id" => NULL, "contact_id" => NULL]);
                    }
                    $contact->isSubAccount = false;
                    $contact->permissions = [];
                    $contact->passwordHash = \Illuminate\Support\Str::random(64);
                    $contact->save();
                } catch (\WHMCS\Exception\User\EmailAlreadyExists $e) {
                } catch (\Throwable $e) {
                }
            }
        });
        return $this;
    }

    public function getFeatureHighlights()
    {
        $utmString = "?utm_source=in-product&utm_medium=whatsnew80";
        return [new \WHMCS\Notification\FeatureHighlight("Users and <span>Client Accounts</span>", "The new way to log in and share access", NULL, "ico-splash-users.png", "Introducing a new concept for end user login management that gives account holders more control and users with access to multiple accounts greater flexibility and convenience.", "https://docs.whmcs.com/Users_and_Accounts" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>New Look</span> Admin Area", "With improved mobile and tablet support", NULL, "ico-splash-ui.png", "Version 8.0 introduces a new look that maximises focus on content and reduces distractions, while also delivering a number of usability improvements.", "https://docs.whmcs.com/New_Look_Admin_Area", "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>Email</span> Delivery Providers", "For increased email reliability and deliverability", NULL, "ico-splash-mail.png", "Email deliverability is vitally important. Choose from a range of providers, including MailGun, SparkPost, and SendGrid, to help ensure your emails reach your customers' inboxes.", "https://docs.whmcs.com/Mail_Providers" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Improved <span>IDN Support</span>", "Accept orders for a broader range of domain names with full international support", NULL, "ico-splash-domain.png", "Improvements include automatic registration support for Enom and ResellerClub, more robust IDN validation, usability enhancements, and more.", "https://docs.whmcs.com/IDN_Support" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Improved <span>Currency Support</span>", "Support for 3 decimal places in tax rates", NULL, "ico-splash-currency.png", "Now supporting tax rates with up to three decimal place precision and support for larger currency values up to 99 trillion.", "https://docs.whmcs.com/Currency_Support" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>OAuth2</span> for SMTP", "Fully ready for upcoming changes", NULL, "ico-splash-oauth.png", "Some of the big hosted email providers are moving away from traditional password-based authentication for their email services. With WHMCS version 8.0, you'll be ready for when they do.", "https://docs.whmcs.com/OAuth2_for_SMTP" . $utmString, "Learn More")];
    }

    public function convertLegacyActivityLogAdminEntries()
    {
        $admins = \WHMCS\Database\Capsule::table("tbladmins")->get(["id", "username"]);
        foreach ($admins as $admin) {
            \WHMCS\Database\Capsule::table("tblactivitylog")->where("user", $admin->username)->update(["admin_id" => $admin->id]);
        }
    }
}
