<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Manage Affiliates", false);
$aInt->title = $aInt->lang("affiliates", "title");
$aInt->sidebar = "clients";
$aInt->icon = "affiliates";
$aInt->helplink = "Affiliates";
$aInt->requiredFiles(["invoicefunctions", "gatewayfunctions"]);
$action = App::getFromRequest("action");
$sub = App::getFromRequest("sub");
$client = App::getFromRequest("client");
$visitors = App::getFromRequest("visitors");
$balance = App::getFromRequest("balance");
$withdrawn = App::getFromRequest("withdrawn");
$payouttype = App::getFromRequest("payouttype");
if ($action == "save") {
    check_token("WHMCS.admin.default");
    update_query("tblaffiliates", ["paytype" => $paymenttype, "payamount" => $payamount, "onetime" => $onetime, "visitors" => $visitors, "balance" => $balance, "withdrawn" => $withdrawn], ["id" => $id]);
    logActivity("Affiliate ID " . $id . " Details Updated");
    redir("action=edit&id=" . $id);
}
if ($action == "deletecommission") {
    check_token("WHMCS.admin.default");
    delete_query("tblaffiliatespending", ["id" => $cid]);
    redir("action=edit&id=" . $id);
}
if ($action == "deletehistory") {
    check_token("WHMCS.admin.default");
    delete_query("tblaffiliateshistory", ["id" => $hid]);
    redir("action=edit&id=" . $id);
}
if ($action == "deletereferral") {
    check_token("WHMCS.admin.default");
    try {
        $affiliateAccount = WHMCS\Affiliate\Accounts::findOrFail($affaccid);
        $affiliateAccount->pending()->delete();
        $affiliateAccount->delete();
        WHMCS\FlashMessages::add(json_encode(["title" => AdminLang::trans("global.success"), "message" => AdminLang::trans("affiliates.refdeletesuccess")]));
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        WHMCS\FlashMessages::add(json_encode(["title" => AdminLang::trans("global.error"), "message" => AdminLang::trans("affiliates.refdeletenotfound")]), "error");
    } catch (Throwable $e) {
        WHMCS\FlashMessages::add(json_encode(["title" => AdminLang::trans("global.error"), "message" => $e->getMessage()]), "error");
    }
    redir("action=edit&id=" . $id);
}
if ($action == "deletewithdrawal") {
    check_token("WHMCS.admin.default");
    delete_query("tblaffiliateswithdrawals", ["id" => $wid]);
    redir("action=edit&id=" . $id);
}
if ($action == "addcomm") {
    check_token("WHMCS.admin.default");
    $amount = format_as_currency($amount);
    insert_query("tblaffiliateshistory", ["affiliateid" => $id, "date" => toMySQLDate($date), "affaccid" => $refid, "description" => $description, "amount" => $amount]);
    update_query("tblaffiliates", ["balance" => "+=" . $amount], ["id" => (int) $id]);
    logActivity("Manual Commission Added to Affiliate - Affiliate ID: " . $id);
    redir("action=edit&id=" . $id);
}
if ($action == "withdraw") {
    check_token("WHMCS.admin.default");
    insert_query("tblaffiliateswithdrawals", ["affiliateid" => $id, "date" => "now()", "amount" => $amount]);
    update_query("tblaffiliates", ["balance" => "-=" . $amount, "withdrawn" => "+=" . $amount], ["id" => (int) $id]);
    if ($payouttype == "1") {
        $result = select_query("tblaffiliates", "", ["id" => (int) $id]);
        $data = mysql_fetch_array($result);
        $id = (int) $data["id"];
        $clientid = (int) $data["clientid"];
        addTransaction($clientid, "", "Affiliate Commissions Withdrawal Payout", "0", "0", $amount, $paymentmethod, $transid);
    } else {
        if ($payouttype == "2") {
            $result = select_query("tblaffiliates", "", ["id" => (int) $id]);
            $data = mysql_fetch_array($result);
            $id = (int) $data["id"];
            $clientid = (int) $data["clientid"];
            insert_query("tblcredit", ["clientid" => $clientid, "date" => "now()", "description" => "Affiliate Commissions Withdrawal", "amount" => $amount]);
            update_query("tblclients", ["credit" => "+=" . $amount], ["id" => $clientid]);
            logActivity("Processed Affiliate Commissions Withdrawal to Credit Balance - User ID: " . $clientid . " - Amount: " . $amount);
        }
    }
    redir("action=edit&id=" . $id);
}
if ($sub === "delete") {
    check_token("WHMCS.admin.default");
    try {
        $affiliate = WHMCS\User\Client\Affiliate::findOrFail($ide);
        $affiliate->delete();
        logActivity("Affiliate " . $ide . " Deleted");
        $aInt->flash(AdminLang::trans("global.success"), AdminLang::trans("affiliates.deletesuccess"));
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        $aInt->flash(AdminLang::trans("global.error"), AdminLang::trans("affiliates.notfound"), "error");
    } catch (Throwable $e) {
        $aInt->flash(AdminLang::trans("global.error"), $e->getMessage() . "error");
    }
    redir();
}
ob_start();
$tableformurl = NULL;
$tableformbuttons = NULL;
if ($action == "") {
    $aInt->sortableTableInit("clientname", "ASC");
    $query = "FROM `tblaffiliates` INNER JOIN tblclients ON tblclients.id=tblaffiliates.clientid WHERE tblaffiliates.id!=''";
    if ($client) {
        $query .= " AND concat(firstname,' ',lastname) LIKE '%" . db_escape_string($client) . "%'";
    }
    if ($visitors) {
        $visitorstype = $visitorstype == "greater" ? ">" : "<";
        $query .= " AND visitors " . $visitorstype . " '" . db_escape_string($visitors) . "'";
    }
    if ($balance) {
        $balancetype = $balancetype == "greater" ? ">" : "<";
        $query .= " AND balance " . $balancetype . " '" . db_escape_string($balance) . "'";
    }
    if ($withdrawn) {
        $withdrawntype = $withdrawntype == "greater" ? ">" : "<";
        $query .= " AND withdrawn " . $withdrawntype . " '" . db_escape_string($withdrawn) . "'";
    }
    $result = full_query("SELECT COUNT(tblaffiliates.id) " . $query);
    $data = mysql_fetch_array($result);
    $numrows = $data[0];
    $aInt->deleteJSConfirm("doDelete", "affiliates", "deletesure", "affiliates.php?sub=delete&ide=");
    echo $aInt->beginAdminTabs([$aInt->lang("global", "searchfilter")]);
    echo "\n<form action=\"";
    echo $whmcs->getPhpSelf();
    echo "\" method=\"get\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
    echo $aInt->lang("fields", "clientname");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"client\" class=\"form-control input-250\" value=\"";
    echo $client;
    echo "\"></td><td width=\"10%\" class=\"fieldlabel\">";
    echo $aInt->lang("fields", "balance");
    echo "</td><td class=\"fieldarea\"><select name=\"balancetype\" class=\"form-control select-inline\"><option value=\"greater\">";
    echo $aInt->lang("affiliates", "greaterthan");
    echo "<option>";
    echo $aInt->lang("affiliates", "lessthan");
    echo "</select> <input type=\"text\" name=\"balance\" class=\"form-control input-100 input-inline\" value=\"";
    echo $balance;
    echo "\"></td></tr>\n<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("affiliates", "visitorsref");
    echo "</td><td class=\"fieldarea\"><select name=\"visitorstype\" class=\"form-control select-inline\"><option value=\"greater\">";
    echo $aInt->lang("affiliates", "greaterthan");
    echo "<option>";
    echo $aInt->lang("affiliates", "lessthan");
    echo "</select> <input type=\"text\" name=\"visitors\" class=\"form-control input-100 input-inline\" value=\"";
    echo $visitors;
    echo "\"></td><td class=\"fieldlabel\">";
    echo $aInt->lang("affiliates", "withdrawn");
    echo "</td><td class=\"fieldarea\"><select name=\"withdrawntype\" class=\"form-control select-inline\"><option value=\"greater\">";
    echo $aInt->lang("affiliates", "greaterthan");
    echo "<option>";
    echo $aInt->lang("affiliates", "lessthan");
    echo "</select> <input type=\"text\" name=\"withdrawn\" class=\"form-control input-100 input-inline\" value=\"";
    echo $withdrawn;
    echo "\"></td></tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("global", "search");
    echo "\" class=\"btn btn-default\">\n</div>\n\n</form>\n\n";
    echo $aInt->endAdminTabs();
    echo "\n<br>\n\n";
    if (!($orderby == "id" || $orderby == "date" || $orderby == "clientname" || $orderby == "visitors" || $orderby == "balance" || $orderby == "withdrawn")) {
        $orderby = "clientname";
    }
    $query .= " ORDER BY ";
    $query .= $orderby == "clientname" ? "tblclients.firstname " . $order . ",tblclients.lastname" : $orderby;
    $query .= " " . $order;
    $query = "SELECT tblaffiliates.*,tblclients.firstname,tblclients.lastname,tblclients.companyname,tblclients.groupid,tblclients.currency,(SELECT COUNT(*) FROM tblaffiliatesaccounts WHERE tblaffiliatesaccounts.affiliateid=tblaffiliates.id) AS signups " . $query . " LIMIT " . (int) ($page * $limit) . "," . (int) $limit;
    $result = full_query($query);
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $date = $data["date"];
        $userid = $data["clientid"];
        $visitors = $data["visitors"];
        $balance = $data["balance"];
        $withdrawn = $data["withdrawn"];
        $firstname = $data["firstname"];
        $lastname = $data["lastname"];
        $companyname = $data["companyname"];
        $groupid = $data["groupid"];
        $currency = $data["currency"];
        $signups = $data["signups"];
        $currency = getCurrency(NULL, $currency);
        $balance = formatCurrency($balance);
        $withdrawn = formatCurrency($withdrawn);
        $date = fromMySQLDate($date);
        $tabledata[] = ["<input type=\"checkbox\" name=\"selectedclients[]\" value=\"" . $id . "\" class=\"checkall\" />", "<a href=\"affiliates.php?action=edit&id=" . $id . "\">" . $id . "</a>", $date, $aInt->outputClientLink($userid, $firstname, $lastname, $companyname, $groupid), $visitors, $signups, $balance, $withdrawn, "<a href=\"?action=edit&id=" . $id . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $id . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    $tableformurl = "sendmessage.php?type=affiliate&multiple=true";
    $tableformbuttons = "<input type=\"submit\" value=\"" . $aInt->lang("global", "sendmessage") . "\" class=\"button btn btn-default\">";
    echo $aInt->sortableTable(["checkall", ["id", $aInt->lang("fields", "id")], ["date", $aInt->lang("affiliates", "signupdate")], ["clientname", $aInt->lang("fields", "clientname")], ["visitors", $aInt->lang("affiliates", "visitorsref")], $aInt->lang("affiliates", "signups"), ["balance", $aInt->lang("fields", "balance")], ["withdrawn", $aInt->lang("affiliates", "withdrawn")], "", ""], $tabledata, $tableformurl, $tableformbuttons);
} else {
    if ($action == "edit") {
        if (App::getFromRequest("pay") == "true") {
            $id = (int) App::getFromRequest("id");
            $serviceid = (int) App::getFromRequest("serviceid");
            $userid = (int) App::getFromRequest("userid");
            $error = AffiliatePayment($affaccid);
            if ($error) {
                WHMCS\FlashMessages::add(json_encode(["title" => AdminLang::trans("affiliates.paymentfailed"), "message" => $error]), "error");
            } else {
                WHMCS\FlashMessages::add(json_encode(["title" => AdminLang::trans("affiliates.paymentsuccess"), "message" => AdminLang::trans("affiliates.paymentsuccessdetail")]));
                logActivity("Manual Payout to Affiliate - Affiliate ID: " . $id . " - Service ID: " . $serviceid, $userid);
            }
            App::redirect("affiliates.php", ["action" => "edit", "id" => $id, "tab" => 2]);
        }
        $flashMessage = (new WHMCS\FlashMessages())->get();
        if ($flashMessage) {
            $type = $flashMessage["type"];
            $message = json_decode($flashMessage["text"], true);
            if (!$message || !is_array($message) || json_last_error() !== JSON_ERROR_NONE) {
                $message = ["title" => "", "message" => $flashMessage["text"]];
            }
            infoBox($message["title"], $message["message"], $type);
        }
        WHMCS\Session::release();
        echo $infobox;
        $result = select_query("tblaffiliates", "", ["id" => $id]);
        $data = mysql_fetch_array($result);
        $id = $data["id"];
        if (!$id) {
            $aInt->gracefulExit("Invalid Affiliate ID. Please Try Again...");
        }
        $date = $data["date"];
        $affiliateClientID = $data["clientid"];
        $visitors = $data["visitors"];
        $balance = $data["balance"];
        $withdrawn = $data["withdrawn"];
        $paymenttype = $data["paytype"];
        $payamount = $data["payamount"];
        $onetime = $data["onetime"];
        $result = select_query("tblclients", "", ["id" => $affiliateClientID]);
        $data = mysql_fetch_array($result);
        $firstname = $data["firstname"];
        $lastname = $data["lastname"];
        $result = select_query("tblaffiliatesaccounts", "COUNT(id)", ["affiliateid" => $id]);
        $data = mysql_fetch_array($result);
        $signups = $data[0];
        $result = select_query("tblaffiliatespending", "COUNT(*),SUM(tblaffiliatespending.amount)", ["affiliateid" => $id], "clearingdate", "DESC", "", "tblaffiliatesaccounts ON tblaffiliatesaccounts.id=tblaffiliatespending.affaccid INNER JOIN tblhosting ON tblhosting.id=tblaffiliatesaccounts.relid INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblclients ON tblclients.id=tblhosting.userid");
        $data = mysql_fetch_array($result);
        list($pendingcommissions, $pendingcommissionsamount) = $data;
        $currency = getCurrency($affiliateClientID);
        $date = fromMySQLDate($date);
        $pendingcommissionsamount = formatCurrency($pendingcommissionsamount);
        $conversionrate = 0 < $visitors ? round($signups / $visitors * 100, 2) : NULL;
        $aInt->deleteJSConfirm("doAccDelete", "affiliates", "refdeletesure", "affiliates.php?action=deletereferral&id=" . $id . "&affaccid=");
        $aInt->deleteJSConfirm("doPendingCommissionDelete", "affiliates", "pendeletesure", "affiliates.php?action=deletecommission&id=" . $id . "&cid=");
        $aInt->deleteJSConfirm("doAffHistoryDelete", "affiliates", "pytdeletesure", "affiliates.php?action=deletehistory&id=" . $id . "&hid=");
        $aInt->deleteJSConfirm("doWithdrawHistoryDelete", "affiliates", "witdeletesure", "affiliates.php?action=deletewithdrawal&id=" . $id . "&wid=");
        echo "\n<form method=\"post\" action=\"";
        echo $whmcs->getPhpSelf();
        echo "?action=save&id=";
        echo $id;
        echo "\">\n\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "id");
        echo "            </td>\n            <td class=\"fieldarea\">\n                ";
        echo $id;
        echo "            </td>\n            <td width=\"20%\"\n                class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "signupdate");
        echo "            </td>\n            <td class=\"fieldarea\">\n                ";
        echo $date;
        echo "            </td>\n        </tr>\n        <tr>\n            <td width=\"15%\" class=\"fieldlabel\">\n                ";
        echo $aInt->lang("fields", "clientname");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <a href=\"clientssummary.php?userid=";
        echo $affiliateClientID;
        echo "\">\n                    ";
        echo $firstname . " " . $lastname;
        echo "                </a>\n            </td>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "pendingcommissions");
        echo "            </td>\n            <td class=\"fieldarea\">\n                ";
        echo $pendingcommissionsamount;
        echo "            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "commissiontype");
        echo "</td>\n            <td class=\"fieldarea\">\n                <label class=\"radio-inline\">\n                    <input type=\"radio\" name=\"paymenttype\" value=\"\"";
        echo !$paymenttype ? " checked=\"checked\"" : "";
        echo ">\n                    ";
        echo $aInt->lang("affiliates", "usedefault");
        echo "                </label>\n                <label class=\"radio-inline\">\n                    <input type=\"radio\" name=\"paymenttype\" value=\"percentage\"";
        echo $paymenttype == "percentage" ? " checked=\"checked\"" : "";
        echo ">\n                    ";
        echo $aInt->lang("affiliates", "percentage");
        echo "                </label>\n                <label class=\"radio-inline\">\n                    <input type=\"radio\" name=\"paymenttype\" value=\"fixed\"";
        echo $paymenttype == "fixed" ? " checked=\"checked\"" : "";
        echo ">\n                    ";
        echo $aInt->lang("affiliates", "fixedamount");
        echo "                </label>\n            </td>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "availablebalance");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"number\" name=\"balance\" class=\"form-control input-100\" value=\"";
        echo $balance;
        echo "\" step=\"0.01\">\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
        echo $aInt->lang("affiliates", "commissionamount");
        echo "</td>\n            <td class=\"fieldarea\">\n                <input type=\"number\" name=\"payamount\" class=\"form-control input-inline input-100 \" value=\"";
        echo $payamount;
        echo "\" step=\"0.01\">\n                <label class=\"checkbox-inline\">\n                    <input type=\"checkbox\" name=\"onetime\" id=\"onetime\" value=\"1\"";
        echo $onetime ? " checked=\"checked\"" : "";
        echo " />\n                    Pay One Time Only\n                </label>\n            </td>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "withdrawnamount");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"number\" name=\"withdrawn\" class=\"form-control input-100\" value=\"";
        echo $withdrawn;
        echo "\" step=\"0.01\">\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "visitorsref");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"number\" name=\"visitors\" class=\"form-control input-75\" value=\"";
        echo $visitors;
        echo "\">\n            </td>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("affiliates", "conversionrate");
        echo "            </td>\n            <td class=\"fieldarea\">\n                ";
        echo !is_null($conversionrate) ? $conversionrate . "%" : AdminLang::trans("global.na");
        echo "            </td>\n        </tr>\n    </table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
        echo $aInt->lang("global", "savechanges");
        echo "\" class=\"btn btn-primary\">\n    <input type=\"reset\" value=\"";
        echo $aInt->lang("global", "cancelchanges");
        echo "\" class=\"btn btn-default\" />\n</div>\n\n</form>\n\n";
        echo $aInt->beginAdminTabs([AdminLang::trans("affiliates.referrals"), AdminLang::trans("affiliates.referredsignups"), AdminLang::trans("affiliates.pendingcommissions") . sprintf(" (%s)", $pendingcommissions), AdminLang::trans("affiliates.commissionshistory"), AdminLang::trans("affiliates.withdrawalshistory")], true);
        $referralTimePeriods = ["30" => "30 Days", "60" => "60 Days", "90" => "90 Days", "180" => "180 Days"];
        $adminUser = (new WHMCS\Authentication\CurrentUser())->admin();
        $adminPreferences = $adminUser->userPreferences ?? [];
        $maxToDisplay = $adminPreferences["tableLengths"]["summaryAffiliate"] ?? 25;
        $days = (int) App::getFromRequest("days");
        if (!$days) {
            $days = key($referralTimePeriods);
        }
        echo "<div class=\"text-right\"><strong>Time Period</strong> <div class=\"btn-group\" role=\"group\">";
        foreach ($referralTimePeriods as $referralDays => $referralLabel) {
            echo "<a href=\"affiliates.php?action=edit&id=" . $id . "&days=" . $referralDays . "\" class=\"btn btn-default" . ($days == $referralDays ? " active" : "") . "\">" . $referralLabel . "</a>";
        }
        echo "</div></div>";
        $chartData = [];
        $hitData = [];
        $referrers = WHMCS\Database\Capsule::table("tblaffiliates_hits")->join("tblaffiliates_referrers", "tblaffiliates_referrers.id", "=", "tblaffiliates_hits.referrer_id")->where("tblaffiliates_hits.affiliate_id", "=", $id)->where("tblaffiliates_hits.created_at", ">", WHMCS\Carbon::now()->subDays($days)->toDateTimeString())->groupBy(WHMCS\Database\Capsule::raw("date_format(tblaffiliates_hits.created_at, '%D %M %Y')"))->orderBy("tblaffiliates_hits.created_at", "DESC")->selectRaw("tblaffiliates_hits.created_at,COUNT(tblaffiliates_hits.id) as hits")->pluck("hits", "created_at")->all();
        foreach ($referrers as $created => $referrer) {
            $hitData[substr($created, 0, 10)] = $referrer;
        }
        for ($chartDay = 1; $chartDay <= $days; $chartDay++) {
            $chartData["rows"][] = ["c" => [["v" => WHMCS\Carbon::now()->subDays($days - $chartDay)->format("jS F Y")], ["v" => isset($hitData[WHMCS\Carbon::now()->subDays($days - $chartDay)->toDateString()]) ? $hitData[WHMCS\Carbon::now()->subDays($days - $chartDay)->toDateString()] : 0]]];
        }
        $chartData["cols"][] = ["label" => AdminLang::trans("fields.date"), "type" => "string"];
        $chartData["cols"][] = ["label" => AdminLang::trans("affiliates.numberOfHits"), "type" => "number"];
        echo (new WHMCS\Chart())->drawChart("Area", $chartData, [], "400px") . "<br>";
        $referrers = WHMCS\Database\Capsule::table("tblaffiliates_hits")->join("tblaffiliates_referrers", "tblaffiliates_referrers.id", "=", "tblaffiliates_hits.referrer_id")->where("tblaffiliates_hits.affiliate_id", "=", $id)->where("tblaffiliates_hits.created_at", ">", WHMCS\Carbon::now()->subDays($days)->toDateTimeString())->groupBy("tblaffiliates_hits.referrer_id")->orderBy("hits", "DESC")->selectRaw("referrer,COUNT(tblaffiliates_hits.id) as hits")->pluck("hits", "referrer")->all();
        $aInt->sortableTableInit("nopagination");
        $tabledata = [];
        foreach ($referrers as $referrer => $hits) {
            if (!trim($referrer)) {
                $referrer = AdminLang::trans("affiliates.noReferrer");
            } else {
                if (120 < strlen($referrer)) {
                    $referrer = substr($referrer, 0, 120) . "... <a href=\"#\">Reveal</a>";
                }
            }
            $tabledata[] = [$referrer, $hits];
        }
        echo $aInt->sortableTable([AdminLang::trans("affiliates.referrerUrl"), AdminLang::trans("affiliates.numberOfHits")], $tabledata);
        echo $aInt->nextAdminTab();
        $tabledata = [];
        $select = ["tblaffiliatesaccounts.id", "tblaffiliatesaccounts.lastpaid", "tblaffiliatesaccounts.relid", WHMCS\Database\Capsule::raw("CONCAT(tblclients.firstname,\" \" ,tblclients.lastname,\" \", tblclients.currency) as clientname"), "tblproducts.name", "tblhosting.userid", "tblhosting.domainstatus as productstatus", "tblhosting.domain", "tblhosting.amount", "tblhosting.firstpaymentamount", "tblhosting.regdate", "tblhosting.billingcycle"];
        $referredSignups = WHMCS\Affiliate\Accounts::has("service")->select($select)->where("affiliateid", $id)->join("tblhosting", "tblhosting.id", "=", "relid")->join("tblclients", "tblclients.id", "=", "userid")->join("tblproducts", "tblproducts.id", "=", "packageid")->orderBy("id", "desc");
        unset($select);
        $totalSignups = $referredSignups->count();
        $referredSignupsData = $referredSignups->limit($maxToDisplay)->get();
        foreach ($referredSignupsData as $data) {
            $serviceModel = $data->service;
            $clientModel = $serviceModel->client;
            $commission = calculateAffiliateCommission($id, $serviceModel->id, $data->lastpaid);
            $commission = formatCurrency($commission);
            $lastPaid = $data->lastpaid->isEmpty() ? AdminLang::trans("affiliates.never") : fromMySQLDate($data->lastpaid);
            $tabledata[] = ["id" => $id, "affaccid" => $data->id, "commission" => $commission, "lastpaid" => $lastPaid, "clientname" => $aInt->outputClientLink($clientModel->id, $clientModel->firstname, $clientModel->lastname, $clientModel->company), "userid" => $clientModel->id, "relid" => $serviceModel->id, "product" => $serviceModel->product->name, "productstatus" => $data->productstatus, "signupdate" => fromMySQLDate($serviceModel->regdate), "amountdesc" => WHMCS\Table\AffiliatesReferredSignupsTable::getServiceAmountDescription($serviceModel)];
        }
        unset($referredSignupsData);
        unset($serviceModel);
        unset($clientModel);
        $templatevars["affiliateId"] = $id;
        $templatevars["accounts"] = $tabledata;
        $templatevars["filteredSignups"] = $totalSignups;
        $templatevars["totalSignups"] = $totalSignups;
        $templatevars["AffiliatesPageLength"] = $maxToDisplay;
        $aInt->templatevars = $templatevars;
        $aInt->populateStandardAdminSmartyVariables();
        echo $aInt->getTemplate("affiliates/referredsignups");
        unset($templatevars);
        unset($tabledata);
        echo $aInt->nextAdminTab();
        $tabledata = [];
        $pending = WHMCS\Affiliate\Pending::whereHas("account", function ($query) use($id) {
            return $query->where("affiliateid", $id);
        })->orderBy("clearingdate", "DESC");
        $totalPending = $pending->count();
        $pendingData = $pending->limit($maxToDisplay)->get();
        foreach ($pendingData as $pendingDatum) {
            $invoiceNumString = "";
            if ($pendingDatum->invoice) {
                $invoiceNumString = sprintf("<a href=\"%s\">%s</a>", $pendingDatum->invoice->getEditInvoiceUrl(), $pendingDatum->invoice->getInvoiceNumber());
            }
            $tabledata[] = ["affaccid" => $pendingDatum->affiliateAccountId, "clientname" => $aInt->outputClientLink($pendingDatum->invoice->client->id, $pendingDatum->invoice->client->firstName, $pendingDatum->invoice->client->lastName, $pendingDatum->invoice->client->companyName), "product" => $pendingDatum->account->service->product->name, "productstatus" => $pendingDatum->account->service->status, "invoicenum" => $invoiceNumString, "amount" => formatCurrency($pendingDatum->amount), "clearingdate" => fromMySQLDate($pendingDatum->clearingDate), "pendingid" => $pendingDatum->id, "userid" => $pendingDatum->invoice->client->id, "relid" => $pendingDatum->account->relId];
        }
        $templatevars["affiliateId"] = $id;
        $templatevars["pending"] = $tabledata;
        $templatevars["filteredPending"] = $totalPending;
        $templatevars["totalPending"] = $totalPending;
        $templatevars["AffiliatesPageLength"] = $maxToDisplay;
        $aInt->templatevars = $templatevars;
        $aInt->populateStandardAdminSmartyVariables();
        echo $aInt->getTemplate("affiliates/pendingcommission");
        unset($templatevars);
        unset($tabledata);
        echo $aInt->nextAdminTab();
        $tabledata = [];
        $commissionHistory = WHMCS\Affiliate\History::where("affiliateid", $id)->orderBy("date", "desc");
        $totalHistory = $commissionHistory->count();
        $historyData = $commissionHistory->limit($maxToDisplay)->get();
        foreach ($historyData as $historyDatum) {
            $userid = "";
            $product = "";
            $relid = "";
            $status = "";
            $clientLInk = "";
            if ($historyDatum->affiliateAccountId) {
                $serviceModel = $historyDatum->account->service;
                $clientModel = $serviceModel->client;
                $userid = $clientModel->id;
                $product = $serviceModel->product->name;
                $relid = $serviceModel->id;
                $status = $serviceModel->status;
                $clientLInk = $aInt->outputClientLink($userid, $clientModel->firstName, $clientModel->lastName, $clientModel->companyName);
                unset($serviceModel);
                unset($clientModel);
            }
            $invoiceNumString = "";
            if ($historyDatum->invoice) {
                $invoiceNumString = sprintf("<a href=\"%s\">%s</a>", $historyDatum->invoice->getEditInvoiceUrl(), $historyDatum->invoice->getInvoiceNumber());
            }
            $tabledata[] = ["date" => fromMySQLDate($historyDatum->date), "affaccid" => $historyDatum->affiliateAccountId, "clientname" => $clientLInk, "description" => $historyDatum->description ?? "&nbsp;", "product" => $product, "productstatus" => $status, "invoicenum" => $invoiceNumString, "amount" => formatCurrency($historyDatum->amount), "historyid" => $historyDatum->id, "userid" => $userid, "relid" => $relid];
        }
        unset($historyData);
        $templatevars["affiliateId"] = $id;
        $templatevars["history"] = $tabledata;
        $templatevars["filteredHistory"] = $totalHistory;
        $templatevars["totalHistory"] = $totalHistory;
        $templatevars["AffiliatesPageLength"] = $maxToDisplay;
        $templatevars["referralDropdown"] = [];
        $result = select_query("tblaffiliatesaccounts", "tblaffiliatesaccounts.*,(SELECT CONCAT(tblclients.firstname,'|||',tblclients.lastname,'|||',tblhosting.userid,'|||',tblproducts.name,'|||',tblhosting.domainstatus,'|||',tblhosting.domain,'|||',tblhosting.amount,'|||',tblhosting.regdate,'|||',tblhosting.billingcycle) FROM tblhosting INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblclients ON tblclients.id=tblhosting.userid WHERE tblhosting.id=tblaffiliatesaccounts.relid) AS referraldata", ["affiliateid" => $id]);
        while ($data = mysql_fetch_array($result)) {
            $affaccid = $data["id"];
            $referraldata = explode("|||", $data["referraldata"]);
            $firstname = $referraldata[0] ?? "";
            $lastname = $referraldata[1] ?? "";
            $product = $referraldata[3] ?? "";
            $optionText = "ID " . $affaccid;
            if ($firstname . $lastname) {
                $optionText .= " - " . $firstname . " " . $lastname;
            }
            if (!empty($product)) {
                $optionText .= " - " . $product;
            }
            $templatevars["referralOptions"][] = ["value" => $affaccid, "text" => $optionText];
        }
        $aInt->templatevars = $templatevars;
        $aInt->populateStandardAdminSmartyVariables();
        echo $aInt->getTemplate("affiliates/commissionhistory");
        unset($templatevars);
        unset($tabledata);
        echo $aInt->nextAdminTab();
        $templatevars = [];
        $tabledata = [];
        $withdrawalHistory = WHMCS\Affiliate\Withdrawals::where("affiliateid", $id)->orderBy("date", "desc");
        $totalWiithdrawalHistory = $withdrawalHistory->count();
        $withdrawalhistoryData = $withdrawalHistory->limit($maxToDisplay)->get();
        foreach ($withdrawalhistoryData as $data) {
            $historyid = $data->id;
            $tabledata[] = ["date" => fromMySQLDate($data->date), "amount" => formatCurrency($data->amount), "affaccid" => $data->affaccid, "historyid" => $data->id];
        }
        $templatevars["affiliateId"] = $id;
        $templatevars["withdrawals"] = $tabledata;
        $templatevars["filteredHistory"] = $totalWiithdrawalHistory;
        $templatevars["totalHistory"] = $totalWiithdrawalHistory;
        $templatevars["AffiliatesPageLength"] = $maxToDisplay;
        $aInt->templatevars = $templatevars;
        $aInt->populateStandardAdminSmartyVariables();
        echo $aInt->getTemplate("affiliates/withdrawalhistory");
        unset($templatevars);
        unset($tabledata);
        echo $aInt->endAdminTabs();
    }
}
$content = $aInt->getFlashAsInfobox();
$content .= ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
