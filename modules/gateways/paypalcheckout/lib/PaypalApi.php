<?php

namespace WHMCS\Module\Gateway\Paypalcheckout;

class PaypalApi
{
    public function getAccessToken($clientId, $clientSecret, $sandbox)
    {
        $endpoint = "v1/oauth2/token";
        $options = ["CURLOPT_HTTPHEADER" => ["Accept: application/json", "Accept-Language: en_US"], "CURLOPT_USERPWD" => $clientId . ":" . $clientSecret];
        $data = ["grant_type" => "client_credentials"];
        return (new ApiClient())->setSandbox($sandbox)->setOptions($options)->post($endpoint, $data)->getFromResponse("access_token");
    }

    public function createOrder($amount, $currency, $intent, $companyName, \WHMCS\User\Client $client = NULL, \WHMCS\Billing\Invoice $invoice = NULL)
    {
        $endpoint = "v2/checkout/orders";
        if ($invoice) {
            $description = $companyName . " - Invoice #" . $invoice->getInvoiceNumber();
            $invoiceId = $invoice->id;
        } else {
            $description = $companyName . " Shopping Cart Checkout";
            $invoiceId = 0;
        }
        $data = ["intent" => $intent, "purchase_units" => [["description" => $description, "amount" => ["currency_code" => $currency, "value" => $amount]]]];
        if (0 < $invoiceId) {
            $data["purchase_units"][0]["invoice_id"] = $invoiceId;
        }
        if ($client instanceof \WHMCS\User\Client) {
            $data["payer"] = ["name" => ["given_name" => $client->firstName, "surname" => $client->lastName], "email_address" => $client->email, "address" => ["address_line_1" => $client->address1, "address_line_2" => $client->address2, "admin_area_1" => $client->state, "admin_area_2" => $client->city, "postal_code" => $client->postcode, "country_code" => $client->country]];
        }
        $response = $this->call("post", $endpoint, json_encode($data), true);
        return $response->getFromResponse("id");
    }

    public function captureOrder($orderId)
    {
        $endpoint = "v2/checkout/orders/" . $orderId . "/capture";
        $response = $this->call("post", $endpoint, NULL, true);
        return $response->getResponse();
    }

    public function activateSubscription($subscriptionId)
    {
        $endpoint = "v1/billing/subscriptions/" . $subscriptionId . "/activate";
        $response = $this->call("post", $endpoint);
        return $response->getResponse();
    }

    public function createProduct($name, $description)
    {
        $endpoint = "v1/catalogs/products";
        $data = ["name" => $name, "description" => $description, "type" => "Service"];
        $response = $this->call("post", $endpoint, json_encode($data));
        return $response->getFromResponse("id");
    }

    public function createProductPlan($productId, $productName, $productDescription, $totalDueToday, $recurringAmount, $billingCycle, $billingCyclePeriod, $currencyCode, $initialCycle = NULL, $initialPeriod = NULL)
    {
        $endpoint = "v1/billing/plans";
        $billingCycles = [];
        $sequence = 1;
        if ($totalDueToday != $recurringAmount) {
            if (!is_null($initialCycle) && !is_null($initialPeriod)) {
                $paypalInitialCycle = $this->getPaypalCycle($initialCycle);
            } else {
                $paypalInitialCycle = $this->getPaypalCycle($billingCycle);
                $initialPeriod = $billingCyclePeriod;
            }
            $billingCycles[] = ["frequency" => ["interval_unit" => $paypalInitialCycle, "interval_count" => $initialPeriod], "tenure_type" => "TRIAL", "sequence" => $sequence, "total_cycles" => 1, "pricing_scheme" => ["fixed_price" => ["value" => $totalDueToday, "currency_code" => $currencyCode]]];
            $sequence++;
        }
        $billingCycles[] = ["frequency" => ["interval_unit" => $this->getPaypalCycle($billingCycle), "interval_count" => $billingCyclePeriod], "tenure_type" => "REGULAR", "sequence" => $sequence, "total_cycles" => 0, "pricing_scheme" => ["fixed_price" => ["value" => $recurringAmount, "currency_code" => $currencyCode]]];
        $data = ["product_id" => $productId, "name" => $productName, "description" => $productDescription, "status" => "ACTIVE", "billing_cycles" => $billingCycles, "payment_preferences" => ["auto_bill_outstanding" => true, "payment_failure_threshold" => "3"]];
        $response = $this->call("post", $endpoint, json_encode($data));
        return $response->getFromResponse("id");
    }

    protected function getPaypalCycle($cycle)
    {
        if ($cycle == "days") {
            return "DAY";
        }
        if ($cycle == "monthly") {
            return "MONTH";
        }
        if ($cycle == "annually") {
            return "YEAR";
        }
        return NULL;
    }

    public function createSubscription(int $invoiceId, $planId, \WHMCS\User\Client $client, $companyName, $returnUrl, $cancelUrl)
    {
        $endpoint = "v1/billing/subscriptions";
        $data = ["plan_id" => $planId, "custom_id" => $invoiceId, "quantity" => "1", "subscriber" => ["name" => ["given_name" => $client->firstName, "surname" => $client->lastName], "email_address" => $client->email], "application_context" => ["brand_name" => $companyName, "shipping_preference" => "NO_SHIPPING", "user_action" => "SUBSCRIBE_NOW", "payment_method" => ["payer_selected" => "PAYPAL", "payee_preferred" => "IMMEDIATE_PAYMENT_REQUIRED"], "return_url" => $returnUrl, "cancel_url" => $cancelUrl]];
        return $this->call("post", $endpoint, json_encode($data), true);
    }

    public function getOrderDetails($orderId)
    {
        $endpoint = "v2/checkout/orders/" . $orderId;
        $response = $this->call("get", $endpoint);
        return $response->getResponse();
    }

    public function getSubscriptionDetails($subscriptionId)
    {
        $endpoint = "v1/billing/subscriptions/" . $subscriptionId;
        return $this->call("get", $endpoint);
    }

    public function refundPayment($invoiceId, $paymentId, $amount, $currencyCode)
    {
        $endpoint = "v2/payments/captures/" . $paymentId . "/refund";
        $data = ["amount" => ["value" => $amount, "currency_code" => $currencyCode], "invoice_id" => $invoiceId];
        return $this->call("post", $endpoint, json_encode($data), true);
    }

    public function getRefundData($refundId)
    {
        $endpoint = "v2/payments/refunds/" . $refundId . "";
        return $this->call("get", $endpoint);
    }

    public function cancelSubscription($subscriptionId)
    {
        $endpoint = "v1/billing/subscriptions/" . $subscriptionId . "/cancel";
        return $this->call("post", $endpoint);
    }

    public function getCaptureDetails($captureId)
    {
        $endpoint = "v2/payments/captures/" . $captureId;
        $response = $this->call("get", $endpoint);
        return $response->getResponse();
    }

    public function authorizeOrder($orderId)
    {
        $endpoint = "v2/checkout/orders/" . $orderId . "/authorize";
        $response = $this->call("post", $endpoint);
        $data = $response->getResponse();
        return $data->purchase_units[0]->payments->authorizations[0]->id;
    }

    public function capturePayment($authId, $amount, $currency, $invoiceNumber)
    {
        $endpoint = "v2/payments/authorizations/" . $authId . "/capture";
        $data = ["amount" => ["value" => $amount, "currency_code" => $currency], "invoice_id" => $invoiceNumber, "final_capture" => true];
        $response = $this->call("post", $endpoint, json_encode($data), true);
        return $response->getResponse();
    }

    public function createWebhook($url, $eventTypes)
    {
        $endpoint = "v1/notifications/webhooks";
        $data = ["url" => $url, "event_types" => []];
        foreach ($eventTypes as $eventType) {
            $data["event_types"][] = ["name" => $eventType];
        }
        $response = $this->call("post", $endpoint, json_encode($data));
        return $response->getFromResponse("id");
    }

    public function listWebhooks()
    {
        $endpoint = "v1/notifications/webhooks";
        $response = $this->call("get", $endpoint);
        return $response->getResponse();
    }

    public function listDisputes($nextPageToken = NULL)
    {
        $endpoint = "v1/customer/disputes/?page_size=50";
        if (!is_null($nextPageToken)) {
            $endpoint .= "&next_page_token=" . $nextPageToken;
        }
        $response = $this->call("get", $endpoint);
        return $response->getResponse();
    }

    public function getDisputeDetails($disputeId)
    {
        $endpoint = "v1/customer/disputes/" . $disputeId;
        $response = $this->call("get", $endpoint);
        return $response->getResponse();
    }

    public function submitEvidence($disputeId, $evidenceData, $file = NULL)
    {
        $endpoint = "/v1/customer/disputes/" . $disputeId . "/provide-evidence";
        $options = ["MULTIPART" => [["name" => "input", "contents" => json_encode($evidenceData), "headers" => ["Content-Type" => "application/json"]]]];
        if ($file && $file["tmp_name"]) {
            $options["MULTIPART"][] = ["name" => $file["name"], "contents" => fopen($file["tmp_name"], "r"), "filename" => $file["name"], "headers" => ["Content-Type" => $file["type"]]];
        }
        $response = $this->call("POST", $endpoint, json_encode($evidenceData), false, $options);
        return $response->getResponse();
    }

    public function acceptClaim($disputeId)
    {
        $endpoint = "v1/customer/disputes/" . $disputeId . "/accept-claim";
        $data = ["note" => "Claim accepted via WHMCS.", "accept_claim_type" => "REFUND"];
        $response = $this->call("post", $endpoint, json_encode($data));
        return $response->getResponse();
    }

    public function verifyWebhookSignature($authAlgo, $certUrl, $transmissionId, $transmissionSig, $transmissionTime, $webhookId, $webhookEvent)
    {
        $endpoint = "v1/notifications/verify-webhook-signature";
        $data = ["auth_algo" => $authAlgo, "cert_url" => $certUrl, "transmission_id" => $transmissionId, "transmission_sig" => $transmissionSig, "transmission_time" => $transmissionTime, "webhook_id" => $webhookId, "webhook_event" => $webhookEvent];
        $response = $this->call("post", $endpoint, json_encode($data));
        return $response->getFromResponse("verification_status") === "SUCCESS";
    }

    protected function call($method, $endpoint, $data = NULL, $sendPartnerId = false, $additionalOptions = [])
    {
        $settings = \WHMCS\Module\GatewaySetting::getForGateway("paypalcheckout");
        $accessTokenName = !$settings["sandbox"] ? "accessToken-production-" . md5($settings["clientId"]) : "accessToken-sandbox-" . md5($settings["sandboxClientId"]);
        $accessToken = isset($settings[$accessTokenName]) ? decrypt($settings[$accessTokenName]) : NULL;
        $clientId = $settings["sandbox"] ? $settings["sandboxClientId"] : $settings["clientId"];
        $clientSecret = $settings["sandbox"] ? $settings["sandboxClientSecret"] : $settings["clientSecret"];
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken($clientId, $clientSecret, $settings["sandbox"]);
            \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", $accessTokenName, encrypt($accessToken));
        }
        try {
            $apiClient = new ApiClient();
            return $apiClient->setSandbox($settings["sandbox"])->setAccessToken($accessToken)->setSendPartnerId($sendPartnerId)->setOptions(array_merge($apiClient->getOptions(), $additionalOptions))->{$method}($endpoint, $data);
        } catch (Exception\AuthError $e) {
            $accessToken = $this->getAccessToken($clientId, $clientSecret, $settings["sandbox"]);
            \WHMCS\Module\GatewaySetting::setValue("paypalcheckout", $accessTokenName, encrypt($accessToken));
            return (new ApiClient())->setSandbox($settings["sandbox"])->setAccessToken($accessToken)->setSendPartnerId($sendPartnerId)->{$method}($endpoint, $data);
        }
    }
}
