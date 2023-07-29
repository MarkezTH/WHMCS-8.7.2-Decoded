<?php

add_hook("ClientAreaFooterOutput", 1, function ($vars) {
    $return = "";
    try {
        WHMCS\Module\Gateway::factory("stripe");
    } catch (Exception $e) {
        $filename = $vars["filename"];
        $template = $vars["templatefile"];
        $requiredFiles = ["cart", "creditcard"];
        $templateFiles = ["account-paymentmethods-manage", "invoice-payment"];
        if (in_array($filename, $requiredFiles) || in_array($template, $templateFiles)) {
            $return = "<script type=\"text/javascript\" src=\"https://js.stripe.com/v3/\"></script>";
        }
    }
    return $return;
});
