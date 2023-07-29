<?php

namespace WHMCS\Admin\Wizard\Steps\ConfigureSsl;

class Contacts
{
    public function getStepContent()
    {
        $langFirstName = \AdminLang::trans("fields.firstname");
        $langLastName = \AdminLang::trans("fields.lastname");
        $langOrgName = \AdminLang::trans("fields.ssl.organizationName");
        $langJobTitle = \AdminLang::trans("fields.ssl.jobTitle");
        $langJobReq = \AdminLang::trans("fields.ssl.titleRequiredForCompany");
        $langEmail = \AdminLang::trans("fields.email");
        $langAddress1 = \AdminLang::trans("fields.address1");
        $langAddress2 = \AdminLang::trans("fields.address2");
        $langCity = \AdminLang::trans("fields.city");
        $langState = \AdminLang::trans("fields.state");
        $langPostcode = \AdminLang::trans("fields.postcode");
        $langCountry = \AdminLang::trans("fields.country");
        $langPhone = \AdminLang::trans("fields.phonenumber");
        $serviceId = \App::getFromRequest("serviceid");
        $addonId = \App::getFromRequest("addonid");
        if ($serviceId) {
            $service = \WHMCS\Service\Service::find($serviceId);
            $client = $service->client;
        } else {
            $addon = \WHMCS\Service\Addon::find($addonId);
            $client = $addon->client;
        }
        $countries = new \WHMCS\Utility\Country();
        $countryList = [];
        foreach ($countries->getCountryNameArray() as $code => $country) {
            $countryList[] = "<option value=\"" . $code . "\"" . ($code == $client->country ? " selected" : "") . ">" . $country . "</option>";
        }
        $countryList = implode($countryList);
        $title = \AdminLang::trans("wizard.ssl.contactTitle");
        $sslAdminDetails = \AdminLang::trans("wizard.ssl.contactDetails");
        return "            <h2>" . $title . "</h2>\n\n            <div class=\"alert alert-info info-alert\">" . $sslAdminDetails . "</div>\n\n            <fieldset class=\"form-horizontal\">\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputFirstName\">" . $langFirstName . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"firstname\" id=\"inputFirstName\" value=\"" . $client->firstName . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputLastName\">" . $langLastName . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"lastname\" id=\"inputLastName\" value=\"" . $client->lastName . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputOrgName\">" . $langOrgName . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"orgname\" id=\"inputOrgName\" value=\"" . $client->companyName . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputJobTitle\">" . $langJobTitle . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"jobtitle\" id=\"inputJobTitle\" value=\"\" />\n                        <p class=\"help-block\">" . $langJobReq . "</p>\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputEmail\">" . $langEmail . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"email\" id=\"inputEmail\" value=\"" . $client->email . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputAddress1\">" . $langAddress1 . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"address1\" id=\"inputAddress1\" value=\"" . $client->address1 . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputAddress2\">" . $langAddress2 . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"address2\" id=\"inputAddress2\" value=\"" . $client->address2 . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputCity\">" . $langCity . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"city\" id=\"inputCity\" value=\"" . $client->city . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputState\">" . $langState . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"state\" id=\"inputState\" value=\"" . $client->state . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputPostcode\">" . $langPostcode . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"text\" class=\"form-control\" name=\"postcode\" id=\"inputPostcode\" value=\"" . $client->postcode . "\" />\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputCountry\">" . $langCountry . "</label>\n                    <div class=\"col-sm-8\">\n                    <select name=\"country\" id=\"inputCountry\" class=\"form-control\">\n                        " . $countryList . "\n                    </select>\n                    </div>\n                </div>\n\n                <div class=\"form-group\">\n                    <label class=\"col-sm-4 control-label\" for=\"inputPhoneNumber\">" . $langPhone . "</label>\n                    <div class=\"col-sm-8\">\n                        <input type=\"tel\" class=\"form-control\" name=\"phonenumber\" id=\"inputPhoneNumber\" value=\"" . $client->phoneNumber . "\" />\n                    </div>\n                </div>";
    }

    public function save($data)
    {
        $firstname = isset($data["firstname"]) ? trim($data["firstname"]) : "";
        $lastname = isset($data["lastname"]) ? trim($data["lastname"]) : "";
        $orgname = isset($data["orgname"]) ? trim($data["orgname"]) : "";
        $jobtitle = isset($data["jobtitle"]) ? trim($data["jobtitle"]) : "";
        $email = isset($data["email"]) ? trim($data["email"]) : "";
        $address1 = isset($data["address1"]) ? trim($data["address1"]) : "";
        $address2 = isset($data["address2"]) ? trim($data["address2"]) : "";
        $city = isset($data["city"]) ? trim($data["city"]) : "";
        $state = isset($data["state"]) ? trim($data["state"]) : "";
        $postcode = isset($data["postcode"]) ? trim($data["postcode"]) : "";
        $country = isset($data["country"]) ? trim($data["country"]) : "";
        $phonenumber = isset($data["phonenumber"]) ? trim($data["phonenumber"]) : "";
        if (!$firstname) {
            throw new \WHMCS\Exception("First name is required");
        }
        if (!$lastname) {
            throw new \WHMCS\Exception("Last name is required");
        }
        if ($orgname && !$jobtitle) {
            throw new \WHMCS\Exception("Job title is required");
        }
        if (!$email) {
            throw new \WHMCS\Exception("Email is required");
        }
        if (!$address1) {
            throw new \WHMCS\Exception("Address 1 is required");
        }
        if (!$city) {
            throw new \WHMCS\Exception("City is required");
        }
        if (!$state) {
            throw new \WHMCS\Exception("State is required");
        }
        if (!$postcode) {
            throw new \WHMCS\Exception("Postcode is required");
        }
        if (!$phonenumber) {
            throw new \WHMCS\Exception("Phone number is required");
        }
        $certConfig = \WHMCS\Session::get("AdminCertConfiguration");
        $certConfig["admin"] = ["firstname" => $firstname, "lastname" => $lastname, "orgname" => $orgname, "jobtitle" => $jobtitle, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber];
        \WHMCS\Session::setAndRelease("AdminCertConfiguration", $certConfig);
    }
}
