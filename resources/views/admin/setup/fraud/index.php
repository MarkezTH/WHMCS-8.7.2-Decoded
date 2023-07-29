<?php

$validationCom = new WHMCS\User\Validation\ValidationCom\ValidationCom();
$isValComEnabled = $validationCom->isEnabled();
$isValComeSignedUp = $validationCom->isSignedUp();
$enabledFraudProvider = WHMCS\Database\Capsule::table("tblfraud")->where([["setting", "Enable"], ["value", "!=", ""]])->value("fraud");
if (!empty($success)) {
    echo infoBox(AdminLang::trans("fraud.changesuccess"), AdminLang::trans("fraud.changesuccessinfo"));
}
if (!isset($module)) {
    $module = ["friendlyName" => ""];
}
echo "<p>";
echo AdminLang::trans("fraud.info");
echo "</p>\n\n<div class=\"signin-apps-container\">\n    <div class=\"row\">\n        <div class=\"col-lg-4\">\n            <div><h1>";
echo AdminLang::trans("fraud.verificationProviders");
echo "</h1></div>\n            <div class=\"app\">\n                <div class=\"logo-container\">\n                    <img class=\"valcom_logo\" src=\"../assets/img/fraud/validationcom.png\"></span>\n                </div>\n                <h2>Validation.com</h2>\n                <p>";
echo AdminLang::trans("validationCom.tagline");
echo "</p>\n                <a href=\"";
echo routePath("admin-validation_com-configure");
echo "\"\n                   id=\"btnConfigureValidation\"\n                   class=\"btn ";
echo $isValComEnabled ? "btn-default" : "btn-success";
echo " open-modal\"\n                    ";
if (!$isValComeSignedUp) {
    echo "style=\"display: none\" ";
}
echo "                   data-modal-title=\"";
echo AdminLang::trans("global.configure");
echo " Validation.com\"\n                   data-modal-class=\"validationConfigModal\"\n                   data-btn-submit-id=\"btnSaveValidationConfiguration\"\n                   data-btn-submit-label=\"";
echo AdminLang::trans("global.save");
echo "\"\n                >\n                    ";
echo $isValComEnabled ? AdminLang::trans("global.configure") : AdminLang::trans("global.enable");
echo "                </a>\n                <a href=\"#\"\n                   id=\"btnSignupValidation\"\n                   class=\"btn btn-success\"\n                   ";
if ($isValComeSignedUp) {
    echo "style=\"display: none\" ";
}
echo "                   onclick=\"validationComSignup();return false;\"\n                >\n                    <i class=\"fas fa-spinner fa-spin\" style=\"display: none\"></i>\n                    ";
echo AdminLang::trans("global.activate");
echo "                </a>\n            </div>\n        </div>\n        <div class=\"col-lg-8\">\n            <div class=\"row\">\n                <div><h1>";
echo AdminLang::trans("fraud.fraudProviders");
echo "</h1></div>\n                ";
foreach ($fraudModules as $fraudModule) {
    echo "                    <div class=\"col-sm-6\">\n                        <div class=\"app\">\n                            <div class=\"logo-container\">\n                                <img src=\"../assets/img/fraud/";
    echo $fraudModule;
    echo ".png\">\n                            </div>\n                            <h2 style=\"text-transform: capitalize;\">";
    echo $fraudModule;
    echo "</h2>\n                            ";
    if (in_array($fraudModule, ["maxmind", "fraudlabs"])) {
        echo "                                <p>";
        echo AdminLang::trans($fraudModule . ".tagline");
        echo "</p>\n                            ";
    }
    echo "                            <a href=\"#\" id=\"btnConfigure-";
    echo $fraudModule;
    echo "\" class=\"btn btn-";
    echo $fraudModule == $enabledFraudProvider ? "default" : "success";
    echo "\" data-modal-title=\"Configure ";
    echo $module["friendlyName"];
    echo "\" data-toggle=\"modal\" data-target=\"#";
    echo $fraudModule;
    echo "Modal\">";
    echo $fraudModule == $enabledFraudProvider ? AdminLang::trans("global.configure") : AdminLang::trans("global.activate");
    echo "</a>\n                        </div>\n                    </div>\n                ";
}
echo "            </div>\n        </div>\n    </div>\n</div>\n\n<div class=\"fraud-protection-faq\">\n    <div class=\"panel panel-default\">\n        <div class=\"panel-heading\">";
echo AdminLang::trans("fraud.whatIsVerificationQ");
echo "</div>\n        <div class=\"panel-body\">";
echo AdminLang::trans("fraud.whatIsVerificationA");
echo "</div>\n    </div>\n    <div class=\"panel panel-default\">\n        <div class=\"panel-heading\">";
echo AdminLang::trans("fraud.whatIsFraudQ");
echo "</div>\n        <div class=\"panel-body\">";
echo AdminLang::trans("fraud.whatIsFraudA");
echo "</div>\n    </div>\n</div>\n\n<div id=\"validationModal\" class=\"modal fade\" role=\"dialog\">\n    <div class=\"modal-dialog modal-lg\">\n        <div class=\"modal-content\">\n            <div class=\"modal-body top-margin-10\">\n                <iframe id=\"validationContent\" allow=\"camera ";
echo $validationCom->getSubmitHost();
echo "\" width=\"100%\" height=\"600\" frameborder=\"0\"></iframe>\n            </div>\n            <div class=\"modal-footer\">\n                <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">";
echo AdminLang::trans("global.close");
echo "</button>\n            </div>\n        </div>\n    </div>\n</div>\n\n";
foreach ($fraudModules as $fraudModule) {
    $fraudObject->load($fraudModule);
    $configArray = $fraudObject->call("getConfigArray");
    $configValues = $fraudObject->getSettings();
    echo "<form method=\"post\" action=\"configfraud.php?action=save\">\n<div id=\"";
    echo $fraudModule;
    echo "Modal\" class=\"modal whmcs-modal fade\" role=\"dialog\" aria-hidden=\"true\">\n    <div class=\"modal-dialog\">\n        <div class=\"modal-content panel panel-primary\">\n            <div class=\"modal-header panel-heading\">\n                <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"";
    echo AdminLang::trans("close");
    echo "\">\n                    <span aria-hidden=\"true\">×</span>\n                </button>\n                <h4 class=\"modal-title\">\n                    ";
    echo ($fraudModule == $enabledFraudProvider ? AdminLang::trans("global.configure") : AdminLang::trans("global.activate")) . " " . ucfirst($fraudModule);
    echo "                </h4>\n            </div>\n            <div class=\"modal-body panel-body fraud-provider-form\">\n                <input type=\"hidden\" name=\"fraud\" value=\"";
    echo $fraudModule;
    echo "\">\n                <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n                    ";
    foreach ($configArray as $regConfOption => $regConfValues) {
        $regConfValues["FriendlyName"] = $regConfValues["FriendlyName"] ?? $regConfOption;
        $regConfValues["Name"] = $regConfOption;
        $regConfValues["Value"] = $configValues[$regConfOption] ?? NULL;
        $fieldArea = moduleConfigFieldOutput($regConfValues);
        echo "                        <tr>\n                            <td class=\"fieldlabel\">\n                                ";
        echo $regConfValues["FriendlyName"];
        echo "                            </td>\n                            <td class=\"fieldarea\">\n                                ";
        echo $fieldArea;
        echo "                            </td>\n                        </tr>\n                    ";
    }
    echo "                </table>\n            </div>\n            <div class=\"modal-footer panel-footer\">\n                <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">\n                    ";
    echo AdminLang::trans("global.cancelchanges");
    echo "                </button>\n                <button type=\"submit\" class=\"btn btn-primary\">\n                    ";
    echo AdminLang::trans("global.savechanges");
    echo "                </button>\n            </div>\n        </div>\n    </div>\n</div>\n</form>\n";
}
echo "\n<script>\n    var loginPopup;\n\n    \$(document).ready(function () {\n        showSpinnerWhileIFrameLoads();\n    });\n\n    function showSpinnerWhileIFrameLoads() {\n        var iframe = \$('#validationContent');\n        if (iframe.length) {\n            \$(iframe).before('<div class=\"valComSpinnerOverlay\"><div class=\"valComSpinner\"><i class=\"fa fa-spinner fa-spin fa-5x fa-fw\"></i></div></div>');\n            \$(iframe).on('load', function () {\n                jQuery('.valComSpinnerOverlay').hide();\n            });\n        }\n    }\n\n    function validationComSignup() {\n        var validationModal = jQuery('#validationModal'),\n            iframeSrc = validationModal.find('.modal-body iframe').attr('src'),\n            errorMessage = null;\n\n        if ((typeof iframeSrc !== 'undefined' && iframeSrc !== false)) {\n            validationModal.modal('show');\n            return false;\n        }\n\n        \$('#btnSignupValidation')\n            .attr('disabled', 'disabled')\n            .find('.fa-spinner')\n            .show();\n\n        WHMCS.http.jqClient.post(\n            '";
echo routePath("admin-validation_com-signup");
echo "',\n            {\n                token: '";
echo generate_token("plain");
echo "'\n            }\n        ).done(function(response) {\n            if (response.success === false) {\n                errorMessage = response.status;\n                return false;\n            }\n            if (response.display === 'popup') {\n                loginPopup = window.open(\n                    response.location,\n                    '_blank',\n                    'width=800,height=600,top=100,left=100,scrollbars=yes,toolbar=no'\n                );\n\n                loginPopup.focus();\n\n                return false;\n            }\n            validationModal.find('.modal-body iframe').attr('src', response.location);\n            validationModal.modal('show');\n        }).error(function() {\n            errorMessage = '";
echo AdminLang::trans("global.unexpectedError");
echo "';\n        }).always(function() {\n            \$('#btnSignupValidation')\n                .removeAttr('disabled')\n                .find('.fa-spinner')\n                .hide();\n            if (errorMessage != null) {\n                jQuery.growl.error({title: '', message: errorMessage});\n            }\n        });\n    }\n\n    function completeValidationComLinkWorkflow() {\n        if (loginPopup) {\n            loginPopup.close();\n        } else {\n            \$('#validationModal').modal('hide');\n        }\n\n        \$('#btnSignupValidation').hide();\n\n        \$('#btnConfigureValidation').show().removeClass('btn-success')\n            .addClass('btn-default')\n            .text('";
echo AdminLang::trans("global.configure");
echo "');\n\n        jQuery.growl.notice({\n            title: '";
echo AdminLang::trans("global.success");
echo "',\n            message: '";
echo AdminLang::trans("validationCom.linkSuccess");
echo "'\n        });\n    }\n</script>\n";
