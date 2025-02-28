<?php

function globalsignssl_MetaData()
{
    return ["DisplayName" => "GlobalSign SSL Certificates", "APIVersion" => "1.0", "RequiresServer" => false, "AutoGenerateUsernameAndPassword" => false];
}

function globalsignssl_ConfigOptions()
{
    $soap_check_msg = "";
    if (!class_exists("SoapClient")) {
        $soap_check_msg = " This module requires the PHP SOAP extension which is not currently compiled into your PHP build.";
    }
    $configarray = ["Username" => ["Type" => "text", "Size" => "25"], "Password" => ["Type" => "password", "Size" => "25"], "SSL Certificate Type" => ["Type" => "dropdown", "Options" => "AlphaSSL,DomainSSL,OrganizationSSL,ExtendedSSL"], "Base Option" => ["Type" => "dropdown", "Options" => "Standard SSL,Wildcard SSL"], "Validity Period" => ["Type" => "dropdown", "Options" => "1", "Description" => "Years"], "Test Mode" => ["Type" => "yesno"], "" => ["Type" => "na", "Description" => "Don't have a GlobalSign SSL account? Visit <a href=\"http://go.whmcs.com/414/globalsign-ssl-signup\" target=\"_blank\">www.globalsign.com/partners/whmcs/</a> to signup free." . $soap_check_msg]];
    return $configarray;
}

function globalsignssl_CreateAccount($params)
{
    $result = select_query("tblsslorders", "COUNT(*)", ["serviceid" => $params["serviceid"]]);
    $data = mysql_fetch_array($result);
    if ($data[0]) {
        return "An SSL Order already exists for this order";
    }
    $sslorderid = insert_query("tblsslorders", ["userid" => $params["clientsdetails"]["userid"], "serviceid" => $params["serviceid"], "remoteid" => "", "module" => "globalsignssl", "certtype" => $params["configoption3"], "status" => "Awaiting Configuration"]);
    global $CONFIG;
    $sslconfigurationlink = $CONFIG["SystemURL"] . "/configuressl.php?cert=" . md5($sslorderid);
    $sslconfigurationlink = "<a href=\"" . $sslconfigurationlink . "\">" . $sslconfigurationlink . "</a>";
    sendMessage("SSL Certificate Configuration Required", $params["serviceid"], ["ssl_configuration_link" => $sslconfigurationlink]);
    return "success";
}

function globalsignssl_AdminCustomButtonArray()
{
    $buttonarray = ["Cancel" => "cancel", "Resend Configuration Email" => "resend", "Resend Approver Email" => "resendapprover"];
    return $buttonarray;
}

function globalsignssl_cancel($params)
{
    $result = select_query("tblsslorders", "COUNT(*)", ["serviceid" => $params["serviceid"], "status" => "Awaiting Configuration"]);
    $data = mysql_fetch_array($result);
    if (!$data[0]) {
        return "No Incomplete SSL Order exists for this order";
    }
    update_query("tblsslorders", ["status" => "Cancelled"], ["serviceid" => $params["serviceid"]]);
    return "success";
}

function globalsignssl_resend($params)
{
    $result = select_query("tblsslorders", "id", ["serviceid" => $params["serviceid"]]);
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    if (!$id) {
        return "No SSL Order exists for this product";
    }
    global $CONFIG;
    $sslconfigurationlink = $CONFIG["SystemURL"] . "/configuressl.php?cert=" . md5($id);
    $sslconfigurationlink = "<a href=\"" . $sslconfigurationlink . "\">" . $sslconfigurationlink . "</a>";
    sendMessage("SSL Certificate Configuration Required", $params["serviceid"], ["ssl_configuration_link" => $sslconfigurationlink]);
    return "success";
}

function globalsignssl_resendapprover($params)
{
    $result = select_query("tblsslorders", "remoteid", ["serviceid" => $params["serviceid"]]);
    $data = mysql_fetch_array($result);
    $remoteid = $data["remoteid"];
    if (!$remoteid) {
        return "No SSL Order exists for this product";
    }
    $user = $params["configoption1"];
    $pass = $params["configoption2"];
    $prodcode = $params["configoption3"];
    $baseoption = $params["configoption4"];
    $validityperiod = $params["configoption5"];
    $testmode = $params["configoption6"];
    if ($testmode) {
        $wsdlorderurl = "https://test-gcc.globalsign.com/kb/ws/v1/ServerSSLService?wsdl";
        $wsdlqueryurl = "https://test-gcc.globalsign.com/kb/ws/v1/GASService?wsdl";
        $wsdlaccounturl = "https://test-gcc.globalsign.com/kb/ws/v1/AccountService?wsdl";
    } else {
        $wsdlorderurl = "https://system.globalsign.com/kb/ws/v1/ServerSSLService?wsdl";
        $wsdlqueryurl = "https://system.globalsign.com/kb/ws/v1/GASService?wsdl";
        $wsdlaccounturl = "https://system.globalsign.com/kb/ws/v1/AccountService?wsdl";
    }
    $request = [];
    $request["Request"]["OrderRequestHeader"]["AuthToken"]["UserName"] = $user;
    $request["Request"]["OrderRequestHeader"]["AuthToken"]["Password"] = $pass;
    $request["Request"]["OrderID"] = $remoteid;
    $request["Request"]["ResendEmailType"] = "APPROVEREMAIL";
    if (!class_exists("SoapClient")) {
        return "Error: This module requires the PHP SOAP extension which is not currently compiled into your PHP build.";
    }
    $client = new SoapClient($wsdlorderurl);
    $result = $client->ResendEmail($request);
    logModuleCall("globalsignssl", "resendapprover", $request, (array) $result, "", [$user, $pass]);
    $errorcode = $result->Response->OrderResponseHeader->SuccessCode;
    if (0 <= $errorcode) {
        return "success";
    }
    return "Error Code: " . $result->Response->OrderResponseHeader->Errors->Error->ErrorCode . " - " . $result->Response->OrderResponseHeader->Errors->Error->ErrorMessage;
}

function globalsignssl_ClientArea($params)
{
    global $_LANG;
    $result = select_query("tblsslorders", "", ["serviceid" => $params["serviceid"]]);
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    $orderid = $data["orderid"];
    $serviceid = $data["serviceid"];
    $remoteid = $data["remoteid"];
    $module = $data["module"];
    $certtype = $data["certtype"];
    $domain = $data["domain"];
    $completiondate = $data["completiondate"];
    $status = $data["status"];
    if ($id) {
        $status .= " - <a href=\"configuressl.php?cert=" . md5($id) . "\">" . $_LANG["sslconfigurenow"] . "</a>";
        $output = "<div align=\"left\">\n<table width=\"100%\" cellspacing=\"1\" cellpadding=\"0\" class=\"frame\"><tr><td>\n<table width=\"100%\" border=\"0\" cellpadding=\"10\" cellspacing=\"2\">\n<tr><td class=\"fieldarea\">" . $_LANG["sslstatus"] . ":</td><td>" . $status . "</td></tr>\n</table>\n</td></tr></table>\n</div>";
        return $output;
    }
}

function globalsignssl_SSLStepOne($params)
{
    $user = $params["configoption1"];
    $pass = $params["configoption2"];
    $prodcode = $params["configoption3"];
    $baseoption = $params["configoption4"];
    $validityperiod = $params["configoption5"];
    $testmode = $params["configoption6"];
    $values = [];
    if ($prodcode == "OrganizationSSL") {
        $values["additionalfields"]["Organization Information"] = ["orgname" => ["FriendlyName" => "Organization Name", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgaddress" => ["FriendlyName" => "Address 1", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgcity" => ["FriendlyName" => "City", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgstate" => ["FriendlyName" => "State", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgpostcode" => ["FriendlyName" => "Postcode", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgcountry" => ["FriendlyName" => "Country", "Type" => "country", "Required" => true], "orgphone" => ["FriendlyName" => "Phone Number", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true]];
    } else {
        if ($prodcode == "ExtendedSSL") {
            $values["additionalfields"]["Organization Information"] = ["bizcatcode" => ["FriendlyName" => "Business Category Code", "Type" => "dropdown", "Options" => "Private Organization,Government Entity,Business Entity"], "bizname" => ["FriendlyName" => "Business Name", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgaddress" => ["FriendlyName" => "Address 1", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgcity" => ["FriendlyName" => "City", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgstate" => ["FriendlyName" => "State", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgpostcode" => ["FriendlyName" => "Postcode", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgcountry" => ["FriendlyName" => "Country", "Type" => "country", "Required" => true], "orgphone" => ["FriendlyName" => "Phone Number", "Type" => "text", "Size" => "30", "Description" => "", "Required" => true], "orgregnum" => ["FriendlyName" => "Incorporating Agency Reg Number", "Type" => "text", "Size" => "20", "Description" => "As supplied to you by Companies House, Secretary of State, etc...", "Required" => true]];
        }
    }
    return $values;
}

function globalsignssl_SSLStepTwo($params)
{
    $user = $params["configoption1"];
    $pass = $params["configoption2"];
    $prodcode = $params["configoption3"];
    $baseoption = $params["configoption4"];
    $validityperiod = $params["configoption5"];
    $testmode = $params["configoption6"];
    $webservertype = $params["servertype"];
    $csr = $params["csr"];
    $firstname = $params["firstname"];
    $lastname = $params["lastname"];
    $orgname = $params["orgname"];
    $jobtitle = $params["jobtitle"];
    $emailaddress = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"];
    $postcode = $params["postcode"];
    $country = $params["country"];
    $phonenumber = $params["phonenumber"];
    if ($prodcode == "AlphaSSL") {
        $prodcode = "DV_LOW_SHA2";
    } else {
        if ($prodcode == "DomainSSL") {
            $prodcode = "DV_SHA2";
        } else {
            if ($prodcode == "OrganizationSSL") {
                $prodcode = "OV_SHA2";
            } else {
                if ($prodcode == "ExtendedSSL") {
                    $prodcode = "EV_SHA2";
                }
            }
        }
    }
    if ($baseoption == "Wildcard SSL") {
        $baseoption = "wildcard";
    } else {
        $baseoption = "";
    }
    $orderkind = "new";
    if ($params["customfields"]["OrderKind"] == "transfer") {
        $orderkind = "transfer";
    }
    if ($params["configoptions"]["ValidityPeriod"]) {
        $validityperiod = $params["configoptions"]["ValidityPeriod"];
    }
    if ($params["configoptions"]["Years"]) {
        $validityperiod = $params["configoptions"]["Years"];
    }
    if (1 < $validityperiod) {
        return ["error" => "Multi year SSL Certificates are no longer supported. Please contact support to resolve."];
    }
    $validityperiod = $validityperiod * 12;
    if ($testmode) {
        $wsdlorderurl = "https://test-gcc.globalsign.com/kb/ws/v1/ServerSSLService?wsdl";
        $wsdlqueryurl = "https://test-gcc.globalsign.com/kb/ws/v1/GASService?wsdl";
        $wsdlaccounturl = "https://test-gcc.globalsign.com/kb/ws/v1/AccountService?wsdl";
    } else {
        $wsdlorderurl = "https://system.globalsign.com/kb/ws/v1/ServerSSLService?wsdl";
        $wsdlqueryurl = "https://system.globalsign.com/kb/ws/v1/GASService?wsdl";
        $wsdlaccounturl = "https://system.globalsign.com/kb/ws/v1/AccountService?wsdl";
    }
    $request = [];
    $request["Request"]["OrderRequestHeader"]["AuthToken"]["UserName"] = $user;
    $request["Request"]["OrderRequestHeader"]["AuthToken"]["Password"] = $pass;
    $request["Request"]["OrderRequestParameter"]["ProductCode"] = $prodcode;
    $request["Request"]["OrderRequestParameter"]["BaseOption"] = $baseoption;
    $request["Request"]["OrderRequestParameter"]["OrderKind"] = $orderkind;
    $request["Request"]["OrderRequestParameter"]["ValidityPeriod"]["Months"] = $validityperiod;
    $request["Request"]["OrderRequestParameter"]["Licenses"] = "1";
    $request["Request"]["OrderRequestParameter"]["CSR"] = $csr;
    if (!class_exists("SoapClient")) {
        return ["error" => "Error: This module requires the PHP SOAP extension which is not currently compiled into your PHP build."];
    }
    $client = new SoapClient($wsdlqueryurl);
    $result = $client->ValidateOrderParameters($request);
    logModuleCall("globalsignssl", "validateorder", $request, (array) $result, "", [$user, $pass]);
    $errorcode = $result->Response->OrderResponseHeader->SuccessCode;
    if (0 <= $errorcode) {
        $csrdata = $result->Response->ParsedCSR;
        $request = [];
        $request["Request"]["QueryRequestHeader"]["AuthToken"]["UserName"] = $user;
        $request["Request"]["QueryRequestHeader"]["AuthToken"]["Password"] = $pass;
        $request["Request"]["FQDN"] = $csrdata->DomainName;
        if (!class_exists("SoapClient")) {
            return ["error" => "Error: This module requires the PHP SOAP extension which is not currently compiled into your PHP build."];
        }
        $client2 = new SoapClient($wsdlorderurl);
        $result = $client2->GetDVApproverList($request);
        logModuleCall("globalsignssl", "getapprovers", $request, (array) $result, "", [$user, $pass]);
        $errorcode = $result->Response->QueryResponseHeader->SuccessCode;
        if (0 <= $errorcode) {
            $tempapproveremails = $result->Response->Approvers->SearchOrderDetail;
            $approveremails = [];
            foreach ($tempapproveremails as $tempapproveremail) {
                $approveremails[] = $tempapproveremail->ApproverEmail;
            }
            $orderid = $result->Response->OrderID;
            $_SESSION["globalsignsslcert"]["orderid"] = $orderid;
            update_query("tblsslorders", ["remoteid" => $orderid], ["serviceid" => $params["serviceid"]]);
            $values["approveremails"] = $approveremails;
            $values["displaydata"]["Domain"] = $csrdata->DomainName ?? "";
            $values["displaydata"]["Validity Period"] = $validityperiod . " Months";
            $values["displaydata"]["Organization"] = $csrdata->Organization ?? "";
            $values["displaydata"]["Organization Unit"] = $csrdata->OrganizationUnit ?? "";
            $values["displaydata"]["Email"] = $csrdata->Email ?? "";
            $values["displaydata"]["Locality"] = $csrdata->Locality ?? "";
            $values["displaydata"]["State"] = $csrdata->State ?? "";
            $values["displaydata"]["Country"] = $csrdata->Country ?? "";
            $values["approvalmethods"] = ["email"];
            $params["model"]->serviceProperties->save(["domain" => $values["displaydata"]["Domain"]]);
            return $values;
        } else {
            $values["error"] = $result->Response->QueryResponseHeader->Errors->Error->ErrorCode . " - " . $result->Response->QueryResponseHeader->Errors->Error->ErrorMessage;
            return $values;
        }
    } else {
        $values["error"] = "Error Code: " . $result->Response->OrderResponseHeader->Errors->Error->ErrorCode . " - " . $result->Response->OrderResponseHeader->Errors->Error->ErrorMessage;
        return $values;
    }
}

function globalsignssl_SSLStepThree($params)
{
    $user = $params["configoption1"];
    $pass = $params["configoption2"];
    $prodcode = $params["configoption3"];
    $baseoption = $params["configoption4"];
    $validityperiod = $params["configoption5"];
    $testmode = $params["configoption6"];
    $webservertype = $params["servertype"];
    $csr = $params["csr"];
    $firstname = $params["firstname"];
    $lastname = $params["lastname"];
    $orgname = $params["orgname"];
    $jobtitle = $params["jobtitle"];
    $emailaddress = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"];
    $postcode = $params["postcode"];
    $country = $params["country"];
    $phonenumber = $params["phonenumber"];
    $orderid = $params["remoteid"];
    $approveremail = $params["approveremail"];
    if ($prodcode == "AlphaSSL") {
        $prodcode = "DV_LOW_SHA2";
    } else {
        if ($prodcode == "DomainSSL") {
            $prodcode = "DV_SHA2";
        } else {
            if ($prodcode == "OrganizationSSL") {
                $prodcode = "OV_SHA2";
            } else {
                if ($prodcode == "ExtendedSSL") {
                    $prodcode = "EV_SHA2";
                }
            }
        }
    }
    if ($baseoption == "Wildcard SSL") {
        $baseoption = "wildcard";
    } else {
        $baseoption = "";
    }
    $orderkind = "new";
    if ($params["customfields"]["OrderKind"] == "transfer") {
        $orderkind = "transfer";
    }
    if ($params["configoptions"]["ValidityPeriod"]) {
        $validityperiod = $params["configoptions"]["ValidityPeriod"];
    }
    if ($params["configoptions"]["Years"]) {
        $validityperiod = $params["configoptions"]["Years"];
    }
    $validityperiod = $validityperiod * 12;
    if ($testmode) {
        $wsdlorderurl = "https://test-gcc.globalsign.com/kb/ws/v1/ServerSSLService?wsdl";
        $wsdlqueryurl = "https://test-gcc.globalsign.com/kb/ws/v1/GASService?wsdl";
        $wsdlaccounturl = "https://test-gcc.globalsign.com/kb/ws/v1/AccountService?wsdl";
    } else {
        $wsdlorderurl = "https://system.globalsign.com/kb/ws/v1/ServerSSLService?wsdl";
        $wsdlqueryurl = "https://system.globalsign.com/kb/ws/v1/GASService?wsdl";
        $wsdlaccounturl = "https://system.globalsign.com/kb/ws/v1/AccountService?wsdl";
    }
    $request = [];
    $request["Request"]["OrderRequestHeader"]["AuthToken"]["UserName"] = $user;
    $request["Request"]["OrderRequestHeader"]["AuthToken"]["Password"] = $pass;
    $request["Request"]["OrderRequestParameter"]["ProductCode"] = $prodcode;
    $request["Request"]["OrderRequestParameter"]["BaseOption"] = $baseoption;
    $request["Request"]["OrderRequestParameter"]["OrderKind"] = $orderkind;
    $request["Request"]["OrderRequestParameter"]["ValidityPeriod"]["Months"] = $validityperiod;
    $request["Request"]["OrderRequestParameter"]["Licenses"] = "1";
    $request["Request"]["OrderRequestParameter"]["CSR"] = $csr;
    $request["Request"]["ApproverEmail"] = $approveremail;
    $request["Request"]["ContactInfo"]["FirstName"] = $firstname;
    $request["Request"]["ContactInfo"]["LastName"] = $lastname;
    $request["Request"]["ContactInfo"]["Phone"] = $phonenumber;
    $request["Request"]["ContactInfo"]["Email"] = $emailaddress;
    if ($prodcode == "OV_SHA2") {
        $request["Request"]["OrganizationInfo"]["OrganizationName"] = $params["fields"]["orgname"];
        $request["Request"]["OrganizationInfo"]["OrganizationAddress"]["AddressLine1"] = $params["fields"]["orgaddress"];
        $request["Request"]["OrganizationInfo"]["OrganizationAddress"]["City"] = $params["fields"]["orgcity"];
        $request["Request"]["OrganizationInfo"]["OrganizationAddress"]["Region"] = $params["fields"]["orgstate"];
        $request["Request"]["OrganizationInfo"]["OrganizationAddress"]["PostalCode"] = $params["fields"]["orgpostcode"];
        $request["Request"]["OrganizationInfo"]["OrganizationAddress"]["Country"] = $params["fields"]["orgcountry"];
        $request["Request"]["OrganizationInfo"]["OrganizationAddress"]["Phone"] = $params["fields"]["orgphone"];
    } else {
        if ($prodcode == "EV_SHA2") {
            $bizcatcode = $params["fields"]["bizcatcode"];
            if ($bizcatcode == "Business Entity") {
                $bizcatcode = "BE";
            } else {
                if ($bizcatcode == "Government Entity") {
                    $bizcatcode = "GE";
                } else {
                    $bizcatcode = "PO";
                }
            }
            $request["Request"]["OrganizationInfoEV"]["BusinessAssumedName"] = $params["fields"]["bizname"];
            $request["Request"]["OrganizationInfoEV"]["BusinessCategoryCode"] = $bizcatcode;
            $request["Request"]["OrganizationInfoEV"]["OrganizationAddress"]["AddressLine1"] = $params["fields"]["orgaddress"];
            $request["Request"]["OrganizationInfoEV"]["OrganizationAddress"]["City"] = $params["fields"]["orgcity"];
            $request["Request"]["OrganizationInfoEV"]["OrganizationAddress"]["Region"] = $params["fields"]["orgstate"];
            $request["Request"]["OrganizationInfoEV"]["OrganizationAddress"]["PostalCode"] = $params["fields"]["orgpostcode"];
            $request["Request"]["OrganizationInfoEV"]["OrganizationAddress"]["Country"] = $params["fields"]["orgcountry"];
            $request["Request"]["OrganizationInfoEV"]["OrganizationAddress"]["Phone"] = $params["fields"]["orgphone"];
            $request["Request"]["RequestorInfo"]["FirstName"] = $firstname;
            $request["Request"]["RequestorInfo"]["LastName"] = $lastname;
            $request["Request"]["RequestorInfo"]["OrganizationName"] = $orgname;
            $request["Request"]["RequestorInfo"]["Email"] = $emailaddress;
            $request["Request"]["RequestorInfo"]["Phone"] = $phonenumber;
            $request["Request"]["ApproverInfo"]["FirstName"] = $firstname;
            $request["Request"]["ApproverInfo"]["LastName"] = $lastname;
            $request["Request"]["ApproverInfo"]["OrganizationName"] = $orgname;
            $request["Request"]["ApproverInfo"]["Email"] = $emailaddress;
            $request["Request"]["ApproverInfo"]["Phone"] = $phonenumber;
            $request["Request"]["AuthorizedSignerInfo"]["FirstName"] = $firstname;
            $request["Request"]["AuthorizedSignerInfo"]["LastName"] = $lastname;
            $request["Request"]["AuthorizedSignerInfo"]["Email"] = $emailaddress;
            $request["Request"]["AuthorizedSignerInfo"]["Phone"] = $phonenumber;
            $request["Request"]["JurisdictionInfo"]["Country"] = $country;
            $request["Request"]["JurisdictionInfo"]["StateOrProvince"] = $state;
            $request["Request"]["JurisdictionInfo"]["Locality"] = $city;
            $request["Request"]["JurisdictionInfo"]["IncorporatingAgencyRegistrationNumber"] = $params["fields"]["orgregnum"];
        } else {
            $request["Request"]["OrderID"] = $orderid;
        }
    }
    if (!class_exists("SoapClient")) {
        return ["error" => "Error: This module requires the PHP SOAP extension which is not currently compiled into your PHP build."];
    }
    $client = new SoapClient($wsdlorderurl);
    if ($prodcode == "DV_LOW_SHA2" || $prodcode == "DV_SHA2") {
        $result = $client->DVOrder($request);
    } else {
        if ($prodcode == "OV_SHA2") {
            $result = $client->OVOrder($request);
        } else {
            if ($prodcode == "EV_SHA2") {
                $result = $client->EVOrder($request);
            }
        }
    }
    logModuleCall("globalsignssl", "order", $request, (array) $result, "", [$user, $pass]);
    $errorcode = $result->Response->OrderResponseHeader->SuccessCode;
    if (0 <= $errorcode) {
        $orderid = $result->Response->OrderID;
    } else {
        if ($result->Response->OrderResponseHeader->Errors->Error->ErrorCode) {
            $values["error"] = $result->Response->OrderResponseHeader->Errors->Error->ErrorCode . " - " . $result->Response->OrderResponseHeader->Errors->Error->ErrorMessage;
        } else {
            if ($result->Response->OrderResponseHeader->Errors->Error[0]->ErrorCode) {
                $values["error"] = $result->Response->OrderResponseHeader->Errors->Error[0]->ErrorCode . " - " . $result->Response->OrderResponseHeader->Errors->Error[0]->ErrorMessage;
            } else {
                $values["error"] = "An Unknown Error Occurred. Please contact support.";
            }
        }
    }
    return $values;
}

function globalsignssl_CustomActions($CustomActionCollection, $params)
{
    $sslCertificate = WHMCS\Service\Ssl::where("serviceid", $params["serviceid"])->first();
    $customActionCollection = new WHMCS\Module\Server\CustomActionCollection();
    $customActionCollection->add(WHMCS\Module\Server\CustomAction::factory("globalsignssl", "sslconfigurenow", function ($sslCertificate) {
        return ["success" => true, "redirectTo" => "configuressl.php?cert=" . md5($sslCertificate->id)];
    }, [$sslCertificate], [], !is_null($sslCertificate) && $sslCertificate->status === WHMCS\Service\Ssl::STATUS_AWAITING_CONFIGURATION));
    return $customActionCollection;
}
