<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Hook;
use WHMCS\Application\Support\Facades\HookMgr;
use WHMCS\Application\Support\Facades\Lang;

if (!function_exists("sendMessage")) {
    function sendMessage($template, int $func_id, $extra = [], $displayresult = false, $attachments = [])
    {
        try {
            $emailer = WHMCS\Mail\Emailer::factoryByTemplate($template, $func_id, $extra);
            if (is_array($attachments)) {
                try {
                    $storage = Storage::emailAttachments();
                } catch (Exception $e) {
                    logActivity("Email Sending Failed - The system encountered an error while attempting to access email attachments: " . $e->getMessage());
                    throw new WHMCS\Exception\Mail\InvalidAttachment("Unable to access email attachments");
                }
                foreach ($attachments as $attachment) {
                    $emailer->getMessage()->addStringAttachment($attachment["displayname"], $storage->read($attachment["filename"]));
                }
                unset($storage);
            }
            $emailer->send();
            if ($displayresult) {
                echo "<p>Email Sent Successfully to <a href=\"clientssummary.php?userid=" . $emailer->getMergeDataByKey("client_id") . "\">" . WHMCS\Input\Sanitize::makeSafeForOutput($emailer->getMergeDataByKey("client_first_name")) . " " . WHMCS\Input\Sanitize::makeSafeForOutput($emailer->getMergeDataByKey("client_last_name")) . "</a></p>";
            }
        } catch (WHMCS\Exception\Mail\SendHookAbort $e) {
            if ($displayresult) {
                echo "<p>" . $e->getMessage() . "</p>";
            }
            if (App::isApiRequest()) {
                return false;
            }
            return $e->getMessage();
        } catch (WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            if ($displayresult) {
                echo "<p>" . $e->getMessage() . "</p>";
            }
            $whmcs = App::self();
            if ($whmcs->isApiRequest()) {
                return false;
            }
            return $e->getMessage();
        } catch (WHMCS\Exception\Mail\SendFailure $e) {
            if ($displayresult) {
                echo "<p>Email Sending Failed - " . $e->getMessage() . "</p>";
            }
            $whmcs = App::self();
            if ($whmcs->isApiRequest()) {
                return false;
            }
            return $e->getMessage();
        } catch (WHMCS\Exception\Mail\InvalidTemplate $e) {
        } catch (WHMCS\Exception\Mail\TemplateDisabled $e) {
        } catch (WHMCS\Exception\Mail\InvalidAttachment $e) {
            if ($displayresult) {
                echo "<p>Email Sending Failed - " . $e->getMessage() . "</p>";
            }
            $whmcs = App::self();
            if ($whmcs->isApiRequest()) {
                return false;
            }
            return "Email Sending Failed - " . $e->getMessage();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
    function sendAdminNotification($to, $subject, $messageBody, $deptid = 0, $appendAdminLink = true)
    {
        $sendNow = true;
        if (!class_exists("\\DI")) {
            $sendNow = false;
        } else {
            if (!DI::has("app")) {
                $sendNow = false;
            } else {
                $app = DI::make("app");
                if (!$app instanceof WHMCS\Application) {
                    $sendNow = false;
                }
            }
        }
        if ($sendNow) {
            return sendAdminNotificationNow($to, $subject, $messageBody, $deptid, $appendAdminLink);
        }
        return WHMCS\Scheduling\Jobs\Queue::add(WHMCS\Mail\Job\AdminNotification::JOB_NAME_GENERIC, "WHMCS\\Mail\\Job\\AdminNotification", "send", [$to, $subject, $messageBody, $deptid, $appendAdminLink], 0, false);
    }
    function sendAdminNotificationNow($to, $subject, $messageBody, $deptid = 0, $appendAdminLink = true)
    {
        global $smtp_debug;
        $whmcs = App::self();
        $whmcsAppConfig = $whmcs->getApplicationConfig();
        if (!trim($messageBody)) {
            return false;
        }
        $messageBody = "<p>" . $messageBody . "</p>";
        if ($appendAdminLink) {
            $adminurl = $whmcs->getSystemURL() . $whmcsAppConfig["customadminpath"] . "/";
            $messageBody .= "\n<p><a href=\"" . $adminurl . "\">" . $adminurl . "</a></p>";
        }
        $message = new WHMCS\Mail\Message();
        $message->setType("admin");
        $message->setSubject($subject);
        $message->setBodyAndPlainText($messageBody);
        if ($deptid) {
            $data = get_query_vals("tblticketdepartments", "name,email", ["id" => $deptid]);
            $message->setFromName(WHMCS\Config\Setting::getValue("CompanyName") . " " . $data["name"]);
            $message->setFromEmail($data["email"]);
        } else {
            $message->setFromName(WHMCS\Config\Setting::getValue("SystemEmailsFromName"));
            $message->setFromEmail(WHMCS\Config\Setting::getValue("SystemEmailsFromEmail"));
        }
        $where = "tbladmins.disabled=0 AND tbladminroles." . db_escape_string($to) . "emails='1'";
        if ($deptid) {
            $where .= " AND tbladmins.ticketnotifications!=''";
        }
        $result = select_query("tbladmins", "firstname,lastname,email,ticketnotifications", $where, "", "", "", "tbladminroles ON tbladminroles.id=tbladmins.roleid");
        while ($data = mysql_fetch_array($result)) {
            if ($data["email"]) {
                $adminsend = true;
                if ($deptid) {
                    $ticketnotifications = explode(",", $data["ticketnotifications"]);
                    if (!in_array($deptid, $ticketnotifications)) {
                        $adminsend = false;
                    }
                }
                if ($adminsend) {
                    $message->addRecipient("to", trim($data["email"]), $data["firstname"] . " " . $data["lastname"]);
                }
            }
        }
        if (!$message->hasRecipients()) {
            return false;
        }
        try {
            WHMCS\Module\Mail::factory()->send($message);
        } catch (WHMCS\Exception\Mail\EmailSendingDisabled $e) {
        } catch (WHMCS\Exception\Mail\SendFailure $e) {
            logActivity("Admin Email Notification Sending Failed - Mailer Exception - " . $e->getMessage() . " (Subject: " . $subject . ")", "none");
        } catch (WHMCS\Exception $e) {
            logActivity("Admin Email Notification Sending Failed - " . $e->getMessage() . " (Subject: " . $subject . ")", "none");
        }
    }
    function sendAdminMessage($template, $email_merge_fields = [], $to = "system", $deptid = 0, $adminid = 0, $ticketnotify = "")
    {
        try {
            $emailer = WHMCS\Mail\Emailer::factoryByTemplate($template, "");
            $subject = $emailer->getMessage()->getSubject();
            $type = $emailer->getMessage()->getType();
            if ($type != "admin") {
                throw new WHMCS\Exception("Email template provided is not an admin email template");
            }
            $emailer->massAssign($email_merge_fields);
            $emailer->determineAdminRecipientsAndSender($to, $deptid, $adminid, $ticketnotify);
            $emailer->send();
            return true;
        } catch (WHMCS\Exception\Mail\SendHookAbort $e) {
            $logSubject = isset($subject) ? " (Subject: " . $subject . ")" : "";
            logActivity("Admin Email Message Sending Aborted by Hook" . $logSubject, "none");
        } catch (WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            $logSubject = isset($subject) ? " (Subject: " . $subject . ")" : "";
            logActivity("Admin Email Message Sending Aborted by Configuration" . $logSubject, "none");
        } catch (WHMCS\Exception $e) {
            $logSubject = isset($subject) ? " (Subject: " . $subject . ")" : "";
            logActivity("Admin Email Message Sending Failed - " . $e->getMessage() . $logSubject, "none");
        }
        return false;
    }
    function toMySQLDate($date)
    {
        $dateFormats = ["DD/MM/YYYY" => ["d", "mo", "yr", "hr", "min", "s", "/"], "DD.MM.YYYY" => ["d", "mo", "yr", "hr", "min", "s", "."], "DD-MM-YYYY" => ["d", "mo", "yr", "hr", "min", "s", "-"], "MM/DD/YYYY" => ["mo", "d", "yr", "hr", "min", "s", "/"], "YYYY/MM/DD" => ["yr", "mo", "d", "hr", "min", "s", "/"], "YYYY-MM-DD" => ["yr", "mo", "d", "hr", "min", "s", "-"]];
        $activeDateFormat = WHMCS\Config\Setting::getValue("DateFormat");
        $dateOrder = $dateFormats[$activeDateFormat];
        $dateTime = explode(" ", $date);
        $hasTime = false;
        if (isset($dateTime[1])) {
            $hasTime = true;
        }
        $dateTimeValues = [];
        foreach ($dateTime as $key => $value) {
            $delimiter = ":";
            if ($key === 0) {
                $delimiter = end($dateOrder);
            }
            $dateTimeValues = array_merge($dateTimeValues, explode($delimiter, $value));
        }
        array_pop($dateOrder);
        foreach ($dateOrder as $orderKey => $orderValue) {
            $length = 2;
            if ($orderValue == "yr") {
                $length = 4;
            }
            $value = sprintf("%0" . $length . "d", $dateTimeValues[$orderKey] ?? 0);
            $value = substr($value, 0, $length);
            $dateTime[$orderValue] = $value;
        }
        $date = $dateTime["yr"] . "-" . $dateTime["mo"] . "-" . $dateTime["d"];
        if ($hasTime) {
            $date .= " " . $dateTime["hr"] . ":" . $dateTime["min"] . ":" . $dateTime["s"];
        }
        return $date;
    }
    function validateDateInput($date)
    {
        $sqldate = toMySQLDate($date);
        $dateonly = explode(" ", $sqldate);
        $dateparts = explode("-", $dateonly[0]);
        list($year, $month, $day) = $dateparts;
        if (is_numeric($day) && is_numeric($month) && is_numeric($year)) {
            return checkdate($month, $day, $year);
        }
        return false;
    }
    function getClientDateFormat()
    {
        return WHMCS\Carbon::now()->getClientDateFormat();
    }
    function fromMySQLDate($date, $time = false, $client = false, $zerodateval = false)
    {
        if ($date instanceof WHMCS\Carbon) {
            if ($date->year < 0) {
                $date = "0000-00-00";
            } else {
                $date = (string) $date;
                if ((string) $date === (string) WHMCS\Carbon::createFromTimestamp(0, "UTC")) {
                    $date = "0000-00-00";
                }
            }
        } else {
            if (!is_string($date)) {
                $date = "0000-00-00";
            }
        }
        $isZeroDate = substr($date, 0, 10) == "0000-00-00";
        if ($isZeroDate) {
            if ($zerodateval !== false) {
                return $zerodateval;
            }
            $dateFormat = WHMCS\Carbon::now()->getAdminDateFormat();
            return str_replace(["d", "m", "Y"], ["00", "00", "0000"], $dateFormat);
        }
        try {
            $date = WHMCS\Carbon::parse($date);
        } catch (Exception $e) {
            throw new WHMCS\Exception\Fatal("Invalid date format provided: " . $date);
        }
        if ($client && $time) {
            return $date->toClientDateTimeFormat();
        }
        if ($client) {
            return $date->toClientDateFormat();
        }
        if ($time) {
            return $date->toAdminDateTimeFormat();
        }
        return $date->toAdminDateFormat();
    }
    function MySQL2Timestamp($datetime)
    {
        $val = explode(" ", $datetime, 2);
        $date = explode("-", $val[0]);
        if ($val[1]) {
            $time = explode(":", $val[1]);
        } else {
            $time = "00:00:00";
        }
        return mktime($time[0], $time[1], $time[2], $date[1], $date[2], $date[0]);
    }
    function getTodaysDate($client = "")
    {
        return fromMySQLDate(date("Y-m-d"), 0, $client);
    }
    function xdecrypt($ckey, $string)
    {
        $string = base64_decode($string);
        $keys = [];
        $c_key = base64_encode(sha1(md5($ckey)));
        $c_key = substr($c_key, 0, round(ord($ckey[0]) / 5));
        $c2_key = base64_encode(md5(sha1($ckey)));
        $last = strlen($ckey) - 1;
        $c2_key = substr($c2_key, 1, round(ord($ckey[$last]) / 7));
        $c3_key = base64_encode(sha1(md5($c_key) . md5($c2_key)));
        $mid = round($last / 2);
        $c3_key = substr($c3_key, 1, round(ord($ckey[$mid]) / 9));
        $c_key = $c_key . $c2_key . $c3_key;
        $c_key = base64_encode($c_key);
        $c_key_length = strlen($c_key);
        for ($i = 0; $i < $c_key_length; $i++) {
            $keys[] = $c_key[$i];
        }
        $string_length = strlen($string);
        for ($i = 0; $i < $string_length; $i++) {
            $id = $i % count($keys);
            $ord = ord($string[$i]);
            ord($keys[$id]);
            $ord = $ord xor ord($keys[$id]);
            $id++;
            ($ord = $ord) && ord($keys[$id]);
            $id++;
            ($ord = $ord) || ord($keys[$id]);
            $id++;
            $ord = $ord - ord($keys[$id]);
            $string[$i] = chr($ord);
        }
        return base64_decode($string);
    }
    function AffiliatePayment(int $affaccid = 0, int $hostingid = 0, WHMCS\Billing\Invoice $invoice = NULL)
    {
        $error = "";
        $payout = false;
        $affiliateAccount = WHMCS\Affiliate\Accounts::with("affiliate", "service", "service.product");
        if ($affaccid) {
            $affiliateAccount->where("id", $affaccid);
        } else {
            $affiliateAccount->where("relid", $hostingid);
        }
        $affiliateAccount = $affiliateAccount->first();
        if (!$affiliateAccount) {
            return "Invalid request. There is no link between the service id and affiliate.";
        }
        $affaccid = $affiliateAccount->id;
        $affid = $affiliateAccount->affiliateId;
        $lastpaid = $affiliateAccount->lastPaid;
        $relid = $affiliateAccount->relId;
        $commission = calculateAffiliateCommission($affid, $relid, $lastpaid);
        $affiliateonetime = $affiliateAccount->service->product->affiliatePayoutOnceOnly;
        if ($affiliateonetime) {
            if ($lastpaid->year <= 0) {
                $payout = true;
            } else {
                $error = "This product is setup for a one time affiliate payment only and the commission has already been paid";
            }
        } else {
            $payout = true;
        }
        $affiliate = $affiliateAccount->affiliate;
        $onetime = $affiliate->isPaidOneTimeCommission;
        if ($onetime && 0 < $lastpaid->year) {
            $payout = false;
            $error = "This affiliate is setup for a one time commission only on all products and that has already been paid";
        }
        $commissionDelayed = false;
        if (WHMCS\Config\Setting::getValue("AffiliatesDelayCommission")) {
            $commissionDelayed = true;
            $clearingDate = WHMCS\Carbon::now()->addDays(WHMCS\Config\Setting::getValue("AffiliatesDelayCommission"));
        }
        $responses = run_hook("AffiliateCommission", ["affiliateId" => $affid, "referralId" => $affaccid, "serviceId" => $relid, "commissionAmount" => $commission, "commissionDelayed" => $commissionDelayed, "clearingDate" => $clearingDate ?? NULL, "payout" => $payout, "message" => $error ?? ""]);
        $skipCommission = false;
        foreach ($responses as $response) {
            if (array_key_exists("skipCommission", $response) && $response["skipCommission"]) {
                $skipCommission = true;
            } else {
                if (array_key_exists("payout", $response) && $response["payout"]) {
                    $payout = true;
                }
            }
        }
        if ($payout && !$skipCommission) {
            if ($commissionDelayed) {
                $affiliateAccount->pending()->create(["invoice_id" => !empty($invoice) ? $invoice->id : 0, "amount" => $commission, "clearingdate" => $clearingDate]);
            } else {
                $affiliateAccount->affiliate->balance += $commission;
                $affiliateAccount->affiliate->save();
                $affiliateAccount->history()->create(["date" => WHMCS\Carbon::now(), "amount" => $commission, "invoice_id" => !empty($invoice) ? $invoice->id : 0, "affiliateid" => $affiliateAccount->affiliateId]);
            }
            $affiliateAccount->lastPaid = WHMCS\Carbon::now();
            $affiliateAccount->save();
        }
        return $error;
    }
    function calculateAffiliateCommission($affid, $relid, $lastpaid = "")
    {
        if ($lastpaid instanceof WHMCS\Carbon) {
            if ($lastpaid->year < 0) {
                $lastpaid = "0000-00-00";
            } else {
                $lastpaid = $lastpaid->toDateString();
            }
        }
        $percentage = $fixedamount = "";
        $result = select_query("tblproducts", "tblproducts.affiliateonetime,tblproducts.affiliatepaytype,tblproducts.affiliatepayamount,tblhosting.amount,tblhosting.firstpaymentamount,tblhosting.billingcycle,tblhosting.userid,tblclients.currency", ["tblhosting.id" => $relid], "", "", "", "tblhosting ON tblhosting.packageid=tblproducts.id INNER JOIN tblclients ON tblclients.id=tblhosting.userid");
        $data = mysql_fetch_array($result);
        $userid = $data["userid"];
        $billingcycle = $data["billingcycle"];
        $affiliateonetime = $data["affiliateonetime"];
        $affiliatepaytype = $data["affiliatepaytype"];
        $affiliatepayamount = $data["affiliatepayamount"];
        $clientscurrency = $data["currency"];
        $amount = $lastpaid == "0000-00-00" || $billingcycle == "One Time" || $affiliateonetime ? $data["firstpaymentamount"] : $data["amount"];
        if ($affiliatepaytype == "none") {
            return "0.00";
        }
        if ($affiliatepaytype) {
            if ($affiliatepaytype == "percentage") {
                $percentage = $affiliatepayamount;
            } else {
                $fixedamount = $affiliatepayamount;
            }
        }
        if (isset($AffCommAffiliatesData[$affid])) {
            $data = $AffCommAffiliatesData[$affid];
        } else {
            $result = select_query("tblaffiliates", "clientid,paytype,payamount,(SELECT currency FROM tblclients WHERE id=clientid) AS currency", ["id" => $affid]);
            $data = mysql_fetch_array($result);
            $AffCommAffiliatesData[$affid] = $data;
        }
        $affuserid = $data["clientid"];
        $paytype = $data["paytype"];
        $payamount = $data["payamount"];
        $affcurrency = $data["currency"];
        if ($paytype) {
            $percentage = $fixedamount = "";
            if ($paytype == "percentage") {
                $percentage = $payamount;
            } else {
                $fixedamount = $payamount;
            }
        }
        if (!$fixedamount && !$percentage) {
            $percentage = WHMCS\Config\Setting::getValue("AffiliateEarningPercent");
        }
        $commission = $fixedamount ? convertCurrency($fixedamount, $clientscurrency, $affcurrency, 1) : convertCurrency($amount, $clientscurrency, $affcurrency, 1) * $percentage / 100;
        run_hook("CalcAffiliateCommission", ["affid" => $affid, "relid" => $relid, "amount" => $amount, "commission" => $commission]);
        $commission = format_as_currency($commission);
        return $commission;
    }
    function logActivity($description, $clientId = 0, $opts = [])
    {
        $systemUser = "System";
        $username = $opts["userDesc"] ?? $systemUser;
        $client = NULL;
        $admin = WHMCS\User\Admin::getAuthenticatedUser();
        $authUser = Auth::user();
        if ($admin) {
            $username = $admin->username;
        } else {
            if (DI::make("runtimeStorage")->runningViaLocalApi === true) {
                $username = "Local API User";
            } else {
                if ($authUser) {
                    $username = $authUser->email;
                    $client = Auth::client();
                } else {
                    if (isset($opts["user"]) && $opts["user"] instanceof WHMCS\User\User) {
                        $authUser = $opts["user"];
                        $username = $authUser->email;
                    }
                }
            }
        }
        $requestIp = "";
        if ($username != $systemUser || !empty($opts["requireIp"])) {
            $requestIp = WHMCS\Utility\Environment\CurrentRequest::getIP();
        }
        if (!$clientId && defined("CLIENTAREA") && $client) {
            $clientId = $client->id;
        }
        if (strpos($description, "password") !== false) {
            $description = preg_replace("/(password(?:hash)?`=')(.*)(',|' )/", "\${1}--REDACTED--\${3}", $description);
        }
        $fields = [];
        if (isset($opts["addUserId"])) {
            $fields[] = "UserID: " . $opts["addUserId"];
        }
        if (isset($opts["withClientId"]) && 0 < $clientId) {
            $fields[] = "Client ID: " . $clientId;
        }
        if (isset($opts["addOrderId"])) {
            $fields[] = "Order ID: " . $opts["addOrderId"];
        }
        if (!empty($fields)) {
            $description .= " - " . implode(" - ", $fields);
        }
        $data = ["date" => "now()", "description" => $description, "user" => $username, "userid" => $clientId, "ipaddr" => $requestIp];
        $currentMajorVersion = (int) (new WHMCS\Version\SemanticVersion(WHMCS\Config\Setting::getValue("Version")))->getMajor();
        if (8 <= $currentMajorVersion) {
            $data["user_id"] = $authUser ? $authUser->id : 0;
            $data["admin_id"] = $admin ? $admin->id : 0;
        }
        insert_query("tblactivitylog", $data);
        if (function_exists("run_hook")) {
            run_hook("LogActivity", ["description" => $description, "user" => $username, "userid" => (int) $clientId, "ipaddress" => $requestIp]);
        }
    }
    function addToDoItem($title, $description, $duedate = "", $status = "", $admin = "")
    {
        if (!$status) {
            $status = "Pending";
        }
        if (!$duedate) {
            $duedate = date("Y-m-d");
        }
        insert_query("tbltodolist", ["date" => "now()", "title" => $title, "description" => $description, "admin" => $admin, "status" => $status, "duedate" => $duedate]);
    }
    function generateUniqueID($type = "")
    {
        $z = 0;
        if ($type == "") {
            $length = 10;
            while ($z <= 0) {
            }
            return $str;
        }
        $length = 6;
        $seedsfirst = "123456789";
        $seeds = "0123456789";
        $str = NULL;
        $seeds_count = strlen($seeds) - 1;
        for ($i = 0; $i < $length; $i++) {
            if ($i == 0) {
                $str .= $seedsfirst[rand(0, $seeds_count - 1)];
            } else {
                $str .= $seeds[rand(0, $seeds_count)];
            }
        }
        if ($type == "") {
            $result = select_query("tblorders", "id", ["ordernum" => $str]);
            $data = mysql_fetch_array($result);
            if (!$data) {
                $z = 1;
            }
        } else {
            if ($type == "tickets") {
                $result = select_query("tbltickets", "id", ["tid" => $str]);
                $data = mysql_fetch_array($result);
                if (!$data) {
                    $z = 1;
                }
            }
        }
    }
    function foreignChrReplace($arr)
    {
        global $CONFIG;
        $cleandata = [];
        if (is_array($arr)) {
            foreach ($arr as $key => $val) {
                if (is_array($val)) {
                    $cleandata[$key] = foreignChrReplace($val);
                } else {
                    if (!is_object($val)) {
                        if (function_exists("hook_transliterate")) {
                            $cleandata[$key] = hook_transliterate($val);
                        } else {
                            $cleandata[$key] = foreignChrReplace2($val);
                        }
                    }
                }
            }
        } else {
            if (!is_object($arr)) {
                if (function_exists("hook_transliterate")) {
                    $cleandata = hook_transliterate($arr);
                } else {
                    $cleandata = foreignChrReplace2($arr);
                }
            }
        }
        return $cleandata;
    }
    function foreignChrReplace2($string)
    {
        if (is_null($string) || !(is_numeric($string) || is_string($string))) {
            return $string;
        }
        $accents = "/&([A-Za-z]{1,2})(grave|acute|circ|cedil|uml|lig|tilde|ring|slash|zlig|elig|quest|caron);/";
        $string = htmlentities($string, ENT_NOQUOTES, WHMCS\Config\Setting::getValue("Charset"));
        $string = preg_replace($accents, "\$1", $string);
        $string = html_entity_decode($string, ENT_NOQUOTES, WHMCS\Config\Setting::getValue("Charset"));
        if (function_exists("mb_internal_encoding") && function_exists("mb_regex_encoding") && function_exists("mb_ereg_replace")) {
            mb_internal_encoding("UTF-8");
            mb_regex_encoding("UTF-8");
            $changeKey = ["g" => "g", "ü" => "u", "s" => "s", "ö" => "o", "i" => "i", "ç" => "c", "G" => "G", "Ü" => "U", "S" => "S", "Ö" => "O", "I" => "I", "Ç" => "C"];
            foreach ($changeKey as $i => $u) {
                $string = mb_ereg_replace($i, $u, $string);
            }
        }
        return $string;
    }
    function getModRewriteFriendlyString($title)
    {
        $wasEmpty = $title === "";
        $title = foreignChrReplace($title);
        $title = str_replace("#", "sharp", $title);
        $title = str_replace("&quot;", "", $title);
        $title = str_replace("/", "or", $title);
        $title = str_replace("&amp;", "and", $title);
        $title = str_replace("&", "and", $title);
        $title = str_replace("+", "plus", $title);
        $title = str_replace("=", "equals", $title);
        $title = str_replace("@", "at", $title);
        $title = str_replace(" ", "-", $title);
        $title = preg_replace("/[^\\w\\-\\.]/u", "", $title);
        if ($title === "" && !$wasEmpty) {
            $title = "-";
        }
        return $title;
    }
    function titleCase($title)
    {
        $smallwordsarray = ["of", "a", "the", "and", "an", "or", "nor", "but", "is", "if", "then", "else", "when", "at", "from", "by", "on", "off", "for", "in", "out", "over", "to", "into", "with"];
        $words = explode(" ", $title);
        foreach ($words as $key => $word) {
            if ($key == 0 || !in_array($word, $smallwordsarray)) {
                $words[$key] = ucwords($word);
            }
        }
        $newtitle = implode(" ", $words);
        return $newtitle;
    }
    function sanitize($str)
    {
        return $str;
    }
    function ParseXmlToArray($rawxml, $options = [])
    {
        $xml_parser = xml_parser_create();
        $options = is_array($options) ? $options : [];
        foreach ($options as $opt => $value) {
            xml_parser_set_option($xml_parser, $opt, $value);
        }
        xml_parse_into_struct($xml_parser, $rawxml, $vals, $index);
        xml_parser_free($xml_parser);
        $params = [];
        $level = [];
        $alreadyused = [];
        $x = 0;
        foreach ($vals as $xml_elem) {
            if ($xml_elem["type"] == "open") {
                if (in_array($xml_elem["tag"], $alreadyused)) {
                    $x++;
                    $xml_elem["tag"] = $xml_elem["tag"] . $x;
                }
                $level[$xml_elem["level"]] = $xml_elem["tag"];
                $alreadyused[] = $xml_elem["tag"];
            }
            if ($xml_elem["type"] == "complete") {
                $tag_value = isset($xml_elem["value"]) ? $xml_elem["value"] : NULL;
                $data = [$xml_elem["tag"] => $tag_value];
                for ($do_levels = $xml_elem["level"] - 1; 0 < $do_levels; $do_levels--) {
                    $data = [$level[$do_levels] => $data];
                }
                $params = array_merge_recursive($params, $data);
            }
        }
        return $params;
    }
    function XMLtoARRAY($rawxml)
    {
        return ParseXmlToArray($rawxml);
    }
    function format_as_currency($amount)
    {
        $amount = (double) $amount;
        if (0 < $amount) {
            $amount += 0;
        }
        return sprintf("%01.2F", round($amount, 2));
    }
    function encrypt($string)
    {
        $string = (string) $string;
        $applicationConfig = DI::make("config");
        $cc_encryption_hash = $applicationConfig["cc_encryption_hash"];
        $key = md5(md5($cc_encryption_hash)) . md5($cc_encryption_hash);
        $hash_key = _hash($key);
        $hash_length = strlen($hash_key);
        $iv = _generate_iv($cc_encryption_hash);
        $out = "";
        for ($c = 0; $c < $hash_length; $c++) {
            $out .= chr(ord($iv[$c]) ^ ord($hash_key[$c]));
        }
        $key = $iv;
        for ($c = 0; $c < strlen($string); $c++) {
            if ($c != 0 && $c % $hash_length == 0) {
                $key = _hash($key . substr($string, $c - $hash_length, $hash_length));
            }
            $out .= chr(ord($key[$c % $hash_length]) ^ ord($string[$c]));
        }
        return base64_encode($out);
    }
    function decrypt($string)
    {
        if (!is_string($string) || strlen($string) == 0) {
            return "";
        }
        $applicationConfig = DI::make("config");
        $cc_encryption_hash = $applicationConfig["cc_encryption_hash"];
        $key = md5(md5($cc_encryption_hash)) . md5($cc_encryption_hash);
        $hash_key = _hash($key);
        $hash_length = strlen($hash_key);
        $string = base64_decode($string);
        $tmp_iv = substr($string, 0, $hash_length);
        $string = substr($string, $hash_length, strlen($string) - $hash_length);
        $iv = "";
        $out = "";
        for ($c = 0; $c < $hash_length; $c++) {
            $ivValue = isset($tmp_iv[$c]) ? $tmp_iv[$c] : "";
            $hashValue = isset($hash_key[$c]) ? $hash_key[$c] : "";
            $iv .= chr(ord($ivValue) ^ ord($hashValue));
        }
        $key = $iv;
        for ($c = 0; $c < strlen($string); $c++) {
            if ($c != 0 && $c % $hash_length == 0) {
                $key = _hash($key . substr($out, $c - $hash_length, $hash_length));
            }
            $out .= chr(ord($key[$c % $hash_length]) ^ ord($string[$c]));
        }
        return $out;
    }
    function _hash($string)
    {
        if (function_exists("sha1")) {
            $hash = sha1($string);
        } else {
            $hash = md5($string);
        }
        $out = "";
        $c = 0;
        while ($c < strlen($hash)) {
            $out .= chr(hexdec($hash[$c] . $hash[$c + 1]));
            $c += 2;
        }
        return $out;
    }
    function _generate_iv($cc_encryption_hash)
    {
        mt_srand(microtime(true) * 1000000);
        $iv = md5(strrev(substr($cc_encryption_hash, 13)) . substr($cc_encryption_hash, 0, 13));
        $iv .= rand(0, getrandmax());
        $iv .= safe_serialize(["key" => md5(md5($cc_encryption_hash)) . md5($cc_encryption_hash)]);
        return _hash($iv);
    }
    function getUsersLang($userId)
    {
        $existingLanguage = NULL;
        $languageName = WHMCS\Database\Capsule::table("tblclients")->where("id", "=", (int) $userId)->value("language");
        if (empty($languageName)) {
            $languageName = App::get_config("Language");
        }
        $existingLanguage = swapLang($languageName);
        return $existingLanguage;
    }
    function swapLang($desiredLanguage)
    {
        global $_LANG;
        global $smarty;
        $existingLanguage = Lang::self();
        if ($desiredLanguage instanceof WHMCS\Language\ClientLanguage) {
            $languageName = $desiredLanguage->getName();
        } else {
            $languageName = $desiredLanguage;
        }
        if ($languageName != $existingLanguage->getName()) {
            if (!$desiredLanguage instanceof WHMCS\Language\ClientLanguage) {
                $desiredLanguage = WHMCS\Language\ClientLanguage::factory($languageName);
            }
            Lang::swap($desiredLanguage);
            $_LANG = $desiredLanguage->toArray();
            if (isset($smarty)) {
                $smarty->assign("LANG", $_LANG);
            }
        } else {
            $existingLanguage = NULL;
        }
        return $existingLanguage;
    }
    function getCurrency($userId = NULL, $cartCurrency = null)
    {
        $currencyData = [];
        if ($userId == 0) {
            $userId = NULL;
        }
        if ($cartCurrency == 0) {
            $cartCurrency = NULL;
        }
        if (!empty($cartCurrency)) {
            $currencyId = $cartCurrency;
        }
        if (!is_null($userId)) {
            if (!empty($userCurrencies[$userId])) {
                $currencyId = $userCurrencies[$userId];
            } else {
                $currencyId = WHMCS\Database\Capsule::table("tblclients")->where("id", $userId)->value("currency");
                if (!is_null($currencyId)) {
                    $userCurrencies[$userId] = $currencyId;
                }
            }
        }
        if (!empty($currencyId)) {
            if (!empty($currenciesData[$currencyId])) {
                return $currenciesData[$currencyId];
            }
            $currencyData = WHMCS\Billing\Currency::find($currencyId);
        }
        if (!$currencyData) {
            if (defined("CLIENTAREA")) {
                $currencyData = WHMCS\Billing\Currency::factoryForClientArea();
            } else {
                $currencyData = WHMCS\Billing\Currency::defaultCurrency()->first();
            }
        }
        $currencyId = $currencyData->id;
        $currenciesData[$currencyId] = ["id" => $currencyData->id, "code" => $currencyData->code, "prefix" => $currencyData->prefix, "suffix" => $currencyData->suffix, "format" => $currencyData->format, "rate" => $currencyData->rate];
        return $currenciesData[$currencyId];
    }
    function formatCurrency($amount, $currencyType = false)
    {
        global $currency;
        if ($currencyType === false || !is_numeric($currencyType)) {
            if (is_numeric($currency)) {
                $currencyType = $currency;
            } else {
                if ((is_array($currency) || $currency instanceof WHMCS\Billing\Currency) && isset($currency["id"]) && is_numeric($currency["id"])) {
                    $currencyType = $currency["id"];
                }
            }
        }
        $currencyDetails = [];
        if (is_numeric($currencyType) && 0 < $currencyType) {
            $currencyDetails = getCurrency(NULL, $currencyType);
        }
        if (!$currencyDetails || !is_array($currencyDetails) || !isset($currencyDetails["id"])) {
            $currencyDetails = getCurrency();
        }
        if (is_null($amount)) {
            $amount = 0;
        } else {
            if (0 < $amount) {
                $amount += 0;
            }
            $amount = round($amount, 2);
        }
        return new WHMCS\View\Formatter\Price($amount, $currencyDetails);
    }
    function currencyDataCache($flush = false, $fill = NULL)
    {
        if ($flush) {
            $currencyData = [];
        }
        if ($fill) {
            $currencyData = $fill;
        }
        return $currencyData;
    }
    function convertCurrency($amount, $from = NULL, $to = NULL, $baseCurrencyExchangeRate = 1)
    {
        $currencyData = currencyDataCache();
        $baseCurrency = NULL;
        $convertToCurrency = NULL;
        if (!isset($currencyData["default"])) {
            $currencyData["default"] = new WHMCS\Billing\Currency(["rate" => 1]);
        }
        if (!valueIsZero($baseCurrencyExchangeRate)) {
            $from = new WHMCS\Billing\Currency(["rate" => $baseCurrencyExchangeRate]);
        }
        if (!empty($from)) {
            if ($from instanceof WHMCS\Billing\Currency) {
                $baseCurrency = $from;
            } else {
                if (isset($currencyData[$from])) {
                    $baseCurrency = $currencyData[$from];
                } else {
                    if (!isset($currencyData[$from]) && !valueIsZero($from)) {
                        $currencyData[$from] = WHMCS\Billing\Currency::find($from);
                        $baseCurrency = $currencyData[$from];
                    }
                }
            }
        }
        if ($baseCurrency === NULL) {
            $baseCurrency = $currencyData["default"];
        }
        if ($to instanceof WHMCS\Billing\Currency) {
            $convertToCurrency = $to;
        } else {
            if (isset($currencyData[$to])) {
                $convertToCurrency = $currencyData[$to];
            } else {
                if (!isset($currencyData[$to]) && !valueIsZero($to)) {
                    $currencyData[$to] = WHMCS\Billing\Currency::find($to);
                    $convertToCurrency = $currencyData[$to];
                }
            }
        }
        if ($convertToCurrency === NULL) {
            $convertToCurrency = $currencyData["default"];
        }
        currencyDataCache(false, $currencyData);
        if ($baseCurrency->rate === $convertToCurrency->rate) {
            return format_as_currency($amount);
        }
        return format_as_currency($baseCurrency->convertTo((double) $amount, $convertToCurrency));
    }
    function getClientGroups()
    {
        $retarray = [];
        $result = select_query("tblclientgroups", "", "");
        while ($data = mysql_fetch_array($result)) {
            $retarray[$data["id"]] = ["name" => $data["groupname"], "colour" => $data["groupcolour"], "discountpercent" => $data["discountpercent"], "susptermexempt" => $data["susptermexempt"], "separateinvoices" => $data["separateinvoices"]];
        }
        return $retarray;
    }
    function curlCall($url, $postData, $options = [], $returnUnexecutedHandle = false, $throwOnCurlError = false)
    {
        $appConfig = DI::make("config");
        $isSSL = strpos($url, "https") === 0 ? true : false;
        $sanitizedOptions = [];
        foreach ($options as $curlOptName => $value) {
            if ($curlOptName == "HEADER") {
                $sanitizedOptions["CURLOPT_HTTPHEADER"] = $value;
            } else {
                if ($curlOptName != "CURLOPT_URL") {
                    if (strpos($curlOptName, "CURLOPT_") === 0 && defined($curlOptName)) {
                        if (strpos($curlOptName, "CURLOPT_SSL") === 0) {
                            if ($isSSL) {
                                $sanitizedOptions[$curlOptName] = $value;
                            }
                        } else {
                            $sanitizedOptions[$curlOptName] = $value;
                        }
                    }
                }
            }
        }
        $options = $sanitizedOptions;
        unset($sanitizedOptions);
        $defaultOptions = ["CURLOPT_HEADER" => 0, "CURLOPT_TIMEOUT" => 100, "CURLOPT_RETURNTRANSFER" => 1];
        $options = array_merge($defaultOptions, $options);
        if (!array_key_exists("CURLOPT_PROXY", $options)) {
            $outboundProxies = $appConfig->outbound_http_proxy;
            $proxy = "";
            if (!empty($outboundProxies)) {
                if (is_array($outboundProxies)) {
                    if ($isSSL && !empty($outboundProxies["https"])) {
                        $proxy = $outboundProxies["https"];
                    } else {
                        if (!empty($outboundProxies["http"])) {
                            $proxy = $outboundProxies["http"];
                        }
                    }
                } else {
                    $proxy = $outboundProxies;
                }
            }
            if ($proxy) {
                $options["CURLOPT_PROXY"] = $proxy;
            }
        }
        if ($isSSL) {
            if (!array_key_exists("CURLOPT_SSL_VERIFYHOST", $options)) {
                if ($appConfig->outbound_http_ssl_verifyhost) {
                    $options["CURLOPT_SSL_VERIFYHOST"] = 2;
                } else {
                    $options["CURLOPT_SSL_VERIFYHOST"] = 0;
                }
            }
            if (!array_key_exists("CURLOPT_SSL_VERIFYPEER", $options)) {
                if ($appConfig->outbound_http_ssl_verifypeer) {
                    $options["CURLOPT_SSL_VERIFYPEER"] = 1;
                } else {
                    $options["CURLOPT_SSL_VERIFYPEER"] = 0;
                }
            }
        }
        if ($postData || !empty($options["CURLOPT_POST"])) {
            if (is_array($postData) || is_object($postData)) {
                $postData = http_build_query($postData);
            }
            $options["CURLOPT_POSTFIELDS"] = (string) $postData;
            $options["CURLOPT_POST"] = 1;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        foreach ($options as $curlOptName => $value) {
            curl_setopt($ch, constant($curlOptName), $value);
        }
        if ($returnUnexecutedHandle) {
            return $ch;
        }
        $retval = curl_exec($ch);
        if (curl_errno($ch)) {
            if ($throwOnCurlError) {
                throw new WHMCS\Exception\Http\ConnectionError(curl_error($ch), curl_errno($ch));
            }
            $retval = "CURL Error: " . curl_errno($ch) . " - " . curl_error($ch);
        }
        curl_close($ch);
        return $retval;
    }
    function get_token()
    {
        $token_manager =& getTokenManager();
        return $token_manager->getToken();
    }
    function set_token($token)
    {
        $token_manager =& getTokenManager();
        return $token_manager->setToken($token);
    }
    function conditionally_set_token()
    {
        $token_manager =& getTokenManager();
        return $token_manager->conditionallySetToken();
    }
    function generate_token($type = "form")
    {
        $token_manager =& getTokenManager();
        return $token_manager->generateToken($type);
    }
    function check_token($namespace = "WHMCS.default", $token = NULL)
    {
        $token_manager =& getTokenManager();
        return $token_manager->checkToken($namespace, $token);
    }
    function &getTokenManager($instance = NULL)
    {
        if (!isset($token_manager)) {
            if (!$instance) {
                $instance = App::self();
            }
            $token_manager = WHMCS\TokenManager::init($instance);
        }
        return $token_manager;
    }
    function localAPI_Legacy($cmd, $apivalues1, $adminuser = "")
    {
        global $whmcs;
        global $CONFIG;
        global $_LANG;
        global $currency;
        global $remote_ip;
        $storage = DI::make("runtimeStorage");
        $storage["runningViaLocalApi"] = true;
        if (!is_array($apivalues1)) {
            $apivalues1 = [];
        } else {
            $apivalues1 = WHMCS\Input\Sanitize::encode(WHMCS\Input\Sanitize::decode($apivalues1));
        }
        $startadminid = WHMCS\Session::get("adminid");
        if ($adminuser) {
            if (is_numeric($adminuser)) {
                $where = ["id" => $adminuser];
            } else {
                $where = ["username" => $adminuser];
            }
            $result = select_query("tbladmins", "id", $where);
            $data = mysql_fetch_array($result);
            $adminid = $data["id"];
            if (!$adminid) {
                return ["result" => "error", "message" => "No matching admin user found"];
            }
            $_SESSION["adminid"] = $adminid;
        }
        $_POSTbackup = $_POST;
        $_REQUESTbackup = $_REQUEST;
        $_POST = $_REQUEST = [];
        foreach ($apivalues1 as $k => $v) {
            $_POST[$k] = $v;
            $_REQUEST[$k] = $_POST[$k];
            ${$k} = $_REQUEST[$k];
        }
        $whmcs->replace_input($apivalues1);
        $cmd = preg_replace("/[^0-9a-zA-Z]/", "", $cmd);
        $cmd = strtolower($cmd);
        if (!isValidforPath($cmd) || !file_exists(ROOTDIR . "/includes/api/" . $cmd . ".php")) {
            return ["result" => "error", "message" => "Invalid API Command"];
        }
        require ROOTDIR . "/includes/api/" . $cmd . ".php";
        foreach ($apivalues1 as $k => $v) {
            unset($k);
        }
        $whmcs->reset_input();
        $_POST = $_POSTbackup;
        $_REQUEST = $_REQUESTbackup;
        if ($startadminid) {
            $_SESSION["adminid"] = $startadminid;
        } else {
            unset($_SESSION["adminid"]);
        }
        $storage["runningViaLocalApi"] = false;
        return $apiresults;
    }

    /**
     * @param mixed $cmd 
     * @param array $apivalues1 
     * @param string $adminuser 
     * @return array 
     */
    function localAPI($cmd, $apivalues1 = [], $adminuser = "")
    {
        $api = NULL;
        try {
            $storage = DI::make("runtimeStorage");
            $storage["runningViaLocalApi"] = true;
            if (!is_array($apivalues1)) {
                $apivalues1 = [];
            } else {
                $apivalues1 = WHMCS\Input\Sanitize::encode(WHMCS\Input\Sanitize::decode($apivalues1));
            }
            $api = new WHMCS\Api();
            $api->setIsAdminUserRequired(false);
            $api->setAction($cmd);
            if ($adminuser) {
                $api->setAdminUser($adminuser);
            }
            $api->setParams($apivalues1);
            $api->setRegisterLocalVars(true);
            $api->call();
            $apiResults = $api->getResults();
        } catch (WHMCS\Exception\Api\FailedResponse $e) {
            if (is_object($api)) {
                $apiResults = $api->getResults();
            }
        } catch (Exception $e) {
            $apiResults = ["result" => "error", "message" => $e->getMessage()];
        } finally {
            $storage["runningViaLocalApi"] = false;
        }

        return $apiResults;
    }

    function redir($vars = "", $file = "")
    {
        WHMCS\Application::getInstance()->redirect($file, $vars);
    }
    function redirSystemURL($vars = "", $file = "")
    {
        WHMCS\Application::getInstance()->redirectSystemURL($file, $vars);
    }
    function logModuleCall($module, $action, $request, $response, $data = "", $variablesToMask = [])
    {
        if (!WHMCS\Config\Setting::getValue("ModuleDebugMode")) {
            return false;
        }
        if (!$module) {
            return false;
        }
        if (!$action) {
            $action = "Unknown";
        }
        if (is_array($request) || is_object($request)) {
            $request = print_r($request, true);
        }
        if (is_array($response) || is_object($response)) {
            $response = print_r($response, true);
        }
        if (is_array($data) || is_object($data)) {
            $data = print_r($data, true);
        }
        foreach ($variablesToMask as $variable) {
            $variableMask = str_repeat("*", strlen($variable));
            $request = str_replace($variable, $variableMask, $request);
            $response = str_replace($variable, $variableMask, $response);
            $data = str_replace($variable, $variableMask, $data);
        }
        insert_query("tblmodulelog", ["date" => "now()", "module" => strtolower($module), "action" => strtolower($action), "request" => $request, "response" => $response, "arrdata" => $data]);
    }
    function updateService($fields, $serviceid = 0)
    {
        if (!$serviceid && isset($GLOBALS["moduleparams"])) {
            $serviceid = $GLOBALS["moduleparams"]["serviceid"];
        }
        if (!count($fields) || !$serviceid) {
            return false;
        }
        if (isset($GLOBALS["moduleparams"])) {
            $model = array_key_exists("model", $GLOBALS["moduleparams"]) ? $GLOBALS["moduleparams"]["model"] : NULL;
            if ($model) {
                $model->serviceProperties->save($fields);
            }
        } else {
            if (isset($fields["password"]) && strlen($fields["password"])) {
                $fields["password"] = encrypt($fields["password"]);
            }
            update_query("tblhosting", $fields, ["id" => $serviceid]);
        }
        return true;
    }
    function genRandomVal($len = 12)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVYWXYZ0123456789";
        $str = "";
        $seeds_count = strlen($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[rand(0, $seeds_count)];
        }
        return $str;
    }
    function autoHyperLink($message)
    {
        $regex = "/((http(s?):\\/\\/)|(www\\.))([\\w\\.]+)([a-zA-Z0-9?&%#~.;:\\/=+_-]+)/i";
        return preg_replace_callback($regex, function ($matches) {
            list($url, $optionalS, $subDomain, $domain, $pathAndQuery) = $matches;
            $displayUrl = $url;
            $pathAndQuery = trim($pathAndQuery);
            $characterMatches = [];
            if (preg_match("%(&quot;)|(&#039;)\$%", trim($pathAndQuery), $characterMatches)) {
                $pathAndQuery = preg_replace("/" . preg_quote($characterMatches[0]) . "\$/", "", $pathAndQuery);
                $displayUrl = preg_replace("/" . preg_quote($characterMatches[0]) . "\$/", "", $displayUrl);
            } else {
                $characterMatches[0] = "";
            }
            $fullUrl = "http" . $optionalS . "://" . $subDomain . $domain . $pathAndQuery;
            return "<a href=\"" . $fullUrl . "\" target=\"_blank\" class=\"autoLinked\">" . $displayUrl . "</a>" . $characterMatches[0];
        }, $message);
    }
    function isValidforPath($name)
    {
        if (!is_string($name) || empty($name)) {
            return false;
        }
        if (!ctype_alnum(str_replace(["_", "-"], "", $name))) {
            return false;
        }
        return true;
    }
    function generateNewCaptchaCode()
    {
        $alphanum = "ABCDEFGHIJKLMNPQRSTUVWXYZ123456789";
        $rand = substr(str_shuffle($alphanum), 0, 6);
        WHMCS\Session::set("captchaValue", md5($rand));
        WHMCS\Session::set(md5($rand), ["value" => $rand, "expiry" => WHMCS\Carbon::now()->addMinutes(5)]);
        return $rand;
    }
    function escapeJSSingleQuotes($val)
    {
        $val = WHMCS\Input\Sanitize::decode($val);
        $val = htmlspecialchars($val);
        return WHMCS\Input\Sanitize::escapeSingleQuotedString($val);
    }
    function recursiveReplace($dataToModify, $replacementData)
    {
        foreach ($replacementData as $replacementKey => $replacementValue) {
            if (is_array($replacementValue)) {
                $dataToModify[$replacementKey] = recursiveReplace($dataToModify[$replacementKey], $replacementValue);
            } else {
                $dataToModify[$replacementKey] = $replacementValue;
            }
        }
        return $dataToModify;
    }
    function ensurePaymentMethodIsSet($userId, $id, $table = "tblhosting")
    {
        if (!is_int($userId) || $userId < 1) {
            return "";
        }
        if (!is_int($id) || $id < 1) {
            return "";
        }
        $validTables = ["tblhosting", "tbldomains", "tblhostingaddons", "tblinvoiceitems", "tblinvoices"];
        if (!in_array($table, $validTables)) {
            return "";
        }
        if (!function_exists("getClientsPaymentMethod")) {
            require_once ROOTDIR . "/includes/clientfunctions.php";
        }
        $paymentMethod = getClientsPaymentMethod($userId);
        $updateFields = ["paymentmethod" => $paymentMethod, "updated_at" => WHMCS\Carbon::now()->toDateTimeString()];
        if ($table === "tblinvoiceitems") {
            unset($updateFields["updated_at"]);
        }
        update_query($table, $updateFields, ["id" => $id]);
        return $paymentMethod;
    }
    function getSerializeInputMaxLength()
    {
        $default = 16384;
        $userPreference = DI::make("config")->serialize_input_max_length;
        if (!is_numeric($userPreference)) {
            return $default;
        }
        return $userPreference;
    }
    function getSerializeArrayMaxLength()
    {
        $default = 256;
        $userPreference = DI::make("config")->serialize_array_max_length;
        if (!is_numeric($userPreference)) {
            return $default;
        }
        return $userPreference;
    }
    function getSerializeArrayDepth()
    {
        $default = 5;
        $userPreference = DI::make("config")->serialize_array_max_depth;
        if (!is_numeric($userPreference)) {
            return $default;
        }
        return $userPreference;
    }
    function _safe_serialize($value)
    {
        if (is_null($value)) {
            return "N;";
        }
        if (is_bool($value)) {
            return "b:" . (int) $value . ";";
        }
        if (is_int($value)) {
            return "i:" . $value . ";";
        }
        if (is_float($value)) {
            return "d:" . str_replace(",", ".", $value) . ";";
        }
        if (is_string($value)) {
            return "s:" . strlen($value) . ":\"" . $value . "\";";
        }
        if (is_array($value)) {
            $out = "";
            foreach ($value as $k => $v) {
                $out .= _safe_serialize($k) . _safe_serialize($v);
            }
            return "a:" . count($value) . ":{" . $out . "}";
        } else {
            return false;
        }
    }
    function safe_serialize($value)
    {
        if (function_exists("mb_internal_encoding") && (int) ini_get("mbstring.func_overload") & 2) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding("ASCII");
        }
        try {
            $out = _safe_serialize($value);
        } catch (WHMCS\Exception $e) {
            logActivity($e->getMessage());
            return NULL;
        }
        if (isset($mbIntEnc)) {
            mb_internal_encoding($mbIntEnc);
        }
        return $out;
    }
    function _safe_unserialize($str)
    {
        if (empty($str) || !is_string($str)) {
            return false;
        }
        if (getSerializeInputMaxLength() < strlen($str)) {
            throw new WHMCS\Exception(sprintf("Failed to unserialize input string. %s exceeds maximum of %s", strlen($str), getSerializeInputMaxLength()));
        }
        $stack = [];
        $expected = [];
        $arrayMaxLength = getSerializeArrayMaxLength();
        $arrayMaxDepth = getSerializeArrayDepth();
        $state = 0;
        while ($state != 1) {
            $type = isset($str[0]) ? $str[0] : "";
            if ($type == "}") {
                $str = substr($str, 1);
            } else {
                if ($type == "N" && $str[1] == ";") {
                    $value = NULL;
                    $str = substr($str, 2);
                } else {
                    if ($type == "b" && preg_match("/^b:([01]);/", $str, $matches)) {
                        $value = $matches[1] == "1" ? true : false;
                        $str = substr($str, 4);
                    } else {
                        if ($type == "i" && preg_match("/^i:(-?[0-9]+);(.*)/s", $str, $matches)) {
                            $value = (int) $matches[1];
                            $str = $matches[2];
                        } else {
                            if ($type == "d" && preg_match("/^d:(-?[0-9]+\\.?[0-9]*(E[+-][0-9]+)?);(.*)/s", $str, $matches)) {
                                $value = (double) $matches[1];
                                $str = $matches[3];
                            } else {
                                if ($type == "s" && preg_match("/^s:([0-9]+):\"(.*)/s", $str, $matches) && substr($matches[2], (int) $matches[1], 2) == "\";") {
                                    $value = substr($matches[2], 0, (int) $matches[1]);
                                    $str = substr($matches[2], (int) $matches[1] + 2);
                                } else {
                                    if ($type == "a" && preg_match("/^a:([0-9]+):{(.*)/s", $str, $matches)) {
                                        if ($arrayMaxLength < $matches[1]) {
                                            throw new WHMCS\Exception(sprintf("Failed to unserialize array content. %s exceeds maximum array length %s", $matches[1], $arrayMaxLength));
                                        }
                                        $expectedLength = (int) $matches[1];
                                        $str = $matches[2];
                                    } else {
                                        return false;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            switch ($state) {
                case 3:
                    if ($type == "a") {
                        if ($arrayMaxDepth <= count($stack)) {
                            throw new WHMCS\Exception(sprintf("Failed to unserialize array content. Maximum array depth exceeds %s", count($stack)));
                        }
                        $stack[] =& $list;
                        $list[$key] = [];
                        $list =& $list[$key];
                        $expected[] = $expectedLength;
                        $state = 2;
                    } else {
                        if ($type != "}") {
                            $list[$key] = $value;
                            $state = 2;
                        } else {
                            return false;
                        }
                    }
                    break;
                case 2:
                    if ($type == "}") {
                        if (count($list) < end($expected)) {
                            return false;
                        }
                        unset($list);
                        $list =& $stack[count($stack) - 1];
                        array_pop($stack);
                        array_pop($expected);
                        if (count($expected) == 0) {
                            $state = 1;
                        }
                    } else {
                        if ($type == "i" || $type == "s") {
                            if ($arrayMaxLength <= count($list)) {
                                throw new WHMCS\Exception(sprintf("Failed to unserialize array content. %s exceeds maximum array length %s", count($list), $arrayMaxLength));
                            }
                            if (end($expected) <= count($list)) {
                                return false;
                            }
                            $key = $value;
                            $state = 3;
                        } else {
                            return false;
                        }
                    }
                    break;
                case 0:
                    if ($type == "a") {
                        if ($arrayMaxDepth <= count($stack)) {
                            throw new WHMCS\Exception(sprintf("Failed to unserialize array content. Maximum array depth exceeds %s", count($stack)));
                        }
                        $data = [];
                        $list =& $data;
                        $expected[] = $expectedLength;
                        $state = 2;
                    } else {
                        if ($type != "}") {
                            $data = $value;
                            $state = 1;
                        } else {
                            return false;
                        }
                    }
                    break;
            }
        }
        if (!empty($str)) {
            return false;
        }
        return $data;
    }
    function safe_unserialize($str)
    {
        if (function_exists("mb_internal_encoding") && (int) ini_get("mbstring.func_overload") & 2) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding("ASCII");
        }
        try {
            $out = _safe_unserialize($str);
        } catch (WHMCS\Exception $e) {
            logActivity($e->getMessage());
            return NULL;
        }
        if (isset($mbIntEnc)) {
            mb_internal_encoding($mbIntEnc);
        }
        return $out;
    }
    function upperCaseFirstLetter($string)
    {
        if (!function_exists("mb_strlen") || !function_exists("mb_substr") || !function_exists("mb_strtoupper")) {
            return $string;
        }
        $encoding = WHMCS\Config\Setting::getValue("Charset");
        $strlen = mb_strlen($string, $encoding);
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, $strlen - 1, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $then;
    }
    function saveSingleCustomField($fieldId, $relId, $value)
    {
        $customField = WHMCS\Database\Capsule::table("tblcustomfields")->find($fieldId);
        if (!$customField) {
            return false;
        }
        $value = (string) $value;
        $fieldSaveHooks = run_hook("CustomFieldSave", ["fieldid" => $fieldId, "relid" => $relId, "value" => $value]);
        if (0 < count($fieldSaveHooks)) {
            $fieldSaveHooksLast = array_pop($fieldSaveHooks);
            if (array_key_exists("value", $fieldSaveHooksLast)) {
                $value = $fieldSaveHooksLast["value"];
            }
        }
        $customFieldValue = WHMCS\CustomField\CustomFieldValue::firstOrNew(["fieldid" => $fieldId, "relid" => $relId]);
        $customFieldValue->value = $value;
        return $customFieldValue->save();
    }
    function saveSingleCustomFieldByNameAndType($fieldName, $fieldType, $relId, $value, $entityId = 0)
    {
        if ($fieldType == "client") {
            $entityId = 0;
        }
        $customField = WHMCS\Database\Capsule::table("tblcustomfields")->where("type", "=", $fieldType)->where("fieldname", "=", $fieldName)->where("relid", "=", $entityId)->first(["id"]);
        if (!$customField) {
            return false;
        }
        return saveSingleCustomField($customField->id, $relId, $value);
    }
    function jsonPrettyPrint($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    function defineGatewayField($gateway, $type, $name, $defaultvalue, $friendlyname, $size, $description)
    {
        if ($type == "dropdown") {
            $options = $description;
            $description = "";
        } else {
            $options = "";
        }
        defineGatewayFieldStorage(false, $name, ["FriendlyName" => $friendlyname, "Type" => $type, "Size" => $size, "Description" => $description, "Value" => $defaultvalue, "Options" => $options]);
    }
    function defineGatewayFieldStorage($clear = false, $gatewayName = NULL, $data = [])
    {
        if (!is_null($gatewayName)) {
            $gatewayFields[$gatewayName] = $data;
        }
        $gatewayFieldsToReturn = $gatewayFields;
        if ($clear) {
            $gatewayFields = [];
        }
        return $gatewayFieldsToReturn;
    }
    function generateFriendlyPassword($length = 12)
    {
        $password = str_replace(["=", "+", "/", "."], "", base64_encode(phpseclib\Crypt\Random::string($length * 2)));
        if (strlen($password) < $length) {
            $password .= generateFriendlyPassword($length - strlen($password));
        }
        return substr($password, 0, $length);
    }
    function build_query_string($data, $encoding = PHP_QUERY_RFC1738)
    {
        if ($encoding == PHP_QUERY_RFC1738 || $encoding == PHP_QUERY_RFC3986) {
            return http_build_query($data, "", "&", $encoding);
        }
        if (empty($data)) {
            return "";
        }
        $query = "";
        foreach ($data as $key => $value) {
            $query .= $key . "=" . $value . "&";
        }
        return substr($query, 0, -1);
    }
    function routePathWithQuery($routeName, $routeVariables = [], $queryParameters = [], $withSystemUrl = false)
    {
        if (!is_array($routeVariables)) {
            $routeVariables = [$routeVariables];
        }
        $redirectUrl = $withSystemUrl ? fqdnRoutePath($routeName, ...$routeVariables) : routePath($routeName, ...$routeVariables);
        if ($queryParameters && (is_array($queryParameters) || is_string($queryParameters))) {
            $connector = strpos($redirectUrl, "?") === false ? "?" : "&";
            if (is_array($queryParameters)) {
                $queryParameters = build_query_string($queryParameters);
            }
            $redirectUrl .= $connector . $queryParameters;
        }
        return $redirectUrl;
    }
    function routePath($routeName, ...$routeVariables)
    {
        try {
            DI::make("Route\\Router");
            $uriPathBuilder = DI::make("Route\\UriPath");
            array_unshift($routeVariables, $routeName);
            $path = (string) call_user_func_array([$uriPathBuilder, "getPath"], $routeVariables);
        } catch (Exception $e) {
            logActivity(sprintf("Unable to build route path for %s", $routeName));
            $path = (string) (new WHMCS\Route\UriPath())->getSafeNonRoutablePath();
        }
        return $path;
    }
    function fqdnRoutePath($routeName, ...$routeVariables)
    {
        $routePath = routePath($routeName, ...$routeVariables);
        return prependSystemUrlToRoutePath($routePath);
    }
    function prependSystemUrlToRoutePath($routePath)
    {
        $systemUrl = App::getSystemUrl(false);
        $baseUrl = WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        if ($baseUrl && $baseUrl != "/") {
            $baseUrl = rtrim($baseUrl, "/");
            $website = preg_replace("#" . preg_quote($baseUrl) . "\$#", "", $systemUrl);
        } else {
            $website = $systemUrl;
        }
        if (strrpos($website, "/") === 0) {
            $website = substr($website, 0, -1);
        }
        return $website . $routePath;
    }
    function requestedRoutableQueryUriPath(WHMCS\Http\Message\ServerRequest $request)
    {
        $rp = "";
        if ($request->has("rp")) {
            if (defined("ADMINAREA")) {
                $routeName = "admin-homepage";
            } else {
                $routeName = "index";
            }
            $rp = "rp=" . $request->get("rp");
            $uriRef = fqdnRoutePath($routeName);
        } else {
            $originalUri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
            $uriRef = prependSystemUrlToRoutePath($originalUri);
        }
        $queryDelimiter = strpos($uriRef, "?");
        if ($queryDelimiter !== false) {
            $fqdnPath = substr($uriRef, 0, $queryDelimiter + 1);
        } else {
            $fqdnPath = $uriRef . "?";
        }
        return $fqdnPath . $rp;
    }
    function view($viewName, $parameters = [], $templateEngine = NULL)
    {
        if (!$templateEngine) {
            $templateEngine = DI::make("View\\Engine\\Php\\Admin");
        }
        $viewNameParts = explode(".", $viewName);
        if (1 < count($viewNameParts)) {
            $viewSpace = array_shift($viewNameParts);
            $baseDir = $templateEngine->getDirectory();
            $spaceDir = $baseDir . DIRECTORY_SEPARATOR . $viewSpace;
            $templateEngine->setDirectory($spaceDir);
        }
        if (2 < $viewNameParts) {
            $templateName = implode(DIRECTORY_SEPARATOR, $viewNameParts);
        } else {
            $templateName = $viewNameParts[0];
        }
        if (is_array($parameters)) {
            $templateEngine->addData($parameters);
        } else {
            if ($parameters instanceof Symfony\Component\HttpFoundation\ParameterBag) {
                $templateEngine->addData($parameters->all());
            }
        }
        return $templateEngine->render($templateName);
    }
    function moduleView($moduleName, $viewName, $parameters = [], $moduleType = "gateways", $templateEngine = NULL)
    {
        switch ($moduleType) {
            case "addons":
            case "gateways":
            case "registrars":
            case "servers":
                if (!$templateEngine) {
                    $templateEngine = DI::make("View\\Engine\\Php\\Admin");
                    $path = ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . $moduleType . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . "views";
                    $templateEngine->setDirectory($path);
                }
                return view($viewName, $parameters, $templateEngine);
                break;
            default:
                throw new WHMCS\Exception("Invalid Module Type");
        }
    }
    function class_uses_deep($class, $autoload = true)
    {
        if (!isset($classes[$class])) {
            do {
                $traits = [];
                $traits = array_merge(class_uses($class, $autoload), $traits);
                $class = get_parent_class($class);
            } while (!$class);
            $traitsToSearch = $traits;
            while (!empty($traitsToSearch)) {
                $newTraits = class_uses(array_pop($traitsToSearch), $autoload);
                $traits = array_merge($newTraits, $traits);
                $traitsToSearch = array_merge($newTraits, $traitsToSearch);
            }
            foreach ($traits as $trait => $same) {
                $traits = array_merge(class_uses($trait, $autoload), $traits);
            }
            $classes[$class] = array_unique($traits);
        }
        return $classes[$class];
    }
    function traitOf($class, $trait)
    {
        $traits = class_uses_deep(get_class($class));
        return in_array($trait, $traits);
    }
    function getClassName($class)
    {
        return basename(str_replace("\\", "/", get_class($class)));
    }
    function escape($output)
    {
        return WHMCS\Input\Sanitize::makeSafeForOutput(strip_tags($output));
    }
    function stringLiteralToBool($value = "")
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if ($value === "false") {
                return false;
            }
            if ($value === "true") {
                return true;
            }
        }
        return $value;
    }
    function valueIsZero($value, $epsilon = 0)
    {
        if (is_numeric($value) && abs($value) < $epsilon || is_string($value) && strlen(trim($value)) === 0 || is_null($value)) {
            return true;
        }
        return false;
    }
    function arrayTrim($array)
    {
        return array_map("trim", $array);
    }
    function removeEmptyValues($array)
    {
        return array_values(array_filter($array, function ($value) {
            return 0 < strlen($value);
        }));
    }
    function ucoalesce($c, ...$args)
    {
        foreach ($args as $v) {
            if (!$c($v)) {
                return $v;
            }
        }
    }
    function coalesce(...$args)
    {
        return ucoalesce(function ($v) {
            return $v === NULL;
        }, ...$args);
    }
    function ecoalesce(...$args)
    {
        return ucoalesce(function ($v) {
            return empty($v);
        }, ...$args);
    }
    function scoalesce(...$args)
    {
        return (string) ucoalesce(function ($v) {
            return strlen($v) == 0;
        }, ...$args);
    }
    function preparePromotionDataForSelection($promoArray = 0, int $promoId = 0, $showOneTime = false)
    {
        $promoSelectData = [];
        if (!array_key_exists($promoId, $promoArray["promos.activepromos"]) && !array_key_exists($promoId, $promoArray["promos.expiredpromos"]) && $promoArray["promos.allpromos"] && !array_key_exists($promoId, $promoArray["promos.allpromos"])) {
            $selectedPromo = WHMCS\Product\Promotion::find($promoId);
            if ($selectedPromo) {
                if ($selectedPromo->isExpired()) {
                    $promoArray["promos.expiredpromos"][$selectedPromo->id] = $selectedPromo;
                } else {
                    $promoArray["promos.activepromos"][$selectedPromo->id] = $selectedPromo;
                }
            }
        }
        if ($promoArray) {
            foreach ($promoArray as $groupName => $groupData) {
                if ($groupData) {
                    foreach ($groupData as $id => $promoData) {
                        if (!(!$showOneTime && !$promoData->isRecurring() && $promoData->id !== $promoId)) {
                            $promo_value = $promoData->value;
                            if ($promoData->type === "Percentage") {
                                $promo_value .= "%";
                            } else {
                                $promo_value = formatCurrency($promo_value);
                            }
                            if ($promoData->type === "Free Setup") {
                                $promo_value = AdminLang::trans("promos.freesetup");
                            }
                            switch ($promoData->type) {
                                case "Price Override":
                                    $promo_recurring = " " . AdminLang::trans("promos.priceoverride");
                                    break;
                                case "Free Setup":
                                    $promo_recurring = "";
                                    break;
                                default:
                                    $promo_recurring = $promoData->isRecurring() ? " " . AdminLang::trans("status.recurring") : " " . AdminLang::trans("status.onetime");
                                    if ($promoData->type === "Price Override") {
                                        $promo_recurring = " " . AdminLang::trans("promos.priceoverride");
                                    }
                                    if ($promoData->type === "Free Setup") {
                                        $promo_recurring = "";
                                    }
                                    $promoSelectData[$groupName][$promoData->id] = [$promoData->isExpired() ? "#efefef" : "", $promoData->code . " - " . $promo_value . $promo_recurring];
                            }
                        }
                    }
                }
            }
        }
        return $promoSelectData;
    }
    function get_flash_message()
    {
        return WHMCS\FlashMessages::get();
    }
    function getLastInput($key)
    {
        $lastInput = WHMCS\Session::get("lastInput");
        if (is_array($lastInput) && array_key_exists($key, $lastInput)) {
            $value = $lastInput[$key];
            unset($lastInput[$key]);
            WHMCS\Session::set("lastInput", $lastInput);
            return $value;
        }
        return NULL;
    }
    function clearLastInput()
    {
        WHMCS\Session::delete("lastInput");
    }
    function run_hook($hook_name, $args, $unpackArguments = false)
    {
        return HookMgr::run($hook_name, $args, $unpackArguments);
    }
    function run_validate_hook($validate, $hook_name, $args)
    {
        HookMgr::validate($validate, $hook_name, $args);
    }
    function add_hook($hook_name, $priority, $hook_function, $rollback_function = "")
    {
        Hook::add($hook_name, $priority, $hook_function, $rollback_function);
    }
    function convertIniSize($size)
    {
        $multipliers = ["K" => 1024, "M" => 1048576, "G" => 1073741824, "T" => 0];
        $mod = strtoupper(substr($size, -1, 1));
        $mult = $multipliers[$mod] ?: 1;
        if (1 < $mult) {
            $size = (int) substr($size, 0, -1);
        }
        return $size * $mult;
    }
    function getUploadMaxFileSize($unit)
    {
        return getIniSettingSizeUnit("upload_max_filesize", $unit);
    }
    function getIniSettingSizeUnit($iniFileSizeType, $unit)
    {
        return getIniSettingSize($iniFileSizeType, $unit) . $unit;
    }
    function getIniSettingSize($iniFileSizeType, $unit)
    {
        return convertBytesToUnit(convertIniSize(ini_get($iniFileSizeType)), $unit);
    }
    function convertBytesToUnit($bytes, $unit)
    {
        $conversions = ["B" => $bytes, "KB" => $bytes / 1024, "MB" => $bytes / 1048576, "GB" => $bytes / 1073741824, "TB" => $bytes / 0];
        return $conversions[strtoupper($unit)] ?? -1;
    }
    function hasMaskedPasswordChanged($newPassword, $originalPassword)
    {
        $passwordInputIsOnlyMask = str_replace("*", "", $newPassword) == "";
        $passwordInputIsMaskExactlyAsLongAsPreviousPassword = strlen($newPassword) == strlen($originalPassword);
        $previousPasswordIsOnlyMaskMarks = str_replace("*", "", $originalPassword) == "";
        if (!$originalPassword && $newPassword || !($passwordInputIsMaskExactlyAsLongAsPreviousPassword && $passwordInputIsOnlyMask) || $originalPassword && !$passwordInputIsMaskExactlyAsLongAsPreviousPassword && !$passwordInputIsOnlyMask) {
            return true;
        }
        return false;
    }
    function interpretMaskedPasswordChangeForStorage($newPassword, $originalPassword)
    {
        if (!$newPassword) {
            return "";
        }
        if (hasMaskedPasswordChanged($newPassword, $originalPassword)) {
            return encrypt(WHMCS\Input\Sanitize::decode($newPassword));
        }
        return false;
    }
}
