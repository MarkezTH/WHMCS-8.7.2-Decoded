<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$adminId = WHMCS\Session::get("adminid");
$showActive = $showAwaiting = [];
$ticketStatuses = WHMCS\Database\Capsule::table("tblticketstatuses")->get(["title", "showactive", "showawaiting"])->all();
foreach ($ticketStatuses as $status) {
    if ($status->showactive) {
        $showActive[] = $status->title;
    }
    if ($status->showawaiting) {
        $showAwaiting[] = $status->title;
    }
}
$applyDepartmentFilter = (bool) (!App::getFromRequest("ignoreDepartmentAssignments"));
$adminSupportDepartmentsQuery = [];
if ($applyDepartmentFilter) {
    $adminSupportDepartments = get_query_val("tbladmins", "supportdepts", ["id" => $adminId]);
    $adminSupportDepartments = explode(",", $adminSupportDepartments);
    foreach ($adminSupportDepartments as $departmentId) {
        if (trim($departmentId)) {
            $adminSupportDepartmentsQuery[] = (int) $departmentId;
        }
    }
}
$appConfig = App::getApplicationConfig()->getData();
if (array_key_exists("disable_admin_ticket_page_counts", $appConfig) && $appConfig["disable_admin_ticket_page_counts"]) {
    $allActive = "x";
    $awaitingReply = "x";
    $flaggedTickets = "x";
} else {
    $flaggedTickets = WHMCS\Database\Capsule::table("tbltickets")->where("merged_ticket_id", 0)->whereIn("status", $showActive)->where("flag", (int) $adminId)->count();
    $query = WHMCS\Database\Capsule::table("tbltickets")->where("merged_ticket_id", 0);
    if (0 < count($adminSupportDepartmentsQuery)) {
        $query->whereIn("did", $adminSupportDepartmentsQuery);
    }
    $allActive = $query->whereIn("status", $showActive)->count();
    $query = WHMCS\Database\Capsule::table("tbltickets")->where("merged_ticket_id", 0);
    if (0 < count($adminSupportDepartmentsQuery)) {
        $query->whereIn("did", $adminSupportDepartmentsQuery);
    }
    $awaitingReply = $query->whereIn("status", $showAwaiting)->count();
    unset($allTickets);
}
$apiresults = ["result" => "success", "filteredDepartments" => $adminSupportDepartmentsQuery, "allActive" => $allActive, "awaitingReply" => $awaitingReply, "flaggedTickets" => $flaggedTickets];
if (App::getFromRequest("includeCountsByStatus")) {
    $ticketCounts = [];
    $ticketStatuses = WHMCS\Database\Capsule::table("tblticketstatuses")->pluck(WHMCS\Database\Capsule::raw("0"), "title")->all();
    $tickets = WHMCS\Database\Capsule::table("tbltickets")->where("merged_ticket_id", "=", "0")->selectRaw("status, COUNT(*) as count")->groupBy("status")->pluck("count", "status")->all();
    foreach ($tickets as $status => $count) {
        $ticketStatuses[$status] = $count;
    }
    foreach ($ticketStatuses as $ticketStatus => $ticketCount) {
        $ticketCounts[preg_replace("/[^a-z0-9]/", "", strtolower($ticketStatus))] = ["title" => $ticketStatus, "count" => $ticketCount];
    }
    $apiresults["status"] = $ticketCounts;
}
