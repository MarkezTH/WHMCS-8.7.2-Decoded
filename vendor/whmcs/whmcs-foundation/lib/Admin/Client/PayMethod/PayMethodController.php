<?php

namespace WHMCS\Admin\Client\PayMethod;

class PayMethodController
{
    public function selectStorageOptions(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = (int) $request->getAttribute("userId");
        $client = \WHMCS\User\Client::find($clientId);
        $payMethodType = $request->getAttribute("payMethodType");
        $storageOptions = [];
        $resolver = new \WHMCS\Gateways();
        $gatewayModules = $resolver->getAvailableGatewayInstances();
        foreach ($gatewayModules as $name => $module) {
            $workflow = $module->getWorkflowType();
            if ($payMethodType === "card") {
                if ($module->getMetaDataValue("gatewayType") != \WHMCS\Module\Gateway::GATEWAY_BANK) {
                    if (in_array($workflow, \WHMCS\Module\Gateway::NONLOCAL_WORKFLOW_TYPES)) {
                        $storageOptions[] = ["id" => $name, "value" => $module->getDisplayName()];
                        if ($resolver->isLocalCreditCardStorageEnabled(false)) {
                            $storageOptions[] = ["id" => "local", "value" => \AdminLang::trans("payments.localEncryption")];
                        }
                    }
                }
            } else {
                if ($payMethodType === "bank_account") {
                    if ($module->getMetaDataValue("gatewayType") === \WHMCS\Module\Gateway::GATEWAY_BANK) {
                        if (in_array($workflow, \WHMCS\Module\Gateway::NONLOCAL_WORKFLOW_TYPES)) {
                            $storageOptions[] = ["id" => $name, "value" => $module->getDisplayName()];
                            if ($resolver->isLocalBankAccountGatewayAvailable()) {
                                $storageOptions[] = ["id" => "local", "value" => \AdminLang::trans("payments.localEncryption")];
                            }
                        }
                    }
                }
            }
        }
        $actionUrl = routePath("admin-client-paymethods-new", $client->id, $payMethodType);
        $body = view("admin.client.paymethods.select-card-storage", ["client" => $client, "actionUrl" => $actionUrl, "storageOptions" => $storageOptions]);
        $body = (new \WHMCS\Admin\ApplicationSupport\View\PreRenderProcessor())->process($body);
        $response = new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
        return $response;
    }

    public function newPayMethodForm(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $clientId = (int) $request->getAttribute("userId");
            $payMethodType = $request->getAttribute("payMethodType");
            $client = \WHMCS\User\Client::find($clientId);
            if (!$client) {
                throw new \RuntimeException("Missing client data");
            }
            $desiredStorage = $request->getAttribute("desiredStorage");
            $storageOptions = [];
            $allowLocalStorage = false;
            $storageGateway = NULL;
            $gatewayInputControl = "";
            $remoteInput = "";
            if ($payMethodType === "card" || $payMethodType === "bank_account") {
                if (!$desiredStorage) {
                    return $this->selectStorageOptions($request);
                }
                $resolver = new \WHMCS\Gateways();
                $gatewayModules = $resolver->getAvailableGatewayInstances();
                if ($payMethodType === "card") {
                    foreach ($gatewayModules as $name => $module) {
                        $workflow = $module->getWorkflowType();
                        if (in_array($workflow, \WHMCS\Module\Gateway::NONLOCAL_WORKFLOW_TYPES)) {
                            if ($workflow != \WHMCS\Module\Gateway::WORKFLOW_TOKEN || $module->functionExists("storeremote")) {
                                $storageOptions[$name] = $module;
                            }
                        }
                    }
                    $type = \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_LOCAL;
                    if ($desiredStorage !== "local") {
                        if (!array_key_exists($desiredStorage, $storageOptions)) {
                            throw new \WHMCS\Exception("Invalid storage option");
                        }
                        $type = \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_REMOTE_MANAGED;
                        $storageGateway = $storageOptions[$desiredStorage];
                        if ($storageGateway->functionExists("credit_card_input")) {
                            $gatewayInputControl = $storageGateway->call("credit_card_input");
                        } else {
                            if ($storageGateway->functionExists("remoteinput")) {
                                $params = [];
                                $params["clientdetails"] = (new \WHMCS\Client($client))->getDetails("billing");
                                $remoteInput = $storageGateway->call("remoteinput", $params);
                            }
                        }
                    }
                    $allowLocalStorage = $resolver->isLocalCreditCardStorageEnabled(false);
                } else {
                    if ($payMethodType === "bank_account") {
                        foreach ($gatewayModules as $name => $module) {
                            if ($module->getMetaDataValue("gatewayType") == \WHMCS\Module\Gateway::GATEWAY_BANK) {
                                $workflow = $module->getWorkflowType();
                                if (in_array($workflow, \WHMCS\Module\Gateway::NONLOCAL_WORKFLOW_TYPES)) {
                                    if ($workflow != \WHMCS\Module\Gateway::WORKFLOW_TOKEN || $module->functionExists("storeremote")) {
                                        $storageOptions[$name] = $module;
                                    }
                                }
                            }
                        }
                        $type = \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_BANK_ACCOUNT;
                        if ($desiredStorage !== "local") {
                            if (!array_key_exists($desiredStorage, $storageOptions)) {
                                throw new \WHMCS\Exception("Invalid storage option");
                            }
                            $type = \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_REMOTE_BANK_ACCOUNT;
                            $storageGateway = $storageOptions[$desiredStorage];
                            if ($storageGateway->functionExists("remoteinput")) {
                                $params = [];
                                $params["clientdetails"] = (new \WHMCS\Client($client))->getDetails("billing");
                                $remoteInput = $storageGateway->call("remoteinput", $params);
                            }
                        }
                        $allowLocalStorage = $resolver->isLocalBankAccountGatewayAvailable();
                    }
                }
                if ($allowLocalStorage) {
                    $storageOptions["local"] = \AdminLang::trans("payments.localEncryption");
                }
                $actionUrl = routePath("admin-client-paymethods-save", $client->id);
                $gatewaysHelper = new \WHMCS\Gateways();
                $enableStartDateIssueNumber = $gatewaysHelper->isIssueDateAndStartNumberEnabled();
                if (\WHMCS\Session::exists("remoteStorageToken")) {
                    \WHMCS\Session::delete("remoteStorageToken");
                }
                $body = view("admin.client.paymethods.details", ["client" => $client, "actionUrl" => $actionUrl, "storageOptions" => $storageOptions, "gatewayInputControl" => $gatewayInputControl, "remoteInput" => $remoteInput, "remoteUpdate" => "", "payMethodType" => $type, "storageGateway" => $storageGateway ? $storageGateway->getLoadedModule() : "", "startDateEnabled" => $enableStartDateIssueNumber, "issueNumberEnabled" => $enableStartDateIssueNumber, "forceDefault" => $client->payMethods->count() === 0, "supportedCardTypes" => \WHMCS\Gateways::getSupportedCardTypesForJQueryPayment()]);
                $body = (new \WHMCS\Admin\ApplicationSupport\View\PreRenderProcessor())->process($body);
                $response = new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
                return $response;
            } else {
                throw new \WHMCS\Exception("Invalid paymethod type");
            }
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["error" => true, "errorMsg" => $e->getMessage()]);
        }
    }

    public function saveNew(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $payment = \WHMCS\Payment\PayMethod\Model::factoryFromRequest($request);
            $payMethod = $payment->payMethod;
            if ($payMethod->isCreditCard()) {
                if ($payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                    $gateway = $payMethod->getGateway();
                    $params = $gateway->loadSettings();
                    $params["companyname"] = \WHMCS\Config\Setting::getValue("CompanyName");
                    $params["systemurl"] = \App::getSystemURL();
                    $params["payMethod"] = $payMethod;
                    $params["action"] = "create";
                    $params["remoteStorageToken"] = $request->get("remoteStorageToken", "");
                    if (!empty($params["convertto"])) {
                        $currencyCode = \WHMCS\Database\Capsule::table("tblcurrencies")->where("id", (int) $params["convertto"])->value("code");
                        $params["currency"] = $currencyCode;
                    }
                    if (empty($params["currency"])) {
                        $clientCurrency = $payMethod->client->currencyrel->code;
                        $params["currency"] = $clientCurrency;
                    }
                    if ($payment instanceof \WHMCS\Payment\PayMethod\Adapter\RemoteCreditCard) {
                        $params = array_merge($params, $payment->getPaymentParamsForRemoteCall(), $payment->getBillingContactParamsForRemoteCall($payMethod->client, $payMethod->contact));
                    }
                    $result = $gateway->call("storeremote", $params);
                    if (!is_array($result) || $result["status"] != "success" || !array_key_exists("gatewayid", $result) && !array_key_exists("remoteToken", $result)) {
                        logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote storage failed", $params, $gateway);
                        throw new \RuntimeException("Remote storage failed");
                    }
                    logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote Storage Success", $params, $gateway);
                    if (array_key_exists("gatewayid", $result) && !array_key_exists("remoteToken", $result)) {
                        $result["remoteToken"] = $result["gatewayid"];
                    }
                    $payment->setCardNumber("");
                    if (array_key_exists("cardtype", $result)) {
                        $payment->setCardType($result["cardtype"]);
                    }
                    if (array_key_exists("cardlastfour", $result)) {
                        $payment->setLastFour($result["cardlastfour"]);
                    }
                    if (array_key_exists("cardexpiry", $result)) {
                        $payment->setExpiryDate(\WHMCS\Carbon::createFromCcInput($result["cardexpiry"]));
                    }
                    $payment->setRemoteToken($result["remoteToken"]);
                }
                $payment->runCcUpdateHook();
            } else {
                if (($payMethod->isBankAccount() || $payMethod->isRemoteBankAccount()) && $payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                    $gateway = $payMethod->getGateway();
                    $params = $gateway->loadSettings();
                    $params["companyname"] = \WHMCS\Config\Setting::getValue("CompanyName");
                    $params["systemurl"] = \App::getSystemURL();
                    $params["payMethod"] = $payMethod;
                    $params["action"] = "create";
                    $params["remoteStorageToken"] = $request->get("remoteStorageToken", "");
                    if (!empty($params["convertto"])) {
                        $currencyCode = \WHMCS\Database\Capsule::table("tblcurrencies")->where("id", (int) $params["convertto"])->value("code");
                        $params["currency"] = $currencyCode;
                    }
                    if (empty($params["currency"])) {
                        $clientCurrency = $payMethod->client->currencyrel->code;
                        $params["currency"] = $clientCurrency;
                    }
                    if ($payment instanceof \WHMCS\Payment\PayMethod\Adapter\RemoteBankAccount) {
                        $params = array_merge($params, $payment->getPaymentParamsForRemoteCall(), $payment->getBillingContactParamsForRemoteCall($payMethod->client, $payMethod->contact));
                    }
                    $result = $gateway->call("storeremote", $params);
                    if (!is_array($result) || $result["status"] != "success" || !array_key_exists("gatewayid", $result) && !array_key_exists("remoteToken", $result)) {
                        logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote storage failed", $params, $gateway);
                        throw new \RuntimeException("Remote storage failed");
                    }
                    logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote Storage Success", $params, $gateway);
                    if (array_key_exists("gatewayid", $result) && !array_key_exists("remoteToken", $result)) {
                        $result["remoteToken"] = $result["gatewayid"];
                    }
                    $payment->setAccountNumber($payment->getAccountNumber());
                    if (array_key_exists("banktype", $result)) {
                        $payment->setAccountType($result["banktype"]);
                    }
                    $payment->setRemoteToken($result["remoteToken"]);
                }
            }
            if ($request->has("isDefault") && $request->get("isDefault") && !$payMethod->isDefaultPayMethod()) {
                $payMethod->setAsDefaultPayMethod();
            }
            $payment->save();
            $payMethod->save();
            logActivity("Pay Method Created - " . $payment->getDisplayName(), $payMethod->client->id, ["withClientId" => true]);
            $responseData = ["successMsgTitle" => \AdminLang::trans("global.saved"), "successMsg" => \AdminLang::trans("payments.new" . $payMethod->getType() . "Saved"), "dismiss" => true, "successWindow" => "reloadTablePayMethods"];
        } catch (\Exception $e) {
            $responseData = ["errorMsgTitle" => "Error", "errorMsg" => "Details could not be saved. " . $e->getMessage(), "errorTrace" => $e->getTraceAsString()];
        }
        return new \WHMCS\Http\Message\JsonResponse($responseData);
    }

    public function viewPayMethod(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = (int) $request->getAttribute("userId");
        $payMethodId = (int) $request->getAttribute("payMethodId");
        $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient($payMethodId, $clientId);
        if (!$payMethod) {
            return new \WHMCS\Http\Message\JsonResponse([]);
        }
        $payType = $payMethod->getType();
        $storedAt = NULL;
        $remoteInput = $remoteUpdate = "";
        if ($payType == \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_LOCAL || $payType == \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_BANK_ACCOUNT) {
            $storedAt = \AdminLang::trans("payments.localEncryption");
        } else {
            $gateway = $payMethod->getGateway();
            if ($gateway) {
                $storedAt = $gateway->getDisplayName();
                if ($gateway->isLoadedModuleActive() && $gateway->functionExists("remoteupdate")) {
                    if (!function_exists("getClientsDetails")) {
                        require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
                    }
                    $contactId = $payMethod->getContactId();
                    if (!$contactId) {
                        $contactId = "billing";
                    }
                    $passedParams = getClientsDetails($clientId, $contactId);
                    $passedParams["gatewayid"] = $payMethod->payment->getRemoteToken();
                    $passedParams["payMethod"] = $payMethod;
                    $passedParams["paymethodid"] = $payMethod->id;
                    $remoteUpdate = $gateway->call("remoteupdate", $passedParams);
                }
            } else {
                $storedAt = $payMethod->gateway_name . " - Inoperable";
            }
        }
        $gatewaysHelper = new \WHMCS\Gateways();
        $enableStartDateIssueNumber = $gatewaysHelper->isIssueDateAndStartNumberEnabled();
        $gatewaysList = $gatewaysHelper->getActiveMerchantGatewaysByType();
        switch ($payType) {
            case \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_REMOTE_MANAGED:
                $gatewayList = array_merge($gatewaysList[\WHMCS\Module\Gateway::WORKFLOW_TOKEN], $gatewaysList[\WHMCS\Module\Gateway::WORKFLOW_REMOTE], $gatewaysList[\WHMCS\Module\Gateway::WORKFLOW_ASSISTED], $gatewaysList[\WHMCS\Module\Gateway::WORKFLOW_NOLOCALCARDINPUT]);
                $gatewayList = array_keys($gatewayList);
                break;
            case \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_REMOTE_BANK_ACCOUNT:
                $gatewayList = $gatewaysList[\WHMCS\Module\Gateway::WORKFLOW_NOLOCALCARDINPUT];
                $gatewayList = array_keys($gatewayList);
                break;
            default:
                $gatewayList = [];
                $alternativeGateways = [];
                $gatewayInterface = new \WHMCS\Module\Gateway();
                foreach ($gatewayList as $gateway) {
                    if ($gatewayInterface->load($gateway)) {
                        $alternativeGateways[$gateway] = $gatewayInterface->getDisplayName();
                    }
                }
                $body = view("admin.client.paymethods.details", ["payMethod" => $payMethod, "storedAt" => $storedAt, "client" => $payMethod->client, "actionUrl" => routePath("admin-client-paymethods-update", $clientId, $payMethodId), "deleteUrl" => routePath("admin-client-paymethods-delete", $clientId, $payMethodId), "startDateEnabled" => $enableStartDateIssueNumber, "issueNumberEnabled" => $enableStartDateIssueNumber, "remoteInput" => $remoteInput, "remoteUpdate" => $remoteUpdate, "forceDefault" => false, "supportedCardTypes" => \WHMCS\Gateways::getSupportedCardTypesForJQueryPayment(), "alternativeGateways" => $alternativeGateways]);
                $body = (new \WHMCS\Admin\ApplicationSupport\View\PreRenderProcessor())->process($body);
                $response = new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
                return $response;
        }
    }

    public function updateExisting(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $payment = \WHMCS\Payment\PayMethod\Model::factoryFromRequest($request);
            $payMethod = $payment->payMethod;
            if ($request->has("gateway_name") && $payment->isMigrated()) {
                $inputGatewayName = $request->get("gateway_name");
                if ((new \WHMCS\Module\Gateway())->load($inputGatewayName)) {
                    \WHMCS\Database\Capsule::table("tblpaymethods")->where("id", $request->get("payMethodId"))->update(["gateway_name" => $inputGatewayName]);
                    $payment = \WHMCS\Payment\PayMethod\Model::factoryFromRequest($request);
                    $payMethod = $payment->payMethod;
                }
            }
            if ($payMethod->isCreditCard()) {
                $issueNumber = $request->get("ccissuenum");
                if ($issueNumber) {
                    $payment->setIssueNumber($issueNumber);
                }
            }
            $payMethod->description = $request->get("description");
            $billingContact = $payMethod::getBillingContact($request, $payMethod->client);
            $payMethod->contact()->associate($billingContact);
            if ($payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface && $payment->isDirty()) {
                $gateway = $payMethod->getGateway();
                $params = $gateway->loadSettings();
                $params["companyname"] = \WHMCS\Config\Setting::getValue("CompanyName");
                $params["systemurl"] = \App::getSystemURL();
                $params["payMethod"] = $payMethod;
                $params["action"] = "update";
                $params["gatewayid"] = \WHMCS\Input\Sanitize::decode($payment->getRemoteToken());
                $params["remoteStorageToken"] = $params["gatewayid"];
                if ($payMethod->isCreditCard()) {
                    $params["cardexp"] = $payment->expiry_date->format("my");
                    $params["cardExpiry"] = $payment->expiry_date->format("mY");
                    $params["cardExpiryMonth"] = $payment->expiry_date->format("m");
                    $params["cardExpiryYear"] = $payment->expiry_date->format("Y");
                    $params["cardlastfour"] = $payment->getLastFour();
                }
                $params = array_merge($params, $payment->getBillingContactParamsForRemoteCall($payMethod->client, $payMethod->contact));
                if ($gateway->functionExists("storeremote")) {
                    $result = $gateway->call("storeremote", $params);
                    $payment = $payMethod->payment;
                    if (!is_array($result) || $result["status"] != "success") {
                        logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote Update Failed", $params, $gateway);
                        throw new \RuntimeException("Remote update failed");
                    }
                    logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote Update Success", $params, $gateway);
                    if (array_key_exists("gatewayid", $result) && !array_key_exists("remoteToken", $result)) {
                        $result["remoteToken"] = $result["gatewayid"];
                    }
                    if ($payMethod->isCreditCard() && array_key_exists("cardexpiry", $result)) {
                        $payment->setExpiryDate(\WHMCS\Carbon::createFromCcInput($result["cardexpiry"]));
                    }
                    if (array_key_exists("remoteToken", $result) && $result["remoteToken"]) {
                        $payment->setRemoteToken($result["remoteToken"]);
                    }
                }
                if ($payment->isCreditCard()) {
                    $payment->runCcUpdateHook();
                }
            }
            if ($request->has("isDefault") && $request->get("isDefault") && !$payMethod->isDefaultPayMethod()) {
                $payMethod->setAsDefaultPayMethod();
            }
            $payment->save();
            $payMethod->save();
            if ($payMethod->isCreditCard()) {
                $payMethod->payment->runCcUpdateHook();
            }
            logActivity("Pay Method Updated - " . $payment->getDisplayName(), $payMethod->client->id, ["withClientId" => true]);
            $responseData = ["successMsgTitle" => \AdminLang::trans("global.saved"), "successMsg" => \AdminLang::trans("payments.existing" . $payMethod->getType() . "Updated"), "dismiss" => true, "successWindow" => "reloadTablePayMethods"];
            return new \WHMCS\Http\Message\JsonResponse($responseData);
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["error" => true, "errorMsg" => $e->getMessage()]);
        }
    }

    public function deleteExisting(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = (int) $request->getAttribute("userId");
        $payMethodId = (int) $request->getAttribute("payMethodId");
        $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient($payMethodId, $clientId);
        if (!$payMethod) {
            return new \WHMCS\Http\Message\JsonResponse([]);
        }
        $payType = $payMethod->getType();
        $storedAt = NULL;
        if ($payType == \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_LOCAL || $payType == \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_BANK_ACCOUNT) {
            $storedAt = \AdminLang::trans("payments.localEncryption");
        } else {
            $gateway = $payMethod->getGateway();
            if ($gateway) {
                $storedAt = $gateway->getDisplayName();
            } else {
                $storedAt = $payMethod->gateway_name . " - Inoperable";
            }
        }
        $body = view("admin.client.paymethods.confirm-delete", ["payMethod" => $payMethod, "storedAt" => $storedAt, "client" => $payMethod->client, "confirmDelete" => true, "deleteUrl" => routePath("admin-client-paymethods-delete-confirm", $clientId, $payMethodId)]);
        $body = (new \WHMCS\Admin\ApplicationSupport\View\PreRenderProcessor())->process($body);
        $response = new \WHMCS\Http\Message\JsonResponse(["body" => $body]);
        return $response;
    }

    public function doDeleteExisting(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $payment = \WHMCS\Payment\PayMethod\Model::factoryFromRequest($request);
            $payMethod = $payment->payMethod;
            $payMethodType = $payMethod->getType();
            $description = $payment->getDisplayName();
            if ($payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                $gateway = $payMethod->getGateway();
                if ($gateway && $gateway->functionExists("storeremote")) {
                    if ($gateway->isActive($gateway->getLoadedModule())) {
                        $params = $gateway->loadSettings();
                        $params["companyname"] = \WHMCS\Config\Setting::getValue("CompanyName");
                        $params["systemurl"] = \App::getSystemURL();
                        $params["payMethod"] = $payMethod;
                        $params["action"] = "delete";
                        $params["gatewayid"] = \WHMCS\Input\Sanitize::decode($payment->getRemoteToken());
                        $params = array_merge($params, $payment->getBillingContactParamsForRemoteCall($payMethod->client, $payMethod->contact));
                        $result = $gateway->call("storeremote", $params);
                    } else {
                        $result = ["status" => "error", "rawdata" => \AdminLang::trans("clientsummary.inactiveGatewayRemoteToken")];
                    }
                    $ignoreRemoteFailure = $request->get("ignoreRemoteFailure");
                    if (!is_array($result) || $result["status"] !== "success") {
                        logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote Delete Gateway Call Failed", !empty($params) ? $params : [], $gateway);
                        if (isset($result["rawdata"])) {
                            $details = $result["rawdata"];
                            if (is_array($details)) {
                                $details = json_encode($details, JSON_PRETTY_PRINT);
                            }
                            $details = "Gateway response:<pre class=\"gateway-response\">" . nl2br(\WHMCS\Input\Sanitize::makeSafeForOutput($details)) . "</pre>";
                        } else {
                            $details = "Please check the Gateway Log in a new window for more information.";
                        }
                        if (!$ignoreRemoteFailure) {
                            throw new \RuntimeException("Remote delete failed. " . $details);
                        }
                    }
                    logTransaction($gateway->getDisplayName(), $result["rawdata"], "Remote Delete Success", !empty($params) ? $params : [], $gateway);
                }
            }
            $payMethod->delete();
            if ($payMethod->isCreditCard()) {
                $payMethod->payment->runCcUpdateHook();
            }
            logActivity("Pay Method Deleted - " . $description, $payMethod->client->id, ["withClientId" => true]);
            $responseData = ["successMsgTitle" => \AdminLang::trans("global.saved"), "successMsg" => \AdminLang::trans("payments.existing" . $payMethodType . "Removed"), "dismiss" => true, "successWindow" => "reloadTablePayMethods"];
            return new \WHMCS\Http\Message\JsonResponse($responseData);
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["error" => true, "errorMsg" => $e->getMessage()]);
        }
    }

    public function payMethodsHtmlRows(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = (int) $request->getAttribute("userId");
        $client = \WHMCS\User\Client::find($clientId);
        $responseData = ["body" => ""];
        if ($client) {
            $helper = new ViewHelper();
            $responseData["body"] = $helper->clientProfileSummaryHtmlTableRows($client);
        }
        return new \WHMCS\Http\Message\JsonResponse($responseData);
    }

    public function decryptCcData(\WHMCS\Http\Message\ServerRequest $request)
    {
        $submittedHash = $request->get("cchash");
        if ($submittedHash !== \DI::make("config")->cc_encryption_hash) {
            return new \WHMCS\Http\Message\JsonResponse(["errorMsgTitle" => "", "errorMsg" => \AdminLang::trans("clients.incorrecthash")]);
        }
        $payment = \WHMCS\Payment\PayMethod\Model::factoryFromRequest($request);
        if (!$payment || $payment->payMethod->getType() != \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_LOCAL && $payment->payMethod->getType() != \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_BANK_ACCOUNT) {
            return new \WHMCS\Http\Message\JsonResponse(["errorMsgTitle" => "", "errorMsg" => \AdminLang::trans("global.erroroccurred")]);
        }
        if ($payment->payMethod->getType() === \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_LOCAL) {
            return new \WHMCS\Http\Message\JsonResponse(["ccnum" => $payment->getCardNumber()]);
        }
        return new \WHMCS\Http\Message\JsonResponse(["bankAcctNumber" => $payment->getAccountNumber()]);
    }

    public function clearLocalCardPayMethods(\WHMCS\Http\Message\ServerRequest $request)
    {
        \WHMCS\Database\Capsule::table("tblclients")->where("gatewayid", "")->update(["cardtype" => "", "cardlastfour" => "", "cardnum" => "", "expdate" => "", "startdate" => "", "issuenumber" => ""]);
        \WHMCS\Payment\PayMethod\Model::deleteLocalCreditCards();
        logAdminActivity("Locally stored credit cards deleted");
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "successMsgTitle" => \AdminLang::trans("global.success"), "successMsg" => \AdminLang::trans("global.operationCompletedSuccessfully")]);
    }

    public function clearLocalBankPayMethods(\WHMCS\Http\Message\ServerRequest $request)
    {
        \WHMCS\Database\Capsule::table("tblclients")->where("gatewayid", "")->update(["bankname" => "", "banktype" => "", "bankcode" => "", "bankacct" => ""]);
        \WHMCS\Payment\PayMethod\Model::deleteLocalBankAccounts();
        logAdminActivity("Locally stored bank accounts deleted");
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "successMsgTitle" => \AdminLang::trans("global.success"), "successMsg" => \AdminLang::trans("global.operationCompletedSuccessfully")]);
    }

    public function remoteConfirm(\WHMCS\Http\Message\ServerRequest $request)
    {
        $gatewayName = $request->get("gateway");
        $gateway = new \WHMCS\Module\Gateway();
        if (!$gatewayName) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Invalid Request"]);
        }
        if (!$gateway->load($gatewayName)) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Module Not Active"]);
        }
        $remoteStorageToken = \WHMCS\Session::getAndDelete($gatewayName . "Confirm");
        if (!$remoteStorageToken) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Invalid Request"]);
        }
        if (!$gateway->functionExists("remote_input_confirm")) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Unsupported Request"]);
        }
        $result = $gateway->call("remote_input_confirm", ["gatewayid" => $remoteStorageToken, "remoteStorageToken" => $remoteStorageToken]);
        if (array_key_exists("warning", $result) && $result["warning"]) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => $result["warning"]]);
        }
        $client = \WHMCS\User\Client::find($request->get("client_id"));
        if (!$client) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Client ID not found"]);
        }
        $payMethod = \WHMCS\Payment\PayMethod\Adapter\RemoteCreditCard::factoryPayMethod($client, $client->billingContact);
        $payment = $payMethod->payment;
        $payMethod->setGateway($gateway);
        $payment->setCardNumber($result["cardnumber"])->setExpiryDate(\WHMCS\Carbon::createFromCcInput($result["cardexpiry"]))->setRemoteToken($result["gatewayid"])->save();
        $payMethod->save();
        \WHMCS\Session::set("payMethodCreateSuccess", true);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => "", "successWindow" => "reloadTablePayMethods"]);
    }

    public function remoteUpdate(\WHMCS\Http\Message\ServerRequest $request)
    {
        $gatewayName = $request->get("gateway");
        $payMethodId = $request->get("pay_method_id");
        $gateway = new \WHMCS\Module\Gateway();
        if (!$gatewayName) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Invalid Request"]);
        }
        if (!$gateway->load($gatewayName)) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Module Not Active"]);
        }
        $remoteStorageToken = \WHMCS\Session::getAndDelete($gatewayName . "Confirm");
        if (!$remoteStorageToken || !$payMethodId) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Invalid Request"]);
        }
        $payMethod = \WHMCS\Payment\PayMethod\Model::find($payMethodId);
        if ($payMethod->gateway_name != $gatewayName) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Invalid Request"]);
        }
        if (!$gateway->functionExists("remote_input_confirm")) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Unsupported Request"]);
        }
        $result = $gateway->call("remote_input_confirm", ["gatewayid" => $remoteStorageToken, "remoteStorageToken" => $remoteStorageToken]);
        if (array_key_exists("warning", $result) && $result["warning"]) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => $result["warning"]]);
        }
        $payment = $payMethod->payment;
        $payMethod->setGateway($gateway);
        $payment->setCardNumber($result["cardnumber"])->setExpiryDate(\WHMCS\Carbon::createFromCcInput($result["cardexpiry"]))->setRemoteToken($result["gatewayid"])->save();
        $payMethod->save();
        \WHMCS\Session::set("payMethodSaveSuccess", true);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "redirectUrl" => "", "successWindow" => "reloadTablePayMethods"]);
    }
}
