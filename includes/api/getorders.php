<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!isset($limitstart) || !$limitstart) {
    $limitstart = 0;
}
if (!isset($limitnum) || !$limitnum) {
    $limitnum = 25;
}
$query = " FROM tblorders o\n    LEFT JOIN tblclients c ON o.userid=c.id\n    LEFT JOIN tblpaymentgateways p ON o.paymentmethod=p.gateway AND p.setting='name'\n    LEFT JOIN tblinvoices i ON o.invoiceid=i.id";
$where = [];
$id = (int) App::get_req_var("id");
$userid = (int) App::get_req_var("userid");
$requestor_id = (int) App::get_req_var("requestor_id");
$status = App::get_req_var("status");
if ($id) {
    $where[] = "o.id=" . $id;
}
if ($userid) {
    $where[] = "o.userid=" . $userid;
}
if ($requestor_id) {
    $where[] = "o.requestor_id=" . $requestor_id;
}
if ($status) {
    $where[] = "o.status='" . mysql_real_escape_string($status) . "'";
}
if (count($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$result_count = full_query("SELECT COUNT(o.id)" . $query);
$data = mysql_fetch_array($result_count);
$totalresults = $data[0];
$result = full_query("SELECT o.*, p.value AS paymentmethodname, i.status AS paymentstatus, CONCAT(c.firstname,' ',c.lastname) AS name" . $query . " ORDER BY o.id DESC LIMIT " . (int) $limitstart . "," . (int) $limitnum);
$apiresults = ["result" => "success", "totalresults" => $totalresults, "startnumber" => $limitstart, "numreturned" => mysql_num_rows($result)];
while ($orderdata = mysql_fetch_assoc($result)) {
    $orderid = $orderdata["id"];
    $userid = $orderdata["userid"];
    $requestorid = $orderdata["requestor_id"];
    $fraudmodule = $orderdata["fraudmodule"];
    $fraudoutput = $orderdata["fraudoutput"];
    $currency = getCurrency($userid);
    $orderdata["currencyprefix"] = $currency["prefix"];
    $orderdata["currencysuffix"] = $currency["suffix"];
    $frauddata = "";
    if ($fraudmodule) {
        $fraud = new WHMCS\Module\Fraud();
        if ($fraud->load($fraudmodule)) {
            $fraudresults = $fraud->processResultsForDisplay($orderid, $fraudoutput);
            if (is_array($fraudresults)) {
                foreach ($fraudresults as $key => $value) {
                    $frauddata .= $key . " => " . $value . "\n";
                }
            }
        }
    }
    $orderdata["fraudoutput"] = $fraudoutput;
    $orderdata["frauddata"] = $frauddata;
    $orderdata["validationdata"] = "";
    $user = WHMCS\User\User::find($requestorid);
    if (!is_null($user) && !is_null($user->validation)) {
        $orderdata["validationdata"] = $user->validation->toArray();
    }
    unset($user);
    $lineitems = [];
    $result2 = select_query("tblhosting", "", ["orderid" => $orderid]);
    while ($data = mysql_fetch_array($result2)) {
        $serviceid = $data["id"];
        $domain = $data["domain"];
        $billingcycle = $data["billingcycle"];
        $hostingstatus = $data["domainstatus"];
        $firstpaymentamount = formatCurrency($data["firstpaymentamount"]);
        $packageid = $data["packageid"];
        $result3 = select_query("tblproducts", "tblproducts.name,tblproducts.type,tblproducts.welcomeemail,tblproducts.autosetup,tblproducts.servertype,tblproductgroups.name as group_name,tblproductgroups.id AS group_id", ["tblproducts.id" => $packageid], "", "", "", "tblproductgroups ON tblproducts.gid=tblproductgroups.id");
        $data = mysql_fetch_array($result3);
        $groupname = WHMCS\Product\Group::getGroupName($data["group_id"], $data["group_name"]);
        $productname = WHMCS\Product\Product::getProductName($packageid, $data["name"]);
        $producttype = $data["type"];
        if ($producttype == "hostingaccount") {
            $producttype = "Hosting Account";
        } else {
            if ($producttype == "reselleraccount") {
                $producttype = "Reseller Account";
            } else {
                if ($producttype == "server") {
                    $producttype = "Dedicated/VPS Server";
                } else {
                    if ($producttype == "other") {
                        $producttype = "Other Product/Service";
                    }
                }
            }
        }
        $lineitems["lineitem"][] = ["type" => "product", "relid" => $serviceid, "producttype" => $producttype, "product" => $groupname . " - " . $productname, "domain" => $domain, "billingcycle" => $billingcycle, "amount" => $firstpaymentamount, "status" => $hostingstatus];
    }
    $predefinedaddons = [];
    $result2 = select_query("tbladdons", "", "");
    while ($data = mysql_fetch_array($result2)) {
        $addon_id = $data["id"];
        $addon_name = $data["name"];
        $addon_welcomeemail = $data["welcomeemail"];
        $predefinedaddons[$addon_id] = ["name" => $addon_name, "welcomeemail" => $addon_welcomeemail];
    }
    $result2 = select_query("tblhostingaddons", "", ["orderid" => $orderid]);
    while ($data = mysql_fetch_array($result2)) {
        $aid = $data["id"];
        $hostingid = $data["hostingid"];
        $addonid = $data["addonid"];
        $name = $data["name"];
        $billingcycle = $data["billingcycle"];
        $addonamount = $data["recurring"] + $data["setupfee"];
        $addonstatus = $data["status"];
        $regdate = $data["regdate"];
        $nextduedate = $data["nextduedate"];
        $addonamount = formatCurrency($addonamount);
        if (!$name) {
            $name = $predefinedaddons[$addonid]["name"];
        }
        $lineitems["lineitem"][] = ["type" => "addon", "relid" => $aid, "producttype" => "Addon", "product" => $name, "domain" => "", "billingcycle" => $billingcycle, "amount" => $addonamount, "status" => $addonstatus];
    }
    $result2 = select_query("tbldomains", "", ["orderid" => $orderid]);
    while ($data = mysql_fetch_array($result2)) {
        $domainid = $data["id"];
        $type = $data["type"];
        $domain = $data["domain"];
        $registrationperiod = $data["registrationperiod"];
        $status = $data["status"];
        $regdate = $data["registrationdate"];
        $nextduedate = $data["nextduedate"];
        $domainamount = formatCurrency($data["firstpaymentamount"]);
        $domainregistrar = $data["registrar"];
        $dnsmanagement = $data["dnsmanagement"];
        $emailforwarding = $data["emailforwarding"];
        $idprotection = $data["idprotection"];
        $lineitems["lineitem"][] = ["type" => "domain", "relid" => $domainid, "producttype" => "Domain", "product" => $type, "domain" => $domain, "billingcycle" => $registrationperiod, "amount" => $domainamount, "status" => $status, "dnsmanagement" => $dnsmanagement, "emailforwarding" => $emailforwarding, "idprotection" => $idprotection];
    }
    $renewals = $orderdata["renewals"];
    if ($renewals) {
        $renewals = explode(",", $renewals);
        foreach ($renewals as $renewal) {
            $renewal = explode("=", $renewal);
            list($domainid, $registrationperiod) = $renewal;
            $renewalResult = select_query("tbldomains", "", ["id" => $domainid]);
            $data = mysql_fetch_array($renewalResult);
            $domainid = $data["id"];
            $type = $data["type"];
            $domain = $data["domain"];
            $registrar = $data["registrar"];
            $status = $data["status"];
            $regdate = $data["registrationdate"];
            $nextduedate = $data["nextduedate"];
            $domainamount = formatCurrency($data["recurringamount"]);
            $domainregistrar = $data["registrar"];
            $dnsmanagement = $data["dnsmanagement"];
            $emailforwarding = $data["emailforwarding"];
            $idprotection = $data["idprotection"];
            $lineitems["lineitem"][] = ["type" => "renewal", "relid" => $domainid, "producttype" => "Domain", "product" => "Renewal", "domain" => $domain, "billingcycle" => $registrationperiod, "amount" => $domainamount, "status" => $status, "dnsmanagement" => $dnsmanagement, "emailforwarding" => $emailforwarding, "idprotection" => $idprotection];
        }
    }
    $result2 = select_query("tblupgrades", "", ["orderid" => $orderid]);
    while ($data = mysql_fetch_array($result2)) {
        $upgradeid = $data["id"];
        $type = $data["type"];
        $relid = $data["relid"];
        $originalvalue = $data["originalvalue"];
        $newvalue = $data["newvalue"];
        $upgradeamount = formatCurrency($data["amount"]);
        $newrecurringamount = $data["newrecurringamount"];
        $status = $data["status"];
        $paid = $data["paid"];
        if ($type == "package") {
            $oldpackagename = WHMCS\Product\Product::getProductName($originalvalue);
            $newvalue = explode(",", $newvalue);
            $newpackageid = $newvalue[0];
            $newpackagename = WHMCS\Product\Product::getProductName($newpackageid);
            $details = "Package Upgrade: " . $oldpackagename . " => " . $newpackagename . "<br>";
        } else {
            if ($type == "configoptions") {
                $tempvalue = explode("=>", $originalvalue);
                list($configid, $oldoptionid) = $tempvalue;
                $result2 = select_query("tblproductconfigoptions", "", ["id" => $configid]);
                $data = mysql_fetch_array($result2);
                $configname = $data["optionname"];
                $optiontype = $data["optiontype"];
                if ($optiontype == 1 || $optiontype == 2) {
                    $result2 = select_query("tblproductconfigoptionssub", "", ["id" => $oldoptionid]);
                    $data = mysql_fetch_array($result2);
                    $oldoptionname = $data["optionname"];
                    $result2 = select_query("tblproductconfigoptionssub", "", ["id" => $newvalue]);
                    $data = mysql_fetch_array($result2);
                    $newoptionname = $data["optionname"];
                } else {
                    if ($optiontype == 3) {
                        if ($oldoptionid) {
                            $oldoptionname = "Yes";
                            $newoptionname = "No";
                        } else {
                            $oldoptionname = "No";
                            $newoptionname = "Yes";
                        }
                    } else {
                        if ($optiontype == 4) {
                            $result2 = select_query("tblproductconfigoptionssub", "", ["configid" => $configid]);
                            $data = mysql_fetch_array($result2);
                            $optionname = $data["optionname"];
                            $oldoptionname = $oldoptionid;
                            $newoptionname = $newvalue . " x " . $optionname;
                        }
                    }
                }
                $details = $configname . ": " . $oldoptionname . " => " . $newoptionname . "<br>";
            }
        }
        $lineitems["lineitem"][] = ["type" => "upgrade", "relid" => $relid, "producttype" => "Upgrade", "product" => $details, "domain" => "", "billingcycle" => "", "amount" => $upgradeamount, "status" => $status];
    }
    $apiresults["orders"]["order"][] = array_merge($orderdata, ["lineitems" => $lineitems]);
}
$responsetype = "xml";
