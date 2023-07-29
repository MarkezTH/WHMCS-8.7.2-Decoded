<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$where = [];
if (!empty($clientid)) {
    $where["userid"] = $clientid;
}
if (!empty($invoiceid)) {
    $where["invoiceid"] = $invoiceid;
}
if (!empty($transid)) {
    $where["transid"] = $transid;
}
$result = select_query("tblaccounts", "", $where);
$apiresults = ["result" => "success", "totalresults" => mysql_num_rows($result), "startnumber" => 0, "numreturned" => mysql_num_rows($result)];
while ($data = mysql_fetch_assoc($result)) {
    $apiresults["transactions"]["transaction"][] = $data;
}
$responsetype = "xml";
