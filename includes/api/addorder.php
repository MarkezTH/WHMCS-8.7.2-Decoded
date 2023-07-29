<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("addClient")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("getCartConfigOptions")) {
    require ROOTDIR . "/includes/configoptionsfunctions.php";
}
if (!function_exists("getTLDPriceList")) {
    require ROOTDIR . "/includes/domainfunctions.php";
}
if (!function_exists("updateInvoiceTotal")) {
    require ROOTDIR . "/includes/invoicefunctions.php";
}
if (!function_exists("createInvoices")) {
    require ROOTDIR . "/includes/processinvoices.php";
}
if (!function_exists("calcCartTotals")) {
    require ROOTDIR . "/includes/orderfunctions.php";
}
if (!function_exists("ModuleBuildParams")) {
    require ROOTDIR . "/includes/modulefunctions.php";
}
if (!empty($promocode) && empty($promooverride)) {
    define("CLIENTAREA", true);
}
$whmcs = WHMCS\Application::getInstance();
try {
    $client = WHMCS\User\Client::findOrFail($whmcs->get_req_var("clientid"));
    $blockedStatus = ["Closed"];
    if (in_array($client->status, $blockedStatus)) {
        $apiresults = ["result" => "error", "message" => "Unable to add order when client status is " . $client->status];
        return NULL;
    }
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Client ID Not Found"];
    return NULL;
}
$userid = (int) $client->id;
$gatewayModules = WHMCS\Module\GatewaySetting::getActiveGatewayModules();
if (!in_array($paymentmethod, $gatewayModules)) {
    $apiresults = ["result" => "error", "message" => "Invalid Payment Method. Valid options include " . implode(",", $gatewayModules)];
} else {
    if (!empty($clientip)) {
        if (filter_var($clientip, FILTER_VALIDATE_IP) === false) {
            $apiresults = ["result" => "error", "message" => "Invalid IP address provided for 'clientip'"];
            return NULL;
        }
        global $remote_ip;
        $remote_ip = $clientip;
        WHMCS\Order\Order::creating(function ($model) use($clientip) {
            $model->setAttribute("ipAddress", $clientip);
        });
    }
    unset($clientip);
    global $currency;
    $currency = getCurrency($userid);
    $_SESSION["cart"] = [];
    if (isset($pid) && is_array($pid)) {
        foreach ($pid as $i => $prodid) {
            if ($prodid) {
                $proddomain = $domain[$i];
                $prodbillingcycle = $billingcycle[$i];
                $configoptionsarray = [];
                $customfieldsarray = [];
                $domainfieldsarray = [];
                $addonsarray = [];
                if (isset($addons[$i]) && $addons[$i]) {
                    foreach (explode(",", $addons[$i]) as $addonForPid) {
                        $addonsarray[] = ["addonid" => $addonForPid, "qty" => 1];
                    }
                }
                if (isset($configoptions[$i]) && $configoptions[$i]) {
                    $configoptionsarray = safe_unserialize(base64_decode($configoptions[$i]));
                }
                if (isset($customfields[$i]) && $customfields[$i]) {
                    $customfieldsarray = safe_unserialize(base64_decode($customfields[$i]));
                }
                $productarray = ["pid" => $prodid, "domain" => $proddomain, "billingcycle" => $prodbillingcycle, "server" => "", "configoptions" => $configoptionsarray, "customfields" => $customfieldsarray, "addons" => $addonsarray, "strictDomain" => false];
                if (!empty($hostname[$i]) || !empty($ns1prefix[$i]) || !empty($ns2prefix[$i]) || !empty($rootpw[$i])) {
                    $productarray["server"] = ["hostname" => $hostname[$i] ?? NULL, "ns1prefix" => $ns1prefix[$i] ?? NULL, "ns2prefix" => $ns2prefix[$i] ?? NULL, "rootpw" => $rootpw[$i] ?? NULL];
                }
                if (isset($priceoverride[$i]) && strlen($priceoverride[$i])) {
                    $productarray["priceoverride"] = $priceoverride[$i];
                }
                $_SESSION["cart"]["products"][] = $productarray;
            }
        }
    } else {
        if (isset($pid) && $pid) {
            $configoptionsarray = [];
            $customfieldsarray = [];
            $domainfieldsarray = [];
            $addonsarray = [];
            if (isset($addons) && $addons) {
                foreach (explode(",", $addons) as $addonForPid) {
                    $addonsarray[] = ["addonid" => $addonForPid, "qty" => 1];
                }
            }
            if (isset($configoptions) && $configoptions) {
                $configoptions = base64_decode($configoptions);
                $configoptionsarray = safe_unserialize($configoptions);
            }
            if (isset($customfields) && $customfields) {
                $customfields = base64_decode($customfields);
                $customfieldsarray = safe_unserialize($customfields);
            }
            $productarray = ["pid" => $pid, "domain" => $domain, "billingcycle" => $billingcycle, "server" => "", "configoptions" => $configoptionsarray, "customfields" => $customfieldsarray, "addons" => $addonsarray, "strictDomain" => false];
            if (!empty($hostname) || !empty($ns1prefix) || !empty($ns2prefix) || !empty($rootpw)) {
                $productarray["server"] = ["hostname" => $hostname ?? NULL, "ns1prefix" => $ns1prefix ?? NULL, "ns2prefix" => $ns2prefix ?? NULL, "rootpw" => $rootpw ?? NULL];
            }
            if (isset($priceoverride) && strlen($priceoverride)) {
                $productarray["priceoverride"] = $priceoverride;
            }
            $_SESSION["cart"]["products"][] = $productarray;
        }
    }
    $requestOptionalArray = function ($requestVar) {
        $value = [];
        if (App::isInRequest($requestVar)) {
            $value = App::getFromRequest($requestVar);
            if (!is_array($value)) {
                throw new UnexpectedValueException($requestVar);
            }
        }
        return $value;
    };
    $domaintype = App::getFromRequest("domaintype");
    $domainfields = App::getFromRequest("domainfields");
    $domain = App::getFromRequest("domain");
    $regperiod = App::getFromRequest("regperiod");
    $idnLanguage = App::getFromRequest("idnlanguage");
    $dnsmanagement = App::getFromRequest("dnsmanagement");
    $emailforwarding = App::getFromRequest("emailforwarding");
    $idprotection = App::getFromRequest("idprotection");
    $eppcode = App::getFromRequest("eppcode");
    $domainpriceoverride = App::getFromRequest("domainpriceoverride");
    $domainrenewoverride = App::getFromRequest("domainrenewoverride");
    if (is_array($domaintype)) {
        try {
            $domainfields = $requestOptionalArray("domainfields");
            $domain = $requestOptionalArray("domain");
            $regperiod = $requestOptionalArray("regperiod");
            $idnLanguage = $requestOptionalArray("idnlanguage");
            $dnsmanagement = $requestOptionalArray("dnsmanagement");
            $emailforwarding = $requestOptionalArray("emailforwarding");
            $idprotection = $requestOptionalArray("idprotection");
            $eppcode = $requestOptionalArray("eppcode");
            $domainpriceoverride = $requestOptionalArray("domainpriceoverride");
            $domainrenewoverride = $requestOptionalArray("domainrenewoverride");
        } catch (UnexpectedValueException $e) {
            $apiresults = ["result" => "error", "message" => "Expecting parameter '" . $e->getMessage() . "' to be an array"];
            return NULL;
        }
        foreach ($domaintype as $i => $type) {
            if ($type) {
                if (array_key_exists($i, $domainfields)) {
                    $domainfields[$i] = base64_decode($domainfields[$i]);
                    $domainfieldsarray[$i] = safe_unserialize($domainfields[$i]);
                } else {
                    $domainfields[$i] = NULL;
                    $domainfieldsarray[$i] = NULL;
                }
                $idnLanguage[$i] = $idnLanguage[$i] ?? "";
                $dnsmanagement[$i] = $dnsmanagement[$i] ?? "";
                $emailforwarding[$i] = $emailforwarding[$i] ?? "";
                $idprotection[$i] = $idprotection[$i] ?? "";
                $eppcode[$i] = $eppcode[$i] ?? "";
                $domainArray = ["type" => $type, "domain" => $domain[$i], "regperiod" => $regperiod[$i], "idnLanguage" => $idnLanguage[$i], "dnsmanagement" => $dnsmanagement[$i], "emailforwarding" => $emailforwarding[$i], "idprotection" => $idprotection[$i], "eppcode" => $eppcode[$i], "fields" => $domainfieldsarray[$i]];
                if (isset($domainpriceoverride[$i]) && 0 < strlen($domainpriceoverride[$i])) {
                    $domainArray["domainpriceoverride"] = $domainpriceoverride[$i];
                }
                if (isset($domainrenewoverride[$i]) && 0 < strlen($domainrenewoverride[$i])) {
                    $domainArray["domainrenewoverride"] = $domainrenewoverride[$i];
                }
                $_SESSION["cart"]["domains"][] = $domainArray;
            }
        }
    } else {
        if ($domaintype) {
            if ($domainfields) {
                $domainfields = base64_decode($domainfields);
                $domainfieldsarray = safe_unserialize($domainfields);
            }
            if (empty($idnLanguage)) {
                $idnLanguage = "";
            }
            $domainArray = ["type" => $domaintype, "domain" => $domain, "regperiod" => $regperiod, "idnLanguage" => $idnLanguage, "dnsmanagement" => $dnsmanagement, "emailforwarding" => $emailforwarding, "idprotection" => $idprotection, "eppcode" => $eppcode, "fields" => $domainfieldsarray ?? NULL];
            if (isset($domainpriceoverride) && 0 < strlen($domainpriceoverride)) {
                $domainArray["domainpriceoverride"] = $domainpriceoverride;
            }
            if (isset($domainrenewoverride) && 0 < strlen($domainrenewoverride)) {
                $domainArray["domainrenewoverride"] = $domainrenewoverride;
            }
            $_SESSION["cart"]["domains"][] = $domainArray;
        }
    }
    if (isset($addonid) && $addonid) {
        $addonData = WHMCS\Database\Capsule::table("tbladdons")->find($addonid);
        if (!$addonData) {
            $apiresults = ["result" => "error", "message" => "Addon ID invalid"];
            return NULL;
        }
        $addonid = $addonData->id;
        $allowMultipleQuantities = (int) $addonData->allowqty;
        if ($allowMultipleQuantities === 1) {
            $allowMultipleQuantities = 0;
        }
        $serviceid = get_query_val("tblhosting", "id", ["userid" => $userid, "id" => $serviceid]);
        if (!$serviceid) {
            $apiresults = ["result" => "error", "message" => "Service ID not owned by Client ID provided"];
            return NULL;
        }
        $_SESSION["cart"]["addons"][] = ["id" => $addonid, "productid" => $serviceid, "qty" => 1, "allowsQuantity" => $allowsQuantity ?? NULL];
    }
    if (isset($addonids) && $addonids) {
        foreach ($addonids as $i => $addonid) {
            $addonData = WHMCS\Database\Capsule::table("tbladdons")->find($addonid);
            if (!$addonData) {
                $apiresults = ["result" => "error", "message" => "Addon ID invalid"];
                return NULL;
            }
            $addonid = $addonData->id;
            $allowsQuantity = (int) $addonData->allowqty;
            if ($allowsQuantity === 1) {
                $allowsQuantity = 0;
            }
            $serviceid = get_query_val("tblhosting", "id", ["userid" => $userid, "id" => $serviceids[$i]]);
            if (!$serviceid) {
                $apiresults = ["result" => "error", "message" => sprintf("Service ID %s not owned by Client ID provided", (int) $serviceids[$i])];
                return NULL;
            }
            $_SESSION["cart"]["addons"][] = ["id" => $addonid, "productid" => $serviceid, "qty" => 1, "allowsQuantity" => $allowMultipleQuantities];
        }
    }
    $domainrenewals = $whmcs->get_req_var("domainrenewals");
    if ($domainrenewals) {
        foreach ($domainrenewals as $domain => $regperiod) {
            $domain = mysql_real_escape_string($domain);
            $sql = "SELECT `id`\n                FROM `tbldomains`\n                WHERE userid=" . $userid . " AND domain='" . $domain . "' AND status IN ('Active', 'Expired', 'Grace', 'Redemption')";
            $domainResult = full_query($sql);
            $domainData = mysql_fetch_array($domainResult);
            if (isset($domainData["id"])) {
                $domainid = $domainData["id"];
            }
            if (!$domainid) {
                $sql = "SELECT `status`\n                    FROM `tbldomains`\n                    WHERE userid=" . $userid . " AND domain='" . $domain . "'";
                $domainResult = full_query($sql);
                $domainData = mysql_fetch_array($domainResult);
                $apiresults = ["result" => "error", "message" => ""];
                if (isset($domainData["status"])) {
                    $apiresults["message"] = "Domain status is set to '" . $domainData["status"] . "' and cannot be renewed";
                } else {
                    $apiresults["message"] = "Domain not owned by Client ID provided";
                }
                return NULL;
            }
            $_SESSION["cart"]["renewals"][$domainid] = $regperiod;
        }
    }
    $cartitems = count($_SESSION["cart"]["products"] ?? []) + count($_SESSION["cart"]["addons"] ?? []) + count($_SESSION["cart"]["domains"] ?? []) + count($_SESSION["cart"]["renewals"] ?? []);
    if (!$cartitems) {
        $apiresults = ["result" => "error", "message" => "No items added to cart so order cannot proceed"];
        return NULL;
    }
    $_SESSION["cart"]["ns1"] = $nameserver1 ?? NULL;
    $_SESSION["cart"]["ns2"] = $nameserver2 ?? NULL;
    $_SESSION["cart"]["ns3"] = $nameserver3 ?? NULL;
    $_SESSION["cart"]["ns4"] = $nameserver4 ?? NULL;
    $_SESSION["cart"]["paymentmethod"] = $paymentmethod;
    $_SESSION["cart"]["promo"] = $promocode ?? NULL;
    $_SESSION["cart"]["notes"] = $notes ?? NULL;
    if (isset($contactid) && $contactid) {
        $_SESSION["cart"]["contact"] = $contactid;
    }
    if (isset($noinvoice) && $noinvoice) {
        $_SESSION["cart"]["geninvoicedisabled"] = true;
    }
    if (isset($noinvoiceemail) && $noinvoiceemail) {
        $CONFIG["NoInvoiceEmailOnOrder"] = true;
    }
    if (isset($noemail) && $noemail) {
        $_SESSION["cart"]["orderconfdisabled"] = true;
    }
    $cartdata = calcCartTotals($client, true, false);
    if (isset($cartdata["result"]) && $cartdata["result"] == "error") {
        $apiresults = $cartdata;
        return NULL;
    }
    if ($cartdata === false) {
        $apiresults = ["result" => "error", "message" => "No items remain in the cart. Order cannot proceed."];
        return NULL;
    }
    if (isset($affid) && $affid) {
        $verifyAffId = WHMCS\Database\Capsule::table("tblaffiliates")->where("id", $affid)->first();
    }
    if (isset($affid) && $affid && is_array($_SESSION["orderdetails"]["Products"]) && !empty($verifyAffId) && $_SESSION["uid"] != $verifyAffId->clientid) {
        foreach ($_SESSION["orderdetails"]["Products"] as $productid) {
            insert_query("tblaffiliatesaccounts", ["affiliateid" => $affid, "relid" => $productid]);
        }
    } else {
        unset($affid);
    }
    $productids = $addonids = $domainids = "";
    if (is_array($_SESSION["orderdetails"]["Products"])) {
        $productids = implode(",", $_SESSION["orderdetails"]["Products"]);
    }
    if (is_array($_SESSION["orderdetails"]["Addons"])) {
        $addonids = implode(",", $_SESSION["orderdetails"]["Addons"]);
    }
    if (is_array($_SESSION["orderdetails"]["Domains"])) {
        $domainids = implode(",", $_SESSION["orderdetails"]["Domains"]);
    }
    $apiresults = ["result" => "success", "orderid" => $_SESSION["orderdetails"]["OrderID"], "productids" => $productids, "serviceids" => $productids, "addonids" => $addonids, "domainids" => $domainids];
    if (empty($noinvoice)) {
        $apiresults["invoiceid"] = $_SESSION["orderdetails"]["InvoiceID"] ? $_SESSION["orderdetails"]["InvoiceID"] : get_query_val("tblorders", "invoiceid", ["id" => $_SESSION["orderdetails"]["OrderID"]]);
    }
}
