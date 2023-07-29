<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("calcCartTotals")) {
    require ROOTDIR . "/includes/orderfunctions.php";
}
if (!function_exists("checkDetailsareValid")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("saveCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
$clientIp = $whmcs->get_req_var("clientip");
$customFields = $whmcs->get_req_var("customfields");
$skipValidation = $whmcs->get_req_var("skipvalidation");
$noEmail = $whmcs->get_req_var("noemail");
$checkEmail = CHECKDETAILS_EMAIL_ALL;
$owner = NULL;
$ownerUserId = (int) App::getFromRequest("owner_user_id");
if ($ownerUserId) {
    try {
        $owner = WHMCS\User\User::findOrFail($ownerUserId);
        $checkEmail = CHECKDETAILS_EMAIL_UNIQUE_CLIENT;
    } catch (Exception $e) {
        $apiresults = ["result" => "error", "message" => "Invalid Owner User ID"];
        return NULL;
    }
}
$errorMessage = checkDetailsareValid("", false, $checkEmail, true, false);
$currency = (int) $whmcs->get_req_var("currency");
$language = $whmcs->get_req_var("language");
$firstName = $whmcs->get_req_var("firstname");
$lastName = $whmcs->get_req_var("lastname");
$companyName = $whmcs->get_req_var("companyname");
$email = $whmcs->get_req_var("email");
$address1 = $whmcs->get_req_var("address1");
$address2 = $whmcs->get_req_var("address2");
$city = $whmcs->get_req_var("city");
$state = $whmcs->get_req_var("state");
$postcode = $whmcs->get_req_var("postcode");
$country = $whmcs->get_req_var("country");
$phoneNumber = $whmcs->get_req_var("phonenumber");
$taxId = App::getFromRequest("tax_id");
$password2 = WHMCS\Input\Sanitize::decode(trim($whmcs->get_req_var("password2")));
$securityQuestionId = (int) $whmcs->get_req_var("securityqid");
$securityQuestionAnswer = $whmcs->get_req_var("securityqans");
$clientGroupId = $whmcs->get_req_var("groupid");
$notes = $whmcs->get_req_var("notes");
$marketingOptIn = App::isInRequest("marketingoptin") ? (bool) App::getFromRequest("marketingoptin") : (bool) (!WHMCS\Config\Setting::getValue("EmailMarketingRequireOptIn"));
$customFieldsErrors = [];
if (!empty($customFields)) {
    $customFields = safe_unserialize(base64_decode($customFields));
    $validate = new WHMCS\Validate();
    $validate->validateCustomFields("client", "", false, $customFields);
    $customFieldsErrors = $validate->getErrors();
} else {
    $fetchedCustomClientFields = getCustomFields("client", NULL, NULL);
    if (is_array($fetchedCustomClientFields)) {
        foreach ($fetchedCustomClientFields as $fetchedCustomClientField) {
            if ($fetchedCustomClientField["required"] == "*") {
                $customFieldsErrors[] = "You did not provide required custom field value for " . $fetchedCustomClientField["name"];
            }
        }
    }
}
if (($errorMessage || 0 < count($customFieldsErrors)) && !$skipValidation) {
    if ($errorMessage) {
        $errorMessage = explode("<li>", $errorMessage);
        $error = $errorMessage[1];
        $error = strip_tags($error);
    } else {
        $error = implode(", ", $customFieldsErrors);
    }
    $apiresults = ["result" => "error", "message" => $error];
} else {
    if ($errorMessage) {
        $nonUniqueEmailErrors = [Lang::trans("ordererroruserexists"), Lang::trans("clientareaerroremailexists")];
        foreach (explode("<li>", $errorMessage) as $error) {
            $error = strip_tags($error);
            if (!empty($error) && in_array($error, $nonUniqueEmailErrors) !== false) {
                $apiresults = ["result" => "error", "message" => $error];
                return NULL;
            }
        }
        unset($nonUniqueEmailErrors);
    }
    $_SESSION["currency"] = $currency;
    $sendEmail = $noEmail ? false : true;
    $langAtStart = NULL;
    if (isset($_SESSION["Language"])) {
        $langAtStart = $_SESSION["Language"];
    }
    if ($language) {
        $_SESSION["Language"] = $language;
    }
    if (!$owner) {
        try {
            $owner = WHMCS\User\User::createUser($firstName, $lastName, $email, $password2, $language, false, $skipValidation);
        } catch (WHMCS\Exception\User\EmailAlreadyExists $e) {
            $apiresults = ["result" => "error", "message" => "A user already exists with that email address"];
            return NULL;
        } catch (Exception $e) {
            $apiresults = ["result" => "error", "message" => $e->getMessage()];
            return NULL;
        }
        $ownerUserId = $owner->id;
        if ($securityQuestionId) {
            try {
                $owner->setSecurityQuestion($securityQuestionId, $securityQuestionAnswer);
            } catch (Exception $e) {
                $apiresults = ["result" => "error", "message" => $e->getMessage()];
            }
        }
    }
    $client = $owner->createClient($firstName, $lastName, $companyName, $email, $address1, $address2, $city, $state, $postcode, $country, $phoneNumber, $sendEmail, ["notes" => $notes, "groupid" => $clientGroupId, "customfields" => $customFields, "tax_id" => $taxId], "", true, $marketingOptIn, $clientIp, $language);
    $apiresults = ["result" => "success", "clientid" => $client->id, "owner_id" => $ownerUserId];
    $cardType = $whmcs->get_req_var("cardtype");
    if (!$cardType) {
        $cardType = $whmcs->get_req_var("cctype");
    }
    if ($cardType) {
        $apiresults["warning"] = "Credit card related parameters are now deprecated and may be removed in a future version. Use AddPayMethod instead.";
        if (!function_exists("updateCCDetails")) {
            require ROOTDIR . "/includes/ccfunctions.php";
        }
        $cardNumber = $whmcs->get_req_var("cardnum");
        $cardCVV = $whmcs->get_req_var("cvv");
        $cardExpiry = $whmcs->get_req_var("expdate");
        $cardStartDate = $whmcs->get_req_var("startdate");
        $cardIssueNumber = $whmcs->get_req_var("issuenumber");
        updateCCDetails($client->id, $cardType, $cardNumber, $cardCVV, $cardExpiry, $cardStartDate, $cardIssueNumber);
        unset($cardNumber);
        unset($cardCVV);
        unset($cardExpiry);
        unset($cardStartDate);
        unset($cardIssueNumber);
    }
    if (WHMCS\Config\Setting::getValue("TaxEUTaxValidation")) {
        $taxExempt = WHMCS\Billing\Tax\Vat::setTaxExempt($client);
        if ($client->isDirty()) {
            $client->save();
        }
        if ($taxExempt != $additionalData["taxexempt"]) {
            $additionalData["taxexempt"] = $taxExempt;
        }
    }
    if (defined("APICALL")) {
        run_hook("ClientAdd", array_merge(["client_id" => $client->id, "user_id" => $ownerUserId, "userid" => $client->id, "firstname" => $firstName, "lastname" => $lastName, "companyname" => $companyName, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phoneNumber, "tax_id" => $taxId, "password" => $password2], ["notes" => $notes, "groupid" => $clientGroupId], ["customfields" => $customFields]));
    }
    $_SESSION["Language"] = $langAtStart;
}
