<?php

namespace WHMCS\Module\Gateway\TCO;

use WHMCS\Application\Support\Facades\Lang;

class Inline
{
    public function link($params = [])
    {
        $formParameters = [];
        $recurringFormParameters = [];
        if ($params["demomode"]) {
            $formParameters["demo"] = "Y";
        }
        $invoice = \WHMCS\Billing\Invoice::find($params["invoiceid"]);
        $invoiceData = $invoice->getBillingValues();
        $overdue = $invoiceData["overdue"];
        if ($overdue) {
            $params["recurringBilling"] = "disablerecur";
        }
        unset($invoiceData["overdue"]);
        $i = 0;
        foreach ($invoiceData as $invoiceDatum) {
            $firstPaymentAmount = $invoiceDatum["amount"];
            if (array_key_exists("firstPaymentAmount", $invoiceDatum)) {
                $firstPaymentAmount = $invoiceDatum["firstPaymentAmount"];
            }
            if (0 <= $invoiceDatum["amount"]) {
                $startupFee = $firstPaymentAmount - $invoiceDatum["amount"];
                $startupFee += $invoiceDatum["setupFee"];
                $formParameters["li_" . $i . "_product_id"] = $invoiceDatum["itemId"];
                $formParameters["li_" . $i . "_startup_fee"] = $startupFee;
                $formParameters["li_" . $i . "_type"] = "product";
                $formParameters["li_" . $i . "_price"] = $invoiceDatum["lineItemAmount"];
                $formParameters["li_" . $i . "_name"] = $invoiceDatum["description"];
                if ($params["recurringBilling"] != "disablerecur" && !$overdue && $invoiceDatum["recurringCyclePeriod"]) {
                    $billingCycle = $invoiceDatum["recurringCyclePeriod"] . " Month";
                    if ($invoiceDatum["recurringCycleUnits"] == "Years") {
                        $billingCycle = $invoiceDatum["recurringCyclePeriod"] . " Year";
                    }
                    $recurringFormParameters["li_" . $i . "_recurrence"] = $billingCycle;
                    $recurringFormParameters["li_" . $i . "_duration"] = "Forever";
                    $recurringFormParameters["li_" . $i . "_price"] = $invoiceDatum["amount"];
                }
            } else {
                $formParameters["li_" . $i . "_type"] = "coupon";
                $formParameters["li_" . $i . "_price"] = abs($invoiceDatum["amount"]);
                $formParameters["li_" . $i . "_name"] = $invoiceDatum["description"];
            }
            $i++;
        }
        if (0 < $invoice->credit) {
            $formParameters["li_" . $i . "_type"] = "coupon";
            $formParameters["li_" . $i . "_price"] = $invoice->credit;
            $formParameters["li_" . $i . "_name"] = "Credit";
            $i++;
        }
        if (0 < $invoice->amountPaid) {
            $formParameters["li_" . $i . "_type"] = "coupon";
            $formParameters["li_" . $i . "_price"] = $invoice->amountPaid;
            $formParameters["li_" . $i . "_name"] = "Partial Payments";
            $i++;
        }
        $cardName = $params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"];
        $formParameters["sid"] = $params["vendornumber"];
        $formParameters["mode"] = "2CO";
        $formParameters["currency_code"] = $params["currency"];
        $formParameters["merchant_order_id"] = $params["invoiceid"];
        $formParameters["card_holder_name"] = $cardName;
        $formParameters["email"] = $params["clientdetails"]["email"];
        $formParameters["street_address"] = $params["clientdetails"]["address1"];
        $formParameters["street_address2"] = $params["clientdetails"]["address2"];
        $formParameters["city"] = $params["clientdetails"]["city"];
        $formParameters["state"] = $params["clientdetails"]["state"];
        $formParameters["zip"] = $params["clientdetails"]["postcode"];
        $formParameters["country"] = $params["clientdetails"]["country"];
        $formParameters["phone"] = $params["clientdetails"]["telephoneNumber"];
        $formParameters["return_url"] = $params["systemurl"] . "viewinvoice.php?id=" . $params["invoiceid"];
        $formParameters["x_receipt_link_url"] = $params["systemurl"] . "modules/gateways/callback/2checkout.php";
        return $this->buildHtmlForm($params, $formParameters, $recurringFormParameters);
    }

    protected function buildHtmlForm($params, $formParameters, $recurringFormParameters = [])
    {
        $url = "https://www.2checkout.com/checkout/purchase";
        $jsUrl = "https://www.2checkout.com/static/checkout/javascript/direct.min.js";
        $recurringForm = "";
        if ($recurringFormParameters) {
            $recurringFormParameters = array_merge($formParameters, $recurringFormParameters);
            foreach ($recurringFormParameters as $parameterName => $parameterValue) {
                $recurringForm .= "<input type=\"hidden\" name=\"" . $parameterName . "\" value=\"" . $parameterValue . "\">";
            }
        }
        $oneTimeFormParameters = array_filter($formParameters, function ($key) {
            return !preg_match("/^li_[\\d]+/", $key);
        }, ARRAY_FILTER_USE_KEY);
        $oneTimeFormParameters = array_merge($oneTimeFormParameters, ["li_0_type" => "product", "li_0_name" => "Invoice #" . $params["invoiceid"], "li_0_price" => $params["amount"], "li_0_startup_fee" => "0.00", "li_0_product_id" => "I" . $params["invoiceid"]]);
        $items = "";
        foreach ($oneTimeFormParameters as $parameterName => $parameterValue) {
            $items .= "<input type=\"hidden\" name=\"" . $parameterName . "\" value=\"" . $parameterValue . "\">";
        }
        $items .= Helper::languageInput(Lang::getName());
        $code = "";
        $payButtonText = Lang::trans("invoicesubscriptionpayment");
        $redirectWait = Lang::trans("pleaseWaitForPayment");
        $clickToReload = Lang::trans("clickToReload");
        if ($recurringForm) {
            $code .= "<form id=\"tcoInlineRecurringFrm\" action=\"" . $url . "\" method=\"post\">\n" . $recurringForm . "\n    <button id=\"tcoRecurringSubmit\" type=\"submit\" class=\"btn btn-primary\">\n        " . $payButtonText . "\n    </button>\n</form><br>";
        }
        if ($params["recurringBilling"] != "forcerecur") {
            $payButtonText = Lang::trans("invoiceoneoffpayment");
            $code .= "<form id=\"tcoInlineFrm\" action=\"" . $url . "\" method=\"post\">\n" . $items . "\n    <button id=\"tcoSubmit\" type=\"submit\" class=\"btn btn-primary\">\n        " . $payButtonText . "\n    </button>\n</form>";
        }
        $code .= "<script src=\"" . $jsUrl . "\"></script>\n<script type=\"text/javascript\">\njQuery(document).ready(function () {\n    var noAutoSubmit = true,\n        oneoffSubmit = jQuery('#tcoInlineFrm'),\n        button = jQuery('#tcoSubmit'),\n        recurringSubmit = jQuery('#tcoInlineRecurringFrm'),\n        recurringButton = jQuery('#tcoRecurringSubmit'),\n        spinner = jQuery('<i class=\"fas fa-spinner fa-spin\"></i>'),\n        btnClick = function (e) { e.prop('disabled', true).append(spinner); },\n        btnReset = function (e) { e.removeProp('disabled').find(spinner).remove(); };\n      \n    \n    oneoffSubmit.submit(function () {\n        btnClick(button);\n        recurringButton.prop('disabled', true);\n    });\n    \n    \n    recurringSubmit.submit(function () {\n        btnClick(recurringButton);\n        button.prop('disabled', true);\n    });\n    \n    function close_callback(d)\n    {\n        if (recurringButton) {\n            btnReset(recurringButton);\n            button.removeProp('disabled');\n        }\n        if (button) {\n            btnReset(button);\n            recurringButton.removeProp('disabled');\n        }\n        window.location.href = 'viewinvoice.php?id=" . $params["invoiceid"] . "&paymentfailed=true';\n    }\n    \n    (function() {\n         inline_2Checkout.subscribe('checkout_closed', close_callback);\n     }());\n});\n</script>";
        return $code;
    }

    public function callback($params = [])
    {
        $gatewayModuleName = "tco";
        $tcoOrderNumber = \App::getFromRequest("sale_id");
        $tcoInvoiceId = \App::getFromRequest("invoice_id");
        $hashSid = $params["vendornumber"];
        $postedSid = \App::getFromRequest("vendor_id");
        $hashSecretWord = $params["secretword"];
        $hashToValidate = strtoupper(md5($tcoOrderNumber . $hashSid . $tcoInvoiceId . $hashSecretWord));
        if ($hashSid != $postedSid || hash_equals($hashToValidate, \App::getFromRequest("md5_hash")) === false) {
            logTransaction($params["paymentmethod"], $_POST, "MD5 Hash Failure");
        } else {
            $notificationType = \App::getFromRequest("message_type");
            $itemCount = \App::getFromRequest("item_count");
            $serviceId = \App::getFromRequest("item_id_1");
            $transactionId = \App::getFromRequest("sale_id");
            $recurringTransactionId = $transactionId . "-" . \App::getFromRequest("invoice_id");
            $amount = \App::getFromRequest("invoice_list_amount");
            if (!$amount) {
                $amount = \App::getFromRequest("item_list_amount_1");
            }
            $invoiceId = \App::getFromRequest("vendor_order_id");
            $currency = \App::getFromRequest("list_currency");
            try {
                $currency = \WHMCS\Billing\Currency::where("code", $currency)->firstOrFail();
            } catch (\Exception $e) {
                logTransaction($params["paymentmethod"], $_POST, "Unrecognised Currency");
                return NULL;
            }
            $hostingAndAddonIds = ["hosting" => [], "addon" => []];
            if (in_array($notificationType, ["INVOICE_STATUS_CHANGED", "ORDER_CREATED", "RECURRING_INSTALLMENT_SUCCESS"])) {
                $invoiceToBeFound = true;
                for ($i = 1; $i <= $itemCount; $i++) {
                    $serviceId = \App::getFromRequest("item_id_" . $i);
                    $recurringPayment = trim(\App::getFromRequest("item_rec_status_" . $i));
                    if (substr($serviceId, 0, 1) == "H") {
                        $hostingAndAddonIds["hosting"][] = substr($serviceId, 1);
                    } else {
                        if (substr($serviceId, 0, 1) == "A") {
                            $hostingAndAddonIds["addon"][] = substr($serviceId, 1);
                        }
                    }
                    if ($invoiceToBeFound && ($recurringPayment && $serviceId || $notificationType == "RECURRING_INSTALLMENT_SUCCESS")) {
                        $invoiceId = self::findInvoiceID($serviceId, $transactionId);
                        if ($invoiceId) {
                            $invoiceToBeFound = false;
                        }
                    }
                }
                $invoiceId = checkCbInvoiceID($invoiceId, $params["paymentmethod"]);
                $invoice = \WHMCS\Billing\Invoice::with("client", "client.currencyrel")->find($invoiceId);
            }
            $notificationOnly = false;
            switch ($notificationType) {
                case "INVOICE_STATUS_CHANGED":
                    if (!$params["skipfraudcheck"]) {
                        $fraudStatus = \App::getFromRequest("fraud_status");
                        $invoiceStatus = \App::getFromRequest("invoice_status");
                        if (in_array($invoiceStatus, ["approved", "deposited"])) {
                            if ($fraudStatus == "pass") {
                                logTransaction($params["paymentmethod"], $_POST, "Fraud Status Pass");
                                checkCbTransID($transactionId);
                                $amount = Helper::convertCurrency($amount, $currency, $invoice);
                                $invoice->addPayment($amount, $transactionId, 0, $gatewayModuleName);
                                self::saveRecurringSaleId($hostingAndAddonIds, $transactionId);
                            } else {
                                logTransaction($params["paymentmethod"], $_POST, "Fraud Status Fail");
                            }
                        } else {
                            $notificationOnly = true;
                        }
                    }
                    break;
                case "ORDER_CREATED":
                    if ($params["skipfraudcheck"]) {
                        logTransaction($params["paymentmethod"], $_POST, "Payment Success");
                        checkCbTransID($transactionId);
                        $amount = Helper::convertCurrency($amount, $currency, $invoice);
                        $invoice->addPayment($amount, $transactionId, 0, $gatewayModuleName);
                    }
                    break;
                case "RECURRING_INSTALLMENT_FAILED":
                    logTransaction($params["paymentmethod"], $_POST, "Recurring Payment Failed", $params);
                    break;
                case "RECURRING_INSTALLMENT_SUCCESS":
                    checkCbTransID($recurringTransactionId);
                    if (!$invoiceId && !$serviceId) {
                        logTransaction($params["paymentmethod"], array_merge(["InvoiceLookup" => "No Service ID Found in Callback"], $_POST), "Recurring Error");
                    } else {
                        if (!$invoiceId) {
                            $message = "No invoice match found for Service ID " . $serviceId . " or Subscription ID";
                            logTransaction($params["paymentmethod"], array_merge(["InvoiceLookup" => $message], $_POST), "Recurring Error");
                        } else {
                            logTransaction($params["paymentmethod"], $_POST, "Recurring Success");
                            $amount = Helper::convertCurrency($amount, $currency, $invoice);
                            $invoice->addPayment($amount, $recurringTransactionId, 0, $gatewayModuleName);
                            self::saveRecurringSaleId($hostingAndAddonIds, $recurringTransactionId);
                        }
                    }
                    break;
                default:
                    $notificationOnly = true;
                    if ($notificationOnly) {
                        logTransaction($params["paymentmethod"], $_POST, "Notification Only", $params);
                    }
            }
        }
    }

    public function clientCallback($params = [])
    {
        $invoiceId = \App::getFromRequest("merchant_order_id");
        $tcoOrderNumber = \App::getFromRequest("order_number");
        $total = \App::getFromRequest("total");
        if (\App::isInRequest("product_description")) {
            $invoiceId = \App::getFromRequest("product_description");
        }
        if (!$params["demomode"]) {
            $hashSid = $params["vendornumber"];
            $postedSid = \App::getFromRequest("sid");
            if ($hashSid != $postedSid) {
                logTransaction($params["paymentmethod"], $_REQUEST, "SID Mismatch");
                return NULL;
            }
            $hashSecretWord = $params["secretword"];
            $hashToValidate = strtoupper(md5($hashSecretWord . $hashSid . $tcoOrderNumber . $total));
            if (hash_equals($hashToValidate, \App::getFromRequest("key")) === false) {
                logTransaction($params["paymentmethod"], $_REQUEST, "MD5 Hash Failure");
                return NULL;
            }
        }
        logTransaction($params["paymentmethod"], $_REQUEST, "Client Redirect", $params);
        $systemUrl = \App::getSystemURL();
        $companyName = \WHMCS\Config\Setting::getValue("CompanyName");
        $redirectUri = $systemUrl . "clientarea.php?action=invoices";
        if (\App::getFromRequest("credit_card_processed") == "Y" && $params["skipfraudcheck"]) {
            $redirectUri = $systemUrl . "viewinvoice.php?id=" . $invoiceId . "&paymentsuccess=true";
        } else {
            if (\App::getFromRequest("credit_card_processed") == "Y") {
                $redirectUri = $systemUrl . "viewinvoice.php?id=" . $invoiceId . "&pendingreview=true";
            } else {
                logTransaction($params["paymentmethod"], $_REQUEST, "Unsuccessful", $params);
            }
        }
        header("Location: " . $redirectUri);
    }

    protected static function findInvoiceId($serviceId, $transactionId)
    {
        $itemType = substr($serviceId, 0, 1);
        switch ($itemType) {
            case "H":
                $types = ["Hosting"];
                break;
            case "A":
                $types = ["Addon"];
                break;
            case "D":
                $types = ["Domain", "DomainTransfer", "DomainRegister"];
                foreach ($types as $type) {
                    $invoiceId = findInvoiceID(substr($serviceId, 1), $transactionId, $type);
                    if ($invoiceId) {
                        return $invoiceId;
                    }
                }
                return NULL;
                break;
            case "i":
            case "I":
                $parts = explode("_", substr($serviceId, 1));
                return $parts[0];
                break;
        }
    }

    protected static function saveRecurringSaleId($ids, $subscriptionId)
    {
        if (is_array($ids["hosting"]) && count($ids["hosting"])) {
            \WHMCS\Service\Service::whereIn("id", $ids["hosting"])->update(["subscriptionid" => $subscriptionId]);
        }
        if (is_array($ids["addon"]) && count($ids["addon"])) {
            foreach ($ids["addon"] as $id) {
                try {
                    $addon = \WHMCS\Service\Addon::findOrFail($id);
                    $addon->serviceProperties->save(["subscriptionid" => $subscriptionId]);
                } catch (\Exception $e) {
                }
            }
        }
    }
}
