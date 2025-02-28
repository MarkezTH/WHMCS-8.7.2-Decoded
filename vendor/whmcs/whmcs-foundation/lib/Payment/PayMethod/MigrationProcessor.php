<?php

namespace WHMCS\Payment\PayMethod;

class MigrationProcessor
{
    private function getEncryptedDataFields()
    {
        return ["cardtype", "cardlastfour", "cardnum", "startdate", "expdate", "issuenumber", "bankcode", "bankacct"];
    }

    private function getLegacyClientPaymentData(\WHMCS\User\Client $client)
    {
        $ccHash = md5(\DI::make("config")->cc_encryption_hash . $client->id);
        $columns = array_map(function ($fieldName) use($ccHash) {
            return \WHMCS\Database\Capsule::connection()->raw(sprintf("AES_DECRYPT(`%s`, '%s') as `%s`", $fieldName, $ccHash, $fieldName));
        }, $this->getEncryptedDataFields());
        $columns = array_merge($columns, ["bankname", "banktype", "cardtype as cardtyperaw", "cardlastfour as cardlastfourraw"]);
        $legacyPaymentData = (array) \WHMCS\Database\Capsule::table("tblclients")->where("id", $client->id)->select($columns)->first();
        if (empty($legacyPaymentData["cardtype"]) && !empty($legacyPaymentData["cardtyperaw"])) {
            $legacyPaymentData["cardtype"] = $legacyPaymentData["cardtyperaw"];
        }
        if (empty($legacyPaymentData["cardlastfour"]) && !empty($legacyPaymentData["cardlastfourraw"])) {
            $legacyPaymentData["cardlastfour"] = $legacyPaymentData["cardlastfourraw"];
        }
        unset($legacyPaymentData["cardtyperaw"]);
        unset($legacyPaymentData["cardlastfourraw"]);
        return $legacyPaymentData;
    }

    private function getBillingContact(\WHMCS\User\Client $client)
    {
        if ($client->billingContact) {
            return $client->billingContact;
        }
        return $client;
    }

    private function migrateLocalCreditCardDetails(\WHMCS\User\Client $client, $paymentData)
    {
        $payMethod = Adapter\CreditCard::factoryPayMethod($client, $this->getBillingContact($client), "");
        $payment = $payMethod->payment;
        $payment->setCardNumber($paymentData["cardnum"]);
        if ($paymentData["cardtype"]) {
            $payment->setCardType($paymentData["cardtype"]);
        }
        if ($paymentData["startdate"]) {
            $payment->setStartDate(\WHMCS\Carbon::createFromCcInput($paymentData["startdate"]));
        }
        if ($paymentData["expdate"]) {
            $payment->setExpiryDate(\WHMCS\Carbon::createFromCcInput($paymentData["expdate"]));
        }
        if ($paymentData["issuenumber"]) {
            $payment->setIssueNumber($paymentData["issuenumber"]);
        }
        $payment->setMigrated()->validateRequiredValuesPreSave()->save();
    }

    private function findGatewayForClient(\WHMCS\User\Client $client, $callback)
    {
        $gatewayInterface = new \WHMCS\Module\Gateway();
        $activeCcGateways = \WHMCS\Module\GatewaySetting::gatewayType(\WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD)->pluck("gateway")->all();
        $tokenisedPaymentInvoices = \WHMCS\Database\Capsule::table("tblinvoices")->where("tblinvoices.userid", $client->id)->where("tblinvoices.status", \WHMCS\Billing\Invoice::STATUS_PAID)->whereIn("paymentmethod", $activeCcGateways)->orderBy("id", "DESC")->distinct()->pluck("paymentmethod")->all();
        $gateways = array_unique(array_merge($tokenisedPaymentInvoices, $activeCcGateways));
        if ($client->defaultPaymentGateway && $gatewayInterface->isActiveGateway($client->defaultPaymentGateway) && !in_array($client->defaultPaymentGateway, $gateways)) {
            $gateways[] = $client->defaultPaymentGateway;
        }
        foreach ($gateways as $gatewayName) {
            if ($gatewayInterface->load($gatewayName) && $callback($gatewayInterface)) {
                return $gatewayInterface;
            }
        }
        return NULL;
    }

    private function migrateRemoteCreditCardDetails(\WHMCS\User\Client $client, $paymentData)
    {
        $remoteCreditCardGateway = $this->findGatewayForClient($client, function (\WHMCS\Module\Gateway $gateway) {
            return $gateway->isTokenised() && !$gateway->functionExists("no_cc");
        });
        if (!$remoteCreditCardGateway) {
            throw new \Exception("Client's remote credit card gateway could not be determined. Client ID: " . $client->id);
        }
        $payMethod = Adapter\RemoteCreditCard::factoryPayMethod($client, $this->getBillingContact($client), "");
        $payMethod->setGateway($remoteCreditCardGateway)->save();
        $payment = $payMethod->payment;
        $payment->setRemoteToken($client->paymentGatewayToken);
        if ($paymentData["cardlastfour"]) {
            $payment->setLastFour($paymentData["cardlastfour"]);
        }
        if ($paymentData["cardtype"]) {
            $payment->setCardType($paymentData["cardtype"]);
        } else {
            $payment->setCardType("Card");
        }
        if ($paymentData["startdate"]) {
            $payment->setStartDate(\WHMCS\Carbon::createFromCcInput($paymentData["startdate"]));
        }
        if ($paymentData["expdate"]) {
            $payment->setExpiryDate(\WHMCS\Carbon::createFromCcInput($paymentData["expdate"]));
        }
        if ($paymentData["issuenumber"]) {
            $payment->setIssueNumber($paymentData["issuenumber"]);
        }
        $payment->setMigrated()->validateRequiredValuesPreSave()->save();
    }

    private function migrateBankDetails(\WHMCS\User\Client $client, $paymentData)
    {
        $payMethod = Adapter\BankAccount::factoryPayMethod($client, $this->getBillingContact($client), "Default Bank Account");
        $payment = $payMethod->payment;
        $payment->setMigrated()->setAccountType($paymentData["banktype"])->setAccountHolderName($client->firstName . " " . $client->lastName)->setBankName($paymentData["bankname"])->setRoutingNumber($paymentData["bankcode"])->setAccountNumber($paymentData["bankacct"])->validateRequiredValuesPreSave()->save();
    }

    private function migrateNonCardPaymentToken(\WHMCS\User\Client $client)
    {
        $remoteNonCardGateway = $this->findGatewayForClient($client, function (\WHMCS\Module\Gateway $gateway) {
            return $gateway->getWorkflowType() === \WHMCS\Module\Gateway::WORKFLOW_NOLOCALCARDINPUT;
        });
        if (!$remoteNonCardGateway) {
            throw new \Exception("Client's remote non-card gateway could not be determined. Client ID: " . $client->id);
        }
        $payMethod = Adapter\RemoteBankAccount::factoryPayMethod($client, $this->getBillingContact($client), $remoteNonCardGateway->getDisplayName());
        $payMethod->setGateway($remoteNonCardGateway)->save();
        $payment = $payMethod->payment;
        $payment->setMigrated()->setRemoteToken($client->paymentGatewayToken)->setName($remoteNonCardGateway->getDisplayName())->validateRequiredValuesPreSave()->save();
    }

    private function migrateFromHook(\WHMCS\User\Client $client, $paymentData, $moduleName)
    {
        $gatewayInterface = new \WHMCS\Module\Gateway();
        if (!$gatewayInterface->load($moduleName)) {
            throw new \Exception("Unrecognised gateway module name `" . $moduleName . "`");
        }
        $gatewayInterface->getWorkflowType();
        switch ($gatewayInterface->getWorkflowType()) {
            case \WHMCS\Module\Gateway::WORKFLOW_MERCHANT:
                $this->migrateLocalCreditCardDetails($client, $paymentData);
                break;
            case \WHMCS\Module\Gateway::WORKFLOW_ASSISTED:
            case \WHMCS\Module\Gateway::WORKFLOW_REMOTE:
            case \WHMCS\Module\Gateway::WORKFLOW_TOKEN:
                $this->migrateRemoteCreditCardDetails($client, $paymentData);
                $client->markPaymentTokenMigrated();
                break;
            case \WHMCS\Module\Gateway::WORKFLOW_NOLOCALCARDINPUT:
                if ($client->needsCardDetailsMigrated()) {
                    $this->migrateRemoteCreditCardDetails($client, $paymentData);
                } else {
                    $this->migrateNonCardPaymentToken($client);
                }
                $client->markPaymentTokenMigrated();
                $client->markCardDetailsAsMigrated();
                $client->markBankDetailsAsMigrated();
                return true;
                break;
            case \WHMCS\Module\Gateway::WORKFLOW_THIRDPARTY:
            default:
                throw new \Exception("Invalid gateway module name `" . $moduleName . "`");
        }
    }

    private function finalStepMigrationForAppropriateGateways(\WHMCS\User\Client $client)
    {
        $gatewayInterface = new \WHMCS\Module\Gateway();
        $activeGateways = \WHMCS\Module\GatewaySetting::gatewayType(\WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD)->pluck("gateway")->all();
        $remoteBank = "";
        $remoteCC = "";
        foreach ($activeGateways as $gatewayName) {
            if ($remoteBank && $remoteCC) {
                $invoice = $client->invoices()->where("status", "Paid")->orderBy("datepaid", "desc")->first();
                if (!empty($invoice)) {
                    if ($invoice->paymentGateway === $remoteBank) {
                        $remoteCC = "";
                    } else {
                        if ($invoice->paymentGateway === $remoteCC) {
                            $remoteBank = "";
                        }
                    }
                } else {
                    $gateway = $client->defaultPaymentGateway;
                    if ($gateway === $remoteBank) {
                        $remoteCC = "";
                    } else {
                        if ($gateway === $remoteCC) {
                            $remoteBank = "";
                        }
                    }
                }
                if ($remoteBank) {
                    $gatewayInterface = new \WHMCS\Module\Gateway();
                    $gatewayInterface->load($remoteBank);
                    $payMethod = Adapter\RemoteBankAccount::factoryPayMethod($client, $this->getBillingContact($client), $gatewayInterface->getDisplayName());
                    $payMethod->setGateway($gatewayInterface)->save();
                    $payment = $payMethod->payment;
                    $payment->setMigrated()->setRemoteToken($client->paymentGatewayToken)->setName($gatewayInterface->getDisplayName())->validateRequiredValuesPreSave()->save();
                }
                if ($remoteCC) {
                    $payMethod = Adapter\RemoteCreditCard::factoryPayMethod($client, $this->getBillingContact($client), "");
                    $gatewayInterface = new \WHMCS\Module\Gateway();
                    $gatewayInterface->load($remoteCC);
                    $payMethod->setGateway($gatewayInterface)->save();
                    $payment = $payMethod->payment;
                    $payment->setRemoteToken($client->paymentGatewayToken)->setLastFour("XXXX")->setCardType("Card")->setExpiryDate(\WHMCS\Carbon::today()->endOfDay()->addYears(10)->endOfYear())->setMigrated()->validateRequiredValuesPreSave()->save();
                }
            } else {
                if ($gatewayInterface->load($gatewayName)) {
                    $gatewayInterface->getWorkflowType();
                    switch ($gatewayInterface->getWorkflowType()) {
                        case \WHMCS\Module\Gateway::WORKFLOW_ASSISTED:
                        case \WHMCS\Module\Gateway::WORKFLOW_REMOTE:
                        case \WHMCS\Module\Gateway::WORKFLOW_TOKEN:
                            if (!$remoteCC) {
                                $remoteCC = $gatewayName;
                            }
                            break;
                        case \WHMCS\Module\Gateway::WORKFLOW_NOLOCALCARDINPUT:
                            if (!$remoteBank) {
                                $remoteBank = $gatewayName;
                            }
                            break;
                    }
                }
            }
        }
    }

    public function migrateForClient(\WHMCS\User\Client $client)
    {
        $legacyPaymentData = $this->getLegacyClientPaymentData($client);
        $clientBeforeHook = $client;
        $hookResponses = run_hook("PayMethodMigration", ["client" => $client]);
        $client = $clientBeforeHook;
        foreach ($hookResponses as $hookResponse) {
            if ($hookResponse && is_string($hookResponse)) {
                try {
                    $this->migrateFromHook($client, $legacyPaymentData, $hookResponse);
                    return NULL;
                } catch (\Exception $e) {
                    logActivity("Pay Method Migration Hook Response Failure: " . $e->getMessage() . " - Client ID: " . $client->id, $client->id);
                }
            }
        }
        if ($client->needsCardDetailsMigrated()) {
            if ($legacyPaymentData["cardnum"] && preg_match("/^[\\d]+\$/", $legacyPaymentData["cardnum"])) {
                $this->migrateLocalCreditCardDetails($client, $legacyPaymentData);
            } else {
                try {
                    $this->migrateRemoteCreditCardDetails($client, $legacyPaymentData);
                    $client->markPaymentTokenMigrated();
                } catch (\Exception $e) {
                }
            }
            $client->markCardDetailsAsMigrated();
        }
        if ($client->needsBankDetailsMigrated()) {
            $this->migrateBankDetails($client, $legacyPaymentData);
            $client->markBankDetailsAsMigrated();
        }
        if ($client->needsUnknownPaymentTokenMigrated()) {
            $this->finalStepMigrationForAppropriateGateways($client);
            $client->markPaymentTokenMigrated();
        }
    }
}
