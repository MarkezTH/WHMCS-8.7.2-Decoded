<?php

echo "<p>";
echo Lang::trans("twofadisableintro");
echo "</p>\n\n";
if ($errorMsg) {
    echo "    <div class=\"alert alert-danger\">\n        ";
    echo $errorMsg;
    echo "    </div>\n";
}
echo "\n<form class=\"form-horizontal\" method=\"post\" action=\"";
echo routePath(($isAdmin ? "admin-" : "") . "account-security-two-factor-disable-confirm");
echo "\">\n    ";
echo generate_token("form");
echo "    <div class=\"form-group\">\n        <label for=\"inputPasswordVerify\" class=\"col-sm-4 control-label font-weight-bold\">\n            ";
echo Lang::trans("twofaconfirmpw");
echo ":\n        </label>\n        <div class=\"col-sm-6\">\n            <input type=\"password\" autocomplete=\"off\" name=\"pwverify\" id=\"inputPasswordVerify\" value=\"\" class=\"form-control\" autofocus>\n        </div>\n    </div>\n</form>\n";
