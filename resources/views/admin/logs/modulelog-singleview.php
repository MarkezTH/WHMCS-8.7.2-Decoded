<?php

$date = WHMCS\Carbon::parse($record->date);
if ($date) {
    $date = $date->toAdminDateTimeFormat();
} else {
    $date = "N/A";
}
echo "\n<p>\n    <strong>";
echo AdminLang::trans("fields.date");
echo ":</strong> ";
echo $date;
echo " -\n    <strong>";
echo AdminLang::trans("fields.module");
echo ":</strong> ";
echo $record->module;
echo " -\n    <strong>";
echo AdminLang::trans("fields.action");
echo ":</strong> ";
echo $record->action;
echo "</p>\n\n<div style=\"margin-bottom: 10px;\">\n    <strong>";
echo AdminLang::trans("fields.request");
echo "</strong><br />\n    <textarea rows=\"10\" class=\"form-control\">";
echo htmlentities($record->request);
echo "</textarea>\n</div>\n\n<div style=\"margin-bottom: 10px;\">\n    <strong>";
echo AdminLang::trans("fields.response");
echo "</strong><br />\n    <textarea rows=\"20\" class=\"form-control\">";
echo htmlentities($record->response);
echo "</textarea>\n</div>\n\n";
if ($record->arrdata) {
    echo "    <div style=\"margin-bottom: 10px;\">\n        <strong>";
    echo AdminLang::trans("fields.interpretedresponse");
    echo "</strong><br />\n        <textarea rows=\"20\" class=\"form-control\">";
    echo htmlentities($record->arrdata);
    echo "</textarea>\n    </div>\n";
}
echo "\n<div style=\"margin-bottom: 10px;\">\n    <a href=\"";
echo routePath("admin-logs-module-log");
echo "\" class=\"btn btn-primary\">\n        &laquo; ";
echo AdminLang::trans("global.back");
echo "    </a>\n</div>\n";
