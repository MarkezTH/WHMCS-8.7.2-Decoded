<?php

echo "<div class=\"category-chooser visible-xs visible-sm\">\n    <select class=\"form-control\" id=\"inputCategoryDropdown\">\n        ";
foreach ($categories->all() as $categoryDrop) {
    echo "            <option value=\"";
    echo escape($categoryDrop->getSlug());
    echo "\" data-name=\"";
    echo escape($categoryDrop->getDisplayName());
    echo "\"";
    echo $category->getSlug() == $categoryDrop->getSlug() ? " selected" : "";
    echo ">\n                    ";
    echo escape($categoryDrop->getDisplayName());
    echo "            </option>\n        ";
}
echo "    </select>\n</div>\n\n<div class=\"row\">\n    <div class=\"col-md-3 hidden-xs hidden-sm\">\n        <ul class=\"categories-nav\">\n            <li class=\"title\">";
echo escape(AdminLang::trans("apps.categoriesTitle"));
echo "</li>\n            ";
foreach ($categories->all() as $categoryDrop) {
    echo "                <li>\n                    <a href=\"#\" data-slug=\"";
    echo escape($categoryDrop->getSlug());
    echo "\" data-name=\"";
    echo escape($categoryDrop->getDisplayName());
    echo "\" class=\"truncate ";
    echo $category->getSlug() == $categoryDrop->getSlug() ? "active" : "";
    echo "\">\n                        <i class=\"";
    echo escape($categoryDrop->getIcon());
    echo " fa-fw\"></i>\n                        ";
    echo escape($categoryDrop->getDisplayName());
    echo "                    </a>\n                </li>\n            ";
}
echo "        </ul>\n    </div>\n    <div class=\"col-md-9\">\n\n        <div class=\"app-category-title\">\n            <h2>";
echo escape($category->getDisplayName());
echo " <span>";
echo AdminLang::trans("apps.apps");
echo "</span></h2>\n            <p class=\"lead\">";
echo escape($category->getTagline());
echo "</p>\n        </div>\n\n        ";
if (!empty($hero)) {
    echo "        <div class=\"app-wrapper clearfix\">\n            <div class=\"app-category-hero\">\n                ";
    if ($hero->hasRemoteUrl()) {
        echo "                <a href=\"";
        echo urlencode($hero->getRemoteUrl());
        echo "\" target=\"_blank\" class=\"app-external-url\">\n                ";
    } else {
        if ($hero->hasTargetAppKey()) {
            echo "                <a href=\"";
            echo routePath("admin-apps-info", $hero->getTargetAppKey());
            echo "\" class=\"app-inner open-modal\" data-modal-class=\"app-info-modal\" data-modal-size=\"modal-lg\">\n                ";
        }
    }
    echo "                    <img src=\"";
    echo escape($hero->getImageUrl());
    echo "\">\n                </a>\n            </div>\n        </div>\n        ";
}
echo "\n        <div class=\"app-wrapper category-view clearfix\">\n            <h3>";
echo AdminLang::trans("apps.recommendedTitle");
echo "</h3>\n            <div class=\"apps\">\n                ";
foreach ($category->getFeaturedApps($apps) as $app) {
    $this->insert("apps/shared/app", ["app" => $app, "featuredOutput" => true]);
}
echo "            </div>\n        </div>\n\n        <div class=\"app-wrapper category-view list-view clearfix\">\n            <h3 class=\"pull-left\">";
echo AdminLang::trans("apps.additionalApps");
echo "</h3>\n            <div role=\"button\" class=\"pull-right view-btn-container\">\n                <span class=\"list-view-btn selected\"><i class=\"fas fa-bars\" title=\"";
echo AdminLang::trans("apps.listView");
echo "\"></i></span\n                ><span class=\"grid-view-btn\"><i class=\"fas fa-grip-horizontal\" title=\"";
echo AdminLang::trans("apps.gridView");
echo "\"></i></span>\n            </div>\n            <div class=\"clearfix\"></div>\n            <div class=\"apps\">\n                ";
foreach ($category->getNonFeaturedApps($apps) as $app) {
    $this->insert("apps/shared/app", ["app" => $app, "featuredOutput" => false]);
}
echo "            </div>\n        </div>\n\n    </div>\n</div>\n";
