<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$serviceId = (int) App::getFromRequest("serviceid");
if (!$serviceId && App::isInRequest("accountid")) {
    $serviceId = (int) App::getFromRequest("accountid");
}
if (!$serviceId) {
    $apiresults = ["result" => "error", "message" => "Service ID is required"];
} else {
    $service = WHMCS\Service\Service::with("product")->find($serviceId);
    if (is_null($service)) {
        $apiresults = ["result" => "error", "message" => "Service ID not found"];
    } else {
        if (!$service->product->module) {
            $apiresults = ["result" => "error", "message" => "Service not assigned to a module"];
        } else {
            $result = $service->legacyProvision();
            if ($result == "success") {
                $apiresults = ["result" => "success"];
            } else {
                $apiresults = ["result" => "error", "message" => $result];
            }
        }
    }
}
