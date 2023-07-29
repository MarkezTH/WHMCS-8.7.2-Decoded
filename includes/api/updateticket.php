<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("closeTicket")) {
    require ROOTDIR . "/includes/ticketfunctions.php";
}
if (!function_exists("migrateCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
$whmcs = App::self();
$ticketID = (int) $whmcs->get_req_var("ticketid");
$ticket = new WHMCS\Tickets();
if (!$ticket->setID($ticketID)) {
    $apiresults = ["result" => "error", "message" => "Ticket ID Not Found"];
} else {
    $departmentId = $whmcs->get_req_var("deptid") ? (int) $whmcs->get_req_var("deptid") : "";
    $userId = App::isInRequest("userid") ? (int) App::getFromRequest("userid") : NULL;
    $name = $whmcs->get_req_var("name");
    $email = $whmcs->get_req_var("email");
    $cc = $whmcs->get_req_var("cc");
    $subject = $whmcs->get_req_var("subject");
    $priority = $whmcs->get_req_var("priority");
    $created = App::getFromRequest("created");
    $status = $whmcs->get_req_var("status");
    $flag = $whmcs->get_req_var("flag") ? (int) $whmcs->get_req_var("flag") : "";
    $removeFlag = (bool) $whmcs->get_req_var("removeFlag");
    $message = App::getFromRequest("message");
    $customfields = (string) App::getFromRequest("customfields");
    if ($customfields) {
        $customfields = safe_unserialize(base64_decode($customfields));
    }
    if (!is_array($customfields)) {
        $customfields = [];
    }
    if (!is_null($userId) && $userId <= 0 && $userId != (int) $ticket->getData("userid")) {
        $userId = 0;
        if (!$name || !$email) {
            $apiresults = ["result" => "error", "message" => "Name and email address are required if not a client"];
            return NULL;
        }
        $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$validEmail) {
            $apiresults = ["result" => "error", "message" => "Email Address Invalid"];
            return NULL;
        }
    }
    if ($departmentId && $departmentId != (int) $ticket->getData("did") && !$ticket->setDept($departmentId)) {
        $apiresults = ["result" => "error", "message" => "Department ID Not Found"];
    } else {
        if ($priority && $priority != $ticket->getData("urgency") && !$ticket->setPriority($priority)) {
            $apiresults = ["result" => "error", "message" => "Invalid Ticket Priority. Valid priorities are: Low,Medium,High"];
        } else {
            if ($created) {
                try {
                    $created = WHMCS\Carbon::parse($created);
                    $timeDateNow = WHMCS\Carbon::now();
                } catch (Exception $e) {
                    $apiresults = ["result" => "error", "message" => "Invalid Date Format"];
                    return NULL;
                }
                if (!$created->lte($timeDateNow)) {
                    $apiresults = ["result" => "error", "message" => "Ticket creation date cannot be in the future"];
                    return NULL;
                }
            }
            if ($status && $status != "Closed" && $status != $ticket->getData("status") && !$ticket->setStatus($status)) {
                $validStatuses = $ticket->getAssignableStatuses();
                $validStatuses[0] = "";
                $validStatuses[1] = "";
                $validStatuses[2] = "";
                $validStatuses = array_filter($validStatuses);
                $apiresults = ["result" => "error", "message" => "Invalid Ticket Status. Valid statuses are: " . implode(",", $validStatuses)];
            } else {
                if ($flag && $flag != $ticket->getData("flag") && !$ticket->setFlagTo($flag)) {
                    $apiresults = ["result" => "error", "message" => "Invalid Admin ID for Flag"];
                } else {
                    if ($removeFlag && !$flag && $ticket->getData("flag") !== 0) {
                        $ticket->setFlagTo(0);
                    }
                    if ($subject && $subject != $ticket->getData("subject")) {
                        $ticket->setSubject($subject);
                    }
                    if ($status && $status == "Closed" && $status != $ticket->getData("status")) {
                        closeTicket($ticketID);
                    }
                    $updateQuery = [];
                    if (!is_null($userId) && $userId != (int) $ticket->getData("userid")) {
                        $updateQuery["userid"] = $userId;
                    }
                    if ($name && $name != $ticket->getData("name")) {
                        $updateQuery["name"] = $name;
                    }
                    if ($email && $email != $ticket->getData("email")) {
                        $updateQuery["email"] = $email;
                    }
                    if ($cc && $cc != $ticket->getData("cc")) {
                        $updateQuery["cc"] = $cc;
                    }
                    if ($message && $message != $ticket->getData("message")) {
                        $updateQuery["message"] = $message;
                    }
                    if (App::isInRequest("markdown")) {
                        $markdown = "plain";
                        if (App::getFromRequest("markdown")) {
                            $markdown = "markdown";
                        }
                        $updateQuery["editor"] = $markdown;
                    }
                    if ($created && $created instanceof WHMCS\Carbon) {
                        $updateQuery["date"] = $created->toDateTimeString();
                    }
                    if (0 < count($updateQuery)) {
                        update_query("tbltickets", $updateQuery, ["id" => $ticketID]);
                    }
                    if ($customfields) {
                        saveCustomFields($ticketID, $customfields, "support", true);
                    }
                    $apiresults = ["result" => "success", "ticketid" => $ticketID];
                }
            }
        }
    }
}
