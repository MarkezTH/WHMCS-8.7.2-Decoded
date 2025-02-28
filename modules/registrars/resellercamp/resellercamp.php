<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("resellerclub_GetConfigArray")) {
    require ROOTDIR . "/modules/registrars/resellerclub/resellerclub.php";
}

function resellercamp_MetaData()
{
    return ["DisplayName" => "ResellerCamp", "APIVersion" => "1.1", "NonLinearRegistrationPricing" => true];
}

function resellercamp_GetConfigArray()
{
    $vals = resellerclub_GetConfigArray();
    unset($vals["Description"]);
    return $vals;
}

function resellercamp_config_validate($params)
{
    resellerclub_config_validate($params);
}

function resellercamp_GetNameservers($params)
{
    return resellerclub_GetNameservers($params);
}

function resellercamp_SaveNameservers($params)
{
    return resellerclub_SaveNameservers($params);
}

function resellercamp_GetRegistrarLock($params)
{
    return resellerclub_GetRegistrarLock($params);
}

function resellercamp_SaveRegistrarLock($params)
{
    return resellerclub_SaveRegistrarLock($params);
}

function resellercamp_RegisterDomain($params)
{
    return resellerclub_RegisterDomain($params);
}

function resellercamp_TransferDomain($params)
{
    return resellerclub_TransferDomain($params);
}

function resellercamp_RenewDomain($params)
{
    return resellerclub_RenewDomain($params);
}

function resellercamp_GetContactDetails($params)
{
    return resellerclub_GetContactDetails($params);
}

function resellercamp_SaveContactDetails($params)
{
    return resellerclub_SaveContactDetails($params);
}

function resellercamp_GetEPPCode($params)
{
    return resellerclub_GetEPPCode($params);
}

function resellercamp_RegisterNameserver($params)
{
    return resellerclub_RegisterNameserver($params);
}

function resellercamp_ModifyNameserver($params)
{
    return resellerclub_ModifyNameserver($params);
}

function resellercamp_DeleteNameserver($params)
{
    return resellerclub_DeleteNameserver($params);
}

function resellercamp_RequestDelete($params)
{
    return resellerclub_RequestDelete($params);
}

function resellercamp_GetDNS($params)
{
    return resellerclub_GetDNS($params);
}

function resellercamp_SaveDNS($params)
{
    return resellerclub_SaveDNS($params);
}

function resellercamp_GetEmailForwarding($params)
{
    return resellerclub_GetEmailForwarding($params);
}

function resellercamp_SaveEmailForwarding($params)
{
    return resellerclub_SaveEmailForwarding($params);
}

function resellercamp_ReleaseDomain($params)
{
    return resellerclub_ReleaseDomain($params);
}

function resellercamp_IDProtectToggle($params)
{
    return resellerclub_IDProtectToggle($params);
}

function resellercamp_Sync($params)
{
    return resellerclub_Sync($params);
}

function resellercamp_TransferSync($params)
{
    return resellerclub_TransferSync($params);
}

function resellercamp_CheckAvailability($params)
{
    return resellerclub_CheckAvailability($params);
}

function resellercamp_GetDomainSuggestions($params)
{
    return resellerclub_GetDomainSuggestions($params);
}

function resellercamp_GetPremiumPrice($params)
{
    return resellerclub_GetPremiumPrice($params);
}

function resellercamp_DomainSuggestionOptions()
{
    return resellerclub_DomainSuggestionOptions();
}

function resellercamp_GetDomainInformation($params)
{
    return resellerclub_GetDomainInformation($params);
}

function resellercamp_ResendIRTPVerificationEmail($params)
{
    return resellerclub_ResendIRTPVerificationEmail($params);
}

function resellercamp_GetTldPricing($params)
{
    return resellerclub_GetTldPricing($params);
}

function resellercamp_AdditionalDomainFields($params)
{
    return resellerclub_AdditionalDomainFields($params);
}
