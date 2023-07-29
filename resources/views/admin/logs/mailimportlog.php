<?php

echo "\n<div class=\"row\" style=\"margin: 10px 0px\">\n    ";
echo sprintf("%s %s, %s %s %s %s.", $totalEntries, AdminLang::trans("global.recordsfound"), AdminLang::trans("global.page"), $page, AdminLang::trans("global.of"), $totalPages);
echo "    <table class=\"datatable table-responsive\" id=\"tblImportLog\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n        <thead>\n            <th>";
echo AdminLang::trans("fields.date");
echo "</th>\n            <th>";
echo AdminLang::trans("emails.to");
echo "</th>\n            <th>";
echo AdminLang::trans("emails.subject");
echo "</th>\n            <th>";
echo AdminLang::trans("fields.status");
echo "</th>\n            <th>View</th>\n        </thead>\n        <tbody>\n        ";
if (!empty($importLogData)) {
    $modalTitle = AdminLang::trans("system.viewimportmessage");
    $viewLogText = AdminLang::trans("global.view");
    foreach ($importLogData as $record) {
        $date = WHMCS\Carbon::parse($record->date)->toAdminDateTimeFormat();
        $to = WHMCS\Input\Sanitize::makeSafeForOutput($record->to);
        $from = WHMCS\Input\Sanitize::makeSafeForOutput($record->name . " &laquo;" . $record->email . "&raquo;");
        $subject = WHMCS\Input\Sanitize::makeSafeForOutput($record->subject);
        $routePath = routePath("admin-logs-mail-import-view", $record->id);
        $modalLink = function ($string = "") use($routePath, $modalTitle) {
            return "<a href=\"" . $routePath . "\"\n    class=\"open-modal\"\n    data-modal-title=\"" . $modalTitle . "\">\n    " . $string . "\n</a>";
        };
        echo "                <tr>\n                    <td>\n                        ";
        echo $modalLink($date);
        echo "                    </td>\n                    <td>\n                        ";
        echo $to;
        echo "                    </td>\n                    <td>\n                        ";
        echo $modalLink($subject);
        echo "<br />\n                        ";
        echo $modalLink($from);
        echo "                    </td>\n                    <td>";
        echo $record->getStatusLabel();
        echo "</td>\n                    <td class=\"text-center\">\n                        ";
        echo $modalLink("<button class=\"btn btn-default\">" . $viewLogText . "</button>");
        echo "                    </td>\n                </tr>\n                ";
    }
} else {
    echo "<tr><td colspan=\"5\" class=\"text-center\">No Records Found</td></tr>";
}
echo "        </tbody>\n    </table>\n    <nav class=\"pull-right\">\n        <ul class=\"pagination\">\n            ";
foreach ($pagination as $item) {
    $disabled = $item["disabled"] ? " disabled" : "";
    $active = $item["active"] ? " active" : "";
    echo "<li class=\"page-item" . $active . $disabled . "\">\n    <a class=\"page-link" . $active . $disabled . "\" href=\"" . $item["link"] . "\">" . $item["text"] . "</a>\n</li>";
}
echo "        </ul>\n    </nav>\n</div>\n";
