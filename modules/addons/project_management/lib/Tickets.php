<?php

namespace WHMCS\Module\Addon\ProjectManagement;

class Tickets extends BaseProjectEntity
{
    protected $statusColours = [];

    protected function getTicketByMask($ticketMask)
    {
        $data = \WHMCS\Database\Capsule::table("tbltickets")->leftJoin("tblclients", "tblclients.id", "=", "tbltickets.userid")->leftJoin("tblcontacts", "tblcontacts.id", "=", "tbltickets.contactid")->leftJoin("tblticketdepartments", "tblticketdepartments.id", "=", "tbltickets.did")->where("tid", "=", $ticketMask)->first(["tbltickets.id", "tbltickets.tid", "tblticketdepartments.name as departmentName", "tbltickets.date", "tbltickets.title", "tbltickets.status", "tbltickets.lastreply", "tbltickets.admin", "tbltickets.name", "tbltickets.userid", \WHMCS\Database\Capsule::raw("CONCAT_WS(' ', tblclients.firstname, tblclients.lastname) as client"), \WHMCS\Database\Capsule::raw("CONCAT_WS(' ', tblcontacts.firstname, tblcontacts.lastname) as contact"), \WHMCS\Database\Capsule::raw("tblclients.id as clientid")]);
        if (!$data) {
            throw new Exception("Ticket ID Not Found");
        }
        $lastReplyData = \WHMCS\Database\Capsule::table("tblticketreplies")->leftJoin("tblclients", "tblclients.id", "=", "tblticketreplies.userid")->leftJoin("tblcontacts", "tblcontacts.id", "=", "tblticketreplies.contactid")->where("tid", "=", $data->id)->orderBy("tblticketreplies.id", "desc")->limit(1)->first(["tblticketreplies.admin", \WHMCS\Database\Capsule::raw("CONCAT_WS(' ', tblclients.firstname, tblclients.lastname) as client"), \WHMCS\Database\Capsule::raw("CONCAT_WS(' ', tblcontacts.firstname, tblcontacts.lastname) as contact"), "tblticketreplies.name"]);
        $data->isAdminReply = false;
        if ($lastReplyData) {
            $data->lastReplyUser = $lastReplyData->admin ?: $lastReplyData->contact ?: $lastReplyData->client ?: $lastReplyData->name;
            if ($lastReplyData->admin) {
                $data->isAdminReply = true;
            }
        } else {
            $data->lastReplyUser = $data->admin ?: $data->contact ?: $data->client ?: $data->name;
            if ($data->admin) {
                $data->isAdminReply = true;
            }
        }
        $data->lastreply = fromMySQLDate($data->lastreply, true);
        $data->statusColour = $this->getStatusColour($data->status);
        $data->statusTextColour = $this->getContrastYIQ(substr($data->statusColour, 1));
        if ($data->contact) {
            $data->userDetails = $data->contact . " (" . Helper::getClientLink($data->userid) . ")";
        } else {
            if ($data->client) {
                $data->userDetails = Helper::getClientLink($data->userid);
            } else {
                $data->userDetails = $data->name;
            }
        }
        return $data;
    }

    public function get()
    {
        $tickets = [];
        foreach ($this->project->ticketids as $key => $ticketMask) {
            try {
                $tickets[] = $this->getTicketByMask($ticketMask);
            } catch (Exception $e) {
                unset($this->project->ticketids[$key]);
                $this->project->save();
            }
        }
        return $tickets;
    }

    public function associate($ticketMask = NULL)
    {
        if (!$this->project->permissions()->check("Associate Tickets")) {
            throw new Exception("You don't have permission to associate tickets.");
        }
        $ticketMask = $ticketMask ?: trim(\App::getFromRequest("ticketmask"));
        if (!$ticketMask) {
            throw new Exception("Ticket Mask is required");
        }
        $ticketData = $this->getTicketByMask($ticketMask);
        if (in_array($ticketMask, $this->project->ticketids)) {
            throw new Exception("This ticket is already associated with this project");
        }
        $currentTicketList = $this->ticketLinks($this->project->ticketids);
        $this->project->ticketids[] = $ticketMask;
        $this->project->save();
        $newTicketList = $this->ticketLinks($this->project->ticketids);
        $projectChanges = [["field" => "Ticket Associated", "oldValue" => implode(", ", $currentTicketList), "newValue" => implode(", ", $newTicketList)]];
        $this->project->notify()->staff($projectChanges);
        $this->project->log()->add("Support Ticket Associated: #" . $ticketMask);
        return ["ticket" => $ticketData];
    }

    public function getDepartments()
    {
        return \WHMCS\Database\Capsule::table("tblticketdepartments")->pluck("name", "id")->toArray();
    }

    public function parseMarkdown()
    {
        $markup = new \WHMCS\View\Markup\Markup();
        $content = \App::get_req_var("content");
        return ["body" => "<div class=\"markdown-content\">" . $markup->transform($content, "markdown") . "</div>"];
    }

    public function open()
    {
        if (!$this->project->permissions()->check("Associate Tickets")) {
            throw new Exception("You don't have permission to associate tickets.");
        }
        $userId = $this->project->userid;
        $contactId = \App::getFromRequest("contact");
        $name = !$userId ? \App::getFromRequest("name") : "";
        $email = !$userId ? \App::getFromRequest("email") : "";
        $subject = \App::getFromRequest("subject");
        $departmentId = \App::getFromRequest("department");
        $priority = \App::getFromRequest("priority");
        $message = \App::getFromRequest("message");
        $ticketDetails = localAPI("openticket", ["clientid" => $userId, "contactid" => $contactId, "name" => $name, "email" => $email, "deptid" => $departmentId, "subject" => $subject, "message" => $message, "priority" => $priority, "admin" => true]);
        if ($ticketDetails["result"] != "success") {
            throw new Exception($ticketDetails["message"]);
        }
        $this->project->ticketids[] = $ticketDetails["tid"];
        $this->project->save();
        $this->project->log()->add("Support Ticket Created: #" . $ticketDetails["tid"]);
        return ["ticket" => $this->getTicketByMask($ticketDetails["tid"]), "ticketCount" => count($this->project->ticketids)];
    }

    public function search()
    {
        $searchTerm = \App::getFromRequest("search");
        $tickets = [];
        try {
            $tickets[] = $this->getTicketByMask($searchTerm);
        } catch (\Exception $e) {
            $tickets = \WHMCS\Database\Capsule::table("tbltickets")->where("title", "like", "%" . $searchTerm . "%")->leftJoin("tblclients", "tblclients.id", "=", "tbltickets.userid");
            if ($this->project->ticketids) {
                $tickets = $tickets->whereNotIn("tid", $this->project->ticketids);
            }
            $tickets = $tickets->get(["tbltickets.id", "tbltickets.tid", "tbltickets.title", "tbltickets.status", "tbltickets.userid", \WHMCS\Database\Capsule::raw("CONCAT_WS(' ', tblclients.firstname, tblclients.lastname) as client"), \WHMCS\Database\Capsule::raw("tblclients.id as clientid")])->all();
            foreach ($tickets as $ticket) {
                $ticket->statusColour = $this->getStatusColour($ticket->status);
                $ticket->statusTextColour = $this->getContrastYIQ(substr($ticket->statusColour, 1));
            }
        }
        return ["tickets" => $tickets];
    }

    public function unlink()
    {
        $ticketMask = \App::getFromRequest("ticketmask");
        if (!$ticketMask) {
            throw new Exception("No Ticket Supplied");
        }
        if (!in_array($ticketMask, $this->project->ticketids)) {
            throw new Exception("Ticket not associated with Project");
        }
        $currentTicketList = $this->ticketLinks($this->project->ticketids);
        $ticketId = \WHMCS\Database\Capsule::table("tbltickets")->where("tid", "=", $ticketMask)->pluck("id")->all();
        $tickets = array_flip($this->project->ticketids);
        unset($tickets[$ticketMask]);
        $this->project->ticketids = array_flip($tickets);
        $this->project->save();
        $this->project->log()->add("Support Ticket Unlinked: #" . $ticketMask);
        $newTicketList = $this->ticketLinks($this->project->ticketids);
        $projectChanges = [["field" => "Ticket Association Removed", "oldValue" => implode(", ", $currentTicketList), "newValue" => implode(", ", $newTicketList)]];
        $this->project->notify()->staff($projectChanges);
        return ["ticketId" => $ticketId, "ticketCount" => count($this->project->ticketids)];
    }

    protected function getStatusColour($status)
    {
        if (!$this->statusColours) {
            $this->statusColours = \WHMCS\Database\Capsule::table("tblticketstatuses")->pluck("color", "title")->toArray();
        }
        return $this->statusColours[$status] ?: "#F0AD4E";
    }

    protected function getContrastYIQ($hexColour)
    {
        $r = hexdec(substr($hexColour, 0, 2));
        $g = hexdec(substr($hexColour, 2, 2));
        $b = hexdec(substr($hexColour, 4, 2));
        $yiq = ($r * 299 + $g * 587 + $b * 114) / 1000;
        return 128 <= $yiq ? "black" : "white";
    }

    public function ticketLinks($ticketIds)
    {
        $systemUrl = \App::getSystemURL();
        $adminFolder = \App::get_admin_folder_name();
        $ticketList = [];
        $tickets = \WHMCS\Database\Capsule::table("tbltickets")->whereIn("tid", $ticketIds)->get()->all();
        foreach ($tickets as $ticket) {
            $ticketLink = $systemUrl . $adminFolder . DIRECTORY_SEPARATOR . "supporttickets.php?action=viewticket&id=" . $ticket->id;
            $ticketList[] = "<a href=\"" . $ticketLink . "\">" . "#" . $ticket->tid . "</a>";
        }
        return $ticketList;
    }

    public function associateTicket()
    {
        $lang = $this->project->getLanguage();
        $projectId = \App::getFromRequest("projectid");
        $csrfToken = generate_token();
        $ticketTable = "";
        $noResults = "<td colspan=\"4\" class=\"text-center\">No tickets found</td>";
        $latestTickets = \WHMCS\Support\Ticket::orderBy("id", "DESC")->take(10)->get();
        if ($latestTickets) {
            foreach ($latestTickets as $ticket) {
                $badgeColor = $this->getStatusColour($ticket->status);
                $ticketTable .= "<tr>\n    <td>\n        <input type=\"checkbox\" name=\"ticket[" . $ticket->ticketNumber . "]\" value=\"1\" />\n    </td>\n    <td>\n        <a href=\"supporttickets.php?action=view&id=" . $ticket->id . "\" target=\"_blank\">\n            #" . $ticket->ticketNumber . " - " . $ticket->title . "\n        </a> <span class=\"badge\" style=\"background-color: " . $badgeColor . ";\">" . $ticket->status . "</span>\n    </td>\n    <td>\n        <a href=\"clientssummary.php?userid=" . $ticket->client->id . "\" target=\"_blank\">\n            " . $ticket->client->fullName . "\n        </a>\n    </td>\n</tr>";
            }
        } else {
            $ticketTable = "<tr>\n    " . $noResults . "\n</tr>";
        }
        $body = "<div class=\"input-group\">\n    <input type=\"text\" class=\"form-control\" id=\"associateTicketSearch\" name=\"search\" placeholder=\"" . $lang["placeholders"]["ticketNumberOrName"] . "\">\n      <span class=\"input-group-btn\">\n        <button class=\"btn btn-default\" id=\"btnSearchTickets\" type=\"button\">" . $lang["searchNoElipsis"] . "</button>\n      </span>\n</div>\n<form action=\"addonmodules.php?module=project_management&action=doAssociateTicket&projectid=" . $projectId . "&ajaxModal=1\" class=\"form table-pm\" id=\"frmAssociateTicket\">\n    " . $csrfToken . "\n    <input type=\"hidden\" name=\"projectId\" value=\"" . $projectId . "\" />\n    <div class=\"tablebg\">\n        <table id=\"associateTicketSelect\" class=\"datatable\" width=\"100%\">\n            <thead class=\"text-center\">\n                <th width=\"10%\">Select</th>\n                <th width=\"40%\">Ticket</th>\n                <th width=\"40%\">Client</th>\n            </thead>\n            <tbody id=\"tblAssociateRows\" class=\"text-center\">\n                " . $ticketTable . "\n            </tbody>\n        </table>\n    </div>\n</form>";
        $jsCode = "<script type=\"text/javascript\">\n    var tableContent = jQuery('#tblAssociateRows'),\n        originalTableContent = tableContent.html(),\n        search = jQuery('#associateTicketSearch'),\n        searchButton = jQuery('#btnSearchTickets'),\n        searchTerm = null;\n        characterCount = 0,\n        timeout = null;\n    \n    function updateTicketSearchTable(data)\n    {\n        var tableData = '';\n        if (data.tickets) {\n            jQuery.each(data.tickets, function(i, ticket) {\n                tableData += '<tr>';\n                tableData += '<td><input type=\"checkbox\" name=\"ticket[' + ticket.tid + ']\" value=\"1\" /></td>';\n                tableData += '<td>'\n                    + '<a href=\"supporttickets.php?action=view&id=' + ticket.id + '\" target=\"_blank\">'\n                    + '#' + ticket.tid + ' - ' + ticket.title\n                    + '</a> <span class=\"badge\" style=\"background-color: ' + ticket.statusColour + ';\">'\n                    + ticket.status\n                    + '</span>'\n                    + '</td>';\n                tableData += '<td>'\n                    + '<a href=\"clientssummary.php?userid=' + ticket.clientid + '\" target=\"_blank\">'\n                    + ticket.client\n                    + '</a>'\n                    + '</td>';\n                tableData += '</tr>';\n            });\n        } else if (data.reset) {\n            tableData = data.content;\n        } else if (typeof data === 'string') {\n            var error = '';\n            if (data === 'failed' || data === '') {\n                error = 'An error occurred: Could not complete search.';\n            } else {\n                error = 'An error occurred: ' + data;\n            }\n            \n            tableData = '<tr>'\n                + '<td colspan=\"4\">' + data + '</td>'\n                + '</tr>';\n        } else {\n            tableData = '<tr>" . $noResults . "</tr>';\n        }\n        \n        tableContent.html(tableData);\n    }\n    \n    searchButton.on('click', function(e) {\n        searchTerm = search.val();\n        characterCount = searchTerm.length;\n        if (characterCount >= 3) {\n            WHMCS.http.jqClient.post(\n                'addonmodules.php?module=project_management&action=searchTickets&projectid=" . $projectId . "&ajaxModal=1',\n                {\n                    token: csrfToken,\n                    search: searchTerm\n                },\n                function(data) {\n                    if (data.status == '0' && data.error) {\n                        updateTicketSearchTable(data.error);\n                        return;\n                    } else if (data.status == '0') {\n                        updateTicketSearchTable('failed');\n                        return;\n                    }\n                    \n                    updateTicketSearchTable(data);\n                    return;\n                }\n            )\n            .fail(function(xhr) {\n                updateTicketSearchTable('failed');\n            });\n        } else if (characterCount === 0) {\n            updateTicketSearchTable({\n                reset: true,\n                content: originalTableContent\n            });\n        }\n    });\n</script>";
        return ["body" => $body . $jsCode];
    }

    public function doAssociateTicket()
    {
        check_token("WHMCS.admin.default");
        $tickets = array_keys(\App::getFromRequest("ticket") ?: []);
        foreach ($tickets as $ticketId) {
            try {
                $this->associate($ticketId);
            } catch (\Exception $e) {
                $arrayKey = array_search($ticketId, $tickets);
                if ($arrayKey !== false) {
                    unset($tickets[$arrayKey]);
                }
            }
        }
        \WHMCS\Session::set("ticketsAssociatedSuccess", $tickets);
        return ["reloadPage" => true, "ticketsAssociated" => $tickets];
    }
}
