<?php

require "../../../init.php";
App::load_function("gateway");
App::load_function("invoice");
$gatewayParams["paymentmethod"] = "stripe_sepa";
$logTransactionResult = "";
$data = $passedParams = [];
$payload = @file_get_contents("php://input");
try {
    $gatewayParams = getGatewayVariables("stripe_sepa");
    if (!$gatewayParams["type"]) {
        throw new WHMCS\Payment\Exception\InvalidModuleException("Module Not Activated");
    }
    $passedParams = [];
    $sigHeader = $_SERVER["HTTP_STRIPE_SIGNATURE"];
    $event = NULL;
    stripe_sepa_start_stripe($gatewayParams);
    $event = Stripe\Webhook::constructEvent($payload, $sigHeader, $gatewayParams["webhookEndpointSecret"]);
    if ($event->data->object instanceof Stripe\Charge) {
        $charge = $event->data->object;
        $payMethodDetails = $charge->payment_method_details;
        $protectionMessage = "";
        switch ($payMethodDetails["type"]) {
            case "card":
            case "card_present":
                $protectionMessage = "Webhook intended for Stripe";
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
        case "charge.succeeded":
            $charge = $event->data->object;
            $chargeMetaData = json_decode(json_encode($charge->metadata), true);
            $transaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
            $transactionExchangeRate = $transaction->exchange_rate;
            $conversionCurrency = WHMCS\Billing\Currency::where("code", strtoupper($charge->currency))->first();
            $transactionId = $transaction->id;
            checkCbTransID($transactionId);
            $invoiceId = $charge->metadata->id;
            try {
                $invoice = WHMCS\Billing\Invoice::with("client")->findOrFail($invoiceId);
                $transactionFee = $transaction->fee / 100;
                $amount = $transaction->amount / 100;
                if ($transactionExchangeRate) {
                    $transactionFee /= $transactionExchangeRate;
                    $amount /= $transactionExchangeRate;
                    $convertCurrency = $gatewayParams["convertto"];
                    if (!$convertCurrency) {
                        $convertCurrency = $invoice->client->currencyId;
                    }
                    if ($convertCurrency && $conversionCurrency) {
                        $transactionFee = convertCurrency($transactionFee, $conversionCurrency->id, $convertCurrency, 1);
                        $amount = convertCurrency($amount, $conversionCurrency->id, $convertCurrency, 1);
                    }
                }
                $history = WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoice->id, "gateway" => $gatewayParams["paymentmethod"], "transaction_id" => $transactionId]);
                $history->remoteStatus = $charge->status;
                $history->description = "Payment Confirmed";
                $history->additionalInformation = $charge->jsonSerialize();
                $history->completed = true;
                $history->save();
                $passedParams["history_id"] = $history->id;
                checkCbTransID($transactionId);
                $invoice->addPayment($amount, $transactionId, $transactionFee, $gatewayParams["paymentmethod"]);
                $data = ["charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()];
                $logTransactionResult = "Success";
            } catch (Exception $e) {
                $data = ["message" => "Invalid Invoice ID", "invoiceIdReturned" => $invoiceId, "event" => $event->jsonSerialize(), "charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()];
                $logTransactionResult = "Error";
            }
            break;
        case "charge.failed":
            $charge = $event->data->object;
            $invoiceId = $charge->metadata->id;
            $transaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction);
            try {
                $invoice = WHMCS\Billing\Invoice::findOrFail($invoiceId);
                $history = WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoice->id, "gateway" => $gatewayParams["paymentmethod"], "transaction_id" => $transaction->id]);
                $history->remoteStatus = $charge->status;
                $history->description = $charge->failure_message;
                $history->additionalInformation = $charge->jsonSerialize();
                $history->completed = false;
                $history->save();
                $passedParams["history_id"] = $history->id;
                $invoice->status = "Unpaid";
                $invoice->save();
                $emailTemplate = "Credit Card Payment Failed";
                sendMessage($emailTemplate, $invoiceId);
                $data = ["event" => $event->jsonSerialize(), "charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()];
                $logTransactionResult = "Payment Failed";
            } catch (Exception $e) {
                $data = ["message" => "Invalid Invoice ID", "invoiceIdReturned" => $invoiceId, "event" => $event->jsonSerialize(), "charge" => $charge->jsonSerialize(), "transaction" => $transaction->jsonSerialize()];
                $logTransactionResult = "Error";
            }
            break;
        default:
            WHMCS\Terminus::getInstance()->doExit();
    }
} catch (WHMCS\Payment\Exception\InvalidModuleException $e) {
    $gatewayParams["paymentmethod"] = "stripe_sepa";
    $data = ["error" => $e->getMessage()];
    $logTransactionResult = "Module Not Active";
} catch (Stripe\Exception\SignatureVerificationException $e) {
    $data = ["payload" => $payload, "error" => $e->getMessage()];
    $logTransactionResult = "Invalid Access Attempt";
} catch (WHMCS\Exception\Module\NotServicable $e) {
    WHMCS\Terminus::getInstance()->doExit();
} catch (Exception $e) {
    $data = ["payload" => $payload, "error" => $e->getMessage()];
    $logTransactionResult = "Invalid Response";
    http_response_code(400);
}
logTransaction($gatewayParams["paymentmethod"], $data, $logTransactionResult, $passedParams);
WHMCS\Terminus::getInstance()->doExit();
