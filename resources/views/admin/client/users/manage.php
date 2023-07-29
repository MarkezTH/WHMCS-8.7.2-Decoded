<?php

echo "<div class=\"alert alert-danger admin-modal-error\" style=\"display: none\"></div>\n<form class=\"form-horizontal\" id=\"frmManageUser\" method=\"post\" action=\"";
echo routePath("admin-client-user-manage-save", $client->id, $user->id);
echo "\">\n    ";
echo generate_token();
echo "    <div class=\"admin-tabs-v2\">\n        <div class=\"tab-content\">\n            <div class=\"tab-pane active\">\n                ";
$this->insert("user/partials/user-details");
echo "                <div class=\"form-group\">\n                    <label for=\"permissions\" class=\"col-md-2 col-sm-4 control-label\">\n                        ";
echo AdminLang::trans("fields.permissions");
echo "                    </label>\n                    <div class=\"col-md-10 col-sm-8\">\n                        ";
$this->insert("client/users/partials/permission-list");
echo "                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"inputMakeOwner\" class=\"col-md-2 col-sm-4 control-label\">\n                        ";
echo AdminLang::trans("user.makeOwner");
echo "                    </label>\n                    <div class=\"col-md-10 col-sm-8\">\n                        <label class=\"checkbox-inline\">\n                            <input type=\"hidden\" name=\"make_owner\" value=\"0\">\n                            <input type=\"checkbox\"\n                                   id=\"inputMakeOwner\"\n                                   name=\"make_owner\"\n                                   value=\"1\"\n                                ";
echo $user && $user->isOwner($client) ? "checked=\"checked\" disabled" : "";
echo "                            >\n                            ";
echo AdminLang::trans("user.makeOwnerDescription", [":client" => $client->displayName]);
echo "                        </label>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</form>\n<script type=\"text/javascript\">\n    jQuery(document).ready(function() {\n        generateBootstrapSwitches();\n    });\n</script>\n";
