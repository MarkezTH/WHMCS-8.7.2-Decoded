<?php

function resellerclub_MetaData()
{
    return ["DisplayName" => "ResellerClub", "APIVersion" => "1.1", "NonLinearRegistrationPricing" => true];
}

function resellerclub_GetConfigArray()
{
    return ["Description" => ["Type" => "System", "Value" => "Don't have a ResellerClub Account yet? Get one here: <a href=\"http://go.whmcs.com/86/resellerclub\" target=\"_blank\">www.whmcs.com/partners/resellerclub</a>"], "ResellerID" => ["Type" => "text", "Size" => "20", "Description" => "You can get this from the LogicBoxes Control Panel in Settings > Personal Information > Primary Profile"], "APIKey" => ["Type" => "password", "Size" => "20", "Description" => "Your API Key. You can get this from the LogicBoxes Control Panel in Settings -> API"], "DesignatedAgent" => ["FriendlyName" => "Designated Agent", "Type" => "yesno", "Description" => "Check to act as Designated Agent for all contact changes. Ensure you understand your role and responsibilities before checking this option."], "TestMode" => ["Type" => "yesno"]];
}

function resellerclub_config_validate($params)
{
    if (!$params["ResellerID"]) {
        throw new WHMCS\Exception\Module\InvalidConfiguration("Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure.");
    }
    if (!$params["APIKey"]) {
        throw new WHMCS\Exception\Module\InvalidConfiguration("Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure.");
    }
    if (!is_numeric($params["ResellerID"])) {
        throw new WHMCS\Exception\Module\InvalidConfiguration("You have entered an invalid Reseller ID.");
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["type"] = "resellermasteragreement";
    $result = resellerclub_SendCommand("legal-agreements", "commons", $postfields, $params, "GET");
    if (isset($result["response"])) {
        $result = $result["response"];
    }
    if (strtoupper((string) ($result["status"] ?? NULL)) == "ERROR") {
        $error = $result["message"];
        if (!$error) {
            $error = $result["error"];
        }
        $curlErrorNo = CURLE_COULDNT_CONNECT;
        if (substr($error, 0, 14) == "CURL Error: " . $curlErrorNo . " ") {
            throw new WHMCS\Exception\Module\GeneralError($error);
        }
        throw new WHMCS\Exception\Module\InvalidConfiguration($error);
    }
}

function resellerclub_GetNameservers($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "NsDetails";
    $result = resellerclub_SendCommand("details", "domains", $postfields, $params, "GET");
    if (strtoupper($result["status"] ?? "") === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $values = [];
    for ($x = 1; $x <= 5; $x++) {
        $values["ns" . $x] = $result["ns" . $x] ?? NULL;
    }
    return $values;
}

function resellerclub_SaveNameservers($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "NsDetails";
    $nameserver1 = $params["ns1"];
    $nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
    $nameserver4 = $params["ns4"];
    $nameserver5 = $params["ns5"];
    $nslist = $nameserver1 . "&ns=" . $nameserver2;
    if ($nameserver3) {
        $nslist .= "&ns=" . $nameserver3;
    }
    if ($nameserver4) {
        $nslist .= "&ns=" . $nameserver4;
    }
    if ($nameserver5) {
        $nslist .= "&ns=" . $nameserver5;
    }
    $postfields["ns"] = (string) $nslist;
    $result = resellerclub_SendCommand("modify-ns", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) == "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    return ["success" => true];
}

function resellerclub_GetRegistrarLock($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $lockstatus = "unlocked";
    $result = resellerclub_SendCommand("locks", "domains", $postfields, $params, "GET");
    if ($result["transferlock"] === true) {
        $lockstatus = "locked";
    }
    return $lockstatus;
}

function resellerclub_SaveRegistrarLock($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    if ($params["lockenabled"] === "locked") {
        resellerclub_SendCommand("enable-theft-protection", "domains", $postfields, $params, "POST");
    } else {
        resellerclub_SendCommand("disable-theft-protection", "domains", $postfields, $params, "POST");
    }
    return ["success" => true];
}

function resellerclub_isCanonIndividual($countryContactType, $legalType)
{
    $caNonIndividual = false;
    if ($countryContactType === "CaContact") {
        $legal = strtolower($legalType);
        $caIndividual = ["canadian citizen", "permanent resident of canada", "aboriginal peoples", "legal representative of a canadian citizen"];
        if (!in_array($legal, $caIndividual)) {
            $caNonIndividual = true;
        }
    }
    return $caNonIndividual;
}

function resellerclub_RegisterDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $isPremium = $params["premiumEnabled"] && $params["premiumCost"];
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["username"] = resellerclub_getClientEmail($params["userid"]);
    $tld = $params["domainObj"]->getPunycodeTopLevel();
    $result = resellerclub_SendCommand("details", "customers", $postfields, $params, "GET");
    unset($postfields);
    if (strtoupper($result["response"]["status"] ?? "") == "ERROR") {
        if (!$result["response"]["message"]) {
            $result["response"]["message"] = $result["response"]["error"];
        }
        return ["error" => $result["response"]["message"]];
    }
    if (strtoupper($result["status"] ?? "") == "ERROR") {
        $customerid = resellerclub_addCustomer($params);
    } else {
        $customerid = $result["customerid"];
    }
    if (!$customerid) {
        return ["error" => "Error obtaining customer id"];
    }
    if (is_array($customerid)) {
        return $customerid;
    }
    $postfields["name"] = $params["firstname"] . " " . $params["lastname"];
    $contacttype = resellerclub_ContactType($params);
    $canonindv = resellerclub_iscanonindividual($contacttype, $params["additionalfields"]["Legal Type"] ?? "");
    $contacts = resellerclub_addContact($params, $customerid, $contacttype, $canonindv);
    if (!$contacts) {
        return ["error" => "Error obtaining contact id"];
    }
    if (is_array($contacts) && !empty($contacts["error"])) {
        return $contacts;
    }
    $contactfields = resellerclub_ContactTLDSpecificFields($params);
    if (count($contactfields) && $contactfields["product-key"]) {
        $postfields["auth-userid"] = $params["ResellerID"];
        $postfields["api-key"] = $params["APIKey"];
        $postfields["customer-id"] = $customerid;
        $postfields["contact-id"] = $contacts["Registrant"];
        $postfields = array_merge($postfields, $contactfields);
        $result = resellerclub_SendCommand("set-details", "contacts", $postfields, $params, "POST");
    }
    unset($postfields);
    if ($params["domainObj"]->getLastTLDSegment() === "coop") {
        $sponsorid = resellerclub_addCOOPSponsor($params, $customerid);
        if (!$sponsorid) {
            return ["error" => "Unable to add/obtain Sponsor ID"];
        }
    }
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["customer-id"] = $customerid;
    if ($params["is_idn"]) {
        $idnLanguage = $params["idnLanguage"];
        if (!$idnLanguage) {
            return ["error" => "IDN Language Code Missing"];
        }
        $postfields["attr-name1"] = "idnLanguageCode";
        try {
            $postfields["attr-value1"] = resellerclub_getIdnLanguage($tld, $idnLanguage);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"], true);
    $nameserver1 = $params["ns1"];
    $nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
    $nameserver4 = $params["ns4"];
    $nameserver5 = $params["ns5"];
    $nslist = $nameserver1 . "&ns=" . $nameserver2;
    if ($nameserver3) {
        $nslist .= "&ns=" . $nameserver3;
    }
    if ($nameserver4) {
        $nslist .= "&ns=" . $nameserver4;
    }
    if ($nameserver5) {
        $nslist .= "&ns=" . $nameserver5;
    }
    $postfields["ns"] = (string) $nslist;
    $postfields["years"] = $params["regperiod"];
    $postfields["reg-contact-id"] = $contacts["Registrant"];
    $postfields["admin-contact-id"] = $contacts["Admin"] ?: $contacts["Registrant"];
    $postfields["tech-contact-id"] = $contacts["Tech"] ?: $contacts["Registrant"];
    $postfields["billing-contact-id"] = $contacts["Billing"] ?: $contacts["Registrant"];
    $postfields["invoice-option"] = "NoInvoice";
    $postfields["purchase-privacy"] = resellerclub_boolToString($params["idprotection"]);
    $postfields = array_merge($postfields, resellerclub_DomainTLDSpecificFields($params, $contacts["Registrant"], $isPremium));
    if ($params["domainObj"]->getLastTLDSegment() === "au" && is_numeric($postfields["attr-value2"]) && !resellerclub_validateABN($postfields["attr-value2"])) {
        return ["error" => "Invalid ABN"];
    }
    $result = resellerclub_SendCommand("register", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    if ($params["domainObj"]->getLastTLDSegment() === "xxx" && $params["additionalfields"]["Membership Token/ID"]) {
        unset($postfields);
        $postfields["auth-userid"] = $params["ResellerID"];
        $postfields["api-key"] = $params["APIKey"];
        $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
        $orderid = resellerclub_getOrderID($postfields, $params);
        unset($postfields);
        if (is_numeric($orderid)) {
            $postfields["auth-userid"] = $params["ResellerID"];
            $postfields["api-key"] = $params["APIKey"];
            $postfields["order-id"] = $orderid;
            $postfields["association-id"] = $params["additionalfields"]["Membership Token/ID"];
            $result = resellerclub_SendCommand("association-details", "domains/dotxxx", $postfields, $params, "POST");
        }
    }
    return ["success" => "success"];
}

function resellerclub_TransferDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $isPremium = $params["premiumEnabled"] && $params["premiumCost"];
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $transfersecret = $params["transfersecret"];
    $postfields["username"] = resellerclub_getClientEmail($params["userid"]);
    $result = resellerclub_SendCommand("details", "customers", $postfields, $params, "GET");
    unset($postfields);
    if (strtoupper($result["response"]["status"]) === "ERROR") {
        if (!$result["response"]["message"]) {
            $result["response"]["message"] = $result["response"]["error"];
        }
        return ["error" => $result["response"]["message"]];
    }
    if (strtoupper($result["status"]) === "ERROR") {
        $customerid = resellerclub_addCustomer($params);
    } else {
        $customerid = $result["customerid"];
    }
    if (!$customerid) {
        return ["error" => "Error obtaining customer id"];
    }
    if (is_array($customerid)) {
        return $customerid;
    }
    $contacttype = resellerclub_ContactType($params);
    $canonindv = resellerclub_iscanonindividual($contacttype, $params["additionalfields"]["Legal Type"]);
    $contacts = resellerclub_addContact($params, $customerid, $contacttype, $canonindv);
    if (!$contacts) {
        return ["error" => "Error obtaining contact id"];
    }
    if (is_array($contacts) && $contacts["error"]) {
        return $contacts;
    }
    $contactfields = resellerclub_ContactTLDSpecificFields($params);
    if (count($contactfields)) {
        $postfields["auth-userid"] = $params["ResellerID"];
        $postfields["api-key"] = $params["APIKey"];
        $postfields["customer-id"] = $customerid;
        $postfields["contact-id"] = $contacts["Registrant"];
        $postfields = array_merge($postfields, $contactfields);
        $result = resellerclub_SendCommand("set-details", "contacts", $postfields, $params, "POST");
    }
    unset($postfields);
    if ($params["domainObj"]->getLastTLDSegment() === "coop") {
        $sponsorid = resellerclub_addCOOPSponsor($params, $customerid);
        if (!$sponsorid) {
            return ["error" => "Unable to add/obtain Sponsor ID"];
        }
    }
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["customer-id"] = $customerid;
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $postfields["years"] = $params["regperiod"];
    if ($transfersecret) {
        $postfields["auth-code"] = $transfersecret;
    }
    $postfields["reg-contact-id"] = $contacts["Registrant"];
    $postfields["admin-contact-id"] = $contacts["Admin"] ?: $contacts["Registrant"];
    $postfields["tech-contact-id"] = $contacts["Tech"] ?: $contacts["Registrant"];
    $postfields["billing-contact-id"] = $contacts["Billing"] ?: $contacts["Registrant"];
    $postfields["invoice-option"] = "NoInvoice";
    $postfields["purchase-privacy"] = resellerclub_boolToString($params["idprotection"]);
    if ($params["domainObj"]->getLastTLDSegment() !== "au") {
        $postfields = array_merge($postfields, resellerclub_DomainTLDSpecificFields($params, $contacts["Registrant"], $isPremium));
    } else {
        if ($isPremium) {
            $postfields["attr-name1"] = "premium";
            $postfields["attr-value1"] = "true";
        }
    }
    $result = resellerclub_SendCommand("transfer", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) == "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    return ["success" => "success"];
}

function resellerclub_RenewDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $isPremium = $params["premiumEnabled"] && $params["premiumCost"];
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "OrderDetails";
    $result = resellerclub_SendCommand("details", "domains", $postfields, $params, "GET");
    $expiry = $result["endtime"];
    if (!$expiry) {
        return ["error" => "Unable to obtain Domain Expiry Date from Registrar"];
    }
    unset($postfields);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["invoice-option"] = "NoInvoice";
    if ($params["isInRedemptionGracePeriod"]) {
        $result = resellerclub_SendCommand("restore", "domains", $postfields, $params, "POST");
    } else {
        $regperiod = $params["regperiod"];
        $postfields["years"] = $regperiod;
        $postfields["exp-date"] = $expiry;
        $postfields["purchase-privacy"] = resellerclub_boolToString($params["idprotection"]);
        if ($isPremium) {
            $postfields["attr-name1"] = "premium";
            $postfields["attr-value1"] = "true";
        }
        $result = resellerclub_SendCommand("renew", "domains", $postfields, $params, "POST");
    }
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["error"])) {
        return ["error" => "Renewal order placed. " . substr($result["error"], 0, -1) . " if / when sufficient funds are available in the reseller account."];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    if ($params["isInRedemptionGracePeriod"] && $params["idprotection"]) {
        resellerclub_SendCommand("purchase-privacy", "domains", $postfields, $params, "POST");
    }
    return ["success" => "success"];
}

function resellerclub_GetContactDetails($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if (!$params["ResellerID"]) {
        return ["error" => "Missing Reseller ID. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    if (!$params["APIKey"]) {
        return ["error" => "Missing API Key. Please navigate to Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars to configure."];
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "ContactIds";
    $result = resellerclub_SendCommand("details", "domains", $postfields, $params, "GET");
    if (strtoupper($result["status"]) == "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    $contacts = [];
    if ($result["registrantcontactid"] != -1) {
        $contacts["Registrant"] = $result["registrantcontactid"];
    }
    if ($result["admincontactid"] != -1) {
        $contacts["Admin"] = $result["admincontactid"];
    }
    if ($result["techcontactid"] != -1) {
        $contacts["Tech"] = $result["techcontactid"];
    }
    if ($result["billingcontactid"] != -1) {
        $contacts["Billing"] = $result["billingcontactid"];
    }
    unset($postfields);
    $tempValues = $values = [];
    foreach ($contacts as $contactType => $contactId) {
        if (!array_key_exists($contactId, $tempValues)) {
            $postFields = [];
            $postFields["auth-userid"] = $params["ResellerID"];
            $postFields["api-key"] = $params["APIKey"];
            $postFields["contact-id"] = $contactId;
            $result = resellerclub_SendCommand("details", "contacts", $postFields, $params, "GET");
            if (strtoupper($result["status"]) === "ERROR") {
                if (!$result["message"]) {
                    $result["message"] = $result["error"];
                }
                return ["error" => $result["message"]];
            }
            if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
                return ["error" => $result["actionstatusdesc"]];
            }
            $tempValues[$contactId] = ["Full Name" => $result["name"], "Email" => $result["emailaddr"], "Company Name" => $result["company"], "Address 1" => $result["address1"], "Address 2" => $result["address2"], "City" => $result["city"], "State" => $result["state"], "Postcode" => $result["zip"], "Country" => $result["country"], "Phone Number" => "+" . $result["telnocc"] . $result["telno"]];
            unset($postFields);
        }
    }
    foreach ($contacts as $contactType => $contactId) {
        $values[$contactType] = $tempValues[$contactId];
    }
    return $values;
}

function resellerclub_SaveContactDetails($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if ($params["domainObj"]->getLastTLDSegment() == "uk") {
        return ["error" => "It is not possible to change the registration on a UK domain"];
    }
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderId = resellerclub_getOrderID($postFields, $params);
    if (!is_numeric($orderId)) {
        return ["error" => $orderId];
    }
    unset($postFields);
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["order-id"] = $orderId;
    $postFields["options"] = ["ContactIds", "OrderDetails"];
    $result = resellerclub_SendCommand("details", "domains", $postFields, $params, "GET");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $existingContactDetails = resellerclub_getcontactdetails($params);
    $fields = ["Full Name", "Company Name", "Email", "Address 1", "Address 2", "City", "State", "Postcode", "Country", "Phone Number", "Phone Country Code"];
    $originalContactIds = $contacts = ["Registrant" => $result["registrantcontactid"], "Admin" => $result["admincontactid"], "Tech" => $result["techcontactid"], "Billing" => $result["billingcontactid"]];
    $changed = false;
    $registrantFieldsChanged = false;
    foreach (["Registrant", "Admin", "Tech", "Billing"] as $contactType) {
        if ($contacts[$contactType] > 0) {
            if (empty($params["contactdetails"][$contactType]["Phone Country Code"])) {
                $params["contactdetails"][$contactType]["Phone Country Code"] = (new WHMCS\Utility\Country())->getCallingCode($params["contactdetails"][$contactType]["Country"]);
            }
            $params["contactdetails"][$contactType]["Phone Number"] = str_replace(["+" . $params["contactdetails"][$contactType]["Phone Country Code"] . ".", "+" . $params["contactdetails"][$contactType]["Phone Country Code"], $params["contactdetails"][$contactType]["Phone Country Code"] . "."], "", $params["contactdetails"][$contactType]["Phone Number"]);
            $params["contactdetails"][$contactType]["Phone Number"] = preg_replace("/[^0-9]/", "", $params["contactdetails"][$contactType]["Phone Number"]);
            foreach ($fields as $field) {
                if ($params["contactdetails"][$contactType][$field] != $existingContactDetails[$contactType][$field]) {
                    $changed = true;
                    if (!$registrantFieldsChanged && $contactType == "Registrant" && in_array($field, ["Full Name", "Company Name", "Email"])) {
                        $registrantFieldsChanged = true;
                    }
                }
            }
            if ($changed) {
                $postFields = [];
                $postFields["domainObj"] = $params["domainObj"];
                $postFields["ResellerID"] = $params["ResellerID"];
                $postFields["APIKey"] = $params["APIKey"];
                if (!$params["contactdetails"][$contactType]["Company Name"]) {
                    $params["contactdetails"][$contactType]["Company Name"] = "N/A";
                }
                $postFields["fullName"] = $params["contactdetails"][$contactType]["Full Name"];
                $postFields["companyname"] = $params["contactdetails"][$contactType]["Company Name"];
                $postFields["email"] = $params["contactdetails"][$contactType]["Email"];
                $postFields["address1"] = $params["contactdetails"][$contactType]["Address 1"];
                $postFields["address2"] = $params["contactdetails"][$contactType]["Address 2"];
                $postFields["city"] = $params["contactdetails"][$contactType]["City"];
                $postFields["state"] = $params["contactdetails"][$contactType]["State"];
                $postFields["fullstate"] = $params["contactdetails"][$contactType]["State"];
                $postFields["postcode"] = $params["contactdetails"][$contactType]["Postcode"];
                $postFields["country"] = $params["contactdetails"][$contactType]["Country"];
                $countryCode = $params["contactdetails"][$contactType]["Phone Country Code"];
                $phoneNumber = $params["contactdetails"][$contactType]["Phone Number"];
                if (preg_match("/^1([\\d]{3})\$/", $countryCode, $matches)) {
                    $countryCode = 1;
                    $phoneNumber = $matches[1] . $phoneNumber;
                }
                $postFields["phone-cc"] = $countryCode;
                $postFields["phonenumber"] = $phoneNumber;
                $countryContactType = resellerclub_ContactType($params);
                $individualCheck = false;
                if ($countryContactType == "CaContact" && in_array($contactType, ["Admin", "Tech"])) {
                    $postFields["additionalfields"]["Legal Type"] = "Canadian Citizen";
                    $individualCheck = false;
                } else {
                    if ($countryContactType == "CaContact") {
                        $individualCheck = true;
                    }
                }
                if ($countryContactType == "EsContact") {
                    $postFields["additionalfields"] = $params["additionalfields"];
                }
                $newContactId = resellerclub_addContact($postFields, $result["customerid"], $countryContactType, $individualCheck ? resellerclub_iscanonindividual($countryContactType, $params["additionalfields"]["Legal Type"]) : false, true);
                if (!$newContactId) {
                    return ["error" => "Error creating new contact id"];
                }
                if (is_array($newContactId) && $newContactId["error"]) {
                    return $newContactId;
                }
                if (is_array($newContactId)) {
                    $newContactId = $newContactId["contactid"];
                }
                $contacts[$contactType] = $newContactId;
            }
        }
    }
    $values = ["success" => true];
    unset($postFields);
    if ($changed) {
        $postFields = [];
        $postFields["auth-userid"] = $params["ResellerID"];
        $postFields["api-key"] = $params["APIKey"];
        $postFields["order-id"] = $orderId;
        $postFields["reg-contact-id"] = $contacts["Registrant"];
        $postFields["admin-contact-id"] = $contacts["Admin"];
        $postFields["billing-contact-id"] = $contacts["Billing"];
        $postFields["tech-contact-id"] = $contacts["Tech"];
        if (array_key_exists("DesignatedAgent", $params) && $params["DesignatedAgent"] && $registrantFieldsChanged) {
            $postFields["designated-agent"] = true;
        } else {
            if (array_key_exists("irtpOptOut", $params) && $registrantFieldsChanged) {
                $postFields["sixty-day-lock-optout"] = resellerclub_boolToString($params["irtpOptOut"]);
                $values["pending"] = true;
            }
        }
        if ($params["domainObj"]->getLastTLDSegment() == "es") {
            $postFields["reg-contact-id"] = $originalContactIds["Registrant"];
        }
        $result = resellerclub_SendCommand("modify-contact", "domains", $postFields, $params, "POST");
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
    }
    return $values;
}

function resellerclub_GetEPPCode($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "OrderDetails";
    $result = resellerclub_SendCommand("details", "domains", $postfields, $params, "GET");
    if (strtoupper($result["status"] ?? "") === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $values["eppcode"] = $result["domsecret"];
    return $values;
}

function resellerclub_RegisterNameserver($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["cns"] = $params["nameserver"];
    $postfields["ip"] = $params["ipaddress"];
    $result = resellerclub_SendCommand("add-cns", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    return [];
}

function resellerclub_ModifyNameserver($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["cns"] = $params["nameserver"];
    $postfields["old-ip"] = $params["currentipaddress"];
    $postfields["new-ip"] = $params["newipaddress"];
    $result = resellerclub_SendCommand("modify-cns-ip", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    return [];
}

function resellerclub_DeleteNameserver($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["cns"] = $params["nameserver"];
    $postfields["ip"] = gethostbyname($params["nameserver"] . "." . $postfields["domain-name"]);
    $result = resellerclub_SendCommand("delete-cns-ip", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    return [];
}

function resellerclub_RequestDelete($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("delete", "domains", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    return [];
}

function resellerclub_GetDNS($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("activate", "dns", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $postfields["no-of-records"] = "50";
    $typelist = ["A", "MX", "CNAME", "TXT", "AAAA"];
    $hostrecords = [];
    foreach ($typelist as $type) {
        $pageNumber = 0;
        $numTotalRecords = 0;
        $postfields["type"] = $type;
        $maxPagesToRequest = 4;
        $postfields["page-no"] = ++$pageNumber;
        $result = resellerclub_SendCommand("search-records", "dns/manage", $postfields, $params, "GET");
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
        $numResultRecords = (int) $result["recsonpage"];
        $numTotalRecords += $numResultRecords;
        if (0 < $numResultRecords) {
            foreach ($result as $entry => $value) {
                if (is_array($value)) {
                    $recid = $entry;
                    $host = $value["host"];
                    $address = $value["value"];
                    if ($type === "MX") {
                        $priority = $value["priority"];
                    } else {
                        $priority = "";
                    }
                    if ($host && $address) {
                        $hostrecords[] = ["hostname" => $host, "type" => (string) $type, "address" => $address, "priority" => (string) $priority, "recid" => $recid];
                    }
                }
            }
        }
        if (!(0 < $numResultRecords && $numTotalRecords < $result["recsindb"] && 0 < --$maxPagesToRequest)) {
        }
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $testmode = $params["TestMode"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("details", "domainforward", $postfields, $params, "GET");
    if (!$result["status"] && $result["forward"]) {
        $host = "";
        $address = "";
        $recid = "";
        $hostrecords[] = ["hostname" => "@", "type" => "URL", "address" => htmlentities($result["forward"])];
    }
    return $hostrecords;
}

function resellerclub_SaveDNS($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $errormsgs = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $hostrecords = resellerclub_getdns($params);
    $newrecords = $params["dnsrecords"];
    foreach ($newrecords as $num => $newvalues) {
        $oldvalues = $hostrecords[$num];
        $oldhostname = $oldvalues["hostname"];
        $oldtype = $oldvalues["type"];
        $oldaddress = $oldvalues["address"];
        $oldpriority = $oldvalues["priority"];
        $newhostname = $newvalues["hostname"];
        $newtype = $newvalues["type"];
        $newaddress = $newvalues["address"];
        $newpriority = $newvalues["priority"];
        if ($newpriority === "N/A") {
            $newpriority = "";
        }
        if (!$newhostname || !$newaddress) {
            if ($oldhostname && $oldaddress) {
                if ($oldtype !== "URL" && $oldtype !== "FRAME") {
                    switch ($oldtype) {
                        case "A":
                            $ltype = "ipv4";
                            break;
                        case "AAAA":
                            $ltype = "ipv6";
                            break;
                        default:
                            $ltype = strtolower($oldtype);
                            $postfields["host"] = $oldhostname;
                            $postfields["value"] = $oldaddress;
                            $result = resellerclub_SendCommand("delete-" . $ltype . "-record", "dns/manage", $postfields, $params, "POST");
                    }
                } else {
                    $orderid = resellerclub_getOrderID($postfields, $params);
                    $postfields["order-id"] = $orderid;
                    $postfields["url-masking"] = "false";
                    $postfields["sub-domain-forwarding"] = "false";
                    $postfields["path-forwarding"] = "false";
                    $postfields["forward-to"] = "";
                    $result = resellerclub_SendCommand("manage", "domainforward", $postfields, $params, "POST");
                }
            }
        } else {
            if ($oldhostname !== $newhostname || $oldtype !== $newtype || $oldaddress !== $newaddress || $oldtype === "MX" && $oldpriority !== $newpriority) {
                $postfields["host"] = $newhostname;
                $ltype = strtolower($newtype);
                if ($ltype === "a") {
                    $ltype = "ipv4";
                }
                if ($ltype === "aaaa") {
                    $ltype = "ipv6";
                }
                if ($ltype === "mx") {
                    $postfields["priority"] = $newpriority;
                }
                if ($ltype === "url" || $ltype === "frame") {
                    $orderid = resellerclub_getOrderID($postfields, $params);
                    $postfields["order-id"] = $orderid;
                    $result = resellerclub_SendCommand("activate", "domainforward", $postfields, $params, "POST");
                    $postfields["url-masking"] = "true";
                    $postfields["sub-domain-forwarding"] = "true";
                    $postfields["path-forwarding"] = "true";
                    $postfields["forward-to"] = WHMCS\Input\Sanitize::decode($newaddress);
                    $result = resellerclub_SendCommand("manage", "domainforward", $postfields, $params, "POST");
                } else {
                    if (in_array($ltype, ["ipv4", "ipv6", "cname", "mx", "ns", "txt", "srv", "soa"])) {
                        if (!$oldhostname && !$oldaddress) {
                            $postfields["value"] = $newaddress;
                            $result = resellerclub_SendCommand("add-" . $ltype . "-record", "dns/manage", $postfields, $params, "POST");
                        } else {
                            $postfields["current-value"] = WHMCS\Input\Sanitize::decode($oldaddress);
                            $postfields["new-value"] = WHMCS\Input\Sanitize::decode($newaddress);
                            $result = resellerclub_SendCommand("update-" . $ltype . "-record", "dns/manage", $postfields, $params, "POST");
                        }
                    }
                }
                $error = false;
                if ($result["status"] === "Failed" || $result["status"] === "ERROR") {
                    if (!$result["msg"]) {
                        $result["msg"] = $result["message"];
                    }
                    $errormsgs[] = $newtype . "|" . $newhostname . "|" . $newaddress . " - " . $result["msg"];
                }
            }
        }
    }
    if (!empty($errormsgs)) {
        return ["error" => implode("<br />", $errormsgs)];
    }
    return [];
}

function resellerclub_GetEmailForwarding($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("is-ownership-verified", "mail/domain", $postfields, $params, "GET");
    if ($result["response"]["isOwnershipVerified"] !== "true") {
        unset($postfields);
        $postfields["auth-userid"] = $params["ResellerID"];
        $postfields["api-key"] = $params["APIKey"];
        $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
        $postfields["value"] = "@";
        $postfields["type"] = "MX";
        $postfields["host"] = "mx1.mailhostbox.com";
        $postfields["priority"] = "100";
        $result = resellerclub_SendCommand("add-mx-record", "dns/manage", $postfields, $params, "POST");
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
        $postfields["host"] = "mx2.mailhostbox.com";
        $result = resellerclub_SendCommand("add-mx-record", "dns/manage", $postfields, $params, "POST");
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("activate", "mail", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $postfields["account-types"] = "forward_only";
    $result = resellerclub_SendCommand("search", "mail/users", $postfields, $params, "GET");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $values = [];
    foreach ($result["response"]["users"] as $entry => $value) {
        $email = explode("@", $value["emailAddress"]);
        $values[$entry]["prefix"] = $email[0];
        $values[$entry]["forwardto"] = $value["adminForwards"];
    }
    return $values;
}

function resellerclub_SaveEmailForwarding($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("activate", "mail", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $postfields["account-types"] = "forward_only";
    foreach ($params["prefix"] as $key => $value) {
        $email = $params["prefix"][$key] . "@" . $params["sld"] . "." . $params["tld"];
        $postfields["email"] = $email;
        $forwardto = $params["forwardto"][$key];
        $result = resellerclub_SendCommand("search", "mail/users", $postfields, $params, "GET");
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
        if ($result["response"]["message"] === "No Records found") {
            unset($postfields);
            $postfields["auth-userid"] = $params["ResellerID"];
            $postfields["api-key"] = $params["APIKey"];
            $postfields["order-id"] = $orderid;
            $postfields["email"] = $email;
            $postfields["forwards"] = $forwardto;
            $result2 = resellerclub_SendCommand("add-forward-only-account", "mail/user", $postfields, $params, "POST");
        } else {
            foreach ($result["response"]["users"] as $entry => $values) {
                unset($postfields);
                $postfields["auth-userid"] = $params["ResellerID"];
                $postfields["api-key"] = $params["APIKey"];
                $postfields["order-id"] = $orderid;
                $postfields["email"] = $email;
                if (!$forwardto) {
                    $postfields["forwards"] = $values["adminForwards"];
                    $result2 = resellerclub_SendCommand("delete", "mail/user", $postfields, $params, "POST");
                } else {
                    $existingforwards = explode(",", $values["adminForwards"]);
                    $addforwards = explode(",", $forwardto);
                    $forwards = $removeforwards = "";
                    foreach ($addforwards as $key => $value) {
                        if (!in_array($value, $existingforwards)) {
                            $forwards = $value . ",";
                        }
                    }
                    if ($forwards) {
                        $forwards = substr($forwards, 0, -1);
                        $postfields["forwards"] = $forwards;
                        $result2 = resellerclub_SendCommand("add-admin-forwards", "mail/user", $postfields, $params, "POST");
                    }
                    foreach ($existingforwards as $key => $value) {
                        if (!in_array($value, $addforwards)) {
                            $removeforwards = $value . ",";
                        }
                    }
                    if ($removeforwards) {
                        $postfields["forwards"] = $removeforwards;
                        $result2 = resellerclub_SendCommand("delete-admin-forwards", "mail/user", $postfields, $params, "POST");
                    }
                }
                if (strtoupper($result2["status"]) === "ERROR") {
                    if (!$result2["message"]) {
                        $result2["message"] = $result2["error"];
                    }
                    return ["error" => $result2["message"]];
                }
            }
        }
    }
}

function resellerclub_ReleaseDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $transfertag = $params["transfertag"];
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["new-tag"] = $transfertag;
    $result = resellerclub_SendCommand("release", "domains/uk", $postfields, $params, "POST");
    if (strtoupper($result["status"]) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
}

function resellerclub_IDProtectToggle($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    if ($params["protectenable"]) {
        $postfields = [];
        $postfields["auth-userid"] = $params["ResellerID"];
        $postfields["api-key"] = $params["APIKey"];
        $postfields["order-id"] = $orderid;
        $postfields["options"] = "OrderDetails";
        $privacyProtectEndTime = 0;
        $result = resellerclub_SendCommand("details", "domains", $postfields, $params, "GET");
        if ($result["privacyprotectedallowed"] === true) {
            $privacyProtectEndTime = $result["privacyprotectendtime"];
        }
        if (0 < $privacyProtectEndTime) {
            $postfields["protect-privacy"] = "true";
            $postfields["reason"] = "Customer Request";
            $action = "modify-privacy-protection";
            $idprotect = "0";
        } else {
            $postfields["invoice-option"] = "NoInvoice";
            $action = "purchase-privacy";
            $idprotect = "1";
        }
    } else {
        $postfields["protect-privacy"] = "false";
        $postfields["reason"] = "Customer Request";
        $action = "modify-privacy-protection";
        $postfields["order-id"] = $orderid;
        $idprotect = "0";
    }
    $result = resellerclub_SendCommand($action, "domains", $postfields, $params, "POST");
    if (strtolower($result["status"]) === "error") {
        $returnMsg = $result["message"];
        if (!$returnMsg) {
            $returnMsg = $result["error"];
        }
        if (!$returnMsg) {
            $returnMsg = "An unknown error occurred";
        }
        return ["error" => $returnMsg];
    }
    update_query("tbldomains", ["idprotection" => $idprotect], ["id" => $params["domainid"]]);
}

function resellerclub_AdminCustomButtonArray()
{
    $buttonarray = [];
    $params = get_query_vals("tbldomains", "", ["id" => $_REQUEST["id"] ?? NULL]);
    if (is_array($params) && $params["type"] == "Transfer" && $params["status"] === "Pending Transfer") {
        $buttonarray["Cancel Domain Transfer"] = "canceldomaintransfer";
    }
    return $buttonarray;
}

function resellerclub_canceldomaintransfer($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = $values = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getOrderID($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $result = resellerclub_SendCommand("cancel-transfer", "domains", $postfields, $params, "POST");
    if ($result["status"] === "Success") {
        $values["message"] = "Successfully cancelled the domain transfer";
    } else {
        $values["error"] = $result["message"];
    }
    return $values;
}

function resellerclub_SendCommand($command, $type, $postfields, $params, $method, $jsonDecodeResult = false)
{
    $error = function ($message) {
        return ["response" => ["status" => "ERROR", "message" => $message]];
    };
    $whiteListMessage = function () {
        $ip = WHMCS\Environment\WebServer::getExternalCommunicationIp();
        $ip2 = coalesce($_SERVER["SERVER_ADDR"] ?? NULL, $_SERVER["LOCAL_ADDR"] ?? NULL);
        if ($ip2) {
            $ip .= ", " . $ip2;
        }
        $href = "https://docs.whmcs.com/ResellerClub#IP_Registration";
        return "You must authorize your IP address to use this API." . " IP address(es) detected: " . $ip . "." . " <a href=\"" . $href . "\" class=\"autoLinked\">Learn More</a>";
    };
    $params = injectDomainObjectIfNecessary($params);
    if (!empty($params["TestMode"])) {
        $url = "https://test.httpapi.com/api/" . $type . "/" . $command . ".json";
    } else {
        $url = "https://httpapi.com/api/" . $type . "/" . $command . ".json";
    }
    if ($command === "available") {
        $url = "https://domaincheck.httpapi.com/api/" . $type . "/" . $command . ".json";
    }
    $curlOptions = [];
    $callDataForLog = $curlPostData = $postfields;
    $postFieldQuery = "";
    if ($method === "GET") {
        $queryParams = "";
        foreach ($curlPostData as $field => $data) {
            if (is_array($data)) {
                foreach ($data as $subData) {
                    $queryParams .= "&" . build_query_string([$field => $subData], PHP_QUERY_RFC3986);
                }
            } else {
                $queryParams .= "&" . build_query_string([$field => $data], PHP_QUERY_RFC3986);
            }
        }
        if ($queryParams) {
            $url .= "?" . ltrim($queryParams, "&");
        }
        unset($queryParams);
        $callDataForLog["url"] = $url;
    } else {
        $isEsTld = $params["domainObj"]->getLastTLDSegment() === "es";
        foreach ($curlPostData as $field => $data) {
            if ($field === "ns") {
                $postFieldQuery .= "&" . build_query_string([$field => $data], NULL);
            } else {
                if ($isEsTld && !$data) {
                    if ($field === "attr-value2") {
                        $data = 0;
                    } else {
                        if ($field === "attr-value3") {
                            $data = $params["additionalfields"]["ID Form Number"];
                        }
                    }
                }
                $postFieldQuery .= "&" . build_query_string([$field => $data], PHP_QUERY_RFC3986);
            }
        }
        $postFieldQuery = ltrim($postFieldQuery, "&");
        $callDataForLog["posteddata"] = $postFieldQuery;
    }
    $ch = curlCall($url, $postFieldQuery, $curlOptions, true);
    $data = curl_exec($ch);
    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorNumber = curl_errno($ch);
    if ($errorNumber !== 0) {
        $errorMessage = curl_error($ch);
        switch ($errorNumber) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_SSL_CONNECT_ERROR:
                $errorMessage = $whiteListMessage();
                break;
            default:
                $result = $error("CURL Error: " . $errorNumber . " - " . $errorMessage);
        }
    } else {
        if ($httpStatusCode == 403) {
            $result = $error($whiteListMessage());
        } else {
            if (!$jsonDecodeResult && is_numeric($data)) {
                $result = $data;
            } else {
                $result = json_decode($data, true);
            }
        }
    }
    curl_close($ch);
    logModuleCall("logicboxes", $type . "/" . $command, $callDataForLog, $data, $result, [$params["ResellerID"], $params["APIKey"]]);
    return $result;
}

function resellerclub_getOrderID($postfields, $params)
{
    $params = injectDomainObjectIfNecessary($params);
    $domain = $postfields["domain-name"];
    if (isset($GLOBALS["logicboxesorderids"][$domain])) {
        $result = $GLOBALS["logicboxesorderids"][$domain];
    } else {
        $result = resellerclub_sendcommand("orderid", "domains", $postfields, $params, "GET", true);
        $GLOBALS["logicboxesorderids"][$domain] = $result;
    }
    if (is_array($result)) {
        if (strtoupper($result["response"]["status"] ?? "") === "ERROR") {
            return $result["response"]["message"];
        }
        if (strtoupper($result["status"]) === "ERROR") {
            return $result["message"];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return $result["actionstatusdesc"];
        }
    }
    $orderid = $result;
    if (!$orderid || is_array($orderid)) {
        return "Unable to obtain Order-ID";
    }
    return $orderid;
}

function resellerclub_genLBRandomPW()
{
    $lowercase = 4;
    $uppercase = 4;
    $numbers = 4;
    $symbols = 3;
    return (new WHMCS\Utility\Random())->string($lowercase, $uppercase, $numbers, $symbols);
}

function resellerclub_xml2array($contents, $get_attributes = 1, $priority = "tag")
{
    $type = $tag = "";
    $level = 0;
    $parser = xml_parser_create("");
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values) {
        return NULL;
    }
    $xml_array = [];
    $parents = [];
    $opened_tags = [];
    $arr = [];
    $current =& $xml_array;
    $repeated_tag_index = [];
    foreach ($xml_values as $data) {
        unset($attributes);
        unset($value);
        extract($data);
        $result = [];
        $attributes_data = [];
        if (isset($value)) {
            if ($priority === "tag") {
                $result = $value;
            } else {
                $result["value"] = $value;
            }
        }
        if (isset($attributes) && $get_attributes) {
            foreach ($attributes as $attr => $val) {
                if ($priority === "tag") {
                    $attributes_data[$attr] = $val;
                } else {
                    $result["attr"][$attr] = $val;
                }
            }
        }
        if ($type === "open") {
            $parent[$level - 1] =& $current;
            if (!is_array($current) || !in_array($tag, array_keys($current))) {
                $current[$tag] = $result;
                if ($attributes_data) {
                    $current[$tag . "_attr"] = $attributes_data;
                }
                $repeated_tag_index[$tag . "_" . $level] = 1;
                $current =& $current[$tag];
            } else {
                if (isset($current[$tag][0])) {
                    $current[$tag][$repeated_tag_index[$tag . "_" . $level]] = $result;
                    $repeated_tag_index[$tag . "_" . $level]++;
                } else {
                    $current[$tag] = [$current[$tag], $result];
                    $repeated_tag_index[$tag . "_" . $level] = 2;
                    if (isset($current[$tag . "_attr"])) {
                        $current[$tag]["0_attr"] = $current[$tag . "_attr"];
                        unset($current[$tag . "_attr"]);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . "_" . $level] - 1;
                $current =& $current[$tag][$last_item_index];
            }
        } else {
            if ($type === "complete") {
                if (!isset($current[$tag])) {
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag . "_" . $level] = 1;
                    if ($priority === "tag" && $attributes_data) {
                        $current[$tag . "_attr"] = $attributes_data;
                    }
                } else {
                    if (isset($current[$tag][0]) && is_array($current[$tag])) {
                        $current[$tag][$repeated_tag_index[$tag . "_" . $level]] = $result;
                        if ($priority === "tag" && $get_attributes && $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag . "_" . $level] . "_attr"] = $attributes_data;
                        }
                        $repeated_tag_index[$tag . "_" . $level]++;
                    } else {
                        $current[$tag] = [$current[$tag], $result];
                        $repeated_tag_index[$tag . "_" . $level] = 1;
                        if ($priority === "tag" && $get_attributes) {
                            if (isset($current[$tag . "_attr"])) {
                                $current[$tag]["0_attr"] = $current[$tag . "_attr"];
                                unset($current[$tag . "_attr"]);
                            }
                            if ($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag . "_" . $level] . "_attr"] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag . "_" . $level]++;
                    }
                }
            } else {
                if ($type === "close") {
                    $current =& $parent[$level - 1];
                }
            }
        }
    }
    return $xml_array;
}

function resellerclub_ContactTLDSpecificFields($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    if ($params["domainObj"]->getLastTLDSegment() === "us") {
        $purpose = $params["additionalfields"]["Application Purpose"];
        $category = $params["additionalfields"]["Nexus Category"];
        if ($purpose == "Business use for profit") {
            $purpose = "P1";
        } else {
            if ($purpose === "Non-profit business" || $purpose === "Club" || $purpose === "Association" || $purpose === "Religious Organization") {
                $purpose = "P2";
            } else {
                if ($purpose === "Personal Use") {
                    $purpose = "P3";
                } else {
                    if ($purpose === "Educational purposes") {
                        $purpose = "P4";
                    } else {
                        if ($purpose === "Government purposes") {
                            $purpose = "P5";
                        }
                    }
                }
            }
        }
        $postfields["attr-name1"] = "purpose";
        $postfields["attr-value1"] = (string) $purpose;
        $postfields["attr-name2"] = "category";
        $postfields["attr-value2"] = (string) $category;
        $postfields["product-key"] = "domus";
    } else {
        if ($params["domainObj"]->getLastTLDSegment() === "uk") {
            if ($params["additionalfields"]["Registrant Name"]) {
                $postfields["name"] = $params["additionalfields"]["Registrant Name"];
            }
        } else {
            if ($params["domainObj"]->getLastTLDSegment() === "ca") {
                if ($params["additionalfields"]["Legal Type"] === "Corporation") {
                    $legaltype = "CCO";
                } else {
                    if ($params["additionalfields"]["Legal Type"] === "Canadian Citizen") {
                        $legaltype = "CCT";
                    } else {
                        if ($params["additionalfields"]["Legal Type"] === "Permanent Resident of Canada") {
                            $legaltype = "RES";
                        } else {
                            if ($params["additionalfields"]["Legal Type"] === "Government") {
                                $legaltype = "GOV";
                            } else {
                                if ($params["additionalfields"]["Legal Type"] === "Canadian Educational Institution") {
                                    $legaltype = "EDU";
                                } else {
                                    if ($params["additionalfields"]["Legal Type"] === "Canadian Unincorporated Association") {
                                        $legaltype = "ASS";
                                    } else {
                                        if ($params["additionalfields"]["Legal Type"] === "Canadian Hospital") {
                                            $legaltype = "HOP";
                                        } else {
                                            if ($params["additionalfields"]["Legal Type"] === "Partnership Registered in Canada") {
                                                $legaltype = "PRT";
                                            } else {
                                                if ($params["additionalfields"]["Legal Type"] === "Trade-mark registered in Canada") {
                                                    $legaltype = "TDM";
                                                } else {
                                                    $legaltype = "CCO";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $postfields["attr-name1"] = "CPR";
                $postfields["attr-value1"] = (string) $legaltype;
                $postfields["attr-name2"] = "AgreementVersion";
                $postfields["attr-value2"] = "2.0";
                $postfields["attr-name3"] = "AgreementValue";
                $postfields["attr-value3"] = "y";
                $postfields["product-key"] = "dotca";
            } else {
                if ($params["domainObj"]->getLastTLDSegment() === "es") {
                    $legalType = $params["additionalfields"]["Legal Form"];
                    if (!$legalType) {
                        $legalType = "1";
                    }
                    if ($legalType === "1") {
                        $postfields["company"] = "";
                    }
                    $idtype = $params["additionalfields"]["ID Form Type"];
                    if ($idtype === "Other Identification") {
                        $idtype = 0;
                    } else {
                        if ($idtype === "Tax Identification Number" || $idtype === "Tax Identification Code") {
                            $idtype = 1;
                        } else {
                            if ($idtype === "Foreigner Identification Number") {
                                $idtype = 3;
                            }
                        }
                    }
                    $idnumber = $params["additionalfields"]["ID Form Number"];
                    $postfields["attr-name1"] = "es_form_juridica";
                    $postfields["attr-value1"] = $legalType;
                    $postfields["attr-name2"] = "es_tipo_identificacion";
                    $postfields["attr-value2"] = $idtype;
                    $postfields["attr-name3"] = "es_identificacion";
                    $postfields["attr-value3"] = $idnumber;
                    $postfields["product-key"] = "dotes";
                } else {
                    if ($params["domainObj"]->getLastTLDSegment() === "asia") {
                        $postfields["attr-name1"] = "locality";
                        $postfields["attr-value1"] = $params["country"];
                        $postfields["attr-name2"] = "legalentitytype";
                        $postfields["attr-value2"] = $params["additionalfields"]["Legal Type"];
                        $postfields["attr-name3"] = "identform";
                        $postfields["attr-value3"] = $params["additionalfields"]["Identity Form"];
                        $postfields["attr-name4"] = "identnumber";
                        $postfields["attr-value4"] = $params["additionalfields"]["Identity Number"];
                        $postfields["product-key"] = "dotasia";
                    } else {
                        if ($params["domainObj"]->getLastTLDSegment() === "ru") {
                            $postfields["attr-name1"] = "contract-type";
                            if ($params["additionalfields"]["Registrant Type"] === "ORG") {
                                $postfields["attr-value1"] = "ORG";
                                $postfields["attr-name3"] = "org-r";
                                $postfields["attr-value3"] = $params["companyname"];
                                $postfields["attr-name6"] = "kpp";
                                $postfields["attr-value6"] = $params["additionalfields"]["Russian Organizations Territory-Linked Taxpayer Number 2"];
                                $postfields["attr-name7"] = "code";
                                $postfields["attr-value7"] = $params["additionalfields"]["Russian Organizations Taxpayer Number 1"];
                            } else {
                                $postfields["attr-value1"] = "PRS";
                                $postfields["attr-name2"] = "birth-date";
                                $dateParts = explode("-", $params["additionalfields"]["Individuals Birthday"]);
                                $postfields["attr-value2"] = $dateParts[2] . "." . $dateParts[1] . "." . $dateParts[0];
                                $postfields["attr-name4"] = "person-r";
                                $postfields["attr-value4"] = $params["firstname"] . " " . $params["lastname"];
                                $postfields["attr-name8"] = "passport";
                                $dateParts = explode("-", $params["additionalfields"]["Individuals Passport Issue Date"]);
                                $passportIssuanceDate = $dateParts[2] . "." . $dateParts[1] . "." . $dateParts[0];
                                $postfields["attr-value8"] = $params["additionalfields"]["Individuals Passport Number"] . ", issued by " . $params["additionalfields"]["Individuals Passport Issuer"] . ", " . $passportIssuanceDate;
                            }
                            $postfields["attr-name5"] = "address-r";
                            $postfields["attr-value5"] = $params["address1"] . " " . $params["city"] . " " . $params["fullstate"] . " " . $params["country"] . " " . $params["postcode"];
                        } else {
                            if ($params["domainObj"]->getLastTLDSegment() === "pro") {
                                $postfields["attr-name1"] = "profession";
                                $postfields["attr-value1"] = $params["additionalfields"]["Profession"];
                                $postfields["product-key"] = "dotpro";
                            } else {
                                if ($params["domainObj"]->getLastTLDSegment() === "nl") {
                                    $postfields["attr-name1"] = "legalForm";
                                    $postfields["attr-value1"] = $params["companyname"] ? "ANDERS" : "PERSOON";
                                    $postfields["product-key"] = "dotnl";
                                } else {
                                    if ($params["domainObj"]->getLastTLDSegment() === "tel") {
                                        $postfields["attr-name1"] = "whois-type";
                                        if ($params["additionalfields"]["Legal Type"] === "Natural Person") {
                                            $postfields["attr-value1"] = "Natural";
                                        } else {
                                            if ($params["additionalfields"]["Legal Type"] === "Legal Person") {
                                                $postfields["attr-value1"] = "Legal";
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $postfields;
}

function resellerclub_DomainTLDSpecificFields($params, $contactid, $isPremium = false)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $fieldCount = 0;
    $tncTlds = ["airforce", "army", "app", "bio", "co.nz", "degree", "dentist", "dev", "engineer", "fr", "gives", "law", "market", "mortgage", "navy", "nz", "page", "rehab", "scot", "software", "vet", "vote", "voto"];
    if (in_array($params["domainObj"]->getLastTLDSegment(), ["es", "de"])) {
        $postfields["purchase-privacy"] = "0";
    } else {
        if ($params["domainObj"]->getLastTLDSegment() === "asia") {
            $postfields["attr-name1"] = "cedcontactid";
            $postfields["attr-value1"] = $contactid;
            $fieldCount = 1;
        } else {
            if (in_array($params["domainObj"]->getLastTLDSegment(), ["uk", "eu", "nz", "ru"])) {
                $postfields["admin-contact-id"] = "-1";
                $postfields["tech-contact-id"] = "-1";
                $postfields["billing-contact-id"] = "-1";
            } else {
                if (in_array($params["domainObj"]->getLastTLDSegment(), ["ca", "nl", "london"])) {
                    $postfields["billing-contact-id"] = "-1";
                } else {
                    if ($params["domainObj"]->getLastTLDSegment() === "cn") {
                        $postfields["attr-name1"] = "cnhosting";
                        $postfields["attr-value1"] = resellerclub_boolToString($params["additionalfields"]["cnhosting"]);
                        $postfields["attr-name2"] = "cnhostingclause";
                        $postfields["attr-value2"] = $params["additionalfields"]["cnhregisterclause"] ? "yes" : "no";
                        $fieldCount = 2;
                    } else {
                        if ($params["domainObj"]->getLastTLDSegment() === "au") {
                            $postfields["attr-name1"] = "id-type";
                            $postfields["attr-name2"] = "id";
                            $postfields["attr-name3"] = "policyReason";
                            $postfields["attr-name4"] = "isAUWarranty";
                            $postfields["attr-value4"] = "true";
                            $postfields["attr-value5"] = "";
                            $postfields["attr-value6"] = "";
                            $postfields["attr-value7"] = "";
                            $val4 = $params["additionalfields"]["Eligibility ID"];
                            switch ($params["additionalfields"]["Eligibility ID Type"]) {
                                case "Australian Company Number (ACN)":
                                    $val5 = "ACN";
                                    break;
                                case "ACT Business Number":
                                    $val5 = "ACT BN";
                                    break;
                                case "NSW Business Number":
                                    $val5 = "NSW BN";
                                    break;
                                case "NT Business Number":
                                    $val5 = "NT BN";
                                    break;
                                case "QLD Business Number":
                                    $val5 = "QLD BN";
                                    break;
                                case "SA Business Number":
                                    $val5 = "SA BN";
                                    break;
                                case "TAS Business Number":
                                    $val5 = "TAS BN";
                                    break;
                                case "VIC Business Number":
                                    $val5 = "VIC BN";
                                    break;
                                case "WA Business Number":
                                    $val5 = "WA BN";
                                    break;
                                case "Trademark (TM)":
                                    $val5 = "TM";
                                    break;
                                case "Australian Business Number (ABN)":
                                    $val5 = "ABN";
                                    break;
                                case "Australian Registered Body Number (ARBN)":
                                    $val5 = "ARBN";
                                    break;
                                case "Other - Used to record an Incorporated Association number":
                                    $val5 = "Other";
                                    break;
                                default:
                                    $val5 = "ABN";
                                    if ($params["additionalfields"]["Eligibility Reason"] === "Domain name is an Exact Match Abbreviation or Acronym of your Entity or Trading Name.") {
                                        $postfields["attr-value3"] = "1";
                                    } else {
                                        $postfields["attr-value3"] = "2";
                                        $postfields["attr-value4"] = "true";
                                    }
                                    $postfields["attr-value1"] = $val5;
                                    $postfields["attr-value2"] = $val4;
                                    if ($val5 === "TM" || $val5 === "Other") {
                                        $postfields["attr-name5"] = "eligibilityType";
                                        $postfields["attr-value5"] = $val5 === "Other" ? "Other" : "Trademark Owner";
                                    }
                                    $regnamevals = ["VIC BN", "NSW BN", "SA BN", "NT BN", "WA BN", "TAS BN", "ACT BN", "QLD BN", "TM", "Other"];
                                    if ($val5 === "TM") {
                                        $postfields["attr-name6"] = "eligibilityName";
                                        $postfields["attr-value6"] = $params["additionalfields"]["Eligibility Name"];
                                    }
                                    if (in_array($val5, $regnamevals)) {
                                        $postfields["attr-name7"] = "registrantName";
                                        $postfields["attr-value7"] = $params["additionalfields"]["Registrant Name"];
                                    }
                                    $fieldCount = 7;
                            }
                        } else {
                            if ($params["domainObj"]->getLastTLDSegment() === "tel") {
                                $postfields["attr-name2"] = "publish";
                                $postfields["attr-value2"] = $params["additionalfields"]["WHOIS Opt-out"] ? "Y" : "N";
                                $fieldCount = 2;
                            } else {
                                if ($params["domainObj"]->getLastTLDSegment() == "fr") {
                                    $postfields["billing-contact-id"] = "-1";
                                    $postfields["tech-contact-id"] = "-1";
                                } else {
                                    if (in_array($params["domainObj"]->getLastTLDSegment(), ["quebec", "scot"])) {
                                        $intendedUse = $params["additionalfields"]["Intended Use"];
                                        $intendedUseTruncated = substr($intendedUse, 0, 2048);
                                        $postfields["attr-name1"] = "intended-use";
                                        $postfields["attr-value1"] = $intendedUseTruncated;
                                        $fieldCount = 1;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (in_array($params["domainObj"]->getTopLevel(), $tncTlds)) {
        $fieldCount++;
        $postfields["attr-name" . $fieldCount] = "tnc";
        $postfields["attr-value" . $fieldCount] = $params["additionalfields"]["TNC"] ? "y" : "n";
    }
    if ($isPremium) {
        $postfields["attr-name" . ($fieldCount + 1)] = "premium";
        $postfields["attr-value" . ($fieldCount + 1)] = "true";
    }
    return $postfields;
}

function resellerclub_Sync($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getorderid($postfields, $params);
    if (!is_numeric($orderid)) {
        if (stripos($orderid, "Website doesn't exist for") === 0) {
            return ["transferredAway" => true];
        }
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "All";
    $result = resellerclub_sendcommand("details", "domains", $postfields, $params, "GET");
    if (strtoupper((string) ($result["status"] ?? NULL)) === "ERROR") {
        if (!$result["message"]) {
            $result["message"] = $result["error"];
        }
        return ["error" => $result["message"]];
    }
    if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
        return ["error" => $result["actionstatusdesc"]];
    }
    $expirytime = $currentstatus = "";
    $expirytime = $result["endtime"];
    $currentstatus = $result["currentstatus"];
    if ($expirytime) {
        $returndata = [];
        if ($currentstatus == "Active") {
            $returndata["active"] = true;
        } else {
            if ($currentstatus === "Expired") {
                $returndata["expired"] = true;
            }
        }
        $returndata["expirydate"] = date("Y-m-d", $expirytime);
        return $returndata;
    }
    return ["error" => "No expiry date returned"];
}

function resellerclub_TransferSync($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"], true);
    $orderid = resellerclub_getorderid($postfields, $params);
    if (!is_numeric($orderid)) {
        return ["error" => $orderid];
    }
    unset($postfields);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["order-id"] = $orderid;
    $postfields["options"] = "All";
    $result = resellerclub_sendcommand("details", "domains", $postfields, $params, "GET");
    if (isset($result["status"]) && $result["status"] === "ERROR") {
        return ["error" => $result["message"]];
    }
    $currentstatus = $result["currentstatus"];
    if ($currentstatus === "InActive") {
        return ["inprogress" => true];
    }
    $expirytime = $result["endtime"];
    if ($expirytime) {
        $returndata = [];
        if ($currentstatus == "Active") {
            $returndata["active"] = true;
        } else {
            if ($currentstatus === "Expired") {
                $returndata["expired"] = true;
            }
        }
        $returndata["expirydate"] = date("Y-m-d", $expirytime);
        return $returndata;
    }
    return ["error" => "No expiry date returned"];
}

function resellerclub_DomainSync($registrar)
{
    $lcregistrar = strtolower($registrar);
    $cronreport = $registrar . " Domain Sync Report<br>\n---------------------------------------------------<br>\n";
    $registrar = new WHMCS\Module\Registrar();
    $registrar->load($lcregistrar);
    $params = $registrar->getSettings();
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $testmode = $params["TestMode"];
    $queryresult = select_query("tbldomains", "id,domain,status", "registrar='" . $lcregistrar . "' AND (status='Pending Transfer' OR status='Active')");
    while ($data = mysql_fetch_array($queryresult)) {
        $domainid = $data["id"];
        $domainname = $data["domain"];
        $status = $data["status"];
        $postfields["domain-name"] = $domainname;
        $orderid = resellerclub_getorderid($postfields, $params);
        if (!is_numeric($orderid)) {
            $cronreport .= "Error for " . $domainname . ": " . $orderid . "<br>\n";
        } else {
            unset($postfields);
            $postfields["auth-userid"] = $params["ResellerID"];
            $postfields["api-key"] = $params["APIKey"];
            $postfields["order-id"] = $orderid;
            $postfields["options"] = "All";
            $result = resellerclub_sendcommand("details", "domains", $postfields, $params, "GET");
            if ($result["status"] === "ERROR") {
                $cronreport .= "Error for " . $domainname . ": " . $result["message"] . "<br>\n";
            } else {
                $expirytime = $currentstatus = "";
                $expirytime = $result["endtime"];
                $currentstatus = $result["currentstatus"];
                if ($expirytime) {
                    $updateqry = [];
                    if ($currentstatus === "Active") {
                        $updateqry["status"] = "Active";
                    }
                    $expirydate = date("Y-m-d", $expirytime);
                    $updateqry["expirydate"] = $expirydate;
                    if (count($updateqry)) {
                        update_query("tbldomains", $updateqry, ["id" => $domainid]);
                    }
                    if ($status === "Pending Transfer" && $currentstatus === "Active") {
                        sendMessage("Domain Transfer Completed", $domainid);
                        $cronreport .= "Processed Domain Transfer Completion of " . $domainname . " - Updated expiry to " . fromMySQLDate($expirydate) . "<br>\n";
                    } else {
                        $cronreport .= "Updated " . $domainname . " expiry to " . fromMySQLDate($expirydate) . "<br>\n";
                    }
                } else {
                    $cronreport .= "Error for " . $domainname . ": No expiry date returned<br>\n";
                }
            }
        }
    }
    echo $cronreport;
    logActivity($registrar . " Domain Sync Run");
    sendAdminNotification("system", "WHMCS " . $registrar . " Domain Syncronisation Report", $cronreport);
}

function resellerclub_Language($language)
{
    $language = strtolower($language);
    $allowedlanguages = ["ar", "by", "bg", "ch", "nl", "en", "fi", "fr", "de", "ID", "it", "ja", "me", "mn", "pt", "ru", "sk", "sl", "e1", "es", "tr"];
    switch ($language) {
        case "arabic":
            $language = "ar";
            break;
        case "bulgarian":
            $language = "bg";
            break;
        case "chinese":
            $language = "zh";
            break;
        case "dutch":
            $language = "nl";
            break;
        case "finnish":
            $language = "fi";
            break;
        case "german":
            $language = "de";
            break;
        case "italian":
            $language = "it";
            break;
        case "japanese":
            $language = "ja";
            break;
        case "portuguese-br":
        case "portuguese-pt":
            $language = "pt";
            break;
        case "russian":
            $language = "ru";
            break;
        case "spanish":
            $language = "es";
            break;
        case "turkish":
            $language = "tr";
            break;
        case "english":
        default:
            $language = "en";
            if (!in_array($language, $allowedlanguages)) {
                $language = "en";
            }
            if (strlen($language) === 2) {
                return $language;
            }
            return "en";
    }
}

function resellerclub_ClientAreaCustomButtonArray($params)
{
    $buttonArray = [];
    $params = injectDomainObjectIfNecessary($params);
    if ($params["domainObj"]->getLastTLDSegment() === "xxx") {
        $buttonArray[Lang::trans("xxxmembershipidupdate") != "xxxmembershipidupdate" ?: "XXX Membership ID Update"] = "UpdateXXX";
    }
    return $buttonArray;
}

function resellerclub_UpdateXXX($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if ($params["domainObj"]->getLastTLDSegment() !== "xxx") {
        return ["error" => "Incorrect TLD"];
    }
    if ($_POST["membershipid"] && $params["domainObj"]->getLastTLDSegment() === "xxx") {
        unset($postfields);
        $postfields["auth-userid"] = $params["ResellerID"];
        $postfields["api-key"] = $params["APIKey"];
        $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
        $orderid = resellerclub_getorderid($postfields, $params);
        unset($postfields);
        if (is_numeric($orderid)) {
            $postfields["auth-userid"] = $params["ResellerID"];
            $postfields["api-key"] = $params["APIKey"];
            $postfields["order-id"] = $orderid;
            $postfields["association-id"] = $_POST["membershipid"];
            $result = resellerclub_sendcommand("association-details", "domains/dotxxx", $postfields, $params, "POST");
            if (strtoupper($result["status"]) === "ERROR") {
                $error = $result["message"];
            }
            if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
                $error = true;
            }
            if ($error && $result["actionstatusdesc"]) {
                $error = $result["actionstatusdesc"];
            }
            foreach ($result["hashtable"]["entry"] as $id => $values) {
                if ($values["string"][0] === "actionstatus" && $values["string"][1] === "Failed") {
                    $error = true;
                }
                if ($values["string"][0] === "actionstatusdesc" && $error === true) {
                    $error = $values["string"][1];
                }
            }
            if (!$error) {
                update_query("tbldomainsadditionalfields", ["value" => $_POST["membershipid"]], ["name" => "Membership Token/ID", "domainid" => $params["domainid"]]);
                $success = true;
            }
        } else {
            $error = $orderid;
        }
    }
    $postfields = [];
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["domain-name"] = resellerclub_getDomainName($params["domainObj"]);
    $orderid = resellerclub_getorderid($postfields, $params);
    $memberid = get_query_val("tbldomainsadditionalfields", "value", ["name" => "Membership Token/ID", "domainid" => $params["domainid"]]);
    $retarray = ["templatefile" => "updatexxx", "vars" => ["order-id" => $orderid, "domain" => $postfields["domain-name"], "membershipid" => $memberid]];
    if ($error) {
        $retarray["vars"]["error"] = $error;
    }
    if ($success) {
        $retarray["vars"]["success"] = $success;
    }
    return $retarray;
}

function resellerclub_validateABN($abn)
{
    $abnarray = str_split($abn);
    $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
    $abnarray[0] -= 1;
    $abnsum = 0;
    foreach ($abnarray as $id => $num) {
        $abnarray[$id] = $abnarray[$id] * $weights[$id];
        $abnsum += $abnarray[$id];
    }
    if ($abnsum % 89) {
        return false;
    }
    return true;
}

function resellerclub_ContactType($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $params["domainObj"]->getLastTLDSegment();
    switch ($params["domainObj"]->getLastTLDSegment()) {
        case "br":
            $contacttype = "BrContact";
            break;
        case "ca":
            $contacttype = "CaContact";
            break;
        case "cl":
            $contacttype = "ClContact";
            break;
        case "cn":
            $contacttype = "CnContact";
            break;
        case "co":
            $contacttype = "CoContact";
            break;
        case "de":
            $contacttype = "DeContact";
            break;
        case "es":
            $contacttype = "EsContact";
            break;
        case "eu":
            $contacttype = "EuContact";
            break;
        case "fr":
            $contacttype = "FrContact";
            break;
        case "mx":
        case "lat":
            $contacttype = "MxContact";
            break;
        case "nl":
            $contacttype = "NlContact";
            break;
        case "nyc":
            $contacttype = "NycContact";
            break;
        case "ru":
            $contacttype = "RuContact";
            break;
        case "uk":
            $contacttype = "UkContact";
            break;
        case "tel":
        default:
            $contacttype = "Contact";
            return $contacttype;
    }
}

function resellerclub_addCustomer($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $countries = new WHMCS\Utility\Country();
    if (!function_exists("getClientsDetails")) {
        require ROOTDIR . "/includes/clientfunctions.php";
    }
    $clientdetails = foreignChrReplace(getClientsDetails($params["userid"]));
    $language = $clientdetails["language"] ? $clientdetails["language"] : WHMCS\Config\Setting::getValue("Language");
    $language = resellerclub_language($language);
    $clientdetails = WHMCS\Input\Sanitize::decode($clientdetails);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["username"] = $clientdetails["email"];
    $postfields["passwd"] = resellerclub_genlbrandompw();
    $postfields["name"] = $clientdetails["firstname"] . " " . $clientdetails["lastname"];
    $companyname = $clientdetails["companyname"];
    if (!$companyname) {
        $companyname = "N/A";
    }
    $postfields["company"] = $companyname;
    $postfields["address-line-1"] = (string) substr($clientdetails["address1"], 0, 64);
    if (64 < strlen($clientdetails["address1"])) {
        $postfields["address-line-2"] = (string) substr($clientdetails["address1"] . ", " . $clientdetails["address2"], 64, 128);
    } else {
        $postfields["address-line-2"] = (string) substr($clientdetails["address2"], 0, 64);
    }
    $postfields["city"] = $clientdetails["city"];
    if ($params["country"] !== "US") {
        $postfields["state"] = $clientdetails["state"];
    } else {
        $postfields["state"] = convertStateToCode($clientdetails["state"], $clientdetails["country"]);
    }
    $postfields["zipcode"] = $clientdetails["postcode"];
    $postfields["country"] = $clientdetails["country"];
    $phonenumber = $clientdetails["phonenumber"];
    $phonenumber = preg_replace("/[^0-9]/", "", $phonenumber);
    $countryCode = $clientdetails["phonecc"];
    if (preg_match("/^1([\\d]{3})\$/", $countryCode, $matches)) {
        $countryCode = 1;
        $phonenumber = $matches[1] . $phonenumber;
    }
    $postfields["phone-cc"] = $countryCode;
    $postfields["phone"] = $phonenumber;
    $postfields["lang-pref"] = $language;
    $result = resellerclub_sendcommand("signup", "customers", $postfields, $params, "POST");
    unset($postfields);
    $customerid = NULL;
    if (is_array($result)) {
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
    } else {
        $customerid = $result;
    }
    return $customerid;
}

function resellerclub_addContact($params, $customerid, $contacttype, $canonindv = false, $singleContact = false)
{
    $params = injectDomainObjectIfNecessary($params);
    $countries = new WHMCS\Utility\Country();
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["customer-id"] = $customerid;
    $postFields["email"] = $params["email"];
    if ($canonindv) {
        $postFields["name"] = $params["companyname"];
        $postFields["company"] = "N/A";
    } else {
        $postFields["name"] = array_key_exists("fullName", $params) ? $params["fullName"] : $params["firstname"] . " " . $params["lastname"];
        $companyname = $params["companyname"];
        if (!$companyname) {
            $companyname = "N/A";
        }
        $postFields["company"] = $companyname;
    }
    $postFields["address-line-1"] = (string) substr($params["address1"], 0, 64);
    if (64 < strlen($params["address1"])) {
        $postFields["address-line-2"] = (string) substr($params["address1"] . ", " . $params["address2"], 64, 128);
    } else {
        $postFields["address-line-2"] = (string) substr($params["address2"], 0, 64);
    }
    $postFields["city"] = $params["city"];
    if ($params["country"] !== "US") {
        $postFields["state"] = $params["fullstate"];
    } else {
        $postFields["state"] = $params["state"];
    }
    $postFields["zipcode"] = $params["postcode"];
    $postFields["country"] = $params["country"];
    $phonenumber = $params["phonenumber"];
    $phonenumber = preg_replace("/[^0-9]/", "", $phonenumber);
    if (!array_key_exists("phone-cc", $params)) {
        $countrycode = $countries->getCallingCode($params["country"]);
    } else {
        $countrycode = $params["phone-cc"];
    }
    if (preg_match("/^1([\\d]{3})\$/", $countrycode, $matches)) {
        $countrycode = 1;
        $phonenumber = $matches[1] . $phonenumber;
    }
    $postFields["phone-cc"] = $countrycode;
    $postFields["phone"] = $phonenumber;
    $postFields["type"] = $contacttype;
    $postFields = array_merge($postFields, resellerclub_contacttldspecificfields($params));
    $result = resellerclub_sendcommand("add", "contacts", $postFields, $params, "POST");
    $contactID = NULL;
    if (is_array($result)) {
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
    } else {
        $contactID = $result;
        if ($singleContact) {
            return $contactID;
        }
    }
    if ($params["adminemail"] == $params["email"] && $params["adminfirstname"] == $params["firstname"] && $params["adminaddress1"] == $params["address1"] && !$canonindv) {
        return ["Registrant" => $contactID, "Admin" => $contactID, "Tech" => $contactID, "Billing" => $contactID];
    }
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["customer-id"] = $customerid;
    $postFields["email"] = $params["adminemail"];
    $postFields["name"] = $params["adminfirstname"] . " " . $params["adminlastname"];
    $companyName = $params["admincompanyname"] ? $params["admincompanyname"] : "N/A";
    $postFields["company"] = $companyName;
    $postFields["address-line-1"] = (string) substr($params["adminaddress1"], 0, 64);
    if (64 < strlen($params["address1"])) {
        $postFields["address-line-2"] = (string) substr($params["adminaddress1"] . ", " . $params["adminaddress2"], 64, 128);
    } else {
        $postFields["address-line-2"] = (string) substr($params["adminaddress2"], 0, 64);
    }
    $postFields["city"] = $params["admincity"];
    if ($params["admincountry"] !== "US") {
        $postFields["state"] = $params["adminfullstate"];
    } else {
        $postFields["state"] = $params["adminstate"];
    }
    $postFields["zipcode"] = $params["adminpostcode"];
    $postFields["country"] = $params["admincountry"];
    $phonenumber = $params["adminphonenumber"];
    $phonenumber = preg_replace("/[^0-9]/", "", $phonenumber);
    $countrycode = $params["admincountry"];
    $countrycode = $params["adminphonecc"] ?: $countries->getCallingCode($countrycode);
    if (preg_match("/^1([\\d]{3})\$/", $countrycode, $matches)) {
        $countrycode = 1;
        $phonenumber = $matches[1] . $phonenumber;
    }
    $postFields["phone-cc"] = $countrycode;
    $postFields["phone"] = $phonenumber;
    $postFields["type"] = $contacttype;
    $postFields = array_merge($postFields, resellerclub_contacttldspecificfields($params));
    if ($params["domainObj"]->getLastTLDSegment() === "es") {
        $postFields["company"] = "N/A";
        $params["additionalfields"]["Contact ID Form Type"] = explode("|", $params["additionalfields"]["Contact ID Form Type"]);
        $idtype = $params["additionalfields"]["Contact ID Form Type"][0];
        $idnumber = $params["additionalfields"]["Contact ID Form Number"];
        $postFields["attr-name1"] = "es_form_juridica";
        $postFields["attr-value1"] = "1";
        $postFields["attr-name2"] = "es_tipo_identificacion";
        $postFields["attr-value2"] = $idtype;
        $postFields["attr-name3"] = "es_identificacion";
        $postFields["attr-value3"] = $idnumber;
        $postFields["product-key"] = "dotes";
    } else {
        if ($params["domainObj"]->getLastTLDSegment() === "ca") {
            $postFields["attr-name1"] = "CPR";
            $postFields["attr-value1"] = "CCT";
            if ($canonindv) {
                $postFields["name"] = array_key_exists("fullName", $params) ? $params["fullName"] : $params["firstname"] . " " . $params["lastname"];
                $companyname = $params["companyname"];
                if (!$companyname) {
                    $companyname = "N/A";
                }
                $postFields["company"] = $companyname;
            }
        }
    }
    $result = resellerclub_sendcommand("add", "contacts", $postFields, $params, "POST");
    $contactId = NULL;
    if (is_array($result)) {
        if (strtoupper($result["status"]) === "ERROR") {
            if (!$result["message"]) {
                $result["message"] = $result["error"];
            }
            return ["error" => $result["message"]];
        }
        if (!empty($result["actionstatus"]) && $result["actionstatus"] === "Failed") {
            return ["error" => $result["actionstatusdesc"]];
        }
    } else {
        $contactId = $result;
    }
    unset($postFields);
    return ["Registrant" => $contactID, "Admin" => $contactId, "Tech" => $contactId, "Billing" => $contactId];
}

function resellerclub_getClientEmail($userid)
{
    return get_query_val("tblclients", "email", ["id" => $userid]);
}

function resellerclub_addCOOPSponsor($params, $customerid)
{
    $params = injectDomainObjectIfNecessary($params);
    $postfields["auth-userid"] = $params["ResellerID"];
    $postfields["api-key"] = $params["APIKey"];
    $postfields["customer-id"] = $customerid;
    $postfields["name"] = $params["additionalfields"]["Contact Name"];
    $postfields["email"] = $params["additionalfields"]["Contact Email"];
    $postfields["company"] = $params["additionalfields"]["Contact Company"];
    $postfields["address-line-1"] = $params["additionalfields"]["Address 1"];
    if ($params["additionalfields"]["Address 2"]) {
        $postfields["address-line-2"] = $params["additionalfields"]["Address 2"];
    }
    $postfields["city"] = $params["additionalfields"]["City"];
    $postfields["state"] = $params["additionalfields"]["State"];
    $postfields["zipcode"] = $params["additionalfields"]["ZIP Code"];
    $postfields["country"] = $params["additionalfields"]["Country"];
    $postfields["phone-cc"] = $params["additionalfields"]["Phone CC"];
    $postfields["phone"] = $params["additionalfields"]["Phone"];
    return resellerclub_sendcommand("add-sponsor", "contacts/coop", $postfields, $params, "POST");
}

function resellerclub_getDomainName(WHMCS\Domains\Domain $domain, $skipFilter = false)
{
    $domainName = $domain->getDomain();
    if ($skipFilter) {
        return $domainName;
    }
    if (function_exists("mb_strtolower")) {
        return mb_strtolower($domainName);
    }
    if (preg_replace("/[^a-z0-9-.]/i", "", $domainName) === $domainName) {
        return strtolower($domainName);
    }
    return $domainName;
}

function resellerclub_CheckAvailability($params)
{
    $type = App::isInRequest("epp") ? "Transfer" : "Register";
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["domain-name"] = $params["sld"];
    array_walk($params["tlds"], function (&$value) {
        $value = substr($value, 1);
    });
    $postFields["tlds"] = $params["tlds"];
    $result = resellerclub_sendcommand("available", "domains", $postFields, $params, "GET");
    unset($postFields);
    if (strtoupper($result["response"]["status"]) === "ERROR") {
        if (!$result["response"]["message"]) {
            $result["response"]["message"] = $result["response"]["error"];
        }
        throw new Exception($result["response"]["message"]);
    }
    $results = new WHMCS\Domains\DomainLookup\ResultsList();
    foreach ($result as $domainName => $domainData) {
        $parts = explode(".", $domainName, 2);
        $searchResult = new WHMCS\Domains\DomainLookup\SearchResult($parts[0], $parts[1]);
        if ($domainData["status"] === "available") {
            $searchResult->setStatus(WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED);
        } else {
            if ($domainData["status"] === "unknown") {
                $searchResult->setStatus(WHMCS\Domains\DomainLookup\SearchResult::STATUS_TLD_NOT_SUPPORTED);
            } else {
                $searchResult->setStatus(WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED);
            }
        }
        if (array_key_exists("costHash", $domainData)) {
            if ($params["premiumEnabled"]) {
                $premiumPricing = [];
                if ($type === "Register" && array_key_exists("create", $domainData["costHash"])) {
                    $premiumPricing["register"] = $domainData["costHash"]["create"];
                }
                if (array_key_exists("renew", $domainData["costHash"])) {
                    $premiumPricing["renew"] = $domainData["costHash"]["renew"];
                }
                if ($type === "Transfer" && array_key_exists("transfer", $domainData["costHash"])) {
                    $premiumPricing["transfer"] = $domainData["costHash"]["transfer"];
                }
                if ($premiumPricing) {
                    $searchResult->setPremiumDomain(true);
                    $premiumPricing["CurrencyCode"] = $domainData["costHash"]["sellingCurrencySymbol"];
                    $searchResult->setPremiumCostPricing($premiumPricing);
                }
            } else {
                $searchResult->setStatus(WHMCS\Domains\DomainLookup\SearchResult::STATUS_RESERVED);
            }
        }
        $results->append($searchResult);
    }
    return $results;
}

function resellerclub_GetDomainSuggestions($params)
{
    $groups = resellerclub_GetDomainExtensionGroup();
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["keyword"] = $params["punyCodeSearchTerm"] ?: $params["searchTerm"];
    $postFields["tld-only"] = $params["tldsToInclude"];
    $result = resellerclub_sendcommand("suggest-names", "domains/v5", $postFields, $params, "GET");
    unset($postFields);
    if (strtoupper($result["response"]["status"]) === "ERROR") {
        if (!$result["response"]["message"]) {
            $result["response"]["message"] = $result["response"]["error"];
        }
        throw new Exception($result["response"]["message"]);
    }
    $results = new WHMCS\Domains\DomainLookup\ResultsList();
    if ($params["premiumEnabled"]) {
        $domainNames = $domainTlds = $separateDomains = $domainAvailability = [];
        foreach ($result as $domainName => $domainData) {
            $domainParts = explode(".", $domainName, 2);
            if (in_array($domainParts[1], $groups["Donuts"])) {
                $separateDomains[] = $domainName;
            } else {
                if (!in_array($domainParts[0], $domainNames)) {
                    $domainNames[] = $domainParts[0];
                }
                if (!in_array($domainParts[1], $domainTlds)) {
                    $domainTlds[] = $domainParts[1];
                }
            }
        }
        $postFields = [];
        $postFields["auth-userid"] = $params["ResellerID"];
        $postFields["api-key"] = $params["APIKey"];
        $postFields["domain-name"] = $domainNames;
        $postFields["tlds"] = $domainTlds;
        $availableResults = resellerclub_sendcommand("available", "domains", $postFields, $params, "GET");
        unset($postFields);
        foreach ($availableResults as $availableDomainName => $availableDomainData) {
            if (array_key_exists($availableDomainName, $result)) {
                $thisDomain = [];
                if ($availableResults["status"] === "available") {
                    $thisDomain["status"] = WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED;
                } else {
                    $thisDomain["status"] = WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;
                }
                $thisDomain["premium"] = false;
                $thisDomain["premiumPricing"] = [];
                if (array_key_exists("costHash", $availableDomainData)) {
                    $premiumPricing = [];
                    if (array_key_exists("create", $availableDomainData["costHash"])) {
                        $premiumPricing["register"] = $availableDomainData["costHash"]["create"];
                    }
                    if (array_key_exists("renew", $availableDomainData["costHash"])) {
                        $premiumPricing["renew"] = $availableDomainData["costHash"]["renew"];
                    }
                    if ($premiumPricing) {
                        $thisDomain["premium"] = true;
                        $premiumPricing["CurrencyCode"] = $availableDomainData["costHash"]["sellingCurrencySymbol"];
                        $thisDomain["premiumPricing"] = $premiumPricing;
                    }
                }
                $domainAvailability[$availableDomainName] = $thisDomain;
            }
        }
        foreach ($separateDomains as $domainName) {
            $domainParts = explode(".", $domainName, 2);
            $postFields = [];
            $postFields["auth-userid"] = $params["ResellerID"];
            $postFields["api-key"] = $params["APIKey"];
            list($postFields["domain-name"], $postFields["tlds"]) = $domainParts;
            $availableResults = resellerclub_sendcommand("available", "domains", $postFields, $params, "GET");
            unset($postFields);
            foreach ($availableResults as $availableDomainName => $availableDomainData) {
                if (array_key_exists($availableDomainName, $result)) {
                    $thisDomain = [];
                    if ($availableResults["status"] === "available") {
                        $thisDomain["status"] = WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED;
                    } else {
                        $thisDomain["status"] = WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;
                    }
                    $thisDomain["premium"] = false;
                    $thisDomain["premiumPricing"] = [];
                    if (array_key_exists("costHash", $availableDomainData)) {
                        $premiumPricing = [];
                        if (array_key_exists("create", $availableDomainData["costHash"])) {
                            $premiumPricing["register"] = $availableDomainData["costHash"]["create"];
                        }
                        if (array_key_exists("renew", $availableDomainData["costHash"])) {
                            $premiumPricing["renew"] = $availableDomainData["costHash"]["renew"];
                        }
                        if ($premiumPricing) {
                            $thisDomain["premium"] = true;
                            $premiumPricing["CurrencyCode"] = $availableDomainData["costHash"]["sellingCurrencySymbol"];
                            $thisDomain["premiumPricing"] = $premiumPricing;
                        }
                    }
                    $domainAvailability[$availableDomainName] = $thisDomain;
                }
            }
        }
        foreach ($result as $domainName => $domainData) {
            if ($domainData["status"] === "available") {
                $domainParts = explode(".", $domainName, 2);
                $thisDomain = $domainAvailability[$domainName];
                unset($domainAvailability[$domainName]);
                $searchResult = new WHMCS\Domains\DomainLookup\SearchResult($domainParts[0], $domainParts[1]);
                $searchResult->setStatus($thisDomain["status"]);
                if ($thisDomain["premium"]) {
                    $searchResult->setPremiumDomain(true);
                    $searchResult->setPremiumCostPricing($thisDomain["premiumPricing"]);
                }
                $searchResult->setScore($domainData["score"]);
                $results->append($searchResult);
            }
        }
    } else {
        foreach ($result as $domainName => $domainData) {
            if ($domainData["status"] === "available") {
                $domainParts = explode(".", $domainName, 2);
                $searchResult = new WHMCS\Domains\DomainLookup\SearchResult($domainParts[0], $domainParts[1]);
                $searchResult->setStatus(WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED);
                $searchResult->setScore($domainData["score"]);
                $results->append($searchResult);
            }
        }
    }
    return $results;
}

function resellerclub_GetPremiumPrice($params)
{
    $premiumPricing = [];
    return $premiumPricing;
}

function resellerclub_GetDomainExtensionGroup()
{
    $jsonList = "{\"gTld\":[\"ae.org\",\"asia\",\"at\",\"berlin\",\"bid\",\"biz\",\"blue\",\"build\",\"buzz\",\"bz\",\"cc\",\"club\",\"cn\",\"cn.com\",\"co\",\"co.bz\",\"co.com\",\"co.de\",\"co.in\",\"co.nz\",\"co.uk\",\"com\",\"com.au\",\"com.bz\",\"com.cn\",\"com.co\",\"com.de\",\"coop\",\"dance\",\"de\",\"de.com\",\"democrat\",\"es\",\"eu\",\"eu.com\",\"firm.in\",\"futbol\",\"gen.in\",\"gr.com\",\"green\",\"hu.com\",\"immobilien\",\"in\",\"in.net\",\"ind.in\",\"info\",\"ink\",\"jpn.com\",\"kim\",\"la\",\"me\",\"me.uk\",\"menu\",\"mn\",\"mobi\",\"name\",\"net\",\"net.au\",\"net.bz\",\"net.cn\",\"net.co\",\"net.in\",\"net.nz\",\"ninja\",\"nl\",\"no.com\",\"nom.co\",\"org\",\"org.bz\",\"org.cn\",\"org.in\",\"org.nz\",\"org.uk\",\"pink\",\"pro\",\"pw\",\"qc.com\",\"red\",\"reviews\",\"ru.com\",\"sa.com\",\"sc\",\"se.com\",\"se.net\",\"shiksha\",\"social\",\"sx\",\"tel\",\"trade\",\"tv\",\"uk\",\"uk.net\",\"uno\",\"us\",\"vc\",\"webcam\",\"wien\",\"wiki\",\"ws\",\"xn--3ds443g\",\"xn--6frz82g\",\"xn--c1avg\",\"xn--fiq228c5hs\",\"xn--i1b6b1a6a2e\",\"xn--ngbc5azd\",\"xn--nqv7f\",\"xxx\",\"xyz\"],\"Donuts\":[\"academy\",\"agency\",\"apartments\",\"associates\",\"bargains\",\"bike\",\"bingo\",\"boutique\",\"builders\",\"cab\",\"cafe\",\"capital\",\"cards\",\"care\",\"careers\",\"cash\",\"catering\",\"center\",\"chat\",\"cheap\",\"church\",\"city\",\"claims\",\"clinic\",\"clothing\",\"coach\",\"codes\",\"coffee\",\"community\",\"computer\",\"condos\",\"construction\",\"contractors\",\"cool\",\"coupons\",\"cruises\",\"dating\",\"deals\",\"delivery\",\"dental\",\"diamonds\",\"digital\",\"direct\",\"directory\",\"discount\",\"domains\",\"education\",\"email\",\"engineering\",\"enterprises\",\"equipment\",\"estate\",\"events\",\"exchange\",\"expert\",\"exposed\",\"express\",\"fail\",\"farm\",\"finance\",\"financial\",\"fish\",\"fitness\",\"flights\",\"florist\",\"football\",\"foundation\",\"fund\",\"furniture\",\"fyi\",\"gallery\",\"gifts\",\"gmbh\",\"golf\",\"graphics\",\"gratis\",\"gripe\",\"group\",\"guide\",\"guru\",\"healthcare\",\"hockey\",\"holdings\",\"holiday\",\"house\",\"immo\",\"industries\",\"institute\",\"insure\",\"international\",\"jewelry\",\"land\",\"lease\",\"legal\",\"life\",\"lighting\",\"limited\",\"limo\",\"ltd\",\"maison\",\"management\",\"marketing\",\"mba\",\"media\",\"memorial\",\"money\",\"network\",\"partners\",\"parts\",\"photography\",\"photos\",\"pizza\",\"place\",\"plus\",\"productions\",\"properties\",\"recipes\",\"reisen\",\"rentals\",\"repair\",\"report\",\"restaurant\",\"run\",\"salon\",\"sarl\",\"school\",\"schule\",\"services\",\"show\",\"singles\",\"soccer\",\"solutions\",\"style\",\"supplies\",\"supply\",\"support\",\"surgery\",\"systems\",\"tax\",\"taxi\",\"team\",\"technology\",\"tennis\",\"theater\",\"tienda\",\"tips\",\"tools\",\"tours\",\"town\",\"training\",\"university\",\"vacations\",\"ventures\",\"viajes\",\"villas\",\"vin\",\"vision\",\"voyage\",\"watch\",\"wine\",\"works\",\"world\",\"wtf\",\"zone\"]}";
    return json_decode($jsonList, true);
}

function resellerclub_DomainSuggestionOptions()
{
    return [];
}

function resellerclub_GetDomainInformation($params)
{
    $seemsLikeTimestamp = function ($value) {
        return is_int($value) && 0 < $value || !empty($value) && is_string($value) && ctype_digit($value);
    };
    $tryCreateDate = function ($Carbon, $value) {
        if (!$seemsLikeTimestamp($value)) {
            return NULL;
        }
        try {
            return WHMCS\Carbon::createFromTimestamp($value, Carbon\CarbonTimeZone::create("UTC"));
        } catch (Exception $e) {
        }
    };
    $params = injectDomainObjectIfNecessary($params);
    $domainName = resellerclub_getdomainname($params["domainObj"]);
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["domain-name"] = $domainName;
    $orderId = resellerclub_getorderid($postFields, $params);
    if (!is_numeric($orderId)) {
        throw new WHMCS\Exception\Module\NotServicable($orderId);
    }
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["order-id"] = $orderId;
    $postFields["options"] = "All";
    $domainStatus = resellerclub_sendcommand("details", "domains", $postFields, $params, "GET");
    $contactChangePending = $domainStatus["raaVerificationStatus"] === "Pending";
    $pendingSuspend = false;
    $contactChangePendingExpiry = NULL;
    if ($contactChangePending) {
        $contactChangePendingExpiry = $tryCreateDate($domainStatus["raaVerificationStartTime"] ?? NULL);
        if (!is_null($contactChangePendingExpiry)) {
            $contactChangePendingExpiry->addDays(15);
            $pendingSuspend = true;
        }
    }
    $nameservers = [];
    for ($x = 1; $x <= $domainStatus["noOfNameServers"]; $x++) {
        $nameservers["ns" . $x] = $domainStatus["ns" . $x];
    }
    $irtpLock = in_array("sixtydaylock", $domainStatus["domainstatus"]);
    $transferLock = in_array("transferlock", $domainStatus["orderstatus"]);
    if (array_key_exists("irtp_status", $domainStatus)) {
        $contactChangePending = $domainStatus["irtp_status"]["task-status"] === "PENDING";
        if ($contactChangePending) {
            $contactChangePendingExpiry = $tryCreateDate($domainStatus["irtp_status"]["expiry"] ?? NULL);
        }
        $pendingSuspend = false;
    }
    $isIcannTld = resellerclub_tld_type($params) === "generic";
    $irtpOptOut = true;
    $triggerFields = [];
    if (!array_key_exists("DesignatedAgent", $params) || !$params["DesignatedAgent"]) {
        $triggerFields = ["Registrant" => ["Full Name", "Company Name", "Email"]];
        $irtpOptOut = false;
    }
    $expiryDate = $tryCreateDate($domainStatus["endtime"] ?? NULL);
    return (new WHMCS\Domain\Registrar\Domain())->setDomain($domainName)->setIrtpOptOutStatus($irtpOptOut)->setIsIrtpEnabled($isIcannTld)->setPendingSuspension($pendingSuspend)->setDomainContactChangePending($contactChangePending)->setDomainContactChangeExpiryDate($contactChangePendingExpiry)->setTransferLock($transferLock)->setIrtpTransferLock($irtpLock)->setNameservers($nameservers)->setRegistrantEmailAddress($domainStatus["registrantcontact"]["emailaddr"])->setIdProtectionStatus($domainStatus["isprivacyprotected"] !== "false")->setExpiryDate($expiryDate)->setRegistrationStatus(resellerclub_normalise_status($domainStatus["currentstatus"]))->setIrtpVerificationTriggerFields($triggerFields);
}

function resellerclub_ResendIRTPVerificationEmail($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $domainName = resellerclub_getdomainname($params["domainObj"]);
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $postFields["domain-name"] = $domainName;
    $orderId = resellerclub_getorderid($postFields, $params);
    if (!is_numeric($orderId)) {
        return ["error" => $orderId];
    }
    unset($params["domain-name"]);
    $postFields["order-id"] = $orderId;
    $resendStatus = resellerclub_sendcommand("resend-verification", "domains/raa", $postFields, $params, "POST");
    if (is_array($resendStatus) && $resendStatus["status"] !== "Success") {
        return ["error" => $resendStatus["message"]];
    }
    return ["success" => true];
}

function resellerclub_normalise_status($status)
{
    switch ($status) {
        case "InActive":
            return WHMCS\Domain\Registrar\Domain::STATUS_INACTIVE;
            break;
        case "Suspended":
            return WHMCS\Domain\Registrar\Domain::STATUS_SUSPENDED;
            break;
        case "Pending Delete Restorable":
            return WHMCS\Domain\Registrar\Domain::STATUS_PENDING_DELETE;
            break;
        case "Deleted":
            return WHMCS\Domain\Registrar\Domain::STATUS_DELETED;
            break;
        case "Archived":
            return WHMCS\Domain\Registrar\Domain::STATUS_ARCHIVED;
            break;
        default:
            return WHMCS\Domain\Status::ACTIVE;
    }
}

function resellerclub_tld_type($params = [])
{
    $params = injectDomainObjectIfNecessary($params);
    $tld = $params["domainObj"]->getLastTLDSegment();
    $transientData = WHMCS\TransientData::getInstance()->retrieve("ResellerClubTldData");
    if ($transientData) {
        $transientData = json_decode($transientData, true);
    }
    if (!$transientData) {
        $postFields = [];
        $postFields["auth-userid"] = $params["ResellerID"];
        $postFields["api-key"] = $params["APIKey"];
        $transientData = resellerclub_sendcommand("tld-info", "domains", $postFields, $params, "POST");
        WHMCS\TransientData::getInstance()->store("ResellerClubTldData", json_encode($transientData), 2592000);
    }
    $type = "generic";
    if (array_key_exists($tld, $transientData)) {
        $type = $transientData[$tld]["type"];
    }
    unset($transientData);
    return $type;
}

function resellerclub_GetTldPricing($params)
{
    $resellerDetails = resellerclub_get_reseller_details($params);
    if (is_array($resellerDetails) && array_key_exists("error", $resellerDetails)) {
        return $resellerDetails;
    }
    $currencyCode = $resellerDetails["parentsellingcurrencysymbol"];
    $currenciesData = resellerclub_get_currencies_data($params);
    $multiplier = 1;
    if (array_key_exists($currencyCode, $currenciesData)) {
        $multiplier = (int) $currenciesData[$currencyCode]["currencyunit"];
    }
    $pricingData = resellerclub_get_pricing_data($params);
    $categoryData = resellerclub_get_category_data($params);
    $results = new WHMCS\Results\ResultsList();
    foreach ($categoryData as $category => $categoryDatum) {
        $pricing = $pricingData[$category];
        $minRegisterYear = (int) $categoryDatum["minregistrationyear"];
        $maxRegisterYear = (int) $categoryDatum["maxregistrationyear"];
        $registerPrice = $pricing["addnewdomain"][$minRegisterYear] * $multiplier;
        $renewPrice = $pricing["renewdomain"][$minRegisterYear] * $multiplier;
        $transferPrice = $pricing["addtransferdomain"][$minRegisterYear] * $multiplier;
        $redemptionPrice = $pricing["restoredomain"][$minRegisterYear] * $multiplier;
        foreach ($categoryDatum["tldlist"] as $tld) {
            $item = (new WHMCS\Domain\TopLevel\ImportItem())->setExtension($tld)->setCurrency($currencyCode)->setMaxYears($maxRegisterYear)->setMinYears($minRegisterYear)->setRegisterPrice($registerPrice)->setRenewPrice($renewPrice)->setTransferPrice($transferPrice)->setRedemptionFeePrice($redemptionPrice)->setEppRequired($categoryDatum["istransfersecretrequired"] === "true");
            $results[] = $item;
        }
    }
    return $results;
}

function resellerclub_get_reseller_details($params)
{
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $resellerDetails = resellerclub_sendcommand("details", "resellers", $postFields, [], "GET", true);
    if (is_array($resellerDetails)) {
        $check = $resellerDetails;
        if (array_key_exists("response", $resellerDetails)) {
            $check = $resellerDetails["response"];
        }
        if (array_key_exists("status", $check) && strtolower($check["status"]) == "error") {
            return ["error" => $check["message"]];
        }
    }
    return $resellerDetails;
}

function resellerclub_get_pricing_data($params)
{
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $pricingData = resellerclub_sendcommand("reseller-cost-price", "products", $postFields, [], "GET", true);
    return $pricingData;
}

function resellerclub_get_category_data($params)
{
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $categoryData = resellerclub_sendcommand("details", "products", $postFields, [], "GET", true);
    return $categoryData;
}

function resellerclub_get_tld_to_category_map($params)
{
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    $data = resellerclub_sendcommand("category-keys-mapping", "products", $postFields, [], "GET", true);
    $data = $data["domorder"];
    $tldMap = [];
    foreach ($data as $categories) {
        foreach ($categories as $category => $tlds) {
            foreach ($tlds as $tld) {
                $tldMap[$tld] = $category;
            }
        }
    }
    return $tldMap;
}

function resellerclub_get_currencies_data($params)
{
    $postFields = [];
    $postFields["auth-userid"] = $params["ResellerID"];
    $postFields["api-key"] = $params["APIKey"];
    return resellerclub_sendcommand("details", "currency", $postFields, [], "GET", true);
}

function resellerclub_getIdnLanguage($tld, $idnLanguage)
{
    $languageMap = resellerclub_idnLanguageMap($tld);
    if (!$languageMap) {
        throw new InvalidArgumentException("Invalid domain for IDN");
    }
    if (in_array("*", $languageMap)) {
        return $idnLanguage;
    }
    if (empty($languageMap[$idnLanguage]) && empty($languageMap["all-else"])) {
        throw new InvalidArgumentException("Invalid IDN Language Code for TLD");
    }
    if (empty($languageMap[$idnLanguage]) && !empty($languageMap["all-else"])) {
        return $languageMap["all-else"];
    }
    return $languageMap[$idnLanguage];
}

function resellerclub_idnLanguageMap($tld)
{
    switch ($tld) {
        case "ae.org":
        case "sa.com":
            $map = ["ara" => "ara"];
            break;
        case "bharat":
        case "xn--i1b6b1a6a2e":
            $map = ["hin" => "hin-deva"];
            break;
        case "biz":
        case "tel":
            $map = ["chi" => "zh", "dan" => "da", "fin" => "fi", "ger" => "de", "hun" => "hu", "ice" => "is", "jpn" => "jp", "kor" => "ko", "lav" => "lv", "lit" => "lt", "nor" => "no", "pol" => "pl", "por" => "pt", "spa" => "es", "swe" => "sv"];
            break;
        case "br.com":
        case "de.com":
        case "eu":
        case "gb.com":
        case "gb.net":
        case "hu.com":
        case "no.com":
        case "qc.com":
        case "se.com":
        case "se.net":
        case "uk.com":
        case "uk.net":
        case "uy.com":
        case "za.com":
            $map = ["all-else" => "latin"];
            break;
        case "ca":
            $map = ["fre" => "fr"];
            break;
        case "cn.com":
            $map = ["chi" => "chi"];
            break;
        case "co":
            $map = ["chi" => "zh", "dan" => "da", "fin" => "fi", "ice" => "is", "jpn" => "jp", "kor" => "ko", "nor" => "no", "spa" => "es", "swe" => "sv"];
            break;
        case "com":
        case "name":
        case "net":
            $map = ["*"];
            break;
        case "de":
            $map = ["ger" => "de"];
            break;
        case "es":
            $map = ["spa" => "es"];
            break;
        case "eu.com":
            $map = ["ara" => "ara", "gre" => "gre", "heb" => "heb", "lat" => "lat", "rus" => "cyr"];
            break;
        case "gr.com":
            $map = ["gre" => "gre"];
            break;
        case "in.net":
        case "pw":
            $map = ["ara" => "ara", "chi" => "chi", "gre" => "gre", "heb" => "heb", "jpn" => "jpn", "kor" => "kor", "lao" => "lao", "rus" => "cyr", "tha" => "tha", "all-else" => "lat"];
            break;
        case "info":
            $map = ["dan" => "da", "ger" => "de", "hun" => "hu", "ice" => "is", "kor" => "ko", "lav" => "lv", "lit" => "lt", "pol" => "pl", "spa" => "es", "swe" => "sv"];
            break;
        case "jpn.com":
            $map = ["jpn" => "jpn"];
            break;
        case "kr.com":
            $map = ["kor" => "kor"];
            break;
        case "org":
            $map = ["chi" => "zn-ch", "dan" => "da", "ger" => "de", "hun" => "hu", "ice" => "is", "kor" => "ko", "lav" => "lv", "lit" => "lt", "pol" => "pl", "spa" => "es", "swe" => "sv"];
            break;
        case "ru.com":
            $map = ["rus" => "cyr"];
            break;
        case "us.com":
            $map = ["ara" => "ara", "chi" => "chi", "gre" => "gre", "heb" => "heb", "jpn" => "jpn", "all-else" => "latin"];
            break;
        case "xn--c1avg":
            $map = ["rus" => "ru"];
            break;
        case "xn--fiq228c5hs":
        case "xn--3ds443g":
            $map = ["chi" => "zn"];
            break;
        case "xn--nqv7f":
            $map = ["chi" => "zn-ch"];
            break;
        default:
            $map = [];
            return $map;
    }
}

function resellerclub_AdditionalDomainFields($params)
{
    $values = [];
    $fields = [];
    $tncTlds = ["airforce", "army", "app", "bio", "co.nz", "degree", "dev", "engineer", "fr", "gives", "law", "market", "mortgage", "navy", "nz", "page", "rehab", "software", "vet", "vote", "voto"];
    if (in_array($params["tld"], $tncTlds)) {
        $fields[] = ["Name" => "TNC", "LangVar" => "tnc", "DisplayName" => "Terms and Conditions", "Type" => "tickbox", "Description" => "Accept the Terms and Conditions", "Required" => true];
    }
    $values["fields"] = $fields;
    return $values;
}

function resellerclub_boolToString($string)
{
    return $string ? "true" : "false";
}
