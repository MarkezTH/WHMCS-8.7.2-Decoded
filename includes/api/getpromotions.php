<?php
if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$where = [];
if (isset($code) && $code) {
    $where["code"] = (string) $code;
} else {
    if (isset($id) && $id) {
        $where["id"] = (int) $id;
    }
}
$result = select_query("tblpromotions", "", $where, "code", "ASC");
$apiresults = ["result" => "success", "totalresults" => mysql_num_rows($result)];
while ($data = mysql_fetch_assoc($result)) {
    $apiresults["promotions"]["promotion"][] = $data;
}
$responsetype = "xml";
