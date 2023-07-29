<?php

echo "<div class=\"alert alert-danger admin-modal-error\" style=\"display: none\"></div>\n<form class=\"form-horizontal\" id=\"frmManageUser\" method=\"post\" action=\"";
echo routePath("admin-user-manage-save", $user->id);
echo "\">\n    ";
echo generate_token();
echo "    <div class=\"admin-tabs-v2\">\n        <div class=\"tab-content\">\n            <div class=\"tab-pane active\">\n                ";
$this->insert("user/partials/user-details");
echo "                ";
if (DI::make("userValidation")->isEnabled()) {
    echo "                    <div class=\"form-group\">\n                        <label class=\"col-md-2 col-sm-4 control-label\">\n                            ";
    echo AdminLang::trans("validationCom.validationStatus");
    echo "                        </label>\n                        <div class=\"col-sm-8 col-md-10\">\n                            <div class=\"validation-container-user\">\n                                ";
    echo view("admin.orders.validation.results", ["validationUser" => $user]);
    echo "                            </div>\n                        </div>\n                    </div>\n                ";
}
echo "                <div class=\"form-group\">\n                    <label for=\"permissions\" class=\"col-md-2 col-sm-4 control-label\">\n                        ";
echo AdminLang::trans("fields.accounts");
echo "                    </label>\n                    <div class=\"col-md-10 col-sm-8\">\n                        <table class=\"table table-responsive table-striped\">\n                            <thead>\n                            <tr>\n                                <th width=\"20px;\">";
echo AdminLang::trans("fields.id");
echo "</th>\n                                <th>";
echo AdminLang::trans("fields.clientname");
echo "</th>\n                                <th>";
echo AdminLang::trans("fields.companyname");
echo "</th>\n                                <th>";
echo AdminLang::trans("fields.owner");
echo "</th>\n                            </tr>\n                            </thead>\n                            <tbody>\n                            ";
foreach ($user->clients as $client) {
    echo "                                <tr>\n                                    <td>\n                                        <a href=\"";
    echo $assetHelper->getWebRoot() . "/" . $client->getLink();
    echo "\">\n                                            ";
    echo $client->id;
    echo "                                        </a>\n                                    </td>\n                                    <td>\n                                        <a href=\"";
    echo $assetHelper->getWebRoot() . "/" . $client->getLink();
    echo "\">\n                                            ";
    echo $client->fullName;
    echo "                                        </a>\n                                    </td>\n                                    <td>";
    echo $client->companyName;
    echo "</td>\n                                    <td>";
    echo $client->isOwnedBy($user) ? $ownerFA : "";
    echo "</td>\n                                </tr>\n                            ";
}
if (count($user->clients) === 0) {
    echo "                                <tr>\n                                    <td colspan=\"4\">\n                                        ";
    echo AdminLang::trans("user.noAccounts");
    echo "                                    </td>\n                                </tr>\n                            ";
}
echo "                            </tbody>\n                        </table>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</form>\n<script type=\"text/javascript\">\n    jQuery(document).ready(function() {\n        generateBootstrapSwitches();\n        ";
$deleteText = AdminLang::trans("user.permanentlyDelete");
$deleteTooltip = AdminLang::trans("user.deleteTooltip");
if (count($user->clients) === 0) {
    echo "\$('#modalAjaxLoader').before('<div id=\"divPermDeleteButton\" class=\"pull-left\"><button class=\"btn btn-danger btn-delete-user pull-right\" data-delete-id=\"" . $user->id . "\">" . $deleteText . "</button></div>');\n\$('.delete-user').off('click').on('click', function() {\n    \$('#divPermDeleteButton').remove();\n});";
} else {
    echo "\$('#modalAjaxLoader').before('<div id=\"divPermDeleteButtonDisabled\" class=\"pull-left tooltip-wrapper\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . $deleteTooltip . "\"><a class=\"btn btn-danger disabled delete-user pull-right\" data-role=\"btn-delete-user\" disabled=\"disabled\">" . $deleteText . "</a></div>');\n\$('.tooltip-wrapper').tooltip({position: \"bottom\"});";
}
echo "\n        jQuery('#modalAjax').on('hidden.bs.modal', function (e) {\n            jQuery('#divPermDeleteButton').remove();\n            jQuery('#divPermDeleteButtonDisabled').remove();\n        });\n    });\n</script>\n";
