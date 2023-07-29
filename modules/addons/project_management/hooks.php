<?php

use WHMCS\Application\Support\Facades\Lang;
use WHMCS\Application\Support\Facades\Menu;

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
add_hook("ClientAreaPrimaryNavbar", -1, function ($primaryNavbar) {
    $client = Menu::context("client");
    if (is_null($client)) {
        return false;
    }
    $clientAccessEnabled = WHMCS\Database\Capsule::table("tbladdonmodules")->where("module", "=", "project_management")->where("setting", "=", "clientenable")->first(["value"]);
    if (!(bool) $clientAccessEnabled->value) {
        return false;
    }
    $primaryNavbar->addChild("pm-addon-overview", ["label" => Lang::trans("clientareaprojects"), "uri" => "index.php?m=project_management", "order" => "65"]);
});
add_hook("ClientAreaHomepagePanels", -1, function (WHMCS\View\Menu\Item $homePagePanels) {
    $clientAccessEnabled = WHMCS\Database\Capsule::table("tbladdonmodules")->where("module", "=", "project_management")->where("setting", "=", "clientenable")->first(["value"]);
    if (!(bool) $clientAccessEnabled->value) {
        return false;
    }
    $client = Menu::context("client");
    $projects = [];
    $completedStatuses = get_query_val("tbladdonmodules", "value", ["module" => "project_management", "setting" => "completedstatuses"]);
    $result = select_query("mod_project", "", "userid=" . (int) $client->id . " AND status NOT IN (" . db_build_in_array(explode(",", $completedStatuses)) . ")", "lastmodified", "DESC");
    while ($data = mysql_fetch_array($result)) {
        $projects[] = ["id" => $data["id"], "title" => $data["title"], "lastmodified" => fromMySQLDate($data["lastmodified"], 1, 1), "status" => $data["status"]];
    }
    if (count($projects) == 0) {
        return NULL;
    }
    $projectPanel = $homePagePanels->addChild("pm-addon", ["name" => "Project Management Addon Active Projects", "label" => Lang::trans("projectManagement.activeProjects"), "icon" => "fas fa-calendar-alt", "order" => "225", "extras" => ["color" => "silver", "btn-link" => "index.php?m=project_management", "btn-text" => Lang::trans("manage"), "btn-icon" => "fas fa-arrow-right"]]);
    foreach ($projects as $i => $project) {
        $projectPanel->addChild("pm-addon-" . $project["id"], ["label" => $project["title"] . " <span class=\"label status-" . strtolower(str_replace(" ", "", $project["status"])) . "\">" . $project["status"] . "</span><br />" . "<small>" . Lang::trans("supportticketsticketlastupdated") . ": " . $project["lastmodified"] . "</small>", "uri" => "index.php?m=project_management&a=view&id=" . $project["id"], "order" => ($i + 1) * 10]);
    }
});
add_hook("AdminAreaClientSummaryActionLinks", 1, "hook_project_management_csoactions");
add_hook("AdminAreaPage", 1, function ($vars) {
    $jQueryCode = $vars["jquerycode"];
    if ($vars["filename"] == "supporttickets") {
        $action = App::get_req_var("action");
        $ticketId = (int) App::get_req_var("id");
        if (($action == "viewticket" || $action == "view") && $ticketId) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . "addons" . DIRECTORY_SEPARATOR . "project_management" . DIRECTORY_SEPARATOR . "project_management.php";
            $jQueryCode .= "jQuery('div.tab-content.admin-tabs').append(\n    '<div class=\"tab-pane\" id=\"tabProjects\"><i class=\"fa fa-spinner fa-spin\" id=\"projectsSpinner\"></i> Loading...</div>'\n);\njQuery('ul.nav.nav-tabs.admin-tabs').append(\n    '<li><a href=\"#tabProjects\" role=\"tab\" data-toggle=\"tab\" onclick=\"getProjectsTab()\">Projects</a></li>'\n);";
            if (project_management_checkperm("Create New Projects")) {
                $jQueryCode .= "jQuery('ul.nav.nav-tabs.admin-tabs').append(\n    '<li>'\n        + '<a id=\"createProject\" href=\"#\" onclick=\"createnewproject();return false;\" class=\"create\">'\n        + 'Create New Project</a></li>'\n);";
            }
        }
    }
    return ["jquerycode" => $jQueryCode];
});
add_hook("AdminAreaViewTicketPage", 1, "hook_project_management_adminticketinfo");
add_hook("AdminHomeWidgets", 1, function () {
    return new WHMCS\Module\Addon\ProjectManagement\Widget();
});
add_hook("CalendarEvents", "0", "hook_project_management_calendar");
add_hook("CalendarEvents", "0", "hook_project_management_calendar_tasks");
add_hook("IntelligentSearch", 0, function ($vars) {
    $searchResults = [];
    $searchTerm = $vars["searchTerm"];
    $query = WHMCS\Database\Capsule::table("mod_project");
    if (is_numeric($searchTerm)) {
        $query->where("ticketids", "like", "%" . (int) $searchTerm . "%")->orWhere("title", "like", "%" . $searchTerm . "%")->orWhere("userid", "=", (int) $searchTerm);
    } else {
        if ($searchTerm) {
            $query->orWhere("title", "like", "%" . $searchTerm . "%");
            $query->leftJoin("tblclients", "tblclients.id", "=", "mod_project.userid");
            $query->orWhere(function ($where) use($searchTerm) {
                $firstName = WHMCS\Database\Capsule::applyCollationIfCompatible("tblclients.firstname");
                $lastName = WHMCS\Database\Capsule::applyCollationIfCompatible("tblclients.lastname");
                $where->where(WHMCS\Database\Capsule::raw("CONCAT(" . $firstName . ",' '," . $lastName . ")"), "like", "%" . $searchTerm . "%")->orWhere(WHMCS\Database\Capsule::applyCollationIfCompatible("tblclients.email"), "like", "%" . $searchTerm . "%");
            });
        }
    }
    foreach ($query->get(["mod_project.*"]) as $project) {
        $searchResults[] = "<a href=\"addonmodules.php?module=project_management&m=view&projectid=" . $project->id . "\">\n    <strong>" . $project->title . "</strong> Project #" . $project->id . "\n</a>";
    }
    return $searchResults;
});
function hook_project_management_csoactions($vars)
{
    return ["<a href=\"addonmodules.php?module=project_management&view=user&userid=" . $_REQUEST["userid"] . "\"><img src=\"images/icons/invoices.png\" border=\"0\" align=\"absmiddle\" /> View Projects</a>"];
}

function hook_project_management_adminticketinfo($vars)
{
    global $aInt;
    global $jscode;
    global $jquerycode;
    $ticketid = $vars["ticketid"];
    $ticketdata = get_query_vals("tbltickets", "userid,title,tid", ["id" => $ticketid]);
    $tid = $ticketdata["tid"];
    $userid = $ticketdata["userid"];
    $clientData = $userid ? WHMCS\User\Client::find($userid) : NULL;
    require ROOTDIR . "/modules/addons/project_management/project_management.php";
    $projectCount = WHMCS\Database\Capsule::table("mod_project")->where("ticketids", "like", $tid)->count();
    $defaultSelectizeOption = "<option value=\"0\">" . AdminLang::trans("global.none") . "</option>";
    if ($clientData) {
        $defaultSelectizeOption .= "<option value=\"" . $clientData->id . "\" selected=\"selected\">" . $clientData->fullName . "</option>";
    }
    $code = "\n<link href=\"../modules/addons/project_management/css/style.css\" rel=\"stylesheet\" type=\"text/css\" />\n<script>\n\$(document).on(\"keyup\",\"#cpclientname\",function () {\n    var ticketuseridsearchlength = \$(\"#cpclientname\").val().length;\n    if (ticketuseridsearchlength>2) {\n    WHMCS.http.jqClient.post(\"search.php\", { ticketclientsearch: 1, value: \$(\"#cpclientname\").val(), token: \"" . generate_token("plain") . "\" },\n        function(data){\n            if (data) {\n                \$(\"#cpticketclientsearchresults\").html(data.replace(\"searchselectclient(\",\"projectsearchselectclient(\"));\n                \$(\"#cpticketclientsearchresults\").slideDown(\"slow\");\n                \$(\"#cpclientsearchcancel\").fadeIn();\n            }\n        });\n    }\n});\nfunction projectsearchselectclient(userid,name,email) {\n    \$(\"#cpclientname\").val(name);\n    \$(\"#cpuserid\").val(userid);\n    \$(\"#cpclientsearchcancel\").fadeOut();\n    \$(\"#cpticketclientsearchresults\").slideUp(\"slow\");\n}\n\nfunction createnewproject() {\n    \$(\"#popupcreatenew\").show();\n    \$(\"#popupstarttimer\").hide();\n    \$(\"#popupendtimer\").hide();\n    \$(\"#createnewcont\").slideDown();\n}\nfunction createproject() {\n    inputs = \$(\"#ajaxcreateprojectform\").serializeArray();\n    WHMCS.http.jqClient.post(\"addonmodules.php?module=project_management&createproj=1&ajax=1\",\n        {\n            input : inputs,\n            token: \"" . generate_token("plain") . "\"\n        },\n        function (data) {\n            if(data == \"0\"){\n                alert(\"You do not have permission to create project\");\n            } else {\n                var limitNum = 10;\n                \$(\"#createnewcont\").slideUp();\n                jQuery(\".nav-tabs.admin-tabs a[href='#tabProjects']\").tab(\"show\");\n                getProjects(Math.floor((" . $projectCount . " + 1) / limitNum) * limitNum);\n            }\n        });\n}\n\nfunction projectstarttimer(projectId) {\n    var ticketId = \"" . $tid . "\";\n    \$(\"#popupcreatenew\").hide();\n    \$(\"#popupendtimer\").hide();\n    WHMCS.http.jqClient.post(\n        \"addonmodules.php\",\n        {\n            module: \"project_management\",\n            m: \"view\",\n            a: \"projectstarttimer\",\n            pid: projectId,\n            tid: ticketId,\n            token: csrfToken\n        },\n        function (data) {\n            \$(\"#popupstarttimer\").html(data).show();\n            \$(\"#createnewcont\").slideDown();\n        }\n    );\n}\n\nfunction projectendtimer(projectId) {\n    var ticketId = \"" . $tid . "\";\n    \$(\"#popupcreatenew\").hide();\n    \$(\"#popupstarttimer\").hide();\n    WHMCS.http.jqClient.post(\n        \"addonmodules.php\",\n        {\n            module: \"project_management\",\n            m: \"view\",\n            a: \"projectendtimer\",\n            tid: ticketId,\n            pid: projectId,\n            token: csrfToken\n        },\n        function (data) {\n            \$(\"#popupendtimer\").html(data).show();\n            \$(\"#createnewcont\").slideDown();\n        }\n    );\n}\n\nfunction projectstarttimersubmit() {\n    WHMCS.http.jqClient.post(\"addonmodules.php?module=project_management&m=view\", \"a=hookstarttimer&\"+\$(\"#ajaxstarttimerform\").serialize(),\n        function (data) {\n            if(data == \"0\"){\n                jQuery.growl.error({title: \"\", message: \"Could not start timer.\"});\n            } else {\n                \$(\"#createnewcont\").slideUp();\n                var projid = \$(\"#ajaxstarttimerformprojectid\").val();\n                \$(\"#projecttimercontrol\"+projid).html(\"<a href=\\\"#\\\" onclick=\\\"projectendtimer('\"+projid+\"');return false\\\"><img src=\\\"../modules/addons/project_management/images/notimes.png\\\" align=\\\"absmiddle\\\" border=\\\"0\\\" /> Stop Tracking Time</a>\");\n                \$(\"#activetimers\").html(data);\n            }\n        });\n}\nfunction projectendtimersubmit(projectid,timerid) {\n    WHMCS.http.jqClient.post(\"addonmodules.php?module=project_management&m=view\",\n        {\n            a: \"hookendtimer\",\n            timerid: timerid,\n            ticketnum: \"" . $tid . "\",\n            token: \"" . generate_token("plain") . "\"\n        },\n        function (data) {\n            if (data == \"0\") {\n                jQuery.growl.error({title: \"\", message: \"Could not stop timer.\"});\n            } else {\n                \$(\"#createnewcont\").slideUp();\n                \$(\"#projecttimercontrol\"+projectid).html(\"<a href=\\\"#\\\" onclick=\\\"projectstarttimer('\"+projectid+\"');return false\\\"><img src=\\\"../modules/addons/project_management/images/starttimer.png\\\" align=\\\"absmiddle\\\" border=\\\"0\\\" /> Start Tracking Time</a>\");\n                \$(\"#activetimers\").html(data);\n            }\n        }\n    );\n}\n\nfunction projectpopupcancel() {\n    \$(\"#createnewcont\").slideUp();\n}\n\nfunction getProjectsTab() {\n    if (jQuery(\"#projectsSpinner\").length != 0) {\n        getProjects();\n    }\n}\n\nfunction getProjects(offset = 0) {\n    var ticketId = \"" . $tid . "\";\n    WHMCS.http.jqClient.post(\n        \"addonmodules.php\",\n        {\n            module: \"project_management\",\n            m: \"view\",\n            a: \"getprojectsbyid\",\n            tid: ticketId,\n            offset: offset,\n            token: csrfToken\n        },\n        function (data) {\n            jQuery(\"#tabProjects\").html(data);\n        }\n    );\n}\n\n</script>\n\n<div class=\"projectmanagement\">\n\n<div id=\"createnewcont\" style=\"display:none;\">\n\n<div class=\"createnewcont2\">\n\n<div class=\"createnewproject\" id=\"popupcreatenew\" style=\"display:none\">\n    <div class=\"title\">Create New Project</div>\n    <form id=\"ajaxcreateprojectform\">\n        <div class=\"row\">\n            <div class=\"col-sm-8 leftCol\">\n                <div class=\"form-group\">\n                    <label for=\"inputTitle\">Title</label>\n                    <input type=\"text\" name=\"title\" id=\"inputTitle\" class=\"form-control\" placeholder=\"Title\" />\n                </div>\n            </div>\n            <div class=\"col-sm-4 rightCol\">\n                <div class=\"form-group\">\n                    <label for=\"inputTicketNumber\">Ticket #</label>\n                    <input type=\"text\" name=\"ticketnum\" id=\"inputTicketNumber\" class=\"form-control\" value=\"" . get_query_val("tbltickets", "tid", ["id" => $vars["ticketid"]]) . "\" />\n                </div>\n            </div>\n        </div>\n        <div class=\"row\">\n            <div class=\"col-sm-6 leftCol\">\n                <div class=\"form-group\">\n                    <label for=\"inputAssignedTo\">Assigned To</label>\n                    <select class=\"form-control\" name=\"adminid\" id=\"inputAssignedTo\">";
    $adminid = (int) WHMCS\Session::get("adminid");
    $code .= "<option value=\"0\">None</option>";
    $result = select_query("tbladmins", "id,firstname,lastname", ["disabled" => "0"], "firstname` ASC,`lastname", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $aid = $data["id"];
        $adminfirstname = $data["firstname"];
        $adminlastname = $data["lastname"];
        $selected = "";
        if ($aid == $adminid) {
            $selected = " selected=\"selected\"";
        }
        $code .= "<option value=\"" . $aid . "\"" . $selected . ">" . $adminfirstname . " " . $adminlastname . "</option>";
    }
    $clientname = $clientData ? $clientData->fullName : "";
    $code .= "\n                    </select>\n                </div>\n            </div>\n            <div class=\"col-sm-6 rightCol\">\n                <div class=\"form-group\">\n                    <label for=\"cpclientname\">Associated Client</label>\n                    <select name=\"userid\"\n                            class=\"selectize selectize-client-search\"\n                            data-value-field=\"id\"\n                            data-allow-empty-option=\"1\"\n                            placeholder=\"Associated Client\"\n                            data-active-label=\"" . AdminLang::trans("status.active") . "\"\n                            data-inactive-label=\"" . AdminLang::trans("status.inactive") . "\"\n                    >\n                        " . $defaultSelectizeOption . "\n                    </select>\n                </div>\n            </div>\n        </div>\n        <div class=\"row\">\n            <div class=\"col-sm-6 leftCol\">\n                <label for=\"inputCreatedDate\">Created</label>\n                <div class=\"form-group date-picker-prepend-icon\">\n                    <label for=\"inputCreatedDate\" class=\"field-icon\">\n                        <i class=\"fal fa-calendar-alt\"></i>\n                    </label>\n                    <input id=\"inputCreatedDate\"\n                           type=\"text\"\n                           name=\"created\"\n                           value=\"" . getTodaysDate() . "\"\n                           class=\"form-control date-picker-single\"\n                    />\n                </div>\n            </div>\n            <div class=\"col-sm-6 rightCol\">\n                <label for=\"inputDueDate\">Due Date</label>\n                <div class=\"form-group date-picker-prepend-icon\">\n                    <label for=\"inputDueDate\" class=\"field-icon\">\n                        <i class=\"fal fa-calendar-alt\"></i>\n                    </label>\n                    <input id=\"inputDueDate\"\n                           type=\"text\"\n                           name=\"duedate\"\n                           value=\"" . getTodaysDate() . "\"\n                           class=\"form-control date-picker-single future\"\n                    />\n                </div>\n            </div>\n        </div>\n        <div class=\"text-center\">\n            <input id=\"btnCreateProject\" type=\"button\" value=\"Create\" class=\"btn btn-success\" onclick=\"createproject()\" />\n            <input type=\"button\" value=\"Cancel\" class=\"btn btn-default\" onclick=\"projectpopupcancel();return false\" />\n        </div>\n    </form>\n</div>\n\n<div class=\"createnewproject\" id=\"popupstarttimer\" style=\"display:none\"></div>\n</div>\n\n<div class=\"createnewproject\" id=\"popupendtimer\" style=\"display:none\"></div>\n\n</div>\n\n</div>\n\n";
    return $code;
}

function project_management_hook_daysleft($duedate)
{
    if ($duedate == "0000-00-00") {
        return "N/A";
    }
    $datetime = strtotime("now");
    $date2 = strtotime($duedate);
    $days = ceil(($date2 - $datetime) / 86400);
    if ($days == "-0") {
        $days = 0;
    }
    $dueincolor = $days < 2 ? "cc0000" : "73BC10";
    if (0 <= $days) {
        return "<span style=\"color:#" . $dueincolor . "\">Due In " . $days . " Days</span>";
    }
    return "<span style=\"color:#" . $dueincolor . "\">Due " . $days * -1 . " Days Ago</span>";
}

function hook_project_management_calendar($vars)
{
    $events = [];
    if ($vars["start"] == 0 || !$vars["start"]) {
        $vars["start"] = date("Y");
    }
    if ($vars["end"] == 0 || !$vars["end"]) {
        $vars["end"] = date("Y");
    }
    $queryStart = mktime("0", "0", "0", "1", "1", $vars["start"]);
    $queryEnd = mktime("23", "59", "59", "12", "31", $vars["end"]);
    $result = select_query("mod_project", "", "duedate BETWEEN '" . date("Y-m-d", $queryStart) . "' AND '" . date("Y-m-d", $queryEnd) . "'");
    while ($data = mysql_fetch_assoc($result)) {
        $projecttitle = "Project Due: " . $data["title"] . "\nStatus: " . $data["status"];
        if ($data["adminid"]) {
            $projecttitle .= " (" . getAdminName($data["adminid"]) . ")";
        }
        $events[] = ["id" => "prj" . $data["id"], "title" => $projecttitle, "start" => $data["duedate"], "allDay" => true, "url" => "addonmodules.php?module=project_management&m=view&projectid=" . $data["id"]];
    }
    return $events;
}

function hook_project_management_calendar_tasks($vars)
{
    $events = [];
    if ($vars["start"] == 0 || !$vars["start"]) {
        $vars["start"] = date("Y");
    }
    if ($vars["end"] == 0 || !$vars["end"]) {
        $vars["end"] = date("Y");
    }
    $queryStart = mktime("0", "0", "0", "1", "1", $vars["start"]);
    $queryEnd = mktime("23", "59", "59", "12", "31", $vars["end"]);
    $result = select_query("mod_projecttasks", "mod_projecttasks.*,(SELECT title FROM mod_project WHERE mod_project.id=mod_projecttasks.projectid) AS projecttitle", "duedate BETWEEN '" . date("Y-m-d", $queryStart) . "' AND '" . date("Y-m-d", $queryEnd) . "'");
    while ($data = mysql_fetch_assoc($result)) {
        $projecttitle = "Task Due: " . $data["task"] . "\n" . "Project: " . $data["projecttitle"] . "\nStatus: " . ($data["completed"] ? "Completed" : "Pending");
        if ($data["adminid"]) {
            $projecttitle .= " (" . getAdminName($data["adminid"]) . ")";
        }
        $events[] = ["id" => "prj" . $data["projectid"], "title" => $projecttitle, "start" => $data["duedate"], "allDay" => true, "url" => "addonmodules.php?module=project_management&m=view&projectid=" . $data["projectid"]];
    }
    return $events;
}
