<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$clientId = (int) App::getFromRequest("clientid");
$description = App::getFromRequest("description");
$invoiceAction = App::getFromRequest("invoiceaction");
$recur = (int) App::getFromRequest("recur");
$recurCycle = App::getFromRequest("recurcycle");
$recurfor = (int) App::getFromRequest("recurfor");
$dueDate = App::getFromRequest("duedate");
$quantity = (double) App::getFromRequest("quantity");
$unit = App::getFromRequest("unit");
$hours = (double) App::getFromRequest("hours");
$amount = (double) App::getFromRequest("amount");
$unit = strtolower($unit);
$invoiceAction = strtolower($invoiceAction);
if (!$quantity && $hours) {
    $quantity = $hours;
}
$clientId = WHMCS\Database\Capsule::table("tblclients")->where("id", $clientId)->value("id");
if (!$clientId) {
    $apiresults = ["result" => "error", "message" => "Client ID not Found"];
} else {
    if (!$description) {
        $apiresults = ["result" => "error", "message" => "You must provide a description"];
    } else {
        $allowedTypes = ["noinvoice", "nextcron", "nextinvoice", "duedate", "recur"];
        if ($invoiceAction && !in_array($invoiceAction, $allowedTypes)) {
            $apiresults = ["result" => "error", "message" => "Invalid Invoice Action"];
        } else {
            if ($invoiceAction == "recur" && (!$recur && !$recurCycle || !$recurfor)) {
                $apiresults = ["result" => "error", "message" => "Recurring must have a unit, cycle and limit"];
            } else {
                if ($invoiceAction == "duedate" && !$dueDate) {
                    $apiresults = ["result" => "error", "message" => "Due date is required"];
                } else {
                    try {
                        $dueDate = $dueDate ? WHMCS\Carbon::parse($dueDate) : "";
                    } catch (Throwable $e) {
                        $apiresults = ["result" => "error", "message" => "Invalid Date Format - Expected: 'YYYY-mm-dd'"];
                        return NULL;
                    }
                    $allowedUnits = ["hours", "quantity"];
                    if (!in_array($unit, $allowedUnits)) {
                        $apiresults = ["result" => "error", "message" => "Invalid Unit, please specify either 'hours' or 'quantity'"];
                    } else {
                        $unit = $unit === "hours" ? 0 : 1;
                        if ($invoiceAction == "noinvoice") {
                            $invoiceAction = "0";
                        } else {
                            if ($invoiceAction == "nextcron") {
                                $invoiceAction = "1";
                                if (!$dueDate) {
                                    $dueDate = WHMCS\Carbon::now();
                                }
                            } else {
                                if ($invoiceAction == "nextinvoice") {
                                    $invoiceAction = "2";
                                } else {
                                    if ($invoiceAction == "duedate") {
                                        $invoiceAction = "3";
                                    } else {
                                        if ($invoiceAction == "recur") {
                                            $invoiceAction = "4";
                                        }
                                    }
                                }
                            }
                        }
                        $id = WHMCS\Database\Capsule::table("tblbillableitems")->insertGetId(["userid" => $clientId, "description" => $description, "hours" => $quantity, "unit" => $unit, "amount" => $amount, "recur" => $recur, "recurcycle" => $recurCycle, "recurfor" => $recurfor, "invoiceaction" => $invoiceAction, "duedate" => $dueDate]);
                        $apiresults = ["result" => "success", "billableid" => $id];
                        $responsetype = "json";
                    }
                }
            }
        }
    }
}
