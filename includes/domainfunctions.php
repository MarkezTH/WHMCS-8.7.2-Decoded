<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;

function getTLDList($type = "register")
{
    global $currency;
    $currency_id = is_array($currency) ? $currency["id"] : NULL;
    if (!$currency_id) {
        if (defined("CLIENTAREA")) {
            $currency = WHMCS\Billing\Currency::factoryForClientArea();
        } else {
            $currency = WHMCS\Billing\Currency::defaultCurrency()->first();
        }
        $currency_id = $currency["id"];
    }
    $clientgroupid = Auth::client() ? Auth::client()->groupid : 0;
    $isReg = strcasecmp($type, "register") == 0;
    $checkfields = ["msetupfee", "qsetupfee", "ssetupfee", "asetupfee", "bsetupfee", "monthly", "quarterly", "semiannually", "annually", "biennially"];
    $query = "SELECT DISTINCT tbldomainpricing.extension";
    $query .= " FROM tbldomainpricing";
    $query .= " JOIN tblpricing ON tblpricing.relid=tbldomainpricing.id";
    if (!$isReg) {
        $query .= " JOIN tblpricing AS regcheck ON regcheck.relid=tbldomainpricing.id";
    }
    $query .= " WHERE";
    $query .= " tblpricing.type=?";
    $query .= " AND tblpricing.currency=?";
    $query .= " AND (tblpricing.tsetupfee=? OR tblpricing.tsetupfee=0)";
    if (!$isReg) {
        $query .= " AND regcheck.type=\"domainregister\"";
        $query .= " AND regcheck.currency=tblpricing.currency";
        $query .= " AND regcheck.tsetupfee=tblpricing.tsetupfee";
    }
    $extraConds = [];
    foreach ($checkfields as $field) {
        $cond = "(tblpricing." . $field . " >= 0 ";
        if (!$isReg) {
            $cond .= " AND regcheck." . $field . " >= 0";
        }
        $cond .= ")";
        $extraConds[] = $cond;
    }
    $query .= " AND (" . implode(" OR ", $extraConds) . ")";
    $query .= " ORDER BY tbldomainpricing.order ASC, tbldomainpricing.id ASC";
    $bindings = ["domain" . $type, $currency_id, $clientgroupid];
    $result = WHMCS\Database\Capsule::connection()->select($query, $bindings);
    $extensions = array_map(function ($item) {
        return $item->extension;
    }, $result);
    return $extensions;
}

function getTLDPriceList($tld, $display = false, $renewpricing = "", $userid = 0, $useCache = true)
{
    global $currency;
    if (!$currency || !is_array($currency)) {
        $currency = WHMCS\Billing\Currency::factoryForClientArea();
    }
    if (!$userid && Auth::client()) {
        $userid = Auth::client()->id;
    }
    if (ltrim($tld, ".") == $tld) {
        $tld = "." . $tld;
    }
    $cacheKey = NULL;
    if (!isset($pricingCache)) {
        $pricingCache = [];
    } else {
        foreach ($pricingCache as $key => $pricing) {
            if ($pricing["tld"] == $tld && $pricing["display"] == $display && $pricing["renewpricing"] == $renewpricing && $pricing["userid"] == $userid) {
                if ($useCache) {
                    return $pricing["pricing"];
                }
                $cacheKey = $key;
            }
        }
    }
    if (is_null($cacheKey)) {
        $pricing = ["tld" => $tld, "display" => $display, "renewpricing" => $renewpricing, "userid" => $userid];
        $cacheKey = count($pricingCache);
        $pricingCache[$cacheKey] = $pricing;
    }
    if ($renewpricing == "renew") {
        $renewpricing = true;
    }
    if (!empty($userid)) {
        $currency_id = getCurrency($userid)["id"];
    } else {
        $currency_id = $currency["id"];
    }
    try {
        $extensionData = WHMCS\Domains\Extension::where("extension", $tld)->firstOrFail(["id"]);
        $id = $extensionData->id;
    } catch (Exception $e) {
        return [];
    }
    $clientgroupid = $userid ? get_query_val("tblclients", "groupid", ["id" => $userid]) : "0";
    $checkfields = ["msetupfee", "qsetupfee", "ssetupfee", "asetupfee", "bsetupfee", "monthly", "quarterly", "semiannually", "annually", "biennially"];
    $pricingData = WHMCS\Database\Capsule::table("tblpricing")->whereIn("type", ["domainregister", "domaintransfer", "domainrenew"])->where("currency", "=", $currency_id)->where("relid", "=", $id)->orderBy("tsetupfee", "desc")->get()->all();
    $sortedData = ["domainregister" => [], "domaintransfer" => [], "domainrenew" => []];
    foreach ($pricingData as $entry) {
        $entryPricingGroupId = (int) $entry->tsetupfee;
        if ($entryPricingGroupId == 0 || $entryPricingGroupId == $clientgroupid) {
            $type = $entry->type;
            if (empty($sortedData[$type])) {
                $sortedData[$type] = (array) $entry;
            }
        }
    }
    $register = [];
    if (!$renewpricing || $renewpricing === "transfer") {
        $data = $sortedData["domainregister"];
        foreach ($checkfields as $k => $v) {
            $register[$k + 1] = $data[$v] ?: -1;
        }
        $data = $sortedData["domaintransfer"];
        $minTransferSet = false;
        foreach ($checkfields as $k => $v) {
            $valueForTransfer = -1;
            if ($register[$k + 1] >= 0) {
                if (array_key_exists($v, $data) && 0 <= $data[$v]) {
                    $valueForTransfer = $data[$v];
                    $minTransferSet = true;
                }
                $transfer[$k + 1] = $valueForTransfer;
                if ($minTransferSet) {
                }
            }
        }
    }
    if (!$renewpricing || $renewpricing !== "transfer") {
        $data = $sortedData["domainrenew"];
        if (!count($register)) {
            foreach ($checkfields as $k => $v) {
                $register[$k + 1] = $data[$v] ?: -1;
            }
        }
        foreach ($checkfields as $k => $v) {
            if (!($k == 9 || $register[$k + 1] < 0)) {
                $valueForRenew = -1;
                if (array_key_exists($v, $data) && 0 <= $data[$v]) {
                    $valueForRenew = $data[$v];
                }
                $renew[$k + 1] = $valueForRenew;
            }
        }
    }
    $tldpricing = [];
    $transferPricingSet = false;
    $years = 1;
    while ($years <= 10) {
        if ($renewpricing === "transfer") {
            if (isset($register[$years]) && 0 <= $register[$years] && isset($transfer[$years]) && 0 <= $transfer[$years]) {
                if (!$transferPricingSet) {
                    if ($display) {
                        $transfer[$years] = formatCurrency($transfer[$years]);
                    }
                    $tldpricing[$years]["transfer"] = $transfer[$years];
                    $transferPricingSet = true;
                }
            }
        } else {
            if ($renewpricing) {
                if ($years != 10) {
                    if (isset($renew[$years]) && 0 < $renew[$years]) {
                        if ($display) {
                            $renew[$years] = formatCurrency($renew[$years]);
                        }
                        $tldpricing[$years]["renew"] = $renew[$years];
                    }
                }
            } else {
                if (isset($register[$years]) && 0 <= $register[$years]) {
                    if ($display) {
                        $register[$years] = formatCurrency($register[$years]);
                    }
                    $tldpricing[$years]["register"] = $register[$years];
                    if (isset($transfer[$years]) && 0 <= $transfer[$years] && !$transferPricingSet) {
                        if ($display) {
                            $transfer[$years] = formatCurrency($transfer[$years]);
                        }
                        $tldpricing[$years]["transfer"] = $transfer[$years];
                        $transferPricingSet = true;
                    }
                    if (isset($renew[$years]) && 0 < $renew[$years] && $years != 10) {
                        if ($display) {
                            $renew[$years] = formatCurrency($renew[$years]);
                        }
                        $tldpricing[$years]["renew"] = $renew[$years];
                    }
                }
            }
        }
        $years += 1;
    }
    $pricingCache[$cacheKey]["pricing"] = $tldpricing;
    return $tldpricing;
}

function cleanDomainInput($val)
{
    global $CONFIG;
    $val = trim($val);
    if (!$CONFIG["AllowIDNDomains"]) {
        $val = strtolower($val);
    }
    return $val;
}

function disableAutoRenew($domainid)
{
    $data = get_query_vals("tbldomains", "id,domain,nextduedate,userid", ["id" => $domainid]);
    $domainid = $data["id"];
    $domainname = $data["domain"];
    $nextduedate = $data["nextduedate"];
    $userId = $data["userid"];
    if (!$domainid) {
        return false;
    }
    update_query("tbldomains", ["nextinvoicedate" => $nextduedate, "donotrenew" => "1"], ["id" => $domainid]);
    $who = "Client";
    if ($_SESSION["adminid"]) {
        $who = "Admin";
    }
    logActivity($who . " Disabled Domain Auto Renew - Domain ID: " . $domainid . " - Domain: " . $domainname, $userId);
    $result = select_query("tblinvoiceitems", "tblinvoiceitems.id,tblinvoiceitems.invoiceid", ["type" => "Domain", "relid" => $domainid, "status" => "Unpaid", "tblinvoices.userid" => Auth::client()->id], "", "", "", "tblinvoices ON tblinvoices.id=tblinvoiceitems.invoiceid");
    while ($data = mysql_fetch_array($result)) {
        $itemid = $data["id"];
        $invoiceid = $data["invoiceid"];
        $result2 = select_query("tblinvoiceitems", "COUNT(*)", ["invoiceid" => $invoiceid]);
        $data = mysql_fetch_array($result2);
        $itemcount = $data[0];
        $otheritemcount = 0;
        if (1 < $itemcount) {
            $otheritemcount = get_query_val("tblinvoiceitems", "COUNT(*)", "invoiceid=" . (int) $invoiceid . " AND id!=" . (int) $itemid . " AND type NOT IN ('PromoHosting','PromoDomain','GroupDiscount')");
        }
        if ($itemcount == 1 || $otheritemcount == 0) {
            update_query("tblinvoiceitems", ["type" => "", "relid" => "0"], ["id" => $itemid]);
            WHMCS\Database\Capsule::table("tblinvoices")->where("id", $invoiceid)->update(["status" => WHMCS\Billing\Invoice::STATUS_CANCELLED, "date_cancelled" => WHMCS\Carbon::now()->toDateTimeString(), "updated_at" => WHMCS\Carbon::now()->toDateTimeString()]);
            logActivity("Cancelled Previous Domain Renewal Invoice - Invoice ID: " . $invoiceid . " - Domain: " . $domainname, $userId);
            run_hook("InvoiceCancelled", ["invoiceid" => $invoiceid]);
        } else {
            delete_query("tblinvoiceitems", ["id" => $itemid]);
            updateInvoiceTotal($invoiceid);
            logActivity("Removed Previous Domain Renewal Line Item - Invoice ID: " . $invoiceid . " - Domain: " . $domainname, $userId);
        }
    }
}

function multipleTldPriceListings($tlds)
{
    $tldPriceListings = [];
    if (!isset($group)) {
        $groups = WHMCS\Database\Capsule::table("tbldomainpricing")->pluck("group", "extension")->all();
    }
    foreach ($tlds as $tld) {
        $tldPricing = gettldpricelist($tld, true, "", Auth::client()->id ?? NULL);
        $firstOption = current($tldPricing);
        $year = key($tldPricing);
        $saleGroup = isset($groups[$tld]) && strtolower($groups[$tld]) != "none" ? strtolower($groups[$tld]) : "";
        $tldPriceListings[] = ["tld" => $tld, "tldNoDots" => str_replace(".", "", $tld), "period" => $year, "register" => isset($firstOption["register"]) ? $firstOption["register"] : "", "transfer" => isset($firstOption["transfer"]) ? $firstOption["transfer"] : "", "renew" => isset($firstOption["renew"]) ? $firstOption["renew"] : "", "group" => $saleGroup, "groupDisplayName" => $saleGroup ? Lang::trans("domainCheckerSalesGroup." . $saleGroup) : ""];
    }
    return $tldPriceListings;
}

function getSpotlightTlds()
{
    $setting = WHMCS\Config\Setting::getValue("SpotlightTLDs");
    if (!is_string($setting) || strlen($setting) == 0) {
        return [];
    }
    return array_filter(explode(",", $setting), function ($item) {
        return $item;
    });
}

function getSpotlightTldsWithPricing()
{
    return multipletldpricelistings(getspotlighttlds());
}
