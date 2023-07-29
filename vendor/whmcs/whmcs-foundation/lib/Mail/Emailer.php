<?php

namespace WHMCS\Mail;

class Emailer
{
    protected $message = NULL;
    protected $entityId = NULL;
    protected $extraParams = [];
    protected $isNonClientEmail = false;
    protected $recipientClientId = NULL;
    protected $recipientUser = NULL;
    protected $mergeData = [];
    protected $emailTemplateNamesToNotLog = ["Client Email Address Verification", "Password Reset Validation"];
    const EMAIL_TYPE_ADMIN = "admin";
    const EMAIL_TYPE_AFFILIATE = "affiliate";
    const EMAIL_TYPE_DOMAIN = "domain";
    const EMAIL_TYPE_GENERAL = "general";
    const EMAIL_TYPE_INVOICE = "invoice";
    const EMAIL_TYPE_INVITE = "invite";
    const EMAIL_TYPE_NOTIFICATION = "notification";
    const EMAIL_TYPE_PRODUCT = "product";
    const EMAIL_TYPE_SUPPORT = "support";
    const EMAIL_TYPE_USER = "user";
    const ENTITY_MAP = NULL;
    const CLIENT_EMAILS = [];
    const EMAIL_TPL_OUTPUT_COLS = NULL;

    public function __construct(Message $message, $entityId, $extraParams = [])
    {
        $this->message = $message;
        $this->entityId = $entityId;
        $this->extraParams = $extraParams;
    }

    public static function factory(Message $message, $entityId, $extraParams = [])
    {
        if (!$message->getType()) {
            throw new \WHMCS\Exception("A message type is required");
        }
        $entityName = array_key_exists($message->getType(), static::ENTITY_MAP) ? static::ENTITY_MAP[$message->getType()] : ucfirst($message->getType());
        $entityClass = "WHMCS\\Mail\\Entity\\" . $entityName;
        return new $entityClass($message, $entityId, $extraParams);
    }

    public static function factoryByTemplate($template, $entityId = 0, $extraParams = [])
    {
        if (!$template instanceof Template) {
            $template = self::getTemplate($template, $entityId, $extraParams);
            if (is_array($extraParams) && !empty($extraParams["gatewayInterface"])) {
                unset($extraParams["gatewayInterface"]);
            }
        }
        if (!$template instanceof Template) {
            throw new \WHMCS\Exception\Mail\InvalidTemplate("Email Template Not Found");
        }
        if ($template->disabled) {
            throw new \WHMCS\Exception\Mail\TemplateDisabled("Email Template Disabled");
        }
        $message = Message::createFromTemplate($template);
        $entityName = array_key_exists($message->getType(), static::ENTITY_MAP) ? static::ENTITY_MAP[$message->getType()] : ucfirst($message->getType());
        $entityClass = "WHMCS\\Mail\\Entity\\" . $entityName;
        return new $entityClass($message, $entityId, $extraParams);
    }

    public static function getTemplate($templateName, $entityId = 0, $extraParams = NULL)
    {
        if ($templateName == "defaultnewacc") {
            $templateId = get_query_val("tblproducts", "tblproducts.welcomeemail", ["tblhosting.id" => $entityId], "", "", "", "tblhosting ON tblhosting.packageid=tblproducts.id");
            return Template::find($templateId);
        }
        if (in_array($templateName, \WHMCS\Billing\Invoice::INVOICE_PAYMENT_EMAILS) && is_array($extraParams) && !empty($extraParams["gatewayInterface"]) && $extraParams["gatewayInterface"] instanceof \WHMCS\Module\Gateway) {
            $metaType = "";
            if (in_array($templateName, \WHMCS\Billing\Invoice::PAYMENT_CONFIRMATION_EMAILS)) {
                $metaType = "successEmail";
            } else {
                if (in_array($templateName, \WHMCS\Billing\Invoice::PAYMENT_PENDING_EMAILS)) {
                    $metaType = "pendingEmail";
                } else {
                    if (in_array($templateName, \WHMCS\Billing\Invoice::PAYMENT_FAILED_EMAILS)) {
                        $metaType = "failedEmail";
                    }
                }
            }
            if ($metaType) {
                $templateName = $extraParams["gatewayInterface"]->getMetaDataValue($metaType) ?: $templateName;
            }
        }
        return Template::where("name", "=", $templateName)->where("language", "=", "")->orWhere("language", "=", NULL)->first();
    }

    public function setEmailLogId($id)
    {
        $this->message->setEmailLogId($id);
        return $this;
    }

    protected function getExtra($key)
    {
        if (is_array($this->extraParams) && array_key_exists($key, $this->extraParams)) {
            return $this->extraParams[$key];
        }
        return NULL;
    }

    protected function getClientMergeData()
    {
        $email_merge_fields = [];
        $userid = $this->recipientClientId;
        try {
            $client = \WHMCS\User\Client::findOrFail($userid);
        } catch (\Exception $e) {
            throw new \WHMCS\Exception("Invalid user id provided");
        }
        $firstname = $client->firstName;
        $email = $client->email;
        $lastname = $client->lastName;
        $companyname = $client->companyName;
        $address1 = $client->address1;
        $address2 = $client->address2;
        $city = $client->city;
        $state = $client->state;
        $postcode = $client->postcode;
        $country = $client->country;
        $phonenumber = $client->phoneNumber;
        $taxId = $client->taxId;
        $language = $client->language;
        $credit = $client->credit;
        $status = $client->status;
        $clgroupid = $client->groupId;
        $clgroupname = (string) $client->groupName;
        $gatewayid = $client->paymentGatewayToken;
        $datecreated = fromMySQLDate($client->dateCreated, 0, 1);
        $password = "**********";
        $cardtype = $this->getExtra("card_type");
        $cardnum = $this->getExtra("card_last_four");
        $cardexp = $this->getExtra("card_expiry");
        $cardDescription = $this->getExtra("card_description");
        if (is_null($cardtype)) {
            if (!function_exists("getClientDefaultCardDetails")) {
                require_once ROOTDIR . "/includes/ccfunctions.php";
            }
            $payMethod = NULL;
            if ($this->getExtra("payMethod")) {
                $payMethodExtra = $this->getExtra("payMethod");
                if (is_numeric($payMethodExtra)) {
                    $payMethod = \WHMCS\Payment\PayMethod\Model::find($payMethodExtra);
                } else {
                    if (is_object($payMethodExtra) && $payMethodExtra instanceof \WHMCS\Payment\PayMethod\Model) {
                        $payMethod = $payMethodExtra;
                    }
                }
                unset($payMethodExtra);
                $cardDetails = getPayMethodCardDetails($payMethod);
            } else {
                $cardDetails = getClientDefaultCardDetails($userid);
            }
            $cardtype = $cardDetails["cardtype"];
            $cardnum = $cardDetails["cardlastfour"];
            $cardexp = $cardDetails["expdate"];
            $cardDescription = $cardDetails["card_description"];
            $gatewayid = $cardDetails["gatewayid"];
            unset($cardDetails);
        }
        $currency = getCurrency($userid);
        $totalInvoices = get_query_val("tblinvoices", "SUM(total)", ["userid" => $userid, "status" => "Unpaid"]);
        $unpaidInvoiceIds = \WHMCS\Database\Capsule::table("tblinvoices")->where("status", "Unpaid")->where("userid", $userid)->pluck("id")->all();
        $paidBalance = 0;
        if ($unpaidInvoiceIds) {
            $paidBalance = get_query_val("tblaccounts", "SUM(amountin-amountout)", "tblaccounts.invoiceid IN (" . db_build_in_array($unpaidInvoiceIds) . ")");
        }
        $balance = floatval($totalInvoices) - floatval($paidBalance);
        $email_merge_fields["client_due_invoices_balance"] = formatCurrency($balance);
        $fullName = trim($firstname . " " . $lastname);
        if ($companyname) {
            $fullName .= " (" . $companyname . ")";
        }
        $email = trim($email);
        if (!$email) {
            throw new \WHMCS\Exception("Email address not set for client");
        }
        $sendToClient = true;
        if (!\WHMCS\Config\Setting::getValue("DisableClientEmailPreferences") && $this->allowCc() && !$client->getEmailPreference($this->getMessage()->getType())) {
            $sendToClient = false;
        }
        if ($sendToClient) {
            $this->message->addRecipient("to", $email, $fullName);
        }
        $email_merge_fields["client_id"] = $userid;
        $email_merge_fields["client_name"] = $fullName;
        $email_merge_fields["client_first_name"] = $firstname;
        $email_merge_fields["client_last_name"] = $lastname;
        $email_merge_fields["client_company_name"] = $companyname;
        $email_merge_fields["client_email"] = $email;
        $email_merge_fields["client_address1"] = $address1;
        $email_merge_fields["client_address2"] = $address2;
        $email_merge_fields["client_city"] = $city;
        $email_merge_fields["client_state"] = $state;
        $email_merge_fields["client_postcode"] = $postcode;
        $email_merge_fields["client_country"] = $country;
        $email_merge_fields["client_phonenumber"] = $phonenumber;
        $email_merge_fields["client_tax_id"] = $taxId;
        $email_merge_fields["client_password"] = $password;
        $email_merge_fields["client_signup_date"] = $datecreated;
        $email_merge_fields["client_credit"] = formatCurrency($credit);
        $email_merge_fields["client_cc_description"] = (string) $cardDescription;
        $email_merge_fields["client_cc_type"] = (string) $cardtype;
        $email_merge_fields["client_cc_number"] = (string) $cardnum;
        $email_merge_fields["client_cc_expiry"] = (string) $cardexp;
        $email_merge_fields["client_language"] = $language;
        $email_merge_fields["client_status"] = $status;
        $email_merge_fields["client_group_id"] = $clgroupid;
        $email_merge_fields["client_group_name"] = $clgroupname;
        $email_merge_fields["client_gateway_id"] = $gatewayid;
        $subscriptionController = new \WHMCS\Marketing\EmailSubscription();
        $email_merge_fields["email_marketing_optin_url"] = $subscriptionController->generateOptInUrl($userid, $email, false);
        $email_merge_fields["email_marketing_optout_url"] = $subscriptionController->generateOptOutUrl($userid, $email, false);
        $email_merge_fields["email_marketing_optin_link"] = $subscriptionController->generateOptInUrl($userid, $email, true);
        $email_merge_fields["email_marketing_optout_link"] = $subscriptionController->generateOptOutUrl($userid, $email, true);
        $email_merge_fields["unsubscribe_url"] = $email_merge_fields["email_marketing_optout_url"];
        if (!function_exists("getCustomFields")) {
            require_once ROOTDIR . "/includes/customfieldfunctions.php";
        }
        $customfields = getCustomFields("client", "", $userid, true, "");
        $email_merge_fields["client_custom_fields"] = [];
        foreach ($customfields as $customfield) {
            $customfieldname = preg_replace("/[^0-9a-z]/", "", strtolower($customfield["name"]));
            $email_merge_fields["client_custom_field_" . $customfieldname] = $customfield["value"];
            $email_merge_fields["client_custom_fields"][] = $customfield["value"];
            $email_merge_fields["client_custom_fields_by_name"][] = ["name" => $customfield["name"], "value" => $customfield["value"]];
        }
        $this->massAssign($email_merge_fields);
    }

    protected function getGenericMergeData()
    {
        $sysurl = \App::getSystemUrl();
        $whmcs = \App::self();
        $email_merge_fields = [];
        $email_merge_fields["company_name"] = \WHMCS\Config\Setting::getValue("CompanyName");
        $email_merge_fields["companyname"] = \WHMCS\Config\Setting::getValue("CompanyName");
        $email_merge_fields["company_domain"] = \WHMCS\Config\Setting::getValue("Domain");
        $email_merge_fields["company_logo_url"] = $whmcs->getLogoUrlForEmailTemplate();
        $email_merge_fields["company_tax_code"] = \WHMCS\Config\Setting::getValue("TaxCode");
        $email_merge_fields["whmcs_url"] = $sysurl;
        $email_merge_fields["whmcs_link"] = "<a href=\"" . $sysurl . "\">" . $sysurl . "</a>";
        $email_merge_fields["signature"] = nl2br(\WHMCS\Input\Sanitize::decode(\WHMCS\Config\Setting::getValue("Signature")));
        $email_merge_fields["date"] = date("l, jS F Y");
        $email_merge_fields["time"] = date("g:ia");
        $email_merge_fields["charset"] = \WHMCS\Config\Setting::getValue("Charset");
        $this->massAssign($email_merge_fields);
    }

    protected function allowCc()
    {
        $doNotCcList = ["Password Reset Validation", "Password Reset Confirmation", "Client Email Address Verification"];
        return !in_array($this->message->getTemplateName(), $doNotCcList);
    }

    protected function prepare()
    {
        $originalLanguage = \Lang::self();
        $this->getEntitySpecificMergeData($this->entityId, $this->extraParams);
        if (!$this->isNonClientEmail) {
            $this->getClientMergeData();
        }
        swapLang($originalLanguage);
        if (is_array($this->extraParams)) {
            $this->massAssign($this->extraParams);
        }
        $this->getGenericMergeData();
        $languagePreference = $this->getRecipientLanguage();
        if ($languagePreference != "") {
            $this->overwriteWithLanguageTemplate($languagePreference);
        }
        unset($languagePreference);
        $hookresults = run_hook("EmailPreSend", ["messagename" => $this->message->getTemplateName(), "relid" => $this->entityId, "mergefields" => $this->mergeData]);
        foreach ($hookresults as $hookmergefields) {
            foreach ($hookmergefields as $key => $value) {
                if ($key == "abortsend" && $value) {
                    throw new \WHMCS\Exception\Mail\SendHookAbort("Email Send Aborted By Hook");
                }
                $this->assign($key, $value);
            }
        }
        $smarty = new \WHMCS\Smarty(false, "mail");
        $smarty->setMailMessage($this->message);
        $smarty->compile_id = md5($this->message->getSubject() . $this->message->getBody() . (\App::isExecutingViaCron() || \WHMCS\Environment\Php::isCli() ? "cron" : ""));
        foreach ($this->mergeData as $mergefield => $mergevalue) {
            $smarty->assign($mergefield, $mergevalue);
        }
        $subject = $smarty->fetch("mailMessage:subject");
        $message = $smarty->fetch("mailMessage:message");
        $messageText = $smarty->fetch("mailMessage:plaintext");
        if ((is_null($message) || strlen(trim($message)) == 0) && (is_null($messageText) || strlen(trim($messageText)) == 0)) {
            throw new \WHMCS\Exception("Email message rendered empty - please check the email message Smarty markup syntax");
        }
        $this->message->setSubject($subject);
        $this->message->setBodyFromSmarty($message);
        $this->message->setPlainText($messageText);
        if (!$this->isNonClientEmail) {
            if ($this->allowCc()) {
                $recipients = [];
                if ($this->recipientUser) {
                    $recipients[] = $this->recipientUser;
                } else {
                    $recipients = \WHMCS\User\Client\Contact::where("userid", $this->recipientClientId)->where($this->message->getType() . "emails", "=", "1")->get(["firstname", "lastname", "email"]);
                }
                foreach ($recipients as $recipient) {
                    $this->message->addRecipient("cc", $recipient->email, $recipient->firstName . " " . $recipient->lastName);
                }
                $this->finalizeCopiedRecipients($this->message, $this->entityId);
            } else {
                $this->message->clearRecipients("cc");
                $this->message->clearRecipients("bcc");
            }
        }
    }

    protected function overwriteWithLanguageTemplate($language)
    {
        $template = Template::where("name", "=", $this->message->getTemplateName())->where("language", "=", $language)->first();
        if ($template == NULL) {
            return false;
        }
        if (isset($template->subject) && substr($this->message->getSubject(), 0, 10) != "[Ticket ID") {
            $this->message->setSubject($template->subject);
        }
        if (isset($template->message)) {
            if ($this->message->getPlainText() && !$this->message->getBody()) {
                $this->message->setPlainText($template->message);
            } else {
                $this->message->setBodyAndPlainText($template->message);
            }
        }
        return true;
    }

    public function finalizeCopiedRecipients(Message $message, $relationalId)
    {
        $countToRecipients = count($message->getRecipients("to"));
        $allCopiedRecipients = [];
        foreach (["cc", "bcc"] as $type) {
            $allCopiedRecipients[$type] = [];
            foreach ($message->getRecipients($type) as $recipient) {
                $hash = md5($recipient[0] . $recipient[1]);
                $allCopiedRecipients[$type][$hash] = ["email" => $recipient[0], "fullname" => $recipient[1]];
            }
        }
        $message->clearRecipients("cc");
        $message->clearRecipients("bcc");
        $hookresults = run_hook("PreEmailSendReduceRecipients", ["messagename" => $message->getTemplateName(), "relid" => $relationalId, "recipients" => $allCopiedRecipients]);
        foreach ($hookresults as $hookresult) {
            foreach (["cc", "bcc"] as $type) {
                if (is_array($hookresult) && isset($hookresult[$type]) && is_array($hookresult[$type])) {
                    $hookHashes = array_keys($hookresult[$type]);
                    foreach (array_keys($allCopiedRecipients[$type]) as $hash) {
                        if (!in_array($hash, $hookHashes)) {
                            unset($allCopiedRecipients[$type][$hash]);
                        }
                    }
                }
            }
        }
        foreach (["cc", "bcc"] as $type) {
            $typeToSet = $type;
            if ($type == "cc" && $countToRecipients === 0) {
                $typeToSet = "to";
            }
            foreach ($allCopiedRecipients[$type] as $recipient) {
                $message->addRecipient($typeToSet, $recipient["email"], $recipient["fullname"]);
            }
        }
        return $message;
    }

    public function getMergeData()
    {
        return $this->mergeData;
    }

    public function getMergeDataByKey($key)
    {
        return isset($this->mergeData[$key]) ? $this->mergeData[$key] : "";
    }

    public function preview()
    {
        try {
            $this->prepare();
        } catch (\WHMCS\Exception\Mail\SendHookAbort $e) {
        } catch (\WHMCS\Exception $e) {
            logActivity("An Error Occurred with the email preview: " . $e->getMessage());
            throw $e;
        }
        return $this->message;
    }

    public function send()
    {
        try {
            $this->prepare();
            if (!$this->message->hasRecipients()) {
                throw new \WHMCS\Exception\Mail\NoRecipients("No recipients provided for message");
            }
            \WHMCS\Module\Mail::factory()->send($this->message);
            $clientId = $this->recipientClientId;
            $isEmailToNotLog = in_array($this->message->getTemplateName(), $this->emailTemplateNamesToNotLog);
            $ticketReplyEmails = ["Support Ticket Opened by Admin", "Support Ticket Reply"];
            $isTicketReplyEmail = in_array($this->message->getTemplateName(), $ticketReplyEmails);
            $ticketEmailLoggingDisabled = \WHMCS\Config\Setting::getValue("DisableSupportTicketReplyEmailsLogging");
            if ($clientId && !$isEmailToNotLog && !($isTicketReplyEmail && $ticketEmailLoggingDisabled)) {
                $this->message->saveToEmailLog($clientId);
            }
            $this->createActivityLogEntry();
            return true;
        } catch (\WHMCS\Exception\Mail\NoRecipients $e) {
            logActivity("Email Sending Failed - " . $e->getMessage() . " (Subject: " . $this->message->getSubject() . ")", "none");
            throw $e;
        } catch (\WHMCS\Exception\Mail\SendHookAbort $e) {
            logActivity("Email Sending Aborted by Hook (Subject: " . $this->message->getSubject() . ")", "none");
            throw $e;
        } catch (\WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            throw $e;
        } catch (\WHMCS\Exception\Mail\InvalidAddress $e) {
            logActivity("Email Sending Failed - " . $e->getMessage() . " (Subject: " . $this->message->getSubject() . ")", "none");
            throw $e;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $exceptionMessage = strip_tags($e->getMessage());
            logActivity("Email Sending Failed - " . $exceptionMessage . " (Subject: " . $this->message->getSubject() . ")", "none");
            throw new \WHMCS\Exception\Mail\SendFailure($exceptionMessage);
        } catch (\WHMCS\Exception $e) {
            logActivity("Email Sending Failed - " . $e->getMessage() . " (Subject: " . $this->message->getSubject() . ")", "none");
            throw new \WHMCS\Exception\Mail\SendFailure($e->getMessage());
        }
    }

    protected function getRecipientName()
    {
        $recipientName = "";
        $this->getMessage()->getType();
        switch ($this->getMessage()->getType()) {
            case "user":
                $recipientName = trim($this->getMergeDataByKey("user_first_name") . " " . $this->getMergeDataByKey("user_last_name"));
                break;
            default:
                $recipientName = trim($this->getMergeDataByKey("client_first_name") . " " . $this->getMergeDataByKey("client_last_name"));
                return $recipientName;
        }
    }

    private function createActivityLogEntry()
    {
        $clientId = $this->recipientClientId;
        $opts = [];
        $recipientName = $this->getRecipientName();
        if ($recipientName) {
            $description = "Email Sent to " . $recipientName . " (" . $this->message->getSubject() . ")";
            $this->getMessage()->getType();
            switch ($this->getMessage()->getType()) {
                case "admin":
                    $description = "Email Sent (" . $this->message->getSubject() . ")";
                    $clientId = $this->getMergeDataByKey("client_id");
                    $opts = ["addOrderId" => $this->getMergeDataByKey("order_id"), "withClientId" => true];
                    break;
                case "user":
                    $opts = ["addUserId" => $this->recipientUser->id];
                    break;
                default:
                    $opts = ["withClientId" => true];
                    logActivity($description, $clientId, $opts);
            }
        }
    }

    protected function setRecipient($clientId, $user = NULL)
    {
        global $_LANG;
        global $currency;
        $userId = $clientId;
        if ($user && $user instanceof \WHMCS\User\User) {
            $this->recipientUser = $user;
            $userId = $user->id;
            if (!$clientId) {
                $this->isNonClientEmail = true;
            }
            $this->message->addRecipient("to", $user->email, $user->fullName);
        } else {
            if ($user && $user instanceof \WHMCS\User\User\UserInvite) {
                if (!$clientId) {
                    $this->isNonClientEmail = true;
                }
                $this->message->addRecipient("to", $user->email);
            }
        }
        if (0 < $clientId) {
            $this->recipientClientId = (int) $clientId;
            getUsersLang($userId);
            $currency = getCurrency($clientId);
        }
        return $this;
    }

    protected function getRecipientLanguage()
    {
        $language = \WHMCS\Config\Setting::getValue("Language") ?? "";
        if ($this->getMessage()->getType() == "admin") {
            return $language;
        }
        if (!empty($this->recipientClientId) && !empty($this->mergeData["client_language"])) {
            $language = $this->mergeData["client_language"];
        } else {
            if ($this->recipientUser instanceof \WHMCS\User\User && !empty($this->recipientUser->language)) {
                $language = $this->recipientUser->language;
            }
        }
        return $language;
    }

    public function assign($key, $value)
    {
        $this->mergeData[$key] = $value;
        return $this;
    }

    public function massAssign($data)
    {
        foreach ($data as $key => $value) {
            $this->assign($key, $value);
        }
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
