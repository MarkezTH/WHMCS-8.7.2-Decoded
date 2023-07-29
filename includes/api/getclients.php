<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$limitStart = (int) App::getFromRequest("limitstart");
$limitNum = (int) App::getFromRequest("limitnum");
$sorting = strtoupper(App::getFromRequest("sorting"));
$status = ucfirst(strtolower(App::getFromRequest("status")));
$search = App::getFromRequest("search");
$orderBy = trim(strtolower(App::getFromRequest("orderby")));
$query = WHMCS\Database\Capsule::table("tblclients")->select(["id", "firstname", "lastname", "companyname", "email", "groupid", "datecreated", "status"]);
if (!$limitStart) {
    $limitStart = 0;
}
if (!$limitNum || $limitNum == 0) {
    $limitNum = 25;
}
if (!in_array($sorting, ["ASC", "DESC"])) {
    $sorting = "ASC";
}
$orderByAllowedColumns = ["id", "firstname", "lastname", "companyname", "email", "groupid", "datecreated", "status"];
if (!in_array($orderBy, $orderByAllowedColumns)) {
    $orderBy = "lastname";
}
$supportedStatusNames = [WHMCS\User\Client::STATUS_ACTIVE, WHMCS\User\Client::STATUS_INACTIVE, WHMCS\User\Client::STATUS_CLOSED];
if ($status) {
    if (in_array($status, $supportedStatusNames)) {
        $query->where("status", $status);
    } else {
        $apiresults = ["result" => "error", "message" => "Invalid client status supplied. Supported client statuses include: " . implode(", ", $supportedStatusNames)];
        return NULL;
    }
}
if (0 < strlen(trim($search))) {
    $query->where(function ($query) use($search) {
        $query->where("email", "like", $search . "%")->orWhere("firstname", "like", $search . "%")->orWhere("lastname", "like", $search . "%")->orWhere("companyname", "like", $search . "%")->orWhere(WHMCS\Database\Capsule::raw("CONCAT(firstname, \" \", lastname)"), "like", $search . "%");
    });
}
$results = $query->orderBy($orderBy, $sorting)->offset($limitStart)->limit($limitNum)->get();
$apiresults = ["result" => "success", "totalresults" => $query->count(), "startnumber" => $limitStart, "numreturned" => $results->count()];
foreach ($results as $data) {
    $id = $data->id;
    $firstName = $data->firstname;
    $lastName = $data->lastname;
    $companyName = $data->companyname;
    $email = $data->email;
    $groupID = $data->groupid;
    $dateCreated = $data->datecreated;
    $status = $data->status;
    $apiresults["clients"]["client"][] = ["id" => $id, "firstname" => $firstName, "lastname" => $lastName, "companyname" => $companyName, "email" => $email, "datecreated" => $dateCreated, "groupid" => $groupID, "status" => $status];
}
$responsetype = "xml";
