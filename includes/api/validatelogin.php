<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$_SESSION["adminid"] = "";
$password2 = (string) App::getFromRequest("password2");
$email = (string) App::getFromRequest("email");
$password2 = WHMCS\Input\Sanitize::decode($password2);
try {
    Auth::authenticate($email, $password2);
    $user = Auth::user();
    $apiresults = ["result" => "success", "userid" => $user->id, "passwordhash" => $user->sessionToken()->generateHash(), "twoFactorEnabled" => false];
} catch (WHMCS\Exception\Authentication\UsernameNotFound $e) {
    $apiresults = ["result" => "error", "message" => "Email or Password Invalid"];
} catch (WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
    $apiresults = ["result" => "success", "userid" => WHMCS\Session::get(WHMCS\Authentication\AuthManager::SESSION_TWOFACTOR_CLIENTID_NAME), "twoFactorEnabled" => true];
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Email or Password Invalid"];
}
