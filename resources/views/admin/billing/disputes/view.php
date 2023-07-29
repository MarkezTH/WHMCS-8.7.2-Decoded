<?php

echo "<table class=\"form\" width=\"100%\" border=\"0\" cellpadding=\"3\" cellspacing=\"2\">\n    <tbody>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.createdAt");
echo "</td>\n            <td class=\"fieldarea\">";
echo $dispute->getCreatedDate()->toAdminDateTimeFormat();
echo "</td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("global.gateway");
echo "</td>\n            <td class=\"fieldarea\">";
echo $gateway;
echo "</td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.respondBy");
echo "</td>\n            <td class=\"fieldarea\">";
echo $dispute->getRespondByDate()->toAdminDateTimeFormat();
echo "</td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.amount");
echo "</td>\n            <td class=\"fieldarea\">";
echo $dispute->getAmount();
echo "</td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("disputes.disputeId");
echo "</td>\n            <td class=\"fieldarea\">";
echo $dispute->getId();
echo "</td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.transid");
echo "</td>\n            <td class=\"fieldarea\">";
echo $dispute->getTransactionId();
echo "</td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.status");
echo "</td>\n            <td id=\"statusField\" class=\"fieldarea\">";
echo $dispute->getStatus();
echo "</td>\n            <td class=\"fieldlabel\">";
echo AdminLang::trans("fields.reason");
echo "</td>\n            <td class=\"fieldarea\">";
echo $dispute->getReason();
echo "</td>\n        </tr>\n    </tbody>\n</table>\n<br>\n";
if ($flash) {
    echo WHMCS\View\Helper::alert($flash["text"], $flash["type"]);
}
echo "<h2>";
echo AdminLang::trans("disputes.evidenceSubmitted");
echo "</h2>\n<div class=\"tablebg\">\n    <table class=\"datatable\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n        <thead>\n            <tr>\n                <th>";
echo AdminLang::trans("disputes.evidenceType");
echo "</th>\n                <th>";
echo AdminLang::trans("disputes.evidenceProvided");
echo "</th>\n            </tr>\n        </thead>\n        <tbody>\n            ";
foreach ($dispute->getEvidence() as $disputeEvidence) {
    if ($disputeEvidence["value"]) {
        echo "                <tr>\n                    <td width=\"50%\">";
        echo AdminLang::trans("disputes.evidence." . $disputeEvidence["name"]);
        echo "</td>\n                    <td>\n                        ";
        if (is_string($disputeEvidence["value"])) {
            echo $disputeEvidence["value"];
        } else {
            if (is_array($disputeEvidence["value"])) {
                echo "                            <a href=\"";
                echo $disputeEvidence["value"]["url"];
                echo "\" class=\"autoLinked\">\n                                ";
                echo $disputeEvidence["value"]["filename"];
                echo "                            </a>\n                        ";
            }
        }
        echo "                    </td>\n                </tr>\n            ";
    }
}
echo "        </tbody>\n        <tfoot>\n            <tr>\n                <th colspan=\"2\">&nbsp;</th>\n            </tr>\n        </tfoot>\n    </table>\n</div>\n";
if ($dispute->getIsUpdatable()) {
    echo "    <h2>";
    echo AdminLang::trans("disputes.submitEvidence");
    echo "</h2>\n    <div class=\"tablebg\">\n        <form id=\"frmEvidence\" action=\"";
    echo $dispute->getUpdateHref();
    echo "\" method=\"post\" enctype=\"multipart/form-data\">\n            <table class=\"datatable\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n                <thead>\n                    <tr>\n                        <th width=\"50%\">Submit Evidence</th>\n                        <th></th>\n                        <th width=\"32px\"></th>\n                    </tr>\n                </thead>\n                <tbody>\n                    <tr>\n                        <td>\n                            <select class=\"form-control cloneable\" name=\"new-evidence\">\n                                <option value=\"\" selected=\"selected\">";
    echo AdminLang::trans("disputes.chooseNew");
    echo "</option>\n                                ";
    foreach (collect($dispute->getEvidence())->unique("name") as $disputeEvidence) {
        if (!$dispute->getVisibleTypes() || in_array($disputeEvidence["name"], $dispute->getVisibleTypes())) {
            echo "                                        <option data-name=\"";
            echo $disputeEvidence["name"];
            echo "\"\n                                                data-type=\"";
            echo $dispute->getEvidenceType($disputeEvidence["name"]);
            echo "\"\n                                                data-custom-data='";
            echo $dispute->getCustomData($disputeEvidence["name"]);
            echo "'\n                                                value=\"";
            echo $disputeEvidence["name"];
            echo "\"\n                                        >\n                                            ";
            echo AdminLang::trans("disputes.evidence." . $disputeEvidence["name"]);
            echo "                                        </option>\n                                    ";
        }
    }
    echo "                            </select>\n                        </td>\n                        <td class=\"input-cell\"></td>\n                        <td class=\"remove-row\">\n                            <button type=\"button\" class=\"remove-row hidden btn btn-sm btn-default\" aria-label=\"";
    echo AdminLang::trans("disputes.removeEvidenceRow");
    echo "\">\n                                <i aria-hidden=\"true\" class=\"fal fa-do-not-enter text-danger\"></i>\n                            </button>\n                        </td>\n                    </tr>\n                </tbody>\n                <tfoot>\n                    <tr>\n                        <th colspan=\"3\">\n                            <button type=\"submit\" name=\"submit\" class=\"btn btn-sm btn-default disabled\" disabled=\"disabled\">\n                                ";
    echo AdminLang::trans("disputes.updateEvidence");
    echo "                            </button>\n                        </th>\n                    </tr>\n                </tfoot>\n            </table>\n        </form>\n    </div>\n";
}
echo "\n<div class=\"btn-container\">\n    <div class=\"btn-group\">\n        ";
if ($dispute->getIsSubmittable()) {
    echo "            <button type=\"button\" class=\"btn btn-success\" id=\"btnSubmitDispute\" data-toggle=\"modal\" data-target=\"#modalSubmit\">\n                ";
    echo AdminLang::trans("disputes.submitDispute");
    echo "            </button>\n        ";
}
echo "        <a class=\"btn btn-default\" href=\"";
echo routePath("admin-billing-disputes-index");
echo "\">\n            ";
echo AdminLang::trans("disputes.return");
echo "        </a>\n        ";
if ($dispute->getManageHref()) {
    echo "            <a class=\"btn btn-default\" href=\"";
    echo WHMCS\Input\Sanitize::encode($dispute->getManageHref());
    echo "\" target=\"_blank\">\n                ";
    echo AdminLang::trans("disputes.manageDispute");
    echo "            </a>\n        ";
}
echo "        ";
if ($dispute->getIsClosable()) {
    echo "        <button type=\"button\" class=\"btn btn-danger\" id=\"btnCloseDispute\" data-toggle=\"modal\" data-target=\"#modalClose\">\n            ";
    echo AdminLang::trans("disputes.closeDispute");
    echo "        </button>\n        ";
}
echo "    </div>\n</div>\n\n<div class=\"hidden\">\n    <input type=\"text\" class=\"form-control\" id=\"textClone\">\n    <input type=\"file\" class=\"form-control\" id=\"fileClone\">\n    <textarea class=\"form-control\" id=\"textareaClone\" cols=\"300\" rows=\"5\"></textarea>\n</div>\n\n";
$this->insert("billing/disputes/partials/modals");
echo "\n<script>\n    jQuery(document).ready(function() {\n        jQuery('body').on('change', 'select[name=\"new-evidence\"]', function() {\n            var self = jQuery(this),\n                selectedRow = jQuery(this).find('option[value=\"' + self.val() + '\"]'),\n                tr = self.closest('tr'),\n                clone = tr.clone(),\n                name = selectedRow.data('name'),\n                type = selectedRow.data('type'),\n                form = self.closest('form'),\n                submitButton = form.find('button[name=\"submit\"]');\n\n            if (type === 'custom') {\n                selectedRow.data('custom-data').forEach(function(customInput) {\n                    jQuery('#' + customInput.type + 'Clone')\n                        .clone()\n                        .attr('name', name + '-' + customInput.name)\n                        .attr('id', name + '-' + customInput.name + 'Input')\n                        .attr('placeholder', customInput.placeholder)\n                        .prependTo(tr.find('.input-cell'));\n                });\n            } else {\n                jQuery('#' + type + 'Clone').clone()\n                    .attr('name', name)\n                    .attr('id', name + 'Input')\n                    .prependTo(tr.find('.input-cell'));\n            }\n\n            if (submitButton.hasClass('disabled')) {\n                submitButton.removeClass('disabled').prop('disabled', false);\n            }\n\n            clone.find('option[value=\"' + self.val() + '\"]')\n                .addClass('disabled')\n                .prop('disabled', true);\n\n            tr.find('td.remove-row button').removeClass('hidden').data('input-name', name);\n\n            tr.after(clone);\n            self.addClass('disabled').prop('disabled', true);\n        })\n            .on('click', 'button.remove-row', function() {\n                var self = jQuery(this),\n                    row = self.closest('tr'),\n                    table = row.closest('table'),\n                    form = self.closest('form'),\n                    submitButton = form.find('button[name=\"submit\"]');\n\n                table.find('select[name=\"new-evidence\"]')\n                    .find('option[value=\"' + self.data('input-name') + '\"]')\n                    .removeClass('disabled')\n                    .prop('disabled', false);\n\n                row.find('td.input-cell').html('');\n                row.find('select')\n                    .removeClass('disabled')\n                    .prop('disabled', false);\n\n                if (table.find('input').length === 0) {\n                    submitButton.addClass('disabled').prop('disabled', true);\n                }\n                if (table.find('select').length > 1) {\n                    row.remove();\n                }\n            });\n\n        jQuery('#frmModalSubmit').on('submit', function(e) {\n            e.preventDefault();\n            var btnSubmit = jQuery('#btnSubmitDispute'),\n                btnClose = jQuery('#btnCloseDispute');\n            btnSubmit.addClass('disabled').prop('disabled', true);\n            btnClose.addClass('disabled').prop('disabled', true);\n\n            var frm = jQuery('#frmEvidence')\n\n            if (frm.find('input,textarea').length > 1) {\n                jQuery('#modalSubmit').modal('hide');\n                btnSubmit.removeClass('disabled').prop('disabled', false);\n                btnClose.removeClass('disabled').prop('disabled', false);\n                jQuery.growl.error(\n                    {\n                        title: '',\n                        message: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.unsubmittedEvidence"));
echo "'\n                    }\n                );\n                return;\n            }\n            jQuery('#modalSubmitLoader').show();\n\n            WHMCS.http.jqClient.jsonPost({\n                url: '";
echo $dispute->getSubmitHref();
echo "',\n                data: {\n                    token: csrfToken\n                },\n                success: function(data) {\n                    if (data.success) {\n                        jQuery.growl.notice(\n                            {\n                                title: '',\n                                message: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.submitSuccess"));
echo "'\n                            }\n                        );\n                        jQuery('#statusField').text('";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.statuses.under_review"));
echo "');\n                    }\n                    if (data.errorMsg) {\n                        jQuery.growl.warning({title: '', message: data.errorMsg});\n                        jQuery('#btnSubmitDispute').removeClass('disabled').prop('disabled', false);\n                        jQuery('#btnCloseDispute').removeClass('disabled').prop('disabled', false);\n                    }\n                },\n                always: function() {\n                    jQuery('#modalSubmitLoader').hide();\n                    jQuery('#modalSubmit').modal('hide');\n                }\n            });\n        });\n\n        jQuery('#frmModalClose').on('submit', function(e) {\n            e.preventDefault();\n            jQuery('#modalCloseLoader').show();\n            jQuery('#btnSubmitDispute').addClass('disabled').prop('disabled', true);\n            jQuery('#btnCloseDispute').addClass('disabled').prop('disabled', true);\n\n            WHMCS.http.jqClient.jsonPost({\n                url: '";
echo $dispute->getCloseHref();
echo "',\n                data: {\n                    token: csrfToken\n                },\n                success: function(data) {\n                    if (data.success) {\n                        jQuery.growl.notice(\n                            {\n                                title: '',\n                                message: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.closedSuccess"));
echo "'\n                            }\n                        );\n                        jQuery('#statusField').text('";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("disputes.statuses.lost"));
echo "');\n                    }\n                    if (data.errorMsg) {\n                        jQuery.growl.warning({title: '', message: data.errorMsg});\n                        jQuery('#btnSubmitDispute').removeClass('disabled').prop('disabled', false);\n                        jQuery('#btnCloseDispute').removeClass('disabled').prop('disabled', false);\n                    }\n                },\n                always: function() {\n                    jQuery('#modalCloseLoader').hide();\n                    jQuery('#modalClose').modal('hide');\n                }\n            });\n        });\n    });\n</script>\n";
