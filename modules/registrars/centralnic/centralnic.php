<?php

function centralnic_MetaData()
{
    return ["DisplayName" => "CentralNic Reseller"];
}

function centralnic_getConfigArray()
{
    $configArray = ["Description" => ["Type" => "System", "Value" => "CentralNic Reseller: the trusted worldwide solution for your domain reselling needs."], "Username" => ["Type" => "text", "Size" => "25"], "Password" => ["Type" => "password", "Size" => "25"], "TestMode" => ["FriendlyName" => "Enable Test Mode", "Type" => "yesno"], "ProxyServer" => ["FriendlyName" => "Proxy Server", "Type" => "text", "Description" => "HTTP(S) Proxy Server (Optional)"], "DNSSEC" => ["Type" => "yesno", "Description" => "Display the DNSSEC Management functionality in the domain details"]];
    return $configArray;
}

function centralnic_config_validate($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->configValidate();
    } catch (Exception $e) {
        throw new WHMCS\Exception\Module\InvalidConfiguration($e->getMessage());
    }
}

function centralnic_RegisterDomain($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->registerDomain();
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_TransferDomain($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->transferDomain();
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_RenewDomain($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->renewDomain();
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetDomainInformation($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getDomainInfo();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetEmailForwarding($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getEmailForwarding();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_SaveEmailForwarding($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->saveEmailForwarding();
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetNameservers($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getNameservers();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_SaveNameservers($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->saveNameservers();
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetRegistrarLock($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getRegistrarLock();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_SaveRegistrarLock($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->saveRegistrarLock();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => "Not implemented."];
    }
}

function centralnic_GetContactDetails($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getContactDetails();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_SaveContactDetails($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->saveContactDetails();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_ResendIRTPVerificationEmail($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->resendIRTPVerificationEmail();
        return ["success" => true];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetDNS($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getDNS();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_SaveDNS($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->saveDNS();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_IDProtectToggle($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->toggleIdProtection();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetEPPCode($params)
{
    try {
        $eppCode = (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getEppCode();
        return ["eppcode" => $eppCode];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_ReleaseDomain($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->releaseDomain();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_RegisterNameserver($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->registerNameserver();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_ModifyNameserver($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->modifyNameserver();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_DeleteNameserver($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->deleteNameserver();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_RequestDelete($params)
{
    try {
        (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->requestDelete();
        return ["success" => "success"];
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_ClientAreaCustomButtonArray($params)
{
    if ($params["DNSSEC"] == "on") {
        return ["DNSSEC Management" => "dnssec"];
    }
    return [];
}

function centralnic_ClientAreaAllowedFunctions($params)
{
    if ($params["DNSSEC"] == "on") {
        return ["DNSSEC Management" => "dnssec"];
    }
    return [];
}

function centralnic_Sync($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->syncDomain();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_TransferSync($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->transferSync();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_GetTldPricing($ResultsList, $params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getTldPricing();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_CheckAvailability($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->checkAvailability();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_DomainSuggestionOptions()
{
    return ["maxResults" => ["FriendlyName" => AdminLang::trans("general.maxsuggestions"), "Type" => "dropdown", "Options" => [10 => "10", 25 => "25", 50 => "50", 75 => "75", 99 => "99 (" . AdminLang::trans("global.recommended") . ")"], "Default" => "99", "Description" => ""], "ipAddress" => ["FriendlyName" => AdminLang::trans("general.ipGeolocation"), "Type" => "yesno", "Description" => AdminLang::trans("global.ticktoenable")], "filterContent" => ["FriendlyName" => AdminLang::trans("general.suggestadultdomains"), "Type" => "yesno", "Description" => AdminLang::trans("global.ticktoenable")]];
}

function centralnic_GetDomainSuggestions($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->getDomainSuggestions();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}

function centralnic_dnssec($params)
{
    try {
        return (new WHMCS\Module\Registrar\CentralNic\RRPProxyController($params))->handleDnsSec();
    } catch (Exception $e) {
        return ["error" => $e->getMessage()];
    }
}
