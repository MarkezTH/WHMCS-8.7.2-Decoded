<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
require ROOTDIR . "/includes/gatewayfunctions.php";
require ROOTDIR . "/includes/ticketfunctions.php";
$projectid = $projectId = (int) $_REQUEST["projectid"];
$modulelink .= "&projectid=" . (int) $projectid;
if (!project_management_check_viewproject($projectid)) {
    redir("module=project_management");
}
if ($a == "addinvoice") {
    check_token("WHMCS.admin.default");
    $invoicenum = $_REQUEST["invoicenum"];
    if (!trim($invoicenum)) {
        exit($vars["_lang"]["youmustenterinvoicenumber"]);
    }
    $data = get_query_vals("tblinvoices", "id,date,datepaid,total,paymentmethod,status", ["id" => $invoicenum]);
    $invoicenum = $data["id"];
    if (!$invoicenum) {
        exit($vars["_lang"]["invoicenumberenterednotfound"]);
    }
    $oldInvoiceIds = get_query_val("mod_project", "invoiceids", ["id" => $projectid]);
    $invoiceids = explode(",", $oldInvoiceIds);
    if (in_array($invoicenum, $invoiceids)) {
        exit($vars["_lang"]["invoicenumberalreadyassociated"]);
    }
    $invoiceids[] = $invoicenum;
    update_query("mod_project", ["invoiceids" => implode(",", $invoiceids), "lastmodified" => "now()"], ["id" => $projectid]);
    project_management_log($projectid, $vars["_lang"]["addedinvoiceassociation"] . $invoicenum);
    $invoiceid = $data["id"];
    $invoicedate = $data["date"];
    $invoicedatepaid = $data["datepaid"] != "0000-00-00 00:00:00" ? fromMySQLDate($data["datepaid"]) : "-";
    $invoicetotal = $data["total"];
    $paymentmethod = WHMCS\Module\GatewaySetting::getFriendlyNameFor($data["paymentmethod"]);
    $invoicestatus = $data["status"];
    echo "<tr id=\"invoiceholder" . $i . "\"><td><a href=\"invoices.php?action=edit&id=" . $invoiceid . "\" target=\"_blank\">" . $invoiceid . "</a></td><td>" . fromMySQLDate($invoicedate) . "</td><td>" . $invoicedatepaid . "</td><td>" . $invoicetotal . "</td><td>" . $paymentmethod . "</td><td>" . getInvoiceStatusColour($invoicestatus) . "</td></tr>";
    exit;
}
if ($a == "updatestaffmsg") {
    check_token("WHMCS.admin.default");
    $msgid = $_POST["msgid"];
    $msgtxt = WHMCS\Input\Sanitize::decode($_POST["msgtxt"]);
    $oldMessage = WHMCS\Database\Capsule::table("mod_projectmessages")->where("id", "=", $msgid)->pluck("message")->all();
    update_query("mod_projectmessages", ["message" => $msgtxt], ["id" => $msgid]);
    $projectChanges = [["field" => "Staff Message Updated", "oldValue" => $oldMessage, "newValue" => $msgtxt]];
    $project->notify()->staff($projectChanges);
    project_management_log($projectid, "Edited Staff Message");
    echo nl2br(ticketAutoHyperlinks($msgtxt));
    exit;
}
if ($a == "deletestaffmsg") {
    check_token("WHMCS.admin.default");
    if (project_management_checkperm("Delete Messages")) {
        $projectChanges = [];
        $msgid = (int) $_REQUEST["id"];
        $attachments = explode(",", get_query_val("mod_projectmessages", "attachments", ["id" => $msgid]));
        $storage = Storage::projectManagementFiles($projectid);
        foreach ($attachments as $i => $attachment) {
            if ($attachment) {
                try {
                    $storage->deleteAllowNotPresent($attachment);
                    project_management_log($projectid, $vars["_lang"]["deletedattachment"] . " " . substr($attachment, 7));
                    unset($attachments[$i]);
                } catch (Exception $e) {
                    $aInt->gracefulExit("Could not delete file: " . htmlentities($e->getMessage()));
                }
            }
        }
        delete_query("mod_projectmessages", ["id" => $msgid]);
        project_management_log($projectid, "Deleted Staff Message");
        echo $msgid;
    } else {
        echo "0";
    }
    exit;
} else {
    if ($a == "hookstarttimer") {
        check_token("WHMCS.admin.default");
        $projectid = $_REQUEST["projectid"];
        $ticketnum = $_REQUEST["ticketnum"];
        $taskid = $_REQUEST["taskid"];
        $title = $_REQUEST["title"];
        if (!$taskid && $title) {
            $taskid = insert_query("mod_projecttasks", ["projectid" => $projectid, "task" => $title, "created" => "now()"]);
            project_management_log($projectid, $vars["_lang"]["addedtask"] . $title);
        }
        $timerid = insert_query("mod_projecttimes", ["projectid" => $projectid, "taskid" => $taskid, "start" => time(), "adminid" => $_SESSION["adminid"]]);
        project_management_log($projectid, $vars["_lang"]["startedtimerfortask"] . get_query_val("mod_projecttasks", "task", ["id" => $taskid]));
        if ($timerid) {
            $result = select_query("mod_projecttimes", "mod_projecttimes.id, mod_projecttimes.projectid, mod_project.title, mod_projecttimes.taskid, mod_projecttasks.task, mod_projecttimes.start", ["mod_projecttimes.adminid" => $_SESSION["adminid"], "mod_projecttimes.end" => "", "mod_project.ticketids" => ["sqltype" => "LIKE", "value" => (int) $ticketnum]], "", "", "", "mod_projecttasks ON mod_projecttimes.taskid=mod_projecttasks.id INNER JOIN mod_project ON mod_projecttimes.projectid=mod_project.id");
            while ($data = mysql_fetch_array($result)) {
                echo "<div class=\"stoptimer" . $data["id"] . "\" style=\"padding-bottom:10px;\"><em>" . $data["title"] . " - Project ID " . $data["projectid"] . "</em><br />&nbsp;&raquo; " . $data["task"] . "<br />Started at " . fromMySQLDate(date("Y-m-d H:i", $data["start"]), 1) . ":" . date("s", $data["start"]) . " - <a href=\"#\" onclick=\"projectendtimersubmit('" . $data["projectid"] . "','" . $data["id"] . "');return false\"><strong>Stop Timer</strong></a></div>";
            }
        } else {
            echo "0";
        }
        exit;
    }
    if ($a == "hookendtimer") {
        check_token("WHMCS.admin.default");
        $timerid = $_POST["timerid"];
        $ticketnum = $_POST["ticketnum"];
        $taskid = get_query_val("mod_projecttimes", "taskid", ["id" => $timerid, "adminid" => $_SESSION["adminid"]]);
        $projectid = get_query_val("mod_projecttimes", "projectid", ["id" => $timerid, "adminid" => $_SESSION["adminid"]]);
        update_query("mod_projecttimes", ["end" => time()], ["id" => $timerid, "taskid" => $taskid, "adminid" => $_SESSION["adminid"]]);
        project_management_log($projectid, $vars["_lang"]["stoppedtimerfortask"] . get_query_val("mod_projecttasks", "task", ["id" => $taskid]));
        if (!$taskid) {
            echo "0";
        } else {
            $result = select_query("mod_projecttimes", "mod_projecttimes.id, mod_projecttimes.projectid, mod_project.title, mod_projecttimes.taskid, mod_projecttasks.task, mod_projecttimes.start", ["mod_projecttimes.adminid" => $_SESSION["adminid"], "mod_projecttimes.end" => "", "mod_project.ticketids" => ["sqltype" => "LIKE", "value" => (int) $ticketnum]], "", "", "", "mod_projecttasks ON mod_projecttimes.taskid=mod_projecttasks.id INNER JOIN mod_project ON mod_projecttimes.projectid=mod_project.id");
            while ($data = mysql_fetch_array($result)) {
                echo "<div class=\"stoptimer" . $data["id"] . "\" style=\"padding-bottom:10px;\"><em>" . $data["title"] . " - Project ID " . $data["projectid"] . "</em><br />&nbsp;&raquo; " . $data["task"] . "<br />Started at " . fromMySQLDate(date("Y-m-d H:i", $data["start"]), 1) . ":" . date("s", $data["start"]) . " - <a href=\"#\" onclick=\"projectendtimersubmit('" . $data["projectid"] . "','" . $data["id"] . "');return false\"><strong>Stop Timer</strong></a></div>";
            }
        }
        exit;
    }
    if ($a == "deleteticket") {
        check_token("WHMCS.admin.default");
        if (project_management_checkperm("Associate Tickets")) {
            $result = select_query("mod_project", "ticketids", ["id" => $projectid]);
            $data = mysql_fetch_array($result);
            $ticketids = explode(",", $data["ticketids"]);
            project_management_log($projectid, $vars["_lang"]["deletedticketrelationship"] . $ticketids[$_REQUEST["id"]]);
            unset($ticketids[$_REQUEST["id"]]);
            update_query("mod_project", ["ticketids" => implode(",", $ticketids), "lastmodified" => "now()"], ["id" => $projectid]);
            echo $_REQUEST["id"];
            exit;
        }
    } else {
        if ($a == "projectsave") {
            check_token("WHMCS.admin.default");
            $logmsg = "";
            $projectChanges = [];
            $result = select_query("mod_project", "", ["id" => $projectid]);
            $data = mysql_fetch_array($result);
            $updateqry["userid"] = $_POST["userid"];
            $updateqry["title"] = $_POST["title"];
            $updateqry["adminid"] = $_POST["adminid"];
            $updateqry["created"] = toMySQLDate($_POST["created"]);
            $updateqry["duedate"] = toMySQLDate($_POST["duedate"]);
            $updateqry["lastmodified"] = "now()";
            if ($_POST["completed"]) {
                update_query("mod_projecttasks", ["completed" => "1"], ["projectid" => $projectid]);
            }
            if (!$logmsg) {
                if ($updateqry["title"] && $updateqry["title"] != $data["title"]) {
                    $changes[] = $vars["_lang"]["titlechangedfrom"] . $data["title"] . " to " . $updateqry["title"];
                    $projectChanges[] = ["field" => "Title", "oldValue" => $data["title"], "newValue" => $updateqry["title"]];
                }
                if (isset($updateqry["userid"]) && $updateqry["userid"] != $data["userid"]) {
                    $changes[] = $vars["_lang"]["assignedclientchangedfrom"] . $data["userid"] . " " . $vars["_lang"]["to"] . " " . $updateqry["userid"];
                    $projectChanges[] = ["field" => "User Id", "oldValue" => $data["userid"], "newValue" => $updateqry["userid"]];
                }
                if ($updateqry["adminid"] != $data["adminid"]) {
                    $adminId = $data["adminid"] ? getAdminName($data["adminid"]) : "Nobody";
                    $newAdminId = $updateqry["adminid"] ? getAdminName($updateqry["adminid"]) : "Nobody";
                    $changes[] = $vars["_lang"]["assignedadminchangedfrom"] . $adminId . " " . $vars["_lang"]["to"] . " " . $newAdminId;
                    $projectChanges[] = ["field" => "Admin Id", "oldValue" => $adminId, "newValue" => $newAdminId];
                }
                if ($_POST["created"] && $_POST["created"] != fromMySQLDate($data["created"])) {
                    $oldCreated = fromMySQLDate($data["created"]);
                    $newCreated = $whmcs->get_req_var("created");
                    $changes[] = $vars["_lang"]["creationdatechangedfrom"] . " " . $oldCreated . " to " . $newCreated;
                    $projectChanges[] = ["field" => "Created", "oldValue" => $oldCreated, "newValue" => $newCreated];
                }
                if ($_POST["duedate"] && $_POST["duedate"] != fromMySQLDate($data["duedate"])) {
                    $oldDueDate = fromMySQLDate($data["duedate"]);
                    $newDueDate = $whmcs->get_req_var("duedate");
                    $changes[] = $vars["_lang"]["duedatechangedfrom"] . $oldDueDate . " to " . $newDueDate;
                    $projectChanges[] = ["field" => "Due Date", "oldValue" => $oldDueDate, "newValue" => $newDueDate];
                }
                if ($_POST["newticketid"]) {
                    $newTicketId = $whmcs->get_req_var("newticketid");
                    $changes[] = $vars["_lang"]["addednewrelatedticket"] . $newticketid;
                    $projectChanges[] = ["field" => "New Ticket", "oldValue" => "", "newValue" => $newticketid];
                }
                if ($updateqry["notes"] && $updateqry["notes"] != $data["notes"]) {
                    $changes[] = $vars["_lang"]["notesupdated"];
                    $projectChanges[] = ["field" => "Notes", "oldValue" => $data["notes"], "newValue" => $updateqry["notes"]];
                }
                if ($updateqry["completed"] && $updateqry["completed"] != $data["completed"]) {
                    $changes[] = $vars["_lang"]["projectmarkedcompleted"];
                    $projectChanges[] = ["field" => "Completed", "oldValue" => $data["completed"], "newValue" => $updateqry["completed"]];
                }
                $logmsg = $vars["_lang"]["updatedproject"] . implode(", ", $changes);
            }
            if (count($changes)) {
                project_management_log($projectid, $logmsg);
            }
            update_query("mod_project", $updateqry, ["id" => $projectid]);
            echo project_management_daysleft(toMySQLDate($_POST["duedate"]), $vars);
            exit;
        }
        if ($a == "statussave") {
            check_token("WHMCS.admin.default");
            if (project_management_checkperm("Update Status")) {
                $status = db_escape_string($_POST["status"]);
                $statuses = explode(",", $vars["statusvalues"]);
                $statusarray = [];
                foreach ($statuses as $tmpstatus) {
                    $tmpstatus = explode("|", $tmpstatus, 2);
                    $statusarray[] = $tmpstatus[0];
                }
                if (in_array($status, $statusarray)) {
                    $oldstatus = get_query_val("mod_project", "status", ["id" => $projectid]);
                    $updateqry = ["status" => $status];
                    if (in_array($status, explode(",", $vars["completedstatuses"]))) {
                        $updateqry["completed"] = "1";
                    } else {
                        $updateqry["completed"] = "0";
                    }
                    update_query("mod_project", $updateqry, ["id" => $projectid]);
                    project_management_log($projectid, $vars["_lang"]["statuschangedfrom"] . $oldstatus . " " . $vars["_lang"]["to"] . " " . $status);
                }
            }
            exit;
        } else {
            if ($a == "addquickinvoice") {
                check_token("WHMCS.admin.default");
                $newinvoice = trim($_REQUEST["newinvoice"]);
                $newinvoiceamt = trim($_REQUEST["newinvoiceamt"]);
                if ($newinvoice && $newinvoiceamt) {
                    $projectChanges = [];
                    $userid = get_query_val("mod_project", "userid", ["id" => $projectid]);
                    $gateway = function_exists("getClientsPaymentMethod") ? getClientsPaymentMethod($userid) : "paypal";
                    if ($CONFIG["TaxEnabled"] == "on") {
                        $clientsdetails = getClientsDetails($userid);
                        if (!$clientsdetails["taxexempt"]) {
                            $state = $clientsdetails["state"];
                            $country = $clientsdetails["country"];
                            $taxdata = getTaxRate(1, $state, $country);
                            $taxdata2 = getTaxRate(2, $state, $country);
                            $taxrate = $taxdata["rate"];
                            $taxrate2 = $taxdata2["rate"];
                        }
                    }
                    $invoice = new WHMCS\Billing\Invoice();
                    $invoice->dateCreated = WHMCS\Carbon::now();
                    $invoice->dateDue = WHMCS\Carbon::now();
                    $invoice->clientId = $userid;
                    $invoice->status = "Unpaid";
                    $invoice->paymentGateway = $gateway;
                    $invoice->taxRate1 = $taxrate;
                    $invoice->taxRate2 = $taxrate2;
                    $invoice->save();
                    $invoiceid = $invoice->id;
                    insert_query("tblinvoiceitems", ["invoiceid" => $invoiceid, "userid" => $userid, "type" => "Project", "relid" => $projectid, "description" => $newinvoice, "paymentmethod" => $gateway, "amount" => $newinvoiceamt, "taxed" => "1"]);
                    $invoice->updateInvoiceTotal();
                    $invoiceids = get_query_val("mod_project", "invoiceids", ["id" => $projectid]);
                    $invoiceids = explode(",", $invoiceids);
                    $invoiceids[] = $invoiceid;
                    $invoiceids = implode(",", $invoiceids);
                    update_query("mod_project", ["invoiceids" => $invoiceids], ["id" => $projectid]);
                    project_management_log($projectid, $vars["_lang"]["addedquickinvoice"] . " " . $invoiceid, $userid);
                    $invoice->runCreationHooks("adminarea");
                }
                redir("module=project_management&m=view&projectid=" . $projectid);
            } else {
                if ($a == "gettimesheethead") {
                    check_token("WHMCS.admin.default");
                    echo WHMCS\View\Asset::cssInclude("jquery-ui.min.css") . WHMCS\View\Asset::jsInclude("jquery.min.js") . WHMCS\View\Asset::jsInclude("jquery-ui.min.js");
                    exit;
                }
                if ($a == "gettimesheet") {
                    check_token("WHMCS.admin.default");
                    if (project_management_checkperm("Bill Tasks")) {
                        echo "<form method=\"post\" action=\"" . $modulelink . "&a=dynamicinvoicegenerate\">\n        " . generate_token() . "\n<div class=\"box\">\n<table width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" class=\"tasks\" id=\"tasks\"><tr bgcolor=\"#efefef\">\n    <th width=\"60%\">" . $vars["_lang"]["description"] . "</th><th width=\"10%\">" . $vars["_lang"]["hours"] . "</th><th width=\"14%\">" . $vars["_lang"]["rate"] . "</th><th width=\"15%\">" . $vars["_lang"]["amount"] . "</th><th width=\"20\"></th></tr>";
                        $dti = 0;
                        for ($tasksresult = select_query("mod_projecttasks", "id,task", ["projectid" => $projectid, "billed" => "0"]); $tasksdata = mysql_fetch_assoc($tasksresult); $dti++) {
                            $dynamictimes[$dti]["seconds"] = get_query_val("mod_projecttimes", "SUM(end-start)", ["taskid" => $tasksdata["id"], "donotbill" => 0]);
                            $dynamictimes[$dti]["description"] = $tasksdata["task"];
                            $dynamictimes[$dti]["rate"] = $vars["hourlyrate"];
                            $dynamictimes[$dti]["amount"] = $dynamictimes[$dti]["rate"] * $dynamictimes[$dti]["seconds"] / 3600;
                            if (0 < $dynamictimes[$dti]["seconds"]) {
                                echo "<tr id=\"dynamictaskinvoiceitemholder" . $dti . "\">\n            <td><input type=\"hidden\" name=\"taskid[" . $dti . "]\" value=\"" . $tasksdata["id"] . "\" /><input style=\"width:99%\" type=\"text\" name=\"description[" . $dti . "]\" value=\"" . $dynamictimes[$dti]["description"] . "\" /></td>\n            <td><input type=\"hidden\" id=\"dynamicbillhours" . $dti . "\" name=\"hours[" . $dti . "]\" value=\"" . round($dynamictimes[$dti]["seconds"] / 3600, 2) . "\" /><input type=\"text\" name=\"displayhours[" . $dti . "]\" class=\"dynamicbilldisplayhours\" id=\"dynamicbilldisplayhours" . $dti . "\" name=\"hours[" . $dti . "]\" value=\"" . project_management_sec2hms($dynamictimes[$dti]["seconds"]) . "\" /></td>\n            <td><input type=\"text\" class=\"dynamicbillrate\" id=\"dynamicbillrate" . $dti . "\" name=\"rate[" . $dti . "]\" value=\"" . format_as_currency($dynamictimes[$dti]["rate"]) . "\" /></td>\n            <td><input type=\"text\" id=\"dynamicbillamount" . $dti . "\" name=\"amount[" . $dti . "]\" value=\"" . format_as_currency($dynamictimes[$dti]["amount"], 2) . "\" /></td>\n            <td><a class=\"deldynamictaskinvoice\" id=\"deldynamictaskinvoice" . $dti . "\"><img src=\"images/delete.gif\"></a></td></tr>";
                            }
                        }
                        echo "</table></div>\n        <p align=\"center\">\n            <input type=\"submit\" value=\"" . $vars["_lang"]["generatenow"] . "\" />&nbsp;\n            <input type=\"submit\" onClick=\"form.action='" . $modulelink . "&a=dynamicinvoicegenerate&sendinvoicegenemail=true&token=" . generate_token("plain") . "'\" value=\"" . $vars["_lang"]["generatenowandemail"] . "\" />&nbsp;\n            <input type=\"button\" id=\"dynamictasksinvoicecancel\" value=\"" . $vars["_lang"]["cancel"] . "\" />\n        </p>\n        </form>";
                    }
                    exit;
                }
                if ($a == "dynamicinvoicegenerate") {
                    check_token("WHMCS.admin.default");
                    if (!project_management_checkperm("Bill Tasks")) {
                        redir("module=project_management");
                    }
                    $userid = get_query_val("mod_project", "userid", ["id" => $projectid]);
                    $invoice = WHMCS\Billing\Invoice::newInvoice($userid);
                    $invoice->status = "Unpaid";
                    $invoice->save();
                    $invoiceid = $invoice->id;
                    foreach ($_REQUEST["taskid"] as $taski => $taskid) {
                        update_query("mod_projecttasks", ["billed" => 1], ["id" => $taskid]);
                    }
                    foreach ($_REQUEST["description"] as $desci => $description) {
                        if ($description && $_REQUEST["displayhours"][$desci] && $_REQUEST["rate"][$desci] && $_REQUEST["amount"][$desci]) {
                            $description .= " - " . $_REQUEST["displayhours"][$desci] . " " . $vars["_lang"]["hours"];
                            if ($_REQUEST["rate"][$desci] != $vars["hourlyrate"]) {
                                $amount = $_REQUEST["hours"][$desci] * $_REQUEST["rate"][$desci];
                            } else {
                                $amount = $_REQUEST["amount"][$desci];
                            }
                            insert_query("tblinvoiceitems", ["invoiceid" => $invoiceid, "userid" => $userid, "type" => "Project", "relid" => $projectid, "description" => $description, "paymentmethod" => $gateway, "amount" => round($amount, 2), "taxed" => "1"]);
                        }
                    }
                    $invoice->updateInvoiceTotal();
                    $oldInvoiceIds = get_query_val("mod_project", "invoiceids", ["id" => $projectid]);
                    $invoiceids = explode(",", $oldInvoiceIds);
                    $invoiceids[] = $invoiceid;
                    $invoiceids = implode(",", $invoiceids);
                    update_query("mod_project", ["invoiceids" => $invoiceids], ["id" => $projectid]);
                    if ($invoiceid && $_REQUEST["sendinvoicegenemail"] == "true") {
                        sendMessage("Invoice Created", $invoiceid);
                    }
                    project_management_log($projectid, $vars["_lang"]["createdtimebasedinvoice"] . " " . $invoiceid, $userid);
                    $invoice->runCreationHooks("adminarea");
                    redir("module=project_management&m=view&projectid=" . $projectid);
                } else {
                    if ($a == "savetasklist") {
                        check_token("WHMCS.admin.default");
                        $tasksarray = [];
                        $result = select_query("mod_projecttasks", "", ["projectid" => $_REQUEST["projectid"]], "order", "ASC");
                        while ($data = mysql_fetch_array($result)) {
                            $tasksarray[] = ["task" => $data["task"], "notes" => $data["notes"], "adminid" => $data["adminid"], "duedate" => $data["duedate"]];
                        }
                        $template = new WHMCS\Module\Addon\ProjectManagement\Models\Task\Template();
                        $template->name = App::getFromRequest("name");
                        $template->tasks = $tasksarray;
                        $template->save();
                    } else {
                        if ($a == "loadtasklist") {
                            check_token("WHMCS.admin.default");
                            $maxorder = get_query_val("mod_projecttasks", "MAX(`order`)", ["projectid" => $_REQUEST["projectid"]]);
                            $taskTemplate = WHMCS\Module\Addon\ProjectManagement\Models\Task\Template::find((int) App::getFromRequest("tasktplid"));
                            if ($taskTemplate) {
                                foreach ($taskTemplate->tasks as $task) {
                                    $maxorder++;
                                    WHMCS\Database\Capsule::table("mod_projecttasks")->insert(["projectid" => $projectId, "task" => $task["task"], "notes" => $task["notes"], "adminid" => $task["adminid"], "created" => WHMCS\Carbon::now()->toDateTimeString(), "order" => $maxorder]);
                                }
                            }
                            redir("module=project_management&m=view&projectid=" . $projectid);
                        } else {
                            if ($a == "getprojectsbyid") {
                                check_token("WHMCS.admin.default");
                                $ticketId = (string) App::getFromRequest("tid");
                                $offsetNum = (int) App::getFromRequest("offset");
                                $limitNum = 10;
                                $aInt->sortableTableInit("nopagination");
                                $assocProjects = WHMCS\Database\Capsule::table("mod_project")->select("mod_project.*", WHMCS\Database\Capsule::raw("concat(tbladmins.firstname,' ',tbladmins.lastname) as adminname"))->leftJoin("tbladmins", "tbladmins.id", "=", "mod_project.adminid")->where("ticketids", "like", "%" . $ticketId . "%");
                                $assocProjectsTotal = $assocProjects->count();
                                $assocProjects = $assocProjects->offset($offsetNum)->limit($limitNum)->orderBy("mod_project.id")->get();
                                $tableData = [];
                                foreach ($assocProjects as $assocProject) {
                                    $ticketIdArray = explode(",", $assocProject->ticketids);
                                    if (in_array($ticketId, $ticketIdArray)) {
                                        $timerId = WHMCS\Database\Capsule::table("mod_projecttimes")->where(["projectid" => $assocProject->id, "end" => "", "adminid" => (new WHMCS\Authentication\CurrentUser())->admin()->id])->orderBy("start", "desc")->value("id");
                                        $projectUrl = "addonmodules.php?module=project_management&m=view&projectid=" . $assocProject->id;
                                        $endTimerLink = "<a href=\"#\" onclick=\"projectendtimer('" . $assocProject->id . "');return false;\"><img src=\"../modules/addons/project_management/images/notimes.png\" " . "align=\"absmiddle\" border=\"0\" /> " . $vars["_lang"]["stopTracking"] . "</a>";
                                        $startTimerLink = "<a href=\"#\" onclick=\"projectstarttimer('" . $assocProject->id . "');return false;\"><img src=\"../modules/addons/project_management/images/starttimer.png\" " . "align=\"absmiddle\" border=\"0\" /> " . $vars["_lang"]["startTracking"] . "</a>";
                                        $timeTrackingLink = $timerId ? $endTimerLink : $startTimerLink;
                                        $timeTrackingLink = "<span id=\"projecttimercontrol" . $assocProject->id . "\" class=\"tickettimer\">" . $timeTrackingLink . "</span>";
                                        $tableData[] = ["<a href=\"" . $projectUrl . "\">" . $assocProject->id . "</a>", "<a href=\"" . $projectUrl . "\">" . $assocProject->title . "</a> " . $timeTrackingLink, $assocProject->adminname, $assocProject->created, $assocProject->duedate, $assocProject->lastmodified, $assocProject->status];
                                    }
                                }
                                $createNewLink = "";
                                if ($assocProjects->count() == 0) {
                                    echo "<div align=\"center\">" . $vars["_lang"]["noassociatedprojectsfound"] . "</div>";
                                } else {
                                    $dataSetFloor = $offsetNum + 1;
                                    $dataSetCeil = $offsetNum + $limitNum;
                                    $dataSetCeil = $assocProjectsTotal < $dataSetCeil ? $assocProjectsTotal : $dataSetCeil;
                                    echo "<div style=\"padding:0 0 5px 0;text-align:left;\">\n    Showing <strong>" . $dataSetFloor . "</strong> to <strong>" . $dataSetCeil . "</strong> of <strong>" . $assocProjectsTotal . " total</strong>\n</div>";
                                    echo $aInt->sortableTable([$vars["_lang"]["projectid"], $vars["_lang"]["title"], $vars["_lang"]["assignedto"], $vars["_lang"]["created"], $vars["_lang"]["duedate"], $vars["_lang"]["lastmodified"], $vars["_lang"]["status"]], $tableData);
                                    $prevPageElem = $offsetNum - $limitNum < 0 ? "" : "<a href=\"#\" onclick=\"getProjects(" . ($offsetNum - $limitNum) . ");return false;\">";
                                    $nextPageElem = $assocProjectsTotal <= $offsetNum + $limitNum ? "" : "<a href=\"#\" onclick=\"getProjects(" . ($offsetNum + $limitNum) . ");return false;\">";
                                    echo "<table width=\"80%\" align=\"center\">\n    <tr>\n        <td style=\"text-align:left;\">" . $prevPageElem . "&laquo; " . $vars["_lang"]["js"]["tablePrevious"] . "</a></td>\n        <td style=\"text-align:right;\">" . $nextPageElem . $vars["_lang"]["js"]["tableNext"] . " &raquo;</a></td>\n    </tr>\n</table>";
                                }
                                exit;
                            } else {
                                if ($a == "projectstarttimer") {
                                    check_token("WHMCS.admin.default");
                                    $ticketId = (int) App::getFromRequest("tid");
                                    $projectId = (int) App::getFromRequest("pid");
                                    $optionsOutput = "";
                                    $projectTasks = WHMCS\Database\Capsule::table("mod_projecttasks")->where(["mod_projecttasks.projectid" => $projectId, ["mod_project.ticketids", "like", "%" . $ticketId . "%"]])->join("mod_project", "mod_projecttasks.projectid", "=", "mod_project.id")->get(["mod_project.title", "mod_projecttasks.id", "mod_projecttasks.task"]);
                                    foreach ($projectTasks as $projectTask) {
                                        $optionsOutput .= "<option value=\"" . $projectTask->id . "\">" . $projectId . " - " . $projectTask->title . " - " . $projectTask->task . "</option>";
                                    }
                                    $tokenOutput = generate_token();
                                    $htmlOutput = "<div class=\"title\">" . $vars["_lang"]["startTracking"] . "</div>\n<form id=\"ajaxstarttimerform\">\n    " . $tokenOutput . "\n    <input type=\"hidden\" id=\"ajaxstarttimerformprojectid\" name=\"projectid\" value=\"" . $projectId . "\"/>\n    <input type=\"hidden\" name=\"ticketnum\" value=\"" . $ticketId . "\" />\n    <div class=\"label\" style=\"margin-bottom: 3px;display: block;text-align: left;\">\n        " . $vars["_lang"]["selectExisting"] . "\n    </div>\n    <select class=\"form-control\" name=\"taskid\">\n        <option value=\"\">" . $vars["_lang"]["chooseOne"] . "</option>\n        " . $optionsOutput . "\n    </select>\n    <br />\n    <div class=\"label\" style=\"margin-bottom: 3px;display: block;text-align: left;\">\n        " . $vars["_lang"]["orCreateNew"] . "\n    </div>\n    <input type=\"text\" name=\"title\" class=\"form-control\" /><br />\n    <div align=\"center\">\n        <input type=\"button\"\n               value=\"" . $vars["_lang"]["start"] . "\"\n               onclick=\"projectstarttimersubmit();return false\"\n               class=\"btn btn-primary\"\n        />\n        <input type=\"button\"\n               value=\"" . $vars["_lang"]["cancel"] . "\"\n               class=\"btn btn-default\"\n               onclick=\"projectpopupcancel();return false\"\n        />\n    </div>\n</form>";
                                    echo $htmlOutput;
                                    exit;
                                } else {
                                    if ($a == "projectendtimer") {
                                        check_token("WHMCS.admin.default");
                                        $adminId = (int) (new WHMCS\Authentication\CurrentUser())->admin()->id;
                                        $ticketId = (int) App::getFromRequest("tid");
                                        $projectId = (int) App::getFromRequest("pid");
                                        $activeTimers = WHMCS\Database\Capsule::table("mod_projecttimes")->where(["mod_projecttimes.adminid" => $adminId, "mod_projecttimes.projectid" => $projectId, "mod_projecttimes.end" => "", ["mod_project.ticketids", "like", "%" . $ticketId . "%"]])->join("mod_projecttasks", "mod_projecttimes.taskid", "=", "mod_projecttasks.id")->join("mod_project", "mod_projecttimes.projectid", "=", "mod_project.id")->get(["mod_projecttimes.id", "mod_project.title", "mod_projecttimes.taskid", "mod_projecttasks.task", "mod_projecttimes.start"]);
                                        $activeTimersOutput = "";
                                        foreach ($activeTimers as $activeTimer) {
                                            $startDateTime = fromMySQLDate(date("Y-m-d H:i:s", $activeTimer->start), 1);
                                            $startTimeSeconds = date("s", $activeTimer->start);
                                            $activeTimersOutput .= "<div class=\"stoptimer" . $activeTimer->id . "\"\n     style=\"padding:10px;border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;\"\n >\n    <a href=\"#\"\n       onclick=\"projectendtimersubmit('" . $projectId . "', '" . $activeTimer->id . "');return false\"\n       class=\"btn btn-info btn-sm pull-right\"\n    >" . $vars["_lang"]["endTimer"] . "</a>\n    <em>" . $activeTimer->title . " - " . $vars["_lang"]["projectid"] . " " . $projectId . "</em><br />\n    &nbsp;&raquo; " . $activeTimer->task . "<br />\n    " . $vars["_lang"]["startedtimerat"] . " " . $startDateTime . ":" . $startTimeSeconds . "\n</div>";
                                        }
                                        $htmlOutput = "<div class=\"title\">" . $vars["_lang"]["stopTracking"] . "</div>\n<form id=\"ajaxendtimerform\">\n    <input type=\"hidden\" id=\"ajaxendtimerformprojectid\" name=\"projectid\">\n    <h4 style=\"margin:20px 0 10px;\">" . $vars["_lang"]["activeTimers"] . "</h4>\n    <div id=\"activetimers\">";
                                        $htmlOutput .= $activeTimersOutput;
                                        $htmlOutput .= "    </div>\n    <br />\n    <div align=\"center\">\n        <input type=\"button\"\n               value=\"" . $vars["_lang"]["cancel"] . "\"\n               class=\"btn btn-default\"\n               onclick=\"projectpopupcancel();return false\"\n        />\n    </div>\n</form>";
                                        echo $htmlOutput;
                                        exit;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if ($projectid) {
        $result = select_query("mod_project", "", ["id" => $projectid]);
        $data = mysql_fetch_array($result);
        $projectid = $data["id"];
        if (!$projectid) {
            echo "<p><b>" . $vars["_lang"]["viewingproject"] . "</b></p><p>" . $vars["_lang"]["projectidnotfound"] . "</p>";
            return NULL;
        }
        $title = $data["title"];
        $attachments = $data["attachments"];
        $ticketids = $data["ticketids"];
        $notes = $data["notes"];
        $userid = $data["userid"];
        $adminid = $data["adminid"];
        $created = $data["created"];
        $duedate = $data["duedate"];
        $completed = $data["completed"];
        $projectstatus = $data["status"];
        $lastmodified = $data["lastmodified"];
        $daysleft = project_management_daysleft($duedate, $vars);
        $attachments = explode(",", $attachments);
        $ticketids = explode(",", $ticketids);
        $created = fromMySQLDate($created);
        $duedate = fromMySQLDate($duedate);
        $lastmodified = fromMySQLDate($lastmodified, true);
        $client = "";
        if (!$userid) {
            foreach ($ticketids as $i => $ticketnum) {
                if ($ticketnum) {
                    $result = select_query("tbltickets", "userid", ["tid" => $ticketnum]);
                    $data = mysql_fetch_array($result);
                    $userid = $data["userid"];
                    update_query("mod_project", ["userid" => $userid], ["id" => $projectid]);
                }
            }
        }
        if ($userid) {
            $result = select_query("tblclients", "id,firstname,lastname,companyname", ["id" => $userid]);
            $data = mysql_fetch_array($result);
            $clientname = $data[1] . " " . $data[2];
            if ($data[3]) {
                $clientname .= " (" . $data[3] . ")";
            }
            $client = "<a href=\"clientssummary.php?userid=" . $userid . "\">" . $clientname . "</a>";
        }
        $headtitle = $title;
    } else {
        $headtitle = $vars["_lang"]["newproject"];
        $daysleft = $client = "";
        $created = getTodaysDate();
        $duedate = getTodaysDate();
    }
    $admin = trim(get_query_val("tbladmins", "CONCAT(firstname,' ',lastname)", ["id" => $adminid]));
    if (!$admin) {
        $admin = $vars["_lang"]["none"];
    }
    if (!$client) {
        $client = $vars["_lang"]["none"];
    }
    $output = [];
    $output["tasks"] = $project->tasks()->listall();
    $output["tasksSummary"] = $project->tasks()->getTaskSummary();
    $output["messages"] = $project->messages()->get();
    $output["tickets"] = $project->tickets()->get();
    $output["invoices"] = $project->invoices()->get();
    $output["departments"] = $project->tickets()->getDepartments();
    if ($project->userid) {
        $client = WHMCS\User\Client::find($project->userid);
    }
    $output["client"] = $client ?: NULL;
    $output["contacts"] = $client ? $client->contacts ? $client->contacts->pluck("fullName", "id") : [] : [];
    $output["files"] = $project->files()->get();
    $timers = $project->timers();
    $output["timers"] = $timers->get();
    $output["openTimerId"] = $timers->getOpenTimerId();
    $output["timerStats"] = $timers->getStats();
    $output["taskTemplates"] = WHMCS\Module\Addon\ProjectManagement\Models\Task\Template::all()->pluck("name", "id");
    $output["log"] = $project->log()->get();
    $output["admins"] = WHMCS\Module\Addon\ProjectManagement\Helper::getAdmins();
    $output["adminName"] = array_key_exists($project->adminid, $output["admins"]) ? $output["admins"][$project->adminid] : "Unassigned";
    $output["maxFileSize"] = WHMCS\Module\Addon\ProjectManagement\Helper::getFriendlyMbValue(ini_get("upload_max_filesize"));
    $output["hourlyRate"] = $vars["hourlyrate"];
    $output["statuses"] = explode(",", $vars["statusvalues"]);
    $language = $vars["_lang"];
    $output["emailTemplates"] = WHMCS\Mail\Template::master()->where("type", "general")->pluck("name", "id")->toArray();
    $output["now"] = WHMCS\Carbon::now()->toAdminDateTimeFormat();
    $output["oneWeek"] = WHMCS\Carbon::now()->addWeek()->toAdminDateTimeFormat();
    $output["dateTimeFormat"] = WHMCS\Config\Setting::getValue("DateFormat") . " H:i";
    echo $headeroutput;
    include "views/view.php";
}
