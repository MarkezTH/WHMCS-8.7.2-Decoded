<?php

$email = App::getFromRequest("email");
$clientId = App::getFromRequest("client_id");
$permissions = App::getFromRequest("permissions") ?: [];
try {
    $client = WHMCS\User\Client::findOrFail($clientId);
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Invalid client id"];
    return NULL;
}
if (!$email) {
    $apiresults = ["result" => "error", "message" => "Email is required"];
} else {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $apiresults = ["result" => "error", "message" => "The email address entered is not valid"];
    } else {
        if (!$permissions) {
            $apiresults = ["result" => "error", "message" => "User permissions are required"];
        } else {
            $permissions = new WHMCS\User\Permissions($permissions);
            if (0 < $client->users()->where("email", $email)->count()) {
                $apiresults = ["result" => "error", "message" => "User already associated with client"];
            } else {
                WHMCS\User\User\UserInvite::new($email, $permissions, $client->id);
                $apiresults = ["result" => "success"];
            }
        }
    }
}
