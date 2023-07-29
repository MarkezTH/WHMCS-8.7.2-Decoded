<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$userId = (int) App::getFromRequest("user_id");
$clientId = (int) App::getFromRequest("client_id");
try {
    $user = WHMCS\User\User::findOrFail($userId);
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Invalid User ID requested"];
    return NULL;
}
try {
    $client = WHMCS\User\Client::findOrFail($clientId);
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Invalid Client ID requested"];
    return NULL;
}
if (!$client->users()->find($user->id)) {
    $apiresults = ["result" => "error", "message" => "User is not associated with client"];
} else {
    if ($client->isOwnedBy($user)) {
        $apiresults = ["result" => "error", "message" => "You cannot remove the account owner"];
    } else {
        $user->clients()->detach($client->id);
        $apiresults = ["result" => "success"];
    }
}
