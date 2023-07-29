<?php

echo "<div class=\"row\">\n    <form action=\"";
echo routePath("admin-logs-mail-import-importnow", $record->id);
echo "\" id=\"frmImportNow\" method=\"POST\">\n        ";
echo generate_token();
echo "        <input type=\"hidden\" name=\"id\" value=\"";
echo $record->id;
echo "\" />\n    </form>\n</div>\n<div class=\"row container-fluid\">\n    <strong>";
echo AdminLang::trans("emails.to");
echo ":</strong>\n    <span>\n        ";
echo WHMCS\Input\Sanitize::makeSafeForOutput($record->to);
echo "    </span><br />\n    <strong>";
echo AdminLang::trans("emails.from");
echo ":</strong>\n    <span>\n        ";
$name = WHMCS\Input\Sanitize::makeSafeForOutput($record->name);
$email = WHMCS\Input\Sanitize::makeSafeForOutput($record->email);
if ($user) {
    $routePath = fqdnRoutePath("admin-user-manage", $user->id);
    $title = AdminLang::trans("user.manageUserEmail", [":email" => $user->email]);
    $submitLabel = AdminLang::trans("global.save");
    echo "<a\n    href=\"" . $routePath . "\"\n    class=\"open-modal\"\n    data-modal-title=\"" . $title . "\"\n    data-modal-size=\"modal-lg\"\n    data-btn-submit-label=\"" . $submitLabel . "\"\n    data-btn-submit-id=\"btnUpdateUser\"\n>\n    " . $name . " &laquo;" . $email . "&raquo;\n</a>";
} else {
    echo $name . " &laquo;" . $email . "&raquo;";
}
echo "    </span><br />\n    <strong>";
echo AdminLang::trans("emails.subject");
echo ":</strong>\n    <span>\n        ";
echo WHMCS\Input\Sanitize::makeSafeForOutput($record->subject);
echo "    </span><br />\n    <strong>";
echo AdminLang::trans("fields.status");
echo ":</strong>\n    <span>\n        ";
echo $record->getStatusLabel();
echo "    </span>\n</div>\n\n<h4>";
echo AdminLang::trans("utilities.ticketMailLog.messageContent");
echo ":</h4>\n\n<hr>\n\n<div style=\"padding-bottom: 25px;\">\n    ";
echo $record->safeMessage;
echo "</div>\n\n<hr>\n\n";
if ($footer) {
    echo WHMCS\View\Helper::alert($footer, $footerClass);
}
