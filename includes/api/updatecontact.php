<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$contactid = App::getFromRequest("contactid");
try {
    $contact = WHMCS\User\Client\Contact::with("client")->findOrFail($contactid);
    $subaccount = $contact->isSubAccount;
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Contact ID Not Found"];
    return NULL;
}
$email = App::getFromRequest("email");
$emailPreferences = App::getFromRequest("email_preferences");
if (empty($emailPreferences)) {
    $emailPreferences = [];
}
if (!is_array($emailPreferences)) {
    $apiresults = ["result" => "error", "message" => "Argument email_preferences must be empty or an array."];
} else {
    if (empty($emailPreferences)) {
        foreach (WHMCS\Mail\Emailer::CLIENT_EMAILS as $legacyField) {
            if (App::isInRequest($legacyField . "emails")) {
                $emailPreferences[$legacyField] = App::getFromRequest($legacyField . "emails");
            }
        }
    }
    if (array_key_exists(WHMCS\Mail\Emailer::EMAIL_TYPE_DOMAIN, $emailPreferences)) {
        try {
            $contact->validateEmailPreferences($emailPreferences);
        } catch (WHMCS\Exception\Validation\Required $e) {
            $apiresults = ["result" => "error", "message" => "You must have at least one email address enabled to receive domain related notifications as required by ICANN. To disable domain notifications, please enable domain notifications for the primary account holder or another contact"];
            return NULL;
        } catch (Exception $e) {
            $apiresults = ["result" => "error", "message" => $e->getMessage()];
            return NULL;
        }
    }
    if (App::isInRequest("firstname")) {
        $contact->firstName = App::getFromRequest("firstname");
    }
    if (App::isInRequest("lastname")) {
        $contact->lastName = App::getFromRequest("lastname");
    }
    if (App::isInRequest("companyname")) {
        $contact->companyName = App::getFromRequest("companyname");
    }
    if (App::isInRequest("email")) {
        $contact->email = App::getFromRequest("email");
    }
    if (App::isInRequest("address1")) {
        $contact->address1 = App::getFromRequest("address1");
    }
    if (App::isInRequest("address2")) {
        $contact->address2 = App::getFromRequest("address2");
    }
    if (App::isInRequest("city")) {
        $contact->city = App::getFromRequest("city");
    }
    if (App::isInRequest("state")) {
        $contact->state = App::getFromRequest("state");
    }
    if (App::isInRequest("postcode")) {
        $contact->postcode = App::getFromRequest("postcode");
    }
    if (App::isInRequest("country")) {
        $contact->country = App::getFromRequest("country");
    }
    if (App::isInRequest("phonenumber")) {
        $contact->phoneNumber = App::getFromRequest("phonenumber");
    }
    $contact->setEmailPreferences($emailPreferences);
    if ($contact->isDirty()) {
        $contact->save();
    }
    $apiresults = ["result" => "success", "contactid" => $contactid];
    if ($subaccount || $password2 || $permissions) {
        $apiresults["warning"] = "Sub Accounts are no longer supported. Please use UpdateUser";
    }
}
