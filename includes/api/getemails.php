<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$result = select_query("tblclients", "id", ["id" => $clientid]);
$data = mysql_fetch_array($result);
$clientid = $data[0];
if (!$clientid) {
    $apiresults = ["status" => "error", "message" => "Client ID Not Found"];
} else {
    if (!$limitstart) {
        $limitstart = 0;
    }
    if (!$limitnum) {
        $limitnum = 25;
    }
    $where = [];
    $where["userid"] = $clientid;
    if ($date) {
        $where["date"] = ["sqltype" => "LIKE", "value" => $date];
    }
    if ($subject) {
        $where["subject"] = ["sqltype" => "LIKE", "value" => $subject];
    }
    $result = select_query("tblemails", "COUNT(*)", $where);
    $data = mysql_fetch_array($result);
    $totalresults = $data[0];
    $result = select_query("tblemails", "", $where, "id", "DESC", $limitstart . "," . $limitnum);
    $apiresults = ["result" => "success", "totalresults" => $totalresults, "startnumber" => $limitstart, "numreturned" => mysql_num_rows($result)];
    while ($data = mysql_fetch_assoc($result)) {
        $apiresults["emails"]["email"][] = $data;
    }
    $responsetype = "xml";
}
