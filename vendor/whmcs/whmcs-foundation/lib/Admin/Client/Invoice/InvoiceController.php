<?php

namespace WHMCS\Admin\Client\Invoice;

class InvoiceController
{
    public function capture(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = (int) $request->getAttribute("userId");
        $invoiceId = (int) $request->getAttribute("invoiceId");
        $invoice = \WHMCS\Billing\Invoice::find($invoiceId);
        if (!$invoice || $invoice->client->id != $clientId) {
        }
        $client = $invoice->client;
        $client->migratePaymentDetailsIfRequired();
        $payMethods = $client->payMethods()->get();
        $bankGateway = false;
        if ($invoice->paymentGateway) {
            $payMethods = $payMethods->forGateway($invoice->paymentGateway);
            if (0 < $payMethods->count()) {
                $payMethod = $payMethods->first();
                if ($payMethod && ($payMethod->isBankAccount() || $payMethod->isRemoteBankAccount())) {
                    $bankGateway = true;
                }
            }
        }
        $body = view("admin.client.invoice.capture", ["payMethods" => $payMethods, "client" => $client, "invoice" => $invoice, "viewHelper" => new \WHMCS\Admin\Client\PayMethod\ViewHelper(), "showCvc" => !$bankGateway]);
        $body = (new \WHMCS\Admin\ApplicationSupport\View\PreRenderProcessor())->process($body);
        $response = new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
        return $response;
    }

    public function doCapture(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $clientId = (int) $request->getAttribute("userId");
            $invoiceId = (int) $request->getAttribute("invoiceId");
            $payMethodId = (int) $request->get("paymentId");
            $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient($payMethodId, $clientId);
            $invoice = \WHMCS\Billing\Invoice::find($invoiceId);
            if (!$payMethod || $payMethod->client->id != $clientId || !$invoice || $invoice->client->id != $clientId) {
                throw new \WHMCS\Payment\Exception\InvalidModuleException("Invalid Access Attempt");
            }
            if (in_array($invoice->status, ["Paid", "Cancelled"])) {
                throw new \WHMCS\Exception\Validation\InvalidValue("Invalid Status for Capture");
            }
            logActivity("Admin Initiated Payment Capture - Invoice ID: " . $invoice->id, $clientId);
            $success = $payMethod->capture($invoice, (int) $request->get("cardcvv"));
            if (is_string($success) && $success == "success" || is_string($success) && $success == "pending" || is_bool($success) && $success) {
                $success = true;
            }
            $response = new \WHMCS\Http\Message\JsonResponse(["body" => \AdminLang::trans("general.pleaseWait"), "disableSubmit" => true, "dismissLoader" => false, "redirect" => "invoices.php?action=edit&id=" . $invoiceId . "&payment=" . $success]);
        } catch (\Exception $e) {
            $body = $e->getMessage();
            $response = new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
        }
        return $response;
    }
}
