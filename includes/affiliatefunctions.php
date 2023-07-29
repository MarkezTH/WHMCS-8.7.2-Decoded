<?php

function affiliateActivate($userid)
{
    global $CONFIG;
    $result = select_query("tblclients", "currency", ["id" => $userid]);
    $data = mysql_fetch_array($result);
    $clientcurrency = $data["currency"];
    $bonusdeposit = convertCurrency($CONFIG["AffiliateBonusDeposit"], 1, $clientcurrency);
    $result = select_query("tblaffiliates", "id", ["clientid" => $userid]);
    $data = mysql_fetch_array($result);
    if (!is_array($data)) {
        $affiliateid = insert_query("tblaffiliates", ["date" => "now()", "clientid" => $userid, "balance" => $bonusdeposit]);
    } else {
        $affiliateid = $data["id"];
    }
    logActivity("Activated Affiliate Account - Affiliate ID: " . $affiliateid . " - User ID: " . $userid, $userid);
    run_hook("AffiliateActivation", ["affid" => $affiliateid, "userid" => $userid]);
}
