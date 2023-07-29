<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("");
$response = [];
$wizard = NULL;
try {
    $requestedWizard = App::getFromRequest("wizard");
    $wizard = WHMCS\Admin\Wizard\Wizard::factory($requestedWizard);
} catch (WHMCS\Exception\Authorization\AccessDenied $e) {
    $response = ["body" => "<div class=\"container\"><h2>" . $e->getMessage() . "</h2></div>"];
} catch (Exception $e) {
    $response = ["body" => $e->getMessage()];
    $dismiss = App::getFromRequest("dismiss");
    if ($dismiss == "true") {
        WHMCS\Config\Setting::setValue("DisableSetupWizard", 1);
        $response = ["disabled" => true];
    }
}
if (!is_null($wizard)) {
    $step = App::getFromRequest("step");
    if ($step != "" && ctype_digit($step)) {
        check_token("WHMCS.admin.default");
        try {
            $action = App::getFromRequest("action");
            if (!$action) {
                $action = "save";
            }
            $returnData = $wizard->handleSubmit($step, $action, $_REQUEST);
            $response = ["success" => true];
            if (is_array($returnData)) {
                $response = array_merge($response, $returnData);
            }
        } catch (Exception $e) {
            $response = ["success" => false, "error" => $e->getMessage()];
        }
    } else {
        $output = $wizard->render(new WHMCS\Smarty(true, "mail"));
        $response = ["body" => $output];
    }
}
$aInt->setBodyContent($response);
$aInt->output();
