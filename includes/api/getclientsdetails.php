<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getClientsDetails")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
$clientid = App::getFromRequest("clientid");
$email = App::getFromRequest("email");
if (!$clientid && !$email) {
    $apiresults = ["result" => "error", "message" => "Either clientid Or email Is Required"];
} else {
    try {
        if ($clientid) {
            $client = WHMCS\User\Client::with("currencyrel")->findOrFail($clientid);
        } else {
            $client = WHMCS\User\Client::with("currencyrel")->where("email", $email)->firstOrFail();
        }
    } catch (Exception $e) {
        $apiresults = ["result" => "error", "message" => "Client Not Found"];
        return NULL;
    }
    $clientid = $client->id;
    $clientsdetails = getClientsDetails($client);
    unset($clientsdetails["model"]);
    $clientsdetails["currency_code"] = $client->currencyrel->code;
    $users = [];
    foreach ($client->users()->get() as $user) {
        $users["user"][] = ["id" => $user->id, "name" => $user->fullName, "email" => $user->email, "is_owner" => $user->id == $client->owner()->id];
    }
    $clientsdetails["users"] = $users;
    $apiresults = array_merge(["result" => "success"], $clientsdetails);
    if ($clientsdetails["cctype"]) {
        $apiresults["warning"] = "Credit Card related parameters are now deprecated and have been removed. Use GetPayMethods instead.";
    }
    unset($clientsdetails["cctype"]);
    unset($clientsdetails["cclastfour"]);
    unset($clientsdetails["gatewayid"]);
    $userRequestedResponseType = is_object($request) ? $request->getResponseFormat() : NULL;
    if (is_null($userRequestedResponseType) || WHMCS\Api\ApplicationSupport\Http\ResponseFactory::isTypeHighlyStructured($userRequestedResponseType)) {
        $apiresults["client"] = $clientsdetails;
        if (!empty($stats) || $userRequestedResponseType == WHMCS\Api\ApplicationSupport\Http\ResponseFactory::RESPONSE_FORMAT_XML) {
            $apiresults["stats"] = getClientsStats($clientid);
        }
    }
}
