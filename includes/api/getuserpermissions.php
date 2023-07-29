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
$clientRelation = $user->clients()->find($client->id);
if (!$clientRelation) {
    $apiresults = ["result" => "error", "message" => "User is not associated with client"];
} else {
    $permissions = $clientRelation->pivot->getPermissions();
    $isOwner = $client->isOwnedBy($user);
    if ($isOwner) {
        $permissions = WHMCS\User\Permissions::all();
    }
    $apiresults = ["result" => "success", "user_id" => $user->id, "client_id" => $client->id, "is_owner" => $isOwner, "permissions" => $permissions->get()];
}
