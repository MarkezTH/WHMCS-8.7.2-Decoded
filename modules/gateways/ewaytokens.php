<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
define("EWAY_TOKENS_PARTNER_ID", "311f3706123f4a93bc92841cd3b9e970");
function ewaytokens_MetaData()
{
    return ["DisplayName" => "eWAY Rapid 3.1 Payments", "APIVersion" => "1.1", "AllowActivation" => false];
}

function ewaytokens_config()
{
    $configArray = [];
    $configArray["FriendlyName"] = ["Type" => "System", "Value" => "eWAY Rapid 3.1 Payments"];
    $configArray["apiKey"] = ["FriendlyName" => "API Key", "Type" => "text", "Size" => 20];
    $configArray["apiPass"] = ["FriendlyName" => "API Password", "Type" => "password", "Size" => 20];
    $configArray["testmode"] = ["FriendlyName" => "Test Mode", "Type" => "yesno"];
    return $configArray;
}

function ewaytokens_nolocalcc()
{
}

function ewaytokens_remoteinput($params)
{
    WHMCS\Session::delete("ewaytokensConfirm");
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/AccessCodesShared";
    $customer = [];
    $customer["Reference"] = $params["clientdetails"]["id"];
    $customer["Title"] = "";
    $customer["FirstName"] = $params["clientdetails"]["firstname"];
    $customer["LastName"] = $params["clientdetails"]["lastname"];
    if ($params["clientdetails"]["company"]) {
        $customer["CompanyName"] = $params["clientdetails"]["company"];
    }
    $customer["Street1"] = $params["clientdetails"]["address1"];
    if ($params["clientdetails"]["address2"]) {
        $customer["Street2"] = $params["clientdetails"]["address2"];
    }
    $customer["City"] = $params["clientdetails"]["city"];
    $customer["State"] = $params["clientdetails"]["state"];
    $customer["PostalCode"] = $params["clientdetails"]["postcode"];
    $customer["Email"] = $params["clientdetails"]["email"];
    $customer["Phone"] = $params["clientdetails"]["phonenumber"];
    $customer["Country"] = $params["clientdetails"]["country"];
    $data = ["Method" => "CreateTokenCustomer", "RedirectUrl" => App::getSystemURL(), "CancelUrl" => App::getSystemURL(), "TransactionType" => "Purchase", "PartnerID" => EWAY_TOKENS_PARTNER_ID, "Customer" => $customer];
    if (array_key_exists("amount", $params) && $params["amount"]) {
        $amount = round($params["amount"] * 100);
        $data["Method"] = "TokenPayment";
        $data["Payment"] = ["TotalAmount" => $amount, "InvoiceNumber" => $params["invoiceid"], "InvoiceDescription" => "Invoice #" . $params["invoiceid"], "InvoiceReference" => $params["invoiceid"], "CurrencyCode" => $params["currency"]];
    }
    $response = curlCall($url, json_encode($data), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
    $response = json_decode($response, true);
    if (!$response["SharedPaymentUrl"]) {
        $error = Lang::trans("invoiceserror") . " (" . $response["Errors"] . ")";
        return "<div class=\"alert alert-danger\">" . $error . "</div>";
    }
    WHMCS\Session::set("ewaytokensConfirm", $response["AccessCode"]);
    $loading = Lang::trans("loading");
    $output = ewaytokens_javascript_output($response, NULL, $params["clientdetails"]["id"]);
    return $output . "\n<div id=\"eWayTokensInfo\" class=\"alert alert-info\">\n    " . $loading . "\n</div>";
}

function ewaytokens_remoteupdate($params)
{
    WHMCS\Session::delete("ewaytokensConfirm");
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/AccessCodesShared";
    $customerId = $params["gatewayid"];
    $data = ["Method" => "UpdateTokenCustomer", "RedirectUrl" => App::getSystemURL(), "CancelUrl" => App::getSystemURL(), "PartnerID" => EWAY_TOKENS_PARTNER_ID, "Customer" => ["TokenCustomerID" => $customerId], "Payment" => ["TotalAmount" => 0]];
    $response = curlCall($url, json_encode($data), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
    $response = json_decode($response, true);
    if (!$response["SharedPaymentUrl"]) {
        $error = Lang::trans("invoiceserror") . " (" . $response["Errors"] . ")";
        return "<div class=\"alert alert-danger\">" . $error . "</div>";
    }
    WHMCS\Session::set("ewaytokensConfirm", $response["AccessCode"]);
    $loading = Lang::trans("loading");
    $payMethod = $params["payMethod"];
    $output = ewaytokens_javascript_output($response, $payMethod->id, $payMethod->client->id);
    return $output . "\n<div id=\"eWayTokensInfo\" class=\"alert alert-info\">\n    " . $loading . "\n</div>";
}

function ewaytokens_capture($params)
{
    if (!$params["gatewayid"]) {
        return ["status" => "failed", "rawdata" => "No Remote Card Stored for this Client"];
    }
    $whmcs = App::self();
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/Transaction";
    try {
        $payment = [];
        $payment["InvoiceNumber"] = $params["invoiceid"];
        $payment["InvoiceDescription"] = "Invoice #" . $params["invoiceid"];
        $payment["InvoiceReference"] = $params["invoiceid"];
        $payment["TotalAmount"] = round($params["amount"] * 100);
        $payment["CurrencyCode"] = $params["currency"];
        $parameters = [];
        $parameters["Method"] = "TokenPayment";
        $parameters["RedirectUrl"] = $params["systemurl"];
        $parameters["CancelUrl"] = $params["returnurl"] . "&paymentfailed=true";
        $parameters["CustomerIP"] = $whmcs->getRemoteIp();
        $parameters["TransactionType"] = "Recurring";
        $parameters["Payment"] = $payment;
        $parameters["Customer"] = ["TokenCustomerID" => $params["gatewayid"]];
        $parameters["PartnerID"] = EWAY_TOKENS_PARTNER_ID;
        $payment = curlCall($url, json_encode($parameters), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
        $payment = json_decode($payment, true);
        if ($payment["TransactionStatus"]) {
            return ["status" => "success", "transid" => $payment["TransactionID"], "rawdata" => $payment];
        }
        return ["status" => "declined", "rawdata" => $payment];
    } catch (Exception $e) {
        return ["status" => "error", "rawdata" => $e->getMessage()];
    }
}

function ewaytokens_refund($params)
{
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $url = "https://api" . $sandbox . ".ewaypayments.com/Transaction/" . $params["transid"] . "/Refund";
    $parameters = [];
    $parameters["PartnerID"] = EWAY_TOKENS_PARTNER_ID;
    $refund = [];
    $refund["TotalAmount"] = round($params["amount"] * 100);
    $refund["CurrencyCode"] = $params["currency"];
    $parameters["Refund"] = $refund;
    $refund = curlCall($url, json_encode($parameters), ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
    if ($refund["TransactionStatus"]) {
        return ["status" => "success", "transid" => $refund["TransactionID"], "rawdata" => $refund];
    }
    return ["status" => "declined", "rawdata" => $refund];
}

function ewaytokens_adminstatusmsg($params)
{
    $gatewayId = $params["gatewayid"];
    if ($gatewayId) {
        return ["type" => "info", "title" => "eWay Remote Token", "msg" => "This customer has an eWay Token storing their card details for automated recurring billing with ID " . $gatewayId];
    }
    return [];
}

function ewaytokens_javascript_output($response, $payMethodId = NULL, $clientId = NULL)
{
    $error = Lang::trans("erroroccured");
    $success = Lang::trans("paymentMethods.addedSuccess");
    $pleaseWait = Lang::trans("pleasewait");
    $retry = Lang::trans("paymentMethods.retry");
    $failed = Lang::trans("paymentMethods.requestCancelled");
    $failed .= "<div class=\"pull-right\"><a class=\"btn btn-default btn-xs\" onClick=\"eCrypt.showModalPayment(eWAYConfig, eWayCallback);\">" . $retry . "</a></div>";
    $confirmRouteUrl = "/payment/remote/confirm";
    if ($payMethodId) {
        $confirmRouteUrl .= "/update";
        $success = Lang::trans("paymentMethods.updateSuccess");
    }
    $confirmRouteData = "gateway=ewaytokens";
    if ($payMethodId) {
        $confirmRouteData .= "&pay_method_id=" . $payMethodId;
    } else {
        $confirmRouteData .= "&client_id=" . (int) $clientId;
    }
    return "<script>\n    /**\n     * eWAY Rapid IFrame config object. Contains the SharedPaymentUrl\n     */\n    var eWAYConfig = {\n        sharedPaymentUrl: \"" . $response["SharedPaymentUrl"] . "\"\n    };\n\n    /**\n     * Example eWAY Rapid IFrame callback\n     */\n    function eWayCallback(result, transactionID, errors) {\n        var confirmUrl = '';\n        if (typeof WHMCS.utils !== 'undefined') {\n                confirmUrl = WHMCS.utils.getRouteUrl('" . $confirmRouteUrl . "');\n            } else {\n                confirmUrl = WHMCS.adminUtils.getAdminRouteUrl('/client" . $confirmRouteUrl . "');\n            }\n        if (result === 'Complete') {\n            jQuery('#eWayTokensInfo').slideUp('fast', function() {\n                jQuery(this).removeClass('alert-info alert-danger')\n                        .addClass('alert-success')\n                    .text('" . $success . ". " . $pleaseWait . "')\n                    .slideDown('fast');\n            });\n            WHMCS.http.jqClient.jsonPost({\n                url: confirmUrl,\n                data: '" . $confirmRouteData . "&token=' + csrfToken,\n                success: function(response) {\n                    if (typeof WHMCS.utils !== 'undefined') {\n                        var url = WHMCS.utils.getRouteUrl('/account/paymentmethods');\n                        if (response.redirect !== '') {\n                            url = response.redirect;\n                        }\n                        window.location.replace(url);\n                    }\n                    if (response.successWindow && response.successWindow !== '') {\n                        window[response.successWindow]();\n                        dialogClose();\n                    }\n                },\n                warning: function(error) {\n                    jQuery('#eWayTokensInfo').slideUp('fast', function() {\n                    jQuery(this).removeClass('alert-info alert-success')\n                        .addClass('alert-danger')\n                        .text(error)\n                        .slideDown('fast');\n                    });\n                },\n                fail: function(error) {\n                    jQuery('#eWayTokensInfo').slideUp('fast', function() {\n                    jQuery(this).removeClass('alert-info alert-success')\n                        .addClass('alert-danger')\n                        .text(error)\n                        .slideDown('fast');\n                    });\n                }\n            });\n        } else if (result === 'Error') {\n            jQuery('#eWayTokensInfo').slideUp('fast', function() {\n                jQuery(this).removeClass('alert-info alert-success')\n                        .addClass('alert-danger')\n                    .text('" . $error . ": ' + errors)\n                    .slideDown('fast');\n            });\n        } else if (result === 'Cancel') {\n            jQuery('#eWayTokensInfo').html('" . $failed . "');\n        }\n    }\n    var showModalTimer = null;\n    jQuery(document).ready(function() {\n        if (typeof eCrypt === 'undefined') {\n            jQuery.getScript(\n                'https://secure.ewaypayments.com/scripts/eCrypt.min.js'\n            );\n        }\n        showModalTimer = setTimeout(eWayTokensShowModal, 3000, eWAYConfig, eWayCallback);\n    });\n    function eWayTokensShowModal(config, callback) {\n        if (typeof eCrypt === 'undefined') {\n            showModalTimer = setTimeout(eWayTokensShowModal, 3000, config, callback);\n            return;\n        }\n        eCrypt.showModalPayment(config, callback);\n    }\n</script>";
}

function ewaytokens_remote_input_confirm($params)
{
    $sandbox = "";
    if ($params["testmode"]) {
        $sandbox = ".sandbox";
    }
    $remoteStorageToken = $params["remoteStorageToken"];
    $url = "https://api" . $sandbox . ".ewaypayments.com/AccessCode/" . $remoteStorageToken;
    $response = curlCall($url, "", ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
    $response = json_decode($response, true);
    if (array_key_exists("errors", $response) && $response["errors"]) {
        return ["warning" => $response["errors"]];
    }
    $customerToken = $response["TokenCustomerID"];
    $url = "https://api" . $sandbox . ".ewaypayments.com/Customer/" . $customerToken;
    $response2 = curlCall($url, "", ["CURLOPT_USERPWD" => $params["apiKey"] . ":" . $params["apiPass"], "CURLOPT_HTTPHEADER" => ["Content-Type:  application/json"]]);
    $response2 = json_decode($response2, true);
    $customer = $response2["Customers"][0];
    $invoiceId = $response["InvoiceNumber"];
    $redirectPage = App::getSystemUrl() . "viewinvoice.php?id=" . $invoiceId . "&";
    $success = false;
    if ($response["TransactionStatus"]) {
        logTransaction($params["paymentmethod"], $response, "Success");
        $invoice = WHMCS\Billing\Invoice::find($invoiceId);
        if ($invoice) {
            $invoice->addPayment($invoice->balance, $response["TransactionID"], 0, "ewaytokens");
            $invoice->saveRemoteCard($customer["CardDetails"]["Number"], getCardTypeByCardNumber($customer["CardDetails"]["Number"]), $customer["CardDetails"]["ExpiryMonth"] . $customer["CardDetails"]["ExpiryYear"], $customerToken);
            $success = true;
        } else {
            logTransaction($params["paymentmethod"], $response, "Invoice ID Not Found");
        }
    }
    if ($success) {
        $redirectPage .= "paymentsuccess=true";
    } else {
        $redirectPage .= "paymentfailed=true";
    }
    return ["success" => true, "gatewayid" => $customerToken, "cardnumber" => $customer["CardDetails"]["Number"], "cardexpiry" => $customer["CardDetails"]["ExpiryMonth"] . $customer["CardDetails"]["ExpiryYear"], "redirect" => $redirectPage];
}

function ewaytokens_admin_area_actions($params)
{
    $actions = [];
    $ewayTokenPayMethods = WHMCS\Payment\PayMethod\Model::whereGatewayName("ewaytokens")->count();
    if (0 < $ewayTokenPayMethods) {
        $actions[] = ["label" => "Migrate to eWAY Module", "actionName" => "migrate_to_eway", "modal" => true];
    }
    return $actions;
}

function ewaytokens_migrate_to_eway($params)
{
    $action = App::getFromRequest("action");
    switch ($action) {
        case "migrate":
            check_token("WHMCS.admin.default");
            try {
                $gatewayInterface = WHMCS\Module\Gateway::factory("ewayv4");
                if (App::isInRequest("eway_public_key")) {
                    $gatewayInterface->updateConfiguration(["publicApiKey" => App::getFromRequest("eway_public_key")]);
                }
            } catch (Exception $e) {
                if ($e->getMessage() == WHMCS\Module\Gateway::MODULE_NOT_ACTIVE) {
                    $gatewayInterface = new WHMCS\Module\Gateway();
                    $gatewayInterface->load("ewayv4");
                    $gatewayInterface->activate(["apiKey" => $params["apiKey"], "apiPass" => $params["apiPass"], "publicApiKey" => App::getFromRequest("eway_public_key"), "testmode" => $params["testmode"]]);
                }
            }
            WHMCS\Payment\PayMethod\Model::whereGatewayName("ewaytokens")->update(["gateway_name" => "ewayv4"]);
            $gatewayInterface = WHMCS\Module\Gateway::factory("ewaytokens");
            $gatewayInterface->deactivate(["oldGateway" => "ewaytokens", "newGateway" => "ewayv4"]);
            $return = ["redirect" => "configgateways.php?updated=ewayv4#m_ewayv4"];
            break;
        default:
            $payMethodsToMigrate = WHMCS\Payment\PayMethod\Model::whereGatewayName("ewaytokens")->count();
            $apiKey = WHMCS\Module\GatewaySetting::getValue("ewayv4", "publicApiKey");
            $apiKeyRequired = true;
            $eWayActive = false;
            if ($apiKey) {
                $apiKeyRequired = false;
                $eWayActive = true;
            }
            if (!$eWayActive) {
                $eWayActive = WHMCS\Module\GatewaySetting::gateway("ewayv4")->exists();
            }
            $view = moduleView("ewaytokens", "migrate.start", ["payMethodsToMigrate" => $payMethodsToMigrate, "apiKeyRequired" => $apiKeyRequired, "eWayActive" => $eWayActive, "routePath" => routePath("admin-setup-payments-gateways-action", "ewaytokens", "migrate_to_eway")]);
            $return = ["status" => "success", "body" => $view, "submitlabel" => "Migrate", "submitId" => "btnMigrateToEWAY"];
            return $return;
    }
}
