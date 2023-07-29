<?php

namespace WHMCS\Module\Gateway\Paypalcheckout;

use WHMCS\Application\Support\Facades\Auth;

class PaypalController
{
    public function createOrder(\WHMCS\Http\Message\ServerRequest $request)
    {
        $requestBody = $this->decodeJsonBodyResponse((string) $request->getBody());
        check_token("WHMCS.default", $requestBody["token"]);
        $invoiceId = (int) $request->get("invoiceid");
        $forceOneTime = $requestBody["forceonetime"];
        try {
            if (0 < $invoiceId) {
                $client = Auth::client();
                $invoice = \WHMCS\Billing\Invoice::findOrFail($invoiceId);
                if ($invoice->userid != $client->id) {
                    throw new \WHMCS\Exception("Invalid request.");
                }
                if (!$invoice->requiresPayment()) {
                    return new \WHMCS\Http\Message\JsonResponse(["error" => "Invoice payment not required"]);
                }
                $cart = $invoice->cart();
                $intent = "CAPTURE";
            } else {
                $cart = \WHMCS\Cart\CartCalculator::fromSession();
                $intent = "AUTHORIZE";
                $invoice = NULL;
            }
            $gatewayInterface = \WHMCS\Module\Gateway::factory("paypalcheckout");
            $settings = $gatewayInterface->loadSettings();
            $clientId = $params["sandbox"] ? $params["sandboxClientId"] : $params["clientId"];
            if (paypalcheckout_isRecurringPossible($cart) && !$forceOneTime) {
                return new \WHMCS\Http\Message\JsonResponse(["paypalPlanId" => $this->createPlanId($cart, $clientId)]);
            }
            $paypalApi = new PaypalApi();
            $total = $cart->getTotal()->toNumeric();
            $currency = $cart->total->getCurrency()["code"];
            $orderId = $paypalApi->createOrder(\WHMCS\View\Formatter\Price::adjustDecimals($total, $currency), $currency, $intent, \WHMCS\Config\Setting::getValue("CompanyName"), $cart->client, $invoice);
            return new \WHMCS\Http\Message\JsonResponse(["paypalOrderId" => $orderId]);
        } catch (Exception\AuthError $e) {
            return new \WHMCS\Http\Message\JsonResponse(["error" => "PayPal API Authentication failed."]);
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["error" => "PayPal Create Order Error: " . $e->getMessage()]);
        }
    }

    public function createPlanId($cart, $clientId)
    {
        $firstItem = $cart->getFirstRecurringItem();
        $paypalProduct = \WHMCS\Payment\PaymentGatewayProductMapping::gateway("paypal")->accountIdentifier(md5($clientId))->productIdentifier($firstItem->id)->first();
        if (is_null($paypalProduct)) {
            $paypalApi = new PaypalApi();
            $paypalProduct = new \WHMCS\Payment\PaymentGatewayProductMapping();
            $paypalProduct->gateway = "paypal";
            $paypalProduct->account_identifier = md5($clientId);
            $paypalProduct->product_identifier = $firstItem->id;
            $paypalProduct->remote_identifier = $paypalApi->createProduct($firstItem->name, $cart->getDescription());
            if (!$paypalProduct->remote_identifier) {
                throw new \WHMCS\Exception("Unable to create product. Please refer to the module log for further details.");
            }
            $paypalProduct->save();
        }
        $initialPeriod = $initialCycle = NULL;
        if ($firstItem->hasInitialPeriod()) {
            $initialPeriod = $firstItem->initialPeriod;
            $initialCycle = $firstItem->initialCycle;
        }
        $paypalApi = new PaypalApi();
        $total = $cart->getTotal()->toNumeric();
        $recurringTotal = $cart->getRecurringTotal();
        $currency = $cart->total->getCurrency()["code"];
        $planId = $paypalApi->createProductPlan($paypalProduct->remote_identifier, $firstItem->name, $cart->getDescription(), \WHMCS\View\Formatter\Price::adjustDecimals($total, $currency), \WHMCS\View\Formatter\Price::adjustDecimals($recurringTotal, $currency), $firstItem->billingCycle, $firstItem->billingPeriod, $currency, $initialCycle, $initialPeriod);
        return $planId;
    }

    public function validateOrder(\WHMCS\Http\Message\ServerRequest $request)
    {
        $requestBody = $this->decodeJsonBodyResponse((string) $request->getBody());
        check_token("WHMCS.default", $requestBody["token"]);
        $paypalOrderId = $requestBody["paypalorderid"];
        $paypalSubscriptionId = $requestBody["paypalsubid"];
        $paypalApi = new PaypalApi();
        $details = $paypalApi->getOrderDetails($paypalOrderId);
        $payer = $details->payer;
        $payerFirstName = $payer->name->given_name;
        $payerLastName = $payer->name->surname;
        $payerEmail = $payer->email_address;
        $shippingAddress = $details->purchase_units[0]->shipping->address;
        $state = (new \WHMCS\Utility\Country())->getStateNameFromCode($shippingAddress->country_code, $shippingAddress->admin_area_1);
        $redirectUrl = (new \WHMCS\OrderForm())->startExpressCheckout("paypalcheckout", ["orderId" => $paypalOrderId, "subscriptionId" => $paypalSubscriptionId, "paypalEmail" => $payerEmail], ["firstname" => $payerFirstName, "lastname" => $payerLastName, "email" => $payerEmail, "address1" => $shippingAddress->address_line_1, "city" => $shippingAddress->admin_area_2, "state" => $state, "postcode" => $shippingAddress->postal_code, "country" => $shippingAddress->country_code]);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => $redirectUrl]);
    }

    public function verifyPayment(\WHMCS\Http\Message\ServerRequest $request)
    {
        $requestBody = $this->decodeJsonBodyResponse((string) $request->getBody());
        check_token("WHMCS.default", $requestBody["token"]);
        $invoiceId = $requestBody["invoiceid"];
        $paypalOrderId = $requestBody["paypalorderid"];
        $paypalSubscriptionId = $requestBody["paypalsubid"];
        $invoice = \WHMCS\Billing\Invoice::find($invoiceId);
        if (!$invoice) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => routePath("clientarea-home")]);
        }
        $paypalApi = new PaypalApi();
        $response = $paypalApi->getOrderDetails($paypalOrderId);
        if ($response->status == "COMPLETED") {
            return new \WHMCS\Http\Message\JsonResponse(["success" => true, "reason" => "alreadyCompleted"]);
        }
        if ($response->status == "APPROVED" || $response->status == "CREATED") {
            if ($paypalSubscriptionId) {
                $paypalApi->getSubscriptionDetails($paypalSubscriptionId);
                $invoice->saveSubscriptionId($paypalSubscriptionId);
                $invoice->setStatusPending()->save();
                return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => "viewinvoice.php?id=" . $invoice->id . "&paymentinititated=true"]);
            }
            $captureResponse = $paypalApi->captureOrder($paypalOrderId);
            if ($captureResponse->status == "COMPLETED") {
                if (1 < count($captureResponse->purchase_units) || 1 < count($captureResponse->purchase_units[0]->payments->captures)) {
                    throw new Exception\PaymentIncomplete("Unexpected number of purchase units or captures: " . count($captureResponse->purchase_units));
                }
                $captureData = $captureResponse->purchase_units[0]->payments->captures[0];
                $currency = $captureData->amount->currency_code;
                if ($captureData->status == "COMPLETED") {
                    if ($currency != $invoice->getCurrency()["code"]) {
                        return new \WHMCS\Http\Message\JsonResponse(["success" => false, "reason" => "currencyMismatch"]);
                    }
                    $invoice->addPayment($captureData->amount->value, $captureData->id, $captureData->seller_receivable_breakdown->paypal_fee->value, "paypalcheckout");
                    return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => "viewinvoice.php?id=" . $invoice->id . "&paymentsuccess=true"]);
                }
                if ($captureData->status == "PENDING") {
                    return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => "viewinvoice.php?id=" . $invoice->id . "&paymentinititated=true"]);
                }
                return new \WHMCS\Http\Message\JsonResponse(["success" => false, "reason" => "captureStatusIncomplete"]);
            }
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "reason" => "statusIncomplete", "data" => $captureResponse]);
        }
        throw new \WHMCS\Exception("Order not in capturable state.");
    }

    public function verifySubscriptionSetup(\WHMCS\Http\Message\ServerRequest $request)
    {
        $invoiceId = $request->get("invoice_id");
        $paypalSubscriptionId = $request->get("subscription_id");
        $invoice = \WHMCS\Billing\Invoice::find($invoiceId);
        if (!$invoice || $invoice->userId != Auth::client()->id) {
            return new \Laminas\Diactoros\Response\RedirectResponse(routePath("clientarea-home"));
        }
        $response = (new PaypalApi())->getSubscriptionDetails($paypalSubscriptionId);
        if ($response->getFromResponse("status") !== "ACTIVE") {
            return new \Laminas\Diactoros\Response\RedirectResponse($invoice->getViewInvoiceUrl(["paymentfailed" => true]));
        }
        $invoice->saveSubscriptionId($paypalSubscriptionId);
        if ($invoice->status === \WHMCS\Billing\Invoice::STATUS_UNPAID) {
            $invoice->setStatusPending()->save();
        }
        return new \Laminas\Diactoros\Response\RedirectResponse($invoice->getViewInvoiceUrl(["paymentsuccess" => true]));
    }

    protected function decodeJsonBodyResponse($response)
    {
        $requestBody = json_decode((string) $response, true);
        if (!is_array($requestBody) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \WHMCS\Exception("Invalid request");
        }
        return $requestBody;
    }
}
