<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("ServerUnsuspendAccount")) {
    require ROOTDIR . "/includes/modulefunctions.php";
}
$serviceId = (int) App::getFromRequest("serviceid");
if (!$serviceId && App::isInRequest("accountid")) {
    $serviceId = (int) App::getFromRequest("accountid");
}
if (!$serviceId) {
    $apiresults = ["result" => "error", "message" => "Service ID is required"];
} else {
    $data = WHMCS\Database\Capsule::table("tblhosting")->leftJoin("tblproducts", "tblhosting.packageid", "=", "tblproducts.id")->where("tblhosting.id", $serviceId)->first(["tblhosting.id as service_id", "tblproducts.servertype as module"]);
    if (!$data) {
        $apiresults = ["result" => "error", "message" => "Service ID not found"];
    } else {
        if (!$data->module) {
            $apiresults = ["result" => "error", "message" => "Service not assigned to a module"];
        } else {
            $serviceId = $data->service_id;
            $result = ServerUnsuspendAccount($serviceId);
            if ($result == "success") {
                $apiresults = ["result" => "success"];
            } else {
                $apiresults = ["result" => "error", "message" => $result];
            }
        }
    }
}
