<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Clients Domains", false);
$aInt->requiredFiles(["clientfunctions", "domainfunctions", "gatewayfunctions", "registrarfunctions"]);
$aInt->setClientsProfilePresets();
$aInt->setHelpLink("Clients:Domains Tab");
$id = (int) App::getFromRequest("id");
$domainid = (int) App::getFromRequest("domainid");
$userid = (int) App::getFromRequest("userid");
$action = App::getFromRequest("action");
$domain = trim(App::getFromRequest("domain"));
if (!$id && $domainid) {
    $id = $domainid;
}
if (!$userid && !$id) {
    $userid = get_query_val("tblclients", "id", "", "id", "ASC", "0,1");
}
if ($userid && !$id) {
    $aInt->valUserID($userid);
    $id = get_query_val("tbldomains", "id", ["userid" => $userid], "domain", "ASC", "0,1");
}
if (!$id) {
    $aInt->gracefulExit($aInt->lang("domains", "nodomainsinfo") . " <a href=\"ordersadd.php?userid=" . $userid . "\">" . $aInt->lang("global", "clickhere") . "</a> " . $aInt->lang("orders", "toplacenew"));
}
$domains = new WHMCS\Domains();
$domain_data = $domains->getDomainsDatabyID($id);
$id = $did = $domainid = $domain_data["id"];
if (!$id || !$domain_data) {
    $aInt->gracefulExit(AdminLang::trans("domains.domainidnotfound"));
}
if ($userid != $domain_data["userid"]) {
    $userid = $domain_data["userid"];
    $aInt->valUserID($userid);
}
$aInt->setClientsProfilePresets($userid);
$aInt->assertClientBoundary($userid);
$currency = getCurrency($userid);
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Clients Domains");
    run_hook("DomainDelete", ["userid" => $userid, "domainid" => $id]);
    delete_query("tbldomains", ["id" => $id]);
    logActivity("Deleted Domain - User ID: " . $userid . " - Domain ID: " . $id, $userid);
    redir("userid=" . $userid);
}
$addonsPricing = WHMCS\Database\Capsule::table("tblpricing")->where("type", "domainaddons")->where("currency", $currency["id"])->where("relid", 0)->first(["msetupfee", "qsetupfee", "ssetupfee"]);
$domaindnsmanagementprice = $addonsPricing->msetupfee * $domain_data["registrationperiod"];
$domainemailforwardingprice = $addonsPricing->qsetupfee * $domain_data["registrationperiod"];
$domainidprotectionprice = $addonsPricing->ssetupfee * $domain_data["registrationperiod"];
$conf = App::getFromRequest("conf");
if ($action == "savedomain" && $domain) {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Clients Domains");
    $conf = "success";
    $regperiod = (int) App::getFromRequest("regperiod");
    $recurringamount = App::getFromRequest("recurringamount");
    if ($domain_data["is_premium"]) {
        $regperiod = $domain_data["registrationperiod"];
    }
    $domaindnsmanagementprice = $addonsPricing->msetupfee * $regperiod;
    $domainemailforwardingprice = $addonsPricing->qsetupfee * $regperiod;
    $domainidprotectionprice = $addonsPricing->ssetupfee * $regperiod;
    $olddnsmanagement = $domain_data["dnsmanagement"];
    $oldemailforwarding = $domain_data["emailforwarding"];
    $oldidprotection = $domain_data["idprotection"];
    $olddonotrenew = $domain_data["donotrenew"];
    $dnsmanagement = (int) (bool) App::getFromRequest("dnsmanagement");
    $emailforwarding = (int) (bool) App::getFromRequest("emailforwarding");
    $idprotection = (int) (bool) App::getFromRequest("idprotection");
    $idProtectionInRequest = App::isInRequest("idprotection");
    $donotrenew = (int) (bool) App::getFromRequest("donotrenew");
    $promoid = (int) App::getFromRequest("promoid");
    $oldlockstatus = App::getFromRequest("oldlockstatus");
    $lockstatus = App::getFromRequest("lockstatus");
    $newlockstatus = $lockstatus ? "locked" : "unlocked";
    $autorecalc = App::getFromRequest("autorecalc");
    $regdate = App::getFromRequest("regdate");
    $registrar = App::getFromRequest("registrar");
    $changelog = [];
    $logChangeFields = ["registrationdate" => "Registration Date", "domain" => "Domain Name", "firstpaymentamount" => "First Payment Amount", "recurringamount" => "Recurring Amount", "registrar" => "Registrar", "registrationperiod" => "Registration Period", "expirydate" => "Expiry Date", "subscriptionid" => "Subscription Id", "status" => "Status", "nextduedate" => "Next Due Date", "additionalnotes" => "Notes", "paymentmethod" => "Payment Method", "dnsmanagement" => "DNS Management", "emailforwarding" => "Email Forwarding", "idprotection" => "ID Protection", "donotrenew" => "Do Not Renew", "promoid" => "Promotion Code"];
    if ($olddnsmanagement) {
        if (!$dnsmanagement) {
            $recurringamount -= $domaindnsmanagementprice;
            $conf = "removeddns";
        }
    } else {
        if ($dnsmanagement) {
            $recurringamount += $domaindnsmanagementprice;
            $conf = "addeddns";
        }
    }
    if ($oldemailforwarding) {
        if (!$emailforwarding) {
            $recurringamount -= $domainemailforwardingprice;
            $conf = "removedemailforward";
        }
    } else {
        if ($emailforwarding) {
            $recurringamount += $domainemailforwardingprice;
            $conf = "addedemailforward";
        }
    }
    if ($idProtectionInRequest) {
        if ($oldidprotection) {
            if (!$idprotection) {
                $recurringamount -= $domainidprotectionprice;
                $conf = "removedidprotect";
            }
        } else {
            if ($idprotection) {
                $recurringamount += $domainidprotectionprice;
                $conf = "addedidprotect";
            }
        }
    }
    if ($autorecalc) {
        $domainObject = WHMCS\Domain\Domain::find($id);
        $domainObject->registrationPeriod = $regperiod;
        $domainObject->recalculateRecurringPrice();
        $recurringamount = $domainObject->recurringAmount;
        $regperiod = $domainObject->registrationPeriod;
        unset($domainObject);
    }
    $changes = [];
    foreach ($logChangeFields as $fieldName => $displayName) {
        $newValue = ${$fieldName} ?? NULL;
        if ($fieldName == "registrationdate") {
            $newValue = $regdate;
        }
        if ($fieldName == "registrationperiod") {
            $newValue = $regperiod;
        }
        $oldValue = $domain_data[$fieldName];
        if (in_array($fieldName, ["dnsmanagement", "emailforwarding", "idprotection", "donotrenew"]) && $newValue != $oldValue) {
            if ($newValue && !$oldValue) {
                $changelog[] = $displayName . " Enabled";
                if ($fieldName == "donotrenew") {
                    disableAutoRenew($id);
                }
            } else {
                if (!$newValue && $oldValue) {
                    $changelog[] = $displayName . " Disabled";
                }
            }
            $changes[$fieldName] = $newValue;
        } else {
            if (in_array($fieldName, ["promoid", "additionalnotes"]) && $newValue != $oldValue) {
                $changelog[] = $displayName . " Changed";
                $changes[$fieldName] = $newValue;
            }
            if (in_array($fieldName, ["registrationdate", "expirydate", "nextduedate"])) {
                $newValue = toMySQLDate($newValue);
            }
            if ($newValue != $oldValue) {
                $changelog[] = $displayName . " changed from '" . $oldValue . "' to '" . $newValue . "'";
                $changes[$fieldName] = $newValue;
                if ($fieldName == "nextduedate") {
                    $changes["nextinvoicedate"] = $newValue;
                }
                if ($fieldName == "expirydate") {
                    $changes["reminders"] = "";
                }
            }
        }
    }
    if (0 < count($changes)) {
        WHMCS\Database\Capsule::table("tbldomains")->where("id", $id)->update($changes);
        logActivity("Modified Domain - " . implode(", ", $changelog) . " - User ID: " . $userid . " - Domain ID: " . $id, $userid);
    }
    if (isset($domainfield) && is_array($domainfield)) {
        $additflds = new WHMCS\Domains\AdditionalFields();
        $additflds->setDomain($domain)->setDomainType($domain_data["type"])->setFieldValues($domainfield)->saveToDatabase($id, false);
    }
    loadRegistrarModule($registrar);
    if (function_exists($registrar . "_AdminDomainsTabFieldsSave")) {
        $domainparts = explode(".", $domain, 2);
        $params = [];
        $params["domainid"] = $id;
        list($params["sld"], $params["tld"]) = $domainparts;
        $params["regperiod"] = $regperiod;
        $params["registrar"] = $registrar;
        $fieldsarray = call_user_func($registrar . "_AdminDomainsTabFieldsSave", $params);
    }
    run_hook("AdminClientDomainsTabFieldsSave", $_REQUEST);
    run_hook("DomainEdit", ["userid" => $userid, "domainid" => $id]);
    $domainsavetemp = ["ns1" => $ns1 ?? NULL, "ns2" => $ns2 ?? NULL, "ns3" => $ns3 ?? NULL, "ns4" => $ns4 ?? NULL, "ns5" => $ns5 ?? NULL, "oldns1" => $oldns1 ?? NULL, "oldns2" => $oldns2 ?? NULL, "oldns3" => $oldns3 ?? NULL, "oldns4" => $oldns4 ?? NULL, "oldns5" => $oldns5 ?? NULL, "defaultns" => $defaultns ?? NULL, "newlockstatus" => $newlockstatus, "oldlockstatus" => $oldlockstatus, "oldidprotection" => $oldidprotection, "idprotection" => $idProtectionInRequest ? $idprotection : $oldidprotection];
    WHMCS\Session::set("domainsavetemp", $domainsavetemp);
    redir("userid=" . $userid . "&id=" . $id . "&conf=" . $conf);
}
ob_start();
$did = $domain_data["id"];
$orderid = $domain_data["orderid"];
$ordertype = $domain_data["type"];
$domain = $domain_data["domain"];
$domainPunycode = "";
if ($domain) {
    try {
        $domainPunycode = WHMCS\Domains\Idna::toPunycode($domain);
    } catch (Exception $e) {
        $domainPunycode = $e->getMessage();
    }
}
$paymentmethod = $domain_data["paymentmethod"];
$gateways = new WHMCS\Gateways();
if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
    $paymentmethod = ensurePaymentMethodIsSet($userid, $id, "tbldomains");
}
$firstpaymentamount = $domain_data["firstpaymentamount"];
$recurringamount = $domain_data["recurringamount"];
$registrar = $domain_data["registrar"];
$regtype = $domain_data["type"];
$expirydate = $domain_data["expirydate"];
$nextduedate = $domain_data["nextduedate"];
$subscriptionid = $domain_data["subscriptionid"];
$promoid = $domain_data["promoid"];
$registrationdate = $domain_data["registrationdate"];
$registrationperiod = $domain_data["registrationperiod"];
$domainstatus = $domain_data["status"];
$additionalnotes = $domain_data["additionalnotes"];
$dnsmanagement = $domain_data["dnsmanagement"];
$emailforwarding = $domain_data["emailforwarding"];
$idprotection = $domain_data["idprotection"];
$donotrenew = $domain_data["donotrenew"];
$isPremium = $domain_data["is_premium"];
$expirydate = fromMySQLDate($expirydate);
$nextduedate = fromMySQLDate($nextduedate);
$regdate = fromMySQLDate($registrationdate);
$token = generate_token("link");
$modalHtml = $aInt->modal("Renew", $aInt->lang("domains", "renewdomain"), $aInt->lang("domains", "renewdomainq"), [["title" => $aInt->lang("global", "yes"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&regaction=renew" . $token . "\"", "class" => "btn-primary"], ["title" => $aInt->lang("global", "no")]]);
$modalHtml .= $aInt->modal("GetEPP", $aInt->lang("domains", "requestepp"), $aInt->lang("domains", "requesteppq"), [["title" => $aInt->lang("global", "yes"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&regaction=eppcode" . $token . "\"", "class" => "btn-primary"], ["title" => $aInt->lang("global", "no")]]);
$modalHtml .= $aInt->modal("RequestDelete", $aInt->lang("domains", "requestdel"), $aInt->lang("domains", "requestdelq"), [["title" => $aInt->lang("global", "yes"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&regaction=reqdelete" . $token . "\"", "class" => "btn-primary"], ["title" => $aInt->lang("global", "no")]]);
$modalHtml .= $aInt->modal("Delete", $aInt->lang("domains", "delete"), $aInt->lang("domains", "deleteq"), [["title" => $aInt->lang("global", "yes"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&action=delete" . $token . "\"", "class" => "btn-primary"], ["title" => $aInt->lang("global", "no")]]);
$modalHtml .= $aInt->modal("ReleaseDomain", $aInt->lang("domains", "releasedomain"), $aInt->lang("domains", "releasedomainq") . "<div class=\"margin-top-bottom-20\"><table width=\"80%\" align=\"center\"><tr><td>" . $aInt->lang("domains", "transfertag") . ":</td><td>" . "<input type=\"text\" id=\"transtag\" class=\"form-control\" />" . "</td></tr></table></div>", [["title" => $aInt->lang("global", "submit"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&regaction=release&transtag=\" + jQuery(\"#transtag\").val() + \"" . $token . "\"", "class" => "btn-primary"], ["title" => $aInt->lang("global", "cancel")]]);
$modalHtml .= $aInt->modal("CancelSubscription", $aInt->lang("services", "cancelSubscription"), $aInt->lang("services", "cancelSubscriptionSure"), [["title" => $aInt->lang("global", "yes"), "onclick" => "cancelSubscription()", "class" => "btn-primary"], ["title" => $aInt->lang("global", "no")]]);
$domainsavetemp = WHMCS\Session::get("domainsavetemp");
WHMCS\Session::delete("domainsavetemp");
if ($conf && $domainsavetemp) {
    $ns1 = $domainsavetemp["ns1"];
    $ns2 = $domainsavetemp["ns2"];
    $ns3 = $domainsavetemp["ns3"];
    $ns4 = $domainsavetemp["ns4"];
    $ns5 = $domainsavetemp["ns5"];
    $oldns1 = $domainsavetemp["oldns1"];
    $oldns2 = $domainsavetemp["oldns2"];
    $oldns3 = $domainsavetemp["oldns3"];
    $oldns4 = $domainsavetemp["oldns4"];
    $oldns5 = $domainsavetemp["oldns5"];
    $defaultns = $domainsavetemp["defaultns"];
    $newlockstatus = $domainsavetemp["newlockstatus"];
    $oldlockstatus = $domainsavetemp["oldlockstatus"];
    $oldidprotect = $domainsavetemp["oldidprotection"];
    $idprotect = $domainsavetemp["idprotection"];
} else {
    $ns1 = "";
    $ns2 = "";
    $ns3 = "";
    $ns4 = "";
    $ns5 = "";
    $oldns1 = "";
    $oldns2 = "";
    $oldns3 = "";
    $oldns4 = "";
    $oldns5 = "";
    $defaultns = "";
    $newlockstatus = "";
    $oldlockstatus = "";
    $oldidprotect = "";
    $idprotect = "";
}
switch ($conf) {
    case "success":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("global", "changesuccessdesc"), "success");
        break;
    case "addeddns":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("domains", "dnsmanagementadded"), "success");
        break;
    case "addedemailforward":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("domains", "emailforwardingadded"), "success");
        break;
    case "addedidprotect":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("domains", "idprotectionadded"), "success");
        break;
    case "removeddns":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("domains", "dnsmanagementremoved"), "success");
        break;
    case "removedemailforward":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("domains", "emailforwardingremoved"), "success");
        break;
    case "removedidprotect":
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("domains", "idprotectionremoved"), "success");
        break;
    case "domainreleasedanddeleted":
        $successMessage = WHMCS\Session::getAndDelete("DomainReleaseInfo");
        infoBox(AdminLang::trans("domains.releasesuccess"), $successMessage, "success");
        break;
    default:
        WHMCS\Session::release();
        $domainregistraractions = checkPermission("Perform Registrar Operations", true) && $domains->getModule() ? true : false;
        if ($domainregistraractions) {
            $domainparts = explode(".", $domain, 2);
            $domainPunycodeParts = explode(".", $domainPunycode, 2);
            $params = [];
            $params["domainid"] = $id;
            $params["sld"] = $domainparts[0];
            $params["sld_punycode"] = $domainPunycodeParts[0];
            $params["tld"] = $domainparts[1];
            $params["tld_punycode"] = $domainPunycodeParts[1];
            $params["regperiod"] = $registrationperiod;
            $params["registrar"] = $registrar;
            $params["regtype"] = $regtype;
            $adminbuttonarray = "";
            loadRegistrarModule($registrar);
            if (function_exists($registrar . "_AdminCustomButtonArray")) {
                $adminbuttonarray = call_user_func($registrar . "_AdminCustomButtonArray", $params);
            }
            if ($oldns1 != $ns1 || $oldns2 != $ns2 || $oldns3 != $ns3 || $oldns4 != $ns4 || $oldns5 != $ns5 || $defaultns) {
                $nameservers = $defaultns ? $domains->getDefaultNameservers() : ["ns1" => $ns1, "ns2" => $ns2, "ns3" => $ns3, "ns4" => $ns4, "ns5" => $ns5];
                $success = $domains->moduleCall("SaveNameservers", $nameservers);
                if (!$success) {
                    infoBox($aInt->lang("domains", "nschangefail"), $domains->getLastError(), "error");
                } else {
                    infoBox($aInt->lang("domains", "nschangesuccess"), $aInt->lang("domains", "nschangeinfo"), "success");
                }
            }
            if (!$oldlockstatus) {
                $oldlockstatus = $newlockstatus;
            }
            if ($newlockstatus != $oldlockstatus) {
                $params["lockenabled"] = $newlockstatus;
                $values = RegSaveRegistrarLock($params);
                if ($values["error"]) {
                    infoBox($aInt->lang("domains", "reglockfailed"), $values["error"], "error");
                } else {
                    infoBox($aInt->lang("domains", "reglocksuccess"), $aInt->lang("domains", "reglockinfo"), "success");
                }
            }
            if ($regaction = App::getFromRequest("regaction")) {
                check_token("WHMCS.admin.default");
                define("NO_QUEUE", true);
            }
            if ($regaction == "renew") {
                $values = RegRenewDomain($params);
                WHMCS\Cookie::set("DomRenewRes", $values);
                redir("userid=" . $userid . "&id=" . $id . "&conf=renew");
            }
            if ($regaction == "eppcode") {
                $values = RegGetEPPCode($params);
                if ($values["error"]) {
                    infoBox($aInt->lang("domains", "eppfailed"), $values["error"], "error");
                } else {
                    if ($values["eppcode"]) {
                        infoBox($aInt->lang("domains", "epprequest"), $_LANG["domaingeteppcodeis"] . " " . $values["eppcode"], "success");
                    } else {
                        infoBox($aInt->lang("domains", "epprequest"), $_LANG["domaingeteppcodeemailconfirmation"], "success");
                    }
                }
            }
            if ($regaction == "reqdelete") {
                $values = RegRequestDelete($params);
                if ($values["error"]) {
                    infoBox($aInt->lang("domains", "deletefailed"), $values["error"], "error");
                } else {
                    infoBox($aInt->lang("domains", "deletesuccess"), $aInt->lang("domains", "deleteinfo"), "success");
                }
            }
            if ($regaction == "release") {
                $params["transfertag"] = $transtag;
                $values = RegReleaseDomain($params);
                if (array_key_exists("deleted", $values) && $values["deleted"]) {
                    $successMessage = AdminLang::trans("domains.releasedAndDeleted", [":domain" => $domain, ":tag" => $transtag]);
                    WHMCS\Session::setAndRelease("DomainReleaseInfo", $successMessage);
                    App::redirect(App::getPhpSelf(), ["userid" => $userid, "conf" => "domainreleasedanddeleted"]);
                }
                $successmessage = str_replace("%s", $transtag, $aInt->lang("domains", "releaseinfo"));
                if ($values["error"]) {
                    infoBox(AdminLang::trans("domains.releasefailed"), $values["error"], "error");
                } else {
                    infoBox(AdminLang::trans("domains.releasesuccess"), $successmessage, "success");
                    WHMCS\Database\Capsule::table("tbldomains")->where("id", $domainid)->update(["status" => WHMCS\Domain\Status::TRANSFERRED_AWAY]);
                    $domainstatus = WHMCS\Domain\Status::TRANSFERRED_AWAY;
                    $domain_data["status"] = WHMCS\Domain\Status::TRANSFERRED_AWAY;
                }
            }
            if ($regaction == "idtoggle") {
                $params["protectenable"] = !(bool) (int) $domain_data["idprotection"];
                $values = RegIDProtectToggle($params);
                if ($values["error"]) {
                    infoBox(AdminLang::trans("domains.idprotectfailed"), $values["error"], "error");
                } else {
                    $idprotection = !(bool) (int) $domain_data["idprotection"];
                    $recurringamount = $domain_data["recurringamount"] - $domainidprotectionprice;
                    if ($idprotection) {
                        $recurringamount = $domain_data["recurringamount"] + $domainidprotectionprice;
                    }
                    $updateArray = ["idprotection" => $idprotection, "recurringamount" => $recurringamount];
                    WHMCS\Database\Capsule::table("tbldomains")->where("id", $domain_data["id"])->update($updateArray);
                    infoBox(AdminLang::trans("domains.idprotectsuccess"), AdminLang::trans("domains.idprotectinfo"), "success");
                }
            }
            if ($regaction == "resendirtpemail" && $domains->hasFunction("ResendIRTPVerificationEmail")) {
                $success = $domains->moduleCall("ResendIRTPVerificationEmail");
                if ($success) {
                    infoBox(AdminLang::trans("domains.resendNotification"), AdminLang::trans("domains.resendNotificationSuccess"), "success");
                } else {
                    if ($values["error"]) {
                        infoBox(AdminLang::trans("domains.resendNotification"), $values["error"], "error");
                    }
                }
            }
            if ($regaction == "custom") {
                $values = RegCustomFunction($params, $ac);
                if ($values["error"]) {
                    infoBox($aInt->lang("domains", "registrarerror"), $values["error"], "error");
                } else {
                    if (!$values["message"]) {
                        $values["message"] = $aInt->lang("domains", "changesuccess");
                    }
                    infoBox($aInt->lang("domains", "changesuccess"), $values["message"], "success");
                }
            }
            if ($conf == "renew") {
                $values = WHMCS\Cookie::get("DomRenewRes", 1);
                if ($values["error"]) {
                    infoBox($aInt->lang("domains", "renewfailed"), $values["error"], "error");
                } else {
                    $successmessage = str_replace("%s", $registrationperiod, $aInt->lang("domains", "renewinfo"));
                    infoBox($aInt->lang("domains", "renewsuccess"), $successmessage, "success");
                }
            }
            $nsvalues = ["ns1" => NULL, "ns2" => NULL, "ns3" => NULL, "ns4" => NULL, "ns5" => NULL];
            $lockstatus = NULL;
            $showResendIRTPVerificationEmail = false;
            $alerts = [];
            try {
                $domainInformation = $domains->getDomainInformation();
                $nsvalues = array_merge($nsvalues, $domainInformation->getNameservers());
                $registrarLockStatus = $domainInformation->getTransferLock();
                if (!is_null($registrarLockStatus)) {
                    $lockstatus = "unlocked";
                    if ($registrarLockStatus === true) {
                        $lockstatus = "locked";
                    }
                }
                if ($domainInformation->isIrtpEnabled() && $domainInformation->isContactChangePending()) {
                    $title = AdminLang::trans("domains.contactChangePending");
                    $description = "domains.contactsChanged";
                    if ($domainInformation->getPendingSuspension()) {
                        $title = AdminLang::trans("domains.verificationRequired");
                        $description = "domains.newRegistration";
                    }
                    $parameters = [];
                    if ($domainInformation->getDomainContactChangeExpiryDate()) {
                        $description .= "Date";
                        $parameters = [":date" => $domainInformation->getDomainContactChangeExpiryDate()->toAdminDateFormat()];
                    }
                    $description = AdminLang::trans($description, $parameters);
                    $alerts[] = WHMCS\View\Helper::alert("<strong>" . $title . "</strong><br>" . $description);
                    $showResendIRTPVerificationEmail = true;
                }
                if ($domainInformation->isIrtpEnabled() && $domainInformation->getIrtpTransferLock()) {
                    $title = AdminLang::trans("domains.irtpLockEnabled");
                    $description = AdminLang::trans("domains.irtpLockDescription");
                    if ($domainInformation->getIrtpTransferLockExpiryDate()) {
                        $description = AdminLang::trans("domains.irtpLockDescriptionDate", [":date" => $domainInformation->getIrtpTransferLockExpiryDate()->toAdminDateFormat()]);
                    }
                    $alerts[] = WHMCS\View\Helper::alert("<strong>" . $title . "</strong><br>" . $description);
                }
            } catch (Exception $e) {
                if (!$infobox) {
                    infoBox(AdminLang::trans("domains.registrarerror"), $e->getMessage(), "error");
                }
            }
        }
        if (isset($showResendIRTPVerificationEmail) && $showResendIRTPVerificationEmail && $domains->hasFunction("ResendIRTPVerificationEmail")) {
            $modalHtml .= $aInt->modal("ResendIRTPVerificationEmail", AdminLang::trans("domains.resendNotification"), AdminLang::trans("domains.resendNotificationQuestion"), [["title" => AdminLang::trans("global.submit"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&regaction=resendirtpemail" . $token . "\"", "class" => "btn-primary"], ["title" => AdminLang::trans("global.cancel")]]);
        }
        $idProtectTitle = "domains.enableIdProtection";
        $idProtectQuestion = "domains.enableIdProtectionQuestion";
        if ($idprotection) {
            $idProtectTitle = "domains.disableIdProtection";
            $idProtectQuestion = "domains.disableIdProtectionQuestion";
        }
        $modalHtml .= $aInt->modal("IdProtectToggle", AdminLang::trans($idProtectTitle), AdminLang::trans($idProtectQuestion), [["title" => AdminLang::trans("global.yes"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&regaction=idtoggle" . $token . "\"", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
        echo "\n<div class=\"context-btn-container\">\n    <div class=\"row\">\n        <div class=\"col-sm-7 text-left\">\n            <form action=\"";
        echo $whmcs->getPhpSelf();
        echo "\" method=\"get\">\n                <input type=\"hidden\" name=\"userid\" value=\"";
        echo $userid;
        echo "\">\n                ";
        echo $aInt->lang("clientsummary", "domains");
        echo ":\n                <select name=\"id\" onChange=\"submit()\" class=\"form-control select-inline\">\n";
        $result = select_query("tbldomains", "", ["userid" => $userid], "domain", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $domainlistid = $data["id"];
            $domainlistname = $data["domain"];
            $domainliststatus = $data["status"];
            echo "<option value=\"" . $domainlistid . "\"";
            if ($domainlistid == $id) {
                echo " selected";
            }
            if ($domainliststatus == "Pending") {
                echo " style=\"background-color:#ffffcc;\"";
            } else {
                if (in_array($domainliststatus, ["Expired", "Cancelled", "Fraud", "Transferred Away"])) {
                    echo " style=\"background-color:#ff9999;\"";
                }
            }
            echo ">" . $domainlistname . "</option>";
        }
        echo "                </select>\n                <noscript>\n                    <input type=\"submit\" value=\"";
        echo $aInt->lang("global", "go");
        echo "\" class=\"btn btn-success btn-sm\" />\n                </noscript>\n            </form>\n        </div>\n        <div class=\"col-sm-5\">\n            ";
        $sslStatus = WHMCS\Domain\Ssl\Status::factory($userid, $domain);
        $html = "<img src=\"%s\"\n                           class=\"%s\"\n                           data-toggle=\"tooltip\"\n                           title=\"%s\"\n                           data-domain=\"%s\"\n                           data-user-id=\"%s\"\n                           >";
        echo sprintf($html, $sslStatus->getImagePath(), $sslStatus->getClass(), $sslStatus->getTooltipContent(), $domain, $userid);
        echo "            ";
        $viewInvoicesLabel = AdminLang::trans("invoices.viewinvoices");
        $transferLabel = AdminLang::trans("clients.transferownership");
        $sendMessageLabel = AdminLang::trans("global.sendmessage");
        $deleteLabel = AdminLang::trans("global.delete");
        $viewInvoicesLink = "clientsinvoices.php?userid=" . $userid . "&domainid=" . $id;
        $deleteLink = "<a href=\"#\" data-toggle=\"modal\" data-target=\"#modalDelete\">";
        $transferButton = "<li>\n    <a href=\"#\" onclick=\"window.open('clientsmove.php?type=domain&id=" . $id . "','movewindow','width=500,height=300,top=100,left=100,scrollbars=yes');return false\">\n        <i class=\"fas fa-random fa-fw\"></i>\n        " . $transferLabel . "\n    </a>\n</li>";
        $viewInvoicesBtn = "<a class=\"btn btn-default\" href=\"" . $viewInvoicesLink . "\">\n    <i class=\"fas fa-file-invoice fa-fw\"></i>\n    " . $viewInvoicesLabel . "\n</a>";
        echo "<div class=\"btn-group\" style=\"margin-left:10px;\">\n    " . $viewInvoicesBtn . "\n    <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">\n        More\n        <span class=\"caret\"></span>\n        <span class=\"sr-only\">Toggle Dropdown</span>\n    </button>\n    <ul class=\"dropdown-menu dropdown-menu-right\">\n    " . $transferButton . "\n    <li role=\"separator\" class=\"divider\"></li>\n    <li>\n        <a href=\"#\" data-toggle=\"modal\" data-target=\"#modalSendEmail\">\n            <i class=\"fas fa-envelope fa-fw\"></i>\n            " . $sendMessageLabel . "\n        </a>\n    </li>\n    <li role=\"separator\" class=\"divider\"></li>\n    <li>\n        " . $deleteLink . "\n            <i class=\"fas fa-trash fa-fw\"></i>\n            " . $deleteLabel . "\n        </a>\n    </li>\n  </ul>\n</div>";
        echo "        </div>\n    </div>\n</div>\n\n";
        if ($infobox) {
            echo $infobox;
        }
        if (isset($alerts) && $alerts) {
            echo implode($alerts);
        }
        $premiumLabel = $renewalCostInfo = "";
        $regPeriodDivAdditional = $regPeriodInputClasses = $regPeriodInputAdditional = "";
        if ($isPremium) {
            $extraData = WHMCS\Domain\Extra::whereDomainId($domain_data["id"])->pluck("value", "name");
            $renewalCost = convertCurrency($extraData["registrarRenewalCostPrice"], $extraData["registrarCurrency"], $currency["id"]);
            $premiumLabel = " <span class=\"label label-danger\">" . AdminLang::trans("domains.premiumDomain") . "</span>";
            $regPeriodDivAdditional = " data-toggle=\"tooltip\" data-placement=\"left\" data-trigger=\"hover\" title=\"" . AdminLang::trans("domains.periodPremiumDomains") . "\"";
            $regPeriodInputClasses = " disabled";
            $regPeriodInputAdditional = " disabled=\"disabled\"";
            $renewalCostInfo = "<span class=\"badge\">" . AdminLang::trans("domains.premiumRenewalCost") . ": " . formatCurrency((double) $renewalCost, true)->toPrefixed() . "</span>";
        }
        $infoLabel = "";
        if ($domain !== $domainPunycode) {
            $infoLabel = " <span class=\"label label-info\">" . AdminLang::trans("global.idnDomain") . "</span>";
        }
        echo "\n<form method=\"post\" action=\"";
        echo $whmcs->getPhpSelf();
        echo "?action=savedomain&userid=";
        echo $userid;
        echo "&id=";
        echo $id;
        echo "\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td width=\"15%\" class=\"fieldlabel\">";
        echo $aInt->lang("fields", "ordernum");
        echo "</td>\n    <td width=\"35%\" class=\"fieldarea\">\n        ";
        echo $orderid;
        echo " - <a href=\"orders.php?action=view&id=";
        echo $orderid;
        echo "\">\n            ";
        echo AdminLang::trans("orders.vieworder");
        echo "        </a>\n        ";
        echo $infoLabel;
        echo "    </td>\n    <td width=\"15%\" class=\"fieldlabel\">";
        echo $aInt->lang("domains", "regperiod");
        echo "</td>\n    <td width=\"35%\" class=\"fieldarea\">\n        <div class=\"form-inline\">\n            <input type=\"hidden\" name=\"regperiod\" value=\"";
        echo $registrationperiod;
        echo "\">\n            ";
        echo "<div class=\"input-group\"" . $regPeriodDivAdditional . ">\n    <input type=\"number\" name=\"regperiod\" value=\"" . $registrationperiod . "\" class=\"form-control input-60" . $regPeriodInputClasses . "\"" . $regPeriodInputAdditional . ">\n    <span class=\"input-group-addon\">" . AdminLang::trans("domains.years") . "</span>\n</div>";
        echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("orders", "ordertype");
        echo "</td>\n    <td class=\"fieldarea\">";
        echo $ordertype . $premiumLabel;
        echo "</td>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "regdate");
        echo "</td>\n    <td class=\"fieldarea\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputRegDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputRegDate\"\n                   type=\"text\"\n                   name=\"regdate\"\n                   value=\"";
        echo $regdate;
        echo "\"\n                   class=\"form-control date-picker-single\"\n            />\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "domain");
        echo "</td>\n    <td class=\"fieldarea\">\n        <div class=\"input-group input-300\">\n            <input id=\"inputDomain\" type=\"text\" name=\"domain\" class=\"form-control domain-input\" value=\"";
        echo $domain;
        echo "\">\n            <input id=\"inputDomainPunycode\" type=\"text\" class=\"form-control domain-input hidden disabled\" readonly value=\"";
        echo $domainPunycode;
        echo "\">\n            <div class=\"input-group-btn\">\n                <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\" style=\"margin-left:-3px;\">\n                    <span class=\"caret\"></span>\n                </button>\n                <ul class=\"dropdown-menu dropdown-menu-right\">\n                    <li><a href=\"https://www.";
        echo $domain;
        echo "\" target=\"_blank\">www</a>\n                    <li><a onclick=\"\$('#frmWhois').submit();return false\">";
        echo $aInt->lang("domains", "whois");
        echo "</a>\n                    <li><a href=\"https://www.intodns.com/";
        echo $domain;
        echo "\" target=\"_blank\">intoDNS</a></li>\n                </ul>\n            </div>\n        </div>\n        ";
        if ($domainPunycode && $domain !== $domainPunycode) {
            echo "<input type=\"text\" value=\"" . $domainPunycode . "\"" . " class=\"form-control input-300 domain-input disabled\" readonly>";
        }
        echo "    </td>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "expirydate");
        echo "</td>\n    <td class=\"fieldarea\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputExpiryDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputExpiryDate\"\n                   type=\"text\"\n                   name=\"expirydate\"\n                   value=\"";
        echo $expirydate;
        echo "\"\n                   class=\"form-control date-picker-single future\"\n            />\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "registrar");
        echo "</td>\n    <td class=\"fieldarea\">";
        echo getRegistrarsDropdownMenu($registrar);
        echo "</td>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "nextduedate");
        echo "</td>\n    <td class=\"fieldarea\">\n        <input type=\"hidden\" name=\"oldnextduedate\" value=\"";
        echo $nextduedate;
        echo "\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputNextDueDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputNextDueDate\"\n                   type=\"text\"\n                   name=\"nextduedate\"\n                   value=\"";
        echo $nextduedate;
        echo "\"\n                   class=\"form-control date-picker-single future\"\n            />\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "firstpaymentamount");
        echo "</td>\n    <td class=\"fieldarea\">\n    <input type=\"text\" name=\"firstpaymentamount\" class=\"form-control input-100\" value=\"";
        echo $firstpaymentamount;
        echo "\">\n    </td>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "paymentmethod");
        echo "</td>\n    <td class=\"fieldarea\">";
        echo paymentMethodsSelection();
        echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
        echo AdminLang::trans("fields.recurringamount");
        echo "</td>\n    <td class=\"fieldarea\">\n        <style>\n            .bootstrap-switch.bootstrap-switch-id-inputAutorecalc .bootstrap-switch-handle-off,\n            .bootstrap-switch.bootstrap-switch-id-inputAutorecalc .bootstrap-switch-handle-on,\n            .bootstrap-switch.bootstrap-switch-id-inputAutorecalc.bootstrap-switch-off .bootstrap-switch-label,\n            .bootstrap-switch.bootstrap-switch-id-inputAutorecalc.bootstrap-switch-on .bootstrap-switch-label,\n            .bootstrap-switch.bootstrap-switch-id-inputAutorecalc.bootstrap-switch-inverse.bootstrap-switch-on .bootstrap-switch-label,\n            .bootstrap-switch.bootstrap-switch-id-inputAutorecalc.bootstrap-switch-inverse.bootstrap-switch-off .bootstrap-switch-label {\n                padding: 1px 3px;\n                font-size: 10px;\n                line-height: 1.0;\n            }\n            .font-mouse {\n                font-size: 10px;\n                line-height: 1.0;\n            }\n            .line-through {\n                text-decoration-line: line-through;\n            }\n            .service-field-inline {\n                float: left;\n                max-width: 110px;\n                padding-right: 5px;\n            }\n            .service-field-inline input[type=checkbox] {\n                margin: 0;\n            }\n        </style>\n        <div style=\"width: 100%\">\n        <div class=\"service-field-inline\">\n            <input type=\"text\" id=\"inputRecurringAmount\" name=\"recurringamount\" class=\"form-control input-100\" value=\"";
        echo $recurringamount;
        echo "\">\n        </div>\n        <div class=\"service-field-inline\">\n            <div class=\"font-mouse\">";
        echo AdminLang::trans("services.autorecalc");
        echo "</div>\n            <div>\n                <input type=\"checkbox\"\n                       class=\"slide-toggle auto-recalc-checkbox\"\n                       id=\"inputAutorecalc\"\n                       name=\"autorecalc\"\n                       data-size=\"mini\"\n                       data-on-text=\"";
        echo AdminLang::trans("global.yes");
        echo "\"\n                       data-on-color=\"info\"\n                       data-off-text=\"";
        echo AdminLang::trans("global.no");
        echo "\"\n                />\n            </div>\n        </div>\n\n        ";
        $aInt->addHeadJqueryCode("jQuery('#inputAutorecalc').on('switchChange.bootstrapSwitch',\n    function (event, state) {\n        var element = jQuery('#inputRecurringAmount');\n        element.prop('readonly', state).toggleClass('readonly').toggleClass('line-through');\n    }\n);");
        echo "        <div class=\"form-inline\">\n            ";
        echo $renewalCostInfo;
        echo "        </div>\n    </td>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "status");
        echo "</td>\n    <td class=\"fieldarea\">\n        <select name=\"status\" class=\"form-control select-inline\">\n            ";
        echo (new WHMCS\Domain\Status())->translatedDropdownOptions([$domainstatus]);
        echo "        </select>\n    </td>\n</tr>\n<tr>\n    ";
        $promoJs = "var otherPromos = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("promos.allpromos")) . "';";
        $aInt->addHeadJsCode($promoJs);
        $domainModel = WHMCS\Domain\Domain::find($domainid);
        $promoData = preparePromotionDataForSelection(WHMCS\Product\Promotion::getApplicableToObject($domainModel), $promoid, false);
        $frm = new WHMCS\Form();
        $fieldData = "<div id=\"nonApplicablePromoWarning\" class=\"alert alert-info text-center\" style=\"display: none;\">" . AdminLang::trans("promos.nonApplicableSelected") . "</div>" . "<div style=\"max-width:300px\" class=\"form-field-width-container\">" . $frm->dropdownWithOptGroups("promoid", $promoData, $promoid, "", "", true, 1, "promoid", "form-control selectize-promo") . "</div>";
        $noEffect = " <a href=\"#\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . AdminLang::trans("services.noaffect") . "\">" . "<i class=\"fa fa-info-circle\"></i></a>";
        echo "    <td class=\"fieldlabel\">";
        echo AdminLang::trans("fields.promocode") . $noEffect;
        echo "</td>\n    <td class=\"fieldarea\">";
        echo $fieldData;
        echo "</td>\n    <td class=\"fieldlabel\" colspan=\"2\"></td>\n<tr>\n";
        $currency = getCurrency($userid);
        $subscriptionData = "";
        if ($subscriptionid) {
            $gateway = new WHMCS\Module\Gateway();
            $gateway->load($paymentmethod);
            $manageSubButtons = [];
            if ($gateway->functionExists("get_subscription_info")) {
                $route = routePathWithQuery("admin-domains-subscription-info", [$id], ["token" => generate_token("plain")]);
                $title = AdminLang::trans("subscription.info");
                $manageSubButtons[] = "<a href=\"" . $route . "\" class=\"btn btn-default open-modal\" " . "data-modal-title=\"" . $title . "\">" . AdminLang::trans("global.getSubscriptionInfo") . "</a>";
            }
            if ($gateway->functionExists("cancelSubscription")) {
                $manageSubButtons[] = "<button type=\"button\" class=\"btn btn-default\" onclick=\"jQuery('#modalCancelSubscription').modal('show');\" id=\"btnCancel_Subscription\" style=\"margin-left:-3px;\">" . AdminLang::trans("services.cancelSubscription") . "</button>";
            }
            if (0 < count($manageSubButtons)) {
                $buttons = implode("", $manageSubButtons);
                $subscriptionData = "<span class=\"input-group-btn\" style=\"display:block;\">" . $buttons . "</span>";
            }
        }
        $subscriptionClass = "input-300";
        if ($subscriptionData) {
            $subscriptionClass = "input-group";
        }
        echo "    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "subscriptionid");
        echo "</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <div class=\"";
        echo $subscriptionClass;
        echo "\" id=\"subscription\">\n            <input type=\"text\" class=\"form-control\" name=\"subscriptionid\" value=\"";
        echo $subscriptionid;
        echo "\">\n            ";
        echo $subscriptionData;
        echo "        </div>\n        <div id=\"subscriptionworking\" style=\"display:none;text-align:center;\">\n            <img src=\"images/loader.gif\" />\n            &nbsp;(";
        echo AdminLang::trans("global.working");
        echo ")\n        </div>\n    </td>\n</tr>\n\n";
        if ($domainregistraractions) {
            if ($domains->hasFunction("GetNameservers") || $domains->hasFunction("GetDomainInformation")) {
                echo "<tr>\n    <td class=\"fieldlabel\">";
                echo $aInt->lang("domains", "nameserver");
                echo " 1</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <input type=\"text\" name=\"ns1\" value=\"";
                echo $nsvalues["ns1"];
                echo "\" class=\"form-control input-300\" />\n        <input type=\"hidden\" name=\"oldns1\" value=\"";
                echo $nsvalues["ns1"];
                echo "\" />\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                echo $aInt->lang("domains", "nameserver");
                echo " 2</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <input type=\"text\" name=\"ns2\" value=\"";
                echo $nsvalues["ns2"];
                echo "\" class=\"form-control input-300\" />\n        <input type=\"hidden\" name=\"oldns2\" value=\"";
                echo $nsvalues["ns2"];
                echo "\" />\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                echo $aInt->lang("domains", "nameserver");
                echo " 3</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <input type=\"text\" name=\"ns3\" value=\"";
                echo $nsvalues["ns3"];
                echo "\" class=\"form-control input-300\" />\n        <input type=\"hidden\" name=\"oldns3\" value=\"";
                echo $nsvalues["ns3"];
                echo "\" />\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                echo $aInt->lang("domains", "nameserver");
                echo " 4</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <input type=\"text\" name=\"ns4\" value=\"";
                echo $nsvalues["ns4"];
                echo "\" class=\"form-control input-300\" />\n        <input type=\"hidden\" name=\"oldns4\" value=\"";
                echo $nsvalues["ns4"];
                echo "\" />\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                echo $aInt->lang("domains", "nameserver");
                echo " 5</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <input type=\"text\" name=\"ns5\" value=\"";
                echo $nsvalues["ns5"];
                echo "\" class=\"form-control input-300\" />\n        <input type=\"hidden\" name=\"oldns5\" value=\"";
                echo $nsvalues["ns5"];
                echo "\" />\n    </td>\n</tr>\n    <tr>\n        <td class=\"fieldlabel\">&nbsp;</td>\n        <td class=\"fieldarea\" colspan=\"3\">\n            <label for=\"defaultns\" class=\"checkbox-inline\">\n                <input type=\"checkbox\"\n                       name=\"defaultns\"\n                       id=\"defaultns\"\n                       class=\"slide-toggle-mini\"\n                       data-on-text=\"";
                echo mb_convert_case(AdminLang::trans("global.yes"), MB_CASE_UPPER);
                echo "\"\n                       data-off-text=\"";
                echo mb_convert_case(AdminLang::trans("global.no"), MB_CASE_UPPER);
                echo "\"\n                />\n                ";
                echo AdminLang::trans("domains.resetdefaultns");
                echo "            </label>\n        </td>\n    </tr>\n";
            }
            if ($lockstatus) {
                echo "<tr><td class=\"fieldlabel\">";
                echo $aInt->lang("domains", "reglock");
                echo "</td><td class=\"fieldarea\" colspan=\"3\"><input type=\"checkbox\" name=\"lockstatus\"";
                if ($lockstatus == "locked") {
                    echo " checked";
                }
                echo "> ";
                echo $aInt->lang("global", "ticktoenable");
                echo " <input type=\"hidden\" name=\"oldlockstatus\" value=\"";
                echo $lockstatus;
                echo "\"></td></tr>\n";
            }
            echo "<tr>\n    <td class=\"fieldlabel\">";
            echo $aInt->lang("domains", "registrarcommands");
            echo "</td><td colspan=\"3\">\n";
            if ($domains->hasFunction("RegisterDomain")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "actionreg");
                echo "\" class=\"button btn btn-default\" onClick=\"window.location='clientsdomainreg.php?domainid=";
                echo $id;
                echo "'\"> ";
            }
            if ($domains->hasFunction("TransferDomain")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "transfer");
                echo "\" class=\"button btn btn-default\" onClick=\"window.location='clientsdomainreg.php?domainid=";
                echo $id;
                echo "&ac=transfer'\"> ";
            }
            if ($domains->hasFunction("RenewDomain")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "renew");
                echo "\" class=\"button btn btn-default\" data-toggle=\"modal\" data-target=\"#modalRenew\"> ";
            }
            if ($domains->hasFunction("GetContactDetails")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "modifydetails");
                echo "\" class=\"button btn btn-default\" onClick=\"window.location='clientsdomaincontacts.php?domainid=";
                echo $id;
                echo "'\"> ";
            }
            if ($domains->hasFunction("GetEPPCode")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "getepp");
                echo "\" class=\"button btn btn-default\" data-toggle=\"modal\" data-target=\"#modalGetEPP\"> ";
            }
            if ($domains->hasFunction("RequestDelete")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "requestdelete");
                echo "\" class=\"button btn btn-default\" data-toggle=\"modal\" data-target=\"#modalRequestDelete\"> ";
            }
            if ($domains->hasFunction("ReleaseDomain")) {
                echo "<input type=\"button\" value=\"";
                echo $aInt->lang("domains", "releasedomain");
                echo "\" class=\"button btn btn-default\" data-toggle=\"modal\" data-target=\"#modalReleaseDomain\"> ";
            }
            if ($domains->hasFunction("IDProtectToggle")) {
                $buttonValue = AdminLang::trans("domains.enableIdProtection");
                if ($idprotection) {
                    $buttonValue = AdminLang::trans("domains.disableIdProtection");
                }
                echo "    <button type=\"button\" class=\"button btn btn-default\" data-toggle=\"modal\" data-target=\"#modalIdProtectToggle\">\n        ";
                echo $buttonValue;
                echo "    </button>\n";
            }
            if ($showResendIRTPVerificationEmail && $domains->hasFunction("ResendIRTPVerificationEmail")) {
                echo "    <input type=\"button\" value=\"";
                echo AdminLang::trans("domains.resendNotification");
                echo "\" class=\"button btn btn-default\" data-toggle=\"modal\" data-target=\"#modalResendIRTPVerificationEmail\">\n";
            }
            if ($domains->moduleCall("AdminCustomButtonArray")) {
                $adminbuttonarray = $domains->getModuleReturn();
                foreach ($adminbuttonarray as $key => $value) {
                    echo " <input type=\"button\" value=\"";
                    echo $key;
                    echo "\" class=\"button btn btn-default\" onClick=\"window.location='";
                    echo $whmcs->getPhpSelf();
                    echo "?userid=";
                    echo $userid;
                    echo "&id=";
                    echo $id;
                    echo "&regaction=custom&ac=";
                    echo $value . $token;
                    echo "'\">";
                }
            }
            echo "    </td>\n</tr>\n";
        }
        echo "<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("domains", "managementtools");
        echo "</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <label class=\"checkbox-inline toggle\">\n            <input type=\"checkbox\" class=\"slide-toggle-mini\" name=\"dnsmanagement\"";
        echo $dnsmanagement ? " checked=\"checked\"" : "";
        echo ">\n            ";
        echo AdminLang::trans("domains.dnsmanagement");
        echo "        </label>\n        <label class=\"checkbox-inline toggle\">\n            <input type=\"checkbox\" class=\"slide-toggle-mini\" name=\"emailforwarding\"";
        echo $emailforwarding ? " checked=\"checked\"" : "";
        echo ">\n            ";
        echo AdminLang::trans("domains.emailforwarding");
        echo "        </label>\n        ";
        $onclick = "";
        if ($domains->hasFunction("IDProtectToggle")) {
            $onclick = " onclick=\"\$('#modalIdProtectToggle').modal('show');\"";
        }
        echo "        <label class=\"checkbox-inline toggle\">\n            <input type=\"checkbox\" class=\"slide-toggle-mini\" name=\"idprotection\"";
        echo ($idprotection ? " checked=\"checked\"" : "") . $onclick;
        echo ">\n            ";
        echo AdminLang::trans("domains.idprotection");
        echo "        </label>\n        <label class=\"checkbox-inline toggle\">\n            <input id=\"donotrenew\" type=\"checkbox\"\n                   class=\"slide-toggle-mini\"\n                   name=\"donotrenew\"\n                   ";
        echo $donotrenew ? " checked=\"checked\"" : "";
        echo "                   data-on-text=\"";
        echo mb_convert_case(AdminLang::trans("global.yes"), MB_CASE_UPPER);
        echo "\"\n                   data-off-text=\"";
        echo mb_convert_case(AdminLang::trans("global.no"), MB_CASE_UPPER);
        echo "\"\n            >\n            ";
        echo AdminLang::trans("domains.donotrenew");
        echo "        </label>\n    </td>\n</tr>\n";
        if ($registrar) {
            $module = new WHMCS\Module\Registrar();
            $module->load($registrar);
            if (!$module->functionExists("IDProtectToggle")) {
                echo "<tr>\n    <td class=\"fieldlabel\">&nbsp</td>\n    <td class=\"fieldarea\" colspan=\"3\">";
                echo $aInt->lang("domains", "idprotectioncontrolna");
                echo "</td>\n</tr>\n";
            }
        }
        $reminderEmails = ["", "first", "second", "third", "fourth", "fifth"];
        $reminderEmailOutput = "<tr>\n    <td class=\"fieldlabel\">\n        " . $aInt->lang("domains", "domainReminders") . "\n    </td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <div id=\"domainReminders\" style=\"overflow-y:auto; max-height:100px; font-size: 0.9em;\">\n            <table class=\"datatable\" width=\"100%\" style=\"margin-bottom:0;\">\n                <tr>\n                    <th>" . $aInt->lang("fields", "date") . "</th>\n                    <th>" . $aInt->lang("domains", "reminder") . "</th>\n                    <th>" . $aInt->lang("emails", "to") . "</th>\n                    <th>" . $aInt->lang("domains", "sent") . "</th>\n                </tr>";
        if ($domains->obtainEmailReminders()) {
            foreach ($domains->obtainEmailReminders() as $reminderMail) {
                $reminderType = AdminLang::trans("domains." . $reminderEmails[$reminderMail["type"]] . "Reminder");
                $reminderDate = fromMySQLDate($reminderMail["date"]);
                $recipients = $reminderMail["recipients"];
                $sent = sprintf(AdminLang::trans("domains.beforeExpiry"), $reminderMail["days_before_expiry"]);
                if ($reminderMail["days_before_expiry"] < 0) {
                    $sent = sprintf(AdminLang::trans("domains.afterExpiry"), $reminderMail["days_before_expiry"] * -1);
                }
                $reminderEmailOutput .= "<tr align=\"center\">\n    <td>" . $reminderDate . "</td>\n    <td>" . $reminderType . "</td>\n    <td width=\"50%\">" . $recipients . "</td>\n    <td>" . $sent . "</td>\n</tr>";
            }
        } else {
            $noRecords = AdminLang::trans("global.norecordsfound");
            $reminderEmailOutput .= "<tr align=\"center\">\n    <td colspan=\"4\">" . $noRecords . "</td>\n</tr>";
        }
        $reportLink = "";
        if (checkPermission("View Reports", true) && $domains->obtainEmailReminders()) {
            $reportLink = sprintf("<input type=\"button\" onclick=\"%s\" value=\"%s\" class=\"btn btn-default top-margin-5\" />", "window.location='reports.php?report=domain_renewal_emails&client=" . $userid . "&domain=" . $domain . "'", AdminLang::trans("fields.export"));
        }
        $reminderEmailOutput .= "</table></div>" . $reportLink . "</td></tr>";
        echo $reminderEmailOutput;
        if (function_exists($registrar . "_AdminDomainsTabFields")) {
            $fieldsarray = call_user_func($registrar . "_AdminDomainsTabFields", $params);
            if (is_array($fieldsarray)) {
                foreach ($fieldsarray as $k => $v) {
                    echo "<tr><td class=\"fieldlabel\">" . $k . "</td><td class=\"fieldarea\" colspan=\"3\">" . $v . "</td></tr>";
                }
            }
        }
        $hookret = run_hook("AdminClientDomainsTabFields", ["id" => $id]);
        foreach ($hookret as $hookdat) {
            foreach ($hookdat as $k => $v) {
                echo "<td class=\"fieldlabel\">" . $k . "</td><td class=\"fieldarea\" colspan=\"3\">" . $v . "</td></tr>";
            }
        }
        $additflds = new WHMCS\Domains\AdditionalFields();
        $additflds->setDomain($domain)->setDomainType($ordertype)->getFieldValuesFromDatabase($id);
        foreach ($additflds->getFieldsForOutput() as $fieldLabel => $inputHTML) {
            echo "<tr><td class=\"fieldlabel\">" . $fieldLabel . "</td><td class=\"fieldarea\" colspan=\"3\">" . $inputHTML . "</td></tr>";
        }
        echo "<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "adminnotes");
        echo "</td>\n    <td class=\"fieldarea\" colspan=\"3\">\n        <textarea name=\"additionalnotes\" rows=4 class=\"form-control\">";
        echo $additionalnotes;
        echo "</textarea>\n    </td>\n</tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
        echo $aInt->lang("global", "savechanges");
        echo "\" class=\"btn btn-primary\" />\n    <input type=\"reset\" value=\"";
        echo $aInt->lang("global", "cancelchanges");
        echo "\" class=\"btn btn-default\" />\n</div>\n\n</form>\n";
        $frmsub = new WHMCS\Form("frmSendEmail");
        $emailModalContent = $frmsub->hidden("action", "send") . $frmsub->hidden("type", "domain") . $frmsub->hidden("id", $id);
        $emailArray = [];
        $emailArray[0] = AdminLang::trans("emails.newmessage");
        $mailTemplates = WHMCS\Mail\Template::master()->domain()->orderBy("name")->get();
        foreach ($mailTemplates as $template) {
            $emailArray[$template->id] = $template->custom ? ["#efefef", $template->name] : $template->name;
        }
        $emailModalContent .= AdminLang::trans("global.chooseMessage") . ":<br>" . $frmsub->dropdown("messageID", $emailArray, "", "", "", "", "1", "", "form-control");
        echo $frmsub->form("clientsemails.php?userid=" . $userid) . $aInt->modal("SendEmail", AdminLang::trans("global.sendmessage"), $emailModalContent, [["title" => AdminLang::trans("global.cancel")], ["type" => "submit", "title" => AdminLang::trans("global.sendmessage"), "class" => "btn-primary", "onclick" => ""]]) . $frmsub->close();
        echo "\n<form method=\"post\" action=\"whois.php\" target=\"_blank\" id=\"frmWhois\">\n<input type=\"hidden\" name=\"domain\" value=\"" . $domain . "\" />\n</form>\n";
        echo $modalHtml;
        $content = ob_get_contents();
        ob_end_clean();
        $cancelRoute = routePath("admin-domains-cancel-subscription", $id);
        $anError = addslashes(AdminLang::trans("global.erroroccurred"));
        $jscode = "function cancelSubscription() {\n    var subscription = \$(\"#subscription\"),\n        subscriptionWorking = \$(\"#subscriptionworking\");\n    \$(\"#modalCancelSubscription\").modal(\"hide\");\n\n    subscription.css(\"filter\", \"alpha(opacity=20)\");\n    subscription.css(\"-moz-opacity\", \"0.2\");\n    subscription.css(\"-khtml-opacity\", \"0.2\");\n    subscription.css(\"opacity\", \"0.2\");\n    var position = subscription.position();\n\n    subscriptionWorking.css(\"position\", \"absolute\");\n    subscriptionWorking.css(\"top\", position.top);\n    subscriptionWorking.css(\"left\", position.left);\n    subscriptionWorking.css(\"padding\", \"9px 50px 0\");\n    subscriptionWorking.fadeIn();\n\n    WHMCS.http.jqClient.jsonPost({\n        url: '" . $cancelRoute . "',\n        data: {\n            token: csrfToken\n        },\n        success: function(data) {\n            if (data.successMsg) {\n                jQuery.growl.notice({ title: data.successMsgTitle, message: data.successMsg });\n                subscription.find(\"input\").val(\"\");\n            }\n            if (data.errorMsg) {\n                jQuery.growl.warning({title: data.errorMsgTitle, message: data.errorMsg});\n            }\n        },\n        error: function(data) {\n            jQuery.growl.warning(\n                {\n                    title: '" . $anError . "',\n                    message: data\n                }\n            );\n        },\n        always: function() {\n            subscriptionWorking.fadeOut();\n            subscription.css(\"filter\", \"alpha(opacity=100)\");\n            subscription.css(\"-moz-opacity\", \"1\");\n            subscription.css(\"-khtml-opacity\", \"1\");\n            subscription.css(\"opacity\", \"1\");\n        }\n    });\n}";
        $aInt->content = $content;
        $aInt->jquerycode = "";
        $aInt->jscode = $jscode;
        $aInt->display();
}
