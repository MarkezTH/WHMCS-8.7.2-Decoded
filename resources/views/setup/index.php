<?php

echo "<div class=\"slide-tray right setup-tasks\" id=\"setupTasksDrawer\">\n    <div class=\"inner-container\">\n        <h2>\n            ";
echo AdminLang::trans("setup.tasks");
echo "            <button type=\"button\" class=\"close\" data-dismiss=\"slide-tray\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>\n        </h2>\n\n        <p>\n            ";
echo AdminLang::trans("setup.tasksProgressSummary", [":completed" => $completedTaskCount, ":total" => $totalTaskCount]);
echo "        </p>\n\n        <ul>\n            ";
foreach ($setupTasks as $values) {
    echo "                <li>\n                    <a href=\"";
    echo $values["link"];
    echo "\">\n                        <i class=\"fas fa-";
    echo $values["completed"] ? "check" : "times";
    echo " fa-fw\"></i>\n                        ";
    echo $values["label"];
    echo "                    </a>\n                </li>\n            ";
}
echo "        </ul>\n\n        <p>\n            <a href=\"https://docs.whmcs.com/Setup_Tasks\" target=\"_blank\">\n                ";
echo AdminLang::trans("global.learnMore");
echo "                <i class=\"fas fa-external-link-square\"></i>\n            </a>\n        </p>\n\n    </div>\n</div>\n\n<div class=\"theme-header\">\n    <h1>";
echo AdminLang::trans("setup.title");
echo "</h1>\n    <p class=\"lead\">";
echo AdminLang::trans("setup.systemSettingsTagline");
echo ".</p>\n    <div class=\"setup-tasks-banner\">\n        <a href=\"#\" data-toggle=\"slide-tray\" data-target=\"#setupTasksDrawer\">";
echo AdminLang::trans("setup.tasksClickToView");
echo "</a>\n        <div class=\"progress\">\n          <div class=\"progress-bar progress-bar-success\" role=\"progressbar\" aria-valuenow=\"";
echo $setupTaskPercent;
echo "\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: ";
echo $setupTaskPercent;
echo "%\">\n            <span class=\"sr-only\">";
echo $setupTaskPercent;
echo "%</span>\n          </div>\n        </div>\n        <div style=\"display:inline-block;width:40px;text-align:right;\">\n            ";
echo $setupTaskPercent;
echo "%\n        </div>\n    </div>\n</div>\n\n<div class=\"system-settings-index\">\n    <div class=\"left-col\">\n        <div class=\"filter-container\">\n            <i class=\"fas fa-search\"></i>\n            <input type=\"text\" id=\"inputFilter\" class=\"form-control\" placeholder=\"";
echo AdminLang::trans("global.search");
echo "\" autofocus>\n            <i id=\"btnClearFilter\" class=\"fas fa-times\"></i>\n        </div>\n        <ul class=\"setup-side-menu\">\n            ";
foreach ($categories as $key => $category) {
    echo "                <li";
    echo $key == 0 ? " class=\"active\"" : "";
    echo "><a href=\"#\" data-display-title=\"";
    echo $category["displayTitle"] ?? "";
    echo "\" data-category=\"";
    echo $category["category"];
    echo "\">";
    echo $category["title"];
    echo "</a></li>\n            ";
}
echo "        </ul>\n\n        ";
if (0 < count($recentlyVisited)) {
    echo "            <div class=\"recently-visited\">\n                <h3>";
    echo AdminLang::trans("global.recentlyVisited");
    echo "</h3>\n                <ol>\n                    ";
    foreach ($recentlyVisited as $page) {
        echo "                        <li><a href=\"";
        echo $page["href"];
        echo "\">";
        echo $page["title"];
        echo "</a></li>\n                    ";
    }
    echo "                </ol>\n            </div>\n        ";
}
echo "    </div>\n    <div class=\"right-col\">\n        <select class=\"form-control select-inline custom-select pull-right\" id=\"inputSort\" style=\"margin-top:-8px;\">\n            <option value=\"pop\">";
echo AdminLang::trans("fields.popularity");
echo "</option>\n            <option value=\"asc\">";
echo AdminLang::trans("fields.name");
echo " (";
echo AdminLang::trans("global.asc");
echo ")</option>\n            <option value=\"dsc\">";
echo AdminLang::trans("fields.name");
echo " (";
echo AdminLang::trans("global.desc");
echo ")</option>\n        </select>\n        <h2 class=\"setup-category-title\" data-search-label=\"";
echo addslashes(AdminLang::trans("global.searchResultsFor"));
echo "\">";
echo AdminLang::trans("setup.categories.all");
echo "</h2>\n        <div class=\"hidden\" id=\"noSearchResults\">\n            ";
echo AdminLang::trans("search.noResultsFound");
echo "        </div>\n        <div class=\"setup-links-container\">\n            ";
foreach ($links as $k => $link) {
    echo "                <div class=\"setting-col\" data-category=\"";
    echo $link["category"];
    echo "\" data-weighting=\"";
    echo $k;
    echo "\">\n                    <a href=\"";
    echo $link["link"];
    echo "\" id=\"";
    echo $link["id"];
    echo "\" class=\"setting\">\n                        <div class=\"icon\"><i class=\"";
    echo $link["icon"];
    echo " fa-fw\"></i></div>\n                        <div class=\"content\">\n                            <span class=\"title\">\n                                ";
    if (isset($link["image"])) {
        echo "                                    <img src=\"";
        echo $link["image"];
        echo "\" class=\"pull-right\">\n                                ";
    }
    echo "                                ";
    echo $link["title"];
    echo "                                ";
    if (isset($link["badge"])) {
        echo "                                    ";
        echo $link["badge"];
        echo "                                ";
    }
    echo "                            </span>\n                            <span class=\"desc\">";
    echo $link["description"] ? $link["description"] : "...";
    echo "</span>\n                        </div>\n                    </a>\n                </div>\n            ";
}
echo "        </div>\n    </div>\n</div>\n\n";
echo $highlightAssetInclude;
echo "<script>\n\$(document).ready(function(){\n    \$.extend(\$.expr[\":\"], {\n        \"caseInsensitiveContains\": function(elem, i, match) {\n            return (elem.textContent || elem.innerText || \"\").toLowerCase().indexOf((match[3] || \"\").toLowerCase()) >= 0;\n        }\n    });\n    \$('#inputFilter').keyup(function() {\n        var searchTerm = \$(this).val();\n        if (!searchTerm) {\n            return;\n        }\n        if (\$('.setup-side-menu li.active').data('category') != 'all') {\n            \$('.setup-side-menu li:first-child a').click();\n        }\n        \$('.system-settings-index .setup-category-title').text(\$('.system-settings-index .setup-category-title').data('search-label') + ' \"' + searchTerm + '\"');\n        \$('.setup-links-container .setting-col')\n            .hide()\n            .removeHighlight()\n            .filter('.setting-col')\n            .filter(':caseInsensitiveContains(\"' + searchTerm + '\")')\n            .highlight(searchTerm)\n            .show();\n        if (\$('.setup-links-container .setting-col:visible').length > 0) {\n            \$('#noSearchResults').hide();\n        } else {\n            \$('#noSearchResults').removeClass('hidden').show();\n        }\n        if (searchTerm.length > 0) {\n            \$(\"#btnClearFilter\").fadeIn();\n        } else {\n            \$(\"#btnClearFilter\").fadeOut();\n        }\n    });\n    \$('#btnClearFilter').click(function() {\n        \$('#noSearchResults').hide();\n        \$('.setup-side-menu li:first-child a').click();\n        \$(\"#inputFilter\").val('').focus();\n        \$(\".setup-links-container .setting-col\").removeHighlight().show();\n        \$(\"#btnClearFilter\").fadeOut();\n    });\n    \$('.setup-side-menu a').click(function(e) {\n        e.preventDefault();\n        let displayTitle = \$(this).data('display-title') ? \$(this).data('display-title') : \$(this).text();\n        \$('.system-settings-index .setup-category-title').text(displayTitle);\n        \$('.setup-side-menu li').removeClass('active');\n        \$(this).closest('li').addClass('active');\n        if (\$(this).data('category') == 'all') {\n            \$(\".setup-links-container .setting-col\").show();\n        } else {\n            \$('.setup-links-container .setting-col')\n                .hide()\n                .filter('.setting-col[data-category=\"' + \$(this).data('category') + '\"]')\n                .show();\n        }\n        localStorage.setItem('system-settings-index-category', \$(this).data('category'));\n    });\n    \$('#inputSort').change(function(e) {\n        var sortterm = \$(this).val();\n        var mylist = \$(\".setup-links-container\");\n        var listitems = mylist.children('.setting-col').get();\n        listitems.sort(function(a, b) {\n            if (sortterm == 'asc' || sortterm == 'dsc') {\n                var compA = \$(a).text().toUpperCase();\n                var compB = \$(b).text().toUpperCase();\n            } else {\n                var compA = \$(a).data('weighting');\n                var compB = \$(b).data('weighting');\n            }\n            return (compA < compB) ? -1 : (compA > compB) ? 1 : 0;\n        });\n        if (sortterm == 'dsc') {\n            \$.each(listitems, function(idx, itm) { mylist.prepend(itm); });\n        } else {\n            \$.each(listitems, function(idx, itm) { mylist.append(itm); });\n        }\n        localStorage.setItem('system-settings-index-sort', sortterm);\n    });\n    // load saved values\n    const savedSort = localStorage.getItem('system-settings-index-sort');\n    if (savedSort) {\n        \$('#inputSort').val(savedSort).change();\n    }\n    const savedCategory = localStorage.getItem('system-settings-index-category');\n    if (savedCategory) {\n        \$('.setup-side-menu a[data-category=\"' + savedCategory + '\"').click();\n    }\n});\n</script>\n";
