<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("recalcRecurringProductPrice")) {
    require ROOTDIR . "/includes/clientfunctions.php";
}
if (!function_exists("saveCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
if (!function_exists("getCartConfigOptions")) {
    require ROOTDIR . "/includes/configoptionsfunctions.php";
}
$serviceid = (int) $whmcs->get_req_var("serviceid");
$result = select_query("tblhosting", "id,packageid,billingcycle,promoid,domainstatus", ["id" => $serviceid]);
$data = mysql_fetch_array($result);
if (!is_array($data) || empty($data["id"])) {
    $apiresults = ["result" => "error", "message" => "Service ID Not Found"];
} else {
    $storedStatus = $data["domainstatus"];
    $status = $whmcs->get_req_var("status");
    $terminationDate = $whmcs->get_req_var("terminationdate");
    $completedDate = NULL;
    $updateqry = [];
    if (!empty($pid)) {
        $updateqry["packageid"] = $pid;
    }
    if (!empty($serverid)) {
        $updateqry["server"] = $serverid;
    }
    if (!empty($regdate)) {
        $updateqry["regdate"] = $regdate;
    }
    if (!empty($nextduedate)) {
        $updateqry["nextduedate"] = $nextduedate;
        $updateqry["nextinvoicedate"] = $nextduedate;
    }
    if (!empty($domain)) {
        $updateqry["domain"] = $domain;
    }
    if (!empty($firstpaymentamount)) {
        $updateqry["firstpaymentamount"] = $firstpaymentamount;
    }
    if (!empty($recurringamount)) {
        $updateqry["amount"] = $recurringamount;
    }
    if (!empty($billingcycle)) {
        $updateqry["billingcycle"] = $billingcycle;
    }
    if ($status && $status != $storedStatus) {
        switch ($status) {
            case "Terminated":
            case "Cancelled":
                if ((!$terminationDate || $terminationDate == "0000-00-00") && !in_array($storedStatus, ["Terminated", "Cancelled"])) {
                    $terminationDate = date("Y-m-d");
                }
                $completedDate = "0000-00-00";
                break;
            case "Completed":
                $completedDate = WHMCS\Carbon::today()->toDateString();
                $terminationDate = "0000-00-00";
                break;
            default:
                $terminationDate = "0000-00-00";
                $completedDate = "0000-00-00";
                $updateqry["domainstatus"] = $status;
        }
    }
    if ($terminationDate) {
        if (!$status) {
            switch ($storedStatus) {
                case "Terminated":
                case "Cancelled":
                    if ($terminationDate == "0000-00-00") {
                        unset($terminationDate);
                    }
                    break;
                default:
                    $terminationDate = "0000-00-00";
            }
        }
        if ($terminationDate) {
            $updateqry["termination_date"] = $terminationDate;
        }
    }
    if ($completedDate) {
        $updateqry["completed_date"] = $completedDate;
    }
    if (!empty($serviceusername)) {
        $updateqry["username"] = $serviceusername;
    }
    if (!empty($servicepassword)) {
        $updateqry["password"] = encrypt($servicepassword);
    }
    if (!empty($subscriptionid)) {
        $updateqry["subscriptionid"] = $subscriptionid;
    }
    if (!empty($paymentmethod)) {
        $updateqry["paymentmethod"] = $paymentmethod;
    }
    if (!empty($promoid)) {
        $updateqry["promoid"] = $promoid;
        $updateqry["promocount"] = "0";
    }
    if (!empty($overideautosuspend)) {
        $updateqry["overideautosuspend"] = $overideautosuspend != "off" ? "1" : "0";
    }
    if (!empty($overidesuspenduntil)) {
        $updateqry["overidesuspenduntil"] = $overidesuspenduntil;
    }
    if (!empty($ns1)) {
        $updateqry["ns1"] = $ns1;
    }
    if (!empty($ns2)) {
        $updateqry["ns2"] = $ns2;
    }
    if (!empty($dedicatedip)) {
        $updateqry["dedicatedip"] = $dedicatedip;
    }
    if (!empty($assignedips)) {
        $updateqry["assignedips"] = $assignedips;
    }
    if (!empty($notes)) {
        $updateqry["notes"] = $notes;
    }
    if (!empty($diskusage)) {
        $updateqry["diskusage"] = $diskusage;
    }
    if (!empty($disklimit)) {
        $updateqry["disklimit"] = $disklimit;
    }
    if (!empty($bwusage)) {
        $updateqry["bwusage"] = $bwusage;
    }
    if (!empty($bwlimit)) {
        $updateqry["bwlimit"] = $bwlimit;
    }
    if (!empty($lastupdate)) {
        $updateqry["lastupdate"] = $lastupdate;
    }
    if (!empty($suspendreason)) {
        $updateqry["suspendreason"] = $suspendreason;
    }
    $unsetAttributes = $whmcs->get_req_var("unset");
    if (is_array($unsetAttributes) && !empty($unsetAttributes)) {
        $allowedVariables = ["domain", "serviceusername", "servicepassword", "subscriptionid", "ns1", "ns2", "dedicatedip", "assignedips", "notes", "suspendreason"];
        foreach ($unsetAttributes as $unsetAttribute) {
            if (in_array($unsetAttribute, $allowedVariables)) {
                switch ($unsetAttribute) {
                    case "serviceusername":
                        $unsetAttribute = "username";
                        break;
                    case "servicepassword":
                        $unsetAttribute = "password";
                        break;
                    default:
                        $updateqry[$unsetAttribute] = "";
                }
            }
        }
    }
    if (0 < count($updateqry)) {
        update_query("tblhosting", $updateqry, ["id" => $serviceid]);
    }
    if (!empty($customfields)) {
        if (!is_array($customfields)) {
            $customfields = base64_decode($customfields);
            $customfields = safe_unserialize($customfields);
        }
        saveCustomFields($serviceid, $customfields, "product", true);
    }
    if (!empty($configoptions)) {
        if (!is_array($configoptions)) {
            $configoptions = base64_decode($configoptions);
            $configoptions = safe_unserialize($configoptions);
        }
        foreach ($configoptions as $cid => $vals) {
            if (is_array($vals)) {
                $oid = $vals["optionid"];
                $qty = $vals["qty"];
            } else {
                $oid = $vals;
                $qty = 0;
            }
            if (get_query_val("tblhostingconfigoptions", "COUNT(*)", ["relid" => $serviceid, "configid" => $cid])) {
                update_query("tblhostingconfigoptions", ["optionid" => $oid, "qty" => $qty], ["relid" => $serviceid, "configid" => $cid]);
            } else {
                insert_query("tblhostingconfigoptions", ["relid" => $serviceid, "configid" => $cid, "optionid" => $oid, "qty" => $qty]);
            }
        }
    }
    if (!empty($autorecalc)) {
        if (!$pid) {
            $pid = $data["packageid"];
        }
        if (!$billingcycle) {
            $billingcycle = $data["billingcycle"];
        }
        if (!$promoid) {
            $promoid = $data["promoid"];
        }
        $recurringamount = recalcRecurringProductPrice($serviceid, "", $pid, $billingcycle, "empty", $promoid, false, true);
        update_query("tblhosting", ["amount" => $recurringamount], ["id" => $serviceid]);
    }
    $apiresults = ["result" => "success", "serviceid" => $serviceid];
}
