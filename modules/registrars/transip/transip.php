<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function transip_MetaData()
{
    return ["DisplayName" => "TransIP"];
}

function transip_getConfigArray()
{
    return [WHMCS\Module\Registrar\Transip\TransIP::CONFIG_FIELD_ENDPOINT => ["FriendlyName" => "TransIP Region", "Type" => "dropdown", "Options" => implode(",", array_values(WHMCS\Module\Registrar\Transip\TransIP::AVAILABLE_ENDPOINTS)), "Description" => "The regional site that your account is registered on."], WHMCS\Module\Registrar\Transip\TransIP::CONFIG_FIELD_USERNAME => ["FriendlyName" => "Username", "Type" => "text", "Size" => "32", "Description" => "Your TransIP Username"], WHMCS\Module\Registrar\Transip\TransIP::CONFIG_FIELD_PRIVATE_KEY => ["FriendlyName" => "Private Key", "Type" => "textarea", "Description" => "Private key (downloaded from your Controlpanel)"], WHMCS\Module\Registrar\Transip\TransIP::CONFIG_FIELD_TEST_MODE => ["FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Don't allow any changes, use for testing"]];
}

function transip_AdditionalDomainFields($params)
{
    $values = [];
    $fields = [];
    $companyTypeOptions = [];
    foreach (WHMCS\Module\Registrar\Transip\TransIP::getCompanyTypes() as $machine => $display) {
        if (!empty($machine)) {
            $companyTypeOptions[] = $machine . "|" . $display;
        } else {
            $companyTypeOptions[] = $display;
        }
    }
    $fields[] = ["Name" => "TIPCompanyType", "DisplayName" => "Company Type", "LangVar" => "TIPCompanyType", "Type" => "dropdown", "Options" => implode(",", array_values($companyTypeOptions)), "Description" => "This information is provided to the Domain Registrar.", "Required" => true];
    $fields[] = ["Name" => "TIPCompanyName", "DisplayName" => "Company Name", "LangVar" => "TIPCompanyName", "Type" => "text", "Description" => "Required if registering this domain as a company.", "Required" => false];
    $fields[] = ["Name" => "TIPCompanyNumber", "DisplayName" => "Company Number", "LangVar" => "TIPCompanyNumber", "Type" => "text", "Description" => "Required if registering this domain as a company.", "Required" => false];
    $values["fields"] = $fields;
    return $values;
}

function transip_config_validate($params)
{
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $transIp->client()->test()->test();
    } catch (Throwable $e) {
        throw new WHMCS\Exception\Module\InvalidConfiguration($e->getMessage());
    }
}

function transip_RegisterDomain($params)
{
    $domain = $params["sld"] . "." . $params["tld"];
    $errorResponse = "";
    try {
        $transip = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $contacts = WHMCS\Module\Registrar\Transip\TransIP::getContactsFromParams($params);
        $nameservers = WHMCS\Module\Registrar\Transip\TransIP::getNameserversFromParams($params);
        $transip->client()->domains()->register($domain, $contacts, $nameservers);
    } catch (Throwable $e) {
        $errorResponse = $e->getMessage();
    }
    logModuleCall("transip", "RegisterDomain", $domain, $errorResponse);
    return empty($errorResponse) ? ["success" => true] : ["error" => $errorResponse];
}

function transip_TransferDomain($params)
{
    $domain = $params["sld"] . "." . $params["tld"];
    $authCode = $params["transfersecret"] ?? "";
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $nameservers = WHMCS\Module\Registrar\Transip\TransIP::getNameserversFromParams($params);
        $contacts = WHMCS\Module\Registrar\Transip\TransIP::getContactsFromParams($params);
        $transIp->client()->domains()->transfer($domain, $authCode, $contacts, $nameservers);
        logModuleCall("transip", "TransferDomain", [$domain, $authCode], "");
        return ["success" => true];
    } catch (Throwable $e) {
        logModuleCall("transip", "TransferDomain", [$domain, $authCode], $e->getMessage());
        return ["error" => $e->getMessage()];
    }
}

function transip_RenewDomain($params)
{
    return ["success" => true];
}

function transip_GetNameservers($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $nameserverArray = $transIp->client()->domainNameserver()->getByDomainName($domainName);
        $nameservers = [];
        foreach ($nameserverArray as $index => $nameserver) {
            $nameservers["ns" . ($index + 1)] = $nameserver->getHostname();
        }
        logModuleCall("transip", "GetNameservers", $domainName, $nameserverArray, NULL);
        return $nameservers;
    } catch (Throwable $e) {
        logModuleCall("transip", "GetNameservers", $domainName, $e->getMessage());
        return ["error" => $e->getMessage()];
    }
}

function transip_SaveNameservers($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $nameservers = WHMCS\Module\Registrar\Transip\TransIP::getNameserversFromParams($params);
        $transIp->client()->domainNameserver()->update($domainName, $nameservers);
        logModuleCall("transip", "SaveNameservers", [$domainName, $nameservers], NULL);
        return ["success" => true];
    } catch (Throwable $e) {
        logModuleCall("transip", "SaveNameservers", [$domainName, $nameservers], $e->getMessage());
        return ["error" => $e->getMessage()];
    }
}

function transip_GetRegistrarLock($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $domain = $transIp->client()->domains()->getByName($domainName);
        $result = $domain->isTransferLocked() ? "locked" : "unlocked";
        logModuleCall("transip", "GetRegistrarLock", $domainName, $domain->isTransferLocked(), $result);
        return $result;
    } catch (Throwable $e) {
        logModuleCall("transip", "GetRegistrarLock", $domainName, $e->getMessage());
        return ["error" => $e->getMessage()];
    }
}

function transip_SaveRegistrarLock($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    $transferLock = $params["lockenabled"] === "locked";
    $logArray = [$transferLock ? "set-lock" : "remove-lock", $domainName, $params["lockenabled"]];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $domain = $transIp->client()->domains()->getByName($domainName);
        $domain->setIsTransferLocked($transferLock);
        $transIp->client()->domains()->update($domain);
        logModuleCall("transip", "SaveRegistrarLock", $logArray, NULL);
        return ["success" => true];
    } catch (Throwable $e) {
        logModuleCall("transip", "SaveRegistrarLock", $logArray, $e->getMessage());
        return ["error" => $e->getMessage()];
    }
}

function transip_GetEPPCode($params)
{
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $domainName = $params["sld"] . "." . $params["tld"];
        $result = $transIp->client()->domains()->getByName($domainName)->getAuthCode();
        $authCode = ["eppcode" => $result];
        logModuleCall("transip", "GetEPPCode", $domainName, $authCode);
        return $authCode;
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function transip_SaveContactDetails($params)
{
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $domainName = $params["sld"] . "." . $params["tld"];
        $contacts = transip_contactDetailsToWhoisContacts($params["contactdetails"]);
        $transIp->client()->domainContact()->update($domainName, $contacts);
        logModuleCall("transip", "SaveContactDetails", [$domainName, $contacts], "");
        return [];
    } catch (Exception $exception) {
        return ["error" => $exception->getMessage()];
    }
}

function transip_GetContactDetails($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $contacts = $transIp->client()->domainContact()->getByDomainName($domainName);
        $result = transip_whoisContactsToContactDetails($contacts);
        logModuleCall("transip", "GetContactDetails", $domainName, $result);
        return $result;
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function transip_GetDNS($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $dnsRecords = $transIp->client()->domainDns()->getByDomainName($domainName);
        $records = [];
        foreach ($dnsRecords as $dnsEntry) {
            $records[] = ["hostname" => $dnsEntry->getName(), "type" => $dnsEntry->getType(), "address" => $dnsEntry->getContent()];
        }
        logModuleCall("transip", "GetDNS", $domainName, $dnsRecords, $records);
        return $records;
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function transip_SaveDNS($params)
{
    $domainName = $params["sld"] . "." . $params["tld"];
    try {
        $transIp = WHMCS\Module\Registrar\Transip\TransIP::init($params);
        $dnsEntries = [];
        foreach ($params["dnsrecords"] as $dnsRecord) {
            if (!(empty($dnsRecord["hostname"]) || empty($dnsRecord["address"]))) {
                $dnsEntries[] = (new Transip\Api\Library\Entity\Domain\DnsEntry())->setName($dnsRecord["hostname"])->setExpire(86400)->setType($dnsRecord["type"])->setContent($dnsRecord["address"]);
            }
        }
        $transIp->client()->domainDns()->update($domainName, $dnsEntries);
        logModuleCall("transip", "SaveDNS", [$domainName, $dnsEntries], "");
        return [];
    } catch (Exception $exception) {
        return ["error" => $exception->getMessage()];
    }
}

function transip_whoisContactsToContactDetails($contacts)
{
    $contactTypeMapping = WHMCS\Module\Registrar\Transip\TransIP::getContactTypeMapping();
    $contactFieldMapping = WHMCS\Module\Registrar\Transip\TransIP::getContactFieldMapping();
    $contactDetails = [];
    foreach ($contacts as $contact) {
        if (isset($contactTypeMapping[$contact->getType()])) {
            $whmcsType = $contactTypeMapping[$contact->getType()];
            if (!isset($contactDetails[$whmcsType])) {
                $details = [];
                foreach ($contactFieldMapping as $transipField => $whmcsField) {
                    $setter = "get" . ucfirst($transipField);
                    $details[$whmcsField] = $contact->{$setter}();
                }
                $contactDetails[$whmcsType] = $details;
            }
        }
    }
    return $contactDetails;
}

function transip_contactDetailsToWhoisContacts($contactDetails)
{
    $contactTypeMapping = WHMCS\Module\Registrar\Transip\TransIP::getContactTypeMapping();
    $contactFieldMapping = WHMCS\Module\Registrar\Transip\TransIP::getContactFieldMapping();
    $contacts = [];
    foreach ($contactTypeMapping as $transipType => $whmcsType) {
        if (isset($contactDetails[$whmcsType])) {
            $contact = new Transip\Api\Library\Entity\Domain\WhoisContact();
            $contact->setType($transipType);
            foreach ($contactFieldMapping as $transipField => $whmcsField) {
                if (isset($contactDetails[$whmcsType][$whmcsField])) {
                    $contact->{"set" . ucfirst($transipField)}($contactDetails[$whmcsType][$whmcsField]);
                }
            }
            $contacts[] = $contact;
        }
    }
    return $contacts;
}
