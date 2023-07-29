<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$id = (int) App::getFromRequest("id");
$email = trim(App::getFromRequest("email"));
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $apiresults = ["result" => "error", "message" => "Please provide a valid email address"];
} else {
    $user = NULL;
    if ($id) {
        try {
            $user = WHMCS\User\User::findOrFail($id);
        } catch (Exception $e) {
            $apiresults = ["result" => "error", "message" => "User Not Found"];
            return NULL;
        }
    }
    if (!$id && !$email) {
        $apiresults = ["result" => "error", "message" => "Please enter the email address or provide the id"];
    } else {
        if ($email) {
            try {
                $user = WHMCS\User\User::where("email", $email)->first();
                if (!$user) {
                    $client = WHMCS\User\Client::where("email", $email)->where("status", "!=", WHMCS\User\Client::STATUS_CLOSED)->first();
                    if ($client) {
                        $user = $client->owner();
                    }
                }
            } catch (Exception $e) {
            }
        }
        try {
            if ($user) {
                $email = $user->email;
                $user->sendPasswordResetEmail();
            }
            $apiresults = ["result" => "success", "email" => $email];
        } catch (Throwable $e) {
            $apiresults = ["result" => "error", "email" => $e->getMessage()];
        }
    }
}
