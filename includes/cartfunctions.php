<?php

function bundlesConvertBillingCycle($cycle)
{
    return str_replace(["-", " "], "", strtolower($cycle));
}

function bundlesStepCompleteRedirect($lastconfig)
{
    $i = $lastconfig["i"];
    if ($lastconfig["type"] == "product" && !isset($_SESSION["cart"]["products"][$i]["bnum"])) {
        return false;
    }
    if ($lastconfig["type"] == "domain" && !isset($_SESSION["cart"]["domains"][$i]["bnum"])) {
        return false;
    }
    if (is_array($_SESSION["cart"]["bundle"])) {
        $bnum = count($_SESSION["cart"]["bundle"]);
        $bnum--;
        $bundledata = $_SESSION["cart"]["bundle"][$bnum];
        $bid = $bundledata["bid"];
        $step = $bundledata["step"];
        $complete = $bundledata["complete"];
        if (!$complete) {
            $data = get_query_vals("tblbundles", "", ["id" => $bid]);
            $bid = $data["id"];
            $itemdata = $data["itemdata"];
            $itemdata = safe_unserialize($itemdata);
            $_SESSION["cart"]["bundle"][$bnum]["step"] = $step + 1;
            $step = $_SESSION["cart"]["bundle"][$bnum]["step"];
            $vals = $itemdata[$step] ?? NULL;
            if (is_array($vals)) {
                if ($vals["type"] == "product") {
                    $vals["bnum"] = $bnum;
                    $vals["bitem"] = $step;
                    $vals["billingcycle"] = bundlesconvertbillingcycle($vals["billingcycle"]);
                    $_SESSION["cart"]["passedvariables"] = $vals;
                    unset($_SESSION["cart"]["lastconfigured"]);
                    redir("a=add&pid=" . $vals["pid"]);
                } else {
                    if ($vals["type"] == "domain") {
                        $vals["bnum"] = $bnum;
                        $vals["bitem"] = $step;
                        $_SESSION["cart"]["passedvariables"] = $vals;
                        unset($_SESSION["cart"]["lastconfigured"]);
                        redir("a=add&domain=register");
                    }
                }
            } else {
                $_SESSION["cart"]["bundle"][$bnum]["complete"] = 1;
                $step = $_SESSION["cart"]["bundle"][$bnum]["complete"];
            }
        }
    }
}

function bundlesValidateProductConfig($key, $billingcycle, $configoptions, $addons)
{
    global $_LANG;
    $proddata = $_SESSION["cart"]["products"][$key];
    if (!isset($proddata["bnum"])) {
        return false;
    }
    $bid = $_SESSION["cart"]["bundle"][$proddata["bnum"]]["bid"];
    if (!$bid) {
        return false;
    }
    $data = get_query_vals("tblbundles", "", ["id" => $bid]);
    $itemdata = $data["itemdata"];
    $itemdata = safe_unserialize($itemdata);
    $proditemdata = $itemdata[$proddata["bitem"]];
    $errors = [];
    $productname = WHMCS\Product\Product::getProductName($proddata["pid"]);
    $addonsCollection = collect($addons);
    if ($proditemdata["billingcycle"] && bundlesconvertbillingcycle($proditemdata["billingcycle"]) != $billingcycle) {
        $errors[] = sprintf($_LANG["bundlewarningproductcycle"], $proditemdata["billingcycle"], $productname);
    }
    $errors = array_merge($errors, cartValidateProductConfigOptions($proddata["pid"], $proditemdata, $billingcycle, $configoptions));
    if ($proditemdata["addons"]) {
        foreach ($proditemdata["addons"] as $addonId) {
            if ($addonsCollection->where("addonid", $addonId)->count() < 1 && !$addonsCollection->contains($addonId)) {
                $errors[] = sprintf(Lang::trans("bundlewarningproductaddonreq"), WHMCS\Database\Capsule::table("tbladdons")->where("id", $addonId)->value("name"), $productname);
            }
        }
    }
    array_walk($errors, function (&$message) {
        $message = sprintf("<li>%s</li>", $message);
    });
    return implode("\n", $errors);
}

function bundlesValidateCheckout()
{
    global $_LANG;
    if (!isset($_SESSION["cart"]["bundle"])) {
        return "";
    }
    $bundlesess = $_SESSION["cart"]["bundle"];
    foreach ($bundlesess as $k => $v) {
        unset($bundlesess[$k]["warnings"]);
    }
    $bundledata = $warnings = [];
    foreach ($bundlesess as $bnum => $vals) {
        $bid = $vals["bid"];
        $data = get_query_vals("tblbundles", "", ["id" => $bid]);
        $allowpromo = $data["allowpromo"];
        $itemdata = $data["itemdata"];
        $itemdata = safe_unserialize($itemdata);
        $bundledata[$bid] = $itemdata;
        if (isset($_SESSION["cart"]["promo"]) && $_SESSION["cart"]["promo"] && !$allowpromo) {
            $warnings[] = $_LANG["bundlewarningpromo"];
            $bundlesess[$bnum]["warnings"] = 1;
        }
    }
    $numitemsperbundle = $productbundleddomains = $domainsincart = [];
    if (!empty($_SESSION["cart"]["domains"])) {
        foreach ($_SESSION["cart"]["domains"] as $k => $values) {
            $domainsincart[$values["domain"]] = $k;
        }
    }
    foreach ($_SESSION["cart"]["products"] as $k => $v) {
        if (isset($v["bnum"])) {
            $bnum = $v["bnum"];
            $bitem = $v["bitem"];
            $pid = $v["pid"];
            $domain = $v["domain"];
            $billingcycle = $v["billingcycle"];
            $configoptions = $v["configoptions"];
            $addonsCollection = collect($v["addons"]);
            $bid = $_SESSION["cart"]["bundle"][$bnum]["bid"];
            $itemdata = $bundledata[$bid][$bitem];
            if ($itemdata["type"] != "product" || $pid != $itemdata["pid"]) {
                unset($_SESSION["cart"]["products"][$k]["bnum"]);
                unset($_SESSION["cart"]["products"][$k]["bitem"]);
            } else {
                if (isset($numitemsperbundle[$bnum])) {
                    $numitemsperbundle[$bnum]++;
                } else {
                    $numitemsperbundle[$bnum] = 1;
                }
                $productname = WHMCS\Product\Product::getProductName($pid);
                if ($itemdata["billingcycle"] && bundlesconvertbillingcycle($itemdata["billingcycle"]) != $billingcycle) {
                    $warnings[] = sprintf($_LANG["bundlewarningproductcycle"], $itemdata["billingcycle"], $productname);
                    $bundlesess[$bnum]["warnings"] = 1;
                }
                if (isset($itemdata["configoption"]) && is_array($itemdata["configoption"])) {
                    $warnings = array_merge($warnings, cartValidateProductConfigOptions($v["pid"], $itemdata, $billingcycle, $configoptions));
                }
                if ($itemdata["addons"]) {
                    foreach ($itemdata["addons"] as $addonId) {
                        if ($addonsCollection->where("addonid", $addonId)->count() < 1 && !$addonsCollection->contains($addonId)) {
                            $warnings[] = sprintf(Lang::trans("bundlewarningproductaddonreq"), WHMCS\Database\Capsule::table("tbladdons")->where("id", $addonId)->value("name"), $productname);
                            $bundlesess[$bnum]["warnings"] = 1;
                        }
                    }
                }
                if (array_key_exists($domain, $domainsincart)) {
                    $domid = $domainsincart[$domain];
                    $v = $_SESSION["cart"]["domains"][$domid];
                    $regperiod = $v["regperiod"];
                    if (is_array($itemdata["tlds"])) {
                        $domaintld = explode(".", $domain, 2);
                        $domaintld = "." . $domaintld[1];
                        if (!in_array($domaintld, $itemdata["tlds"])) {
                            $warnings[] = sprintf($_LANG["bundlewarningdomaintld"], implode(",", $itemdata["tlds"]), $domain);
                            $bundlesess[$bnum]["warnings"] = 1;
                        }
                    }
                    if ($itemdata["regperiod"] && $itemdata["regperiod"] != $regperiod) {
                        $warnings[] = sprintf($_LANG["bundlewarningdomainregperiod"], $itemdata["regperiod"], $domain);
                        $bundlesess[$bnum]["warnings"] = 1;
                    }
                    if (is_array($itemdata["domaddons"])) {
                        foreach ($itemdata["domaddons"] as $domaddon) {
                            if (!$v[$domaddon]) {
                                $warnings[] = sprintf($_LANG["bundlewarningdomainaddon"], $_LANG["domain" . $domaddon], $domain);
                                $bundlesess[$bnum]["warnings"] = 1;
                            }
                        }
                    }
                    $productbundleddomains[$domain] = [$bnum, $bitem];
                } else {
                    if (is_array($itemdata["tlds"]) || $itemdata["regperiod"] || is_array($itemdata["domaddons"])) {
                        $warnings[] = sprintf($_LANG["bundlewarningdomainreq"], $productname);
                        $bundlesess[$bnum]["warnings"] = 1;
                    }
                }
            }
            if (0 < count($warnings)) {
                $bundlesess[$bnum]["warnings"] = 1;
            }
        }
    }
    foreach ($_SESSION["cart"]["domains"] ?? [] as $k => $v) {
        if (isset($v["bnum"])) {
            $bnum = $v["bnum"];
            $bitem = $v["bitem"];
            $domain = $v["domain"];
            $regperiod = $v["regperiod"];
            $bid = $_SESSION["cart"]["bundle"][$bnum]["bid"];
            $itemdata = $bundledata[$bid][$bitem];
            if ($itemdata["type"] != "domain") {
                unset($_SESSION["cart"]["domains"][$k]["bnum"]);
                unset($_SESSION["cart"]["domains"][$k]["bitem"]);
            } else {
                if (isset($numitemsperbundle[$bnum])) {
                    $numitemsperbundle[$bnum]++;
                } else {
                    $numitemsperbundle[$bnum] = 1;
                }
                if (is_array($itemdata["tlds"])) {
                    $domaintld = explode(".", $domain, 2);
                    $domaintld = "." . $domaintld[1];
                    if (!in_array($domaintld, $itemdata["tlds"])) {
                        $warnings[] = sprintf($_LANG["bundlewarningdomaintld"], implode(",", $itemdata["tlds"]), $domain);
                        $bundlesess[$bnum]["warnings"] = 1;
                    }
                }
                if ($itemdata["regperiod"] && $itemdata["regperiod"] != $regperiod) {
                    $warnings[] = sprintf($_LANG["bundlewarningdomainregperiod"], $itemdata["regperiod"], $domain);
                    $bundlesess[$bnum]["warnings"] = 1;
                }
                if (is_array($itemdata["addons"])) {
                    foreach ($itemdata["addons"] as $domaddon) {
                        if (!$v[$domaddon]) {
                            $warnings[] = sprintf($_LANG["bundlewarningdomainaddon"], $_LANG["domain" . $domaddon], $domain);
                            $bundlesess[$bnum]["warnings"] = 1;
                        }
                    }
                }
            }
        }
    }
    foreach ($bundlesess as $bnum => $vals) {
        $bid = $vals["bid"];
        $bundletotalitems = count($bundledata[$bid]);
        if ($bundletotalitems != ($numitemsperbundle[$bnum] ?? NULL)) {
            unset($bundlesess[$bnum]);
        }
    }
    $_SESSION["cart"]["bundle"] = $bundlesess;
    $_SESSION["cart"]["prodbundleddomains"] = $productbundleddomains;
    return $warnings;
}

function bundlesGetProductPriceOverride($type, $key)
{
    global $currency;
    $proddata = $_SESSION["cart"][$type . "s"][$key];
    $prodbundleddomain = false;
    if (!isset($proddata["bnum"]) && $type == "domain") {
        $domain = $proddata["domain"];
        if (isset($_SESSION["cart"]["prodbundleddomains"][$domain]) && is_array($_SESSION["cart"]["prodbundleddomains"][$domain])) {
            $proddata["bnum"] = $_SESSION["cart"]["prodbundleddomains"][$domain][0];
            $proddata["bitem"] = $_SESSION["cart"]["prodbundleddomains"][$domain][1];
        }
    }
    if (!isset($proddata["bnum"])) {
        return false;
    }
    $bid = $_SESSION["cart"]["bundle"][$proddata["bnum"]]["bid"];
    if (!$bid) {
        return false;
    }
    $bundlewarnings = $_SESSION["cart"]["bundle"][$proddata["bnum"]]["warnings"] ?? NULL;
    if ($bundlewarnings) {
        return false;
    }
    $data = get_query_vals("tblbundles", "", ["id" => $bid]);
    $itemdata = $data["itemdata"];
    $itemdata = safe_unserialize($itemdata);
    if ($type == "product" && $itemdata[$proddata["bitem"]]["priceoverride"]) {
        return convertCurrency($itemdata[$proddata["bitem"]]["price"], 1, $currency["id"]);
    }
    if ($type == "domain" && $itemdata[$proddata["bitem"]]["dompriceoverride"]) {
        return convertCurrency($itemdata[$proddata["bitem"]]["domprice"], 1, $currency["id"]);
    }
    return false;
}

function cartAvailabilityResultsBackwardsCompat(WHMCS\Domains\Domain $domainToLookup, WHMCS\Domains\DomainLookup\SearchResult $searchResult, $matchString)
{
    $availabilityResults = [["domain" => $searchResult->getDomain(), "status" => $searchResult->getStatus(), "regoptions" => $searchResult->getStatus() == $matchString ? $searchResult->pricing()->toArray() : [], "suggestion" => false]];
    $lookupProvider = WHMCS\Domains\DomainLookup\Provider::factory();
    foreach ($lookupProvider->getSuggestions($domainToLookup) as $suggestion) {
        $availabilityResults[] = ["domain" => $suggestion->getDomain(), "status" => $suggestion->getStatus(), "regoptions" => $suggestion->getStatus() == $matchString ? $suggestion->pricing()->toArray() : [], "suggestion" => true];
    }
    return $availabilityResults;
}

function cartCheckIfDomainAlreadyOrdered(WHMCS\Domains\Domain $domainToCheck)
{
    $existingDomains = WHMCS\Database\Capsule::table("tbldomains")->where("domain", "=", $domainToCheck->getRawDomain())->whereIn("status", ["Active", "Pending", "Pending Registration", "Pending Transfer"])->get(["domain"])->all();
    foreach ($existingDomains as $domain) {
        if ($domain->domain == $domainToCheck->getRawDomain()) {
            return true;
        }
    }
    return false;
}

function cartValidationOnCheckout($clientId, $setSession = false)
{
    $validate = new WHMCS\Validate();
    $cartCheckoutHookData = $_REQUEST;
    $promoCode = "";
    $cartSession = WHMCS\Session::get("cart");
    if (!is_array($cartSession)) {
        $cartSession = [];
    }
    if (array_key_exists("promo", $cartSession)) {
        $promoCode = $cartSession["promo"];
    }
    $cartCheckoutHookData["promocode"] = $promoCode;
    $cartCheckoutHookData["userid"] = $clientId;
    $cartCheckoutHookData["clientId"] = $clientId;
    run_validate_hook($validate, "ShoppingCartValidateCheckout", $cartCheckoutHookData);
    if ($clientId && WHMCS\Config\Setting::getValue("EnableTOSAccept")) {
        $validate->validate("required", "accepttos", "ordererroraccepttos");
    }
    $cartCaptcha = new WHMCS\Utility\Captcha();
    if ($cartCaptcha->isEnabled() && !WHMCS\Session::getAndDelete("CartValidationOnCheckout")) {
        $captchaValidated = $cartCaptcha->validateAppropriateCaptcha(WHMCS\Utility\Captcha::FORM_CHECKOUT_COMPLETION, $validate);
        if ($setSession && $captchaValidated && !$validate->hasErrors()) {
            WHMCS\Session::set("CartValidationOnCheckout", true);
        }
    }
    return $validate->getHTMLErrorOutput();
}

function cartValidateProductConfigOptions($productId, $productItemData, $billingCycle, $selectedConfigOptions)
{
    if (!function_exists("getCartConfigOptions")) {
        require_once ROOTDIR . "/includes/configoptionsfunctions.php";
    }
    $productConfigOptions = collect(getCartConfigOptions($productId, [], $billingCycle));
    $errors = [];
    foreach ($productItemData["configoption"] as $optionId => $optionItemIdOrValue) {
        if ($optionItemIdOrValue != $selectedConfigOptions[$optionId]) {
            $option = $productConfigOptions->where("id", $optionId);
            $optionItem = $option->pluck("options")->flatten(1)->firstWhere("id", $optionItemIdOrValue);
            $option = $option->first();
            if ($option) {
                $errors[] = cartGetErrorForRestrictedConfigOption($option, $optionItem, $optionItemIdOrValue);
            }
        }
    }
    return $errors;
}

function cartGetErrorForRestrictedConfigOption($option, $optionItem, $optionItemIdOrValue)
{
    $lang = DI::make("lang");
    $error = "";
    switch ($option["optiontype"]) {
        case "1":
        case "2":
            $error = sprintf($lang->trans("bundlewarningproductconfopreq"), $optionItem["name"], $option["optionname"]);
            break;
        case "3":
            $message = "bundlewarningproductconfopyesnodisable";
            if ($optionItemIdOrValue) {
                $message = "bundlewarningproductconfopyesnoenable";
            }
            $error = sprintf($lang->trans($message), $option["optionname"]);
            unset($message);
            break;
        case "4":
            $error = sprintf($lang->trans("bundlewarningproductconfopqtyreq"), $optionItemIdOrValue, $option["optionname"]);
            break;
        default:
            return $error;
    }
}
