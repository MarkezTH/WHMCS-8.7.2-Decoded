<?php

namespace WHMCS\Module\Gateway\GoCardless\Resources;

class Payments extends AbstractResource
{
    const CANCELLED_STATES = ["cancelled", "customer_approval_denied", "failed", "charged_back", "paid_out"];

    public function confirmed($event)
    {
        $paymentId = $event["links"]["payment"];
        $response = json_decode($this->client->get("payments/" . $paymentId), true);
        checkCbTransID($response["payments"]["id"]);
        $invoiceId = (int) $response["payments"]["metadata"]["invoice_id"];
        $invoice = \WHMCS\Billing\Invoice::findOrFail($invoiceId);
        $invoiceDetails = $response["payments"]["metadata"]["invoice_details"];
        if (!$invoiceDetails) {
            throw new \WHMCS\Module\Gateway\GoCardless\Exception\MalformedResponseException("Invalid Payment Response");
        }
        $invoiceDetails = explode("|", $invoiceDetails);
        $paymentAmount = $invoiceDetails[0];
        $history = \WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoiceId, "gateway" => $this->params["paymentmethod"], "transaction_id" => $response["payments"]["id"]]);
        $history->remoteStatus = $response["payments"]["status"];
        $history->description = "Payment Confirmed";
        $history->additionalInformation = $response["payments"];
        $history->completed = true;
        $history->save();
        logTransaction($this->params["paymentmethod"], $event, "Payment Confirmed", array_merge($this->params, ["history_id" => $history->id]));
        $invoice->addPayment($paymentAmount, $response["payments"]["id"], 0, $this->params["paymentmethod"]);
    }

    public function failed($event)
    {
        $paymentId = $event["links"]["payment"];
        $response = json_decode($this->client->get("payments/" . $paymentId), true);
        $invoiceId = (int) $response["payments"]["metadata"]["invoice_id"];
        $history = \WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoiceId, "gateway" => $this->params["paymentmethod"], "transaction_id" => $response["payments"]["id"]]);
        $history->remoteStatus = $response["payments"]["status"];
        $history->description = $response["payments"]["description"];
        $history->additionalInformation = $response["payments"];
        $history->completed = false;
        $history->save();
        $failedReason = $event["details"]["cause"];
        switch ($failedReason) {
            case "insufficient_funds":
            case "refer_to_payer":
            case "bank_account_transferred":
                $metadata = $response["payments"]["metadata"];
                try {
                    $this->client->post("payments/" . $paymentId . "/actions/retry", ["json" => ["data" => ["metadata" => $metadata]]]);
                    $history->remoteStatus = "pending_submission";
                    $history->description = "Payment Retry Submitted";
                    $history->save();
                    logTransaction($this->params["paymentmethod"], $event, "Payment Retry Submitted", $this->params);
                    return NULL;
                } catch (\Exception $e) {
                }
                break;
            default:
                $emailTemplate = "Direct Debit Payment Failed";
                sendMessage($emailTemplate, $invoiceId);
                logTransaction($this->params["paymentmethod"], $event, "Payment Failed", $this->params);
        }
    }

    public function charged_back($event)
    {
        $paymentId = $event["links"]["payment"];
        $response = json_decode($this->client->get("payments/" . $paymentId), true);
        $invoiceId = (int) $response["payments"]["metadata"]["invoice_id"];
        \WHMCS\Billing\Invoice::findOrFail($invoiceId);
        $history = \WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoiceId, "gateway" => $this->params["paymentmethod"], "transaction_id" => $response["payments"]["id"]]);
        $history->remoteStatus = $response["payments"]["status"];
        $history->additionalInformation = $response["payments"];
        $history->description = $response["payments"]["description"];
        $history->save();
        logTransaction($this->params["paymentmethod"], $event, "Payment Reversed", $this->params);
        paymentReversed($paymentId . "-reverse", $paymentId, $invoiceId, $this->params["paymentmethod"]);
    }

    public function cancelled($event)
    {
        $paymentId = $event["links"]["payment"];
        $response = json_decode($this->client->get("payments/" . $paymentId), true);
        $invoiceId = (int) $response["payments"]["metadata"]["invoice_id"];
        $invoice = \WHMCS\Billing\Invoice::findOrFail($invoiceId);
        $history = \WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoiceId, "gateway" => $this->params["paymentmethod"], "transaction_id" => $response["payments"]["id"]]);
        $history->remoteStatus = $response["payments"]["status"];
        $history->additionalInformation = $response["payments"];
        $history->description = $response["payments"]["description"];
        $history->completed = false;
        $history->save();
        $cancelledReason = $event["details"]["cause"];
        switch ($cancelledReason) {
            case "mandate_cancelled":
            case "bank_account_closed":
            case "bank_account_transferred":
            case "authorisation_disputed":
            case "invalid_bank_details":
            case "direct_debit_not_enabled":
            case "mandate_expired":
                $existingMandateId = "";
                if ($invoice->payMethod && $invoice->payMethod->payment && $invoice->payMethod->payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                    $existingMandateId = $invoice->payMethod->payment->getRemoteToken();
                }
                $mandateId = $response["payments"]["links"]["mandate"];
                if ($existingMandateId == $mandateId) {
                    invoiceDeletePayMethod($invoice->id);
                    $invoice->status = "Unpaid";
                    $invoice->save();
                }
                break;
            case "payment_cancelled":
            case "other":
            default:
                $emailTemplate = "Direct Debit Payment Failed";
                sendMessage($emailTemplate, $invoiceId);
                logTransaction($this->params["paymentmethod"], $event, "Payment Cancelled", $this->params);
        }
    }

    public function defaultAction($event)
    {
        $paymentId = $event["links"]["payment"];
        $response = json_decode($this->client->get("payments/" . $paymentId), true);
        $invoiceId = (int) $response["payments"]["metadata"]["invoice_id"];
        $history = \WHMCS\Billing\Payment\Transaction\History::firstOrNew(["invoice_id" => $invoiceId, "gateway" => $this->params["paymentmethod"], "transaction_id" => $response["payments"]["id"]]);
        $history->remoteStatus = $response["payments"]["status"];
        $history->additionalInformation = $response["payments"];
        $history->description = $response["payments"]["description"];
        $history->save();
        logTransaction($this->params["paymentmethod"], $event, "Payment Notification", array_merge($this->params, ["history_id" => $history->id]));
    }
}
