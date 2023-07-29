<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$firstname = App::getFromRequest("firstname");
$lastname = App::getFromRequest("lastname");
$email = App::getFromRequest("email");
$password2 = App::getFromRequest("password2");
$language = App::getFromRequest("language");
if (!$firstname) {
    $apiresults = ["result" => "error", "message" => "You did not enter a first name"];
} else {
    if (!$lastname) {
        $apiresults = ["result" => "error", "message" => "You did not enter a last name"];
    } else {
        if (!$email) {
            $apiresults = ["result" => "error", "message" => "You did not enter an email address"];
        } else {
            if (!$password2) {
                $apiresults = ["result" => "error", "message" => "You did not enter a password"];
            } else {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $apiresults = ["result" => "error", "message" => "The email address entered is not valid"];
                } else {
                    try {
                        $user = WHMCS\User\User::createUser($firstname, $lastname, $email, WHMCS\Input\Sanitize::decode($password2), $language);
                    } catch (WHMCS\Exception\User\EmailAlreadyExists $e) {
                        $apiresults = ["result" => "error", "message" => "A user already exists with that email address"];
                        return NULL;
                    } catch (Exception $e) {
                        $apiresults = ["result" => "error", "message" => $e->getMessage()];
                        return NULL;
                    }
                    $apiresults = ["result" => "success", "user_id" => $user->id];
                }
            }
        }
    }
}
