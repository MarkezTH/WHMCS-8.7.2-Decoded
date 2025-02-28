<?php

namespace WHMCS\ClientArea\Invoice;

class InvoiceController
{
    protected $userDetailsValidationError = false;

    public function pay(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true);
        $invoiceId = $request->get("id", 0);
        $invoice = NULL;
        try {
            if (!\Auth::client() || !$invoiceId) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Access Attempt");
            }
            $invoice = new \WHMCS\Invoice($invoiceId);
            $this->checkAccess($invoice);
            $gateway = new \WHMCS\Module\Gateway();
            $gateway->load($invoice->getData("paymentmodule"));
            $gateway->getParam("type");
            switch ($gateway->getParam("type")) {
                case \WHMCS\Module\Gateway::GATEWAY_BANK:
                    return $this->payBank($request, $invoice);
                    break;
                case \WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD:
                    return $this->payCard($request, $invoice);
                    break;
                default:
                    return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/viewinvoice.php?id=" . $invoiceId);
            }
        } catch (\Exception $e) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
        }
    }

    protected function payBank(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\Invoice $invoice = NULL, $errorMessage = "")
    {
        global $params;
        $payMethodId = NULL;
        $payMethod = NULL;
        if (!function_exists("getCCVariables")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ccfunctions.php";
        }
        if (!function_exists("getCountriesDropDown")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $userId = \Auth::client()->id;
        if (is_null($invoice)) {
            $invoiceId = $request->get("id", 0);
            try {
                if (!$userId || !$invoiceId) {
                    throw new \WHMCS\Exception\Module\NotServicable("Invalid Access Attempt");
                }
                $invoice = new \WHMCS\Invoice($invoiceId);
                $this->checkAccess($invoice);
            } catch (\Exception $e) {
                return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
            }
        }
        try {
            $invoiceId = $invoice->getID();
            $client = \WHMCS\User\Client::findOrFail($userId);
            $gateway = new \WHMCS\Module\Gateway();
            $gatewayName = $invoice->getData("paymentmodule");
            $gateway->load($gatewayName);
            $view = $this->initView();
            $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
            $view->addToBreadCrumb("viewinvoice.php?id=" . $invoiceId, \Lang::trans("invoices"));
            $view->addToBreadCrumb(routePath("invoice-pay", $invoiceId), \Lang::trans("invoicenumber") . $invoice->getData("invoicenum"));
            $invoiceId = $invoice->getData("invoiceid");
            $invoiceNum = $invoice->getData("invoicenum");
            $payMethodId = $request->get("paymethod");
            $accountType = $request->get("account_type");
            $accountHolderName = $request->get("account_holder_name");
            $bankName = $request->get("bank_name");
            $routingNumber = $request->get("routing_number");
            $accountNumber = $request->get("account_number");
            $description = $request->get("description");
            $firstName = $request->get("firstname");
            $lastName = $request->get("lastname");
            $address1 = $request->get("address1");
            $address2 = $request->get("address2");
            $city = $request->get("city");
            $state = $request->get("state");
            $postcode = $request->get("postcode");
            $country = $request->get("country");
            $phoneNumber = \App::formatPostedPhoneNumber();
            $billingContactId = $request->get("billingcontact");
            $params = NULL;
            $invoiceData = $invoice->getOutput();
            $existingClientAccounts = [];
            $gatewayAccounts = $client->payMethods->bankAccounts()->validateGateways()->filter(function (\WHMCS\Payment\Contracts\PayMethodInterface $payMethod) use($gateway) {
                if ($payMethod->getType() === \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_BANK_ACCOUNT) {
                    return true;
                }
                $payMethodGateway = $payMethod->getGateway();
                return $payMethodGateway && $payMethodGateway->getLoadedModule() === $gateway->getLoadedModule();
            });
            $billingContacts = $client->buildBillingContactsArray();
            $defaultAccountKey = NULL;
            $lowestOrder = NULL;
            foreach ($gatewayAccounts as $key => $bankAccountMethod) {
                if (is_null($lowestOrder) || $lowestOrder < $bankAccountMethod->order_preference) {
                    $lowestOrder = $bankAccountMethod->order_preference;
                    $defaultAccountKey = $key;
                }
                $existingClientAccounts[$key] = getPayMethodBankDetails($bankAccountMethod);
            }
            $existingAccount = ["bankname" => NULL, "banktype" => NULL, "bankacct" => NULL, "bankcode" => NULL, "gatewayid" => NULL, "billingcontactid" => NULL];
            if (!empty($existingClientAccounts)) {
                $existingAccount = $existingClientAccounts[$defaultAccountKey];
                if (!$payMethodId) {
                    $payMethodId = $existingAccount["paymethodid"];
                }
            }
            $countryObject = new \WHMCS\Utility\Country();
            $hasExistingAccount = 0 < strlen($existingAccount["bankacct"]) || 0 < strlen($existingAccount["gatewayid"]);
            if (!$payMethodId) {
                $payMethodId = "new";
            }
            if (!$country) {
                $country = \WHMCS\Config\Setting::getValue("DefaultCountry");
            }
            $templateVariables = ["submitLocation" => routePath("invoice-pay-process", $invoiceId), "cardOrBank" => "bank", "firstname" => $firstName, "lastname" => $lastName, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "countryname" => $countryObject->getName($country), "countriesdropdown" => getCountriesDropDown($country), "countries" => $countryObject->getCountryNameArray(), "phonenumber" => $phoneNumber, "existingAccount" => $hasExistingAccount, "addingNewAccount" => $payMethodId == "new" || !$hasExistingAccount, "addingNew" => $payMethodId == "new" || !$hasExistingAccount, "payMethodId" => $payMethodId, "accountType" => $accountType, "accountHolderName" => $accountHolderName, "bankName" => $bankName, "routingNumber" => $routingNumber, "accountNumber" => $accountNumber, "description" => $description, "defaultBillingContact" => $billingContacts[$client->billingContactId], "billingContacts" => $billingContacts, "billingContact" => $billingContactId, "existingAccounts" => $existingClientAccounts, "errormessage" => $errorMessage, "invoiceid" => $invoiceId, "invoicenum" => $invoiceNum, "total" => $invoiceData["total"], "balance" => $invoiceData["balance"], "invoice" => $invoiceData, "invoiceitems" => $invoice->getLineItems(), "userDetailsValidationError" => $this->userDetailsValidationError, "hasRemoteInput" => false];
            foreach ($templateVariables as $templateVariable => $value) {
                $view->assign($templateVariable, $value);
            }
            if ($gateway->functionExists("bank_account_input")) {
                if (is_null($params)) {
                    $params = getCCVariables($invoiceId);
                }
                $view->assign("credit_card_input", $gateway->call("bank_account_input", $params));
            }
            return $view;
        } catch (\Exception $e) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
        }
    }

    protected function payCard(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\Invoice $invoice, $errorMessage = "")
    {
        $payMethodId = NULL;
        $payMethod = NULL;
        if (!function_exists("getCCVariables")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ccfunctions.php";
        }
        if (!function_exists("getCountriesDropDown")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $userId = \Auth::client()->id;
        if (is_null($invoice)) {
            $invoiceId = $request->get("id", 0);
            try {
                if (!$userId || !$invoiceId) {
                    throw new \WHMCS\Exception\Module\NotServicable("Invalid Access Attempt");
                }
                $invoice = new \WHMCS\Invoice($invoiceId);
                $this->checkAccess($invoice);
            } catch (\Exception $e) {
                return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
            }
        }
        try {
            $client = \WHMCS\User\Client::findOrFail($userId);
            $invoiceId = $invoice->getID();
            $gateway = new \WHMCS\Module\Gateway();
            $gatewayName = $invoice->getData("paymentmodule");
            $gateway->load($gatewayName);
            $view = $this->initView("ClientAreaPageCreditCardCheckout");
            $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
            $view->addToBreadCrumb("viewinvoice.php?id=" . $invoiceId, \Lang::trans("invoices"));
            $view->addToBreadCrumb(routePath("invoice-pay", $invoiceId), \Lang::trans("invoicenumber") . $invoice->getData("invoicenum"));
            $invoiceId = $invoice->getData("invoiceid");
            $invoiceNum = $invoice->getData("invoicenum");
            $payMethodId = $request->get("ccinfo");
            $ccDescription = $request->get("ccdescription");
            $ccNumber = $request->get("ccnumber");
            $ccExpiryDate = $request->get("ccexpirydate");
            $ccExpiryMonth = $ccExpiryYear = $ccStartMonth = $ccStartYear = "";
            if ($ccExpiryDate) {
                $ccExpiryDate = \WHMCS\Carbon::createFromCcInput($ccExpiryDate);
                $ccExpiryMonth = $ccExpiryDate->month;
                $ccExpiryYear = $ccExpiryDate->year;
            }
            $ccStartDate = $request->get("ccstartdate");
            if ($ccStartDate) {
                $ccStartDate = \WHMCS\Carbon::createFromCcInput($ccStartDate);
                $ccStartMonth = $ccStartDate->month;
                $ccStartYear = $ccStartDate->year;
            }
            $ccIssueNumber = $request->get("ccissuenum");
            $ccCvv = $request->get("cccvv");
            $ccCvv2 = $request->get("cccvv2");
            if (!$ccCvv) {
                $ccCvv = $ccCvv2;
            }
            $description = $request->get("description");
            $firstName = $request->get("firstname");
            $lastName = $request->get("lastname");
            $address1 = $request->get("address1");
            $address2 = $request->get("address2");
            $city = $request->get("city");
            $state = $request->get("state");
            $postcode = $request->get("postcode");
            $country = $request->get("country");
            $phoneNumber = \App::formatPostedPhoneNumber();
            $billingContactId = $this->coalesceBillingContactId($request, $client, 0);
            $invoiceData = $invoice->getOutput();
            $existingClientCards = [];
            $gatewayCards = $client->payMethods->creditCards()->validateGateways()->sortByExpiryDate()->filter(function (\WHMCS\Payment\Contracts\PayMethodInterface $payMethod) use($gateway) {
                if ($payMethod->getType() === \WHMCS\Payment\Contracts\PayMethodTypeInterface::TYPE_CREDITCARD_LOCAL && !in_array($gateway->getWorkflowType(), [\WHMCS\Module\Gateway::WORKFLOW_ASSISTED, \WHMCS\Module\Gateway::WORKFLOW_REMOTE])) {
                    return true;
                }
                $payMethodGateway = $payMethod->getGateway();
                return $payMethodGateway && $payMethodGateway->getLoadedModule() === $gateway->getLoadedModule();
            });
            $billingContacts = $client->buildBillingContactsArray();
            $defaultCardKey = NULL;
            $lowestOrder = NULL;
            foreach ($gatewayCards as $key => $creditCardMethod) {
                if (is_null($lowestOrder) || $creditCardMethod->order_preference < $lowestOrder) {
                    $lowestOrder = $creditCardMethod->order_preference;
                    $defaultCardKey = $key;
                }
                $existingClientCards[$key] = getPayMethodCardDetails($creditCardMethod);
            }
            $existingCard = ["cardtype" => NULL, "cardlastfour" => NULL, "cardnum" => \Lang::trans("nocarddetails"), "fullcardnum" => NULL, "expdate" => "", "startdate" => "", "issuenumber" => NULL, "gatewayid" => NULL, "billingcontactid" => NULL];
            if (!empty($existingClientCards)) {
                $existingCard = $existingClientCards[$defaultCardKey];
                if (!$payMethodId) {
                    $payMethodId = $existingCard["paymethodid"];
                    $billingContactId = $existingCard["billingcontactid"];
                }
            }
            $countryObject = new \WHMCS\Utility\Country();
            $hasExistingCard = 0 < strlen($existingCard["cardlastfour"]);
            $hasRemoteInput = false;
            $showRemoteInput = false;
            $remoteInput = false;
            if ($gateway->functionExists("remoteinput")) {
                $hasRemoteInput = true;
                if (!$payMethodId || $payMethodId === "new") {
                    $params = getCCVariables($invoiceId);
                    $remoteInput = $gateway->call("remoteinput", $params);
                    $remoteInput = str_replace("<form", "<form target=\"3dauth\"", $remoteInput);
                    $showRemoteInput = true;
                }
            }
            if (!$country) {
                $country = \WHMCS\Config\Setting::getValue("DefaultCountry");
            }
            $templateVariables = ["submitLocation" => routePath("invoice-pay-process", $invoiceId), "cardOrBank" => "card", "firstname" => $firstName, "lastname" => $lastName, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "countryname" => $countryObject->getName($country), "countriesdropdown" => getCountriesDropDown($country), "countries" => $countryObject->getCountryNameArray(), "phonenumber" => $phoneNumber, "cardOnFile" => $hasExistingCard, "addingNewCard" => $payMethodId === "new" || !$hasExistingCard, "addingNew" => $payMethodId === "new" || !$hasExistingCard, "payMethodId" => $payMethodId, "cardtype" => $existingCard["cardtype"], "cardnum" => $existingCard["cardlastfour"], "existingCardType" => $existingCard["cardtype"], "existingCardLastFour" => $existingCard["cardlastfour"], "existingCardExpiryDate" => $existingCard["expdate"], "existingCardStartDate" => $existingCard["startdate"], "existingCardIssueNum" => $existingCard["issuenumber"], "description" => $description, "defaultBillingContact" => $billingContacts[$client->billingContactId], "billingContacts" => $billingContacts, "billingContact" => $billingContactId, "existingCards" => $existingClientCards, "ccdescription" => $ccDescription, "ccnumber" => $ccNumber, "ccexpirymonth" => $ccExpiryMonth, "ccexpiryyear" => is_numeric($ccExpiryYear) && $ccExpiryYear < 2000 ? $ccExpiryYear + 2000 : $ccExpiryYear, "ccstartmonth" => $ccStartMonth, "ccstartyear" => is_numeric($ccStartYear) && $ccStartYear < 2000 ? $ccStartYear + 2000 : $ccStartYear, "ccstartdate" => \WHMCS\Carbon::optionalValueForCreditCardInput($ccStartDate), "ccexpirydate" => \WHMCS\Carbon::optionalValueForCreditCardInput($ccExpiryDate), "ccissuenum" => $ccIssueNumber, "cccvv" => $ccCvv, "showccissuestart" => \WHMCS\Config\Setting::getValue("ShowCCIssueStart"), "shownostore" => \WHMCS\Config\Setting::getValue("CCAllowCustomerDelete") && !$gateway->functionExists("storeremote"), "allowClientsToRemoveCards" => \WHMCS\Config\Setting::getValue("CCAllowCustomerDelete") && !$gateway->functionExists("storeremote"), "errormessage" => $errorMessage, "invoiceid" => $invoiceId, "invoicenum" => $invoiceNum, "total" => $invoiceData["total"], "balance" => $invoiceData["balance"], "invoice" => $invoiceData, "invoiceitems" => $invoice->getLineItems(), "userDetailsValidationError" => $this->userDetailsValidationError, "showRemoteInput" => $showRemoteInput, "hasRemoteInput" => $hasRemoteInput, "remoteInput" => $remoteInput, "newCardRoute" => \WHMCS\Input\Sanitize::escapeSingleQuotedString(fqdnRoutePath("invoice-pay", $invoiceId))];
            foreach ($templateVariables as $templateVariable => $value) {
                $view->assign($templateVariable, $value);
            }
            if ($gateway->functionExists("credit_card_input")) {
                $params = getCCVariables($invoiceId);
                $view->assign("credit_card_input", $gateway->call("credit_card_input", $params));
            }
            return $view;
        } catch (\Exception $e) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
        }
    }

    public function process(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-index");
        $userId = \Auth::client()->id;
        $invoiceId = $request->get("id", 0);
        try {
            if (!$userId || !$invoiceId) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Access Attempt");
            }
            $invoice = new \WHMCS\Invoice($invoiceId);
            $this->checkAccess($invoice);
            $gateway = new \WHMCS\Module\Gateway();
            $gatewayName = $invoice->getData("paymentmodule");
            $gateway->load($gatewayName);
        } catch (\Exception $e) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
        }
        $gatewayType = $gateway->getParam("type");
        $payMethodId = $request->get("paymethod");
        if ($request->has("ccinfo")) {
            $payMethodId = $request->get("ccinfo");
        }
        try {
            switch ($gatewayType) {
                case \WHMCS\Module\Gateway::GATEWAY_BANK:
                    $payMethod = $this->validateBank($request, $invoice);
                    break;
                case \WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD:
                    $payMethod = $this->validateCard($request, $invoice);
                    return $this->processPayment($request, $invoice, $payMethod);
                    break;
                default:
                    return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/viewinvoice.php?id=" . $invoiceId);
            }
        } catch (\Exception $e) {
            if (!$e instanceof \WHMCS\Exception && !$e instanceof \RuntimeException) {
                throw $e;
            }
            if ($payMethodId === "new" && isset($payMethod) && $payMethod instanceof \WHMCS\Payment\PayMethod\Model) {
                $payMethod->delete();
                $payMethod = NULL;
            }
            $function = "payBank";
            if ($gatewayType == \WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD) {
                $function = "payCard";
            }
            return $this->{$function}($request, $invoice, $e->getMessage());
        }
    }

    public function processCardFromCart(\WHMCS\Http\Message\ServerRequest $request)
    {
        if (!function_exists("getCCVariables")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ccfunctions.php";
        }
        $userId = \Auth::client()->id;
        $invoiceId = $request->get("id");
        try {
            if (!$userId || !$invoiceId) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Access Attempt");
            }
            $invoice = new \WHMCS\Invoice($invoiceId);
            $this->checkAccess($invoice);
            $gateway = new \WHMCS\Module\Gateway();
            $gatewayName = $invoice->getData("paymentmodule");
            $gateway->load($gatewayName);
        } catch (\Exception $e) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php");
        }
        if (!\WHMCS\Session::get("cartccdetail")) {
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/viewinvoice.php?id=" . $invoiceId);
        }
        $gatewayType = $gateway->getParam("type");
        $cartCcDetail = safe_unserialize(base64_decode(decrypt(\WHMCS\Session::get("cartccdetail"))));
        list($ccNumber, $payMethodId) = $cartCcDetail;
        if (ccFormatNumbers($ccNumber)) {
            $payMethodId = "new";
        }
        unset($ccNumber);
        switch ($gatewayType) {
            case \WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD:
                try {
                    $payMethod = $this->validateCard($request, $invoice, true);
                    return $this->processPayment($request, $invoice, $payMethod, true);
                } catch (\WHMCS\Exception $e) {
                    if ($payMethodId == "new" && isset($payMethod) && $payMethod instanceof \WHMCS\Payment\PayMethod\Model) {
                        $payMethod->delete();
                        $payMethod = NULL;
                    }
                    return $this->payCard($request, $invoice, $e->getMessage());
                }
                break;
            default:
                return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/viewinvoice.php?id=" . $invoiceId);
        }
    }

    protected function validateBank(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\Invoice $invoice)
    {
        global $params;
        if (!function_exists("checkDetailsareValid")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $invoiceId = $invoice->getID();
        $payMethodId = $request->get("paymethod");
        if (!($payMethodId == "new" || is_numeric($payMethodId))) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid Payment Method Selection");
        }
        $firstName = $request->get("firstname");
        $lastName = $request->get("lastname");
        $address1 = $request->get("address1");
        $address2 = $request->get("address2");
        $city = $request->get("city");
        $state = $request->get("state");
        $postcode = $request->get("postcode");
        $country = $request->get("country");
        $phoneNumber = \App::formatPostedPhoneNumber();
        $client = \Auth::assertClient();
        $billingContactId = $this->coalesceBillingContactId($request, $client);
        $billingContact = NULL;
        if ($billingContactId === "new") {
            $errorMessage = checkDetailsareValid($client->id, false, false, false, false);
            if ($errorMessage) {
                $this->userDetailsValidationError = true;
                throw new \WHMCS\Exception\Module\NotServicable($errorMessage);
            }
            $billingContactId = \WHMCS\Database\Capsule::table("tblcontacts")->insertGetId(["userid" => $client->id, "firstname" => $firstName, "lastname" => $lastName, "email" => $client->email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phoneNumber]);
        }
        if (is_numeric($billingContactId) && 0 < $billingContactId) {
            $billingContact = $client->contacts->find((int) $billingContactId);
            if (!$billingContact) {
                throw new \WHMCS\Exception\Module\NotServicable(\Lang::trans("clientareanocontacts"));
            }
        }
        $gateway = new \WHMCS\Module\Gateway();
        $gatewayName = $invoice->getData("paymentmodule");
        $gateway->load($gatewayName);
        $accountType = $request->get("account_type");
        $accountHolderName = $request->get("account_holder_name");
        $bankName = $request->get("bank_name");
        $routingNumber = $request->get("routing_number");
        $accountNumber = $request->get("account_number");
        $description = $request->get("description");
        $invoiceModel = $invoice->getModel();
        $this->userDetailsValidationError = false;
        $params = NULL;
        $payMethod = NULL;
        if ($payMethodId == "new") {
            if ($gateway->supportsLocalBankDetails()) {
                $payMethod = \WHMCS\Payment\PayMethod\Adapter\BankAccount::factoryPayMethod($client, $billingContact, $description);
                $payment = $payMethod->payment;
                try {
                    $payment->setBankName($bankName)->setAccountType($accountType)->setAccountHolderName($accountHolderName)->setAccountNumber($accountNumber)->setRoutingNumber($routingNumber)->validateRequiredValuesPreSave()->save();
                } catch (\Exception $e) {
                    $payMethod->delete();
                    throw $e;
                }
            } else {
                $remoteStorageToken = $request->get("remoteStorageToken");
                $payMethod = \WHMCS\Payment\PayMethod\Adapter\RemoteBankAccount::factoryPayMethod($client, $billingContact, $description);
                $payMethod->setGateway($gateway)->save();
                $payment = $payMethod->payment;
                try {
                    $payment->setRemoteToken($remoteStorageToken)->setBankName($bankName)->setAccountType($accountType)->setAccountHolderName($accountHolderName)->setAccountNumber($accountNumber)->setRoutingNumber($routingNumber)->validateRequiredValuesPreSave()->createRemote()->save();
                } catch (\Exception $e) {
                    $payMethod->delete();
                    throw $e;
                }
            }
        } else {
            $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient((int) $payMethodId, $client->id);
        }
        unset($payMethodId);
        if (!$payMethod) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid Payment Method Selection");
        }
        $invoiceModel->payMethod()->associate($payMethod);
        $invoiceModel->save();
        if (!function_exists("getCCVariables")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ccfunctions.php";
        }
        $params = getCCVariables($invoiceId, $gatewayName, $payMethod, $billingContactId);
        return $payMethod;
    }

    protected function validateCard(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\Invoice $invoice, $fromOrderForm = false)
    {
        global $params;
        if (!function_exists("getCCVariables")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ccfunctions.php";
        }
        if (!function_exists("checkDetailsareValid")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        if (!$fromOrderForm) {
            check_token();
        }
        $errorMessage = "";
        $userId = \Auth::client()->id;
        $invoiceId = $invoice->getID();
        $payMethodId = $request->get("ccinfo");
        $client = \WHMCS\User\Client::findOrFail($userId);
        $gateway = new \WHMCS\Module\Gateway();
        $gatewayName = $invoice->getData("paymentmodule");
        $gateway->load($gatewayName);
        $invoiceModel = $invoice->getModel();
        $ccNumber = $request->get("ccnumber");
        $ccType = getCardTypeByCardNumber($ccNumber);
        $ccExpiryDate = $request->get("ccexpirydate");
        $ccExpiryMonth = $ccExpiryYear = $ccStartMonth = $ccStartYear = "";
        if ($ccExpiryDate) {
            $ccExpiryDate = \WHMCS\Carbon::createFromCcInput($ccExpiryDate);
            $ccExpiryMonth = $ccExpiryDate->month;
            $ccExpiryYear = $ccExpiryDate->year;
        }
        $ccStartDate = $request->get("ccstartdate");
        if ($ccStartDate) {
            $ccStartDate = \WHMCS\Carbon::createFromCcInput($ccStartDate);
            $ccStartMonth = $ccStartDate->month;
            $ccStartYear = $ccStartDate->year;
        }
        $ccIssueNumber = $request->get("ccissuenum");
        $noStore = $request->get("nostore");
        $ccCvv = $request->get("cccvv");
        $ccCvv2 = $request->get("cccvv2");
        if (!$ccCvv) {
            $ccCvv = $ccCvv2;
        }
        $description = $request->get("description");
        $ccDescription = $request->get("ccdescription", "");
        if (!$description) {
            $description = $ccDescription;
        }
        $firstName = $request->get("firstname");
        $lastName = $request->get("lastname");
        $address1 = $request->get("address1");
        $address2 = $request->get("address2");
        $city = $request->get("city");
        $state = $request->get("state");
        $postcode = $request->get("postcode");
        $country = $request->get("country");
        $phoneNumber = \App::formatPostedPhoneNumber();
        $billingContactId = $this->coalesceBillingContactId($request, $client, 0);
        $payMethod = NULL;
        if ($fromOrderForm) {
            $cartCcDetail = safe_unserialize(base64_decode(decrypt(\WHMCS\Session::get("cartccdetail"))));
            $ccNumber = $cartCcDetail[1];
            $ccType = getCardTypeByCardNumber($cartCcDetail[1]);
            list($ccExpiryMonth, $ccExpiryYear, $ccStartMonth, $ccStartYear, $ccIssueNumber, $ccCvv, $noStore, $payMethodId, $description) = $cartCcDetail;
            $orderDetails = \WHMCS\Session::get("orderdetails");
            if (isset($orderDetails["NewPayMethodId"]) && is_numeric($orderDetails["NewPayMethodId"])) {
                $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient($orderDetails["NewPayMethodId"], $client->id);
                if (!$payMethod) {
                    unset($orderDetails["NewPayMethodId"]);
                    \WHMCS\Session::set("orderdetails", $orderDetails);
                }
            }
            if (!$payMethod && ccFormatNumbers($ccNumber)) {
                if ($gateway->isRemote()) {
                    $payMethod = \WHMCS\Payment\PayMethod\Adapter\RemoteCreditCard::factoryPayMethod($client);
                } else {
                    $payMethod = \WHMCS\Payment\PayMethod\Adapter\CreditCard::factoryPayMethod($client, NULL);
                }
            }
            if (!$payMethod && $payMethodId && is_numeric($payMethodId)) {
                $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient($payMethodId, $client->id);
            }
            if (!$payMethod) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Payment Method Selection");
            }
            $this->updatePayMethodContact($payMethod, $client, $billingContactId);
            $invoiceModel->payMethod()->associate($payMethod);
            $invoiceModel->save();
            if (ccFormatNumbers($ccNumber)) {
                $payMethodId = "new";
            }
        }
        $this->userDetailsValidationError = false;
        $params = NULL;
        if (!$fromOrderForm) {
            if ($gateway->functionExists("cc_validation")) {
                $params = [];
                $params["cardtype"] = getCardTypeByCardNumber($ccNumber);
                $params["cardnum"] = ccFormatNumbers($ccNumber);
                $params["cardexp"] = ccFormatDate(ccFormatNumbers($ccExpiryMonth . $ccExpiryYear));
                $params["cardstart"] = ccFormatDate(ccFormatNumbers($ccStartMonth . $ccStartYear));
                $params["cardissuenum"] = ccFormatNumbers($ccIssueNumber);
                $errorMessage = $gateway->call("cc_validation", $params);
                if ($errorMessage) {
                    throw new \WHMCS\Exception\Module\NotServicable($errorMessage);
                }
                $params = NULL;
            } else {
                if ($payMethodId === "new") {
                    $errorMessage .= updateCCDetails("", $ccType, $ccNumber, $ccCvv, $ccExpiryMonth . $ccExpiryYear, $ccStartMonth . $ccStartYear, $ccIssueNumber, "", "", $gateway->getLoadedModule());
                }
                if ($ccCvv2) {
                    $ccCvv = $ccCvv2;
                }
                if (!$ccCvv && $gateway->getWorkflowType() !== \WHMCS\Module\Gateway::WORKFLOW_REMOTE) {
                    $errorMessage .= "<li>" . \Lang::trans("creditcardccvinvalid");
                }
                if ($errorMessage) {
                    throw new \WHMCS\Exception\Module\NotServicable($errorMessage);
                }
            }
            if ($noStore && (!\WHMCS\Config\Setting::getValue("CCAllowCustomerDelete") || $gateway->functionExists("storeremote"))) {
                $noStore = "";
            }
            if ($billingContactId === "new") {
                $errorMessage = checkDetailsareValid($userId, false, false, false, false);
            }
            if ($errorMessage) {
                $this->userDetailsValidationError = true;
                throw new \WHMCS\Exception\Module\NotServicable($errorMessage);
            }
            if ($billingContactId === "new") {
                $array = ["userid" => $userId, "firstname" => $firstName, "lastname" => $lastName, "email" => $client->email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phoneNumber];
                $billingContactId = \WHMCS\Database\Capsule::table("tblcontacts")->insertGetId($array);
            }
            if ($payMethodId === "new") {
                $errorMessage = updateCCDetails($userId, "", $ccNumber, $ccCvv, $ccExpiryMonth . $ccExpiryYear, $ccStartMonth . $ccStartYear, $ccIssueNumber, $noStore, "", $gateway->getLoadedModule(), $payMethod, $description, $billingContactId, $invoiceId);
                if ($errorMessage) {
                    throw new \WHMCS\Exception\Module\NotServicable($errorMessage);
                }
                if (!$payMethod && $noStore && ccFormatNumbers($ccNumber)) {
                    $payMethod = \WHMCS\Payment\PayMethod\Adapter\CreditCard::factoryPayMethod($client, NULL);
                }
            }
            if (!$payMethod && $payMethodId && is_numeric($payMethodId)) {
                $payMethod = \WHMCS\Payment\PayMethod\Model::findForClient($payMethodId, $client->id);
            }
            if (!$payMethod) {
                throw new \WHMCS\Exception\Module\NotServicable("Invalid Payment Method Selection");
            }
            $this->updatePayMethodContact($payMethod, $client, $billingContactId);
            $invoiceModel->payMethod()->associate($payMethod);
            $invoiceModel->save();
        }
        $gatewayName = $payMethod->gateway_name;
        $params = getCCVariables($invoiceId, $gatewayName, $payMethod, $billingContactId);
        if ($payMethodId === "new") {
            $params["cardtype"] = getCardTypeByCardNumber($ccNumber);
            $params["cardnum"] = ccFormatNumbers($ccNumber);
            $params["cardexp"] = ccFormatDate(ccFormatNumbers($ccExpiryMonth . $ccExpiryYear));
            $params["cardstart"] = ccFormatDate(ccFormatNumbers($ccStartMonth . $ccStartYear));
            $params["cardissuenum"] = ccFormatNumbers($ccIssueNumber);
            $params["gatewayid"] = $client->paymentGatewayToken;
            if ($payMethod->payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                $params["gatewayid"] = $payMethod->payment->getRemoteToken();
            }
            $params["billingcontactid"] = $billingContactId;
        }
        return $payMethod;
    }

    protected function processPayment(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\Invoice $invoice, \WHMCS\Payment\PayMethod\Model $payMethod = NULL, $fromOrderForm = false)
    {
        \Auth::requireLoginAndClient(true, "account-index");
        global $params;
        $creditCardPayment = false;
        $invoiceId = $invoice->getID();
        $userId = \Auth::client()->id;
        $ccCvv = "";
        $result = NULL;
        $gateway = \WHMCS\Module\Gateway::factory($invoice->getData("paymentmodule"));
        $payMethodId = $payMethod ? $payMethod->id : NULL;
        $noStore = false;
        if ($payMethod && $payMethod->isCreditCard()) {
            $creditCardPayment = true;
            $ccCvv = $request->get("cccvv");
            $ccCvv2 = $request->get("cccvv2");
            if ($ccCvv2) {
                $ccCvv = $ccCvv2;
            }
            $noStore = (bool) $request->get("nostore");
            if ($fromOrderForm && \WHMCS\Session::get("cartccdetail")) {
                $cartCcDetail = safe_unserialize(base64_decode(decrypt(\WHMCS\Session::get("cartccdetail"))));
                list($ccNumber, $ccCvv) = $cartCcDetail;
                $noStore = (bool) $cartCcDetail[8];
                if (ccFormatNumbers($ccNumber)) {
                    $payMethodId = "new";
                }
            }
            $invoiceModel = $invoice->getModel();
            if ($gateway->functionExists("3dsecure")) {
                $params["cccvv"] = $ccCvv;
                $buttonCode = $gateway->call("3dsecure", $params);
                $buttonCode = str_replace("<form", "<form target=\"3dauth\"", $buttonCode);
                switch ($buttonCode) {
                    case "success":
                    case "declined":
                        $result = $buttonCode;
                        break;
                    default:
                        $view = $this->initView("ClientAreaPageCreditCard3dSecure");
                        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
                        $view->addToBreadCrumb("viewinvoice.php?id=" . $invoiceId, \Lang::trans("invoices"));
                        $view->addToBreadCrumb(routePath("invoice-pay", $invoiceId), \Lang::trans("invoicenumber") . $invoice->getData("invoicenum"));
                        $view->addToBreadCrumb(routePath("invoice-pay", $invoiceId), \Lang::trans("payInvoice"));
                        $view->setTemplate("3dsecure");
                        $view->assign("code", $buttonCode)->assign("width", "400")->assign("height", "500");
                        return $view;
                }
            } else {
                if ($gateway->isTokenised() && $payMethod->isLocalCreditCard()) {
                    $payment = $payMethod->payment;
                    $newRemotePayMethod = \WHMCS\Payment\PayMethod\Adapter\RemoteCreditCard::factoryPayMethod($invoiceModel->client, $invoiceModel->client->billingContact, $payMethod->getDescription());
                    $newRemotePayMethod->setGateway($gateway);
                    updateCCDetails($userId, $payment->getCardType(), $payment->getCardNumber(), $ccCvv, $payment->getExpiryDate()->toCreditCard(), $payment->getStartDate(), $payment->getIssueNumber(), "", "", $invoiceModel->paymentGateway, $newRemotePayMethod);
                    $payMethod->delete();
                    $payMethod = $newRemotePayMethod;
                    $invoiceModel->payMethod()->associate($payMethod);
                    $invoiceModel->save();
                    $params = getCCVariables($invoiceId, $invoiceModel->paymentGateway, $payMethod);
                }
            }
        }
        if (!$result) {
            $result = captureCCPayment($invoiceId, $ccCvv, true, $payMethod);
        }
        if ($gateway->getProcessingType() == \WHMCS\Module\Gateway::PROCESSING_OFFLINE) {
            if ($params["paymentmethod"] == "directdebit") {
                sendAdminNotification("account", "Offline Direct Debit Payment Submitted", "<p>An offline direct debit payment has just been submitted.  Details are below:</p><p>Client ID: " . $userId . "<br />Invoice ID: " . $invoiceId . "</p>");
            } else {
                sendAdminNotification("account", "Offline Credit Card Payment Submitted", "<p>An offline credit card payment has just been submitted." . " Details are below:</p><p>Client ID: " . $userId . "<br />" . "Invoice ID: " . $invoiceId . "</p>");
            }
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/viewinvoice.php?id=" . $invoiceId . "&offlinepaid=true");
        }
        if (is_string($result) && $result == "success" || is_string($result) && $result == "pending" || is_bool($result) && $result) {
            $payment = "paymentsuccess=true";
            if (is_string($result) && $result == "pending") {
                $payment = "paymentinititated=true";
            }
            if ($noStore) {
                $payMethod->delete();
            }
            return new \Laminas\Diactoros\Response\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/viewinvoice.php?id=" . $invoiceId . "&" . $payment);
        }
        if (!$payMethod) {
            $error = "genericPaymentDeclined";
        }
        if (!isset($error)) {
            if ($creditCardPayment) {
                $error = "creditcarddeclined";
            } else {
                $error = "bankPaymentDeclined";
            }
        }
        if ($payMethodId === "new" && $payMethod) {
            $payMethod->delete();
        }
        throw new \WHMCS\Exception\Module\NotServicable("<li>" . \Lang::trans($error));
    }

    protected function checkAccess(\WHMCS\Invoice $invoice)
    {
        if (\Auth::client()->id != $invoice->getData("userid")) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid Access Attempt");
        }
        if ($invoice->getData("status") !== \WHMCS\Billing\Invoice::STATUS_UNPAID) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid Invoice Status for Payment");
        }
    }

    protected function initView($hookFunctionName = "ClientAreaPageBankAccountCheckout")
    {
        $view = new \WHMCS\ClientArea();
        $view->assign("showRemoteInput", NULL);
        $view->assign("hasRemoteInput", NULL);
        $view->assign("billingcontact", NULL);
        $view->setPageTitle(\Lang::trans("ordercheckout"));
        $view->addOutputHookFunction($hookFunctionName);
        $view->setTemplate("invoice-payment");
        return $view;
    }

    protected function coalesceBillingContactId(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\User\Client $client, $default = 0)
    {
        $billingContactId = $default;
        if ($client->billingContactId != 0) {
            $billingContactId = $client->billingContactId;
        }
        if ($request->has("billingcontact")) {
            $billingContactId = $request->get("billingcontact");
        }
        return $billingContactId;
    }

    protected function updatePayMethodContact(\WHMCS\Payment\PayMethod\Model &$payMethod, \WHMCS\User\Client $client, $billingContactId)
    {
        if (empty($billingContactId)) {
            $billingContactId = 0;
        } else {
            $billingContactId = (int) $billingContactId;
        }
        $billingContact = NULL;
        if (0 < $billingContactId) {
            $billingContact = $client->contacts->find($billingContactId);
        } else {
            if (empty($payMethod->contact_id)) {
                $billingContact = $client;
            }
        }
        if ($billingContact) {
            $payMethod->contact()->associate($billingContact);
            $payMethod->save();
        }
    }
}
