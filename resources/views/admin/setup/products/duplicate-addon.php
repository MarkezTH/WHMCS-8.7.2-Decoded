<?php

echo "<h2>";
echo AdminLang::trans("addons.duplicateAddon");
echo "</h2>\n<form class=\"form\" method=\"post\" action=\"configaddons.php?action=duplicateNow\">\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=150 class=\"fieldlabel\">\n                ";
echo AdminLang::trans("addons.existingAddon");
echo "            </td>\n            <td class=\"fieldarea\">\n                <select name=\"existingAddon\" class=\"form-control select-inline\">\n                    ";
if (!empty($availableAddonGroup)) {
    echo "                        <optgroup label=\"";
    echo AdminLang::trans("addons.duplicable");
    echo "\">\n                            ";
    foreach ($availableAddonGroup as $addon) {
        echo "                                <option value=\"";
        echo $addon->id;
        echo "\">";
        echo $addon->name;
        echo "</option>\n                            ";
    }
    echo "                        </optgroup>\n                        ";
}
if (!empty($unavailableAddonGroup)) {
    echo "                        <optgroup label=\"";
    echo AdminLang::trans("addons.nonDuplicable");
    echo "\">\n                            ";
    foreach ($unavailableAddonGroup as $addon) {
        echo "                                <option value=\"";
        echo $addon->id;
        echo "\" disabled=\"disabled\">";
        echo $addon->name;
        echo "</option>\n                            ";
    }
    echo "                        </optgroup>\n                    ";
}
echo "                </select>\n                ";
if (!empty($unavailableAddonGroup)) {
    echo "                    <i class=\"fas fa-exclamation-triangle hidden-xs\"\n                       data-toggle=\"tooltip\"\n                       data-container=\"body\"\n                       data-placement=\"right auto\"\n                       data-trigger=\"hover\"\n                       title=\"";
    echo AdminLang::trans("addons.nonDuplicableWarn");
    echo "\"\n                    ></i>\n                ";
}
echo "            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
echo AdminLang::trans("addons.newAddonName");
echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" class=\"form-control input-500\" name=\"newAddonName\" />\n            </td>\n        </tr>\n    </table>\n    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"Continue &raquo;\" class=\"btn btn-primary\" />\n    </div>\n</form>";
