<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$where = [];
if (App::isInRequest("catid")) {
    $where["catid"] = (int) App::getFromRequest("catid");
}
$result = select_query("tblticketpredefinedreplies", "COUNT(id)", $where);
$data = mysql_fetch_array($result);
$totalresults = $data[0];
$apiresults = ["result" => "success", "totalresults" => $totalresults];
$result = select_query("tblticketpredefinedreplies", "name,reply", $where, "name", "ASC");
while ($data = mysql_fetch_assoc($result)) {
    $apiresults["predefinedreplies"]["predefinedreply"][] = ["name" => $data["name"], "reply" => $data["reply"]];
}
$responsetype = "xml";
