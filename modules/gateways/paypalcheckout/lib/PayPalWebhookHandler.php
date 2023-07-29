<?php

namespace WHMCS\Module\Gateway\Paypalcheckout;

class PayPalWebhookHandler
{
    protected $gatewayFriendlyName = "PayPal";
    protected $actionMap = ["PAYMENT.CAPTURE.PENDING" => "paymentCapturePending", "PAYMENT.CAPTURE.COMPLETED" => "paymentCaptureComplete", "BILLING.SUBSCRIPTION.CREATED" => "subscriptionCreated", "PAYMENT.SALE.COMPLETED" => "paymentCompleted", "BILLING.SUBSCRIPTION.SUSPENDED" => "subscriptionSuspended", "BILLING.SUBSCRIPTION.CANCELLED" => "subscriptionCancelled", "CUSTOMER.DISPUTE.RESOLVED" => "disputeResolved"];
    const GATEWAY_IDENTITY = "paypalcheckout";

    public function setFriendlyName($name)
    {
        $this->gatewayFriendlyName = $name;
        return $this;
    }

    public function execute($data)
    {
        $eventType = $data["event_type"];
        $methodName = array_key_exists($eventType, $this->actionMap) ? $this->actionMap[$eventType] : NULL;
        if ($methodName && method_exists($this, $methodName)) {
            return $this->{$methodName}($data);
        }
        return $this->responseRecordOnly();
    }

    protected function paymentCapturePending($data)
    {
        $payment = $data["resource"];
        $transactionId = $payment["id"];
        $invoiceId = $payment["invoice_id"];
        $paymentStatus = $payment["status"];
        $statusReason = $payment["status_details"]["reason"];
        $history = $this->newHistory();
        $history->invoiceId = $invoiceId;
        $history->transactionId = $transactionId;
        $history->remoteStatus = $paymentStatus;
        $history->description = $statusReason;
        $history->completed = false;
        if (isset($payment["amount"]["currency_code"])) {
            $currencyId = $this->safeLoadCurrencyId($payment["amount"]["currency_code"]);
            if ($currencyId) {
                $history->amount = $payment["amount"]["value"];
                $history->currencyId = $currencyId;
            }
            unset($currencyId);
        }
        $history->save();
        return "Payment Pending";
    }

    protected function paymentCaptureComplete($data)
    {
        $newHistory = function ($History, $data) {
            $payment = $data["resource"];
            $history = $this->newHistory();
            $history->transactionId = $payment["id"];
            $history->remoteStatus = $payment["status"];
            if (!empty($data["summary"])) {
                $history->description = $data["summary"];
            }
            $currencyId = $this->safeLoadCurrencyId($payment["amount"]["currency_code"]);
            if ($currencyId) {
                $history->amount = $payment["amount"]["value"];
                $history->currencyId = $currencyId;
            }
            return $history;
        };
        $actionName = "Payment Complete";
        $expected = ["resource.status"];
        if (!$this->isExpectedPayload($expected, $data)) {
            return $this->responseUnexpected("missing status");
        }
        $payment = $data["resource"];
        if (strtoupper($payment["status"]) == "PENDING") {
            return $this->paymentCapturePending($data);
        }
        if (strtoupper($payment["status"]) != "COMPLETED") {
            return $this->responseUnexpected("unknown status");
        }
        $expected = ["resource.id", "resource.invoice_id", "resource.amount.value", "resource.amount.currency_code", "resource.seller_receivable_breakdown.paypal_fee.value"];
        $missing = "";
        if (!$this->isExpectedPayload($expected, $data, $missing)) {
            return $this->responseUnexpected("missing '" . $missing . "'");
        }
        unset($missing);
        $unpaidInvoice = \WHMCS\Billing\Invoice::unpaidOrPaymentPending()->where("id", $payment["invoice_id"])->first();
        if ($unpaidInvoice) {
            $this->assertCurrencyCode($actionName, $payment["amount"]["currency_code"] ?? "", $unpaidInvoice->getCurrency()["code"] ?? "");
            try {
                $unpaidInvoice->addPaymentIfNotExists($payment["amount"]["value"], $payment["id"], $payment["seller_receivable_breakdown"]["paypal_fee"]["value"], self::GATEWAY_IDENTITY);
            } catch (\Exception $e) {
                throw new \WHMCS\Exception($actionName . ": Transaction ID already exists");
            }
            $history = $newHistory($data);
            $history->invoiceId = $unpaidInvoice->id;
            $history->completed = 1;
            $history->save();
            return $actionName;
        }
        $invoice = \WHMCS\Billing\Invoice::find($payment["invoice_id"]);
        if (is_null($invoice)) {
            throw new \WHMCS\Exception($actionName . ": No invoice found");
        }
        if (is_null($invoice->client)) {
            throw new \WHMCS\Exception($actionName . ": Missing invoice client");
        }
        $this->assertCurrencyCode($actionName, $payment["amount"]["currency_code"] ?? "", $invoice->client->currencyrel->code ?? "");
        \WHMCS\Billing\Payment\Transaction::assertUnique(self::GATEWAY_IDENTITY, $payment["id"]);
        $transaction = $this->newTransaction($payment["id"], $invoice->client);
        $transaction->description = "PayPal " . $actionName;
        $transaction->amountIn = $payment["amount"]["value"];
        $transaction->fees = $payment["seller_receivable_breakdown"]["paypal_fee"]["value"];
        $transaction->save();
        $invoice->client->addCredit("PayPal Payment Transaction ID " . $payment["id"], $payment["amount"]["value"]);
        $history = $newHistory($data);
        $history->invoiceId = $invoice->id;
        $history->completed = 1;
        $history->description = "PayPal Additional " . $actionName;
        $history->save();
        return $actionName;
    }

    protected function subscriptionCreated($data)
    {
        $subscription = $data["resource"];
        $subId = $subscription["id"];
        $invoice = NULL;
        $invoice = \WHMCS\Billing\Invoice::unpaidOrPaymentPending()->subscriptionId($subId)->orderBy("duedate")->first();
        if (is_null($invoice)) {
            $invoiceId = $subscription["custom_id"] ?? NULL;
            if (!empty($invoiceId)) {
                $invoice = \WHMCS\Billing\Invoice::find($invoiceId);
            }
            unset($invoiceId);
        }
        if ($invoice instanceof \WHMCS\Billing\Invoice) {
            $history = \WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoice->id, "gateway" => "paypalcheckout", "transaction_id" => $subId]);
            $history->remoteStatus = $data["summary"];
            $history->description = "";
            $history->completed = true;
            $history->save();
            return "Subscription Created";
        }
        $this->logOrphanedSubscription($data["event_type"], $subId);
        throw new \WHMCS\Exception("Subscription Created: No invoice found");
    }

    protected function paymentCompleted($data)
    {
        $payment = $data["resource"];
        $transactionId = $payment["id"];
        $amount = $payment["amount"];
        $total = $amount["total"];
        $currency = $amount["currency"];
        $transactionFee = $payment["transaction_fee"];
        $feeAmount = $transactionFee["value"];
        $billingAgreementId = $payment["billing_agreement_id"];
        if (!$billingAgreementId) {
            return "Information Only";
        }
        $clientIdForCredit = 0;
        $fallbackInvoice = NULL;
        $firstUnpaidInvoice = \WHMCS\Billing\Invoice::unpaidOrPaymentPending()->subscriptionId($billingAgreementId)->orderBy("duedate")->first();
        $invoiceId = $payment["custom"] ?? NULL;
        if (is_null($firstUnpaidInvoice) && !empty($invoiceId)) {
            $fallbackInvoice = \WHMCS\Billing\Invoice::find($invoiceId);
            if (!is_null($fallbackInvoice) && $fallbackInvoice->canPaymentBeApplied()) {
                $firstUnpaidInvoice = $fallbackInvoice;
            }
        }
        unset($invoiceId);
        if (!$firstUnpaidInvoice) {
            $service = \WHMCS\Service\Service::where("subscriptionid", $billingAgreementId)->first();
            if (!is_null($service)) {
                $clientIdForCredit = $service->userId;
            }
            if (!$clientIdForCredit) {
                $addon = \WHMCS\Service\Addon::where("subscriptionid", $billingAgreementId)->first();
                if (!is_null($addon)) {
                    $clientIdForCredit = $addon->userId;
                }
            }
            if (!$clientIdForCredit) {
                $domain = \WHMCS\Domain\Domain::where("subscriptionid", $billingAgreementId)->first();
                if (!is_null($domain)) {
                    $clientIdForCredit = $domain->userId;
                }
            }
            if (empty($clientIdForCredit) && $fallbackInvoice instanceof \WHMCS\Billing\Invoice) {
                $clientIdForCredit = $fallbackInvoice->clientId;
                $this->logMissingSubscription($data["event_type"], $billingAgreementId, $fallbackInvoice->id, $clientIdForCredit);
            }
            if (!$clientIdForCredit) {
                $this->logOrphanedSubscription($data["event_type"], $billingAgreementId);
                throw new \WHMCS\Exception("Subscription Payment: No invoice found");
            }
            unset($related);
        }
        unset($fallbackInvoice);
        if ($firstUnpaidInvoice) {
            if (is_null($currency) || strlen(trim($currency)) == 0 || $currency != $firstUnpaidInvoice->getCurrency()["code"]) {
                throw new \WHMCS\Exception("Subscription Payment: Invalid currency");
            }
            try {
                $firstUnpaidInvoice->addPaymentIfNotExists($total, $transactionId, $feeAmount, "paypalcheckout");
                return "Subscription Payment: Success";
            } catch (\Exception $e) {
                throw new \WHMCS\Exception("Subscription Payment: Transaction ID already exists");
            }
        } else {
            if ($clientIdForCredit) {
                $client = \WHMCS\User\Client::find($clientIdForCredit);
                if (!trim($currency) || $currency != $client->currencyrel->code) {
                    throw new \WHMCS\Exception("Subscription Payment: Invalid currency");
                }
                $existingTransaction = \WHMCS\Billing\Payment\Transaction::where("transid", $transactionId)->first();
                if (!is_null($existingTransaction)) {
                    throw new \WHMCS\Exception("Subscription Payment: Transaction ID already exists");
                }
                $transaction = new \WHMCS\Billing\Payment\Transaction();
                $transaction->clientId = $client->id;
                $transaction->currency = $client->currencyrel->id;
                $transaction->gateway = "paypalcheckout";
                $transaction->date = \WHMCS\Carbon::now();
                $transaction->description = "PayPal Subscription Payment";
                $transaction->amountIn = $total;
                $transaction->fees = $feeAmount;
                $transaction->exchangeRate = $client->currencyrel->rate;
                $transaction->transactionId = $transactionId;
                $transaction->save();
                $client->addCredit("PayPal Subscription Transaction ID " . $transactionId, $total);
                return "Subscription Payment: Credited";
            }
        }
    }

    protected function subscriptionSuspended($data)
    {
        $subscription = $data["resource"];
        $subId = $subscription["id"];
        $invoice = \WHMCS\Billing\Invoice::unpaidOrPaymentPending()->subscriptionId($subId)->orderBy("duedate")->first();
        if (!$invoice) {
            $invoice = \WHMCS\Billing\Invoice::subscriptionId($subId)->orderBy("duedate", "desc")->first();
        }
        if (!$invoice) {
            throw new \WHMCS\Exception("Subscription Suspended: No invoice found");
        }
        $history = new \WHMCS\Billing\Payment\Transaction\History();
        $history->invoice_id = $invoice->id;
        $history->gateway = "paypalcheckout";
        $history->transactionId = $subId;
        $history->remoteStatus = "Subscription Suspended";
        $history->description = "Subscription reached the maximum number of failed retry attempts";
        $history->completed = true;
        $history->save();
        return "Subscription Suspended: Ok";
    }

    protected function subscriptionCancelled($data)
    {
        $subscription = $data["resource"];
        $subId = $subscription["id"];
        $invoice = \WHMCS\Billing\Invoice::unpaidOrPaymentPending()->subscriptionId($subId)->orderBy("duedate")->first();
        if (!$invoice) {
            $invoice = \WHMCS\Billing\Invoice::subscriptionId($subId)->orderBy("duedate", "desc")->first();
        }
        if ($invoice) {
            $history = new \WHMCS\Billing\Payment\Transaction\History();
            $history->invoice_id = $invoice->id;
            $history->gateway = "paypalcheckout";
            $history->transactionId = $subId;
            $history->remoteStatus = "Subscription Cancelled";
            $history->description = "";
            $history->completed = true;
            $history->save();
        }
        foreach (\WHMCS\Service\Service::where("subscriptionid", $subId)->get() as $service) {
            $service->subscriptionId = "";
            $service->save();
            logActivity("PayPal Subscription Cancellation Auto Removal of Subscription ID - Service ID: " . $service->id, $service->userId);
        }
        foreach (\WHMCS\Service\Addon::where("subscriptionid", $subId)->get() as $addon) {
            $addon->subscriptionId = "";
            $addon->save();
            logActivity("PayPal Subscription Cancellation Auto Removal of Subscription ID - Service Addon ID: " . $addon->id, $addon->userId);
        }
        foreach (\WHMCS\Domain\Domain::where("subscriptionid", $subId)->get() as $domain) {
            $domain->subscriptionId = "";
            $domain->save();
            logActivity("PayPal Subscription Cancellation Auto Removal of Subscription ID - Domain ID: " . $domain->id, $domain->userId);
        }
        return "Subscription Cancelled";
    }

    protected function disputeResolved($data)
    {
        $dispute = $data["resource"];
        $disputeId = $dispute["dispute_id"];
        $transactions = $dispute["disputed_transactions"];
        $reason = $dispute["reason"];
        $status = $dispute["status"];
        $disputeOutcome = $dispute["dispute_outcome"];
        $disputeAmount = $dispute["dispute_amount"];
        $disputeLifeCycleStage = $dispute["dispute_life_cycle_stage"];
        $disputeChannel = $dispute["dispute_channel"];
        if ($status == "RESOLVED") {
            $disputeOutcomeCode = $disputeOutcome["outcome_code"];
            if ($disputeOutcomeCode == "RESOLVED_BUYER_FAVOUR") {
                foreach ($transactions as $transaction) {
                    $originalTransactionId = $transaction["seller_transaction_id"];
                    $sellerProtectionEligible = $transaction["seller_protection_eligible"];
                    try {
                        paymentReversed($disputeId, $originalTransactionId, 0, "paypalcheckout");
                        return "Dispute Resolved: Payment Reversed";
                    } catch (\Exception $e) {
                        throw new \WHMCS\Exception("Payment Reversal Could Not Be Completed: " . $e->getMessage());
                    }
                }
            }
            throw new \WHMCS\Exception("Dispute Resolved: No action");
        } else {
            throw new \WHMCS\Exception("Dispute Resolved: Unrecognised Status");
        }
    }

    protected function isExpectedPayload($expected, $source = "", &$firstMissing)
    {
        foreach ($expected as $keyString) {
            $sourcePointer =& $source;
            foreach (explode(".", $keyString) as $key) {
                if (isset($sourcePointer[$key])) {
                    if (is_array($sourcePointer[$key])) {
                        $sourcePointer =& $sourcePointer[$key];
                    }
                } else {
                    $firstMissing = $key;
                    return false;
                }
            }
        }
        return true;
    }

    protected function safeLoadCurrencyId($currencyCode)
    {
        $nope = 0;
        if (empty($currencyCode)) {
            return $nope;
        }
        $currency = \WHMCS\Billing\Currency::where("code", $currencyCode)->first();
        if (!$currency) {
            return $nope;
        }
        return $currency->id;
    }

    protected function assertCurrencyCode($action, $payloadCode, $targetCode)
    {
        if (empty($payloadCode) || $payloadCode != $targetCode) {
            throw new \WHMCS\Exception($action . ": Invalid currency");
        }
    }

    protected function newHistory()
    {
        $history = new \WHMCS\Billing\Payment\Transaction\History();
        $history->gateway = self::GATEWAY_IDENTITY;
        $history->description = "";
        return $history;
    }

    protected function newTransaction($transactionId, \WHMCS\User\Client $client)
    {
        $transaction = new \WHMCS\Billing\Payment\Transaction();
        $transaction->transactionId = $transactionId;
        $transaction->gateway = self::GATEWAY_IDENTITY;
        $transaction->clientId = $client->id;
        $transaction->currency = $client->currencyrel->id;
        $transaction->exchangeRate = $client->currencyrel->rate;
        $transaction->date = \WHMCS\Carbon::now();
        return $transaction;
    }

    protected function responseRecordOnly()
    {
        return "Information Only";
    }

    protected function responseUnexpected($memo)
    {
        return "Unexpected Payload Structure" . ($memo != "" ? " (" . $memo . ")" : "");
    }

    protected function logMissingSubscription($eventName, $subscriptionIdentifier, $invoiceId, $clientId)
    {
        $message = sprintf("[%s] [%s] The system has detected a missing subscription. The subscription is not associated with any services, but the event was attributable to a client and/or invoice. - Subscription ID: %s - Invoice ID: %d", $this->gatewayFriendlyName ?: self::GATEWAY_IDENTITY, $eventName, $subscriptionIdentifier, $invoiceId);
        logActivity($message, $clientId, ["withClientId" => true]);
    }

    protected function logOrphanedSubscription($eventName, $subscriptionIdentifier)
    {
        $message = sprintf("[%s] [%s] The system has detected an orphaned subscription. It is not associated with any services, nor was attributable to an invoice or client. - Subscription ID: %s", $this->gatewayFriendlyName ?: self::GATEWAY_IDENTITY, $eventName, $subscriptionIdentifier);
        logActivity($message);
    }
}
