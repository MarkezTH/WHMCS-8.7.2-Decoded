<?php

require "../../../init.php";
if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly.");
}
App::load_function("gateway");
App::load_function("invoice");
$event = NULL;
$passedParams = $returnData = [];
$payload = @file_get_contents("php://input");
try {
    $gatewayParams = getGatewayVariables("stripe");
    if (!$gatewayParams["type"]) {
        throw new WHMCS\Payment\Exception\InvalidModuleException("Module Not Activated");
    }
    $sigHeader = $_SERVER["HTTP_STRIPE_SIGNATURE"];
    stripe_start_stripe($gatewayParams);
    $webhookSecret = strpos($gatewayParams["secretKey"], "sk_live") !== false ? $gatewayParams["webhookEndpointSecret"] : $gatewayParams["webhookEndpointSandboxSecret"];
    $event = Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    if ($event->data->object instanceof Stripe\Charge) {
        $charge = $event->data->object;
        $payMethodDetails = $charge->payment_method_details;
        $protectionMessage = "";
        switch ($payMethodDetails["type"]) {
            case "sepa_debit":
                $protectionMessage = "Webhook intended for Stripe SEPA";
                break;
            case "ach_debit":
            case "ach_credit_transfer":
                $protectionMessage = "Webhook intended for Stripe ACH";
                break;
            default:
                if (!empty($protectionMessage)) {
                    throw new WHMCS\Exception\Module\NotServicable($protectionMessage);
                }
        }
    }
    switch ($event->type) {
        case "customer.source.updated":
        case "customer.card.updated":
            $newCard = $event->data->object;
            $stripeToken = $newCard->id;
            $stripeCustomer = Stripe\Customer::retrieve($newCard->customer);
            break;
        case "payment_method.updated":
            $paymentMethod = $event->data->object;
            $newCard = $paymentMethod->card;
            $stripeToken = $paymentMethod->id;
            $created = $paymentMethod->created;
            $stripeCustomer = Stripe\Customer::retrieve($paymentMethod->customer);
            if (empty($event->previous_attributes->card) || empty($event->previous_attributes->card->exp_date)) {
                WHMCS\Terminus::getInstance()->doExit();
            }
            break;
        default:
            WHMCS\Terminus::getInstance()->doExit();
            $stripeClientId = false;
            if (!empty($stripeCustomer->metadata->id)) {
                $stripeClientId = $stripeCustomer->metadata->id;
            } else {
                if (!empty($stripeCustomer->metadata->clientId)) {
                    $stripeClientId = $stripeCustomer->metadata->clientId;
                }
            }
            $client = $stripeClientId ? WHMCS\User\Client::find($stripeClientId) : WHMCS\User\Client::where("email", "=", !empty($stripeCustomer->metadata->email) ? $stripeCustomer->metadata->email : $stripeCustomer->email)->first();
            if (!$client) {
                throw new WHMCS\Exception\User\NoSuchUserException("Unable to determine client for update.");
            }
            $cardUpdated = false;
            $payMethodId = NULL;
            foreach ($client->payMethods as $payMethod) {
                if ($payMethod->gateway_name === "stripe") {
                    $payment = $payMethod->payment;
                    $token = stripe_parseGatewayToken($payment->getRemoteToken());
                    if ($token && $token["customer"] === $stripeCustomer->id && $token["method"] === $stripeToken) {
                        $payMethodId = $payMethod->id;
                        $payment->setLastFour($newCard->last4)->setExpiryDate(WHMCS\Carbon::createFromCcInput($newCard->exp_month . "/" . $newCard->exp_year))->save();
                        $payment->runCcUpdateHook();
                        $displayName = $payment->getDisplayName();
                        logActivity("Pay Method updated by Stripe - " . $displayName . " - User ID: " . $client->id);
                        $cardUpdated = true;
                        $returnData = ["Client ID" => $client->id, "Pay Method ID" => $payMethodId ?: "N/A", "payload" => $event->jsonSerialize()];
                        if ($cardUpdated) {
                            $logTransactionResult = "Automatic Card Update";
                        } else {
                            $logTransactionResult = "Automatic Card Update Failed";
                            $returnData["error"] = "Client identified however no matching card was detected for update.";
                        }
                    }
                }
            }
    }
} catch (WHMCS\Payment\Exception\InvalidModuleException $e) {
    $gatewayParams["paymentmethod"] = "stripe";
    $returnData = ["error" => $e->getMessage()];
    $logTransactionResult = "Module Not Active";
} catch (Stripe\Exception\SignatureVerificationException $e) {
    $returnData = ["payload" => $payload, "error" => $e->getMessage()];
    $logTransactionResult = "Invalid Access Attempt";
} catch (WHMCS\Exception\User\NoSuchUserException $e) {
    $returnData = ["payload" => $event->jsonSerialize(), "error" => $e->getMessage()];
    $logTransactionResult = "Client Not Found";
    http_response_code(400);
} catch (WHMCS\Exception\Module\NotServicable $e) {
    WHMCS\Terminus::getInstance()->doExit();
} catch (Exception $e) {
    $returnData = ["payload" => $returnData, "error" => $e->getMessage()];
    $logTransactionResult = "Invalid Response";
    http_response_code(400);
}
logTransaction("Stripe Webhooks", $returnData, $logTransactionResult, $passedParams);
WHMCS\Terminus::getInstance()->doExit();
