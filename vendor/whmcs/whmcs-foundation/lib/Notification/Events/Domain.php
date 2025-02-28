<?php

namespace WHMCS\Notification\Events;

class Domain
{
    const DISPLAY_NAME = "Domain";

    public function getEvents()
    {
        return ["registration" => ["label" => "New Registration", "hook" => ["AfterRegistrarRegistration", "AfterRegistrarRegistrationFailed"]], "transfer_initiated" => ["label" => "Transfer Initiated", "hook" => ["AfterRegistrarTransfer", "AfterRegistrarTransferFailed"]], "transfer_completed" => ["label" => "Transfer Completed", "hook" => "DomainTransferCompleted"], "transfer_failed" => ["label" => "Transfer Failed", "hook" => "DomainTransferFailed"], "renewal" => ["label" => "Renewal", "hook" => ["AfterRegistrarRenewal", "AfterRegistrarRenewalFailed"]]];
    }

    public function getConditions()
    {
        return ["action_state" => ["FriendlyName" => "Action State", "Type" => "dropdown", "Options" => ["Successful" => "Successful", "Failed" => "Failed"]], "client_group" => ["FriendlyName" => "Client Group", "Type" => "dropdown", "Options" => function () {
            return \WHMCS\Database\Capsule::table("tblclientgroups")->orderBy("groupname")->pluck("groupname", "id")->all();
        }]];
    }

    public function evaluateConditions($event, $conditions, $hookParameters)
    {
        $domainId = isset($hookParameters["params"]["domainid"]) ? $hookParameters["params"]["domainid"] : "";
        if ($conditions["action_state"]) {
            if ($conditions["action_state"] == "Failed" && !isset($hookParameters["error"])) {
                return false;
            }
            if ($conditions["action_state"] == "Successful" && isset($hookParameters["error"])) {
                return false;
            }
        }
        if ($conditions["client_group"]) {
            $userId = \WHMCS\Database\Capsule::table("tbldomains")->where("id", "=", $domainId)->first()->userid;
            $clientGroup = 0 < $userId ? \WHMCS\User\Client::find($userId)->groupId : "";
            if ($conditions["client_group"] != $clientGroup) {
                return false;
            }
        }
        return true;
    }

    public function buildNotification($event, $hookParameters)
    {
        $domainId = isset($hookParameters["params"]["domainid"]) ? $hookParameters["params"]["domainid"] : "";
        if (!$domainId) {
            $domainId = isset($hookParameters["domainId"]) ? $hookParameters["domainId"] : "";
        }
        if (!$domainId) {
            return false;
        }
        $error = isset($hookParameters["error"]) ? $hookParameters["error"] : "";
        $domain = \WHMCS\Domain\Domain::find($domainId);
        $userId = $domain->clientId;
        $domainName = $domain->domain;
        $status = $domain->status;
        $expiryDate = $domain->expiryDate;
        $firstName = $domain->client->firstName;
        $lastName = $domain->client->lastName;
        $url = \App::getSystemUrl() . \App::get_admin_folder_name() . "/clientsdomains.php?userid=" . $userId . "&id=" . $domainId;
        $clientUrl = \App::getSystemUrl() . \App::get_admin_folder_name() . "/clientssummary.php?userid=" . $userId;
        $title = \AdminLang::trans("notifications.domain." . $event . "Title" . ($error ? "Error" : ""));
        $message = \AdminLang::trans("notifications.domain." . $event . ($error ? "Error" : ""));
        $statusStyle = "primary";
        if ($status == "Active") {
            $statusStyle = "success";
        } else {
            if ($status == "Terminated") {
                $statusStyle = "danger";
            } else {
                if ($status == "Suspended") {
                    $statusStyle = "info";
                }
            }
        }
        $notification = (new \WHMCS\Notification\Notification())->setTitle($title)->setMessage($message)->setUrl($url)->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("fields.client"))->setValue($firstName . " " . $lastName)->setUrl($clientUrl))->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("fields.domain"))->setValue($domainName)->setUrl("http://" . $domainName))->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("fields.expirydate"))->setValue(fromMySQLDate($expiryDate)));
        if ($error) {
            $notification->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("fields.failureMessage"))->setValue($error)->setStyle("danger"));
        }
        return $notification->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("fields.status"))->setValue($status)->setStyle($statusStyle));
    }
}
