<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$relatedId = (int) App::getFromRequest("relatedId");
$serviceType = App::getFromRequest("serviceType");
$moduleName = App::getFromRequest("moduleName");
$moduleAction = App::getFromRequest("moduleAction");
$since = App::getFromRequest("since");
$acceptedServiceTypes = ["service", "domain", "addon"];
if (!in_array($serviceType, $acceptedServiceTypes)) {
    $serviceType = "";
}
$queue = WHMCS\Module\Queue::incomplete();
switch ($serviceType) {
    case "addon":
        $queue = $queue->with("addon");
        break;
    case "service":
        $queue = $queue->with("service");
        break;
    case "domain":
        $queue = $queue->with("domain");
        break;
    default:
        $queue = $queue->with("service", "domain", "addon");
        if ($relatedId && is_int($relatedId)) {
            $queue = $queue->where("service_id", $relatedId);
        }
        if ($moduleName) {
            $queue = $queue->whereModuleName($moduleName);
        }
        if ($moduleAction) {
            $queue = $queue->whereModuleAction($moduleName);
        }
        if ($since) {
            try {
                $since = trim($since);
                if (strlen($since) == 10) {
                    $since .= " 00:00:00";
                }
                $since = WHMCS\Carbon::createFromFormat("Y-m-d H:i:s", $since);
                $queue = $queue->where("last_attempt", ">=", $since->toDateTimeString());
            } catch (Exception $e) {
            }
        }
        $queue = $queue->get();
        $apiresults = ["result" => "success", "count" => $queue->count(), "queue" => $queue];
        $responsetype = "xml";
}
