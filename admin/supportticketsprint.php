<?php

define("ADMINAREA", true);
require "../init.php";
require "../includes/customfieldfunctions.php";
$aInt = new WHMCS\Admin("List Support Tickets");
$aInt->title = $aInt->lang("support", "printticketversion");
$aInt->requiredFiles(["ticketfunctions"]);
$ticket = WHMCS\Support\Ticket::find($id);
if (!$ticket) {
    $aInt->gracefulExit($aInt->lang("support", "ticketnotfound"));
}
$data = $ticket->toArray();
$id = $data["id"];
$tid = $data["tid"];
$deptid = $data["deptid"];
$department = $data["deptname"];
$pauserid = $data["userid"];
$name = $data["name"];
$email = $data["email"];
$date = $data["date"];
$title = $data["subject"];
$message = $ticket->getSafeMessage();
$tstatus = $data["status"];
$attachment = $data["attachment"];
$urgency = $data["priority"];
$lastreply = $data["lastreply"];
$flag = $data["flag"];
$access = validateAdminTicketAccess($id);
if ($access == "invalidid") {
    $aInt->gracefulExit($aInt->lang("support", "ticketnotfound"));
}
if ($access == "deptblocked") {
    $aInt->gracefulExit($aInt->lang("support", "deptnoaccess"));
}
if ($access == "flagged") {
    $aInt->gracefulExit($aInt->lang("support", "flagnoaccess") . ": " . getAdminName($flag));
}
if ($access) {
    $aInt->gracefulExit("Access Denied");
}
$message = nl2br($message);
$message = ticketAutoHyperlinks($message);
if ($ticket->userid) {
    $clientinfo = $ticket->getOwnerName();
} else {
    $clientinfo = $aInt->lang("support", "notregclient");
}
if (!$lastreply) {
    $lastreply = $date;
}
$date = fromMySQLDate($date, "time");
$lastreply = fromMySQLDate($lastreply, "time");
$outstatus = getStatusColour($tstatus);
ob_start();
echo "\n<p><b>";
echo $title;
echo "</b></p>\n\n<p><b><i>";
echo $aInt->lang("support", "ticketid");
echo ":</i></b> ";
echo $tid;
echo "<br>\n<b><i>";
echo $aInt->lang("support", "department");
echo ":</i></b> ";
echo $department;
echo "<br>\n<b><i>";
echo $aInt->lang("support", "createdate");
echo ":</i></b> ";
echo $date;
echo "<br>\n<b><i>";
echo $aInt->lang("fields", "owner");
echo ":</i></b> ";
echo $ticket->getOwnerName();
echo "<br>\n<b><i>";
echo $aInt->lang("fields", "requestor");
echo ":</i></b> ";
echo $ticket->getRequestorDisplayLabel();
echo "<br>\n<b><i>";
echo $aInt->lang("support", "lastreply");
echo ":</i></b> ";
echo $lastreply;
echo "<br>\n<b><i>";
echo $aInt->lang("fields", "status");
echo ":</i></b> ";
echo $outstatus;
echo "<br>\n<b><i>";
echo $aInt->lang("support", "priority");
echo ":</i></b> ";
echo $urgency;
echo "</p>\n<hr size=1><p>\n";
$customfields = getCustomFields("support", $deptid, $id, true);
foreach ($customfields as $customfield) {
    echo "<b><i>" . $customfield["name"] . ":</i></b> " . nl2br($customfield["value"]) . "<br>";
}
echo "</p><hr size=1>\n\n";
echo $ticket->getRequestorDisplayLabel() . " @ " . $date . "<br><hr size=1><br>" . stripslashes($message) . "<hr size=1>";
foreach ($ticket->replies()->orderBy("date")->get() as $reply) {
    $replyid = $reply->id;
    $userid = $reply->userid;
    $contactid = $reply->contactid;
    $name = $reply->name;
    $email = $reply->email;
    $date = $reply->date;
    $message = $reply->getSafeMessage();
    $attachment = $reply->attachment;
    $attachmentsRemoved = $reply->attachmentsRemoved;
    $admin = $reply->admin;
    $rating = $reply->rating;
    $message = nl2br($message);
    $message = ticketAutoHyperlinks($message);
    echo $reply->getRequestorDisplayLabel() . " @ " . $date . "<br><hr size=1><br>" . $message . "<br><br><hr size=1>";
}
echo "<p align=center style=\"font-size:10px;\">" . $aInt->lang("support", "outputgenby") . " WHMCompleteSolution (www.whmcs.com)</p>";
echo "\n<style>\n.ticket-requestor-name {\n    font-weight: bold;\n}\n.requestor-type-operator {\n    background-color: #5bc0de;\n}\n.requestor-type-owner {\n    background-color: #5cb85c;\n}\n.requestor-type-authorizeduser {\n    background-color: #777;\n}\n.requestor-type-registereduser {\n    background-color: #f0ad4e;\n}\n.requestor-type-subaccount {\n    background-color: #777;\n}\n.requestor-type-guest {\n    background-color: #ccc;\n}\n</style>\n";
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->displayPopUp();
