<?php
//Decoded By IonCube.cc V12 [ioncube.cc] 2023-7
define("CLIENTAREA", true);
require "init.php";
App::load_function("affiliate");
App::load_function("ticket");
$affiliate = NULL;
$pagetitle = Lang::trans("affiliatestitle");
$affiliateId = 0;
if ($client = Auth::client()) {
    $affiliate = $client->affiliate;
    if ($affiliate) {
        $affiliateId = $affiliate->id;
    }
}
if (WHMCS\Config\Setting::getValue("AffiliateEnabled")) {
    $displayTitle = $affiliateId ? Lang::trans("affiliatestitle") : Lang::trans("affiliatesactivate");
} else {
    $displayTitle = Lang::trans("affiliatestitle");
}
$tagline = $affiliateId ? Lang::trans("affiliatesrealtime") : "";
$ca = new WHMCS\ClientArea();
$ca->setPageTitle($pagetitle)->addToBreadCrumb("index.php", Lang::trans("globalsystemname"))->addToBreadCrumb("affiliates.php", Lang::trans("affiliatestitle"))->setTagLine($tagline)->setDisplayTitle($displayTitle)->initPage();
$ca->requireLogin();
$action = App::getFromRequest("action");
Auth::hasPermission("affiliates");
if (!$affiliateId) {
    if (WHMCS\Config\Setting::getValue("AffiliateEnabled") && App::getFromRequest("activate")) {
        check_token();
        affiliateActivate($client->id);
        redir();
    }
    $clientcurrency = $client->currencyId;
    $bonusdeposit = convertCurrency(WHMCS\Config\Setting::getValue("AffiliateBonusDeposit"), 1, $clientcurrency);
    $templatefile = "affiliatessignup";
    $ca->assign("affiliatesystemenabled", WHMCS\Config\Setting::getValue("AffiliateEnabled"));
    $ca->assign("bonusdeposit", formatCurrency($bonusdeposit));
    $ca->assign("payoutpercentage", WHMCS\Config\Setting::getValue("AffiliateEarningPercent") . "%");
} else {
    $templatefile = "affiliates";
    $currency = WHMCS\Billing\Currency::factoryForClientArea();
    $date = $affiliate->date;
    $visitors = $affiliate->visitorCount;
    $balance = $affiliate->balance;
    $withdrawn = $affiliate->amountWithdrawn;
    $signups = $affiliate->accounts()->count();
    $pendingcommissions = $affiliate->pendingCommissionAmount;
    $conversionrate = 0 < $visitors ? round($signups / $visitors * 100, 2) : 0;
    $ca->assign("affiliateid", $affiliateId);
    $ca->assign("referrallink", $affiliate->getReferralLink());
    $ca->assign("date", $date->toClientDateFormat());
    $ca->assign("visitors", $visitors);
    $ca->assign("signups", $signups);
    $ca->assign("conversionrate", $conversionrate);
    $ca->assign("pendingcommissions", formatCurrency($pendingcommissions));
    $ca->assign("balance", formatCurrency($balance));
    $ca->assign("withdrawn", formatCurrency($withdrawn));
    $affpayoutmin = WHMCS\Config\Setting::getValue("AffiliatePayout");
    if ($affpayoutmin < 0) {
        $affpayoutmin = 0;
    }
    $affpayoutmin = convertCurrency($affpayoutmin, 1, $currency["id"]);
    $ca->assign("withdrawlevel", false);
    if (0 < $balance && $affpayoutmin <= $balance) {
        $ca->assign("withdrawlevel", true);
        if ($action == "withdrawrequest") {
            $deptid = "";
            if (WHMCS\Config\Setting::getValue("AffiliateDepartment")) {
                $deptid = get_query_val("tblticketdepartments", "id", ["id" => WHMCS\Config\Setting::getValue("AffiliateDepartment")]);
                if (!$deptid) {
                    WHMCS\Config\Setting::setValue("AffiliateDepartment", 0);
                }
            }
            if (!$deptid) {
                $deptid = get_query_val("tblticketdepartments", "id", ["hidden" => ""], "order", "ASC");
            }
            $message = "Affiliate Account Withdrawal Request.  Details below:\n\nClient ID: " . Auth::client()->id . "\nAffiliate ID: " . $affiliateId . "\nBalance: " . $balance;
            $responses = run_hook("AffiliateWithdrawalRequest", ["affiliateId" => $affiliateId, "userId" => Auth::user()->id, "clientId" => Auth::client()->id, "balance" => $balance]);
            $skipTicket = false;
            foreach ($responses as $response) {
                if (array_key_exists("skipTicket", $response) && $response["skipTicket"]) {
                    $skipTicket = true;
                }
            }
            if (!$skipTicket) {
                try {
                    $ticketdetails = openNewTicket(Auth::client()->id, 0, $deptid, "Affiliate Withdrawal Request", $message, "Medium", "", [], "", "", "", false);
                    redir("withdraw=1");
                } catch (WHMCS\Exception\Support\TicketMaskIterationException $e) {
                    logActivity("Unable to create withdrawal request ticket. The system could not generate a ticket number because it reached the maximum number of attempts", Auth::client()->id, ["withClientId" => true]);
                    WHMCS\FlashMessages::add(Lang::trans("affiliatesWithdrawalRequestUnsuccessful"), "error");
                }
            }
        }
    }
    $ca->assign("withdrawrequestsent", App::getFromRequest("withdraw") ? true : false)->assign("affiliatePayoutMinimum", formatCurrency($affpayoutmin));
    $content = "\n<p><b>" . Lang::trans("affiliatesreferals") . "</b></p>\n<table align=\"center\" id=\"affiliates\" cellspacing=\"1\">\n<tr><td id=\"affiliatesheading\">" . Lang::trans("affiliatessignupdate") . "</td>" . "<td id=\"affiliatesheading\">" . Lang::trans("affiliateshostingpackage") . "</td>" . "<td id=\"affiliatesheading\">" . Lang::trans("affiliatesamount") . "</td>" . "<td id=\"affiliatesheading\">" . Lang::trans("affiliatescommission") . "</td>" . "<td id=\"affiliatesheading\">" . Lang::trans("affiliatesstatus") . "</td></tr>";
    $numitems = $affiliate->accounts()->count();
    list($orderby, $sort, $limit) = clientAreaTableInit("affiliates", "regdate", "DESC", $numitems);
    $ca->assign("orderby", $orderby)->assign("sort", strtolower($sort));
    switch ($orderby) {
        case "product":
            $orderby = "tblproducts`.`name";
            break;
        case "amount":
            $orderby = "tblhosting`.`amount";
            break;
        case "billingcycle":
            $orderby = "tblhosting`.`billingcycle";
            break;
        case "status":
            $orderby = "tblhosting`.`domainstatus";
            break;
        default:
            $orderby = "tblhosting`.`regdate";
            $referrals = [];
            $result = select_query("tblaffiliatesaccounts", "tblaffiliatesaccounts.*,tblhosting.userid,tblhosting.domainstatus,tblhosting.amount,tblhosting.firstpaymentamount,tblhosting.regdate,unix_timestamp(tblhosting.regdate) as regdate_ts,unix_timestamp(tblaffiliatesaccounts.lastpaid) as lastpaid_ts,tblhosting.billingcycle,tblhosting.packageid", ["affiliateid" => $affiliateId], $orderby, $sort, $limit, "tblhosting ON tblhosting.id=tblaffiliatesaccounts.relid INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblclients ON tblclients.id=tblhosting.userid");
            while ($data = mysql_fetch_array($result)) {
                $affaccid = $data["id"];
                $lastpaid = $data["lastpaid"];
                $lastpaidTs = $data["lastpaid_ts"];
                $relid = $data["relid"];
                $referralClientID = $data["userid"];
                $firstpaymentamount = $data["firstpaymentamount"];
                $amount = $data["amount"];
                $date = $data["regdate"];
                $dateTs = $data["regdate_ts"];
                $service = WHMCS\Product\Product::getProductName($data["packageid"]);
                $billingcycle = $data["billingcycle"];
                $rawstatus = $data["domainstatus"];
                $date = fromMySQLDate($date);
                $commission = calculateAffiliateCommission($affiliateId, $relid, $lastpaid);
                $lastpaid = $lastpaid == "0000-00-00" ? Lang::trans("never") : fromMySQLDate($lastpaid);
                $status = Lang::trans("clientarea" . strtolower($rawstatus));
                $billingcyclelang = strtolower($billingcycle);
                $billingcyclelang = str_replace([" ", "-"], "", $billingcyclelang);
                $billingcyclelang = Lang::trans("orderpaymentterm" . $billingcyclelang);
                $currency = getCurrency($referralClientID);
                $amountnum = 0;
                if ($billingcycle == "Free" || $billingcycle == "Free Account") {
                    $amountdesc = $billingcyclelang;
                } else {
                    if ($billingcycle == "One Time") {
                        $amountdesc = formatCurrency($firstpaymentamount) . " " . $billingcyclelang;
                        $amountnum = $firstpaymentamount;
                    } else {
                        $amountdesc = $firstpaymentamount != $amount ? formatCurrency($firstpaymentamount) . " " . Lang::trans("affiliatesinitialthen") . " " : "";
                        $amountdesc .= formatCurrency($amount) . " " . $billingcyclelang;
                        $amountnum = $firstpaymentamount != $amount ? $firstpaymentamount : $amount;
                    }
                }
                $currency = WHMCS\Billing\Currency::factoryForClientArea();
                $referrals[] = ["id" => $affaccid, "date" => $date, "datets" => $dateTs, "service" => $service, "package" => $service, "userid" => $referralClientID, "amount" => $amount, "billingcycle" => $billingcyclelang, "amountnum" => $amountnum, "amountdesc" => $amountdesc, "commissionnum" => $commission, "commission" => formatCurrency($commission), "lastpaid" => $lastpaid, "lastpaidts" => $lastpaidTs, "status" => $status, "rawstatus" => $rawstatus];
            }
            $ca->assign("referrals", $referrals);
            foreach (clientAreaTablePageNav($numitems) as $key => $value) {
                $ca->assign($key, $value);
            }
            $commissionhistory = [];
            foreach ($affiliate->history as $history) {
                $commissionhistory[] = ["date" => $history->date->toClientDateFormat(), "referralid" => $history->affiliateAccountId, "amount" => formatCurrency($history->amount)];
            }
            $ca->assign("commissionhistory", $commissionhistory);
            $withdrawalshistory = [];
            foreach ($affiliate->withdrawals as $withdrawal) {
                $withdrawalshistory[] = ["date" => $withdrawal->date->toClientDateFormat(), "amount" => formatCurrency($withdrawal->amount)];
            }
            $ca->assign("withdrawalshistory", $withdrawalshistory);
            $affiliatelinkscode = WHMCS\Input\Sanitize::decode(WHMCS\Config\Setting::getValue("AffiliateLinks"));
            $affiliatelinkscode = str_replace("[AffiliateLinkCode]", $affiliate->getReferralLink(), $affiliatelinkscode);
            $affiliatelinkscode = str_replace("<(", "&lt;", $affiliatelinkscode);
            $affiliatelinkscode = str_replace(")>", "&gt;", $affiliatelinkscode);
            $ca->assign("affiliatelinkscode", $affiliatelinkscode);
    }
}
$primarySidebar = Menu::primarySidebar("affiliateView");
$secondarySidebar = Menu::secondarySidebar("affiliateView");
$ca->assign("inactive", is_null(WHMCS\Config\Setting::getValue("AffiliateEnabled")));
$ca->setTemplate($templatefile)->addOutputHookFunction("ClientAreaPageAffiliates")->output();
