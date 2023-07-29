<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("saveCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
if (!function_exists("AddReply")) {
    require ROOTDIR . "/includes/ticketfunctions.php";
}
$errorResponse = function ($message) {
    return ["result" => "error", "message" => $message];
};
$useMarkdown = stringLiteralToBool(App::get_req_var("markdown"));
$from = "";
$ticketData = WHMCS\Support\Ticket::find($ticketid);
if (!$ticketData) {
    $apiresults = ["result" => "error", "message" => "Ticket ID Not Found"];
} else {
    if (isset($clientid) && $clientid) {
        $result = select_query("tblclients", "id", ["id" => $clientid]);
        $data = mysql_fetch_array($result);
        if (!is_array($data) || empty($data["id"])) {
            $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
            return NULL;
        }
        if (isset($contactid) && $contactid) {
            $result = select_query("tblcontacts", "id", ["id" => $contactid, "userid" => $clientid]);
            $data = mysql_fetch_array($result);
            if (!is_array($data) || empty($data["id"])) {
                $apiresults = ["result" => "error", "message" => "Contact ID Not Found"];
                return NULL;
            }
        }
    } else {
        if ((empty($name) || empty($email)) && empty($adminusername)) {
            $apiresults = ["result" => "error", "message" => "Name and email address are required if not a client"];
            return NULL;
        }
        $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$validEmail && !$adminusername) {
            $apiresults = ["result" => "error", "message" => "Email Address Invalid"];
            return NULL;
        }
        $from = ["name" => $name, "email" => $email];
    }
    if (empty($message)) {
        $apiresults = ["result" => "error", "message" => "Message is required"];
    } else {
        if (isset($status) && $status && $status !== $ticketData->status) {
            $validStatus = false;
            $ticketStatuses = WHMCS\Database\Capsule::table("tblticketstatuses")->select(["title"])->get();
            foreach ($ticketStatuses as $ticketStatus) {
                if (strtolower($ticketStatus->title) === strtolower($status)) {
                    $status = $ticketStatus->title;
                    $validStatus = true;
                    if (!$validStatus) {
                        $apiresults = ["result" => "error", "message" => "Invalid Ticket Status"];
                        return NULL;
                    }
                }
            }
        }
        $adminusername = App::getFromRequest("adminusername");
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
        $timeDateNow = false;
        if (empty($created)) {
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
            $apiresults = ["result" => "error", "message" => "Reply creation date cannot be in the future"];
        } else {
            AddReply($ticketData->id, $clientid, $contactid ?? NULL, $message, $adminusername, $attachments, $from, $status ?? NULL, $noemail ?? NULL, true, $useMarkdown, [], $created);
            if (isset($customfields) && $customfields) {
                $customfields = base64_decode($customfields);
                $customfields = safe_unserialize($customfields);
                saveCustomFields($ticketid, $customfields, "support", true);
            }
            $apiresults = ["result" => "success"];
        }
    }
}
