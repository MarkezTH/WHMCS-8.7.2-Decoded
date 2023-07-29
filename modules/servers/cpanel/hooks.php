<?php

add_hook("AdminPredefinedAddons", -100, "cpanel_adminPredefinedAddons");
add_hook("AfterModuleTerminate", 1, function ($vars) {
    $model = $vars["params"]["model"];
    if (is_null($model->product) || $model->product->module !== "cpanel") {
        return NULL;
    }
    $serviceWordPressInstances = $model->serviceProperties->get("WordPress Instances");
    if ($serviceWordPressInstances) {
        logActivity("Deleting WordPress instances on service termination: " . $serviceWordPressInstances . ", " . $model instanceof WHMCS\Service\Service ? "Service" : "Addon ID: " . $model->id);
        $model->serviceProperties->save(["WordPress Instances" => WHMCS\Input\Sanitize::encode(json_encode([]))]);
    }
});
function cpanel_adminPredefinedAddons()
{
    return [["module" => "cpanel", "icontype" => "fa", "iconvalue" => "fad fa-cube", "labeltype" => "success", "labelvalue" => "New!", "paneltitle" => "WP Toolkit Deluxe (cPanel)", "paneldescription" => "Automate provisioning of WP Toolkit Deluxe for cPanel Hosting Accounts", "addonname" => "WP Toolkit Deluxe", "addondescription" => "WP Toolkit Deluxe gives you advanced features like plugin and theme management, staging, cloning, and Smart Updates!", "welcomeemail" => "WP Toolkit Welcome Email", "featureaddon" => "wp-toolkit-deluxe"]];
}
