<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("saveCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
if (!function_exists("openNewTicket")) {
    require ROOTDIR . "/includes/ticketfunctions.php";
}
$errorResponse = function ($message) {
    return ["result" => "error", "message" => $message];
};
$useMarkdown = stringLiteralToBool(App::getFromRequest("markdown"));
$from = [];
$user = NULL;
$clientid = (int) App::getFromRequest("clientid");
$userid = (int) App::getFromRequest("userid");
$contactid = (int) App::getFromRequest("contactid");
$name = (string) App::getFromRequest("name");
$email = (string) App::getFromRequest("email");
$deptid = (int) App::getFromRequest("deptid");
$subject = (string) App::getFromRequest("subject");
$message = (string) App::getFromRequest("message");
$priority = (string) App::getFromRequest("priority");
$created = (string) App::getFromRequest("created");
$serviceid = (string) App::getFromRequest("serviceid");
$domainid = (int) App::getFromRequest("domainid");
$customfields = (string) App::getFromRequest("customfields");
if ($customfields) {
    $customfields = base64_decode($customfields);
    $customfields = safe_unserialize($customfields);
}
if (!is_array($customfields)) {
    $customfields = [];
}
if ($clientid) {
    try {
        $client = WHMCS\User\Client::findOrFail($clientid);
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
        return NULL;
    } catch (Throwable $e) {
        $apiresults = ["result" => "error", "message" => $e->getMessage()];
        return NULL;
    }
    if ($userid) {
        try {
            $user = $client->users()->findOrFail($userid);
        } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $apiresults = ["result" => "error", "message" => "The system cannot find the provided user ID."];
            return NULL;
        } catch (Throwable $e) {
            $apiresults = ["result" => "error", "message" => $e->getMessage()];
            return NULL;
        }
    }
    if ($contactid) {
        $result = select_query("tblcontacts", "id", ["id" => $contactid, "userid" => $clientid]);
        $data = mysql_fetch_array($result);
        if (!is_array($data) || empty($data["id"])) {
            $apiresults = ["result" => "error", "message" => "Contact ID Not Found"];
            return NULL;
        }
    }
    $from = ["name" => "", "email" => ""];
} else {
    if (!$name || !$email) {
        $apiresults = ["result" => "error", "message" => "Name and email address are required if not a client"];
        return NULL;
    }
    $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$validEmail) {
        $apiresults = ["result" => "error", "message" => "Email Address Invalid"];
        return NULL;
    }
    $from = ["name" => $name, "email" => $email];
}
$result = select_query("tblticketdepartments", "", ["id" => $deptid]);
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data["id"])) {
    $apiresults = ["result" => "error", "message" => "Department ID not found"];
} else {
    if (!$subject) {
        $apiresults = ["result" => "error", "message" => "Subject is required"];
    } else {
        if (!$message) {
            $apiresults = ["result" => "error", "message" => "Message is required"];
        } else {
            if (!$priority || !in_array($priority, ["Low", "Medium", "High"])) {
                $priority = "Low";
            }
            $timeDateNow = false;
            if (!$created) {
                $created = WHMCS\Carbon::now();
            } else {
                try {
                    $created = WHMCS\Carbon::parse($created);
                    $timeDateNow = WHMCS\Carbon::now();
                } catch (Exception $e) {
                    $apiresults = ["result" => "error", "message" => "Invalid Date Format"];
                    return NULL;
                }
            }
            if ($timeDateNow && !$created->lte($timeDateNow)) {
                $apiresults = ["result" => "error", "message" => "Ticket creation date cannot be in the future"];
            } else {
                if ($serviceid) {
                    if (is_numeric($serviceid) || substr($serviceid, 0, 1) == "S") {
                        $result = select_query("tblhosting", "id", ["id" => $serviceid, "userid" => $clientid]);
                        $data = mysql_fetch_array($result);
                        if (!is_array($data) || empty($data["id"])) {
                            $apiresults = ["result" => "error", "message" => "Service ID Not Found"];
                            return NULL;
                        }
                        $serviceid = "S" . $data["id"];
                    } else {
                        $serviceid = substr($serviceid, 1);
                        $result = select_query("tbldomains", "id", ["id" => $serviceid, "userid" => $clientid]);
                        $data = mysql_fetch_array($result);
                        if (!$data["id"]) {
                            $apiresults = ["result" => "error", "message" => "Service ID Not Found"];
                            return NULL;
                        }
                        $serviceid = "D" . $data["id"];
                    }
                }
                if ($domainid) {
                    $result = select_query("tbldomains", "id", ["id" => $domainid, "userid" => $clientid]);
                    $data = mysql_fetch_array($result);
                    if (!is_array($data) || empty($data["id"])) {
                        $apiresults = ["result" => "error", "message" => "Domain ID Not Found"];
                        return NULL;
                    }
                    $serviceid = "D" . $data["id"];
                }
                $treatAsAdmin = $whmcs->getFromRequest("admin") ? true : false;
                $validationData = ["clientId" => $clientid, "contactId" => $contactid, "name" => $name, "email" => $email, "isAdmin" => $treatAsAdmin, "departmentId" => $deptid, "subject" => $subject, "message" => $message, "priority" => $priority, "relatedService" => $serviceid, "customfields" => $customfields];
                $ticketOpenValidateResults = run_hook("TicketOpenValidation", $validationData);
                if (is_array($ticketOpenValidateResults)) {
                    $hookErrors = [];
                    foreach ($ticketOpenValidateResults as $hookReturn) {
                        if (is_string($hookReturn) && ($hookReturn = trim($hookReturn))) {
                            $hookErrors[] = $hookReturn;
                        }
                    }
                    if ($hookErrors) {
                        $apiresults = ["result" => "error", "message" => implode(". ", $hookErrors)];
                        return NULL;
                    }
                }
                try {
                    if ($attachment = App::getFromRequest("attachments")) {
                        if (!is_array($attachment)) {
                            $attachment = json_decode(base64_decode($attachment), true);
                        }
                        if (is_array($attachment)) {
                            $attachments = saveTicketAttachmentsFromApiCall($attachment);
                        }
                    } else {
                        $attachments = uploadTicketAttachments();
                    }
                } catch (WHMCS\Exception\Storage\StorageException $e) {
                    $apiresults = $errorResponse(sprintf("%s. See activity log for details.", $e->getMessage()));
                    return NULL;
                }
                $noemail = (bool) stringLiteralToBool(App::getFromRequest("noemail"));
                try {
                    $ticketdata = openNewTicket($clientid, $contactid, $deptid, $subject, $message, $priority, $attachments, $from, $serviceid, $cc ?? NULL, $noemail, $treatAsAdmin, $useMarkdown, $created, $user);
                } catch (WHMCS\Exception\Support\TicketMaskIterationException $e) {
                    $apiresults = ["result" => "error", "message" => "Unable to generate ticket number."];
                    return NULL;
                }
                if ($customfields) {
                    saveCustomFields($ticketdata["ID"], $customfields, "support", true);
                }
                $apiresults = ["result" => "success", "id" => $ticketdata["ID"], "tid" => $ticketdata["TID"], "c" => $ticketdata["C"]];
            }
        }
    }
}
