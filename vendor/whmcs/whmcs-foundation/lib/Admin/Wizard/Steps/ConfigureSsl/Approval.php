<?php

namespace WHMCS\Admin\Wizard\Steps\ConfigureSsl;

class Approval
{
    public function getStepContent()
    {
        $serviceId = \App::getFromRequest("serviceid");
        $addonId = \App::getFromRequest("addonid");
        $chooseTitle = \AdminLang::trans("wizard.ssl.choose");
        $fileBased = \AdminLang::trans("wizard.ssl.fileMethod");
        $emailBased = \AdminLang::trans("wizard.ssl.emailMethod");
        $dnsBased = \AdminLang::trans("wizard.ssl.dnsMethod");
        $emailBasedInstructions = \AdminLang::trans("wizard.ssl.emailMethodDescription");
        $emailHeader = \AdminLang::trans("wizard.ssl.selectAnEmail");
        $fileBasedInstructions = \AdminLang::trans("wizard.ssl.fileMethodDescription");
        $dnsBasedInstructions = \AdminLang::trans("wizard.ssl.dnsMethodDescription");
        return "            <h2>" . $chooseTitle . "</h2>\n            <div class=\"alert info-alert hidden\"></div>\n            <p>\n                <label class=\"radio-inline\" for=\"emailMethod\">\n                    <input type=\"radio\" name=\"approval_method\" id=\"emailMethod\" value=\"email\" checked>\n                    " . $emailBased . "\n                </label>\n                <label class=\"radio-inline hidden\" for=\"dns-txt-tokenMethod\">\n                    <input type=\"radio\" name=\"approval_method\" id=\"dns-txt-tokenMethod\" value=\"dns-txt-token\">\n                    " . $dnsBased . "\n                </label>\n                <label class=\"radio-inline hidden\" for=\"fileMethod\">\n                    <input type=\"radio\" name=\"approval_method\" id=\"fileMethod\" value=\"file\">\n                    " . $fileBased . "\n                </label>\n            </p>\n\n            <div class=\"well text-center hidden\"id=\"containerApprovalMethodFile\">\n                " . $fileBasedInstructions . "\n            </div>\n            \n            <div class=\"well text-center hidden\" id=\"containerApprovalMethodDns\">\n                " . $dnsBasedInstructions . "\n            </div>\n\n            <div id=\"containerApprovalMethodEmail\">\n                <div class=\"well text-center\">\n                    " . $emailBasedInstructions . "\n                </div>\n                <h3>" . $emailHeader . "</h3>\n                <div class=\"cert-approver-emails\">\n                </div>\n            </div>\n\n            <input type=\"hidden\" name=\"serviceid\" value=\"" . $serviceId . "\">\n            <input type=\"hidden\" name=\"addonid\" value=\"" . $addonId . "\">\n\n<script>\njQuery(document).ready(function() {\n    jQuery('input[name=\"approval_method\"]').on('ifChecked', function(event) {\n        var fileMethod = \$('#containerApprovalMethodFile'),\n            emailMethod = \$('#containerApprovalMethodEmail'),\n            dnsMethod = \$('#containerApprovalMethodDns');\n        if (jQuery(this).attr('value') == 'file') {\n            fileMethod.removeClass('hidden').show();\n            dnsMethod.hide();\n            emailMethod.hide();\n        } else if (jQuery(this).attr('value') == 'dns-txt-token') {\n            dnsMethod.removeClass('hidden').show();\n            fileMethod.hide();\n            emailMethod.hide();\n        } else {\n            fileMethod.hide();\n            dnsMethod.hide();\n            emailMethod.removeClass('hidden').show();\n        }\n    });\n});\n</script>";
    }

    public function save()
    {
        $approvalMethod = \App::getFromRequest("approval_method");
        $approverEmail = \App::getFromRequest("approver_email");
        $serviceId = \App::getFromRequest("serviceid");
        $addonId = \App::getFromRequest("addonid");
        if ($approvalMethod == "email" && !$approverEmail) {
            throw new \WHMCS\Exception("Approver email is required");
        }
        $certConfig = \WHMCS\Session::get("AdminCertConfiguration");
        $serverInterface = new \WHMCS\Module\Server();
        if ($addonId) {
            $serverInterface->loadByAddonId($addonId);
        } else {
            $serverInterface->loadByServiceID($serviceId);
        }
        $configData = ["servertype" => $certConfig["serverType"], "csr" => $certConfig["csr"], "domain" => $certConfig["domain"], "firstname" => $certConfig["admin"]["firstname"], "lastname" => $certConfig["admin"]["lastname"], "orgname" => $certConfig["admin"]["orgname"], "jobtitle" => $certConfig["admin"]["jobtitle"], "email" => $certConfig["admin"]["email"], "address1" => $certConfig["admin"]["address1"], "address2" => $certConfig["admin"]["address2"], "city" => $certConfig["admin"]["city"], "state" => $certConfig["admin"]["state"], "postcode" => $certConfig["admin"]["postcode"], "country" => $certConfig["admin"]["country"], "phonenumber" => $certConfig["admin"]["phonenumber"], "approvalmethod" => $approvalMethod, "approveremail" => $approverEmail];
        if (strlen($configData["orgname"]) == 0) {
            $configData["orgname"] = $certConfig["org"];
        }
        $response = $serverInterface->call("SSLStepThree", ["configdata" => $configData]);
        if (isset($response["error"]) && $response["error"]) {
            throw new \WHMCS\Exception($response["error"]);
        }
        \WHMCS\Session::start();
        \WHMCS\Session::delete("AdminCertConfiguration");
        $sslProduct = \WHMCS\Service\Ssl::where("serviceid", $serviceId)->where("addon_id", $addonId)->where("module", "marketconnect")->firstOrFail();
        $sslProduct->configurationData = safe_serialize($configData);
        $sslProduct->status = \WHMCS\Service\Ssl::STATUS_CONFIGURATION_SUBMITTED;
        $sslProduct->save();
        $sslProduct->refresh();
        $response["authData"] = json_decode($sslProduct->authenticationData->defaults()->pack());
        $orderNumber = $configData["order_number"];
        $data = new \WHMCS\TransientData();
        $data->delete("marketconnect.order." . $orderNumber);
        $response["refreshMc"] = true;
        return $response;
    }
}
