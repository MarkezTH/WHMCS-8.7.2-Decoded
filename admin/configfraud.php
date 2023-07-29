<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Configure Fraud Protection");
$aInt->title = AdminLang::trans("fraud.title");
$aInt->sidebar = "config";
$aInt->icon = "configbans";
$aInt->helplink = "Fraud Protection";
$aInt->requiredFiles(["modulefunctions"]);
$action = App::getFromRequest("action");
$module = new WHMCS\Module\Fraud();
$fraudmodules = $module->getList();
if ($action == "save") {
    $fraud = App::getFromRequest("fraud");
    if ($fraud && in_array($fraud, $fraudmodules)) {
        check_token("WHMCS.admin.default");
        $module->load($fraud);
        $configarray = $module->call("getConfigArray");
        $existingValues = $module->getSettings();
        $moduleActivated = false;
        foreach ($configarray as $regconfoption => $values) {
            if ($values["Type"] != "System") {
                $regconfoption2 = str_replace(" ", "_", $regconfoption);
                $valueToSave = trim(WHMCS\Input\Sanitize::decode($_POST[$regconfoption2]));
                if ($regconfoption == "Enable" && $valueToSave == "on" && $existingValues[$regconfoption] != $valueToSave) {
                    $moduleActivated = true;
                    logAdminActivity("Fraud Module Activated: '" . $module->getDisplayName() . "'");
                    WHMCS\Database\Capsule::table("tblfraud")->where("fraud", "!=", $fraud)->where("setting", "Enable")->update(["value" => ""]);
                }
                if ($values["Type"] == "password") {
                    $updatedPassword = interpretMaskedPasswordChangeForStorage($valueToSave, $existingValues[$regconfoption2]);
                    if ($updatedPassword === false) {
                        $valueToSave = $existingValues[$regconfoption2];
                    }
                }
                WHMCS\Database\Capsule::table("tblfraud")->updateOrInsert(["fraud" => $fraud, "setting" => $regconfoption], ["value" => $valueToSave]);
            }
        }
        if ($moduleActivated) {
            $module->call("activate");
        }
        logAdminActivity("Fraud Module Configuration Modified: '" . $module->getDisplayName() . "'");
        redir("success=1");
    }
}
$aInt->content = view("admin.setup.fraud.index", ["fraudObject" => $module, "fraudModules" => $fraudmodules, "success" => $success ?? 0]);
$aInt->display();
