<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$statuses = ["Pending" => 0, "Active" => 0, "Fraud" => 0, "Cancelled" => 0];
$result = full_query("SELECT status, COUNT(*) AS count FROM tblorders GROUP BY status");
$apiresults = ["result" => "success", "totalresults" => 4];
while ($data = mysql_fetch_array($result)) {
    $statuses[$data["status"]] = $data["count"];
}
foreach ($statuses as $status => $ordercount) {
    $apiresults["statuses"]["status"][] = ["title" => $status, "count" => $ordercount];
}
$responsetype = "xml";
