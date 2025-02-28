<?php

namespace WHMCS\Notification\Events;

class Ticket
{
    const DISPLAY_NAME = "Ticket";

    public function getEvents()
    {
        return ["opened" => ["label" => "New Ticket", "hook" => "TicketOpen"], "reply_cust" => ["label" => "New Customer Reply", "hook" => "TicketUserReply"], "reply_admin" => ["label" => "New Staff Reply", "hook" => "TicketAdminReply"], "new_note" => ["label" => "New Note", "hook" => "TicketAddNote"], "dept_change" => ["label" => "Department Change", "hook" => "TicketDepartmentChange"], "priority_change" => ["label" => "Priority Change", "hook" => "TicketPriorityChange"], "status_change" => ["label" => "Status Change", "hook" => "TicketStatusChange"], "assigned" => ["label" => "Ticket Assigned", "hook" => "TicketFlagged"], "closed" => ["label" => "Ticket Closed", "hook" => "TicketClose"]];
    }

    public function getConditions()
    {
        return ["subject" => ["FriendlyName" => "Subject", "Type" => "text"], "department" => ["FriendlyName" => "Department", "Type" => "dropdown", "Options" => function () {
            return \WHMCS\Database\Capsule::table("tblticketdepartments")->orderBy("order")->pluck("name", "id")->all();
        }, "GetDisplayValue" => function ($value) {
            return \WHMCS\Database\Capsule::table("tblticketdepartments")->where("id", $value)->first()->name;
        }], "priority" => ["FriendlyName" => "Priority", "Type" => "dropdown", "Options" => ["Low" => "Low", "Medium" => "Medium", "High" => "High"]], "status" => ["FriendlyName" => "Status", "Type" => "dropdown", "Options" => function () {
            return \WHMCS\Database\Capsule::table("tblticketstatuses")->orderBy("sortorder")->pluck("title", "title")->all();
        }], "client_group" => ["FriendlyName" => "Client Group", "Type" => "dropdown", "Options" => function () {
            return \WHMCS\Database\Capsule::table("tblclientgroups")->orderBy("groupname")->pluck("groupname", "id")->all();
        }, "GetDisplayValue" => function ($value) {
            return \WHMCS\Database\Capsule::table("tblclientgroups")->where("id", $value)->first()->groupname;
        }]];
    }

    public function evaluateConditions($event, $conditions, $hookParameters)
    {
        $ticketId = isset($hookParameters["ticketid"]) ? $hookParameters["ticketid"] : "";
        if ($conditions["subject_filter"] && $conditions["subject"]) {
            $subject = isset($hookParameters["subject"]) ? $hookParameters["subject"] : \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first()->title;
            if ($conditions["subject_filter"] == "exact") {
                if ($conditions["subject"] != $subject) {
                    return false;
                }
            } else {
                if (strpos($subject, $conditions["subject"]) === false) {
                    return false;
                }
            }
        }
        if ($conditions["department"]) {
            $departmentId = isset($hookParameters["deptid"]) ? $hookParameters["deptid"] : \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first()->did;
            if ($conditions["department"] != $departmentId) {
                return false;
            }
        }
        if ($conditions["priority"]) {
            $priority = isset($hookParameters["priority"]) ? $hookParameters["priority"] : \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first()->urgency;
            if ($conditions["priority"] != $priority) {
                return false;
            }
        }
        if ($conditions["status"]) {
            $status = isset($hookParameters["status"]) ? $hookParameters["status"] : \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first()->status;
            if ($conditions["status"] != $status) {
                return false;
            }
        }
        if ($conditions["client_group"]) {
            $userId = isset($hookParameters["userid"]) ? $hookParameters["userid"] : \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first()->userid;
            $clientGroup = 0 < $userId ? \WHMCS\User\Client::find($userId)->groupId : "";
            if ($conditions["client_group"] != $clientGroup) {
                return false;
            }
        }
        return true;
    }

    public function buildNotification($event, $hookParameters)
    {
        $ticketId = isset($hookParameters["ticketid"]) ? $hookParameters["ticketid"] : "";
        $ticket = NULL;
        if (!isset($hookParameters["ticketmask"]) || !isset($hookParameters["subject"]) || !isset($hookParameters["deptid"]) || !isset($hookParameters["deptname"]) || !isset($hookParameters["priority"]) || !isset($hookParameters["status"]) || !isset($hookParameters["userid"])) {
            $ticket = \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first();
        }
        $ticketMask = isset($hookParameters["ticketmask"]) ? $hookParameters["ticketmask"] : $ticket->tid;
        $subject = isset($hookParameters["subject"]) ? $hookParameters["subject"] : $ticket->title;
        $departmentId = isset($hookParameters["deptid"]) ? $hookParameters["deptid"] : $ticket->did;
        $department = isset($hookParameters["deptname"]) ? $hookParameters["deptname"] : \WHMCS\Database\Capsule::table("tblticketdepartments")->where("id", $ticket->did)->first()->name;
        $priority = isset($hookParameters["priority"]) ? $hookParameters["priority"] : $ticket->urgency;
        $status = isset($hookParameters["status"]) ? $hookParameters["status"] : $ticket->status;
        $userId = isset($hookParameters["userid"]) ? $hookParameters["userid"] : $ticket->userid;
        $client = $submitterName = $submitterEmail = NULL;
        if ($userId) {
            $client = \WHMCS\User\Client::find($userId);
        } else {
            $ticket = \WHMCS\Database\Capsule::table("tbltickets")->where("id", $ticketId)->first();
            $submitterName = $ticket->name;
            $submitterEmail = $ticket->email;
        }
        $title = "#" . $ticketMask . " - " . $subject;
        $url = \App::getSystemUrl() . \App::get_admin_folder_name() . "/supporttickets.php?action=view&id=" . $ticketId;
        $message = \AdminLang::trans("notifications.ticket." . $event);
        $statusStyle = "";
        if ($status == "Open") {
            $statusStyle = "success";
        }
        return (new \WHMCS\Notification\Notification())->setTitle($title)->setMessage($message)->setUrl($url)->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("support.department"))->setValue($department))->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel($userId ? \AdminLang::trans("fields.client") : \AdminLang::trans("fields.guest"))->setValue($userId ? $client->firstName . " " . $client->lastName : $submitterName)->setUrl($userId ? \App::getSystemUrl() . \App::get_admin_folder_name() . "/clientssummary.php?userid=" . $userId : "mailto:" . $submitterEmail))->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("support.priority"))->setValue($priority)->setIcon(\App::getSystemUrl() . \App::get_admin_folder_name() . "/images/" . strtolower($priority) . "priority.gif"))->addAttribute((new \WHMCS\Notification\NotificationAttribute())->setLabel(\AdminLang::trans("fields.status"))->setValue($status)->setStyle($statusStyle));
    }
}
