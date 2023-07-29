<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Support Center Overview");
$aInt->title = AdminLang::trans("support.supportoverview");
$aInt->sidebar = "support";
$aInt->icon = "support";
$aInt->helplink = "Support Center";
$aInt->requiredFiles(["ticketfunctions", "reportfunctions"]);
ob_start();
echo "\n<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "\">\n<div style=\"background-color:#f6f6f6;padding:5px 15px;\">" . AdminLang::trans("support.displayOverview") . "<select name=\"period\" class=\"form-control select-inline\" onchange=\"submit()\"><option value=\"Today\">" . AdminLang::trans("calendar.today") . "</option><option value=\"Yesterday\" " . ($period == "Yesterday" ? " selected" : "") . ">" . AdminLang::trans("calendar.yest") . "</option><option value=\"This Week\" " . ($period == "This Week" ? " selected" : "") . ">" . AdminLang::trans("calendar.thisWeek") . "</option><option value=\"This Month\" " . ($period == "This Month" ? " selected" : "") . ">" . AdminLang::trans("calendar.thisMonth") . "</option><option value=\"Last Month\" " . ($period == "Last Month" ? " selected" : "") . ">" . AdminLang::trans("calendar.lastMonth") . "</option></select></div>\n</form>\n\n<div style=\"border:2px solid #f6f6f6;border-top:0;\">";
$chart = new WHMCSChart();
$today = WHMCS\Carbon::today();
switch ($period) {
    case "Yesterday":
        $startDate = $today->copy()->subDay()->startOfDay()->toDateTimeString();
        $endDate = $today->copy()->subDay()->endOfDay()->toDateTimeString();
        break;
    case "This Week":
        $startDate = WHMCS\Carbon::parse("last monday")->startOfDay()->toDateTimeString();
        $endDate = WHMCS\Carbon::parse("next sunday")->endOfDay()->toDateTimeString();
        break;
    case "This Month":
        $startDate = $today->copy()->startOfMonth()->toDateTimeString();
        $endDate = $today->copy()->endOfMonth()->toDateTimeString();
        break;
    case "Last Month":
        $startDate = $today->copy()->subMonth()->startOfMonth()->toDateTimeString();
        $endDate = $today->copy()->subMonth()->endOfMonth()->toDateTimeString();
        break;
    default:
        $startDate = $today->copy()->startOfDay()->toDateTimeString();
        $endDate = $today->copy()->endOfDay()->toDateTimeString();
        $newtickets = WHMCS\Database\Capsule::table("tbltickets")->whereBetween("date", [$startDate, $endDate])->count();
        $clientreplies = WHMCS\Database\Capsule::table("tblticketreplies")->where("admin", "=", "")->whereBetween("date", [$startDate, $endDate])->count();
        $staffreplies = WHMCS\Database\Capsule::table("tblticketreplies")->where("admin", "!=", "")->whereBetween("date", [$startDate, $endDate])->count();
        $hours = [];
        $maxHour = !$period || $period == "Today" ? date("H") : 23;
        for ($hour = 0; $hour <= $maxHour; $hour++) {
            $hours[str_pad($hour, 2, 0, STR_PAD_LEFT)] = 0;
        }
        $replytimes = ["1" => "0", "2" => 0, "4" => "0", "8" => "0", "16" => "0", "24" => "0"];
        $avefirstresponse = "0";
        $avefirstresponsecount = "0";
        $opennoreply = "0";
        $result = WHMCS\Database\Capsule::table("tbltickets")->leftJoin("tblticketreplies", "tblticketreplies.tid", "=", "tbltickets.id")->whereBetween("tbltickets.date", [$startDate, $endDate])->orderBy("id")->groupBy("tblticketreplies.tid")->get(["tbltickets.id", "tbltickets.date", WHMCS\Database\Capsule::raw("MIN(tblticketreplies.date) as first_reply")])->all();
        foreach ($result as $data) {
            $ticketid = $data->id;
            $dateopened = $data->date;
            $datefirstreply = $data->first_reply;
            $datehour = substr($dateopened, 11, 2);
            $hours[$datehour]++;
            if (!$datefirstreply) {
                $opennoreply++;
            } else {
                $timetofirstreply = strtotime($datefirstreply) - strtotime($dateopened);
                $timetofirstreply = round($timetofirstreply / 3600, 2);
                $avefirstresponse += $timetofirstreply;
                $avefirstresponsecount++;
                if ($timetofirstreply <= 1) {
                    $replytimes[1]++;
                } else {
                    if (1 < $timetofirstreply && $timetofirstreply <= 4) {
                        $replytimes[2]++;
                    } else {
                        if (4 < $timetofirstreply && $timetofirstreply <= 8) {
                            $replytimes[4]++;
                        } else {
                            if (8 < $timetofirstreply && $timetofirstreply <= 16) {
                                $replytimes[8]++;
                            } else {
                                if (16 < $timetofirstreply && $timetofirstreply <= 24) {
                                    $replytimes[16]++;
                                } else {
                                    $replytimes[24]++;
                                }
                            }
                        }
                    }
                }
            }
        }
        $avefirstresponse = 0 < $avefirstresponsecount ? round($avefirstresponse / $avefirstresponsecount, 2) : "-";
        $avereplieschartdata = [];
        $avereplieschartdata["cols"][] = ["label" => AdminLang::trans("support.timeframe"), "type" => "string"];
        $avereplieschartdata["cols"][] = ["label" => AdminLang::trans("support.numberOfTickets"), "type" => "number"];
        if (0 < $replytimes[1]) {
            $avereplieschartdata["rows"][] = ["c" => [["v" => "0-1 Hours"], ["v" => $replytimes[1], "f" => $replytimes[1]]]];
        }
        if (0 < $replytimes[2]) {
            $avereplieschartdata["rows"][] = ["c" => [["v" => "1-4 Hours"], ["v" => $replytimes[2], "f" => $replytimes[2]]]];
        }
        if (0 < $replytimes[4]) {
            $avereplieschartdata["rows"][] = ["c" => [["v" => "4-8 Hours"], ["v" => $replytimes[4], "f" => $replytimes[2]]]];
        }
        if (0 < $replytimes[8]) {
            $avereplieschartdata["rows"][] = ["c" => [["v" => "8-16 Hours"], ["v" => $replytimes[8], "f" => $replytimes[8]]]];
        }
        if (0 < $replytimes[16]) {
            $avereplieschartdata["rows"][] = ["c" => [["v" => "16-24 Hours"], ["v" => $replytimes[16], "f" => $replytimes[16]]]];
        }
        if (0 < $replytimes[24]) {
            $avereplieschartdata["rows"][] = ["c" => [["v" => "24+ Hours"], ["v" => $replytimes[24], "f" => $replytimes[24]]]];
        }
        $averepliesargs = [];
        $averepliesargs["title"] = AdminLang::trans("support.averageFirstReplyTime");
        $averepliesargs["legendpos"] = "right";
        $hourschartdata = [];
        $hourschartdata["cols"][] = ["label" => AdminLang::trans("support.timeframe"), "type" => "string"];
        $hourschartdata["cols"][] = ["label" => AdminLang::trans("support.numberOfTickets"), "type" => "number"];
        foreach ($hours as $hour => $count) {
            $hourschartdata["rows"][] = ["c" => [["v" => $hour], ["v" => $count, "f" => $count]]];
        }
        $hoursargs = [];
        $hoursargs["title"] = AdminLang::trans("support.submitByHour");
        $hoursargs["xlabel"] = AdminLang::trans("support.ticketsSubmitted");
        $hoursargs["ylabel"] = AdminLang::trans("support.hour");
        $hoursargs["legendpos"] = "none";
        $ticketStatsArray = ["newTickets" => $newtickets, "clientReplies" => $clientreplies, "staffReplies" => $staffreplies, "withoutReply" => $opennoreply, "firstResponse" => is_numeric($avefirstresponse) ? $avefirstresponse . AdminLang::trans("fields.hours") : AdminLang::trans("global.na")];
        $ticketStatsOutput = function ($array) {
            $output = "";
            foreach ($array as $k => $v) {
                $title = AdminLang::trans("support." . $k);
                $output .= "<div class=\"ticketstatbox\">\n    " . $title . "\n    <div class=\"stat\">" . $v . "</div>\n</div>";
            }
            return $output;
        };
        echo "    <style>\n        .ticket-stats-wrapper {\n            display: flex;\n            flex-wrap: wrap;\n            justify-content: center;\n            align-items: center;\n        }\n        .ticketstatbox {\n            margin: 20px 10px 0;\n            width: 150px;\n            padding: 20px;\n            font-size: 14px;\n            text-align: center;\n            background-color: #FEFAEB;\n            -moz-border-radius: 10px;\n            -webkit-border-radius: 10px;\n            -o-border-radius: 10px;\n            border-radius: 10px;\n        }\n        .ticketstatbox .stat {\n            font-size: 24px;\n            color: #000066;\n        }\n    </style>\n    <div class=\"ticket-stats-wrapper\">\n        " . $ticketStatsOutput($ticketStatsArray) . "\n    </div>\n    <div class=\"row\">\n        <div class=\"col-xs-12 col-sm-5\">\n            " . $chart->drawChart("Pie", $avereplieschartdata, $averepliesargs, "500px", "100%") . "\n        </div>\n        <div class=\"col-xs-12 col-sm-7\">\n            " . $chart->drawChart("Bar", $hourschartdata, $hoursargs, "600px", "100%") . "\n        </div>\n    </div>\n</div>";
        $content = ob_get_contents();
        ob_end_clean();
        $aInt->content = $content;
        $aInt->display();
}
