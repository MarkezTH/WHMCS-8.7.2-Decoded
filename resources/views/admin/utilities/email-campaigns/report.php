<?php

echo "<h2>";
echo $campaign->name;
echo "    <span class=\"pull-right label label-";
echo $statusLabel;
echo "\">\n        ";
echo AdminLang::trans("status." . $campaignStatus);
echo "    </span>\n</h2>\n<div class=\"padding-two bottom-margin-5\">\n    ";
echo AdminLang::trans("fields.startDate");
echo ": ";
echo $campaign->sendingStartAt->toAdminDateTimeFormat();
echo "</div>\n<div class=\"padding-two bottom-margin-5\">\n    ";
echo AdminLang::trans("fields.completedDate");
echo ": ";
echo $campaign->completed_at ? $campaign->completedAt->toAdminDateTimeFormat() : "-";
echo "</div>\n<div id=\"progressSending\" class=\"progress\">\n    <div id=\"divSentEmails\" class=\"progress-bar progress-bar-success\" aria-valuenow=\"";
echo $sent;
echo "\" aria-valuemax=\"";
echo $total;
echo "\">\n        ";
echo AdminLang::trans("utilities.emailCampaigns.sentEmails", [":count" => $sent]);
echo "    </div>\n    <div id=\"divFailedEmails\" class=\"progress-bar progress-bar-danger\" aria-valuenow=\"";
echo $failed;
echo "\" aria-valuemax=\"";
echo $total;
echo "\">\n        ";
echo AdminLang::trans("utilities.emailCampaigns.failedEmails", [":count" => $failed]);
echo "    </div>\n    <div id=\"divRemainingEmails\" class=\"progress-bar progress-bar-info\"  aria-valuenow=\"";
echo $total - ($failed + $sent);
echo "\" aria-valuemax=\"";
echo $total;
echo "\">\n        ";
echo AdminLang::trans("utilities.emailCampaigns.remainingEmails", [":count" => $total - ($failed + $sent)]);
echo "    </div>\n</div>\n<div id=\"retryError\" class=\"alert alert-danger admin-modal-error\" style=\"display: none\"></div>\n<table class=\"table table-condensed table-striped\">\n    <thead>\n        <tr>\n            <th>";
echo AdminLang::trans("fields.client");
echo "</th>\n            <th>";
echo AdminLang::trans("queue.failureReason");
echo "</th>\n            <th></th>\n        </tr>\n    </thead>\n    <tbody>\n        ";
if (0 < $failed && count($failedEmails)) {
    echo "            ";
    foreach ($failedEmails as $failedEmail) {
        echo "                <tr>\n                    <td>\n                        <a href=\"";
        echo DI::make("asset")->getWebRoot() . "/" . $failedEmail->client->getLink();
        echo "\" class=\"autoLinked\">\n                            ";
        echo $failedEmail->client->fullName;
        echo "                        </a>\n                    </td>\n                    <td class=\"failure-reason\">\n                        ";
        echo $failedEmail->failureReason;
        echo "                    </td>\n                    <td class=\"text-center\">\n                        ";
        if ($failedEmail->messageData) {
            echo "                            <button type=\"button\"\n                                    class=\"btn btn-xs btn-default btn-retry\"\n                                    data-email-id=\"";
            echo $failedEmail->id;
            echo "\"\n                            >\n                                ";
            echo AdminLang::trans("global.retry");
            echo "                            </button>\n                        ";
        }
        echo "                    </td>\n                </tr>\n            ";
    }
    echo "        ";
}
echo "        <tr id=\"rowNoResults\" ";
echo 0 < $failed && count($failedEmails) ? "class=\"hidden\"" : "";
echo ">\n            <td colspan=\"3\">\n                ";
echo AdminLang::trans("utilities.emailCampaigns.noFailedEmails");
echo "            </td>\n        </tr>\n    </tbody>\n</table>\n<script>\n    jQuery(document).ready(function() {\n        updateSendingProgress();\n    });\n</script>\n";
