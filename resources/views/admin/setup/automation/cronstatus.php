<?php

echo "<p>WHMCS requires the regular and frequent invocation of a file via cron to automate tasks within WHMCS.</p>\n\n<p>";
echo AdminLang::trans("automation.cronSample");
echo "</p>\n\n<h2>Cron Command</h2>\n\n<p><strong>Recommended Schedule:</strong> Every 5 minutes, or as frequently as your hosting provider allows</p>\n\n<div class=\"cron-command input-group\">\n    <input type=\"text\"\n           id=\"cronPhp\"\n           value=\"";
echo $cronCommand;
echo "\"\n           class=\"form-control\"\n           readonly=\"readonly\"\n    />\n    <span class=\"input-group-btn\">\n        <button class=\"btn btn-default copy-to-clipboard\"\n                data-clipboard-target=\"#cronPhp\"\n                type=\"button\"\n        >\n            <i class=\"fal fa-copy\" title=\"";
echo $copyToClipboard;
echo ">\"></i>\n            <span class=\"sr-only\">";
echo AdminLang::trans("global.clipboardCopy");
echo "></span>\n        </button>\n    </span>\n</div>\n\n<h2>Cron Status</h2>\n\n<table class=\"table table-striped\">\n    <tr>\n        <th width=\"200\">Item</th>\n        <th width=\"100\" class=\"text-center\">Status</th>\n        <th>Description</th>\n    </tr>\n";
foreach ($reportData as $report) {
    echo "<tr>\n        <td>" . $report["title"] . "</td>\n        <td class=\"text-center\">" . (is_null($report["status"]) ? "-" : "<i class=\"fas fa-" . ($report["status"] ? "check text-success" : "times") . "\"></i>") . "</td>\n        <td>" . ($report["status"] ? "Ok" : $report["description"] . " <a href=\"" . $report["docs"] . "\" target=\"_blank\" class=\"underlined\">Learn more...</a>") . "</td>\n    </tr>";
}
echo "</table>\n<form method=\"post\" action=\"";
echo routePath("admin-setup-automation-cron-status");
echo "\">\n    <button type=\"button\" class=\"btn btn-default btn-sm\" onclick=\"submitIdAjaxModalClickEvent()\">\n        <i class=\"fas fa-sync\"></i>\n        Refresh\n    </button>\n</form>\n";
