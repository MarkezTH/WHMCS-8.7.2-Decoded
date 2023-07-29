<?php

add_hook("AdminAreaFooterOutput", 1, function ($vars) {
    $filename = $vars["filename"];
    $return = "";
    if ($filename == "clientssummary") {
        $return = "<script type=\"text/javascript\" src=\"https://js.stripe.com/v3/\"></script>";
    }
    return $return;
});
add_hook("ClientAreaFooterOutput", 1, function ($vars) {
    $filename = $vars["filename"];
    $template = $vars["templatefile"];
    $return = "";
    $requiredFiles = ["cart", "creditcard"];
    $requiredTemplates = ["account-paymentmethods-manage", "invoice-payment"];
    if (in_array($filename, $requiredFiles) || in_array($template, $requiredTemplates)) {
        $return = "<script type=\"text/javascript\" src=\"https://js.stripe.com/v3/\"></script>";
    }
    return $return;
});
Hook::add("AdminHomeWidgets", 1, function () {
    return new WHMCS\Module\Gateway\Stripe\Widget\Stripe();
});
