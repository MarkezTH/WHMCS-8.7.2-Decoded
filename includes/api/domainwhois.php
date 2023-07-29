<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$domain = App::getFromRequest("domain");
if (!$domain) {
    return ["result" => "error", "message" => "Domain not valid"];
}
$domains = new WHMCS\Domains();
$domainparts = $domains->splitAndCleanDomainInput($domain);
$isValid = $domains->checkDomainisValid($domainparts);
if ($isValid) {
    $whois = new WHMCS\WHOIS();
    if ($whois->canLookup($domainparts["tld"])) {
        $result = $whois->lookup($domainparts);
        $whois->logLookup();
        $userRequestedResponseType = is_object($request) ? $request->getResponseFormat() : NULL;
        if (is_null($userRequestedResponseType) || WHMCS\Api\ApplicationSupport\Http\ResponseFactory::isTypeHighlyStructured($userRequestedResponseType)) {
            $whois = $result["whois"] ?? NULL;
        } else {
            $whois = urlencode($result["whois"] ?? NULL);
        }
        if (function_exists("mb_convert_encoding") && $userRequestedResponseType == WHMCS\Api\ApplicationSupport\Http\ResponseFactory::RESPONSE_FORMAT_JSON) {
            $whois = mb_convert_encoding($whois, "UTF-8", mb_detect_encoding($whois));
        }
        $result["whois"] = $whois;
        $apiresults = ["result" => "success", "status" => $result["result"], "whois" => $result["whois"]];
    } else {
        $apiresults = ["result" => "error", "message" => "The given TLD is not supported for WHOIS lookups"];
        return false;
    }
} else {
    $apiresults = ["result" => "error", "message" => "Domain not valid"];
    return false;
}
