<?php

define("CLIENTAREA", true);
require "init.php";
require "includes/ticketfunctions.php";
require "includes/customfieldfunctions.php";
require "includes/clientfunctions.php";
$pagetitle = Lang::trans("supportticketssubmitticket");
$breadcrumbnav = "<a href=\"index.php\">" . Lang::trans("globalsystemname") . "</a> > <a href=\"clientarea.php\">" . Lang::trans("clientareatitle") . "</a> > <a href=\"supporttickets.php\">" . Lang::trans("supportticketspagetitle") . "</a> > <a href=\"submitticket.php\">" . Lang::trans("supportticketssubmitticket") . "</a>";
$pageicon = "images/submitticket_big.gif";
$displayTitle = Lang::trans("navopenticket");
$tagline = "";
initialiseClientArea($pagetitle, $displayTitle, $tagline, $pageicon, $breadcrumbnav);
$action = $whmcs->get_req_var("action");
$deptid = (int) $whmcs->get_req_var("deptid");
$step = $whmcs->get_req_var("step");
$name = $whmcs->get_req_var("name");
$email = $whmcs->get_req_var("email");
$urgency = $whmcs->get_req_var("urgency");
$subject = $whmcs->get_req_var("subject");
$message = $whmcs->get_req_var("message");
$attachments = $whmcs->get_req_var("attachments");
$relatedservice = $whmcs->get_req_var("relatedservice");
$customfield = $whmcs->get_req_var("customfield");
$contactId = App::getFromRequest("contactid");
$cc = App::getFromRequest("cc");
$file_too_large = $whmcs->get_req_var("file_too_large");
$activeTemplate = $whmcs->getClientAreaTemplate();
if ($action == "getkbarticles") {
    $kbarticles = getKBAutoSuggestions($text);
    if (count($kbarticles)) {
        $smarty->assign("kbarticles", $kbarticles);
        echo $smarty->fetch($activeTemplate->resolveFilePath("supportticketsubmit-kbsuggestions.tpl"));
    }
    exit;
}
if ($action == "getcustomfields") {
    $customfields = getCustomFields("support", $deptid, "", "", "", $customfield);
    $smarty->assign("customfields", $customfields);
    $templateName = $whmcs->getClientAreaTemplate()->getName();
    $path = false;
    $templatesToSearch = ["supportticketsubmit-customfields.tpl", "supportticketsubmit-customFields.tpl"];
    foreach ($templatesToSearch as $templateName) {
        $path = file_exists($activeTemplate->resolveFilePath($templateName));
        if ($path) {
            echo $smarty->fetch($activeTemplate->resolveFilePath($templateName));
            if (!$path) {
                echo "supportticketsubmit-customfields.tpl is missing";
            }
            exit;
        }
    }
} else {
    if ($action == "markdown") {
        $response = new WHMCS\Http\JsonResponse();
        $templatefile = $activeTemplate->resolveFilePath("markdown-guide.tpl");
        $response->setData(["body" => $smarty->fetch($templatefile)]);
        $response->send();
        WHMCS\Terminus::getInstance()->doExit();
    }
    $recentTickets = [];
    if (Auth::client()) {
        $result = select_query("tbltickets", "", ["userid" => Auth::client()->id], "id", "DESC", "0,5");
        while ($data = mysql_fetch_array($result)) {
            $recentTickets[] = ["id" => $data["id"], "tid" => $data["tid"], "c" => $data["c"], "date" => fromMySQLDate($data["date"], 1, 1), "department" => $data["did"], "subject" => $data["title"], "status" => getStatusColour($data["status"]), "urgency" => Lang::trans("supportticketsticketurgency" . strtolower($data["urgency"])), "lastreply" => fromMySQLDate($data["lastreply"], 1, 1), "unread" => $data["clientunread"]];
        }
    }
    $smartyvalues["recenttickets"] = $recentTickets;
    $captcha = new WHMCS\Utility\Captcha();
    $validate = new WHMCS\Validate();
    $uploadMaxFileSize = getUploadMaxFileSize("MB");
    if ($step == "3") {
        if (checkTicketAttachmentSize()) {
            check_token();
            if (!Auth::user()) {
                $validate->validate("required", "name", "supportticketserrornoname");
                if ($validate->validate("required", "email", "supportticketserrornoemail")) {
                    $validate->validate("email", "email", "clientareaerroremailinvalid");
                }
            }
            $validate->validate("required", "subject", "supportticketserrornosubject");
            $validate->validate("required", "message", "supportticketserrornomessage");
            $validate->validate("fileuploads", "attachments", "supportticketsfilenotallowed");
            $validate->validateCustomFields("support", $deptid);
            $captcha->validateAppropriateCaptcha(WHMCS\Utility\Captcha::FORM_SUBMIT_TICKET, $validate);
            if (!$validate->hasErrors()) {
                $customfields = [];
                if (is_array($customfield)) {
                    $customfields = getCustomFields("support", $deptid, "", "", "", $customfield);
                }
                $validationData = ["userId" => Auth::user()->id, "clientId" => Auth::client()->id, "name" => $name, "email" => $email, "isAdmin" => false, "departmentId" => $deptid, "subject" => $subject, "message" => $message, "priority" => $urgency, "relatedService" => $relatedservice, "customfields" => $customfields];
                $ticketOpenValidateResults = run_hook("TicketOpenValidation", $validationData);
                if (is_array($ticketOpenValidateResults)) {
                    foreach ($ticketOpenValidateResults as $hookReturn) {
                        if (is_string($hookReturn) && ($hookReturn = trim($hookReturn))) {
                            $validate->addError($hookReturn);
                        }
                    }
                }
            }
            if ($validate->hasErrors()) {
                $step = "2";
            }
        } else {
            if (empty($_POST)) {
                redir("file_too_large=1", "submitticket.php");
            } else {
                $step = 2;
                $file_too_large = true;
            }
        }
    }
    if ($file_too_large) {
        $validate->addError(Lang::trans("supportticketsuploadtoolarge") . "  " . Lang::trans("maxFileSize", [":fileSize" => $uploadMaxFileSize]));
    }
    if (Auth::user()) {
        Auth::requireLoginAndClient(true);
        checkContactPermission("tickets");
        $name = Auth::user()->fullName;
        $email = Auth::user()->email;
    }
    $usingsupportmodule = false;
    $submissionFailure = false;
    if (WHMCS\Config\Setting::getValue("SupportModule")) {
        if (!isValidforPath(WHMCS\Config\Setting::getValue("SupportModule"))) {
            exit("Invalid Support Module");
        }
        $supportmodulepath = "modules/support/" . WHMCS\Config\Setting::getValue("SupportModule") . "/submitticket.php";
        if (file_exists($supportmodulepath)) {
            Auth::requireLoginAndClient(true);
            $usingsupportmodule = true;
            $templatefile = "";
            require $supportmodulepath;
            outputClientArea($templatefile);
            exit;
        }
    }
    if ($step == "") {
        $templatefile = "supportticketsubmit-stepone";
        $departmentCollection = WHMCS\Support\Department::where("hidden", "");
        $totaldepartments = $departmentCollection->count();
        if (!WHMCS\Config\Setting::getValue("ShowClientOnlyDepts") && !Auth::client()) {
            $departmentCollection = $departmentCollection->where("clientsonly", "");
        }
        $departments = [];
        foreach ($departmentCollection->get() as $department) {
            $departments[] = ["id" => $department->id, "name" => $department->name, "description" => $department->description];
        }
        if (!$departments && $totaldepartments) {
            $goto = "submitticket";
            include "login.php";
        }
        if (count($departments) == 1) {
            redir("step=2&deptid=" . $departments[0]["id"] . ($file_too_large ? "&file_too_large=1" : ""));
        }
        $smarty->assign("departments", $departments);
        $smarty->assign("errormessage", $validate->getHTMLErrorOutput());
    } else {
        if ($step == "3") {
            $userId = Auth::client()->id;
            $ticketDepartment = WHMCS\Support\Department::find($deptid);
            if (!$ticketDepartment || $ticketDepartment->clientsOnly && !$userId) {
                redir("", "submitticket.php");
            }
            try {
                $attachments = uploadTicketAttachments();
                $from = [];
                $from["name"] = $name;
                $from["email"] = $email;
                $ticketdetails = openNewTicket($userId, $contactId, $deptid, $subject, $message, $urgency, $attachments, $from, $relatedservice, $cc, false, false, true, NULL, NULL, WHMCS\Utility\Environment\CurrentRequest::getIP());
                saveCustomFields($ticketdetails["ID"], $customfield);
                $_SESSION["tempticketdata"] = $ticketdetails;
                redir("step=4", "submitticket.php");
            } catch (WHMCS\Exception\Support\TicketMaskIterationException $e) {
                logActivity("Unable to create new ticket. The system could not generate a ticket number because it reached the maximum number of attempts", Auth::client()->id, ["withClientId" => true]);
                $validate->addError(Lang::trans("support.ticketError"));
                $submissionFailure = true;
            } catch (WHMCS\Exception\Storage\StorageException $e) {
                $validate->addError(Lang::trans("support.ticketError"));
                $submissionFailure = true;
            }
        }
    }
    if ($step == "2" || $submissionFailure) {
        $templatefile = "supportticketsubmit-steptwo";
        $department = WHMCS\Support\Department::find($deptid);
        if (!$department) {
            redir("", "submitticket.php");
        }
        $deptid = $department->id;
        $deptname = $department->name;
        $clientsonly = $department->clientsOnly;
        if ($clientsonly && !Auth::client()) {
            Auth::requireLoginAndClient(true);
        }
        $smarty->assign("deptid", $deptid);
        $smarty->assign("department", $deptname);
        $smarty->assign("uploadMaxFileSize", $uploadMaxFileSize);
        $departmentCollection = WHMCS\Support\Department::enforceUserVisibilityPermissions()->orWhere("id", $deptid);
        $departments = [];
        foreach ($departmentCollection->get() as $department) {
            $departments[] = ["id" => $department->id, "name" => $department->name, "description" => $department->description];
        }
        $smarty->assign("departments", $departments);
        $relatedservices = [];
        if (Auth::client()) {
            $result = select_query("tblhosting", "tblhosting.id,tblhosting.domain,tblhosting.domainstatus,tblhosting.packageid,tblproducts.name as product_name", ["userid" => Auth::client()->id], "domain", "ASC", "", "tblproducts ON tblproducts.id=tblhosting.packageid");
            while ($data = mysql_fetch_array($result)) {
                $productname = WHMCS\Product\Product::getProductName($data["packageid"], $data["product_name"]);
                if ($data["domain"]) {
                    $productname .= " - " . $data["domain"];
                }
                $relatedservices[] = ["id" => "S" . $data["id"], "name" => $productname, "status" => Lang::trans("clientarea" . strtolower($data["domainstatus"]))];
            }
            $result = select_query("tbldomains", "", ["userid" => Auth::client()->id], "domain", "ASC");
            while ($data = mysql_fetch_array($result)) {
                $relatedservices[] = ["id" => "D" . $data["id"], "name" => Lang::trans("clientareahostingdomain") . " - " . $data["domain"], "status" => Lang::trans("clientarea" . strtolower(str_replace(" ", "", $data["status"])))];
            }
        }
        $smarty->assign("name", $name);
        $smarty->assign("email", $email);
        $smarty->assign("selectedservice", App::getFromRequest("relatedservice"));
        $smartyvalues["relatedservices"] = $relatedservices;
        $customfields = getCustomFields("support", $deptid, "", "", "", $customfield);
        $tickets = new WHMCS\Tickets();
        $smarty->assign("customfields", $customfields);
        $smarty->assign("allowedfiletypes", implode(", ", $tickets->getAllowedAttachments()));
        $smarty->assign("errormessage", $validate->getHTMLErrorOutput());
        $smarty->assign("urgency", $urgency);
        $smarty->assign("subject", $subject);
        $smarty->assign("message", $message);
        $smarty->assign("captcha", $captcha);
        $smarty->assign("captchaForm", WHMCS\Utility\Captcha::FORM_SUBMIT_TICKET);
        $smarty->assign("recaptchahtml", clientAreaReCaptchaHTML());
        $smarty->assign("capatacha", $captcha);
        $smarty->assign("recapatchahtml", clientAreaReCaptchaHTML());
        if (WHMCS\Config\Setting::getValue("SupportTicketKBSuggestions")) {
            $smarty->assign("kbsuggestions", true);
        }
        $locale = preg_replace("/[^a-zA-Z0-9_\\-]*/", "", Lang::getLanguageLocale());
        $locale = $locale == "locale" ? "en" : substr($locale, 0, 2);
        $smarty->assign("mdeLocale", $locale);
        $smarty->assign("loadMarkdownEditor", true);
    } else {
        if ($step == "4") {
            $ticketdetails = $_SESSION["tempticketdata"];
            $templatefile = "supportticketsubmit-confirm";
            $smarty->assign("tid", $ticketdetails["TID"]);
            $smarty->assign("c", $ticketdetails["C"]);
            $smarty->assign("subject", $ticketdetails["Subject"]);
        }
    }
    Menu::addContext("departmentId", $deptid);
    Menu::primarySidebar("ticketSubmit");
    Menu::secondarySidebar("ticketSubmit");
    outputClientArea($templatefile, false, ["ClientAreaPageSubmitTicket"]);
}
