<?php

echo "<form id=\"frmUsersSearch\" method=\"post\" action=\"";
echo routePath("admin-user-search");
echo "\">\n    <div class=\"search-bar\" id=\"search-bar\">\n        <div class=\"simple\">\n            <div class=\"search-icon\">\n                <div class=\"icon-wrapper\">\n                    <i class=\"fas fa-search\"></i>\n                </div>\n            </div>\n            <div class=\"search-fields\">\n                <div class=\"row\">\n                    <div class=\"col-xs-12 col-sm-10\">\n                        <div class=\"form-group\">\n                            <label for=\"inputName\">\n                                ";
echo AdminLang::trans("searchOptions.userNameEmail");
echo "                            </label>\n                            <input type=\"text\" name=\"criteria\" id=\"inputCriteria\" class=\"form-control\"\n                                   value=\"";
echo e($searchCriteria["criteria"] ?? "");
echo "\">\n                        </div>\n                    </div>\n                    <div class=\"col-xs-12 col-sm-2\">\n                        <label class=\"clear-search\">\n                            &nbsp;\n                            <a\n                                href=\"";
echo routePath("admin-user-list");
echo "\"\n                                class=\"";
echo !$searchActive ? " hidden" : "";
echo "\"\n                                title=\"";
echo AdminLang::trans("searchOptions.reset");
echo "\">\n                                <i class=\"fas fa-times fa-fw\"></i>\n                            </a>\n                        </label>\n                        <button type=\"submit\" id=\"btnSearchUsers\" class=\"btn btn-primary btn-sm btn-search btn-block\">\n                            <i class=\"fas fa-search fa-fw\"></i>\n                            <span class=\"hidden-md\">\n                                ";
echo AdminLang::trans("global.search");
echo "                            </span>\n                        </button>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</form>\n\n";
echo $tableOutput;
echo "<script>\n    jQuery(document).ready(function() {\n        jQuery(document).on('click', 'a.manage-user', function() {\n            jQuery(this).closest('tr').find('button.manage-user').click();\n        });\n    });\n</script>\n";
$this->insert("user/partials/confirmation-modals");
