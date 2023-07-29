<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$identifier = App::getFromRequest("notification_identifier");
$title = App::getFromRequest("title");
$message = App::getFromRequest("message");
$url = WHMCS\Input\Sanitize::decode(App::getFromRequest("url"));
$status = App::getFromRequest("status");
$statusStyle = App::getFromRequest("statusStyle");
$notificationAttributes = App::getFromRequest("attributes");
if (!is_array($notificationAttributes)) {
    $notificationAttributes = [];
}
if (!$identifier) {
    $apiresults = ["result" => "error", "message" => "API Notification Events require a identifier string to be passed."];
} else {
    if (!$title) {
        $apiresults = ["result" => "error", "message" => "API Notification Events require a title to be provided."];
    } else {
        if (!$message) {
            $apiresults = ["result" => "error", "message" => "API Notification Events require a message to be provided."];
        } else {
            $parameters = ["identifier" => $identifier, "title" => $title, "message" => $message, "url" => $url, "status" => $status, "statusStyle" => $statusStyle, "attributes" => $notificationAttributes];
            try {
                WHMCS\Notification\Events::trigger(WHMCS\Notification\Events::API, "api_call", $parameters);
            } catch (Exception $e) {
                $apiresults = ["result" => "error", "message" => "Notification failed to send: " . $e->getMessage()];
                return NULL;
            }
            $apiresults = ["result" => "success", "message" => "Notification Event Triggered"];
        }
    }
}
