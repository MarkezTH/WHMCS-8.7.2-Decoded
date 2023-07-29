<?php

if (count($gateways) < 1) {
    echo WHMCS\View\Helper::alert(AdminLang::trans("gateways.nonesetup", [":paymentGatewayURI" => routePath("admin-apps-category", "payments")]), "danger");
} else {
    echo "<div class=\"alert alert-danger admin-modal-error\" style=\"display: none\"></div>\n<form class=\"form-horizontal\" id=\"frmNewInvoice\" action=\"";
    echo routePath("admin-billing-invoice-create");
    echo "\">\n    ";
    echo generate_token();
    echo "    <div class=\"admin-tabs-v2\">\n        <div class=\"tab-content\">\n            <div class=\"tab-pane active\">\n                <div class=\"form-group\">\n                    <label for=\"\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
    echo AdminLang::trans("fields.client");
    echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        <select id=\"clientSearch\"\n                                name=\"client\"\n                                class=\"form-control selectize selectize-user-search\"\n                                data-value-field=\"id\"\n                                data-allow-empty-option=\"0\"\n                                data-search-url=\"";
    echo routePath("admin-search-client");
    echo "\"\n                                placeholder=\"";
    echo AdminLang::trans("global.typeToSearchClients");
    echo "\"\n                                data-active-label=\"";
    echo AdminLang::trans("status.active");
    echo "\"\n                                data-inactive-label=\"";
    echo AdminLang::trans("status.inactive");
    echo "\"\n                        >\n                        </select>\n                        <div class=\"field-error-msg\">\n                            ";
    echo AdminLang::trans("validation.required", [":attribute" => AdminLang::trans("fields.client")]);
    echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"inputNewInvoiceDate\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
    echo AdminLang::trans("fields.invoicedate");
    echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        <div class=\"date-picker-prepend-icon\">\n                            <label for=\"inputNewInvoiceDate\" class=\"field-icon\">\n                                <i class=\"fal fa-calendar-alt\"></i>\n                            </label>\n                            <input id=\"inputNewInvoiceDate\"\n                                   type=\"text\"\n                                   name=\"date\"\n                                   value=\"";
    echo WHMCS\Carbon::now()->toAdminDateFormat();
    echo "\"\n                                   class=\"form-control date-picker-single\"\n                                   data-opens=\"left\"\n                            />\n                        </div>\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"inputNewDueDate\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
    echo AdminLang::trans("fields.duedate");
    echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        <div class=\"date-picker-prepend-icon\">\n                            <label for=\"inputNewDueDate\" class=\"field-icon\">\n                                <i class=\"fal fa-calendar-alt\"></i>\n                            </label>\n                            <input id=\"inputNewDueDate\"\n                                   type=\"text\"\n                                   name=\"due\"\n                                   value=\"";
    echo WHMCS\Carbon::now()->addDays($invoiceGenerationDays)->toAdminDateFormat();
    echo "\"\n                                   class=\"form-control date-picker-single\"\n                                   data-opens=\"left\"\n                            />\n                        </div>\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"inputNewInvoiceGateway\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
    echo AdminLang::trans("fields.paymentmethod");
    echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        <select id=\"inputNewInvoiceGateway\" name=\"gateway\" class=\"form-control\">\n                            <option value=\"\" selected=\"selected\">\n                                ";
    echo AdminLang::trans("clients.changedefault");
    echo "                            </option>\n                            ";
    foreach ($gateways as $moduleName => $displayName) {
        echo "                                <option value=\"";
        echo $moduleName;
        echo "\">";
        echo $displayName;
        echo "</option>\n                            ";
    }
    echo "                        </select>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</form>\n<script>\n    function validateRequired()\n    {\n        var frm = jQuery('#frmNewInvoice');\n        if (!frm.length || !frm.is(':visible')) {\n            return true;\n        }\n\n        frm.find('.form-group').removeClass('has-error');\n        frm.find('.field-error-msg').hide();\n\n        var clientSearch = jQuery('#clientSearch'),\n            complete = true;\n\n        if (!clientSearch.val()) {\n            clientSearch.showInputError();\n            complete = false;\n        }\n\n        return complete;\n    }\n    jQuery(document).ready(function() {\n        WHMCS.selectize.userSearch();\n        initDateRangePicker();\n        addAjaxModalSubmitEvents('validateRequired');\n    });\n</script>\n";
}
