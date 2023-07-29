<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$clientid = (int) App::getFromRequest("clientid");
$amount = App::getFromRequest("amount");
$type = strtolower(App::getFromRequest("type"));
if (!$type) {
    $type = "add";
}
if (!in_array($type, ["add", "remove"])) {
    $apiresults = ["result" => "error", "message" => "Type can only be add or remove"];
} else {
    if (!$amount) {
        $apiresults = ["result" => "error", "message" => "No Amount Provided"];
    } else {
        $amount = (double) $amount;
        if (!(bool) preg_match("/^[\\d]+(\\.[\\d]{1,2})?\$/i", $amount)) {
            $apiresults = ["result" => "error", "message" => "Amount must be in decimal format: ### or ###.##"];
        } else {
            $client = WHMCS\User\Client::find($clientid);
            if (!$client) {
                $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
            } else {
                $adminId = (int) App::getFromRequest("adminid");
                $date = App::getFromRequest("date");
                if ($date) {
                    try {
                        $checkDate = fromMySQLDate($date);
                        if (!validateDateInput($checkDate)) {
                            throw new InvalidArgumentException("Invalid Date");
                        }
                    } catch (Exception $e) {
                        $apiresults = ["result" => "error", "message" => "Date Format is not Valid"];
                        return NULL;
                    }
                }
                if ($type === "remove" && $client->credit < $amount) {
                    $apiresults = ["result" => "error", "message" => "Insufficient Credit Balance"];
                } else {
                    if (!$date) {
                        $date = "now()";
                    }
                    if ($adminId) {
                        $admin = WHMCS\Database\Capsule::table("tbladmins")->where("id", $adminId)->where("disabled", 0)->first(["id"]);
                        if (!$admin) {
                            $apiresults = ["result" => "error", "message" => "Admin ID Not Found"];
                            return NULL;
                        }
                        $adminId = $admin->id;
                    }
                    if (!$adminId) {
                        $adminId = WHMCS\Session::get("adminid");
                    }
                    $relativeChange = $amount;
                    if ($type === "remove") {
                        $relativeChange = -1 * $relativeChange;
                    }
                    insert_query("tblcredit", ["clientid" => $clientid, "admin_id" => $adminId, "date" => $date, "description" => $description, "amount" => $relativeChange]);
                    $client->credit += $relativeChange;
                    $client->save();
                    $client = $client->fresh();
                    $currency = getCurrency($clientid);
                    $message = "Added Credit - User ID: " . $clientid . " - Amount: " . formatCurrency($amount);
                    if ($type == "remove") {
                        $message = "Removed Credit - User ID: " . $clientid . " - Amount: " . formatCurrency($amount);
                    }
                    logActivity($message, $clientid);
                    $apiresults = ["result" => "success", "newbalance" => $client->credit];
                }
            }
        }
    }
}
