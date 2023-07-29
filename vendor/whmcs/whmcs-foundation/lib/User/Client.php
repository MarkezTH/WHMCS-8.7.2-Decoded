<?php

namespace WHMCS\User;

class Client extends AbstractUser implements Contracts\ContactInterface, UserInterface
{
    use Traits\EmailPreferences;
    use Traits\User;
    protected $table = "tblclients";
    protected $columnMap = ["passwordHash" => "password", "twoFactorAuthModule" => "authmodule", "twoFactorAuthData" => "authdata", "currencyId" => "currency", "defaultPaymentGateway" => "defaultgateway", "overrideLateFee" => "latefeeoveride", "overrideOverdueNotices" => "overideduenotices", "disableAutomaticCreditCardProcessing" => "disableautocc", "billingContactId" => "billingcid", "creditCardType" => "cardtype", "creditCardLastFourDigits" => "cardlastfour", "creditCardExpiryDate" => "expdate", "storedBankNameCrypt" => "bankname", "storedBankTypeCrypt" => "banktype", "storedBankCodeCrypt" => "bankcode", "storedBankAccountCrypt" => "bankacct", "paymentGatewayToken" => "gatewayid", "lastLoginDate" => "lastlogin", "lastLoginIp" => "ip", "lastLoginHostname" => "host", "passwordResetKey" => "pwresetkey", "passwordResetKeyRequestDate" => "pwresetexpiry", "passwordResetKeyExpiryDate" => "pwresetexpiry"];
    public $timestamps = true;
    protected $dates = ["lastLoginDate", "passwordResetKeyRequestDate", "passwordResetKeyExpiryDate"];
    protected $booleans = ["taxExempt", "overrideLateFee", "overrideOverdueNotices", "separateInvoices", "disableAutomaticCreditCardProcessing", "emailOptOut", "marketingEmailsOptIn", "overrideAutoClose", "emailVerified"];
    protected $casts = ["email_preferences" => "array"];
    public $unique = ["email"];
    protected $appends = ["fullName", "displayName", "countryName", "groupName", "currencyCode", "displayNameFormatted"];
    protected $fillable = ["lastlogin", "ip", "host", "pwresetkey", "pwresetexpiry"];
    protected $hidden = ["password", "authdata", "securityqans", "cardnum", "startdate", "expdate", "issuenumber", "bankname", "banktype", "bankcode", "bankacct", "pwresetkey", "pwresetexpiry"];
    const STATUS_ACTIVE = "Active";
    const STATUS_INACTIVE = "Inactive";
    const STATUS_CLOSED = "Closed";
    const PAYMENT_DATA_MIGRATED = "--MIGRATED--";

    public function users()
    {
        return $this->belongsToMany("WHMCS\\User\\User", "tblusers_clients", "client_id", "auth_user_id")->using("WHMCS\\User\\Relations\\UserClient")->withTimestamps()->withPivot(["owner", "permissions", "last_login"]);
    }

    public function usersRelation()
    {
        return $this->hasMany("WHMCS\\User\\Relations\\UserClient", "client_id");
    }

    public function getUserIds()
    {
        return $this->users()->pluck("tblusers.id")->toArray();
    }

    public function owner()
    {
        return $this->users()->withoutGlobalScope("order")->where("owner", 1)->first();
    }

    public function invites()
    {
        return $this->hasMany("WHMCS\\User\\User\\UserInvite", "client_id");
    }

    public function domains()
    {
        return $this->hasMany("WHMCS\\Domain\\Domain", "userid");
    }

    public function services()
    {
        return $this->hasMany("WHMCS\\Service\\Service", "userid");
    }

    public function addons()
    {
        return $this->hasMany("WHMCS\\Service\\Addon", "userid");
    }

    public function contacts()
    {
        return $this->hasMany("WHMCS\\User\\Client\\Contact", "userid");
    }

    public function billingContact()
    {
        return $this->hasOne("WHMCS\\User\\Client\\Contact", "id", "billingcid");
    }

    public function quotes()
    {
        return $this->hasMany("WHMCS\\Billing\\Quote", "userid");
    }

    public function affiliate()
    {
        return $this->hasOne("WHMCS\\User\\Client\\Affiliate", "clientid");
    }

    public function invoices()
    {
        return $this->hasMany("WHMCS\\Billing\\Invoice", "userid");
    }

    public function transactions()
    {
        return $this->hasMany("WHMCS\\Billing\\Payment\\Transaction", "userid");
    }

    public function group()
    {
        return $this->hasOne("WHMCS\\User\\Client\\Group", "id", "groupid");
    }

    public function formatter($Client)
    {
        return new \WHMCS\View\Admin\Formatter\Client($this);
    }

    public function remoteAccountLinks()
    {
        $relation = $this->hasMany("WHMCS\\Authentication\\Remote\\AccountLink", "client_id");
        $relation->getQuery()->whereNull("contact_id");
        return $relation;
    }

    public function orders()
    {
        return $this->hasMany("WHMCS\\Order\\Order", "userid");
    }

    public function marketingConsent()
    {
        return $this->hasMany("WHMCS\\Marketing\\Consent", "userid");
    }

    public function currencyrel()
    {
        return $this->hasOne("WHMCS\\Billing\\Currency", "id", "currency");
    }

    public function scopeEmail($query, $email)
    {
        return $query->where("email", $email);
    }

    public function isOwnedBy($user)
    {
        return $this->owner()->id === $user->id;
    }

    public function getAutheduserAttribute()
    {
        $response = new \Stdclass();
        if ($this->authedUserIsOwner()) {
            $response->owner = true;
        } else {
            $response->owner = false;
        }
        return $response;
    }

    public function runPostLoginEvents()
    {
        try {
            $this->migratePaymentDetailsIfRequired();
        } catch (\Exception $e) {
            $this->logActivity("Automatic client payment data migration failed on login: " . $e->getMessage());
        }
    }

    public static function getStatuses()
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_CLOSED];
    }

    public function hasDomain($domainName)
    {
        $domainCount = $this->domains()->where("domain", "=", $domainName)->count();
        if (0 < $domainCount) {
            return true;
        }
        $serviceDomainCount = $this->services()->where("domain", "=", $domainName)->count();
        return 0 < $serviceDomainCount;
    }

    protected function generateCreditCardEncryptionKey()
    {
        $config = \Config::self();
        return md5($config["cc_encryption_hash"] . $this->id);
    }

    public function getAlerts(Client\AlertFactory $factory = NULL)
    {
        if (is_null($alerts)) {
            if (is_null($factory)) {
                $factory = new Client\AlertFactory($this);
            }
            $alerts = $factory->build();
        }
        return $alerts;
    }

    public function isCreditCardExpiring($withinMonths = 2)
    {
        if (!function_exists("getClientDefaultCardDetails")) {
            require_once ROOTDIR . "/includes/ccfunctions.php";
        }
        $cardDetails = getClientDefaultCardDetails($this->id);
        if (empty($cardDetails["expdate"])) {
            return false;
        }
        unset($cardDetails["fullcardnum"]);
        $expiryDate = str_replace("/", "", $cardDetails["expdate"]);
        if (!is_numeric($expiryDate) || strlen($expiryDate) != 4) {
            return false;
        }
        $isExpiring = \WHMCS\Carbon::createFromFormat("dmy", "01" . $expiryDate)->diffInMonths(\WHMCS\Carbon::now()->startOfMonth()) <= $withinMonths;
        if ($isExpiring) {
            return $cardDetails;
        }
        return false;
    }

    public function getPhoneNumberFormattedAttribute()
    {
        $phoneUtil = new \WHMCS\Utility\Phone($this->phonenumber, $this->country);
        return $phoneUtil->getTelephoneNumber();
    }

    public function getDisplayNameAttribute()
    {
        $name = $this->companyName;
        if ($name) {
            $name .= " (" . $this->fullName . ")";
        } else {
            $name .= $this->fullName;
        }
        return $name;
    }

    public function getCountryNameAttribute()
    {
        if (is_null($countries)) {
            $countries = new \WHMCS\Utility\Country();
        }
        return $countries->getName($this->country);
    }

    public function generateCreditCardEncryptedField($value)
    {
        return $this->encryptValue($value, $this->generateCreditCardEncryptionKey());
    }

    public function getUsernameAttribute()
    {
        return $this->email;
    }

    public function hasSingleSignOnPermission()
    {
        return (bool) $this->allowSso;
    }

    public function isAllowedToAuthenticate()
    {
        return $this->status != "Closed";
    }

    public function isEmailAddressVerified()
    {
        $user = $this->owner();
        if ($user) {
            return $user->emailVerified();
        }
        return false;
    }

    public function updateLastLogin(\WHMCS\Carbon $time = NULL, $ip = NULL, $host = NULL)
    {
        if (!$time) {
            $time = \WHMCS\Carbon::now();
        }
        if (!$ip) {
            $ip = $this->currentIp();
        }
        if (!$host) {
            $host = $this->currentHostname();
        }
        $this->update(["lastlogin" => (string) $time->format("YmdHis"), "ip" => $ip, "host" => $host, "pwresetkey" => "", "pwresetexpiry" => 0]);
    }

    public function customFieldValues()
    {
        return $this->hasMany("WHMCS\\CustomField\\CustomFieldValue", "relid");
    }

    protected function getCustomFieldType()
    {
        return "client";
    }

    protected function getCustomFieldRelId()
    {
        return 0;
    }

    public function hasPermission($permission)
    {
        throw new \RuntimeException("WHMCS\\User\\Client::hasPermission not implemented");
    }

    public function tickets()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket", "userid");
    }

    public function isOptedInToMarketingEmails()
    {
        if (\WHMCS\Marketing\EmailSubscription::isUsingOptInField()) {
            return (bool) $this->marketingEmailsOptIn;
        }
        return !(bool) $this->emailOptOut;
    }

    public function marketingEmailOptIn($userIp = "", $performCurrentSettingCheck = true)
    {
        if ($performCurrentSettingCheck && $this->isOptedInToMarketingEmails()) {
            throw new \WHMCS\Exception\Marketing\AlreadyOptedIn();
        }
        $this->emailOptOut = false;
        $this->marketingEmailsOptIn = true;
        $this->save();
        \WHMCS\Marketing\Consent::logOptIn($this->id, $userIp);
        $this->logActivity("Opted In to Marketing Emails");
        return $this;
    }

    public function marketingEmailOptOut($userIp = "", $performCurrentSettingCheck = true)
    {
        if ($performCurrentSettingCheck && !$this->isOptedInToMarketingEmails()) {
            throw new \WHMCS\Exception\Marketing\AlreadyOptedOut();
        }
        $this->emailOptOut = true;
        $this->marketingEmailsOptIn = false;
        $this->save();
        \WHMCS\Marketing\Consent::logOptOut($this->id, $userIp);
        $this->logActivity("Opted Out from Marketing Emails");
        return $this;
    }

    public function logActivity($message)
    {
        logActivity($message, $this->id, ["withClientId" => true]);
        return $this;
    }

    public function deleteEntireClient()
    {
        $userid = $this->id;
        run_hook("PreDeleteClient", ["userid" => $userid]);
        delete_query("tblcontacts", ["userid" => $userid]);
        $tblhostingIds = \WHMCS\Database\Capsule::table("tblhosting")->where("userid", $userid)->pluck("id")->all();
        if (!empty($tblhostingIds)) {
            \WHMCS\Database\Capsule::table("tblhostingconfigoptions")->whereIn("relid", $tblhostingIds)->delete();
        }
        $result = select_query("tblcustomfields", "id", ["type" => "client"]);
        while ($data = mysql_fetch_array($result)) {
            $customfieldid = $data["id"];
            delete_query("tblcustomfieldsvalues", ["fieldid" => $customfieldid, "relid" => $userid]);
        }
        $result = select_query("tblcustomfields", "id,relid", ["type" => "product"]);
        while ($data = mysql_fetch_array($result)) {
            $customfieldid = $data["id"];
            $customfieldpid = $data["relid"];
            $result2 = select_query("tblhosting", "id", ["userid" => $userid, "packageid" => $customfieldpid]);
            while ($data = mysql_fetch_array($result2)) {
                $hostingid = $data["id"];
                delete_query("tblcustomfieldsvalues", ["fieldid" => $customfieldid, "relid" => $hostingid]);
            }
        }
        $addonCustomFields = \WHMCS\Database\Capsule::table("tblcustomfields")->where("type", "addon")->get(["id", "relid"])->all();
        foreach ($addonCustomFields as $addonCustomField) {
            $customFieldId = $addonCustomField->id;
            $customFieldAddonId = $addonCustomField->relid;
            $hostingAddons = \WHMCS\Database\Capsule::table("tblhostingaddons")->where("userid", $userid)->where("addonid", $customFieldAddonId)->pluck("id")->all();
            foreach ($hostingAddons as $hostingAddon) {
                $addonId = $hostingAddon->id;
                \WHMCS\Database\Capsule::table("tblcustomfieldsvalues")->where("fieldid", $customFieldId)->where("relid", $addonId)->delete();
            }
        }
        $result = select_query("tblhosting", "id", ["userid" => $userid]);
        while ($data = mysql_fetch_array($result)) {
            $domainlistid = $data["id"];
            foreach (\WHMCS\Service\Addon::where("hostingid", $domainlistid)->get() as $addon) {
                $addon->delete();
            }
        }
        delete_query("tblorders", ["userid" => $userid]);
        delete_query("tblhosting", ["userid" => $userid]);
        delete_query("tbldomains", ["userid" => $userid]);
        delete_query("tblemails", ["userid" => $userid]);
        delete_query("tblinvoices", ["userid" => $userid]);
        delete_query("tblinvoiceitems", ["userid" => $userid]);
        $tickets = \WHMCS\Database\Capsule::table("tbltickets")->where("userid", $userid)->pluck("id")->all();
        foreach ($tickets as $ticketId) {
            try {
                if (!function_exists("deleteTicket")) {
                    require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
                }
                deleteTicket($ticketId);
            } catch (\WHMCS\Exception\Fatal $e) {
                $this->logActivity($e->getMessage());
                \WHMCS\Database\Capsule::table("tblticketreplies")->where("tid", $ticketId)->delete();
                \WHMCS\Database\Capsule::table("tbltickettags")->where("ticketid", $ticketId)->delete();
                \WHMCS\Database\Capsule::table("tblticketnotes")->where("ticketid", $ticketId)->delete();
                \WHMCS\Database\Capsule::table("tblticketlog")->where("tid", $ticketId)->delete();
                \WHMCS\Database\Capsule::table("tbltickets")->delete($ticketId);
            } catch (\Exception $e) {
            }
        }
        if ($this->affiliate) {
            $this->affiliate->delete();
        }
        delete_query("tblnotes", ["userid" => $userid]);
        delete_query("tblcredit", ["clientid" => $userid]);
        delete_query("tblactivitylog", ["userid" => $userid]);
        delete_query("tblsslorders", ["userid" => $userid]);
        delete_query("tblauthn_account_links", ["client_id" => $userid]);
        foreach ($this->payMethods as $payMethod) {
            $payMethod->forceDelete();
        }
        logActivity("Client Deleted - ID: " . $userid);
        $this->users()->detach();
        return $this->delete();
    }

    public static function getGroups()
    {
        if (is_null($groups)) {
            $groups = \WHMCS\Database\Capsule::table("tblclientgroups")->orderBy("groupname")->pluck("groupname", "id")->all();
        }
        return $groups;
    }

    public function needsCardDetailsMigrated()
    {
        if (trim($this->creditCardType)) {
            return $this->creditCardType !== self::PAYMENT_DATA_MIGRATED;
        }
        if (trim($this->creditCardLastFourDigits)) {
            return true;
        }
        if ($this->cardnum) {
            $cardNumDecrypted = $this->decryptValue($this->cardnum, $this->generateCreditCardEncryptionKey());
            if ($cardNumDecrypted && preg_match("/^[\\d]+\$/", $cardNumDecrypted)) {
                return true;
            }
        }
        return false;
    }

    public function needsBankDetailsMigrated()
    {
        $migrationMarker = $this->banktype;
        return $migrationMarker && $migrationMarker !== self::PAYMENT_DATA_MIGRATED;
    }

    public function needsUnknownPaymentTokenMigrated()
    {
        if (is_null($this->paymentGatewayToken)) {
            return false;
        }
        return trim($this->paymentGatewayToken) !== "";
    }

    public function needsAnyPaymentDetailsMigrated()
    {
        return $this->needsCardDetailsMigrated() || $this->needsBankDetailsMigrated() || $this->needsUnknownPaymentTokenMigrated();
    }

    public function migratePaymentDetailsIfRequired($forceInCron = false)
    {
        if (defined("IN_CRON") && !$forceInCron) {
            return NULL;
        }
        try {
            if ($this->needsAnyPaymentDetailsMigrated()) {
                $migration = new \WHMCS\Payment\PayMethod\MigrationProcessor();
                $migration->migrateForClient($this);
            }
        } catch (\Exception $e) {
            $this->logActivity("Pay Method migration failed. " . $e->getMessage());
        }
    }

    public function markCardDetailsAsMigrated()
    {
        $this->creditCardType = self::PAYMENT_DATA_MIGRATED;
        $this->save();
        return $this;
    }

    public function markBankDetailsAsMigrated()
    {
        $this->banktype = self::PAYMENT_DATA_MIGRATED;
        $this->save();
        return $this;
    }

    public function markPaymentTokenMigrated()
    {
        $this->paymentGatewayToken = "";
        $this->save();
        return $this;
    }

    public function payMethods()
    {
        return $this->hasMany("WHMCS\\Payment\\PayMethod\\Model", "userid");
    }

    public function defaultBillingContact()
    {
        if ($this->billingContactId) {
            return $this->belongsTo("WHMCS\\User\\Client\\Contact", "billingcid");
        }
        return $this->hasOne(static::class, "id");
    }

    public function getGroupNameAttribute()
    {
        $groupName = "";
        if ($this->groupId) {
            $groups = self::getGroups();
            if (array_key_exists($this->groupId, $groups)) {
                $groupName = $groups[$this->groupId];
            }
        }
        return $groupName;
    }

    public function domainSslStatuses()
    {
        return $this->hasMany("WHMCS\\Domain\\Ssl\\Status", "user_id");
    }

    public function generateUniquePlaceholderEmail()
    {
        return "autogen_" . (new \WHMCS\Utility\Random())->string(6, 0, 2, 0) . "@example.com";
    }

    public function deleteAllCreditCards()
    {
        $this->creditCardType = "";
        $this->creditCardLastFourDigits = "";
        $this->cardnum = "";
        $this->creditCardExpiryDate = "";
        $this->startdate = "";
        $this->issuenumber = "";
        $this->paymentGatewayToken = "";
        $this->save();
        foreach ($this->payMethods as $payMethod) {
            if ($payMethod->isCreditCard()) {
                $payMethod->delete();
            }
        }
    }

    public static function getUsedCardTypes()
    {
        $cardTypes = \WHMCS\Payment\PayMethod\Adapter\CreditCard::where("card_type", "!=", "")->distinct("card_type")->pluck("card_type")->toArray();
        $clientCardTypes = self::where("cardtype", "!=", "")->where("cardtype", "!=", self::PAYMENT_DATA_MIGRATED)->distinct("cardtype")->pluck("cardtype")->toArray();
        asort(array_unique(array_merge($cardTypes, $clientCardTypes)));
        return $cardTypes;
    }

    public function buildBillingContactsArray()
    {
        $billingContacts = [["id" => 0, "firstname" => $this->firstName, "lastname" => $this->lastName, "companyname" => $this->companyName, "email" => $this->email, "address1" => $this->address1, "address2" => $this->address2, "city" => $this->city, "state" => $this->state, "postcode" => $this->postcode, "country" => $this->country, "countryname" => $this->countryName, "phonenumber" => $this->phoneNumber]];
        foreach ($this->contacts as $contact) {
            $billingContacts[$contact->id] = ["id" => $contact->id, "firstname" => $contact->firstName, "lastname" => $contact->lastName, "companyname" => $contact->companyName, "email" => $contact->email, "address1" => $contact->address1, "address2" => $contact->address2, "city" => $contact->city, "state" => $contact->state, "postcode" => $contact->postcode, "country" => $contact->country, "countryname" => $contact->countryName, "phonenumber" => $contact->phoneNumber];
        }
        return $billingContacts;
    }

    public function createRemoteCardPayMethod($gateway, $cardNumber, $cardExpiryDate, $remoteToken, $billingContactId = "billing", $description = "", $cardType = NULL, $cardStartDate = NULL, $cardIssueNumber = NULL)
    {
        if (!$gateway instanceof \WHMCS\Module\Gateway) {
            try {
                $gateway = \WHMCS\Module\Gateway::factory($gateway);
            } catch (\WHMCS\Exception\Fatal $e) {
                throw new \WHMCS\Exception($e->getMessage());
            }
        }
        $billingContact = $this->billingContact;
        if ($billingContactId !== "billing" && is_numeric($billingContactId) && $billingContactId) {
            $billingContact = $this->contacts()->where("id", $billingContactId)->first();
        }
        if (!$billingContact) {
            $billingContact = $this;
        }
        $payMethod = \WHMCS\Payment\PayMethod\Adapter\RemoteCreditCard::factoryPayMethod($this, $billingContact, $description);
        $payMethod->setGateway($gateway);
        $payMethod->save();
        if (is_null($cardType)) {
            if (!function_exists("getCardTypeByCardNumber")) {
                require_once ROOTDIR . "/includes/ccfunctions.php";
            }
            $cardType = getCardTypeByCardNumber($cardNumber);
        }
        $newPayment = $payMethod->payment;
        $newPayment->setCardNumber($cardNumber)->setExpiryDate(\WHMCS\Carbon::createFromCcInput($cardExpiryDate))->setCardType($cardType)->setRemoteToken($remoteToken);
        if (!is_null($cardStartDate)) {
            $newPayment->setStartDate(\WHMCS\Carbon::createFromCcInput($cardStartDate));
        }
        if (!is_null($cardIssueNumber) && is_numeric($cardIssueNumber)) {
            $newPayment->setIssueNumber($cardIssueNumber);
        }
        $newPayment->validateRequiredValuesPreSave()->save();
        return $payMethod;
    }

    public function createCardPayMethod($cardNumber, $cardExpiryDate, $billingContactId = "billing", $description = "", $cardType = NULL, $cardStartDate = NULL, $cardIssueNumber = NULL)
    {
        $billingContact = $this->billingContact;
        if ($billingContactId !== "billing" && is_numeric($billingContactId) && $billingContactId) {
            $billingContact = $this->contacts()->where("id", $billingContactId)->first();
        }
        if (!$billingContact) {
            $billingContact = $this;
        }
        $payMethod = \WHMCS\Payment\PayMethod\Adapter\CreditCard::factoryPayMethod($this, $billingContact, $description);
        $newPayment = $payMethod->payment;
        $newPayment->setCardNumber($cardNumber)->setExpiryDate(\WHMCS\Carbon::createFromCcInput($cardExpiryDate))->setCardType($cardType);
        if (!is_null($cardStartDate)) {
            $newPayment->setStartDate(\WHMCS\Carbon::createFromCcInput($cardStartDate));
        }
        if (!is_null($cardIssueNumber) && is_numeric($cardIssueNumber)) {
            $newPayment->setIssueNumber($cardIssueNumber);
        }
        $newPayment->validateRequiredValuesPreSave()->save();
        return $payMethod;
    }

    public function createBankPayMethod($accountType, $routingNumber, $accountNumber, $bankName, $accountHolderName, $billingContactId = "billing", $description = "")
    {
        $billingContact = $this->billingContact;
        if ($billingContactId !== "billing" && is_numeric($billingContactId) && $billingContactId) {
            $billingContact = $this->contacts()->where("id", $billingContactId)->first();
        }
        if (!$billingContact) {
            $billingContact = $this;
        }
        $payMethod = \WHMCS\Payment\PayMethod\Adapter\BankAccount::factoryPayMethod($this, $billingContact, $description);
        $newPayment = $payMethod->payment;
        $newPayment->setAccountType($accountType)->setRoutingNumber($routingNumber)->setAccountNumber($accountNumber)->setBankName($bankName)->setAccountHolderName($accountHolderName)->validateRequiredValuesPreSave()->save();
        return $payMethod;
    }

    public function createRemoteBankPayMethod($gateway, $remoteToken, $accountNumber = "", $accountHolderName = "", $billingContactId = "billing", $description = "")
    {
        if (!$gateway instanceof \WHMCS\Module\Gateway) {
            try {
                $gateway = \WHMCS\Module\Gateway::factory($gateway);
            } catch (\WHMCS\Exception\Fatal $e) {
                throw new \WHMCS\Exception($e->getMessage());
            }
        }
        $billingContact = $this->billingContact;
        if ($billingContactId !== "billing" && is_numeric($billingContactId) && $billingContactId) {
            $billingContact = $this->contacts()->where("id", $billingContactId)->first();
        }
        if (!$billingContact) {
            $billingContact = $this;
        }
        $payMethod = \WHMCS\Payment\PayMethod\Adapter\RemoteBankAccount::factoryPayMethod($this, $billingContact, $description);
        $payMethod->setGateway($gateway);
        $payMethod->save();
        $newPayment = $payMethod->payment;
        $newPayment->setRemoteToken($remoteToken);
        if ($accountNumber) {
            $newPayment->setAccountNumber($accountNumber);
        }
        if ($accountHolderName) {
            $newPayment->setAccountHolderName($accountHolderName);
        }
        $newPayment->validateRequiredValuesPreSave()->save();
        return $payMethod;
    }

    public function getClientDiscountPercentage()
    {
        if (0 < $this->groupId) {
            $clientGroup = \WHMCS\Database\Capsule::table("tblclientgroups")->find($this->groupId);
            if ($clientGroup && 0 < $clientGroup->discountpercent) {
                return $clientGroup->discountpercent;
            }
        }
        return 0;
    }

    public function addCredit($description, $amount)
    {
        \WHMCS\Database\Capsule::table("tblcredit")->insert(["clientid" => $this->id, "date" => \WHMCS\Carbon::now()->toDateString(), "description" => $description, "amount" => $amount]);
        $this->credit = $this->credit + $amount;
        $this->save();
        return $this;
    }

    public function getLink()
    {
        return \App::get_admin_folder_name() . "/clientssummary.php?userid=" . $this->id;
    }

    public function authedUserIsOwner()
    {
        if (!empty($this->pivot)) {
            return 0 < $this->pivot->owner;
        }
        return false;
    }

    public function getAuthUserById($userId)
    {
        return $this->users()->where("auth_user_id", $userId)->first();
    }

    public function getCurrencyCodeAttribute()
    {
        $this->loadMissing("currencyrel");
        if ($this->currencyrel) {
            return $this->currencyrel->code;
        }
        return "";
    }

    public function mergeTo($self, $client)
    {
        $updatedAtTables = ["tblcontacts", "tbldomains", "tblemails", "tblhosting", "tblhostingaddons", "tblinvoices", "tbltickets"];
        $tables_array = ["tblaccounts", "tblactivitylog", "tblcontacts", "tbldomains", "tblemails", "tblhosting", "tblhostingaddons", "tblinvoiceitems", "tblinvoices", "tblnotes", "tblorders", "tblquotes", "tblticketreplies", "tbltickets", "tblsslorders", "tblclientsfiles", "tblbillableitems"];
        foreach ($tables_array as $table) {
            $updateArray = ["userid" => $client->id];
            if (in_array($table, $updatedAtTables)) {
                $updateArray["updated_at"] = \WHMCS\Carbon::now()->toDateTimeString();
            }
            \WHMCS\Database\Capsule::table($table)->where("userid", $this->id)->update($updateArray);
        }
        \WHMCS\Database\Capsule::table("tblcredit")->where("clientid", $this->id)->update(["clientid" => $client->id]);
        $client->credit += $this->credit;
        $existingCount = $client->payMethods()->count();
        foreach ($this->payMethods->sortBy("order_preference") as $payMethod) {
            $payMethod->order_preference = $existingCount;
            $payMethod->save();
            $existingCount++;
        }
        unset($payMethod);
        $this->payMethods()->where("contact_type", "=", "Client")->where("contact_id", "=", $this->id)->update(["contact_id" => $client->id]);
        $this->payMethods()->update(["userid" => $client->id]);
        $this->invites()->update(["client_id" => $client->id]);
        if ($this->affiliate) {
            $oldAffiliate = $this->affiliate;
            $newAffiliate = $client->affiliate;
            if (!$newAffiliate) {
                $oldAffiliate->clientId = $client->id;
                $oldAffiliate->save();
            } else {
                $newAffiliate->visitorCount += $oldAffiliate->visitorCount;
                $newAffiliate->balance += $oldAffiliate->balance;
                $newAffiliate->amountWithdrawn += $oldAffiliate->amountWithdrawn;
                $newAffiliate->save();
                $tables = ["tblaffiliatesaccounts", "tblaffiliateshistory", "tblaffiliateswithdrawals"];
                foreach ($tables as $table) {
                    \WHMCS\Database\Capsule::table($table)->where("affiliateid", $oldAffiliate->id)->update(["affiliateid" => $newAffiliate->id]);
                }
                $oldAffiliate->delete();
            }
        }
        $newClientUserIds = $client->getUserIds();
        foreach ($this->users as $user) {
            if (!in_array($user->id, $newClientUserIds)) {
                if ($user->isOwner($this)) {
                    $user->pivot->owner = false;
                    $user->pivot->setPermissions(Permissions::all());
                }
                $user->pivot->client_id = $client->id;
                $user->pivot->save();
            }
        }
        $this->refresh();
        $this->users()->detach();
        logActivity("Merged User ID: " . $this->id . " with User ID: " . $client->id, $client->id);
        run_hook("AfterClientMerge", ["toUserID" => $client->id, "fromUserID" => $this->id]);
        $client->save();
        return $this;
    }

    public function deleteUsersWithNoOtherClientAccounts()
    {
        foreach ($this->users as $user) {
            if ($user->clients()->where("tblclients.id", "!=", $this->id)->count() === 0) {
                $deletedUserId = $user->id;
                $user->delete();
                logActivity("User Deleted - ID: " . $deletedUserId);
            }
        }
    }

    public function deleteTransactions()
    {
        $this->transactions()->delete();
    }

    public function disassociateTransactions()
    {
        $transactionDescription = "this belonged to deleted client, email sha256:" . hash("sha256", $this->email);
        $this->transactions()->update(["userid" => 0, "currency" => $this->currencyId, "description" => \WHMCS\Database\Capsule::raw("concat(description, ' - " . $transactionDescription . "')")]);
    }

    public function getDisplayNameFormattedAttribute()
    {
        return $this->formatter()->noLink()->markup();
    }
}
