<?php

use WHMCS\Application\Support\Facades\Lang;

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function stripe_ach_MetaData()
{
    return ["APIVersion" => 0, "gatewayType" => WHMCS\Module\Gateway::GATEWAY_BANK, "failedEmail" => "Direct Debit Payment Failed", "successEmail" => "Direct Debit Payment Confirmation", "pendingEmail" => "Direct Debit Payment Pending", "noCurrencyConversion" => true, "supportedCurrencies" => ["USD"]];
}

function stripe_ach_config()
{
    $invalidDescriptorChars = implode(", ", stripe_ach_statement_descriptor_invalid_characters());
    $config = ["FriendlyName" => ["Type" => "System", "Value" => "Stripe ACH"], "publishableKey" => ["FriendlyName" => "Stripe Publishable API Key", "Type" => "text", "Size" => "30", "Description" => "Your publishable API key identifies your website to Stripe during communications. This can be obtained from <a href=\"https://dashboard.stripe.com/account/apikeys\" class=\"autoLinked\">here</a>"], "secretKey" => ["FriendlyName" => "Stripe Secret API Key", "Type" => "text", "Size" => "30", "Description" => "Your secret API Key ensures only communications from Stripe are validated."], "webhookEndpointSecret" => ["FriendlyName" => "Stripe ACH WebHook Endpoint Secret", "Type" => "password", "Size" => "30", "Description" => "Automatically generated web-hook secret key."], "statementDescriptor" => ["FriendlyName" => "Statement Descriptor Suffix", "Type" => "text", "Size" => 25, "Default" => "{CompanyName}", "Description" => "Available merge field tags: <strong>{CompanyName} {InvoiceNumber}</strong>\n<div class=\"alert alert-info top-margin-5 bottom-margin-5\">\n    Displayed on your customer's credit card statement.<br />\n    <strong>Maximum length of 22 characters</strong>, and must not contain any of the following:\n    <span style=\"font-family: monospace\">" . $invalidDescriptorChars . "</span><br />\n    This will be appended to the statement descriptor defined in the Stripe Account.\n</div>"], "plaidClientId" => ["FriendlyName" => "Plaid Client Id", "Type" => "text", "Size" => 25, "Description" => "Your client id identifies your website to Plaid during communications. This can be obtained from <a href=\"https://dashboard.plaid.com/account/keys\" class=\"autoLinked\">here</a>"], "plaidSecret" => ["FriendlyName" => "Plaid Secret", "Type" => "text", "Size" => 25, "Description" => "The secret from your Plaid account"], "plaidMode" => ["FriendlyName" => "Plaid Environment", "Type" => "dropdown", "Options" => ["sandbox" => "Sandbox/Test (Unrestricted)", "development" => "Development (100 Live Items)", "production" => "Production"], "Default" => "sandbox", "Description" => "Choose the environment you wish to use Plaid with."]];
    try {
        WHMCS\Module\Gateway::factory("stripe");
        $config["copyStripeConfig"] = ["FriendlyName" => "Use Stripe Configuration", "Type" => "yesno", "Description" => "Use the configuration from Stripe to configure the Publishable Key, Private Key and Statement Descriptor"];
    } catch (Exception $e) {
    }
    $currencies = WHMCS\Billing\Currency::where("code", "!=", "USD")->pluck("code");
    $usageNotes = [];
    if (count($currencies)) {
        $usageNotes[] = "<strong>Unsupported Currencies.</strong> You have one or more currencies configured that are not supported by Stripe ACH. Invoices using currencies ACH does not support will be unable to be paid using ACH. <a href=\"https://docs.whmcs.com/Stripe_ACH#Supported_Currencies\" target=\"_blank\">Learn more</a>";
    }
    if ($usageNotes) {
        $config["UsageNotes"] = ["Type" => "System", "Value" => implode("<br>", $usageNotes)];
    }
    return $config;
}

function stripe_ach_nolocalcc()
{
}

function stripe_ach_config_validate($params = [])
{
    if (!empty($params["copyStripeConfig"])) {
        return NULL;
    }
    if (isset($params["statementDescriptor"]) && 0 < strlen($params["statementDescriptor"])) {
        $descriptorCheck = str_replace(stripe_ach_statement_descriptor_invalid_characters(), "", $params["statementDescriptor"]);
        if (strlen($params["statementDescriptor"]) != strlen($descriptorCheck)) {
            throw new WHMCS\Exception\Module\InvalidConfiguration("Invalid characters present in Statement Descriptor Suffix");
        }
        unset($descriptorCheck);
    }
    try {
        if ($params["publishableKey"] && substr($params["publishableKey"], 0, 3) === "pk_" && $params["secretKey"] && substr($params["secretKey"], 0, 3) === "sk_") {
            stripe_ach_start_stripe($params);
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

function stripe_ach_config_post_save($params = [])
{
    if (array_key_exists("copyStripeConfig", $params) && $params["copyStripeConfig"]) {
        try {
            $gatewayInterface = WHMCS\Module\Gateway::factory("stripe");
            $gatewayParams = $gatewayInterface->getParams();
            $copiedParams = ["publishableKey" => $gatewayParams["publishableKey"], "secretKey" => $gatewayParams["secretKey"], "statementDescriptor" => $gatewayParams["statementDescriptor"], "copyStripeConfig" => ""];
            foreach ($copiedParams as $copiedParam => $value) {
                WHMCS\Module\GatewaySetting::setValue("stripe_ach", $copiedParam, $value);
            }
            $params = array_merge($params, $copiedParams);
        } catch (Exception $e) {
        }
    }
    if (array_key_exists("secretKey", $params) && $params["secretKey"]) {
        $notificationUrl = App::getSystemURL() . "modules/gateways/callback/stripe_ach.php";
        stripe_ach_start_stripe($params);
        $webHooks = Stripe\WebhookEndpoint::all([]);
        foreach ($webHooks->data as $webHook) {
            if ($webHook->url == $notificationUrl && $webHook->status == "enabled") {
                return NULL;
            }
        }
        $webHook = Stripe\WebhookEndpoint::create(["url" => $notificationUrl, "enabled_events" => ["charge.failed", "charge.succeeded"]]);
        WHMCS\Module\GatewaySetting::setValue("stripe_ach", "webhookEndpointSecret", $webHook->secret);
    }
}

function stripe_ach_deactivate($params)
{
    $notificationUrl = App::getSystemURL() . "modules/gateways/callback/stripe_ach.php";
    stripe_ach_start_stripe($params);
    /**
     * @var Stripe\WebhookEndpoint[] $webHooks
     */
    $webHooks = Stripe\WebhookEndpoint::all()->data;
    foreach ($webHooks as $webHook) {
        if ($webHook->url == $notificationUrl && $webHook->status == "enabled") {
            $webHook->delete();
        }
    }
}

function stripe_ach_storeremote($params)
{
    stripe_ach_start_stripe($params);
    switch ($params["action"]) {
        case "create":
            $customerId = stripe_ach_findFirstStripeCustomerId($params["clientdetails"]["model"]);
            if (!$customerId) {
                $customerId = stripe_ach_create_customer($params);
            }
            $customer = Stripe\Customer::retrieve($customerId);
            $remoteToken = $params["remoteStorageToken"];
            if (substr($remoteToken, 0, 4) !== "btok") {
                return ["status" => "error", "rawdata" => ["message" => "Invalid Remote Token", "token" => $remoteToken]];
            }
            try {
                $source = Stripe\Customer::createSource($customer->id, ["source" => $remoteToken]);
                $accountNumber = $source->last4;
                $bankName = $source->bank_name;
                $routingNumber = $source->routing_number;
                return ["status" => "success", "rawdata" => $customer->jsonSerialize(), "remoteToken" => json_encode(["customer" => $customer->id, "account" => $source->id]), "accountNumber" => $accountNumber, "bankName" => $bankName, "routingNumber" => $routingNumber];
            } catch (Exception $e) {
                $visibleErrors = ["A bank account with that routing number and account number already exists for this customer."];
                $visible = false;
                if (in_array($e->getMessage(), $visibleErrors)) {
                    $visible = true;
                }
                return ["status" => "error", "rawdata" => $e->getMessage(), "visible" => $visible];
            }
            break;
        case "delete":
            try {
                $remoteToken = stripe_ach_parseGatewayToken($params["gatewayid"]);
                if (!$remoteToken) {
                    return ["status" => "error", "rawdata" => ["error" => "Invalid Remote Token for Gateway", "data" => $params["gatewayid"]]];
                }
                Stripe\Customer::deleteSource($remoteToken["customer"], $remoteToken["account"]);
                return ["status" => "success"];
            } catch (Exception $e) {
                return ["status" => "error", "rawdata" => $e->getMessage()];
            }
            break;
        case "update":
            return ["gatewayid" => $params["remoteStorageToken"], "rawdata" => "Pay Method Description has been updated", "status" => "success"];
            break;
        default:
            return ["status" => "error", "rawdata" => "Invalid Action Request"];
    }
}

function stripe_ach_capture($params)
{
    try {
        stripe_ach_start_stripe($params);
        $remoteToken = stripe_ach_parseGatewayToken($params["gatewayid"]);
        if (!$remoteToken) {
            throw new InvalidArgumentException("Invalid Remote Token For Gateway: " . $params["gatewayid"]);
        }
        if ($params["currency"] != "USD") {
            throw new InvalidArgumentException("Invalid Currency For Gateway: " . $params["currency"]);
        }
        $charge = Stripe\Charge::create(["amount" => stripe_ach_formatAmount($params["amount"], $params["currency"]), "currency" => strtolower($params["currency"]), "customer" => $remoteToken["customer"], "source" => $remoteToken["account"], "metadata" => ["id" => $params["invoiceid"], "invoiceNumber" => $params["invoicenum"]], "statement_descriptor" => stripe_ach_statement_descriptor($params)]);
        $transaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
        return ["status" => "pending", "rawdata" => ["charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()]];
    } catch (Exception $e) {
        return ["status" => "error", "rawdata" => ["gatewayId" => $params["gatewayid"], "currency" => $params["currency"], "message" => $e->getMessage()], "declinereason" => $e->getMessage()];
    }
}

function stripe_ach_bank_account_input($params)
{
    $existingSubmittedToken = "";
    $token = App::getFromRequest("remoteStorageToken");
    if ($token && substr($token, 0, 4) != "btok") {
        $token = "";
    }
    if (!$token && $params["gatewayid"]) {
        $remoteToken = stripe_ach_parseGatewayToken($params["gatewayid"]);
        if ($remoteToken && array_key_exists("account", $remoteToken)) {
            $existingSubmittedToken = $remoteToken["account"];
        }
    }
    if ($token) {
        $existingSubmittedToken = $token;
    }
    $plaid = WHMCS\Module\Gateway\StripeAch\Plaid::factory($params);
    $plaid->setExistingToken($existingSubmittedToken);
    return $plaid->getJavascriptOutput();
}

function stripe_ach_refund($params = [])
{
    $amount = stripe_ach_formatAmount($params["amount"], $params["currency"]);
    stripe_ach_start_stripe($params);
    $client = WHMCS\User\Client::find($params["clientdetails"]["userid"]);
    try {
        $transaction = Stripe\BalanceTransaction::retrieve($params["transid"]);
        $refund = Stripe\Refund::create(["charge" => $transaction->source, "amount" => $amount]);
        $refundTransaction = Stripe\BalanceTransaction::retrieve($refund->balance_transaction);
        $transactionFeeCurrency = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", "=", strtoupper($refundTransaction->fee_details[0]->currency))->first(["id"]);
        $refundTransactionFee = 0;
        if ($transactionFeeCurrency) {
            $refundTransactionFee = convertCurrency($refundTransaction->fee / -100, $transactionFeeCurrency->id, $params["convertto"] ?: $client->currencyId, 1);
        }
        return ["transid" => $refundTransaction->id, "rawdata" => array_merge($refund->jsonSerialize(), $refundTransaction->jsonSerialize()), "status" => "success", "fees" => $refundTransactionFee];
    } catch (Exception $e) {
        return ["status" => "error", "rawdata" => $e->getMessage()];
    }
}

function stripe_ach_formatValue($value)
{
    return $value !== "" ? $value : NULL;
}

function stripe_ach_formatAmount($amount, $currencyCode)
{
    $currenciesWithoutDecimals = WHMCS\Module\Gateway\Stripe\Constant::STRIPE_CURRENCIES_NO_DECIMALS;
    $currencyCode = strtoupper($currencyCode);
    $isNoDecimalCurrency = in_array($currencyCode, $currenciesWithoutDecimals);
    $amount = str_replace([",", "."], "", $amount);
    if ($isNoDecimalCurrency) {
        $amount = round($amount / 100);
    }
    return $amount;
}

function stripe_ach_start_stripe($params)
{
    Stripe\Stripe::setAppInfo(WHMCS\Module\Gateway\Stripe\Constant::$appName, App::getVersion()->getMajor(), WHMCS\Module\Gateway\Stripe\Constant::$appUrl, WHMCS\Module\Gateway\Stripe\Constant::$appPartnerId);
    Stripe\Stripe::setApiKey($params["secretKey"]);
    Stripe\Stripe::setApiVersion(WHMCS\Module\Gateway\Stripe\Constant::$apiVersion);
}

function stripe_ach_parseGatewayToken($data)
{
    $data = json_decode($data, true);
    if ($data && is_array($data)) {
        return $data;
    }
    return [];
}

function stripe_ach_findFirstCustomerToken(WHMCS\User\Contracts\ContactInterface $client)
{
    $clientToUse = $client;
    if ($clientToUse instanceof WHMCS\User\Client\Contact) {
        $clientToUse = $clientToUse->client;
    }
    foreach ($clientToUse->payMethods as $payMethod) {
        if ($payMethod->gateway_name == "stripe_ach") {
            $payment = $payMethod->payment;
            $token = stripe_ach_parsegatewaytoken($payment->getRemoteToken());
            if ($token) {
                return $token;
            }
        }
    }
    return NULL;
}

function stripe_ach_findFirstStripeCustomerId(WHMCS\User\Contracts\ContactInterface $client)
{
    $clientToUse = $client;
    if ($clientToUse instanceof WHMCS\User\Client\Contact) {
        $clientToUse = $clientToUse->client;
    }
    foreach ($clientToUse->payMethods as $payMethod) {
        if (in_array($payMethod->gateway_name, ["stripe", "stripe_ach", "stripe_sepa"])) {
            $payment = $payMethod->payment;
            $token = stripe_ach_parsegatewaytoken($payment->getRemoteToken());
            if ($token) {
                return $token["customer"];
            }
        }
    }
    $remoteCustomers = Stripe\Customer::all(["email" => $clientToUse->email, "limit" => 15]);
    foreach ($remoteCustomers->data as $customer) {
        $metaId = !empty($customer->metadata->clientId) ? (int) $customer->metadata->clientId : 0;
        if ($metaId === $clientToUse->id) {
            return $customer->id;
        }
    }
    return NULL;
}

function stripe_ach_statement_descriptor($params)
{
    $defaultDescriptor = Lang::trans("carttitle");
    $descriptor = $defaultDescriptor;
    if (isset($params["statementDescriptor"]) && 0 < strlen($params["statementDescriptor"])) {
        $descriptor = $params["statementDescriptor"];
        $invoiceNumber = array_key_exists("invoicenum", $params) && $params["invoicenum"] ? $params["invoicenum"] : $params["invoiceid"];
        $descriptor = str_replace(["{CompanyName}", "{InvoiceNumber}"], [WHMCS\Config\Setting::getValue("CompanyName"), $invoiceNumber], $descriptor);
    }
    $descriptor = voku\helper\ASCII::to_transliterate($descriptor);
    $descriptor = trim(str_replace(stripe_ach_statement_descriptor_invalid_characters(), "", $descriptor));
    if (strlen($descriptor) == 0) {
        $descriptor = $defaultDescriptor;
    }
    $descriptor = substr($descriptor, -22);
    return $descriptor;
}

function stripe_ach_statement_descriptor_invalid_characters()
{
    return [">", "<", "'", "\"", "*"];
}

function stripe_ach_create_customer($params)
{
    $client = $params["clientdetails"]["model"];
    if ($client instanceof WHMCS\User\Client\Contact) {
        $client = $client->client;
    }
    $stripeCustomer = Stripe\Customer::create(["description" => "Customer for " . $client->fullName . " (" . $client->email . ")", "email" => $client->email, "metadata" => ["id" => $client->id, "fullName" => $client->fullName, "email" => $client->email]]);
    return $stripeCustomer->id;
}
