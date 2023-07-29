<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$clientId = App::getFromRequest("clientid");
$payMethodId = App::getFromRequest("paymethodid");
$type = strtolower(App::getFromRequest("type"));
if (!$clientId) {
    $apiresults = ["result" => "error", "message" => "Client ID Is Required"];
} else {
    if ($type && !in_array($type, [strtolower(WHMCS\Payment\PayMethod\Model::TYPE_BANK_ACCOUNT), strtolower(WHMCS\Payment\PayMethod\Model::TYPE_CREDITCARD_LOCAL)])) {
        $apiresults = ["result" => "error", "message" => "Invalid Pay Method Type. Should be 'BankAccount' or 'CreditCard'"];
    } else {
        try {
            $client = WHMCS\User\Client::with("payMethods")->findOrFail($clientId);
            if ($payMethodId) {
                $payMethods = $client->payMethods()->where("id", $payMethodId)->get();
            } else {
                if ($type) {
                    $types = [WHMCS\Payment\PayMethod\Model::TYPE_CREDITCARD_LOCAL, WHMCS\Payment\PayMethod\Model::TYPE_CREDITCARD_REMOTE_MANAGED, WHMCS\Payment\PayMethod\Model::TYPE_CREDITCARD_REMOTE_UNMANAGED];
                    if ($type == strtolower(WHMCS\Payment\PayMethod\Model::TYPE_BANK_ACCOUNT)) {
                        $types = [WHMCS\Payment\PayMethod\Model::TYPE_BANK_ACCOUNT, WHMCS\Payment\PayMethod\Model::TYPE_REMOTE_BANK_ACCOUNT];
                    }
                    $payMethods = $client->payMethods()->whereIn("payment_type", $types)->get();
                } else {
                    $payMethods = $client->payMethods;
                }
            }
            $payMethodResponse = [];
            foreach ($payMethods as $payMethod) {
                $payment = $payMethod->payment;
                if (!$payment->getSensitiveData()) {
                    $payMethod->delete();
                } else {
                    $response = ["id" => $payMethod->id, "type" => $payMethod->payment_type, "description" => $payMethod->description, "gateway_name" => $payMethod->gateway_name, "contact_type" => $payMethod->contact_type, "contact_id" => $payMethod->contact_id];
                    if ($payment instanceof WHMCS\Payment\PayMethod\Adapter\CreditCardModel) {
                        $remoteToken = "";
                        if ($payment->isRemoteCreditCard()) {
                            $remoteToken = $payment->getRemoteToken();
                        }
                        $startDate = "";
                        if ($payment->getStartDate()) {
                            $startDate = $payment->getStartDate()->toCreditCard();
                        }
                        $expiryDate = "";
                        if ($payment->getExpiryDate()) {
                            $expiryDate = $payment->getExpiryDate()->toCreditCard();
                        }
                        $response = array_merge($response, ["card_last_four" => $payment->getLastFour(), "expiry_date" => $expiryDate, "start_date" => $startDate, "issue_number" => $payment->getIssueNumber(), "card_type" => $payment->getCardType(), "remote_token" => $remoteToken]);
                    } else {
                        $remoteToken = "";
                        $bankName = "";
                        if ($payment instanceof WHMCS\Payment\Contracts\BankAccountDetailsInterface) {
                            $bankName = $payment->getBankName();
                        } else {
                            if ($payment instanceof WHMCS\Payment\PayMethod\Adapter\RemoteBankAccount) {
                                $bankName = $payment->getName();
                                $remoteToken = $payment->getRemoteToken();
                            }
                        }
                        $response = array_merge($response, ["bank_name" => $bankName, "remote_token" => $remoteToken]);
                    }
                    $response["last_updated"] = $payMethod->updated_at->toAdminDateTimeFormat();
                    $payMethodResponse[] = $response;
                }
            }
            $apiresults = ["result" => "success", "clientid" => $clientId, "paymethods" => $payMethodResponse];
        } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $apiresults = ["result" => "error", "message" => "Client Not Found"];
            return NULL;
        } catch (Exception $e) {
            $apiresults = ["result" => "error", "message" => $e->getMessage()];
            return NULL;
        }
    }
}
