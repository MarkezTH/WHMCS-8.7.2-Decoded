<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function paypalcheckout_MetaData()
{
    return ["DisableCheckoutAutoRedirect" => true, "SupportsEmailPaymentLink" => false, "noCurrencyConversion" => true];
}

function paypalcheckout_config()
{
    $config = ["FriendlyName" => ["Type" => "System", "Value" => "PayPal"], "clientId" => ["FriendlyName" => "Client ID", "Type" => "text", "Size" => "70", "ReadOnly" => true], "clientSecret" => ["FriendlyName" => "Client Secret", "Type" => "password", "Size" => "70", "ReadOnly" => true], "sandboxClientId" => ["FriendlyName" => "Client ID (Sandbox)", "Type" => "text", "Size" => "70", "ReadOnly" => true], "sandboxClientSecret" => ["FriendlyName" => "Client Secret (Sandbox)", "Type" => "password", "Size" => "70", "ReadOnly" => true], "sandbox" => ["FriendlyName" => "Sandbox Mode", "Type" => "yesno", "Description" => "Check to use <a href=\"https://developer.paypal.com/\">PayPal's Sandbox Test Environment</a>"]];
    if (!App::isSSLAvailable()) {
        $config["PPSSLError"] = ["FriendlyName" => "SSL Required", "Description" => WHMCS\View\Helper::alert(AdminLang::trans("paypalCheckout.sslRequired", [":configGeneral" => "href=\"configgeneral.php\"", ":learnMore" => "href=\"https://go.whmcs.com/1705/ssl-learn-more\""]), "danger", "top-margin-5 bottom-margin-5")];
    }
    return $config;
}

function paypalcheckout_admin_area_actions($params)
{
    if (!App::isSSLAvailable()) {
        return [];
    }
    $onboarding = new WHMCS\Module\Gateway\Paypalcheckout\PaypalOnboarding();
    if (isset($params["featuredPage"]) && $params["featuredPage"]) {
        $onboarding->isFeaturedPage();
    }
    $actionsArray = [];
    if (empty($params["clientId"]) || empty($params["clientSecret"])) {
        $actionsArray[] = ["label" => AdminLang::trans("paypalCheckout.linkAccount"), "href" => $onboarding->getLinkUri(), "id" => "btnPayPalOnboardViaModule", "target" => "PPFrame", "dataAttributes" => ["paypal-onboard-complete" => $onboarding->getOnboardCompleteJsFunctionName(), "paypal-button" => "true", "securewindowmsg" => "Don't see the secure PayPal browser? We'll help you re-launch the window to complete your flow. You might need to enable pop-ups in your browser in order to continue."], "additionalHtmlOutput" => $onboarding->getJs()];
    } else {
        $actionsArray[] = ["label" => AdminLang::trans("paypalCheckout.unlinkAccount"), "href" => "javascript:paypalUnlinkAccount('live')", "id" => "btnPayPalOffboardViaModule", "additionalHtmlOutput" => $onboarding->getOffboardJs()];
    }
    if (empty($params["sandboxClientId"]) || empty($params["sandboxClientSecret"])) {
        $onboarding->enableSandbox();
        $actionsArray[] = ["label" => AdminLang::trans("paypalCheckout.linkSandboxAccount"), "href" => $onboarding->getLinkUri(), "id" => "btnPayPalSandboxOnboardViaModule", "target" => "PPFrame", "dataAttributes" => ["paypal-onboard-complete" => $onboarding->getOnboardCompleteJsFunctionName(), "paypal-button" => "true", "securewindowmsg" => "Don't see the secure PayPal browser? We'll help you re-launch the window to complete your flow. You might need to enable pop-ups in your browser in order to continue."], "additionalHtmlOutput" => $onboarding->getJs()];
    } else {
        $actionsArray[] = ["label" => AdminLang::trans("paypalCheckout.unlinkSandboxAccount"), "href" => "javascript:paypalUnlinkAccount('sandbox')", "id" => "btnPayPalSandboxOffboardViaModule", "additionalHtmlOutput" => $onboarding->getOffboardJs()];
    }
    return $actionsArray;
}

function paypalcheckout_onboarding_response_handler($params)
{
    $request = $params["request"];
    $gatewayInterface = $params["gatewayInterface"];
    $clientId = $request->get("clientid");
    $clientSecret = $request->get("clientsecret");
    $sandbox = $request->get("sandbox");
    if ($gatewayInterface->isLoadedModuleActive()) {
        $method = "updateConfiguration";
    } else {
        $method = "activate";
    }
    if ($sandbox) {
        $dataToSave = ["sandboxClientId" => $clientId, "sandboxClientSecret" => $clientSecret, "sandbox" => "on"];
    } else {
        $dataToSave = ["clientId" => $clientId, "clientSecret" => $clientSecret];
    }
    $gatewayInterface->{$method}($dataToSave);
    $deleteType = !$sandbox ? "accessToken-production-%" : "accessToken-sandbox-%";
    WHMCS\Module\GatewaySetting::gateway("paypalcheckout")->where("setting", "like", $deleteType)->delete();
    $url = App::getSystemURL() . "modules/gateways/callback/paypalwebhooks.php";
    $eventTypes = ["PAYMENT.AUTHORIZATION.CREATED", "PAYMENT.AUTHORIZATION.VOIDED", "PAYMENT.CAPTURE.COMPLETED", "PAYMENT.CAPTURE.DENIED", "PAYMENT.CAPTURE.PENDING", "PAYMENT.CAPTURE.REFUNDED", "PAYMENT.CAPTURE.REVERSED", "PAYMENT.SALE.COMPLETED", "PAYMENT.SALE.REFUNDED", "PAYMENT.SALE.REVERSED", "BILLING_AGREEMENTS.AGREEMENT.CREATED", "BILLING_AGREEMENTS.AGREEMENT.CANCELLED", "BILLING.SUBSCRIPTION.CANCELLED", "BILLING.SUBSCRIPTION.CREATED", "BILLING.SUBSCRIPTION.RE-ACTIVATED", "BILLING.SUBSCRIPTION.SUSPENDED", "BILLING.SUBSCRIPTION.UPDATED", "CUSTOMER.DISPUTE.CREATED", "CUSTOMER.DISPUTE.RESOLVED", "CUSTOMER.DISPUTE.UPDATED", "CHECKOUT.ORDER.COMPLETED", "CHECKOUT.ORDER.APPROVED", "CHECKOUT.ORDER.PROCESSED"];
    $webhookId = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->createWebhook($url, $eventTypes);
    if ($sandbox) {
        WHMCS\Config\Setting::setValue("PayPalCheckoutSandboxWebhookId", $webhookId);
    } else {
        WHMCS\Config\Setting::setValue("PayPalCheckoutWebhookId", $webhookId);
    }
}

function paypalcheckout_link($params)
{
    $loggedInClient = Auth::client();
    if (WHMCS\User\Admin::getAuthenticatedUser() && (!$loggedInClient || $loggedInClient->id != $params["clientdetails"]["userid"])) {
        return "<div class=\"alert alert-warning\" style=\"margin:5px 0;padding:5px 10px;font-size:0.95em;\">\n            You are viewing this invoice as an admin user.<br>\n            Login as the client to make a payment.\n        </div>";
    }
    $token = generate_token("plain");
    $clientId = $params["sandbox"] ? $params["sandboxClientId"] : $params["clientId"];
    $invoiceId = $params["invoiceid"];
    $amount = WHMCS\View\Formatter\Price::adjustDecimals($params["amount"], $params["currency"]);
    $currency = $params["currency"];
    $clientDetails = $params["clientdetails"];
    $companyName = $params["companyname"];
    $cart = $params["cart"];
    $subscriptionOutput = paypalcheckout_link_subscription_notice($invoiceId);
    $routeCreateOrder = routePathWithQuery("paypal-checkout-create-order", [], ["invoiceid" => $invoiceId]);
    $routeVerifyPayment = routePathWithQuery("paypal-checkout-verify-payment", [], ["invoiceid" => $invoiceId]);
    if ($subscriptionOutput) {
        return $subscriptionOutput . "<div style=\"max-width:200px;margin:0 auto;\">" . (new WHMCS\Module\Gateway\Paypalcheckout\PaypalJsClient())->addParam("client-id", $clientId)->addParam("disable-card", "visa,mastercard,amex,discover,jcb,elo,hiper")->addParam("currency", $currency)->setStyleLabel("pay")->addCreateOrder($routeCreateOrder, $token, true)->addOnApprove($routeVerifyPayment, $token, $invoiceId)->render(true) . "</div>";
    }
    $paypalJsClient = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalJsClient())->addParam("client-id", $clientId)->addParam("disable-card", "visa,mastercard,amex,discover,jcb,elo,hiper")->addParam("currency", $currency);
    $invoiceOverdue = false;
    $forceOneTime = false;
    if (isset($invoiceId)) {
        $invoiceValues = getRecurringBillingValues($invoiceId);
        if ($invoiceValues["overdue"] === true) {
            $invoiceOverdue = true;
            $forceOneTime = true;
        }
    }
    if (paypalcheckout_isRecurringPossible($cart) && $invoiceOverdue === false) {
        $paypalJsClient->addCreateSubscription($invoiceId, $routeCreateOrder, $token, $companyName, $clientDetails["firstname"], $clientDetails["lastname"], $clientDetails["email"]);
    } else {
        $paypalJsClient->addCreateOrder($routeCreateOrder, $token, $forceOneTime);
    }
    return "<div style=\"max-width:200px;margin:0 auto;\">" . $paypalJsClient->addOnApprove($routeVerifyPayment, $token, $invoiceId)->render() . "</div>";
}

function paypalcheckout_refund($params)
{
    $response = [];
    try {
        $amount = WHMCS\View\Formatter\Price::adjustDecimals($params["amount"], $params["currency"]);
        $currencyCode = $params["currency"];
        $paypalApi = new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi();
        $response = $paypalApi->refundPayment($params["invoiceid"], $params["transid"], $amount, $currencyCode);
        $data = $response->getResponse();
        if ($response->isError()) {
            paypalcheckout_apiErrorHandler($data);
        }
        $response = $paypalApi->getRefundData($data->id);
        $feeData = $response->getResponse();
        if ($response->isError()) {
            paypalcheckout_apiErrorHandler($feeData);
        }
        return ["status" => "success", "rawdata" => ["action" => "refund", "transactionID" => $params["transid"], "refundID" => $data->id], "transid" => $data->id, "fees" => $feeData->seller_payable_breakdown->paypal_fee->value];
    } catch (Exception $e) {
        return ["status" => "error", "rawdata" => ["action" => "refund", "transactionID" => $params["transid"], "response" => $response, "error" => $e->getMessage()]];
    }
}

function paypalcheckout_cancelSubscription($params)
{
    try {
        $response = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->cancelSubscription($params["subscriptionID"]);
        $data = $response->getResponse();
        if ($response->isError()) {
            $throw = true;
            $details = isset($data->details[0]) ? $data->details[0] : NULL;
            if (isset($details->issue) && $details->issue === "INVALID_RESOURCE_ID") {
                $gatewayInterface = new WHMCS\Module\Gateway();
                if ($gatewayInterface->load("paypal") && $gatewayInterface->isActive("paypal")) {
                    $passedParams = ["subscriptionID" => $params["subscriptionID"]];
                    $cancelResult = $gatewayInterface->call("cancelSubscription", $passedParams);
                    if (is_array($cancelResult) && $cancelResult["status"] == "success") {
                        $throw = false;
                        $data = $cancelResult;
                    }
                }
            }
            if ($throw) {
                paypalcheckout_apiErrorHandler($data);
            }
        }
        return ["status" => "success", "rawdata" => $data];
    } catch (WHMCS\Exception $e) {
        return ["status" => "error", "errorMsg" => $e->getMessage(), "rawdata" => $data];
    }
}

function paypalcheckout_express_checkout_initiate($params)
{
    $cart = $params["cart"];
    if (paypalcheckout_isRecurringPossible($cart)) {
        return "";
    }
    $routeCreateOrder = routePath("paypal-checkout-create-order");
    $routeValidateOrder = routePath("paypal-checkout-validate-order");
    $clientId = $params["sandbox"] ? $params["sandboxClientId"] : $params["clientId"];
    $companyName = $params["companyname"];
    $token = generate_token("plain");
    $paypalJsClient = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalJsClient())->addParam("client-id", $clientId)->addParam("currency", $params["currency"])->addParam("disable-card", "visa,mastercard,amex,discover,jcb,elo,hiper")->addParam("intent", "authorize")->addParam("commit", "false");
    if (paypalcheckout_isRecurringPossible($cart)) {
        $paypalJsClient->addCreateSubscription(0, $routeCreateOrder, $token, $companyName, "", "", "");
    } else {
        $paypalJsClient->addCreateOrder($routeCreateOrder, $token);
    }
    return $paypalJsClient->addOnApprove($routeValidateOrder, $token, 0, Lang::trans("redirectingToCompleteCheckout"))->render();
}

function paypalcheckout_express_checkout_checkout_output($params)
{
    $paypalEmail = trim($params["metaData"]["paypalEmail"]);
    if (empty($paypalEmail)) {
        $paypalEmail = "Unknown";
    }
    return "<div style=\"margin:5px;padding:0;text-align:center;\"><img src=\"modules/gateways/paypal/logo.png\" style=\"padding:10px;max-width:150px;\"></div><div style=\"margin:5px;padding:0;text-align:center;\">" . Lang::trans("paymentPreApproved", [":gateway" => $params["name"]]) . "</div>" . "<div style=\"margin:5px;padding:0 0 10px 0;text-align:center;\">" . Lang::trans("paypalEmailAddress") . ": <strong>" . WHMCS\Input\Sanitize::encode($paypalEmail) . "</strong>" . "</div>";
}

function paypalcheckout_express_checkout_capture($params)
{
    $invoiceId = $params["invoiceid"];
    $invoiceNumber = $params["invoicenum"];
    $amount = WHMCS\View\Formatter\Price::adjustDecimals($params["amount"], $params["currency"]);
    $currency = $params["currency"];
    $paypalOrderId = $params["expressCheckout"]["orderId"];
    $paypalSubscriptionId = $params["expressCheckout"]["subscriptionId"];
    $paypalApi = new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi();
    $response = $paypalApi->getOrderDetails($paypalOrderId);
    if ($response->status == "COMPLETED") {
        throw new WHMCS\Exception("Order already captured.");
    }
    if ($response->status == "APPROVED") {
        if ($paypalSubscriptionId) {
            return ["status" => "pending", "subscriptionId" => $paypalSubscriptionId];
        }
        $authId = $paypalApi->authorizeOrder($paypalOrderId);
        $captureResponse = $paypalApi->capturePayment($authId, $amount, $currency, $invoiceNumber);
        if ($captureResponse->status == "COMPLETED") {
            $captureDetails = $paypalApi->getCaptureDetails($captureResponse->id);
            if ($captureDetails->status == "COMPLETED") {
                return ["status" => "completed", "transid" => $captureDetails->id, "amount" => $captureDetails->amount->value, "currency" => $captureDetails->amount->currency_code, "fees" => $captureDetails->seller_receivable_breakdown->paypal_fee->value];
            }
            throw new WHMCS\Exception("Unexpected capture status: " . $captureDetails->status);
        }
        if ($captureResponse->status == "PENDING") {
            return ["status" => "pending"];
        }
        throw new WHMCS\Exception("Capture failed with status: " . $captureResponse->status);
    }
    throw new WHMCS\Exception("Order not in approved state.");
}

function paypalcheckout_post_checkout($params)
{
    $cart = $params["cart"];
    $invoice = $cart->getInvoiceModel();
    if (!$cart->isRecurring()) {
        throw new WHMCS\Exception\Gateways\RedirectToInvoice();
    }
    $subscriptionIds = $invoice->getSubscriptionIds()->toArray();
    if (!empty($subscriptionIds)) {
        header("Location: " . $invoice->getViewInvoiceUrl(["paymentsuccess" => true]));
        WHMCS\Terminus::getInstance()->doExit();
    }
    $clientId = $params["sandbox"] ? $params["sandboxClientId"] : $params["clientId"];
    try {
        $planId = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalController())->createPlanId($cart, $clientId);
    } catch (Exception $e) {
        logActivity("PayPal Checkout Subscription Error - Failed to create plan: " . $e->getMessage());
        return NULL;
    }
    $client = $cart->client;
    $companyName = $params["companyname"];
    $returnUrl = fqdnRoutePath("paypal-checkout-verify-subscription-setup", $params["invoiceid"]);
    $cancelUrl = $params["returnurl"] . "&paymentfailed=true";
    $response = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->createSubscription($params["invoiceid"], $planId, $client, $companyName, $returnUrl, $cancelUrl);
    if ($response->isError()) {
        logActivity("PayPal Checkout Subscription Error - Failed to create subscription. Please refer to the module log for further details.");
    } else {
        $subStatus = $response->getFromResponse("status");
        $subId = $response->getFromResponse("id");
        $subLinks = $response->getFromResponse("links");
        $invoice->saveSubscriptionId($subId);
        if ($subStatus != "APPROVAL_PENDING") {
            logActivity("PayPal Checkout Subscription Error - Unexpected status returned: " . $subStatus);
        } else {
            $approvalUrl = NULL;
            foreach ($subLinks as $link) {
                if ($link->rel == "approve") {
                    $approvalUrl = $link->href;
                }
            }
            if (is_null($approvalUrl)) {
                logActivity("PayPal Checkout Subscription Error - Did not get approval url");
                return NULL;
            }
            header("Location: " . $approvalUrl);
            exit;
        }
    }
}

function paypalcheckout_get_subscription_info($params)
{
    $response = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->getSubscriptionDetails($params["subscriptionId"]);
    $data = $response->getResponse();
    if ($response->isError()) {
        paypalcheckout_apiErrorHandler($data);
    }
    $sub_id = $data->id;
    $status = $data->status;
    $start_time = $data->start_time;
    $billing_info = $data->billing_info ?? NULL;
    $lastPaymentDescription = "Never";
    $nextPaymentDescription = "Subscription not yet active";
    $last_payment = $billing_info->last_payment ?? NULL;
    if ($last_payment) {
        $last_payment_amount_value = $last_payment->amount->value;
        $last_payment_amount_currency = $last_payment->amount->currency_code;
        $last_payment_time = $last_payment->time;
        $lastPaymentDescription = $last_payment_amount_value . " " . $last_payment_amount_currency . " on " . WHMCS\Carbon::parse($last_payment_time)->toClientDateFormat() . " (" . WHMCS\Carbon::parse($last_payment_time)->diffForHumans() . ")";
    }
    $next_billing_time = $billing_info->next_billing_time ?? NULL;
    if ($next_billing_time) {
        $nextPaymentDescription = WHMCS\Carbon::parse($next_billing_time)->toClientDateFormat() . " (" . WHMCS\Carbon::parse($next_billing_time)->diffForHumans() . ")";
    }
    $failed_payments_count = (int) $billing_info->failed_payments_count ?? NULL;
    return ["Subscription ID" => $sub_id, "Status" => $status, "Last Payment" => $lastPaymentDescription, "Next Payment Date" => $nextPaymentDescription, "Subscription Start Date" => WHMCS\Carbon::parse($start_time)->toClientDateFormat() . " (" . WHMCS\Carbon::parse($start_time)->diffForHumans() . ")", "Failed Payments Count" => $failed_payments_count];
}

function paypalcheckout_config_post_save($params = [])
{
    WHMCS\Payment\PaymentGatewayProductMapping::gateway("paypal")->delete();
}

function paypalcheckout_get_subscription_transactions($params)
{
}

function paypalcheckout_link_subscription_notice($invoiceId)
{
    $subscriptionIds = WHMCS\Billing\Invoice::find($invoiceId)->getSubscriptionIds(["paypalcheckout", "paypal"]);
    $subscriptionDetails = "";
    $atLeastOneActive = false;
    if (0 < $subscriptionIds->count()) {
        foreach ($subscriptionIds as $subscriptionId) {
            $params["subscriptionId"] = $subscriptionId;
            try {
                $info = paypalcheckout_get_subscription_info($params);
                if ($info["Status"] == "ACTIVE") {
                    $atLeastOneActive = true;
                }
                foreach ($info as $key => $value) {
                    $langId = "subscription." . str_replace("_", "", strtolower($key));
                    $keyTranslation = Lang::trans($langId);
                    if (!$keyTranslation || $keyTranslation == $langId) {
                        $keyTranslation = $key;
                    }
                    $subscriptionDetails .= $keyTranslation . ": " . $value . "<br>";
                }
            } catch (WHMCS\Exception $e) {
                $subscriptionDetails .= "<div class=\"alert alert-danger\">\n                    " . Lang::trans("subscription.errorFetchingDetails") . "\n                </div>";
            }
            $subscriptionDetails .= "<hr>";
        }
    }
    if ($atLeastOneActive) {
        $moreDetails = Lang::trans("subscription.moreDetails");
        $makePayment = Lang::trans("subscription.makePayment");
        $close = Lang::trans("close");
        return "<div class=\"alert alert-success\" style=\"margin:5px 0;padding:5px;font-size:0.95em;\">\n                    <i class=\"fa fa-check fa-fw\"></i>\n                    " . Lang::trans("subscription.active") . "\n                </div>\n                <div style=\"margin:5px 0;font-size:0.9em;\">\n                    " . Lang::trans("subscription.manual") . "\n                    <a href=\"#\" class=\"alert-link\" data-toggle=\"modal\" data-target=\"#paypalSubDetails\">" . $moreDetails . "</a> | <a href=\"#\" onclick=\"jQuery('#paypal-button-container').slideDown();return false;\">" . $makePayment . "</a>\n                </div>\n\n<div class=\"modal fade\" id=\"paypalSubDetails\" tabindex=\"-1\" role=\"dialog\">\n  <div class=\"modal-dialog\" role=\"document\">\n    <div class=\"modal-content\">\n      <div class=\"modal-header\">\n        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"" . $close . "\"><span aria-hidden=\"true\">&times;</span></button>\n        <h4 class=\"modal-title\">" . Lang::trans("subscription.paypalDetails") . "</h4>\n      </div>\n      <div class=\"modal-body\">\n        <p>" . Lang::trans("subscription.subscriptionWarning") . "</p>\n        <hr>\n        " . $subscriptionDetails . "\n      </div>\n      <div class=\"modal-footer\">\n        <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">" . $close . "</button>\n      </div>\n    </div>\n  </div>\n</div>";
    }
}

function paypalcheckout_isRecurringPossible(WHMCS\Cart\CartCalculator $cart)
{
    if (!$cart->isRecurring()) {
        return false;
    }
    $firstRecurringItem = $cart->getFirstRecurringItem();
    if ($firstRecurringItem->billingCycle == "annually" && 1 < $firstRecurringItem->billingPeriod) {
        return false;
    }
    if ($firstRecurringItem->hasInitialPeriod() && $firstRecurringItem->initialCycle == "days" && 365 < $firstRecurringItem->initialPeriod) {
        return false;
    }
    if ($firstRecurringItem->getAmount()->getValue() == 0) {
        return false;
    }
    return true;
}

function paypalcheckout_apiErrorHandler($responseData)
{
    $details = isset($responseData->details[0]) ? $responseData->details[0] : NULL;
    if (isset($details->issue)) {
        $issueDescriptor = $details->issue;
        if ($issueDescriptor == "PERMISSION_DENIED") {
            throw new WHMCS\Exception\Module\ApiException("The subscription ID requested either does not exist or does not belong to this PayPal account.");
        }
        throw new WHMCS\Exception\Module\ApiException($issueDescriptor . " - " . $details->description);
    }
    if (isset($responseData->name)) {
        throw new WHMCS\Exception\Module\ApiException($responseData->name . " - " . $responseData->message);
    }
    throw new WHMCS\Exception\Module\ApiException("An unknown error occurred. Please consult the module log.");
}

function paypalcheckout_ListDisputes($DisputeCollection)
{
    $nextPageToken = NULL;
    $responseItems = collect();
    $i = 0;
    while ($i < 10) {
        $response = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->listDisputes($nextPageToken);
        $responseItems->merge($response->items);
        $nextLink = collect($response->links)->where("rel", "next")->first();
        if ($nextLink) {
            parse_str(parse_url($nextLink->href, PHP_URL_QUERY), $queryParams);
            $nextPageToken = $queryParams["next_page_token"];
            $i++;
        }
    }
    $returnData = [];
    $transientDataCollection = collect(json_decode(WHMCS\TransientData::getInstance()->retrieve("PaypalDisputeData"), true));
    foreach ($response->items as $disputeItem) {
        $transientDataItem = $transientDataCollection->firstWhere("disputeId", $disputeItem->dispute_id);
        if (is_null($transientDataItem)) {
            $disputeDetailsResponse = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->getDisputeDetails($disputeItem->dispute_id);
            $transactionId = collect($disputeDetailsResponse->disputed_transactions)->first()->seller_transaction_id;
            $transientDataCollection->push(["disputeId" => $disputeItem->dispute_id, "transactionId" => $transactionId]);
        } else {
            $transactionId = $transientDataItem["transactionId"];
        }
        if ($transactionId) {
            $paymentDispute = WHMCS\Billing\Payment\Dispute::factory($disputeItem->dispute_id, $disputeItem->dispute_amount->value, $disputeItem->dispute_amount->currency_code, $transactionId, WHMCS\Carbon::parse($disputeItem->create_time), WHMCS\Carbon::parse($disputeItem->seller_response_due_date ?? $disputeItem->buyer_response_due_date), strtolower($disputeItem->reason), strtolower($disputeItem->status));
            $paymentDispute->setGateway("paypalcheckout")->setIsClosable(in_array($response->status, ["RESOLVED", "PRE_ARBITRATION"]));
            $returnData[] = $paymentDispute;
        }
    }
    $data = json_encode($transientDataCollection->toArray());
    WHMCS\TransientData::getInstance()->store("PaypalDisputeData", $data, 31536000);
    return WHMCS\Billing\Payment\Dispute\DisputeCollection::factoryFromItems(...$returnData);
}

function paypalcheckout_FetchDispute($Dispute, $params)
{
    $response = (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->getDisputeDetails($params["disputeId"]);
    $paypalDispute = new WHMCS\Module\Gateway\Paypalcheckout\PaypalDispute();
    $paymentDispute = WHMCS\Billing\Payment\Dispute::factory($response->dispute_id, $response->dispute_amount->value, $response->dispute_amount->currency_code, collect($response->disputed_transactions)->first()->seller_transaction_id, WHMCS\Carbon::parse($response->create_time), WHMCS\Carbon::parse($response->seller_response_due_date ?? $response->buyer_response_due_date), strtolower($response->reason), strtolower($response->status));
    $manageHref = WHMCS\Module\GatewaySetting::getForGateway("paypalcheckout")["sandbox"] ? "https://sandbox.paypal.com/" : "https://paypal.com/";
    $paymentDispute->setGateway("paypalcheckout")->setIsClosable(in_array($response->status, ["RESOLVED", "PRE_ARBITRATION"]))->setManageHref($manageHref . "resolutioncenter/" . $response->dispute_id);
    $evidence = [];
    foreach ($paypalDispute->getEvidenceTypes(strtolower($response->reason)) as $typeKey) {
        $paymentDispute->setEvidenceType($typeKey, $paypalDispute->getFieldType($typeKey))->setCustomData($typeKey, $paypalDispute->getCustomData($typeKey));
        $evidence[] = ["name" => $typeKey, "value" => NULL];
    }
    $paymentDispute->setVisibleTypes($paypalDispute->getVisibleTypes())->setEvidence(array_merge($paypalDispute->parseResponseEvidence($response), $evidence));
    return $paymentDispute;
}

function paypalcheckout_CloseDispute($params)
{
    (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->acceptClaim($params["disputeId"]);
}

function paypalcheckout_UpdateDispute($params)
{
    $paypalDispute = new WHMCS\Module\Gateway\Paypalcheckout\PaypalDispute();
    $file = $params["evidence"]["file"] ?? NULL;
    if ($file) {
        unset($params["evidence"]["file"]);
    }
    $evidences = $paypalDispute->buildEvidencesObject($params["evidence"]);
    (new WHMCS\Module\Gateway\Paypalcheckout\PaypalApi())->submitEvidence($params["disputeId"], $evidences, $file);
}
