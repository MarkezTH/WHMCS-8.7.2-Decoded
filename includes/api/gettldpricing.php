<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getTLDList")) {
    require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "domainfunctions.php";
}
$currencyId = (int) App::getFromRequest("currencyid");
$userId = (int) App::getFromRequest("clientid");
$clientGroupId = 0;
if ($userId) {
    $client = WHMCS\User\Client::find($userId);
    $userId = $client->id;
    $currencyId = $client->currencyId;
    $clientGroupId = $client->groupId;
}
$currency = getCurrency(NULL, $currencyId);
$pricing = [];
$result = WHMCS\Database\Capsule::table("tblpricing")->whereIn("type", ["domainregister", "domaintransfer", "domainrenew"])->where("currency", $currency["id"])->where("tsetupfee", 0)->get()->all();
foreach ($result as $data) {
    $pricing[$data->relid][$data->type] = get_object_vars($data);
}
if ($clientGroupId) {
    $result2 = WHMCS\Database\Capsule::table("tblpricing")->whereIn("type", ["domainregister", "domaintransfer", "domainrenew"])->where("currency", $currency["id"])->where("tsetupfee", $clientGroupId)->get()->all();
    foreach ($result2 as $data) {
        $pricing[$data->relid][$data->type] = get_object_vars($data);
    }
}
$tldIds = [];
$tldGroups = [];
$tldAddons = [];
$result = WHMCS\Database\Capsule::table("tbldomainpricing")->get(["id", "extension", "dnsmanagement", "emailforwarding", "idprotection", "group"])->all();
foreach ($result as $data) {
    $ext = ltrim($data->extension, ".");
    $tldIds[$ext] = $data->id;
    $tldGroups[$ext] = $data->group != "" && $data->group != "none" ? $data->group : "";
    $tldAddons[$ext] = ["dns" => (bool) $data->dnsmanagement, "email" => (bool) $data->emailforwarding, "idprotect" => (bool) $data->idprotection];
}
$extensions = WHMCS\Domains\Extension::all();
$extensionsByTld = [];
foreach ($extensions as $extension) {
    $tld = ltrim($extension->extension, ".");
    $extensionsByTld[$tld] = $extension;
}
$tldList = array_keys($extensionsByTld);
$periods = ["msetupfee" => 1, "qsetupfee" => 2, "ssetupfee" => 3, "asetupfee" => 4, "bsetupfee" => 5, "monthly" => 6, "quarterly" => 7, "semiannually" => 8, "annually" => 9, "biennially" => 10];
$apiresults = ["result" => "success", "currency" => $currency, "pricing" => []];
$tldCategories = new WHMCS\Domain\TopLevel\Categories();
foreach ($tldList as $tld) {
    $tldId = $tldIds[$tld];
    $apiresults["pricing"][$tld]["categories"] = $tldCategories->getCategoriesByTld($tld);
    $apiresults["pricing"][$tld]["addons"] = $tldAddons[$tld];
    $apiresults["pricing"][$tld]["group"] = $tldGroups[$tld];
    foreach (["domainregister", "domaintransfer", "domainrenew"] as $type) {
        foreach ($pricing[$tldId][$type] as $key => $price) {
            if (array_key_exists($key, $periods) && ($type == "domainregister" && 0 <= $price || $type == "domaintransfer" && 0 <= $price || $type == "domainrenew" && 0 <= $price)) {
                $apiresults["pricing"][$tld][str_replace("domain", "", $type)][$periods[$key]] = $price;
            }
        }
    }
    if (isset($extensionsByTld[$tld])) {
        $extension = $extensionsByTld[$tld];
        $apiresults["pricing"][$tld]["grace_period"] = NULL;
        if (0 <= $extension->grace_period_fee) {
            $gracePeriodFee = new WHMCS\View\Formatter\Price(convertCurrency($extension->grace_period_fee, null, $currency["id"], 1), $currency);
            $gracePeriodDays = 0 <= $extension->grace_period ? $extension->grace_period : $extension->defaultGracePeriod;
            $apiresults["pricing"][$tld]["grace_period_days"] = $gracePeriodDays;
            $apiresults["pricing"][$tld]["grace_period_fee"] = $gracePeriodFee;
            $apiresults["pricing"][$tld]["grace_period"] = ["days" => $gracePeriodDays, "price" => $gracePeriodFee];
        }
        $apiresults["pricing"][$tld]["redemption_period"] = NULL;
        if (0 <= $extension->redemption_grace_period_fee) {
            $redemptionGracePeriodFee = new WHMCS\View\Formatter\Price(convertCurrency($extension->redemption_grace_period_fee, null, $currency["id"], 1), $currency);
            $redemptionGracePeriodDays = 0 <= $extension->redemption_grace_period ? $extension->redemption_grace_period : $extension->defaultRedemptionGracePeriod;
            $apiresults["pricing"][$tld]["redemption_period_days"] = $redemptionGracePeriodDays;
            $apiresults["pricing"][$tld]["redemption_period_fee"] = $redemptionGracePeriodFee;
            $apiresults["pricing"][$tld]["redemption_period"] = ["days" => $redemptionGracePeriodDays, "price" => $redemptionGracePeriodFee];
        }
    }
}
