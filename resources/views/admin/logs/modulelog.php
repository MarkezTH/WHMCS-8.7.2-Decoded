<?php

if ($flash) {
    echo WHMCS\View\Helper::alert($flash["text"], $flash["type"]);
}
echo "\n<div class=\"row\" style=\"margin: 0;\">\n    <p>\n        ";
echo AdminLang::trans("system.moduledebuglogdesc");
echo "    </p>\n</div>\n\n<div class=\"admin-tabs-v2\">\n    <ul class=\"nav nav-tabs admin-tabs\" role=\"tablist\" id=\"tablist\">\n        <li class=\"pull-right\">\n            <form id=\"frmModuleLogging\"\n                  action=\"";
echo routePath("admin-logs-module-log-clear");
echo "\"\n                  class=\"form-inline\"\n                  method=\"POST\"\n            >\n                ";
echo AdminLang::trans("utilities.moduleLogging");
echo ":\n                <input type=\"hidden\" name=\"enableModuleLogging\" value=\"0\" />\n                <input type=\"checkbox\"\n                       name=\"enableModuleLogging\"\n                       value=\"1\"\n                       class=\"slide-toggle\"\n                       id=\"enableModuleLogging\"\n                       data-on-color=\"success\"\n                       data-off-color=\"danger\"\n                       onchange=\"moduleLogToggle()\"\n                       ";
echo WHMCS\Config\Setting::getValue("ModuleDebugMode") ? "checked=\"checked\"" : "";
echo "                >\n                <input type=\"submit\" class=\"btn btn-default\" id=\"btnResetModuleLog\" value=\"";
echo AdminLang::trans("system.resetdebuglogging");
echo "\" />\n            </form>\n        </li>\n        <li role=\"presentation\">\n            <div class=\"visible-xs\">&nbsp;</div>\n            <a id=\"tabModuleLogSearch\" data-toggle=\"tab\" href=\"#contentSearch\" role=\"tab\">\n                ";
echo AdminLang::trans("global.searchfilter");
echo "            </a>\n        </li>\n    </ul>\n    <div class=\"tab-content\">\n        <div class=\"tab-pane\" id=\"contentSearch\">\n            <form action=\"";
echo routePath("admin-logs-module-log");
echo "\" method=\"POST\">\n                <div class=\"search-bar\" style=\"margin-top: 0;\">\n                    <div class=\"simple\">\n                        <div class=\"hidden-xs search-icon\">\n                            <div class=\"icon-wrapper\">\n                                <i class=\"fas fa-search\"></i>\n                            </div>\n                        </div>\n                        <div class=\"search-fields\">\n                            <div class=\"row\">\n                                <div class=\"col-xs-12 col-sm-4 col-md-3 col-lg-2\">\n                                    <div class=\"form-group\">\n                                        <label for=\"date\">";
echo AdminLang::trans("fields.date");
echo "</label>\n                                        <input type=\"text\" name=\"date\" id=\"inputDate\" class=\"form-control date-picker-single\" style=\"max-width: none;\" value=\"";
echo $search["date"];
echo "\">\n                                    </div>\n                                </div>\n                                <div class=\"col-xs-12 col-sm-4 col-md-3 col-lg-2\">\n                                    <div class=\"form-group\">\n                                        <label for=\"module\">";
echo AdminLang::trans("fields.module");
echo "</label>\n                                        <input type=\"text\" name=\"module\" id=\"inputModule\" class=\"form-control\" value=\"";
echo $search["module"];
echo "\">\n                                    </div>\n                                </div>\n                                <div class=\"col-xs-12 col-sm-4 col-md-3 col-lg-2\">\n                                    <div class=\"form-group\">\n                                        <label for=\"action\">";
echo AdminLang::trans("fields.action");
echo "</label>\n                                        <input type=\"text\" name=\"action\" id=\"inputAction\" class=\"form-control\" value=\"";
echo $search["action"];
echo "\">\n                                    </div>\n                                </div>\n                                <div class=\"col-xs-12 col-sm-4 col-md-3 col-lg-2\">\n                                    <div class=\"form-group\">\n                                        <label for=\"request\">";
echo AdminLang::trans("fields.request");
echo "</label>\n                                        <input type=\"text\" name=\"request\" id=\"inputRequest\" class=\"form-control\" value=\"";
echo $search["request"];
echo "\">\n                                    </div>\n                                </div>\n                                <div class=\"col-xs-12 col-sm-4 col-md-3 col-lg-2\">\n                                    <div class=\"form-group\">\n                                        <label for=\"response\">";
echo AdminLang::trans("fields.response");
echo "</label>\n                                        <input type=\"text\" name=\"response\" id=\"inputResponse\" class=\"form-control\" value=\"";
echo $search["response"];
echo "\">\n                                    </div>\n                                </div>\n                                <div class=\"col-xs-12 col-sm-2 col-md-2\">\n                                    <label class=\"hidden-xs clear-search\">\n                                        &nbsp;\n                                    </label>\n                                    <button type=\"submit\" id=\"btnSearchLog\" class=\"btn btn-primary btn-sm btn-search btn-block\">\n                                        <i class=\"fas fa-search fa-fw\"></i>\n                                        <span>";
echo AdminLang::trans("global.search");
echo "</span>\n                                    </button>\n                                </div>\n                            </div>\n                        </div>\n                    </div>\n                </div>\n            </form>\n        </div>\n\n    </div>\n</div>\n<div class=\"row\" style=\"margin: 10px 0px\">\n    ";
echo $totalEntries . " " . AdminLang::trans("global.recordsfound");
echo ", ";
echo AdminLang::trans("global.page") . " " . $page . " " . AdminLang::trans("global.of") . " " . $totalPages;
echo ".\n    <table class=\"datatable\" id=\"tblModuleLog\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n        <thead>\n            <th width=\"120\">";
echo AdminLang::trans("fields.date");
echo "</th>\n            <th width=\"120\">";
echo AdminLang::trans("fields.module");
echo "</th>\n            <th width=\"150\">";
echo AdminLang::trans("fields.action");
echo "</th>\n            <th>";
echo AdminLang::trans("fields.request");
echo "</th>\n            <th>";
echo AdminLang::trans("fields.response");
echo "</th>\n        </thead>\n        <tbody>\n        ";
if (!empty($moduleLogData)) {
    foreach ($moduleLogData as $record) {
        $date = WHMCS\Carbon::parse($record->date)->toAdminDateTimeFormat();
        $request = htmlentities($record->request);
        $response = htmlentities($record->arrdata ?: $record->response);
        $routePath = routePath("admin-logs-module-log-single-view", $record->id);
        echo "<tr>\n    <td>\n        <a href=\"" . $routePath . "\">" . $date . "</a>\n    </td>\n    <td>" . $record->module . "</td>\n    <td>" . $record->action . "</td>\n    <td><textarea rows=\"5\" class=\"form-control\">" . $request . "</textarea></td>\n    <td><textarea rows=\"5\" class=\"form-control\">" . $response . "</textarea></td>\n</tr>";
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
echo "        </ul>\n    </nav>\n</div>\n\n<script type=\"text/javascript\">\n    jQuery(document).on('click', '#btnResetModuleLog', function (e) {\n        e.preventDefault();\n        jQuery('#doClearModuleLog').modal('show');\n    }).on('click', '#doClearModuleLog-ok', function (e) {\n        e.preventDefault();\n        jQuery('#frmModuleLogging').submit();\n    });\n\n    function moduleLogToggle() {\n        var checked = \$('#enableModuleLogging').prop('checked'),\n            enabled = 0;\n\n        if (checked) {\n            enabled = 1;\n        }\n\n        WHMCS.http.jqClient.jsonPost({\n            url: \"";
echo routePath("admin-logs-module-log-enable-disable");
echo "\",\n            data: {\n                token: csrfToken,\n                enabled: enabled\n            },\n            success: function(data) {\n                if (data.successMsg) {\n                    jQuery.growl.notice({ title: data.successMsgTitle, message: data.successMsg });\n                }\n                if (data.errorMsg) {\n                    jQuery.growl.warning({title: data.errorMsgTitle, message: data.errorMsg});\n                }\n            },\n            error: function(data) {\n                jQuery.growl.warning(\n                    {\n                        title: '";
echo WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("global.unexpectedError"));
echo "',\n                        message: data\n                    }\n                );\n            }\n        });\n    }\n</script>\n";
echo WHMCS\View\Helper::confirmationModal("doClearModuleLog", AdminLang::trans("global.deleteConfirmation", [":itemToDelete" => AdminLang::trans("system.moduledebuglog")]));
