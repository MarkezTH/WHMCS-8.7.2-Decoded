<?php

$isOwner = !empty($user) && $user->isOwner($client);
if ($isOwner) {
    echo "    <div class=\"alert alert-info\">\n        ";
    echo AdminLang::trans("user.ownerPermissions");
    echo "    </div>\n";
}
echo "<div class=\"row\" id=\"divPermissions\">\n    ";
foreach ($allPermissions as $permission) {
    echo "        <div class=\"col-md-6\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\"\n                       name=\"permission[]\"\n                       value=\"";
    echo $permission;
    echo "\"\n                        ";
    echo !empty($user) && $user->pivot->getPermissions()->hasPermission($permission) ? "checked=\"checked\"" : "";
    echo "                        ";
    echo $isOwner ? "disabled=\"disabled\"" : "";
    echo "                >\n                ";
    echo AdminLang::trans("contactpermissions.perm" . $permission);
    echo "            </label>\n        </div>\n    ";
}
echo "    <div class=\"col-md-12 field-error-msg\">\n        ";
echo AdminLang::trans("user.onePermissionRequired");
echo "    </div>\n    ";
if (!$isOwner) {
    echo "        <div class=\"col-md-12\">\n            <a href=\"#\"\n               class=\"pull-right btn-check-all";
    echo !empty($user) && count($user->pivot->getPermissions()->get()) === count($allPermissions) ? " toggle-active" : "";
    echo "\"\n               data-checkbox-container=\"divPermissions\"\n               data-btn-check-toggle=\"1\"\n               id=\"btnSelectAll-cellPermissions\"\n               data-label-text-select=\"";
    echo AdminLang::trans("global.checkall");
    echo "\"\n               data-label-text-deselect=\"";
    echo AdminLang::trans("global.uncheckAll");
    echo "\"\n            >\n                ";
    echo AdminLang::trans("global." . (!empty($user) && count($user->pivot->getPermissions()->get()) === count($allPermissions) ? "uncheckAll" : "checkall"));
    echo "            </a>\n        </div>\n    ";
}
echo "</div>\n";
