<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$result = select_query("tblannouncements", "id", ["id" => $announcementid]);
$data = mysql_fetch_array($result);
if (!$data["id"]) {
    $apiresults = ["result" => "error", "message" => "Announcement ID Not Found"];
    return false;
}
$title = WHMCS\Input\Sanitize::decode($title);
$announcement = WHMCS\Input\Sanitize::decode($announcement);
$update = [];
if (0 < strlen(trim($date))) {
    $update["date"] = $date;
}
if (0 < strlen(trim($title))) {
    $update["title"] = $title;
}
if (0 < strlen(trim($announcement))) {
    $update["announcement"] = $announcement;
}
if (0 < strlen(trim($published))) {
    $update["published"] = $published;
}
$where = ["id" => $announcementid];
update_query("tblannouncements", $update, $where);
run_hook("AnnouncementEdit", ["announcementid" => $announcementid, "date" => $date, "title" => $title, "announcement" => $announcement, "published" => $published]);
$apiresults = ["result" => "success", "announcementid" => $announcementid];
