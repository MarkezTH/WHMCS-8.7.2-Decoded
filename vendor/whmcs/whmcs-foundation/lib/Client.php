<?php

namespace WHMCS;

class Client
{
    protected $userid = "";
    protected $clientModel = NULL;

    public function __construct($user)
    {
        if ($user instanceof User\Client) {
            $this->clientModel = $user;
            $this->setID($user->id);
        } else {
            $this->setID($user);
            $this->clientModel = User\Client::find($this->getID());
        }
        return $this;
    }

    public function getClientModel()
    {
        return $this->clientModel;
    }

    public function setID($userid)
    {
        $this->userid = (int) $userid;
    }

    public function getID()
    {
        return $this->userid;
    }

    public function getUneditableClientProfileFields()
    {
        global $whmcs;
        return explode(",", $whmcs->get_config("ClientsProfileUneditableFields"));
    }

    public function isEditableField($field)
    {
        $uneditablefields = defined("CLIENTAREA") ? $this->getUneditableClientProfileFields() : [];
        return !in_array($field, $uneditablefields) ? true : false;
    }

    public static function formatPhoneNumber($details)
    {
        $phone = trim($details["phonenumber"] ?? "");
        $phonePrefix = "";
        if (substr($phone, 0, 1) == "+") {
            $phoneParts = explode(".", ltrim($phone, "+"), 2);
            if (count($phoneParts) == 2) {
                list($phonePrefix, $phoneNumber) = $phoneParts;
            } else {
                $phoneNumber = $phoneParts[0];
            }
        } else {
            $phoneNumber = $phone;
        }
        $phonePrefix = preg_replace("/[^0-9]/", "", $phonePrefix);
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
        $countries = new Utility\Country();
        if (!$phonePrefix) {
            $phonePrefix = $countries->getCallingCode($details["countrycode"]);
        }
        $trimmedPhoneNumber = $phoneNumber;
        if ($phonePrefix != $countries->getCallingCode("IT")) {
            $trimmedPhoneNumber = ltrim($trimmedPhoneNumber, "0");
        }
        $fullyFormattedPhoneNumber = $phonePrefix ? "+" . $phonePrefix . "." . $trimmedPhoneNumber : $phoneNumber;
        $details["phonenumber"] = $phoneNumber;
        $details["phonecc"] = $phonePrefix;
        $details["phonenumberformatted"] = $phoneNumber ? $fullyFormattedPhoneNumber : $phoneNumber;
        $details["telephoneNumber"] = Config\Setting::getValue("PhoneNumberDropdown") ? $details["phonenumberformatted"] : $phone;
        return $details;
    }

    public function getDetails($contactid = "")
    {
        if (is_null($this->clientModel)) {
            return false;
        }
        $countries = new Utility\Country();
        if (!function_exists("convertStateToCode")) {
            require ROOTDIR . "/includes/clientfunctions.php";
        }
        if (!function_exists("getCustomFields")) {
            require ROOTDIR . "/includes/customfieldfunctions.php";
        }
        $details = [];
        $details["userid"] = $this->clientModel->id;
        $details["client_id"] = $details["userid"];
        $details["id"] = $details["client_id"];
        $ownerUser = $this->clientModel->owner();
        if (!$ownerUser) {
            $email = Config\Setting::getValue("SystemEmailsFromEmail") ?: "noreply@example.com";
            list($domain) = explode("@", $email);
            $emailCheck = User\User::username($this->clientModel->email)->first();
            $email = $emailCheck ? "client-" . $this->clientModel->id . "-" . time() . "@" . $domain : $this->clientModel->email;
            $ownerUser = User\User::createUser($this->clientModel->firstName, $this->clientModel->lastName, $email, \Illuminate\Support\Str::random(16), NULL, true, true);
            $ownerUser->clients()->attach($this->clientModel->id, ["owner" => true]);
            \DI::make("runtimeStorage")->missingOwnerCreated = true;
        }
        $details["owner_user_id"] = $ownerUser->id;
        $billingContact = false;
        if ($contactid === "billing") {
            $contactid = $this->clientModel->billingContactId;
            $billingContact = true;
        } else {
            $contactid = (int) $contactid;
        }
        $contact = NULL;
        if (0 < $contactid) {
            try {
                $contact = $this->clientModel->contacts()->whereId($contactid)->firstOrFail();
                $details["firstname"] = $contact->firstName;
                $details["lastname"] = $contact->lastName;
                $details["fullname"] = $contact->fullName;
                $details["companyname"] = $contact->companyName;
                $details["email"] = $contact->email;
                $details["address1"] = $contact->address1;
                $details["address2"] = $contact->address2;
                $details["city"] = $contact->city;
                $details["fullstate"] = $contact->state;
                $details["state"] = $details["fullstate"];
                $details["postcode"] = $contact->postcode;
                $details["countrycode"] = $contact->country;
                $details["country"] = $details["countrycode"];
                $details["phonenumber"] = $contact->phoneNumber;
                $details["tax_id"] = $contact->taxId;
                if (empty($details["tax_id"])) {
                    $details["tax_id"] = $this->clientModel->taxId;
                }
                $details["email_preferences"] = $contact->getEmailPreferences();
                $details["domainemails"] = $contact->receivesDomainEmails;
                $details["generalemails"] = $contact->receivesGeneralEmails;
                $details["invoiceemails"] = $contact->receivesInvoiceEmails;
                $details["productemails"] = $contact->receivesProductEmails;
                $details["supportemails"] = $contact->receivesSupportEmails;
                $details["affiliateemails"] = $contact->receivesAffiliateEmails;
                $details["model"] = $contact;
                $details["uuid"] = $this->clientModel->uuid;
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                if ($billingContact) {
                    $this->clientModel->billingcid = 0;
                    $this->clientModel->save();
                }
            }
        }
        if (is_null($contact)) {
            $details["uuid"] = $this->clientModel->uuid;
            $details["firstname"] = $this->clientModel->firstName;
            $details["lastname"] = $this->clientModel->lastName;
            $details["fullname"] = $this->clientModel->fullName;
            $details["companyname"] = $this->clientModel->companyName;
            $details["email"] = $this->clientModel->email;
            $details["address1"] = $this->clientModel->address1;
            $details["address2"] = $this->clientModel->address2;
            $details["city"] = $this->clientModel->city;
            $details["fullstate"] = $this->clientModel->state;
            $details["state"] = $details["fullstate"];
            $details["postcode"] = $this->clientModel->postcode;
            $details["countrycode"] = $this->clientModel->country;
            $details["country"] = $details["countrycode"];
            $details["phonenumber"] = $this->clientModel->phoneNumber;
            $details["tax_id"] = $this->clientModel->taxId;
            $details["email_preferences"] = $this->clientModel->getEmailPreferences();
            $details["model"] = $this->clientModel;
        }
        if (!$details["uuid"]) {
            $uuid = \Ramsey\Uuid\Uuid::uuid4();
            $details["uuid"] = $uuid->toString();
            $this->clientModel->uuid = $details["uuid"];
            $this->clientModel->save();
        }
        if ($details["country"] == "GB") {
            $postcode = $origpostcode = $details["postcode"] ?? "";
            $postcode = strtoupper($postcode);
            $postcode = preg_replace("/[^A-Z0-9]/", "", $postcode);
            if (strlen($postcode) == 5) {
                $postcode = substr($postcode, 0, 2) . " " . substr($postcode, 2);
            } else {
                if (strlen($postcode) == 6) {
                    $postcode = substr($postcode, 0, 3) . " " . substr($postcode, 3);
                } else {
                    if (strlen($postcode) == 7) {
                        $postcode = substr($postcode, 0, 4) . " " . substr($postcode, 4);
                    } else {
                        $postcode = $origpostcode;
                    }
                }
            }
            $postcode = trim($postcode);
            $details["postcode"] = $postcode;
        }
        $details["statecode"] = convertStateToCode($details["state"], $details["country"]);
        $details["countryname"] = $countries->getName($details["countrycode"]);
        $details = self::formatPhoneNumber($details);
        if (!function_exists("getClientDefaultCardDetails")) {
            require_once ROOTDIR . "/includes/ccfunctions.php";
        }
        $defaultPayMethod = getClientDefaultCardDetails($this->userid);
        $details["billingcid"] = $this->clientModel->billingContactId;
        $details["notes"] = $this->clientModel->notes;
        $details["currency"] = $this->clientModel->currencyId;
        $details["defaultgateway"] = $this->clientModel->defaultPaymentGateway;
        $details["cctype"] = $defaultPayMethod["cardtype"];
        $details["cclastfour"] = $defaultPayMethod["cardlastfour"];
        $details["gatewayid"] = $defaultPayMethod["gatewayid"];
        $details["groupid"] = $this->clientModel->groupId;
        $details["status"] = $this->clientModel->status;
        $details["credit"] = $this->clientModel->credit;
        $details["taxexempt"] = $this->clientModel->taxExempt;
        $details["latefeeoveride"] = $this->clientModel->overrideLateFee;
        $details["overideduenotices"] = $this->clientModel->overrideOverdueNotices;
        $details["separateinvoices"] = $this->clientModel->separateInvoices;
        $details["disableautocc"] = $this->clientModel->disableAutomaticCreditCardProcessing;
        $details["emailoptout"] = $this->clientModel->emailOptOut;
        $details["marketing_emails_opt_in"] = $this->clientModel->marketingEmailsOptIn;
        $details["overrideautoclose"] = $this->clientModel->overrideAutoClose;
        $details["allowSingleSignOn"] = $this->clientModel->allowSso;
        $details["email_verified"] = $ownerUser->emailVerified();
        $details["language"] = $this->clientModel->language;
        $details["isOptedInToMarketingEmails"] = $this->clientModel->isOptedInToMarketingEmails();
        $details["tax_state"] = $this->clientModel->state;
        $details["tax_countrycode"] = $this->clientModel->country;
        $details["lastlogin"] = "No Login Logged";
        $lastLogin = $this->clientModel->usersRelation()->orderBy("last_login", "desc")->first();
        if ($lastLogin && $lastLogin->hasLastLogin()) {
            $lastLoginTimestamp = $lastLogin->getLastLogin()->toDateTimeString();
            $details["lastlogin"] = "Date: " . fromMySQLDate($lastLoginTimestamp, "time") . "<br>IP Address: " . $lastLogin->user->lastIp . "<br>Host: " . $lastLogin->user->lastHostname;
        }
        $customfields = getCustomFields("client", "", $this->clientModel->id, true);
        foreach ($customfields as $i => $value) {
            $details["customfields" . ($i + 1)] = $value["value"];
            $details["customfields"][] = ["id" => $value["id"], "value" => $value["value"]];
        }
        return $details;
    }

    public function getCurrency()
    {
        return getCurrency($this->getID());
    }

    public function updateClient()
    {
        global $whmcs;
        $exinfo = $this->getDetails();
        $isAdmin = false;
        if (defined("ADMINAREA")) {
            $updatefieldsarray = [];
            $isAdmin = true;
        } else {
            $updatefieldsarray = ["firstname" => "First Name", "lastname" => "Last Name", "companyname" => "Company Name", "email" => "Email Address", "address1" => "Address 1", "address2" => "Address 2", "city" => "City", "state" => "State", "postcode" => "Postcode", "country" => "Country", "phonenumber" => "Phone Number", "billingcid" => "Billing Contact", "tax_id" => \Lang::trans(Billing\Tax\Vat::getLabel()), "accountLanguage" => "Language"];
        }
        $changelist = [];
        foreach ($updatefieldsarray as $field => $displayname) {
            if ($this->isEditableField($field)) {
                $value = $whmcs->get_req_var($field);
                if ($field == "accountLanguage") {
                    $field = "language";
                }
                $existingValue = $exinfo[$field];
                if ($field == "phonenumber" && $value) {
                    $value = str_replace([" ", "-"], "", \App::formatPostedPhoneNumber());
                    $existingValue = $exinfo["phonenumberformatted"];
                }
                $this->clientModel->{$field} = $value;
                if ($value != $existingValue) {
                    $changelist[] = $displayname . ": '" . $existingValue . "' to '" . $value . "'";
                }
            }
        }
        if (\App::isInRequest("email_preferences") && !Config\Setting::getValue("DisableClientEmailPreferences")) {
            $emailPreferences = \App::getFromRequest("email_preferences");
            $emailPreferencesChanges = [];
            foreach ($emailPreferences as $type => $value) {
                $existingValue = $exinfo["email_preferences"][$type];
                if ((int) $existingValue != (int) $value) {
                    $suffixText = "Disabled";
                    if ($value) {
                        $suffixText = "Enabled";
                    }
                    $emailPreferencesChanges[] = ucfirst($type) . " Emails " . $suffixText;
                }
            }
            if (0 < count($emailPreferencesChanges)) {
                $changelist[] = "Email Preferences Updated: " . implode(", ", $emailPreferencesChanges);
                $this->clientModel->emailPreferences = $emailPreferences;
            }
        }
        if (Config\Setting::getValue("AllowClientsEmailOptOut")) {
            $marketingoptin = (bool) \App::getFromRequest("marketingoptin");
            if ($this->clientModel->isOptedInToMarketingEmails() && !$marketingoptin) {
                $this->clientModel->marketingEmailOptOut();
                $changelist[] = "Opted Out of Marketing Emails";
            } else {
                if (!$this->clientModel->isOptedInToMarketingEmails() && $marketingoptin) {
                    $this->clientModel->marketingEmailOptIn();
                    $changelist[] = "Opted In to Marketing Emails";
                }
            }
        }
        if (Config\Setting::getValue("TaxEUTaxValidation")) {
            $taxExempt = Billing\Tax\Vat::setTaxExempt($this->clientModel);
            $this->clientModel->taxExempt = $taxExempt;
        }
        $this->clientModel->save();
        $customfieldsarray = [];
        $old_customfieldsarray = getCustomFields("client", "", $this->getID(), "", "");
        $customfields = getCustomFields("client", "", $this->getID(), "", "");
        foreach ($customfields as $v) {
            $k = $v["id"];
            $customfieldsarray[$k] = $_POST["customfield"][$k] ?? NULL;
        }
        saveCustomFields($this->getID(), $customfieldsarray, "client", $isAdmin);
        $paymentmethod = $whmcs->get_req_var("paymentmethod");
        if ($paymentmethod == "none") {
            $paymentmethod = "";
        }
        clientChangeDefaultGateway($this->getID(), $paymentmethod);
        if ($paymentmethod != $exinfo["defaultgateway"]) {
            $changelist[] = "Default Payment Method: '" . getGatewayName($exinfo["defaultgateway"]) . "' to '" . getGatewayName($paymentmethod) . "'\n";
        }
        \HookMgr::run("ClientEdit", array_merge(["userid" => $this->getID(), "isOptedInToMarketingEmails" => $this->clientModel->isOptedInToMarketingEmails(), "olddata" => $exinfo], $this->getDetails()));
        if (!defined("ADMINAREA")) {
            foreach ($old_customfieldsarray as $values) {
                $postValue = $_POST["customfield"][$values["id"]] ?? NULL;
                if ($values["value"] != $postValue) {
                    $changelist[] = sprintf("%s: '%s' to '%s'", $values["name"], $values["value"], $postValue);
                }
            }
            unset($postValue);
            if (0 < count($changelist)) {
                if (Config\Setting::getValue("SendEmailNotificationonUserDetailsChange")) {
                    $adminurl = \App::getSystemURL();
                    $adminurl .= \App::get_admin_folder_name() . "/clientssummary.php?userid=" . $this->getID();
                    sendAdminNotification("account", "WHMCS User Details Change", "<p>Client ID: <a href=\"" . $adminurl . "\">" . $this->getID() . " - " . $exinfo["firstname"] . " " . $exinfo["lastname"] . "</a> has requested to change his/her details as indicated below:<br><br>" . implode("<br />\n", $changelist) . "<br>If you are unhappy with any of the changes, you need to login and revert them - this is the only record of the old details.</p><p>This change request was submitted from " . Utility\Environment\CurrentRequest::getIPHost() . " (" . Utility\Environment\CurrentRequest::getIP() . ")</p>");
                }
                logActivity("Client Profile Modified - " . implode(", ", $changelist), $this->getID(), ["withClientId" => true]);
            }
        }
        return true;
    }

    public function getContactsWithAddresses()
    {
        $where = [];
        $where["userid"] = $this->userid;
        $where["address1"] = ["sqltype" => "NEQ", "value" => ""];
        return $this->getContactsData($where);
    }

    public function getContacts()
    {
        $where = [];
        $where["userid"] = $this->userid;
        return $this->getContactsData($where);
    }

    private function getContactsData($where)
    {
        $contactsarray = [];
        $result = select_query("tblcontacts", "id,firstname,lastname,email", $where, "firstname` ASC,`lastname", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $contactsarray[] = ["id" => $data["id"], "name" => $data["firstname"] . " " . $data["lastname"], "email" => $data["email"]];
        }
        return $contactsarray;
    }

    public function getContact($contactid)
    {
        $result = select_query("tblcontacts", "", ["userid" => $this->userid, "id" => $contactid]);
        $data = mysql_fetch_assoc($result);
        $data["permissions"] = explode(",", $data["permissions"]);
        return isset($data["id"]) ? $data : false;
    }

    public function deleteContact($contactid)
    {
        $contactInfo = $this->getContact($contactid);
        $name = $contactInfo["firstname"] . " " . $contactInfo["lastname"];
        $email = $contactInfo["email"];
        delete_query("tblcontacts", ["userid" => $this->userid, "id" => $contactid]);
        update_query("tblclients", ["billingcid" => ""], ["billingcid" => $contactid, "id" => $this->userid]);
        update_query("tblorders", ["contactid" => "0"], ["contactid" => $contactid]);
        delete_query("tblauthn_account_links", ["client_id" => $this->userid, "contact_id" => $contactid]);
        run_hook("ContactDelete", ["userid" => $this->userid, "contactid" => $contactid]);
        logActivity("Deleted Contact - User ID: " . $this->userid . " - Contact ID: " . $contactid . " - Contact Name: " . $name . " - Contact Email: " . $email, $this->userid);
    }

    public function getFiles()
    {
        $where = ["userid" => $this->userid];
        if (!defined("ADMINAREA")) {
            $where["adminonly"] = "";
        }
        $files = [];
        $result = select_query("tblclientsfiles", "", $where, "title", "ASC");
        while ($data = mysql_fetch_assoc($result)) {
            $id = $data["id"];
            $title = $data["title"];
            $adminonly = $data["adminonly"];
            $filename = $data["filename"];
            $filename = substr($filename, 11);
            $date = fromMySQLDate($data["dateadded"], 0, 1);
            $files[] = ["id" => $id, "date" => $date, "title" => $title, "adminonly" => $adminonly, "filename" => $filename];
        }
        return $files;
    }

    public function sendEmailTpl($tplname)
    {
        return sendMessage($tplname, $this->userid);
    }

    public function getEmailTemplates()
    {
        return Mail\Template::where("type", "=", "general")->where("language", "=", "")->where("name", "!=", "Password Reset Validation")->orderBy("name")->get();
    }

    public function sendCustomEmail($subject, $message)
    {
        Mail\Template::where("name", "=", "Client Custom Email Msg")->delete();
        $customTemplate = new Mail\Template();
        $customTemplate->type = "general";
        $customTemplate->name = "Client Custom Email msg";
        $customTemplate->subject = $subject;
        $customTemplate->message = $message;
        $customTemplate->disabled = false;
        $customTemplate->plaintext = false;
        sendMessage($customTemplate, $this->userid);
        return true;
    }
}
