<?php

echo "<div class=\"alert alert-danger admin-modal-error\" style=\"display: none\"></div>\n<form class=\"form-horizontal\" id=\"frmSecurityQuestion\" method=\"post\" action=\"";
echo routePath("admin-client-user-manage-save", $client->id, $user->id);
echo "\">\n    ";
echo generate_token();
echo "    <div class=\"admin-tabs-v2\">\n        <div class=\"tab-content\">\n            <div class=\"tab-pane active\">\n                <div class=\"form-group\">\n                    <label for=\"inputUser\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
echo AdminLang::trans("fields.user");
echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        ";
echo $user->fullName;
echo "<br>\n                        <small>";
echo $user->email;
echo "</small>\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"inputQuestion\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
echo AdminLang::trans("fields.securityquestion");
echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        <input type=\"text\"\n                               class=\"form-control\"\n                               readonly=\"readonly\"\n                               value=\"";
echo $user->getSecurityQuestion();
echo "\"\n                        >\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"inputAnswer\" class=\"col-md-4 col-sm-6 control-label\">\n                        ";
echo AdminLang::trans("fields.securityanswer");
echo "                    </label>\n                    <div class=\"col-md-8 col-sm-6\">\n                        <input type=\"text\"\n                               class=\"form-control\"\n                               readonly=\"readonly\"\n                               value=\"";
echo $user->securityQuestionAnswer;
echo "\"\n                        >\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</form>\n";
