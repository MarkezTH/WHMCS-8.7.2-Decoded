<?php

echo "<div class=\"pull-right\">\n    <div class=\"panel panel-default panel-market-account";
echo $account["linked"] ? " account-linked" : "";
echo "\" id=\"panelAccount\">\n        <div class=\"panel-heading\">\n            <div class=\"btn-group pull-right\" style=\"margin-top: -6px;margin-right: -11px;\">\n                <button type=\"button\" class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\" style=\"background: transparent;border: 0;box-shadow: none;\">\n                    <i class=\"fas fa-question-circle\"></i>\n                </button>\n                <ul class=\"dropdown-menu pull-right\">\n                    <li><a href=\"https://marketplace.whmcs.com/connect/getting-started\" target=\"_blank\"><i class=\"fas fa-star fa-fw\"></i> Getting Started Guide</a></li>\n                    <li><a href=\"https://marketplace.whmcs.com/promotions\" target=\"_blank\"><i class=\"fas fa-ticket-alt fa-fw\"></i> Current Promotions</a></li>\n                    <li><a href=\"https://marketplace.whmcs.com/help/connect/kb\" target=\"_blank\"><i class=\"fas fa-question-circle fa-fw\"></i> Knowledgebase</a></li>\n                    <li><a href=\"https://marketplace.whmcs.com/contact/connect\" target=\"_blank\"><i class=\"fas fa-envelope fa-fw\"></i> Contact Support</a></li>\n                    <li class=\"divider\"></li>\n                    <li><a href=\"#\" class=\"account-refresh\"><i class=\"fas fa-sync fa-fw\"></i> ";
echo AdminLang::trans("marketConnect.refresh");
echo "</a></li>\n                </ul>\n            </div>\n            <strong>";
echo AdminLang::trans("marketConnect.yourAccount");
echo "</strong>\n        </div>\n        <div class=\"panel-body text-center account-linked\">\n            <div class=\"text-center\" style=\"margin-bottom:1px;\">\n                ";
echo AdminLang::trans("marketConnect.yourBalance");
echo ":\n                <span class=\"points-balance\">\n                    <span class=\"points-container";
if (empty($account["balance"]) || $balanceNeedsUpdate) {
    echo " hidden";
}
echo "\">\n                        <span class=\"balance\">";
echo $account["balance"] ?? "";
echo "</span> ";
echo AdminLang::trans("marketConnect.points");
echo "                    </span>\n                    <span class=\"retrieving-container";
if (empty($balanceNeedsUpdate)) {
    echo " hidden";
}
echo "\">\n                        <span style=\"color:#aaa;font-weight:300;\"><i class=\"fas fa-spinner fa-spin\"></i> Retrieving...</span>\n                    </span>\n                </span>\n            </div>\n            <div class=\"info-line\">";
echo AdminLang::trans("marketConnect.lastUpdated");
echo ": <span class=\"balance-last-updated\">";
echo $balanceLastUpdated ?? "";
echo "</span></div>\n            <div class=\"linked-to info-line\">";
echo AdminLang::trans("marketConnect.linkedTo");
echo " <u class=\"account-email\">";
echo $account["email"] ?? "";
echo "</u></div>\n            <div class=\"connection-error\">Unable to communicate with Marketplace.</div>\n            <div class=\"auth-error\">Could not authenticate. <a href=\"#\" id=\"btnResolveAuthError\">Resolve now</a></div>\n            <form method=\"post\" action=\"\" target=\"_blank\" id=\"frmDepositFunds\">\n                <input type=\"hidden\" name=\"action\" value=\"sso\">\n                <button type=\"submit\" name=\"destination\" value=\"deposit\" class=\"btn btn-default btn-sm\">";
echo AdminLang::trans("marketConnect.depositFunds");
echo "</button>\n                <button type=\"button\" class=\"btn btn-default btn-sm\" id=\"btnDisconnect\">";
echo AdminLang::trans("marketConnect.disconnect");
echo "</button>\n            </form>\n        </div>\n        <div class=\"panel-body text-center account-not-linked\">\n            <button type=\"button\" data-toggle=\"modal\" data-target=\"#loginModal\" class=\"btn btn-default btn-sm btn-login-create-account\">";
echo AdminLang::trans("marketConnect.loginCreate");
echo "</button>\n        </div>\n    </div>\n</div>\n";
