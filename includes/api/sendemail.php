<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$validCustomEmailTypes = ["general", "product", "domain", "invoice", "support", "affiliate"];
$incomingEmailTplName = $whmcs->get_req_var("messagename");
$incomingRelId = $whmcs->get_req_var("id");
$incomingCustomType = $whmcs->get_req_var("customtype");
$incomingCustomSubject = $whmcs->get_req_var("customsubject");
$incomingCustomMsg = $whmcs->get_req_var("custommessage");
$incomingCustomVars = $whmcs->get_req_var("customvars");
$incomingNonNl2Br = $whmcs->get_req_var("nonl2br");
if (!$incomingEmailTplName && !$incomingCustomType) {
    $apiresults = ["result" => "error", "message" => "You must provide either an existing email template name or a custom message type"];
} else {
    if ($incomingCustomType) {
        if (!in_array($incomingCustomType, $validCustomEmailTypes)) {
            $apiresults = ["result" => "error", "message" => "Invalid message type provided"];
            return NULL;
        }
        if (!$incomingCustomSubject) {
            $apiresults = ["result" => "error", "message" => "A subject is required for a custom message"];
            return NULL;
        }
        if (!$incomingCustomMsg) {
            $apiresults = ["result" => "error", "message" => "A message body is required for a custom message"];
            return NULL;
        }
    }
    if (!$incomingRelId || !is_numeric($incomingRelId)) {
        $apiresults = ["result" => "error", "message" => "A related ID is required"];
    } else {
        if ($incomingCustomType) {
            $messageBody = WHMCS\Input\Sanitize::decode($incomingCustomMsg);
            if (!$incomingNonNl2Br) {
                $messageBody = nl2br($messageBody);
            }
            WHMCS\Mail\Template::where("name", "=", "Mass Mail Template")->delete();
            $template = new WHMCS\Mail\Template();
            $template->type = $incomingCustomType;
            $template->name = "Mass Mail Template";
            $template->subject = $incomingCustomSubject;
            $template->message = $messageBody;
            $template->plaintext = false;
            $template->disabled = false;
        } else {
            $template = WHMCS\Mail\Template::where("name", "=", $incomingEmailTplName)->where("language", "=", "")->first();
            if (is_null($template)) {
                $apiresults = ["result" => "error", "message" => "Email Template not found"];
                return NULL;
            }
            if ($template->disabled) {
                $apiresults = ["result" => "error", "message" => "Email Template is disabled"];
                return NULL;
            }
        }
        $customVars = [];
        if ($incomingCustomVars) {
            if (is_array($incomingCustomVars)) {
                $customVars = $incomingCustomVars;
            } else {
                $customVars = safe_unserialize(base64_decode($incomingCustomVars));
            }
            if (!is_array($customVars)) {
                $apiresults = ["result" => "error", "message" => "The custom variables you provided are invalid."];
                return NULL;
            }
        }
        $sendingResult = sendMessage($template, $incomingRelId, $customVars);
        if ($sendingResult) {
            $apiresults = ["result" => "success"];
        } else {
            $apiresults = ["result" => "error", "message" => "Sending Failed. Please see documentation."];
        }
    }
}
