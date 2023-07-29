<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$limitStart = (int) App::getFromRequest("limitstart");
$limitNum = App::getFromRequest("limitnum");
if (!$limitStart) {
    $limitStart = 0;
}
if (!$limitNum) {
    $limitNum = 25;
}
$log = new WHMCS\Log\Activity();
$log->setCriteria(["userid" => App::getFromRequest("clientid") ?: App::getFromRequest("userid"), "date" => App::getFromRequest("date"), "username" => App::getFromRequest("user"), "description" => App::getFromRequest("description"), "ipaddress" => App::getFromRequest("ipaddress")]);
$totalResults = $log->getTotalCount();
$apiresults = ["result" => "success", "totalresults" => $totalResults, "startnumber" => $limitStart];
$offset = $limitStart / $limitNum;
$offset = floor($offset);
if ($offset < 0) {
    $offset = 0;
}
$log->setOutputFormatting(App::getFromRequest("format"));
$logOutput = $log->getLogEntries($offset, $limitNum);
$apiresults["activity"]["entry"] = [];
foreach ($logOutput as $output) {
    $output["userid"] = $output["clientId"];
    $apiresults["activity"]["entry"][] = $output;
}
$responsetype = "xml";
