<?php

namespace WHMCS\Admin\Billing;

class BillingController
{
    public function newInvoice($JsonResponse, $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.billing.invoice.new", ["gateways" => \WHMCS\Module\GatewaySetting::getActiveGatewayFriendlyNames(), "invoiceGenerationDays" => \WHMCS\Config\Setting::getValue("CreateInvoiceDaysBefore")])]);
    }

    public function createInvoice($JsonResponse, $request)
    {
        $clientId = $request->get("client");
        $gateway = $request->get("gateway");
        $invoiceDate = $request->get("date");
        $dueDate = $request->get("due");
        $invoice = \WHMCS\Billing\Invoice::newInvoice($clientId, $gateway);
        if ($invoiceDate) {
            $invoice->dateCreated = \WHMCS\Carbon::createFromAdminDateFormat($invoiceDate);
        }
        if ($dueDate) {
            $invoice->dateDue = \WHMCS\Carbon::createFromAdminDateFormat($dueDate);
        }
        $invoice->save();
        $redirectUrl = \WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl() . "/invoices.php?action=edit&id=" . $invoice->id;
        return new \WHMCS\Http\Message\JsonResponse(["redirect" => $redirectUrl]);
    }

    public function gatewayBalancesTotals($JsonResponse, $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "body" => \WHMCS\Gateways::gatewayBalancesTotalsView(false, $request)]);
    }

    public function transactionInformation($JsonResponse, $request)
    {
        if (!function_exists("getClientsDetails")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $id = $request->get("id");
        try {
            $transaction = \WHMCS\Billing\Payment\Transaction::with(["client", "invoice"])->findOrFail($id);
            $gateway = $transaction->paymentGateway;
            if (!$gateway) {
                throw new \WHMCS\Exception("Invalid Request");
            }
            $gatewayInterface = \WHMCS\Module\Gateway::factory($gateway);
            if (!$gatewayInterface->functionExists("TransactionInformation")) {
                throw new \WHMCS\Exception\Module\NotServicable("Transaction information not supported for gateway.");
            }
            if ($transaction->client) {
                $client = $transaction->client;
            } else {
                $client = $transaction->invoice->client;
            }
            $transactionInformation = $gatewayInterface->call("TransactionInformation", ["transactionId" => $transaction->transactionId, "clientdetails" => getClientsDetails($client)]);
            $vars = ["errorMessage" => NULL, "transaction" => $transaction, "transactionInformation" => $transactionInformation, "gatewayInterface" => $gatewayInterface];
        } catch (\WHMCS\Exception\Module\NotServicable $e) {
            $vars = ["errorMessage" => $e->getMessage()];
        } catch (\WHMCS\Exception\Fatal $e) {
            $vars = ["errorMessage" => "Inactive or Missing Gateway"];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $vars = ["errorMessage" => "Invalid Transaction"];
        } catch (\Throwable $t) {
            $vars = ["errorMessage" => $t->getMessage()];
        }
        $body = view("admin.billing.transaction.information", $vars);
        return new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
    }
}
