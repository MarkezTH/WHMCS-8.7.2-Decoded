<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Automation Status");
$aInt->title = AdminLang::trans("utilities.automationStatus");
$aInt->sidebar = "utilities";
$aInt->icon = "clients";
$aInt->helplink = "Automation Status";
$date = App::getFromRequest("date");
$action = App::getFromRequest("action");
if ($date) {
    $date = WHMCS\Carbon::createFromFormat("Y-m-d", $date);
} else {
    $date = WHMCS\Carbon::today();
}
if ($date->isToday()) {
    $dateDisplayLabel = AdminLang::trans("calendar.today");
} else {
    if ($date->isYesterday()) {
        $dateDisplayLabel = AdminLang::trans("calendar.yest");
    } else {
        $format = "l jS";
        if ($date->format("m") != date("m")) {
            $format = "l jS F";
        }
        $dateDisplayLabel = $date->format($format);
    }
}
$tasks = ["CreateInvoices", "AddLateFees", "ProcessCreditCardPayments", "InvoiceReminders", "CancellationRequests", "AutoSuspensions", "AutoTerminations", "FixedTermTerminations", "DomainRenewalNotices", "DomainTransferSync", "DomainStatusSync", "CloseInactiveTickets", "AffiliateCommissions", "EmailMarketer", "AutoClientStatusSync", "DatabaseBackup", "CheckForWhmcsUpdate", "CurrencyUpdateExchangeRates", "CurrencyUpdateProductPricing", "UpdateServerUsage"];
$graphMetric = App::getFromRequest("metric");
$graphTaskKey = "utilities.automationStatusDetail.graph.";
$allowedGraphMetrics = ["CreateInvoices" => AdminLang::trans($graphTaskKey . "createInvoices"), "AddLateFees" => AdminLang::trans($graphTaskKey . "addLateFees"), "ProcessCreditCardPayments" => AdminLang::trans($graphTaskKey . "processCreditCardPayments"), "InvoiceReminders" => AdminLang::trans($graphTaskKey . "invoiceReminders"), "CancellationRequests" => AdminLang::trans($graphTaskKey . "cancellationRequests"), "AutoSuspensions" => AdminLang::trans($graphTaskKey . "autoSuspensions"), "AutoTerminations" => AdminLang::trans($graphTaskKey . "autoTerminations"), "DomainRenewalNotices" => AdminLang::trans($graphTaskKey . "domainRenewalNotices"), "CloseInactiveTickets" => AdminLang::trans($graphTaskKey . "closeInactiveTickets")];
if (!array_key_exists($graphMetric, $allowedGraphMetrics)) {
    $graphMetric = key($allowedGraphMetrics);
}
$graphPeriod = App::getFromRequest("period");
$allowedGraphPeriods = ["thisweek" => AdminLang::trans("calendar.thisWeek"), "lastweek" => AdminLang::trans("calendar.lastWeek"), "last30days" => AdminLang::trans("calendar.lastDays", [":days" => 30]), "thismonth" => AdminLang::trans("calendar.thisMonth"), "lastmonth" => AdminLang::trans("calendar.lastMonth")];
if (!array_key_exists($graphPeriod, $allowedGraphPeriods)) {
    $graphPeriod = key($allowedGraphPeriods);
}
ob_start();
echo "\n<div class=\"graph-filters\">\n\n    <div class=\"btn-group btn-group-sm graph-filter-metric\">\n        <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">\n            ";
echo AdminLang::trans("utilities.automationStatusDetail.viewing") . " " . $allowedGraphMetrics[$graphMetric];
echo " <span class=\"caret\"></span>\n        </button>\n        <ul class=\"dropdown-menu pull-right\">\n";
foreach ($allowedGraphMetrics as $metric => $displayName) {
    echo "            <li><a href=\"";
    echo $metric;
    echo "\"";
    if ($graphMetric == $metric) {
        echo " class=\"active\"";
    }
    echo ">";
    echo $displayName;
    echo "</a></li>\n";
}
echo "        </ul>\n    </div>\n\n    <div class=\"btn-group btn-group-sm graph-filter-period\">\n        <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">\n            ";
echo $allowedGraphPeriods[$graphPeriod];
echo " <span class=\"caret\"></span>\n        </button>\n        <ul class=\"dropdown-menu pull-right\">\n";
foreach ($allowedGraphPeriods as $period => $displayName) {
    echo "            <li><a href=\"";
    echo $period;
    echo "\"";
    if ($graphPeriod == $period) {
        echo " class=\"active\"";
    }
    echo ">";
    echo $displayName;
    echo "</a></li>\n";
}
echo "        </ul>\n    </div>\n\n</div>\n\n<div id=\"overviewChartContainer\">\n    <canvas id=\"overviewChart\" height=\"270\"></canvas>\n</div>\n\n";
if ($graphPeriod == "thisweek") {
    $startDate = WHMCS\Carbon::today()->subWeek();
    $endDate = WHMCS\Carbon::today();
} else {
    if ($graphPeriod == "lastweek") {
        $startDate = WHMCS\Carbon::today()->subWeeks(2);
        $endDate = WHMCS\Carbon::today()->subWeek(1);
    } else {
        if ($graphPeriod == "last30days") {
            $startDate = WHMCS\Carbon::today()->subDays(30);
            $endDate = WHMCS\Carbon::today();
        } else {
            if ($graphPeriod == "thismonth") {
                $startDate = new WHMCS\Carbon("first day of this month");
                $endDate = WHMCS\Carbon::today();
            } else {
                if ($graphPeriod == "lastmonth") {
                    $startDate = new WHMCS\Carbon("first day of last month");
                    $endDate = (new WHMCS\Carbon("first day of this month"))->subDay();
                }
            }
        }
    }
}
$data = localAPI("GetAutomationLog", ["namespace" => $graphMetric, "startdate" => $startDate->toDateString(), "enddate" => $endDate->toDateString()]);
$statistics = $data["statistics"];
$taskName = "\\WHMCS\\Cron\\Task\\" . $graphMetric;
$task = $taskName::firstOfClassOrNew();
$namespaceName = $task->getNamespace();
$successCountIdentifier = $task->getSuccessCountIdentifier();
$graphLabels = [];
$graphData = [];
$i = 0;
while ($i < 32) {
    $graphLabels[] = $startDate->format("jS");
    if (is_array($successCountIdentifier)) {
        $primarySuccessCount = 0;
        foreach ($successCountIdentifier as $identifier) {
            $primarySuccessCount += (int) $statistics[$startDate->toDateString()][$namespaceName][$identifier];
        }
    } else {
        $primarySuccessCount = (int) $statistics[$startDate->toDateString()][$namespaceName][$successCountIdentifier];
    }
    $graphData[] = (int) $primarySuccessCount;
    if ($startDate->toDateString() != $endDate->toDateString()) {
        $startDate->addDay();
        $i++;
    }
}
echo "\n<script>\n\$(document).ready(function() {\n\n    var canvas = document.getElementById(\"overviewChart\");\n    var parent = document.getElementById('overviewChartContainer');\n\n    canvas.width = parent.offsetWidth;\n    canvas.height = parent.offsetHeight;\n\n    var config = {\n        type: 'line',\n        data: {\n            labels: [\"";
echo implode("\",\"", $graphLabels);
echo "\"],\n            datasets: [{\n                label: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("utilities.automationStatusDetail.successCount"));
echo "',\n                backgroundColor: 'rgba(255, 205, 86, 0.4)',\n                borderColor: 'rgba(255, 205, 86, 0.8)',\n                data: [\n                    ";
echo implode(",", $graphData);
echo "                ],\n                fill: true,\n            }]\n        },\n        options: {\n            responsive: true,\n            legend: {\n                display: false\n            },\n            scales: {\n                xAxes: [{\n                    display: true,\n                    scaleLabel: {\n                        display: false,\n                        labelString: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("calendar.month"));
echo "'\n                    },\n                }],\n                yAxes: [{\n                    display: true,\n                    scaleLabel: {\n                        display: false,\n                        labelString: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("utilities.automationStatusDetail.count"));
echo "'\n                    },\n                    ticks: {\n                        beginAtZero: true\n                    }\n                }]\n            }\n        }\n    };\n\n    var ctx = document.getElementById(\"overviewChart\").getContext(\"2d\");\n    window.automationStatusChart = new Chart(ctx, config);\n});\n</script>\n\n";
$graphOutput = ob_get_contents();
ob_end_clean();
if ($action == "graph") {
    $aInt->jsonResponse(["status" => "1", "body" => $graphOutput]);
}
ob_start();
$data = localAPI("GetAutomationLog", ["startdate" => $date->toDateString(), "enddate" => $date->toDateString()]);
$statistics = $data["statistics"];
$isDisabledMap = ["AddLateFees" => WHMCS\Config\Setting::getValue("InvoiceLateFeeAmount") == 0, "AutoSuspensions" => !WHMCS\Config\Setting::getValue("AutoSuspension"), "AutoTerminations" => !WHMCS\Config\Setting::getValue("AutoTermination"), "CloseInactiveTickets" => WHMCS\Config\Setting::getValue("CloseInactiveTickets") == 0, "CurrencyUpdateExchangeRates" => !WHMCS\Config\Setting::getValue("CurrencyAutoUpdateExchangeRates"), "CurrencyUpdateProductPricing" => !WHMCS\Config\Setting::getValue("CurrencyAutoUpdateProductPrices"), "DomainStatusSync" => !WHMCS\Config\Setting::getValue("DomainSyncEnabled"), "DomainTransferSync" => !WHMCS\Config\Setting::getValue("DomainSyncEnabled")];
$isDisabledMap["DatabaseBackup"] = true;
$activeBackupSystems = WHMCS\Config\Setting::getValue("ActiveBackupSystems");
$backupCount = array_intersect(["email", "cpanel", "ftp"], explode(",", $activeBackupSystems));
if (count($backupCount)) {
    $isDisabledMap["DatabaseBackup"] = false;
}
foreach ($tasks as $task) {
    $taskName = "\\WHMCS\\Cron\\Task\\" . $task;
    $task = $taskName::firstOfClassOrNew();
    $namespaceName = $task->getNamespace();
    $decorator = new WHMCS\Cron\Decorator($task);
    $data = $statistics[$date->toDateString()][$namespaceName];
    $isDisabled = array_key_exists($namespaceName, $isDisabledMap) ? $isDisabledMap[$namespaceName] : false;
    echo "<div class=\"col-md-4 col-sm-6\">" . $decorator->render($data, $isDisabled) . "</div>";
}
$widgetsOutput = ob_get_contents();
ob_end_clean();
if ($action == "stats") {
    $aInt->jsonResponse(["status" => "1", "body" => $widgetsOutput, "newDate" => $dateDisplayLabel]);
}
ob_start();
echo "\n<div class=\"row\">\n    <div class=\"col-lg-12\">\n\n        <div class=\"btn-group day-selector\" role=\"group\">\n            <a href=\"#\" class=\"btn btn-viewing\">\n                ";
echo $dateDisplayLabel;
echo "            </a>\n        </div>\n\n        <h2>";
echo AdminLang::trans("utilities.automationStatusDetail.dailyActions");
echo "</h2>\n    </div>\n</div>\n\n<div class=\"row\">\n    <div class=\"col-lg-9\">\n        <div class=\"row widgets-container\">";
echo $widgetsOutput;
echo "</div>\n        <div class=\"alert alert-info\">\n            <i class=\"fas fa-info-circle fa-fw\"></i>\n            ";
echo AdminLang::trans("utilities.automationStatusDetail.info");
echo "        </div>\n    </div>\n    <div class=\"col-lg-3\">\n\n        <div class=\"calendar-container\">\n            <script>\n                \$(document).ready(function(){\n                    \$.fn.bootstrapDP = \$.fn.datepicker.noConflict();\n                    \$(\"#automation-status-calendar\").bootstrapDP({\n                        endDate: '";
echo WHMCS\Carbon::today()->toDateString();
echo "',\n                        format: 'yyyy\\-mm\\-dd',\n                        maxViewMode: 2,\n                        todayBtn: \"linked\",\n                        todayHighlight: true,\n                        templates: {\n                            leftArrow: '<i class=\"fad fa-caret-circle-left fa-swap-opacity\" style=\"--fa-primary-color: white; --fa-secondary-color: #337ab7;\"></i>',\n                            rightArrow: '<i class=\"fad fa-caret-circle-right fa-swap-opacity\" style=\"--fa-primary-color: white; --fa-secondary-color: #337ab7;\"></i>'\n                        }\n                    }).on('changeDate', function(e) {\n                        var date = e.date,\n                            year = date.getFullYear(),\n                            month = (date.getMonth() + 1),\n                            day = date.getDate();\n                        loadAutomationStatsForDate(year + '-' + month + '-' + day);\n                    });\n                });\n            </script>\n            <div id=\"automation-status-calendar\"></div>\n        </div>\n    </div>\n</div>\n\n";
$statsOutput = ob_get_contents();
ob_end_clean();
ob_start();
echo "\n<div class=\"automation-status\">\n\n    ";
echo view("admin.setup.automation.shared.status-badges", ["cronStatus" => new WHMCS\Cron\Status()]);
echo "\n    <div id=\"graphContainer\" class=\"graph-container\">\n        ";
echo $graphOutput;
echo "    </div>\n\n    <div id=\"statsContainer\">\n        ";
echo $statsOutput;
echo "    </div>\n\n</div>\n\n";
$content = ob_get_contents();
ob_end_clean();
$cdnUrlStart = "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/";
$aInt->addHeadOutput("<link href=\"" . $cdnUrlStart . "css/bootstrap-datepicker.standalone.min.css\" rel=\"stylesheet\">");
$aInt->addHeadOutput("<script src=\"" . $cdnUrlStart . "js/bootstrap-datepicker.min.js\"></script>");
$aInt->jquerycode = "jQuery(document).not('a.open-modal').on('click', 'div.automation-clickable-widget', function(e) {\n    if (e.target.localName === 'a') {\n        return true;\n    }\n    e.preventDefault();\n    jQuery(this).find('a:first').click();\n    return true;\n});";
$aInt->content = $content;
$aInt->display();
