<?php

namespace App\Games\Kernel;

class _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F6D6F64756C65732F67617465776179732F7374726970652F6C69622F537472697065436F6E74726F6C6C65722E7068703078376630316332633666303838_
{
    protected $sessionKeyPrefix = "StripeIntentsData";
    protected $sessionKey = NULL;
    protected $data = NULL;

    public function load($invoiceId)
    {
        $sessionKey = $this->sessionKeyPrefix;
        if (!is_null($invoiceId) && 0 < strlen($invoiceId)) {
            $sessionKey = $this->sessionKeyPrefix . $invoiceId;
        }
        $rawData = \WHMCS\Session::get($sessionKey);
        if ($this->isValid($rawData)) {
            $this->data = $rawData;
            $this->sessionKey = $sessionKey;
            return true;
        }
        return false;
    }

    public function isValid($data)
    {
        return is_array($data);
    }

    public function data()
    {
        return $this->data;
    }

    public function delete()
    {
        $this->data = NULL;
        if (!is_null($this->sessionKey)) {
            return \WHMCS\Session::delete($this->sessionKey);
        }
        return false;
    }

    public function clear()
    {
        $intentKeys = array_filter(\WHMCS\Session::keys(), function ($key) {
            return strpos($key, $this->sessionKeyPrefix) === 0;
        });
        foreach ($intentKeys as $key) {
            \WHMCS\Session::delete($key);
        }
        return count($intentKeys);
    }
}
namespace WHMCS\Module\Gateway\Stripe;

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;

class StripeController
{
    public function intent(\WHMCS\Http\Message\ServerRequest $request)
    {
        $token = $request->get("token");
        check_token("WHMCS.default", $token);
        $paymentMethodId = $request->get("payment_method_id");
        if (!function_exists("checkDetailsareValid")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $gateway = new \WHMCS\Module\Gateway();
        if (!$gateway->load("stripe")) {
            return new \WHMCS\Http\Message\JsonResponse(["validation_feedback" => "Module Not Active"]);
        }
        $gatewayParams = $gateway->getParams();
        stripe_start_stripe($gatewayParams);
        $invoiceId = $request->get("invoiceid");
        $stripeCustomer = NULL;
        $client = NULL;
        $method = NULL;
        $billingContact = NULL;
        if ($paymentMethodId) {
            try {
                $method = \Stripe\PaymentMethod::retrieve($paymentMethodId);
                if ($method->customer) {
                    $stripeCustomer = \Stripe\Customer::retrieve($method->customer);
                }
            } catch (\Exception $e) {
            }
        }
        $clientId = NULL;
        if (!$stripeCustomer && $invoiceId) {
            $invoice = \WHMCS\Billing\Invoice::with("client")->find($invoiceId);
            if (!Auth::client() || Auth::client()->id != $invoice->clientId) {
                throw new \InvalidArgumentException("Invalid Access Attempt");
            }
            $client = $invoice->client;
            $clientId = $client->id;
        }
        $errorMessage = NULL;
        if (!$client) {
            $clientId = Auth::client()->id;
        }
        $newExistingOrAccount = $request->get("custtype");
        if (!$clientId || $newExistingOrAccount === "add") {
            if ($newExistingOrAccount === "existing") {
                $loginEmail = $request->get("loginemail");
                $loginPw = \WHMCS\Input\Sanitize::decode($request->get("loginpw"));
                if (!$loginPw) {
                    $loginPw = \WHMCS\Input\Sanitize::decode($request->get("loginpassword"));
                }
                $loginCheck = localAPI("validatelogin", ["email" => $loginEmail, "password2" => $loginPw]);
                if ($loginCheck["result"] === "success") {
                    if ($loginCheck["twoFactorEnabled"] === true) {
                        $response = ["two_factor" => true];
                        return new \WHMCS\Http\Message\JsonResponse($response);
                    }
                    $clientId = (int) $loginCheck["userid"];
                } else {
                    $response = ["validation_feedback" => Lang::trans("loginincorrect")];
                    return new \WHMCS\Http\Message\JsonResponse($response);
                }
            } else {
                if ($newExistingOrAccount === "add" && $clientId) {
                    $clientId = NULL;
                }
                $signup = true;
                $checkClientsProfileUneditiableFields = true;
                $checkTermsOfService = false;
                $emailChecks = CHECKDETAILS_EMAIL_ALL;
                $user = Auth::user();
                if (!is_null($user) && $newExistingOrAccount === "add") {
                    if ((new \WHMCS\Validate())->validate("uniqueemail", "email", "", [$user->id, ""])) {
                        $emailChecks ^= CHECKDETAILS_EMAIL_UNIQUE_USER ^ CHECKDETAILS_EMAIL_ASSOC_CLIENT;
                    }
                    $checkClientsProfileUneditiableFields = false;
                    $checkTermsOfService = true;
                    $signup = NULL;
                }
                $errorMessage .= checkDetailsareValid("", $signup, $emailChecks, false, true, $checkClientsProfileUneditiableFields, false, false, $checkTermsOfService);
                unset($user);
                unset($signup);
                unset($emailChecks);
                unset($checkClientsProfileUneditiableFields);
                unset($checkTermsOfService);
            }
        }
        if ($clientId) {
            if (!$client) {
                $client = \WHMCS\User\Client::find($clientId);
            }
            if (\App::isInRequest("billingcontact")) {
                $billingContactId = \App::getFromRequest("billingcontact");
                if ($billingContactId === "new") {
                    $errorMessage = checkDetailsareValid($clientId, false, false, false, false);
                }
            }
        }
        if ($request->has("custtype")) {
            if (!function_exists("cartValidationOnCheckout")) {
                require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "cartfunctions.php";
            }
            $errorMessage .= cartValidationOnCheckout($clientId, true);
        }
        if ($errorMessage) {
            $response = ["validation_feedback" => $errorMessage, "reloadCaptcha" => (bool) (!\WHMCS\Session::get("CartValidationOnCheckout"))];
            return new \WHMCS\Http\Message\JsonResponse($response);
        }
        if ($client && !$stripeCustomer) {
            $gatewayId = json_encode(stripe_findFirstCustomerToken($client));
            $clientId = $client->id;
            if ($client instanceof \WHMCS\User\Client\Contact) {
                $clientId = $client->clientId;
            }
            if ($client->billingContactId) {
                $billingContact = $client->billingContact;
            }
            if (\App::isInRequest("billingcontact")) {
                $billingContactId = \App::getFromRequest("billingcontact");
                if ($billingContactId === "new") {
                    $billingContact = new \WHMCS\User\Client\Contact();
                    $billingContact->clientId = $clientId;
                    $billingContact->firstName = \App::getFromRequest("firstname");
                    $billingContact->lastName = \App::getFromRequest("lastname");
                    $billingContact->email = $client->email;
                    $billingContact->address1 = \App::getFromRequest("address1");
                    $billingContact->address2 = \App::getFromRequest("address2");
                    $billingContact->city = \App::getFromRequest("city");
                    $billingContact->state = \App::getFromRequest("state");
                    $billingContact->postcode = \App::getFromRequest("postcode");
                    $billingContact->country = \App::getFromRequest("country");
                } else {
                    $billingContact = $client->contacts()->where("id", $billingContactId)->first();
                }
            }
            if ($gatewayId) {
                $jsonCheck = json_decode(\WHMCS\Input\Sanitize::decode($gatewayId), true);
                if (is_array($jsonCheck) && array_key_exists("customer", $jsonCheck)) {
                    $stripeCustomer = \Stripe\Customer::retrieve($jsonCheck["customer"]);
                    if (!$paymentMethodId) {
                        $paymentMethodId = $jsonCheck["method"];
                    }
                } else {
                    if (substr($gatewayId, 0, 3) == "cus") {
                        $stripeCustomer = \Stripe\Customer::retrieve($gatewayId);
                    }
                }
            }
            try {
                if (!$method) {
                    $method = \Stripe\PaymentMethod::retrieve($paymentMethodId);
                }
            } catch (\Exception $e) {
                return new \WHMCS\Http\Message\JsonResponse(["validation_feedback" => $e->getMessage()]);
            }
        }
        if (!$billingContact) {
            $localPayMethodId = \App::getFromRequest("ccinfo");
            if (is_numeric($localPayMethodId)) {
                $payMethod = $client->payMethods()->where("id", $localPayMethodId)->first();
                if ($payMethod) {
                    $billingContact = $payMethod->contact;
                }
            }
        }
        $name = $email = "";
        if (!$stripeCustomer && $client) {
            $stripeCustomer = \Stripe\Customer::create(ApiPayload::customer($client, $client->id));
        } else {
            if (!$stripeCustomer && !$client) {
                $name = trim(sprintf("%s %s", \App::getFromRequest("firstname"), \App::getFromRequest("lastname")));
                $email = \App::getFromRequest("email");
                if (empty($name) || empty($email)) {
                    $response = ["validation_feedback" => "Name and Email are required to pay with this gateway"];
                    return new \WHMCS\Http\Message\JsonResponse($response);
                }
                $stripeCustomer = \Stripe\Customer::create(ApiPayload::customer(\App::self()));
                \WHMCS\Session::set("StripeClientIdRequired", $stripeCustomer->id);
            }
        }
        if (!$method->customer) {
            try {
                $method = $method->attach(["customer" => $stripeCustomer->id]);
                $method->save();
            } catch (\Exception $e) {
                if ($invoiceId) {
                    $by = "Unknown";
                    if (defined("CLIENTAREA")) {
                        $by = "User";
                    } else {
                        if (defined("ADMINAREA")) {
                            $by = "Admin";
                        }
                    }
                    $history = new \WHMCS\Billing\Payment\Transaction\History();
                    $history->gateway = "Stripe";
                    $history->invoiceId = $invoiceId;
                    $history->transactionId = "N/A";
                    $history->remoteStatus = "Declined";
                    $history->description = "Initiated by " . $by . ". Error: " . $e->getMessage();
                    $history->save();
                }
                $response = ["validation_feedback" => $e->getMessage()];
                return new \WHMCS\Http\Message\JsonResponse($response);
            }
        }
        try {
            $methodId = $method->id;
            if (substr($methodId, 0, 4) !== "card") {
                if ($client) {
                    if (!$billingContact) {
                        $billingContact = $client;
                    }
                    $billingContactEmail = $billingContact->email;
                    if (!$billingContactEmail) {
                        $billingContactEmail = $client->email;
                    }
                    $method = \Stripe\PaymentMethod::update($method->id, ["billing_details" => ["email" => $billingContactEmail, "name" => $billingContact->fullName, "address" => ["line1" => ApiPayload::formatValue($billingContact->address1), "line2" => ApiPayload::formatValue($billingContact->address2), "city" => ApiPayload::formatValue($billingContact->city), "state" => self::formatValue($billingContact->state), "country" => ApiPayload::formatValue($billingContact->country), "postal_code" => ApiPayload::formatValue($billingContact->postcode)]], "metadata" => ["id" => $clientId, "fullName" => $client->fullName, "email" => $client->email]]);
                } else {
                    $method = \Stripe\PaymentMethod::update($method->id, ApiPayload::paymentContact(\App::self()));
                }
            }
        } catch (\Exception $e) {
            if ($e instanceof \Stripe\Exception\InvalidRequestException && $e->getStripeCode() == "incorrect_zip") {
                return new \WHMCS\Http\Message\JsonResponse(["warning" => $e->getMessage()]);
            }
            throw $e;
        }
        try {
            $cartData = [];
            if (!Auth::user()) {
                if (!function_exists("calcCartTotals")) {
                    require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "orderfunctions.php";
                }
                if (!$clientId) {
                    $_SESSION["cart"]["user"]["state"] = $request->get("state");
                    $_SESSION["cart"]["user"]["country"] = $request->get("country");
                }
                if (!$clientId && \WHMCS\Billing\Tax\Vat::isTaxEnabled()) {
                    $taxId = $request->get("tax_id");
                    if (!$taxId && \WHMCS\Billing\Tax\Vat::getFieldName() !== "tax_id") {
                        $customFieldId = (int) \WHMCS\Config\Setting::getValue("TaxVatCustomFieldId");
                        $taxId = $request->get("customfield")[$customFieldId];
                    }
                    if (\WHMCS\Config\Setting::getValue("TaxEUTaxExempt") && !empty($taxId)) {
                        $validNumber = \WHMCS\Billing\Tax\Vat::validateNumber($taxId);
                        if ($validNumber && in_array($request->get("country"), array_keys(\WHMCS\Billing\Tax\Vat::EU_COUNTRIES))) {
                            $_SESSION["cart"]["user"]["taxexempt"] = true;
                            if (\WHMCS\Config\Setting::getValue("TaxEUHomeCountryNoExempt") && $request->get("country") == \WHMCS\Config\Setting::getValue("TaxEUHomeCountry")) {
                                $_SESSION["cart"]["user"]["taxexempt"] = false;
                            }
                        }
                    }
                }
                $cartData = calcCartTotals(Auth::client(), false, false);
            }
            $intentResolver = $this->getPaymentIntentHandler();
            if (!$intentResolver->load($invoiceId)) {
                throw new \InvalidArgumentException("Invalid or Missing Payment Information - Please Reload and Try Again");
            }
            $intentsData = $intentResolver->data();
            if (array_key_exists("rawtotal", $cartData)) {
                if (empty($currency)) {
                    $currencyData = \Currency::factoryForClientArea();
                } else {
                    $currencyData = $currency;
                }
                $amount = $cartData["rawtotal"];
                $currencyCode = $currencyData["code"];
                if (isset($gatewayParams["convertto"]) && $gatewayParams["convertto"]) {
                    $currencyCode = \WHMCS\Database\Capsule::table("tblcurrencies")->where("id", "=", (int) $gatewayParams["convertto"])->value("code");
                    $amount = convertCurrency($amount, $currencyData["id"], $gatewayParams["convertto"]);
                }
                $amount = ApiPayload::formatAmountOutbound($amount, $currencyCode);
                $intentsData["amount"] = $amount;
                $intentsData["currency"] = strtolower($currencyCode);
            }
            $intentsData["confirmation_method"] = "automatic";
            $intentsData["capture_method"] = "manual";
            $intentsData["confirm"] = true;
            $intentsData["customer"] = $stripeCustomer->id;
            $intentsData["payment_method"] = $method->id;
            $intentsData["save_payment_method"] = true;
            $intentsData["setup_future_usage"] = "off_session";
            $intent = \Stripe\PaymentIntent::create($intentsData);
        } catch (\Exception $e) {
            if ($invoiceId) {
                $user = "";
                if (defined("CLIENTAREA")) {
                    $user = "Client";
                } else {
                    if (defined("ADMINAREA")) {
                        $user = "Admin";
                    }
                }
                $history = new \WHMCS\Billing\Payment\Transaction\History();
                $history->gateway = "Stripe";
                $history->invoiceId = $invoiceId;
                $history->transactionId = "N/A";
                $history->remoteStatus = "Declined";
                $history->description = "Initiated by " . $user . ". Error: " . $e->getMessage();
                $history->save();
            }
            return new \WHMCS\Http\Message\JsonResponse(["validation_feedback" => $e->getMessage()]);
        }
        $intentResolver->delete();
        unset($intentResolver);
        switch ($intent->status) {
            case "requires_source_action":
            case "requires_action":
                $response = ["requires_action" => true, "success" => false, "token" => $intent->client_secret];
                break;
            case "requires_capture":
            case "succeeded":
                $response = ["success" => true, "requires_action" => false, "token" => $intent->id];
                break;
            default:
                $response = ["validation_feedback" => "Invalid PaymentIntent status"];
                return new \WHMCS\Http\Message\JsonResponse($response);
        }
    }

    public function setupIntent(\WHMCS\Http\Message\ServerRequest $request)
    {
        $token = $request->get("token");
        check_token("WHMCS.default", $token);
        $gateway = new \WHMCS\Module\Gateway();
        if (!$gateway->load("stripe")) {
            return new \WHMCS\Http\Message\JsonResponse(["validation_feedback" => "Module Not Active"]);
        }
        stripe_start_stripe($gateway->getParams());
        $setupIntent = \Stripe\SetupIntent::create();
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "setup_intent" => $setupIntent->client_secret]);
    }

    public function add(\WHMCS\Http\Message\ServerRequest $request)
    {
        $token = $request->get("token");
        check_token("WHMCS.default", $token);
        return $this->addProcess($request, true);
    }

    public function adminAdd(\WHMCS\Http\Message\ServerRequest $request)
    {
        return $this->addProcess($request);
    }

    protected function addProcess(\WHMCS\Http\Message\ServerRequest $request, $sessionUserId = false)
    {
        $paymentMethodId = $request->get("payment_method_id");
        $userId = (int) $request->get("user_id");
        if ($sessionUserId) {
            $userId = Auth::client()->id;
        }
        if (!$userId) {
            $error = "User Id not found in request params";
            if ($sessionUserId) {
                $error = "Login session not found";
            }
            return new \WHMCS\Http\Message\JsonResponse(["validation_feedback" => $error]);
        }
        $gateway = new \WHMCS\Module\Gateway();
        if (!$gateway->load("stripe")) {
            return new \WHMCS\Http\Message\JsonResponse(["validation_feedback" => "Module Not Active"]);
        }
        stripe_start_stripe($gateway->getParams());
        try {
            $client = \WHMCS\User\Client::findOrFail($userId);
            $existingMethod = stripe_findFirstCustomerToken($client);
            $stripeCustomer = NULL;
            $gatewayId = $client->paymentGatewayToken;
            $billingContactId = \App::getFromRequest("billingcontact");
            $billingContact = NULL;
            if ($billingContactId) {
                $billingContact = $client->contacts()->where("id", $billingContactId)->first();
            }
            if (!$billingContact) {
                $billingContact = $client;
            }
            if ($gatewayId) {
                $jsonCheck = json_decode(\WHMCS\Input\Sanitize::decode($gatewayId), true);
                if (is_array($jsonCheck) && array_key_exists("customer", $jsonCheck)) {
                    $stripeCustomer = \Stripe\Customer::retrieve($jsonCheck["customer"]);
                } else {
                    if (substr($gatewayId, 0, 3) == "cus") {
                        $stripeCustomer = \Stripe\Customer::retrieve($gatewayId);
                    }
                }
            }
            if (!$stripeCustomer && $existingMethod && is_array($existingMethod) && array_key_exists("customer", $existingMethod)) {
                $stripeCustomer = \Stripe\Customer::retrieve($existingMethod["customer"]);
            }
            if (!$stripeCustomer) {
                $stripeCustomer = \Stripe\Customer::create(ApiPayload::customer($client, $client->id));
            }
            $method = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            if (!$method->customer) {
                $method->attach(["customer" => $stripeCustomer->id]);
            }
            $billingContactEmail = $billingContact->email;
            if (!$billingContactEmail) {
                $billingContactEmail = $client->email;
            }
            $method = \Stripe\PaymentMethod::update($method->id, ["billing_details" => ["email" => $billingContactEmail, "name" => $billingContact->fullName, "address" => ["line1" => ApiPayload::formatValue($billingContact->address1), "line2" => ApiPayload::formatValue($billingContact->address2), "city" => ApiPayload::formatValue($billingContact->city), "state" => ApiPayload::formatValue($billingContact->state), "country" => ApiPayload::formatValue($billingContact->country), "postal_code" => ApiPayload::formatValue($billingContact->postcode)]], "metadata" => ["id" => $userId, "fullName" => $client->fullName, "email" => $client->email]]);
            $response = ["success" => true, "requires_action" => false, "token" => $method->id];
        } catch (\Exception $e) {
            $response = ["validation_feedback" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    protected function getPaymentIntentHandler()
    {
        _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F6D6F64756C65732F67617465776179732F7374726970652F6C69622F737472697065636F6E74726F6C6C65722E7068703078376630316332633666303838_();
    }
}
