<?php

if ($authenticationFailures) {
    echo infoBox(AdminLang::trans("error.authentication"), AdminLang::trans("disputes.error.authenticationFailure", [":failedGateways" => implode(",", $authenticationFailures)]));
}
echo "\n<p>\n    ";
echo AdminLang::trans("disputes.description1");
echo " ";
echo AdminLang::trans("disputes.description2");
echo "</p>\n\n";
if (!$supportedGateways) {
    $learnMoreUrl = "https://docs.whmcs.com/Disputes";
    $learnMoreText = AdminLang::trans("global.learnMore");
    $learnMoreComplete = sprintf("<a href=\"%s\" target=\"_blank\">%s</a>.", $learnMoreUrl, $learnMoreText);
    unset($learnMoreText);
    unset($learnMoreUrl);
    echo WHMCS\View\Helper::alert(AdminLang::trans("disputes.noSupportedGateways", [":learnMore" => $learnMoreComplete]));
} else {
    echo "\n<table id=\"tblDisputes\" class=\"table display data-driven table-themed\"\n       data-dom='<\"listtable\"ift>pl'\n       data-lang-empty-table=\"";
    echo AdminLang::trans("global.norecordsfound");
    echo "\"\n       data-auto-width=\"false\"\n       data-searching=\"true\"\n       data-paging=\"true\"\n       data-page-length=\"25\"\n       data-defer-reder=\"true\"\n       data-order='[[ 2, \"desc\"]]'\n       data-columns='";
    echo json_encode([["data" => "gateway", "className" => "details-control"], ["data" => "disputeId", "className" => "details-control"], ["data" => "createdAt", "className" => "details-control"], ["data" => "respondBy", "className" => "details-control"], ["data" => "amount", "className" => "details-control"], ["data" => "transid", "className" => "details-control"], ["data" => "reason", "className" => "details-control"], ["data" => "status", "className" => "details-control status"], ["data" => "btnGroup", "orderable" => 0, "width" => "70px", "className" => "text-center"]]);
    echo "'\n>\n    <thead>\n        <tr>\n            <th>";
    echo AdminLang::trans("fields.gateway");
    echo "</th>\n            <th>";
    echo AdminLang::trans("disputes.disputeId");
    echo "</th>\n            <th>";
    echo AdminLang::trans("fields.createdAt");
    echo "</th>\n            <th>";
    echo AdminLang::trans("fields.respondBy");
    echo "</th>\n            <th>";
    echo AdminLang::trans("fields.amount");
    echo "</th>\n            <th>";
    echo AdminLang::trans("fields.transid");
    echo "</th>\n            <th>";
    echo AdminLang::trans("fields.reason");
    echo "</th>\n            <th>";
    echo AdminLang::trans("fields.status");
    echo "</th>\n            <th></th>\n        </tr>\n    </thead>\n    <tbody>\n        ";
    foreach ($disputeCollections as $gateway => $disputeCollection) {
        foreach ($disputeCollection as $dispute) {
            echo "                <tr>\n                    <td>";
            echo $gateway;
            echo "</td>\n                    <td>";
            echo $dispute->getId();
            echo "</td>\n                    <td>\n                        ";
            if ($dispute->getCreatedDate()) {
                echo "                            <span class=\"hidden\">\n                                ";
                echo $dispute->getCreatedDate()->timestamp;
                echo "                            </span>\n                            ";
                echo $dispute->getCreatedDate()->toAdminDateTimeFormat();
                echo "                        ";
            }
            echo "                    </td>\n                    <td>\n                        ";
            if ($dispute->getRespondByDate()) {
                echo "                            <span class=\"hidden\">\n                                ";
                echo $dispute->getRespondByDate()->timestamp;
                echo "                            </span>\n                            ";
                echo $dispute->getRespondByDate()->toAdminDateTimeFormat();
                echo "                        ";
            }
            echo "                    </td>\n                    <td>";
            echo $dispute->getAmount()->toPrefixed();
            echo "</td>\n                    <td>";
            echo $dispute->getTransactionId();
            echo "</td>\n                    <td>";
            echo $dispute->getReason();
            echo "</td>\n                    <td>";
            echo $dispute->getStatus();
            echo "</td>\n                    <td>\n                        ";
            if ($currentUser->hasPermission("Manage Disputes") || $currentUser->hasPermission("Close Disputes")) {
                echo "                            <div class=\"btn-group\">\n                                ";
                if ($currentUser->hasPermission("Manage Disputes")) {
                    echo "                                    <a href=\"";
                    echo $dispute->getViewHref();
                    echo "\"\n                                       class=\"btn btn-sm btn-default\"\n                                       aria-label=\"";
                    echo AdminLang::trans("disputes.viewDispute");
                    echo "\"\n                                       data-toggle=\"tooltip\"\n                                       data-placement=\"auto top\"\n                                       data-trigger=\"hover\"\n                                       title=\"";
                    echo AdminLang::trans("disputes.viewDispute");
                    echo "\"\n                                    >\n                                        <i class=\"fal fa-eye\" aria-hidden=\"true\"></i>\n                                    </a>\n                                ";
                }
                echo "                                ";
                if ($currentUser->hasPermission("Close Disputes")) {
                    echo "                                    <button type=\"button\"\n                                            data-href=\"";
                    echo $dispute->getCloseHref();
                    echo "\"\n                                            class=\"btn btn-sm btn-danger close-dispute";
                    echo !$dispute->getIsClosable() ? " disabled" : "";
                    echo "\"\n                                            aria-label=\"";
                    echo AdminLang::trans("disputes.closeDispute");
                    echo "\"\n                                            data-toggle=\"tooltip\"\n                                            data-placement=\"auto top\"\n                                            data-trigger=\"hover\"\n                                            title=\"";
                    echo AdminLang::trans("disputes.closeDispute");
                    echo "\"\n                                            ";
                    echo !$dispute->getIsClosable() ? "disabled=\"disabled\"" : "";
                    echo "                                    >\n                                        <i class=\"fal fa-times-circle\" aria-hidden=\"true\"></i>\n                                    </button>\n                                ";
                }
                echo "                            </div>\n                        ";
            }
            echo "                    </td>\n                </tr>\n                ";
        }
    }
    echo "    </tbody>\n</table>\n";
    $this->insert("billing/disputes/partials/modals");
    echo "<script>\n    jQuery(document).ready(function() {\n        jQuery('[data-toggle=\"tooltip\"]').tooltip();\n\n        jQuery('button.close-dispute').click(function() {\n            var modal = jQuery('#modalClose');\n            modal.closest('form').attr('action', jQuery(this).data('href'));\n            modal.modal('show');\n        });\n\n        jQuery('#frmModalClose').on('submit', function(e) {\n            e.preventDefault();\n            var self = jQuery(this),\n                row = self.closest('tr');\n            jQuery('#modalCloseLoader').show();\n\n            WHMCS.http.jqClient.jsonPost({\n                url: self.attr('action'),\n                data: self.serialize(),\n                success: function(data) {\n                    if (data.success) {\n                        jQuery.growl.notice(\n                            {\n                                title: '',\n                                message: '";
    echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.closedSuccess"));
    echo "'\n                            }\n                        );\n                        row.find('td.status').text('";
    echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.statuses.lost"));
    echo "');\n                        row.closest('table').DataTable()\n                            .cell(row.find('td.status'))\n                            .invalidate('dom')\n                            .draw();\n                        self.addClass('disabled').prop('disabled', true);\n                    }\n                    if (data.errorMsg) {\n                        jQuery.growl.warning({title: '', message: data.errorMsg});\n                    }\n                },\n                always: function() {\n                    jQuery('#modalCloseLoader').hide();\n                    jQuery('#modalClose').modal('hide');\n                }\n            });\n        });\n    });\n</script>\n";
}
