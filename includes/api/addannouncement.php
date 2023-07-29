<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$title = WHMCS\Input\Sanitize::decode($title);
$announcement = WHMCS\Input\Sanitize::decode($announcement);
$isPublished = $published ? "1" : "0";
$id = insert_query("tblannouncements", ["date" => $date, "title" => $title, "announcement" => $announcement, "published" => $isPublished]);
run_hook("AnnouncementAdd", ["announcementid" => $id, "date" => $date, "title" => $title, "announcement" => $announcement, "published" => $isPublished]);
$apiresults = ["result" => "success", "announcementid" => $id];
