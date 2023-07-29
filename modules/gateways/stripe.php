<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function stripe_MetaData()
{
    return ["APIVersion" => 1.1];
}

function _stripe_isNoDecimalCurrency($currencyCode)
{
    return in_array(strtoupper($currencyCode), WHMCS\Module\Gateway\Stripe\Constant::STRIPE_CURRENCIES_NO_DECIMALS);
}

function stripe_config()
{
    $invalidDescriptorChars = implode(", ", stripe_statement_descriptor_invalid_characters());
    return ["FriendlyName" => ["Type" => "System", "Value" => "Stripe"], "publishableKey" => ["FriendlyName" => "Stripe Publishable API Key", "Type" => "text", "Size" => "30", "Description" => "Your publishable API key identifies your website to Stripe during communications. This can be obtained from <a href=\"https://dashboard.stripe.com/account/apikeys\" class=\"autoLinked\">here</a>"], "secretKey" => ["FriendlyName" => "Stripe Secret API Key", "Type" => "text", "Size" => "30", "Description" => "Your secret API Key ensures only communications from Stripe are validated."], "statementDescriptor" => ["FriendlyName" => "Statement Descriptor Suffix", "Type" => "text", "Size" => 25, "Default" => "Invoice {InvoiceNumber}", "Description" => "Available merge field tags: <strong>{CompanyName} {InvoiceNumber}</strong>\n<div class=\"alert alert-info top-margin-5 bottom-margin-5\">\n    Displayed on your customer's credit card statement.<br />\n    <strong>Maximum length of 22 characters</strong>, and must not contain any of the following:\n    <span style=\"font-family: monospace\">" . $invalidDescriptorChars . "</span><br />\n    This will be appended to the statement descriptor defined in the Stripe Account.\n</div>"], "webhookEndpointSecret" => ["FriendlyName" => "Stripe WebHook Endpoint Secret", "Type" => "password", "Size" => "30", "Description" => "Automatically generated web-hook secret key for Live web-hooks."], "webhookEndpointSandboxSecret" => ["FriendlyName" => "Stripe WebHook Endpoint Secret (Test/Sandbox)", "Type" => "password", "Size" => "30", "Description" => "Automatically generated web-hook secret key for Sandbox web-hooks."], "applePay" => ["FriendlyName" => "Allow Payment Request Buttons", "Type" => "yesno", "Description" => "Check to enable showing the Payment Request buttons on supported devices. <a href=\"https://docs.whmcs.com/Stripe#Payment_Request_Button\" class=\"autoLinked\">Learn More</a>"]];
}

function stripe_config_validate($params = [])
{
    if (isset($params["statementDescriptor"]) && 0 < strlen($params["statementDescriptor"])) {
        $descriptorCheck = str_replace(stripe_statement_descriptor_invalid_characters(), "", $params["statementDescriptor"]);
        if (strlen($params["statementDescriptor"]) != strlen($descriptorCheck)) {
            throw new WHMCS\Exception\Module\InvalidConfiguration("Invalid characters present in Statement Descriptor Suffix");
        }
        unset($descriptorCheck);
    }
    try {
        if ($params["publishableKey"] && substr($params["publishableKey"], 0, 3) === "pk_" && $params["secretKey"] && substr($params["secretKey"], 0, 3) === "sk_") {
            stripe_start_stripe($params);
            Stripe\Account::retrieve();
            Stripe\Stripe::setApiKey($params["publishableKey"]);
            Stripe\Account::retrieve();
        } else {
            throw new WHMCS\Exception\Module\InvalidConfiguration("Please ensure your Stripe API keys are correct and try again.");
        }
    } catch (Exception $e) {
        if (substr($e->getMessage(), 0, 55) != "This API call cannot be made with a publishable API key") {
            throw new WHMCS\Exception\Module\InvalidConfiguration($e->getMessage());
        }
    }
}

function stripe_config_post_save($params = [])
{
    if (array_key_exists("secretKey", $params) && $params["secretKey"]) {
        stripe_start_stripe($params);
        $notificationUrl = App::getSystemURL() . "modules/gateways/callback/stripe.php";
        $webHooks = Stripe\WebhookEndpoint::all();
        $liveMode = strpos($params["secretKey"], "sk_live") !== false;
        foreach ($webHooks->data as $webHookData) {
            if ($webHookData->url === $notificationUrl && $webHookData->status === "enabled" && $webHookData->livemode === $liveMode) {
                $webHook = $webHookData;
            }
        }
        $webhookEndpointSecret = $liveMode ? $params["webhookEndpointSecret"] : $params["webhookEndpointSandboxSecret"];
        if (empty($webHook) || empty($webhookEndpointSecret)) {
            try {
                $webHook = Stripe\WebhookEndpoint::create(["url" => $notificationUrl, "enabled_events" => ["customer.source.updated", "customer.card.updated", "payment_method.updated"]]);
            } catch (Exception $e) {
                if ($e->getStripeCode() == "url_invalid") {
                    throw new Exception(AdminLang::trans("error.invalidSystemUrl", [":link" => "<a href=\"configgeneral.php\" class=\"autoLinked\">" . AdminLang::trans("global.clickhere") . "</a>"]));
                }
                throw $e;
            }
            if ($liveMode) {
                WHMCS\Module\GatewaySetting::setValue("stripe", "webhookEndpointSecret", $webHook->secret);
            } else {
                WHMCS\Module\GatewaySetting::setValue("stripe", "webhookEndpointSandboxSecret", $webHook->secret);
            }
        }
    }
}

function stripe_deactivate($params = [])
{
    try {
        $notificationUrl = App::getSystemURL() . "modules/gateways/callback/stripe.php";
        stripe_start_stripe($params);
        $webHooks = Stripe\WebhookEndpoint::all([]);
        foreach ($webHooks->data as $webHook) {
            if ($webHook->url == $notificationUrl && $webHook->status == "enabled") {
                $webHook->delete();
            }
        }
    } catch (Exception $e) {
    }
}

function stripe_capture($params = [])
{
    $stripeCustomer = $params["gatewayid"];
    $method = NULL;
    $intent = NULL;
    $newMethod = false;
    $return = [];
    stripe_start_stripe($params);
    if ($stripeCustomer) {
        $jsonCheck = json_decode(WHMCS\Input\Sanitize::decode($stripeCustomer), true);
        if (is_array($jsonCheck) && array_key_exists("customer", $jsonCheck)) {
            $stripeCustomer = $jsonCheck["customer"];
            $method = Stripe\PaymentMethod::retrieve($jsonCheck["method"]);
            if ($stripeCustomer != $method->customer) {
                $stripeCustomer = $method->customer;
                $return["gatewayid"] = json_encode(["customer" => $stripeCustomer, "method" => $method->id]);
            }
        }
    }
    if (substr($stripeCustomer, 0, 3) != "cus") {
        $stripeCustomer = "";
    }
    $amount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountOutbound($params["amount"], $params["currency"]);
    $client = WHMCS\User\Client::find($params["clientdetails"]["userid"]);
    $billingDetails = [];
    if ($params["cardnum"] || !$method) {
        $billingDetails = ["name" => $params["clientdetails"]["fullname"], "email" => $params["clientdetails"]["email"], "address" => ["country" => $params["clientdetails"]["country"]]];
        if (array_key_exists("address1", $params["clientdetails"])) {
            $billingDetails["address"]["line1"] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($params["clientdetails"]["address1"]);
        }
        if (array_key_exists("address2", $params["clientdetails"])) {
            $billingDetails["address"]["line2"] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($params["clientdetails"]["address2"]);
        }
        if (array_key_exists("city", $params["clientdetails"])) {
            $billingDetails["address"]["city"] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($params["clientdetails"]["city"]);
        }
        if (array_key_exists("state", $params["clientdetails"])) {
            $billingDetails["address"]["state"] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($params["clientdetails"]["state"]);
        }
        if (array_key_exists("postcode", $params["clientdetails"])) {
            $billingDetails["address"]["postal_code"] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($params["clientdetails"]["postcode"]);
        }
    }
    if ($params["cardnum"]) {
        try {
            $card = ["number" => $params["cardnum"], "exp_month" => substr($params["cardexp"], 0, 2), "exp_year" => substr($params["cardexp"], 2)];
            if ($params["cccvv"]) {
                $card["cvc"] = $params["cccvv"];
            }
            $method = Stripe\PaymentMethod::create(["type" => "card", "card" => $card, "billing_details" => $billingDetails]);
            $newMethod = true;
        } catch (Exception $e) {
            return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["error" => $e->getMessage()]]);
        }
    }
    if (!$method && $stripeCustomer) {
        $remoteCustomer = Stripe\Customer::retrieve($stripeCustomer);
        $source = $remoteCustomer->default_source;
        if ($source) {
            $method = Stripe\PaymentMethod::retrieve($source);
            $newMethod = true;
        }
    }
    if ($newMethod) {
        if (!$stripeCustomer) {
            try {
                $remoteToken = stripe_findFirstCustomerToken($client);
                if ($remoteToken && array_key_exists("customer", $remoteToken)) {
                    $stripeCustomer = $remoteToken["customer"];
                }
                if (!$stripeCustomer || substr($stripeCustomer, 0, 4) !== "cus_") {
                    $stripeCustomer = Stripe\Customer::create(WHMCS\Module\Gateway\Stripe\ApiPayload::customer($client, $client->id));
                    $stripeCustomer = $stripeCustomer->id;
                }
            } catch (Exception $e) {
                return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["error" => $e->getMessage()]]);
            }
        }
        $remoteToken = json_encode(["customer" => $stripeCustomer, "method" => $method->id]);
        $return = ["gatewayid" => $remoteToken];
        if ($stripeCustomer && !$method->customer) {
            try {
                $method->attach(["customer" => $stripeCustomer]);
            } catch (Exception $e) {
                $status = "error";
                if ($e instanceof WHMCS\Exception\Gateways\Declined || $e instanceof Stripe\Exception\CardException) {
                    $status = "declined";
                }
                return stripe_formatDataForGatewayLog(["status" => $status, "rawdata" => ["error" => $e->getMessage()], "declinereason" => $e->getMessage()]);
            }
        }
    }
    try {
        $paymentIntent = WHMCS\Session::getAndDelete("PaymentIntent" . $params["invoiceid"]);
        if (!$paymentIntent) {
            $paymentIntent = App::getFromRequest("remoteStorageToken");
        }
        if ($paymentIntent && substr($paymentIntent, 0, 2) == "pi") {
            $intent = Stripe\PaymentIntent::retrieve($paymentIntent);
            if ($intent->status == "requires_capture") {
                $intent->capture();
            }
            if ($intent->status != "succeeded") {
                throw new WHMCS\Exception\Gateways\Declined($intent->last_payment_error);
            }
            $charge = $intent->charges->data[0];
        } else {
            if (!$stripeCustomer || !$method) {
                throw new InvalidArgumentException("Missing Stripe Customer or Payment Method - Please Try Again");
            }
            $description = stripe_statement_descriptor($params);
            $intent = Stripe\PaymentIntent::create(["amount" => $amount, "currency" => strtolower($params["currency"]), "customer" => $method->customer, "payment_method" => $method->id, "description" => $description, "metadata" => ["id" => $params["invoiceid"], "invoiceNumber" => $params["invoicenum"]], "statement_descriptor_suffix" => $description, "confirm" => true, "off_session" => true]);
            if ($intent->status == "requires_capture") {
                $intent->capture();
            }
            if ($intent->status != "succeeded") {
                $error = $intent->last_payment_error;
                if (!$error) {
                    $error = "Cardholder Action Required";
                }
                throw new WHMCS\Exception\Gateways\Declined($error);
            }
            $charge = $intent->charges->data[0];
        }
        $transaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
        $transactionFee = WHMCS\Module\Gateway\Stripe\ApiPayload::transactionFee($transaction, WHMCS\Billing\Currency::find($params["convertto"] ?: $client->currencyId));
        $transactionId = $transaction->id;
        return stripe_formatDataForGatewayLog(array_merge(["status" => "success", "transid" => $transactionId, "amount" => $params["amount"], "fee" => $transactionFee, "rawdata" => ["charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()]], $return));
    } catch (Exception $e) {
        $status = "error";
        if ($e instanceof WHMCS\Exception\Gateways\Declined || $e instanceof Stripe\Exception\CardException) {
            $status = "declined";
        }
        $data = [];
        if ($intent && in_array($intent->status, ["requires_source_action", "requires_action", "requires_capture"])) {
            $intent->cancel(["cancellation_reason" => "abandoned"]);
            $data = $intent->jsonSerialize();
        }
        $data["error"] = $e->getMessage();
        if (method_exists($e, "getJsonBody")) {
            $data["detail"] = $e->getJsonBody();
        }
        return stripe_formatDataForGatewayLog(["status" => $status, "rawdata" => $data, "declinereason" => $e->getMessage()]);
    }
}

function stripe_post_checkout($params = [])
{
    $amount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountOutbound($params["amount"], $params["currency"]);
    $token = WHMCS\Session::getAndDelete("remoteStorageToken");
    WHMCS\Session::delete("cartccdetail");
    stripe_start_stripe($params);
    $client = WHMCS\User\Client::find($params["clientdetails"]["id"]);
    $stripeCustomer = "";
    $intent = NULL;
    $method = NULL;
    $returnData = [];
    if (substr($token, 0, 2) == "pi") {
        $intent = Stripe\PaymentIntent::retrieve($token);
        $method = Stripe\PaymentMethod::retrieve($intent->payment_method);
        if ($intent->customer) {
            $stripeCustomer = $intent->customer;
        }
        $description = stripe_statement_descriptor($params);
        if (in_array($intent->status, [Stripe\SetupIntent::STATUS_REQUIRES_PAYMENT_METHOD, Stripe\SetupIntent::STATUS_REQUIRES_CONFIRMATION, Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE])) {
            $intent->statement_descriptor_suffix = $description;
        }
        $intent->description = $description;
        $intent->save();
    }
    if (WHMCS\Session::getAndDelete("StripeCardId") === "new") {
        $token = $method;
    }
    if (substr($token, 0, 2) == "pm") {
        $method = Stripe\PaymentMethod::retrieve($token);
    }
    if (!$amount || $amount === "000") {
        $allowedCancellableStatuses = ["requires_payment_method", "requires_capture", "requires_confirmation", "requires_action"];
        if ($intent && in_array($intent->status, $allowedCancellableStatuses)) {
            $intent->cancel();
        }
    } else {
        try {
            if (!$stripeCustomer && $method && $method->customer) {
                $stripeCustomer = $method->customer;
            }
            if (!$stripeCustomer) {
                $stripeCustomer = $params["gatewayid"];
            }
            if ($stripeCustomer && substr($stripeCustomer, 0, 3) != "cus") {
                $jsonCheck = json_decode(WHMCS\Input\Sanitize::decode($stripeCustomer), true);
                if (is_array($jsonCheck) && array_key_exists("customer", $jsonCheck)) {
                    $stripeCustomer = $jsonCheck["customer"];
                }
            }
            if ($stripeCustomer && substr($stripeCustomer, 0, 3) != "cus") {
                $stripeCustomer = "";
            }
            if ($token) {
                if (!$method->customer) {
                    $method->attach(["customer" => $stripeCustomer]);
                }
                $card = $method->jsonSerialize()["card"];
                $remoteToken = json_encode(["customer" => $stripeCustomer, "method" => $method->id]);
                $returnData = ["cardnumber" => $card["last4"], "cardexpiry" => sprintf("%02d%02d", $card["exp_month"], substr($card["exp_year"], 2)), "cardtype" => ucfirst($card["brand"]), "gatewayid" => $remoteToken];
            }
            if (!$stripeCustomer) {
                return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["error" => "No Stripe Customer Details Found"]]);
            }
            if ($intent->status == "requires_capture") {
                $intent->capture(["amount_to_capture" => $amount]);
            }
            if ($intent->status != "succeeded") {
                throw new WHMCS\Exception\Gateways\Declined($intent->last_payment_error);
            }
            $charge = $intent->charges->data[0];
            $transaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
            $transactionFeeCurrency = WHMCS\Billing\Currency::where("code", strtoupper($transaction->fee_details[0]->currency))->first();
            $transactionId = $transaction->id;
            $transactionFee = 0;
            if ($transactionFeeCurrency) {
                $transactionFee = convertCurrency(WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($transaction->fee, $transactionFeeCurrency->code), $transactionFeeCurrency->id, $client->currencyId);
            }
            $amount = $params["amount"];
            if (array_key_exists("convertto", $params) && $params["convertto"]) {
                $amount = $params["basecurrencyamount"];
            }
            if (WHMCS\Session::exists("StripeClientIdRequired")) {
                Stripe\Customer::update(WHMCS\Session::getAndDelete("StripeClientIdRequired"), ["metadata" => ["clientId" => $client->id]]);
            }
            return stripe_formatDataForGatewayLog(array_merge(["status" => "success", "transid" => $transactionId, "amount" => $amount, "fee" => $transactionFee, "rawdata" => ["charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()]], $returnData));
        } catch (Exception $e) {
            $status = "error";
            if ($e instanceof WHMCS\Exception\Gateways\Declined || $e instanceof Stripe\Exception\CardException) {
                $status = "declined";
            }
            $data = [];
            if ($intent && in_array($intent->status, ["requires_source_action", "requires_action", "requires_capture"])) {
                $intent->cancel(["cancellation_reason" => "abandoned"]);
                $data = $intent->jsonSerialize();
            }
            $data["error"] = $e->getMessage();
            WHMCS\Session::set("StripeDeclined" . $params["invoiceid"], true);
            return stripe_formatDataForGatewayLog(["status" => $status, "rawdata" => $data]);
        }
    }
}

function stripe_fraud_check_fail($params)
{
    $token = WHMCS\Session::getAndDelete("remoteStorageToken");
    WHMCS\Session::delete("cartccdetail");
    stripe_start_stripe($params);
    if (substr($token, 0, 2) == "pi") {
        $intent = Stripe\PaymentIntent::retrieve($token);
        $intent->cancel();
    }
}

function stripe_storeremote($params = [])
{
    $action = $params["action"];
    $amount = (double) ($params["amount"] ?? 0);
    if (WHMCS\Session::get("cartccdetail") && $amount) {
        WHMCS\Session::set("StripeCardId", App::getFromRequest("ccinfo"));
        return [];
    }
    if ($action == "delete" && App::isInRequest("ccinfo") && App::getFromRequest("ccinfo") == "new") {
        $action = "create";
    }
    stripe_start_stripe($params);
    if ($action == "create") {
        $token = WHMCS\Session::getAndDelete("remoteStorageToken");
        if (!$token && App::isInRequest("remoteStorageToken")) {
            $token = (string) App::getFromRequest("remoteStorageToken");
        }
        $intent = NULL;
        $method = NULL;
        if (substr($token, 0, 2) == "pi") {
            WHMCS\Session::set("PaymentIntent" . $params["invoiceid"], $token);
            $intent = Stripe\PaymentIntent::retrieve($token);
            $method = Stripe\PaymentMethod::retrieve($intent->payment_method);
        }
        if (substr($token, 0, 3) == "tok") {
            $method = Stripe\PaymentMethod::create($token);
        }
        $setupIntent = NULL;
        if (substr($token, 0, 4) == "seti") {
            $setupIntent = Stripe\SetupIntent::retrieve($token);
            $method = Stripe\PaymentMethod::retrieve($setupIntent->payment_method);
        }
        if (substr($token, 0, 2) == "pm") {
            $method = Stripe\PaymentMethod::retrieve($token);
        }
        if (!$method) {
            return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["message" => "An unexpected error - No Stripe Payment Method found from token", "token" => $token]]);
        }
        $stripeCustomer = $params["gatewayid"] ?? NULL;
        if ($stripeCustomer) {
            $jsonCheck = stripe_parseGatewayToken($stripeCustomer);
            if ($jsonCheck && array_key_exists("customer", $jsonCheck)) {
                $stripeCustomer = $jsonCheck["customer"];
            }
        }
        if (substr($stripeCustomer, 0, 3) != "cus") {
            $stripeCustomer = "";
        }
        if (!$stripeCustomer && $intent) {
            $stripeCustomer = $intent->customer;
        }
        if (!$stripeCustomer && $method) {
            $stripeCustomer = $method->customer;
        }
        if (!$stripeCustomer && $setupIntent) {
            $existingToken = stripe_findFirstCustomerToken($params["clientdetails"]["model"]);
            if ($existingToken) {
                $stripeCustomer = $existingToken["customer"];
            }
        }
        if (substr($stripeCustomer, 0, 3) != "cus") {
            $stripeCustomer = "";
        }
        if (!$stripeCustomer) {
            $model = $params["clientdetails"]["model"];
            if ($model instanceof WHMCS\User\Client\Contact) {
                $client = $model->client;
            } else {
                $client = $model;
            }
            $stripeCustomer = Stripe\Customer::create(WHMCS\Module\Gateway\Stripe\ApiPayload::customer($client, $client->id));
        }
        if ($stripeCustomer && is_string($stripeCustomer)) {
            $stripeCustomer = Stripe\Customer::retrieve($stripeCustomer);
        }
        if (!$method->customer) {
            $method->attach(["customer" => $stripeCustomer->id]);
        }
        if ($method && isset($params["payMethod"]) && is_object($params["payMethod"]) && is_object($params["payMethod"]->contact)) {
            $billingContact = $params["payMethod"]->contact;
            $billingDetails = ["name" => $billingContact->fullName, "address" => ["line1" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->address1), "line2" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->address2), "city" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->city), "state" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->state), "country" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->country), "postal_code" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->postcode)]];
            unset($billingContact);
            try {
                Stripe\PaymentMethod::update($method->id, ["billing_details" => $billingDetails]);
            } catch (Exception $e) {
            }
        }
        if ($token && $method) {
            $card = $method->jsonSerialize()["card"];
            $cardLastFour = $card["last4"];
            $cardExpiry = str_pad($card["exp_month"], 2, "0", STR_PAD_LEFT) . substr($card["exp_year"], 2);
            $cardType = $card["brand"];
            return stripe_formatDataForGatewayLog(["nodelete" => true, "cardnumber" => $cardLastFour, "cardlastfour" => $cardLastFour, "cardexpiry" => $cardExpiry, "cardtype" => ucfirst($cardType), "gatewayid" => json_encode(["customer" => $stripeCustomer->id, "method" => $method->id]), "status" => "success", "rawdata" => $stripeCustomer->jsonSerialize()]);
        }
    } else {
        if ($params["action"] == "update") {
            $stripeCustomer = $params["remoteStorageToken"];
            $method = NULL;
            if ($stripeCustomer && substr($stripeCustomer, 0, 3) === "cus") {
                $stripeCustomer = Stripe\Customer::retrieve($stripeCustomer);
                $source = $stripeCustomer->default_source;
                if ($source) {
                    $method = Stripe\PaymentMethod::retrieve($source);
                    $params["gatewayid"] = json_encode(["customer" => $stripeCustomer->id, "method" => $method->id]);
                    $method = $method->id;
                }
            }
            if ($stripeCustomer) {
                if (is_string($stripeCustomer)) {
                    $jsonCheck = stripe_parseGatewayToken($stripeCustomer);
                    if ($jsonCheck && array_key_exists("customer", $jsonCheck)) {
                        $stripeCustomer = $jsonCheck["customer"];
                        $method = $jsonCheck["method"];
                        $stripeCustomer = Stripe\Customer::retrieve($stripeCustomer);
                    }
                }
                try {
                    if ($method) {
                        $billingContact = $params["payMethod"]->contact;
                        $billingContactEmail = $params["payMethod"]->client->email;
                        $billingDetails = ["name" => $billingContact->fullName, "address" => ["line1" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->address1), "line2" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->address2), "city" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->city), "state" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->state), "country" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->country), "postal_code" => WHMCS\Module\Gateway\Stripe\ApiPayload::formatValue($billingContact->postcode)]];
                        if (substr($method, 0, 4) != "card") {
                            $billingDetails["email"] = $billingContactEmail;
                        }
                        Stripe\PaymentMethod::update($method, ["card" => ["exp_month" => $params["cardExpiryMonth"], "exp_year" => $params["cardExpiryYear"]], "billing_details" => $billingDetails]);
                    }
                    return stripe_formatDataForGatewayLog(["status" => "success", "cardexpiry" => $params["cardexp"], "gatewayid" => $params["gatewayid"], "rawdata" => $stripeCustomer->jsonSerialize()]);
                } catch (Exception $e) {
                    return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["customer" => $stripeCustomer, "error" => $e->getMessage()]]);
                }
            }
        } else {
            if ($params["action"] == "delete") {
                $stripeCustomer = $params["gatewayid"];
                $method = NULL;
                if ($stripeCustomer) {
                    $jsonCheck = stripe_parseGatewayToken($stripeCustomer);
                    if ($jsonCheck && array_key_exists("customer", $jsonCheck)) {
                        $stripeCustomer = $jsonCheck["customer"];
                        $method = $jsonCheck["method"];
                    }
                }
                try {
                    if ($stripeCustomer) {
                        $stripeCustomer = Stripe\Customer::retrieve($stripeCustomer);
                        if (!$method) {
                            $stripeCustomer->delete();
                        } else {
                            if ($method) {
                                $method = Stripe\PaymentMethod::retrieve($method);
                                if ($method->customer) {
                                    $method->detach();
                                }
                            }
                        }
                        return stripe_formatDataForGatewayLog(["status" => "success", "rawdata" => $stripeCustomer->jsonSerialize()]);
                    }
                } catch (Exception $e) {
                    return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["customer" => $stripeCustomer, "error" => $e->getMessage()]]);
                }
            }
        }
    }
    return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["error" => "No Stripe Details Found for Update"]]);
}

function stripe_refund($params = [])
{
    $amount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountOutbound($params["amount"], $params["currency"]);
    stripe_start_stripe($params);
    $client = WHMCS\User\Client::find($params["clientdetails"]["userid"]);
    try {
        $transaction = Stripe\BalanceTransaction::retrieve($params["transid"]);
        $refund = Stripe\Refund::create(["charge" => $transaction->source, "amount" => $amount]);
        $refundTransaction = Stripe\BalanceTransaction::retrieve($refund->balance_transaction);
        $transactionFeeCurrency = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", "=", strtoupper($refundTransaction->fee_details[0]->currency))->first(["id"]);
        $refundTransactionFee = 0;
        if ($transactionFeeCurrency) {
            $refundTransactionFee = convertCurrency($refundTransaction->fee / -100, $transactionFeeCurrency->id, $params["convertto"] ?: $client->currencyId);
        }
        return stripe_formatDataForGatewayLog(["transid" => $refundTransaction->id, "rawdata" => array_merge($refund->jsonSerialize(), $refundTransaction->jsonSerialize()), "status" => "success", "fees" => $refundTransactionFee]);
    } catch (Exception $e) {
        return stripe_formatDataForGatewayLog(["status" => "error", "rawdata" => ["error" => $e->getMessage()]]);
    }
}

function stripe_cc_validation($params = [])
{
    if (App::isInRequest("remoteStorageToken")) {
        WHMCS\Session::set("remoteStorageToken", (string) App::getFromRequest("remoteStorageToken"));
    }
    return "";
}

function stripe_credit_card_input($params = [])
{
    stripe_start_stripe($params);
    $existingSubmittedToken = "";
    $assetHelper = DI::make("asset");
    $now = time();
    $token = App::getFromRequest("remoteStorageToken");
    if ($token && substr($token, 0, 2) != "pi") {
        $token = "";
    }
    if (isset($params["gatewayid"]) && $params["gatewayid"]) {
        $remoteToken = stripe_parseGatewayToken($params["gatewayid"]);
        if ($remoteToken && array_key_exists("method", $remoteToken)) {
            $existingSubmittedToken = $remoteToken["method"];
        }
    } else {
        $client = Auth::client();
        if ($client) {
            $remoteToken = stripe_findFirstCustomerToken($client);
            if ($remoteToken && array_key_exists("method", $remoteToken)) {
                $existingSubmittedToken = $remoteToken["method"];
            }
        }
    }
    if ($token) {
        $existingSubmittedToken = $token;
    }
    $additional = "\n    existingToken = '" . $existingSubmittedToken . "';";
    $amount = 0;
    $currencyCode = "";
    $description = stripe_statement_descriptor($params);
    if (array_key_exists("rawtotal", $params)) {
        $currencyData = WHMCS\Billing\Currency::factoryForClientArea();
        $amount = $params["rawtotal"];
        $currencyCode = $currencyData["code"];
        if (isset($params["convertto"]) && $params["convertto"]) {
            $currencyCode = (string) WHMCS\Database\Capsule::table("tblcurrencies")->where("id", "=", (int) $params["convertto"])->value("code");
            $amount = convertCurrency($amount, $currencyData["id"], $params["convertto"]);
        }
        $amount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountOutbound($amount, $currencyCode);
    }
    if (array_key_exists("amount", $params)) {
        $amount = $params["amount"];
        $currencyCode = $params["currency"];
        $amount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountOutbound($amount, $currencyCode);
    }
    if ($params["applePay"]) {
        if ($amount) {
            $additional .= "\n    paymentRequestButtonEnabled = true;\n    paymentRequestAmountDue = " . $amount . ";\n    paymentRequestCurrency = '" . $currencyCode . "';\n    paymentRequestDescription = '" . $description . "';";
        }
    } else {
        $additional .= "\n    paymentRequestButtonEnabled = false;";
    }
    $savePaymentMethod = 0;
    if ($amount) {
        $intentsArray = ["description" => $description, "amount" => $amount, "currency" => strtolower($currencyCode), "payment_method_types" => ["card"], "statement_descriptor_suffix" => $description];
        if (isset($params["gatewayid"]) && $params["gatewayid"] && substr($params["gatewayid"], 0, 3) == "cus") {
            $intentsArray["customer"] = $params["gatewayid"];
            $savePaymentMethod = 1;
        }
        $intentName = "StripeIntentsData";
        if (!empty($params["invoiceid"])) {
            $intentName .= $params["invoiceid"];
        }
        WHMCS\Session::set($intentName, $intentsArray);
    }
    if ($error = WHMCS\Session::getAndDelete("StripeDeclined" . ($params["invoiceid"] ?? ""))) {
        $error = Lang::trans("creditcarddeclined");
        $additional .= "\njQuery('.gateway-errors').html('" . $error . "').removeClass('hidden');";
    }
    $additional .= "\n    elementOptions = {\n        style: {\n            base: {\n\n            }\n        }\n    },\n        card = elements.create('cardNumber', elementOptions),\n        cardExpiryElements = elements.create('cardExpiry', elementOptions),\n        cardCvcElements = elements.create('cardCvc', elementOptions),\n        savePaymentMethod = " . $savePaymentMethod . ";";
    $lang = ["creditCardInput" => addslashes(Lang::trans("creditcardcardnumber")), "creditCardExpiry" => addslashes(Lang::trans("creditcardcardexpires")), "creditCardCvc" => addslashes(Lang::trans("creditcardcvvnumbershort")), "newCardInformation" => addslashes(Lang::trans("creditcardenternewcard")), "or" => addslashes(Lang::trans("or"))];
    $apiVersion = WHMCS\Module\Gateway\Stripe\Constant::$apiVersion;
    $genericErrorMessage = WHMCS\Input\Sanitize::escapeSingleQuotedString(Lang::trans("remoteTransError"));
    return "<script type=\"text/javascript\" src=\"" . $assetHelper->getWebRoot() . "/modules/gateways/stripe/stripe.min.js?a=" . $now . "\"></script>\n<script type=\"text/javascript\">\n\nvar card = null,\n    stripe = null,\n    elements = null,\n    lang = null,\n    existingToken = null,\n    paymentRequestButtonEnabled = null,\n    paymentRequestAmountDue = null,\n    paymentRequestCurrency = null,\n    paymentRequestDescription = null,\n    paymentRequestButtonEnabled = null,\n    elementOptions = null,\n    amount = '" . $amount . "',\n    elementsClass = 'form-group',\n    defaultErrorMessage = '" . $genericErrorMessage . "';\n\n\$(document).ready(function() {\n    stripe = Stripe('" . $params["publishableKey"] . "');\n    stripe.api_version = \"" . $apiVersion . "\";\n    elements = stripe.elements();\n    " . $additional . "\n    lang = {\n        creditCardInput: '" . $lang["creditCardInput"] . "',\n        creditCardExpiry: '" . $lang["creditCardExpiry"] . "',\n        creditCardCvc: '" . $lang["creditCardCvc"] . "',\n        newCardInformation: '" . $lang["newCardInformation"] . "',\n        or: '" . $lang["or"] . "'\n    };\n\n    initStripe();\n});\n</script>\n<link href=\"" . $assetHelper->getWebRoot() . "/modules/gateways/stripe/stripe.css?a=" . $now . "\" rel=\"stylesheet\">";
}

function stripe_statement_descriptor($params)
{
    $inCart = function ($params) {
        return array_key_exists("rawtotal", $params) && !array_key_exists("invoiceid", $params);
    };
    $defaultDescriptor = Lang::trans("carttitle");
    $descriptor = $defaultDescriptor;
    if (!$inCart($params) && isset($params["statementDescriptor"]) && 0 < strlen($params["statementDescriptor"])) {
        $descriptor = $params["statementDescriptor"];
        $invoiceNumber = "";
        if (isset($params["invoicenum"]) && 0 < strlen($params["invoicenum"])) {
            $invoiceNumber = $params["invoicenum"];
        } else {
            if (isset($params["invoiceid"]) && 0 < strlen($params["invoiceid"])) {
                $invoiceNumber = $params["invoiceid"];
            }
        }
        $descriptor = str_replace(["{CompanyName}", "{InvoiceNumber}"], [WHMCS\Config\Setting::getValue("CompanyName"), $invoiceNumber], $descriptor);
    }
    $descriptor = voku\helper\ASCII::to_transliterate($descriptor);
    $descriptor = trim(str_replace(stripe_statement_descriptor_invalid_characters(), "", $descriptor));
    if (strlen($descriptor) == 0) {
        $descriptor = $defaultDescriptor;
    }
    $descriptor = substr($descriptor, -22);
    return $descriptor;
}

function stripe_statement_descriptor_invalid_characters()
{
    return [">", "<", "'", "\"", "*"];
}

function stripe_start_stripe($params)
{
    Stripe\Stripe::setAppInfo(WHMCS\Module\Gateway\Stripe\Constant::$appName, App::getVersion()->getMajor(), WHMCS\Module\Gateway\Stripe\Constant::$appUrl, WHMCS\Module\Gateway\Stripe\Constant::$appPartnerId);
    Stripe\Stripe::setApiKey($params["secretKey"]);
    Stripe\Stripe::setApiVersion(WHMCS\Module\Gateway\Stripe\Constant::$apiVersion);
}

function stripe_parseGatewayToken($data)
{
    $data = json_decode($data, true);
    if ($data && is_array($data)) {
        return $data;
    }
    return [];
}

function stripe_findFirstCustomerToken(WHMCS\User\Contracts\ContactInterface $client)
{
    $clientToUse = $client;
    if ($clientToUse instanceof WHMCS\User\Client\Contact) {
        $clientToUse = $clientToUse->client;
    }
    foreach ($clientToUse->payMethods as $payMethod) {
        if ($payMethod->gateway_name == "stripe") {
            $payment = $payMethod->payment;
            $token = stripe_parsegatewaytoken($payment->getRemoteToken());
            if ($token) {
                return $token;
            }
        }
    }
    $remoteCustomers = Stripe\Customer::all(["email" => $clientToUse->email, "limit" => 15]);
    foreach ($remoteCustomers->data as $customer) {
        $metaId = !empty($customer->metadata->clientId) ? (int) $customer->metadata->clientId : 0;
        if ($metaId === $clientToUse->id) {
            return ["customer" => $customer->id];
        }
    }
    return NULL;
}

function stripe_get_existing_remote_token($params)
{
    $remoteToken = $params["remoteToken"];
    if (substr($remoteToken, 0, 3) === "cus") {
        stripe_start_stripe($params);
        $stripeCustomer = Stripe\Customer::retrieve($remoteToken);
        $source = $stripeCustomer->default_source;
        if ($source) {
            $method = Stripe\PaymentMethod::retrieve($source);
            $remoteToken = json_encode(["customer" => $stripeCustomer->id, "method" => $method->id]);
            $params["payMethod"]->payment->setRemoteToken($remoteToken)->save();
        }
    }
    $remoteToken = stripe_parsegatewaytoken($remoteToken);
    if (count($remoteToken) < 2) {
        throw new InvalidArgumentException("Invalid Remote Token");
    }
    return $remoteToken["method"];
}

function stripe_formatDataForGatewayLog($data)
{
    $formattedArray = [];
    $rawData = $data["rawdata"];
    if (!empty($data["status"])) {
        $formattedArray["Status"] = $data["status"];
    }
    if (!empty($data["declinereason"])) {
        $formattedArray["Decline Reason"] = $data["declinereason"];
    }
    if (!empty($rawData["object"])) {
        if (is_array($rawData["object"]) || is_object($rawData["object"])) {
            $object = (array) $rawData["object"];
        } else {
            $object = (string) $rawData["object"];
        }
        if (!is_array($object)) {
            $id = !empty($rawData["id"]) ? $rawData["id"] : "Unknown";
            $switch = $object;
        } else {
            $id = $object["id"];
            $switch = $object["object"];
        }
        switch ($switch) {
            case "customer":
                $formattedArray["Customer ID"] = $id;
                break;
            case "payment_method":
            case "card":
                $formattedArray["Payment Method"] = $id;
                break;
            case "balance_transaction":
                $formattedArray["Transaction ID"] = $id;
                break;
        }
    }
    if (!empty($rawData["transaction"]) && empty($formattedArray["Transaction ID"])) {
        if (!is_object($rawData["transaction"]) && !is_array($rawData["transaction"])) {
            $formattedArray["Transaction ID"] = $rawData["transaction"];
        } else {
            $transaction = (array) $rawData["transaction"];
            $formattedArray["Transaction ID"] = $transaction["id"];
        }
    }
    if (!empty($rawData["charge"])) {
        if (!is_object($rawData["charge"]) && !is_array($rawData["charge"])) {
            $formattedArray["Charge ID"] = $rawData["charge"];
        } else {
            $charge = (array) $rawData["charge"];
            $formattedArray["Charge ID"] = $charge["id"];
        }
    }
    $data["rawdata"] = array_merge($formattedArray, $rawData);
    return $data;
}

function stripe_formatTransactionIdForDisplay($params = [])
{
    return str_replace("txn_", "", $params["transactionId"]);
}

function stripe_account_balance($BalanceCollection, $params)
{
    stripe_start_stripe($params);
    $balanceData = Stripe\Balance::retrieve()->toArray();
    $balanceInfo = [];
    $amount = [];
    $pending = [];
    foreach ($balanceData["available"] as $availableData) {
        $currency = strtoupper($availableData["currency"]);
        $amount[$currency] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($availableData["amount"], $availableData["currency"]);
        $balanceInfo[] = WHMCS\Module\Gateway\Balance::factory($amount[$currency], $currency);
    }
    foreach ($balanceData["pending"] as $pendingData) {
        $currency = strtoupper($pendingData["currency"]);
        $pending[$currency] = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($pendingData["amount"], $pendingData["currency"]);
        $balanceInfo[] = WHMCS\Module\Gateway\PendingBalance::factory($pending[$currency], $currency);
    }
    return WHMCS\Module\Gateway\BalanceCollection::factoryFromItems(...$balanceInfo);
}

function stripe_TransactionInformation($Information, $params)
{
    $detail = new WHMCS\Billing\Payment\Transaction\Information();
    stripe_start_stripe($params);
    $transactionData = Stripe\BalanceTransaction::retrieve(["id" => $params["transactionId"], "expand" => ["source", "source.dispute"]]);
    $transactionFee = WHMCS\Module\Gateway\Stripe\ApiPayload::transactionFee($transactionData, WHMCS\Billing\Currency::find($params["clientdetails"]["currency"]));
    $charge = $transactionData->source;
    $chargeAmount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($charge->amount, $charge->currency);
    $capturedAmount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($charge->amount_captured, $charge->currency);
    $refundedAmount = WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($charge->amount_refunded, $charge->currency);
    $detail->setTransactionId($transactionData->id)->setAmount(WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($transactionData->amount, $transactionData->currency))->setCurrency(strtoupper($transactionData->currency))->setType($transactionData->type)->setAvailableOn(WHMCS\Carbon::parse($transactionData->available_on))->setCreated(WHMCS\Carbon::parse($transactionData->created))->setDescription($transactionData->description)->setFee($transactionFee)->setStatus($transactionData->status)->setAdditionalDatum("originalAmount", formatCurrency($chargeAmount)->toNumeric())->setAdditionalDatum("capturedAmount", formatCurrency($capturedAmount)->toNumeric())->setAdditionalDatum("refundedAmount", formatCurrency($refundedAmount)->toNumeric())->setAdditionalDatum("originalCurrency", strtoupper($charge->currency))->setAdditionalDatum("paymentIntent", $charge->payment_intent)->setAdditionalDatum("disputed", AdminLang::trans($charge->disputed ? "global.yes" : "global.no"));
    if ($charge->dispute) {
        $dispute = $charge->dispute;
        $currentUser = WHMCS\User\Admin::getAuthenticatedUser();
        if ($currentUser->hasPermission("Manage Disputes")) {
            $disputeUri = routePath("admin-billing-disputes-view", "stripe", $dispute->id);
            $viewDispute = AdminLang::trans("disputes.viewDispute");
            $detail->setAdditionalDatum("viewDispute", "<a href=\"" . $disputeUri . "\" class=\"btn btn-sm btn-default autoLinked\">" . $viewDispute . "</a>");
        }
        $detail->setAdditionalDatum("disputeReason", AdminLang::trans("disputes.reasons." . $dispute->reason))->setAdditionalDatum("disputeStatus", AdminLang::trans("disputes.statuses." . $dispute->status));
    }
    if ($transactionData->exchange_rate) {
        $detail->setExchangeRate($transactionData->exchange_rate);
    }
    if ($charge->receipt_url) {
        $detail->setAdditionalDatum("receiptUrl", "<a href=\"" . $charge->receipt_url . "\" class=\"btn btn-sm btn-default autoLinked\">" . AdminLang::trans("global.view") . "</a>");
    }
    return $detail;
}

function stripe_ListDisputes($DisputeCollection, $params)
{
    $disputeListLimit = 500;
    stripe_start_stripe($params);
    $returnData = [];
    $startingAfter = "";
    $disputeParams = ["limit" => 100, "expand" => ["data.charge"]];
    if ($startingAfter) {
        $disputeParams["starting_after"] = $startingAfter;
    }
    $disputes = Stripe\Dispute::all($disputeParams);
    foreach ($disputes as $dispute) {
        $paymentDispute = WHMCS\Billing\Payment\Dispute::factory($dispute->id, WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($dispute->amount, $dispute->currency), $dispute->currency, $dispute->charge->balance_transaction, WHMCS\Carbon::parse($dispute->created), WHMCS\Carbon::parse($dispute->evidence_details->due_by), $dispute->reason, $dispute->status);
        $paymentDispute->setGateway("stripe");
        $paymentDispute->setIsClosable(in_array($dispute->status, ["warning_needs_response", "needs_response"]));
        $returnData[] = $paymentDispute;
        $startingAfter = $dispute->id;
    }
    if ($disputes->has_more && count($returnData) <= $disputeListLimit) {
        return WHMCS\Billing\Payment\Dispute\DisputeCollection::factoryFromItems(...$returnData);
    }
}

function stripe_FetchDispute($Dispute, $params)
{
    stripe_start_stripe($params);
    $dispute = Stripe\Dispute::retrieve(["id" => $params["disputeId"], "expand" => ["charge", "evidence.cancellation_policy", "evidence.customer_communication", "evidence.customer_signature", "evidence.duplicate_charge_documentation", "evidence.receipt", "evidence.refund_policy", "evidence.service_documentation", "evidence.shipping_documentation", "evidence.uncategorized_file"]]);
    $paymentDispute = WHMCS\Billing\Payment\Dispute::factory($dispute->id, WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($dispute->amount, $dispute->currency), $dispute->currency, $dispute->charge->balance_transaction, WHMCS\Carbon::parse($dispute->created), WHMCS\Carbon::parse($dispute->evidence_details->due_by), $dispute->reason, $dispute->status);
    $paymentDispute->setGateway("stripe");
    $paymentDispute->setIsUpdatable(in_array($dispute->status, ["warning_needs_response", "needs_response"]));
    $paymentDispute->setIsClosable($paymentDispute->getIsUpdatable());
    $paymentDispute->setIsSubmittable($paymentDispute->getIsUpdatable());
    $paymentDispute->setEvidenceType("access_activity_log", "textarea");
    $paymentDispute->setEvidenceType("billing_address", "textarea");
    $paymentDispute->setEvidenceType("cancellation_policy", "file");
    $paymentDispute->setEvidenceType("customer_communication", "file");
    $paymentDispute->setEvidenceType("customer_signature", "file");
    $paymentDispute->setEvidenceType("duplicate_charge_documentation", "file");
    $paymentDispute->setEvidenceType("duplicate_charge_explanation", "textarea");
    $paymentDispute->setEvidenceType("product_description", "textarea");
    $paymentDispute->setEvidenceType("receipt", "file");
    $paymentDispute->setEvidenceType("refund_policy", "file");
    $paymentDispute->setEvidenceType("refund_refusal_explanation", "textarea");
    $paymentDispute->setEvidenceType("service_documentation", "file");
    $paymentDispute->setEvidenceType("shipping_address", "textarea");
    $paymentDispute->setEvidenceType("shipping_documentation", "file");
    $paymentDispute->setEvidenceType("uncategorized_file", "file");
    $evidence = $dispute->evidence->toArray();
    $formattedEvidence = collect();
    foreach ($evidence as $evidenceName => $evidenceValue) {
        if ($paymentDispute->getEvidenceType($evidenceName) === "file" && $evidenceValue) {
            $links = collect($evidenceValue["links"]["data"]);
            if ($links->count()) {
                $links = $links->where("expired", false);
            }
            if (!$links->count()) {
                $link = Stripe\FileLink::create(["file" => $evidenceValue["id"]]);
                $links->add($link->toArray());
            }
            $evidenceValue["url"] = $links->first()["url"];
        }
        if ($formattedEvidence->where("name", $evidenceName)->count() == 0) {
            $formattedEvidence->push(["name" => $evidenceName, "value" => $evidenceValue]);
        }
        unset($evidenceValue);
    }
    $paymentDispute->setEvidence($formattedEvidence->toArray());
    return $paymentDispute;
}

function stripe_UploadFile($params)
{
    stripe_start_stripe($params);
    $thisFile = $params["file"];
    $fp = fopen($thisFile["tmp_name"], "r");
    $file = Stripe\File::create(["purpose" => "dispute_evidence", "file" => $fp]);
    return $file->id;
}

function stripe_UpdateDispute($params)
{
    stripe_start_stripe($params);
    Stripe\Dispute::update($params["disputeId"], ["submit" => false, "evidence" => $params["evidence"]]);
}

function stripe_SubmitDispute($params)
{
    stripe_start_stripe($params);
    $dispute = Stripe\Dispute::retrieve(["id" => $params["disputeId"]]);
    if (!$dispute->evidence_details->has_evidence) {
        throw new WHMCS\Exception\Validation\InvalidValue(AdminLang::trans("disputes.unableToSubmit"));
    }
    Stripe\Dispute::update($params["disputeId"], ["submit" => true]);
}

function stripe_CloseDispute($params)
{
    stripe_start_stripe($params);
    Stripe\Dispute::retrieve($params["disputeId"])->close();
}
