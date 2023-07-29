<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getAdminPermsArray")) {
    require ROOTDIR . "/includes/adminfunctions.php";
}
$iphone = $whmcs->get_req_var("iphone");
$windows8app = $whmcs->get_req_var("windows8app");
$android = $whmcs->get_req_var("android");
$deptId = $whmcs->get_req_var("deptid");
$admin = WHMCS\User\Admin::find((int) WHMCS\Session::get("adminid"));
if (is_null($admin)) {
    $apiresults = ["result" => "error", "message" => "You must be authenticated as an admin user to perform this action"];
} else {
    $apiresults = ["result" => "success", "adminid" => $admin->id, "name" => $admin->firstName . " " . $admin->lastName, "notes" => $admin->notes, "signature" => $admin->signature];
    $adminPermissionsArray = getAdminPermsArray();
    $adminPermissions = Illuminate\Database\Capsule\Manager::table("tbladminperms")->where("roleid", "=", $admin->roleId)->get()->all();
    $apiresults["allowedpermissions"] = "";
    foreach ($adminPermissions as $adminPermission) {
        if (isset($adminPermissionsArray[$adminPermission->permid])) {
            $apiresults["allowedpermissions"] .= $adminPermissionsArray[$adminPermission->permid] . ",";
        }
    }
    $apiresults["departments"] = $admin->supportDepts;
    $apiresults["allowedpermissions"] = substr($apiresults["allowedpermissions"], 0, -1);
    if ($iphone) {
        if (defined("IPHONELICENSE")) {
            exit("License Hacking Attempt Detected");
        }
        global $licensing;
        define("IPHONELICENSE", $licensing->isActiveAddon("iPhone App"));
        $apiresults["iphone"] = IPHONELICENSE;
    }
    if ($windows8app) {
        if (defined("WINDOWS8APPLICENSE")) {
            exit("License Hacking Attempt Detected");
        }
        global $licensing;
        define("WINDOWS8APPLICENSE", $licensing->isActiveAddon("Windows 8 App"));
        $apiresults["windows8app"] = WINDOWS8APPLICENSE;
    }
    if ($android) {
        if (defined("ANDROIDLICENSE")) {
            exit("License Hacking Attempt Detected");
        }
        if (!function_exists("getGatewaysArray")) {
            require ROOTDIR . "/includes/gatewayfunctions.php";
        }
        global $licensing;
        define("ANDROIDLICENSE", $licensing->isActiveAddon("Android App"));
        $apiresults["android"] = ANDROIDLICENSE;
        $statuses = [];
        $ticketStatuses = Illuminate\Database\Capsule\Manager::table("tblticketstatuses")->orderBy("sortorder")->get()->all();
        foreach ($ticketStatuses as $ticketStatus) {
            $statuses[$ticketStatus->title] = 0;
        }
        $ticketStatuses = Illuminate\Database\Capsule\Manager::table("tbltickets")->selectRaw("status, COUNT(*) AS count")->groupBy("status")->get()->all();
        if ($deptId) {
            $ticketStatuses = Illuminate\Database\Capsule\Manager::table("tbltickets")->selectRaw("status, COUNT(*) AS count")->where("did", "=", (int) $deptId)->groupBy("status")->get()->all();
        }
        foreach ($ticketStatuses as $ticketStatus) {
            $statuses[$ticketStatus->status] = $ticketStatus->count;
        }
        foreach ($statuses as $status => $ticketCount) {
            $apiresults["supportstatuses"]["status"][] = ["title" => $status, "count" => $ticketCount];
        }
        $departments = [];
        $dept = Illuminate\Database\Capsule\Manager::table("tblticketdepartments")->get(["id", "name"])->all();
        foreach ($dept as $department) {
            $departments[$department->id] = $department->name;
        }
        foreach ($departments as $departmentId => $departmentName) {
            $apiresults["supportdepartments"]["department"] = ["id" => $departmentId, "name" => $departmentName, "count" => Illuminate\Database\Capsule\Manager::table("tbltickets")->where("did", "=", $departmentId)->count("id")];
        }
        $paymentMethods = getGatewaysArray();
        foreach ($paymentMethods as $module => $name) {
            $apiresults["paymentmethods"]["paymentmethod"][] = ["module" => $module, "displayname" => $name];
        }
    }
    $apiresults["requesttime"] = date("Y-m-d H:i:s");
    $installedVersion = App::getVersion();
    $apiresults["whmcs"] = ["version" => $installedVersion->getCasual(), "canonicalversion" => $installedVersion->getCanonical()];
}
