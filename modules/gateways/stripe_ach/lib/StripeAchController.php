<?php

namespace WHMCS\Module\Gateway\StripeAch;

class StripeAchController
{
    public function exchange(\WHMCS\Http\Message\ServerRequest $request)
    {
        $linkToken = $request->get("public_token");
        $accountId = $request->get("account_id");
        try {
            $gateway = \WHMCS\Module\Gateway::factory("stripe_ach");
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => \Lang::trans("errors.badRequest")]);
        }
        $gatewayParams = $gateway->getParams();
        $plaid = Plaid::factory($gatewayParams);
        $exchange = "item/public_token/exchange";
        $bankToken = "processor/stripe/bank_account_token/create";
        $client = $plaid->getHttpClient();
        $response = $client->post($exchange, ["headers" => ["Content-Type" => "application/json"], "json" => ["client_id" => $gatewayParams["plaidClientId"], "secret" => $gatewayParams["plaidSecret"], "public_token" => $linkToken]]);
        $statusCode = $response->getStatusCode();
        $data = json_decode($response->getBody());
        if ($statusCode < 400) {
            $accessToken = $data->access_token;
            $response = $client->post($bankToken, ["headers" => ["Content-Type" => "application/json"], "json" => ["client_id" => $plaid->getClientId(), "secret" => $plaid->getSecretKey(), "access_token" => $accessToken, "account_id" => $accountId]]);
            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody());
        }
        if ($statusCode < 400) {
            return new \WHMCS\Http\Message\JsonResponse(["token" => $data->stripe_bank_account_token]);
        }
        $error = ["Malformed response received from server. Please contact support."];
        if ($data !== NULL && 400 <= $statusCode) {
            $error = [];
            foreach ($data->fields as $field) {
                $error[] = $field->path . " " . $field->message;
            }
        }
        return new \WHMCS\Http\Message\JsonResponse(["warning" => implode("<br>", $error)]);
    }
}
