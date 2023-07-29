<?php

echo "<div class=\"form-group\">\n    <label for=\"inputFirstName\" class=\"col-md-2 col-sm-4 control-label\">\n        ";
echo AdminLang::trans("fields.firstname");
echo "    </label>\n    <div class=\"col-md-10 col-sm-8\">\n        <input type=\"text\"\n               id=\"inputFirstName\"\n               name=\"first_name\"\n               class=\"form-control\"\n               value=\"";
echo $user->firstName;
echo "\"\n        >\n        <div class=\"field-error-msg\">\n            ";
echo AdminLang::trans("validation.required", [":attribute" => AdminLang::trans("fields.firstname")]);
echo "        </div>\n    </div>\n</div>\n<div class=\"form-group\">\n    <label for=\"inputLastName\" class=\"col-md-2 col-sm-4 control-label\">\n        ";
echo AdminLang::trans("fields.lastname");
echo "    </label>\n    <div class=\"col-md-10 col-sm-8\">\n        <input type=\"text\"\n               id=\"inputLastName\"\n               name=\"last_name\"\n               class=\"form-control\"\n               value=\"";
echo $user->lastName;
echo "\"\n        >\n        <div class=\"field-error-msg\">\n            ";
echo AdminLang::trans("validation.required", [":attribute" => AdminLang::trans("fields.lastname")]);
echo "        </div>\n    </div>\n</div>\n<div class=\"form-group\">\n    <label for=\"inputEmail\" class=\"col-md-2 col-sm-4 control-label\">\n        ";
echo AdminLang::trans("fields.email");
echo "    </label>\n    <div class=\"col-md-10 col-sm-8\">\n        <input type=\"text\"\n               id=\"inputEmail\"\n               name=\"email\"\n               class=\"form-control\"\n               value=\"";
echo $user->email;
echo "\"\n        >\n        <div class=\"field-error-msg\">\n            ";
echo AdminLang::trans("validation.required", [":attribute" => AdminLang::trans("fields.email")]);
echo "        </div>\n    </div>\n</div>\n<div class=\"form-group\">\n    <label for=\"inputLanguage\" class=\"col-md-2 col-sm-4 control-label\">\n        ";
echo AdminLang::trans("fields.language");
echo "    </label>\n    <div class=\"col-md-10 col-sm-8\">\n        <select id=\"inputLanguage\" name=\"language\" class=\"form-control\">\n            <option value=\"\" ";
echo !$user->language ? "selected=\"selected\"" : "";
echo ">\n                ";
echo AdminLang::trans("global.default");
echo "            </option>\n            ";
foreach ($clientLanguages as $lang) {
    $language = $user->language;
    $selected = "";
    $ufLang = ucfirst($lang);
    if ($language && $lang == WHMCS\Language\ClientLanguage::getValidLanguageName($language)) {
        $selected = " selected=\"selected\"";
    }
    echo "<option value=\"" . $lang . "\"" . $selected . ">" . $ufLang . "</option>";
}
echo "        </select>\n    </div>\n</div>\n\n<div class=\"form-group\">\n    <label class=\"col-md-2 col-sm-4 control-label\">\n        ";
echo AdminLang::trans("clients.2faenabled");
echo "    </label>\n    <div class=\"col-md-10 col-sm-8\" style=\"top: 15px;\">\n        <input type=\"hidden\" name=\"twoFactor\" value=\"0\" />\n        <input type=\"checkbox\" name=\"twoFactor\" value=\"1\"";
echo $user->hasTwoFactorAuthEnabled() ? " checked" : " disabled";
echo " id=\"twoFactor\" class=\"slide-toggle-mini\">\n    </div>\n</div>\n\n";
if ($user->hasSecurityQuestion()) {
    echo "    <div class=\"form-group\">\n        <label class=\"col-md-2 col-sm-4 control-label\">\n            ";
    echo AdminLang::trans("global.disable") . " " . AdminLang::trans("fields.securityquestion");
    echo "        </label>\n        <div class=\"col-md-10 col-sm-8\" style=\"top: 15px;\">\n            <input type=\"hidden\" name=\"disableSecurityQuestion\" value=\"0\" />\n            <input type=\"checkbox\"\n               name=\"disableSecurityQuestion\"\n               value=\"1\"\n               id=\"securityQuestion\"\n               class=\"slide-toggle-mini\"\n               data-on-text=\"";
    echo strtoupper(AdminLang::trans("global.yes"));
    echo "\"\n               data-off-text=\"";
    echo strtoupper(AdminLang::trans("global.no"));
    echo "\"\n            >\n            <br />\n            <small id=\"securityQuestionHelp\" class=\"form-text text-muted\">\n                ";
    echo AdminLang::trans("user.disableSecurityQuestionHelp");
    echo "            </small>\n        </div>\n    </div>\n    ";
}
echo "\n<script type=\"text/javascript\">\n    jQuery(document).ready(function() {\n        generateBootstrapSwitches();\n    });\n</script>\n";
