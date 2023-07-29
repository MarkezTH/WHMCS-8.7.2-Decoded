<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$limitStart = (int) App::getFromRequest("limitstart");
$limitNum = (int) App::getFromRequest("limitnum");
$sorting = strtoupper(App::getFromRequest("sorting"));
$search = App::getFromRequest("search");
$users = WHMCS\User\User::query();
if (0 < strlen(trim($search))) {
    $users->where(function ($query) use($search) {
        $query->where("email", "like", $search . "%")->orWhere("first_name", "like", $search . "%")->orWhere("last_name", "like", $search . "%")->orWhere(WHMCS\Database\Capsule::raw("CONCAT(first_name, \" \", last_name)"), "like", $search . "%");
    });
}
if (!$limitStart) {
    $limitStart = 0;
}
if (!$limitNum || $limitNum == 0 || !is_numeric($limitNum)) {
    $limitNum = 25;
}
if (!in_array($sorting, ["ASC", "DESC"])) {
    $sorting = "ASC";
}
$totalCount = $users->count();
$results = $users->orderBy("last_name", $sorting)->orderBy("first_name", $sorting)->offset($limitStart)->limit($limitNum)->get();
$apiresults = ["result" => "success", "totalresults" => $totalCount, "startnumber" => $limitStart, "numreturned" => $results->count(), "users" => []];
foreach ($results as $data) {
    $id = $data->id;
    $firstName = $data->first_name;
    $lastName = $data->last_name;
    $email = $data->email;
    $dateCreated = $data->created_at;
    $clients = [];
    foreach ($data->clients as $client) {
        $clients[] = ["id" => $client->id, "isOwner" => (bool) $data->isOwner($client)];
    }
    $apiresults["users"][] = ["id" => $id, "firstname" => $firstName, "lastname" => $lastName, "email" => $email, "datecreated" => $dateCreated->toDateTimeString(), "validationdata" => $data->validation ? $data->validation->toArray() : "", "clients" => $clients];
}
$responsetype = "json";
