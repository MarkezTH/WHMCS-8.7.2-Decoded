<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$totalresults = WHMCS\Database\Capsule::table("tblticketpredefinedcats")->count();
$apiresults = ["result" => "success", "totalresults" => $totalresults];
$result = full_query("SELECT c.*, COUNT(r.id) AS replycount FROM tblticketpredefinedcats c LEFT JOIN tblticketpredefinedreplies r ON c.id=r.catid GROUP BY c.id ORDER BY c.name ASC");
while ($data = mysql_fetch_assoc($result)) {
    $apiresults["categories"]["category"][] = $data;
}
$responsetype = "xml";
