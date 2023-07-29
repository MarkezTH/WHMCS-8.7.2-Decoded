<?php

namespace WHMCS\Admin\Wizard\Steps\ConfigureSsl;

class Csr
{
    public function getStepContent()
    {
        $langServerInfoTitle = \AdminLang::trans("wizard.ssl.serverInfo");
        $langSslServerType = \AdminLang::trans("wizard.ssl.serverType");
        $langPleaseChoose = \AdminLang::trans("wizard.ssl.selectType", [":serverType" => $langSslServerType]);
        $langSslCsr = \AdminLang::trans("wizard.ssl.certificateSigningRequest");
        $autoGenerateCsr = \AdminLang::trans("wizard.ssl.autoGenerateCsr");
        $csrInstructions = \AdminLang::trans("wizard.ssl.csrInstructions");
        $serviceId = \App::getFromRequest("serviceid");
        $addonId = \App::getFromRequest("addonid");
        $webServerTypes = ["cpanel" => "cPanel/WHM", "plesk" => "Plesk", "apache2" => "Apache 2", "apacheopenssl" => "Apache + OpenSSL", "apacheapachessl" => "Apache + ApacheSSL", "iis" => "Microsoft IIS", "other" => "Other"];
        $webServerTypesOutput = [];
        foreach ($webServerTypes as $name => $displayLabel) {
            $webServerTypesOutput[] = "<option value=\"" . $name . "\">" . $displayLabel . "</option>";
        }
        $autoGenerateCsrButton = "";
        $serverInterface = new \WHMCS\Module\Server();
        if ($addonId && $serverInterface->loadByAddonId($addonId) && $serverInterface->functionExists("check_auto_install_panels")) {
            $response = $serverInterface->call("check_auto_install_panels");
            if (array_key_exists("supported", $response) && $response["supported"] === true) {
                $autoGenerateCsrDescription = \AdminLang::trans("wizard.ssl.autoGenerateCsrDescription", [":panel" => $response["panel"]]);
                $autoGenerateCsrButton = "<div id=\"autoGenerateCsr\" style=\"margin-top:16px;display: none;\">\n                <a id=\"btnAutoGenerateCsr\" href=\"#\" class=\"btn btn-default btn-sm pull-left\" style=\"margin-right:20px;\" onclick=\"return false;\">" . $autoGenerateCsr . "</a>" . $autoGenerateCsrDescription . "</div>";
            }
        }
        $webServerTypesOutput = implode($webServerTypesOutput);
        return "            <h2>" . $langServerInfoTitle . "</h2>\n            <div class=\"alert alert-info info-alert\">" . $csrInstructions . "</div>\n            <div class=\"form-group\">\n                <label for=\"inputServerType\">" . $langSslServerType . "</label>\n                <select name=\"servertype\" id=\"inputServerType\" class=\"form-control\">\n                    <option value=\"\" selected>" . $langPleaseChoose . "</option>\n                    " . $webServerTypesOutput . "\n                </select>\n            </div>\n\n            <div class=\"form-group\">\n                <label for=\"inputCsr\">" . $langSslCsr . "</label>\n                <textarea name=\"csr\" id=\"inputCsr\" rows=\"7\" class=\"form-control\">-----BEGIN CERTIFICATE REQUEST-----\n-----END CERTIFICATE REQUEST-----</textarea>\n            </div>\n\n            " . $autoGenerateCsrButton . "\n\n            <input type=\"hidden\" name=\"serviceid\" value=\"" . $serviceId . "\">\n            <input type=\"hidden\" name=\"addonid\" value=\"" . $addonId . "\">\n\n<script type=\"text/javascript\">\njQuery(document).ready(function(){\n    jQuery('#btnAutoGenerateCsr').on('click', function(){\n        jQuery('#modalAjaxLoader').show();\n        WHMCS.http.jqClient.post(\n            window.location.href,\n            {\n                modop: \"custom\",\n                ac: \"generate_csr\",\n                token: csrfToken\n            },\n            function (data) {\n                if (typeof data.body.csr !== \"undefined\" && data.body.csr !== false) {\n                    jQuery('#inputCsr').text(data.body.csr);\n                }\n                jQuery('#modalAjaxLoader').hide();\n            },\n            'json'\n        );\n    });\n});\n</script> ";
    }

    public function save($data)
    {
        $serverType = isset($data["servertype"]) ? trim($data["servertype"]) : "";
        $csr = isset($data["csr"]) ? trim($data["csr"]) : "";
        $serviceId = isset($data["serviceid"]) ? trim($data["serviceid"]) : "";
        $addonId = isset($data["addonid"]) ? trim($data["addonid"]) : "";
        if (!$serverType) {
            throw new \WHMCS\Exception("Web Server Type is required");
        }
        if (!$csr || $csr == "-----BEGIN CERTIFICATE REQUEST-----\r\n-----END CERTIFICATE REQUEST-----") {
            throw new \WHMCS\Exception("A Certificate Signing Request (CSR) is required");
        }
        $serverInterface = new \WHMCS\Module\Server();
        if ($addonId) {
            $serverInterface->loadByAddonId($addonId);
        } else {
            $serverInterface->loadByServiceID($serviceId);
        }
        $response = $serverInterface->call("SSLStepTwo", ["csr" => $csr]);
        if (isset($response["error"]) && $response["error"]) {
            throw new \WHMCS\Exception($response["error"]);
        }
        $certConfig = ["serverType" => $serverType, "csr" => $csr, "domain" => $response["displaydata"]["Domain Name"], "org" => $response["displaydata"]["Organization"]];
        $model = $addonId ? \WHMCS\Service\Addon::findOrFail($addonId) : \WHMCS\Service\Service::findOrFail($serviceId);
        $model->serviceProperties->save(["domain" => $response["displaydata"]["Domain Name"]]);
        \WHMCS\Session::setAndRelease("AdminCertConfiguration", $certConfig);
        return ["sslData" => ["approvalMethods" => $response["approvalmethods"], "approverEmails" => $response["approveremails"]]];
    }
}
