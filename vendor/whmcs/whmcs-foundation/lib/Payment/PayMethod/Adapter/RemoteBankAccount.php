<?php

namespace WHMCS\Payment\PayMethod\Adapter;

class RemoteBankAccount extends BankAccountModel implements \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface
{
    use \WHMCS\Payment\PayMethod\Traits\RemoteBankAccountDetailsTrait;

    public function getName()
    {
        return $this->bank_name;
    }

    public function setName($value)
    {
        $this->bank_name = $value;
        return $this;
    }

    public function getRemoteToken()
    {
        $remoteToken = $this->getSensitiveProperty("remoteToken");
        if (is_array($remoteToken)) {
            $remoteToken = json_encode($remoteToken);
        }
        if (!is_string($remoteToken)) {
            $remoteToken = (string) $remoteToken;
        }
        return $remoteToken;
    }

    public function setRemoteToken($value)
    {
        $this->setSensitiveProperty("remoteToken", $value);
        return $this;
    }

    public function getPaymentParamsForRemoteCall()
    {
        return ["bankaccountholder" => $this->getAccountHolderName(), "bankname" => $this->getName(), "banktype" => $this->getAccountType(), "bankcode" => $this->getRoutingNumber(), "bankacct" => $this->getAccountNumber()];
    }

    protected function getGatewayParamsForRemoteCall(\WHMCS\Module\Gateway $gateway)
    {
        $params = $gateway->loadSettings();
        if (!$params) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration("No Gateway Settings Found");
        }
        $params["companyname"] = \WHMCS\Config\Setting::getValue("CompanyName");
        $params["systemurl"] = \App::getSystemURL();
        $params["langpaynow"] = \Lang::trans("invoicespaynow");
        return $params;
    }

    public function getBillingContactParamsForRemoteCall(\WHMCS\User\Contracts\UserInterface $client, \WHMCS\User\Contracts\ContactInterface $contact = NULL)
    {
        $legacyClient = new \WHMCS\Client($client->id);
        $clientsDetails = $legacyClient->getDetails($contact ? $contact->id : "billing");
        $clientsDetails["state"] = $clientsDetails["statecode"];
        return ["clientdetails" => $clientsDetails];
    }

    protected function storeRemote($action)
    {
        $payMethod = $this->payMethod;
        $gateway = $payMethod->getGateway();
        if (!$gateway) {
            throw new \RuntimeException("No valid gateway for PayMethod ID " . $this->payMethod->id);
        }
        $gatewayParams = $this->getGatewayParamsForRemoteCall($gateway);
        $billingParams = $this->getBillingContactParamsForRemoteCall($payMethod->client, $payMethod->contact);
        $paymentParams = $this->getPaymentParamsForRemoteCall();
        $params = array_merge($gatewayParams, $billingParams, $paymentParams);
        $params["action"] = $action;
        $params["payMethod"] = $payMethod;
        $params["gatewayid"] = $this->getRemoteToken();
        $params["remoteStorageToken"] = $this->getRemoteToken();
        $gatewayCallResult = $gateway->call("storeremote", $params);
        if (is_array($gatewayCallResult["rawdata"] ?? NULL)) {
            $debugData = array_merge(["UserID" => $params["clientdetails"]["userid"]], $gatewayCallResult["rawdata"]);
        } else {
            $debugData = "UserID => " . $params["clientdetails"]["userid"] . "\n" . ($gatewayCallResult["rawdata"] ?? NULL);
        }
        if ($gatewayCallResult["status"] === "success") {
            if ($params["action"] === "delete") {
                logTransaction($gateway->getLoadedModule(), $debugData, "Remote Delete Success");
            } else {
                logTransaction($gateway->getLoadedModule(), $debugData, "Remote Storage Success");
            }
            if (is_array($gatewayCallResult) && array_key_exists("gatewayid", $gatewayCallResult) && !array_key_exists("remoteToken", $gatewayCallResult)) {
                $gatewayCallResult["remoteToken"] = $gatewayCallResult["gatewayid"];
            }
            if (isset($gatewayCallResult["remoteToken"])) {
                $this->setRemoteToken($gatewayCallResult["remoteToken"]);
                if (array_key_exists("accountNumber", $gatewayCallResult) && $gatewayCallResult["accountNumber"]) {
                    $this->setAccountNumber($gatewayCallResult["accountNumber"]);
                } else {
                    $this->setAccountNumber($this->getAccountNumber());
                }
                if (array_key_exists("bankName", $gatewayCallResult) && $gatewayCallResult["bankName"]) {
                    $this->setName($gatewayCallResult["bankName"]);
                }
                if (array_key_exists("routingNumber", $gatewayCallResult) && $gatewayCallResult["routingNumber"]) {
                    $this->setRoutingNumber($gatewayCallResult["routingNumber"]);
                }
                if (!$this->getAccountHolderName()) {
                    $this->setAccountHolderName($billingParams["clientdetails"]["fullname"]);
                }
            } else {
                if ($action === "create") {
                    logTransaction($gateway->getLoadedModule(), $debugData, "Remote Storage \"create\" action did NOT provide token");
                    throw new \RuntimeException("Remote Storage Failed");
                }
            }
            $this->save();
            return $this;
        }
        logTransaction($gateway->getLoadedModule(), $debugData, "Remote Storage Failed");
        if ($gatewayCallResult["status"] === "error" && !empty($gatewayCallResult["visible"])) {
            throw new \RuntimeException($gatewayCallResult["rawdata"]);
        }
        throw new \RuntimeException("Remote Storage Failed");
    }

    public function createRemote()
    {
        return $this->storeRemote("create");
    }

    public function updateRemote()
    {
        return $this->storeRemote("update");
    }

    public function deleteRemote()
    {
        return $this->storeRemote("delete");
    }

    public function validateRequiredValuesPreSave()
    {
        return $this;
    }
}
