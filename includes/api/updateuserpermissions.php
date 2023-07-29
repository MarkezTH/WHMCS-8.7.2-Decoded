<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$userId = (int) App::getFromRequest("user_id");
$clientId = (int) App::getFromRequest("client_id");
$permissions = App::getFromRequest("permissions");
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
if (!$permissions) {
    $apiresults = ["result" => "error", "message" => "Missing permissions definition"];
} else {
    if ($client->isOwnedBy($user)) {
        $apiresults = ["result" => "error", "message" => "Permissions cannot be set on a client owner"];
    } else {
        $clientRelation = $user->clients()->find($client->id);
        if (!$clientRelation) {
            $apiresults = ["result" => "error", "message" => "User is not associated with client"];
        } else {
            $permissions = new WHMCS\User\Permissions($permissions);
            try {
                $clientRelation->pivot->setPermissions($permissions)->save();
            } catch (Exception $e) {
                $apiresults = ["result" => "error", "message" => $e->getMessage()];
                return NULL;
            }
            $apiresults = ["result" => "success", "user_id" => $user->id, "client_id" => $client->id, "permissions" => $clientRelation->pivot->getPermissions()->get()];
        }
    }
}
