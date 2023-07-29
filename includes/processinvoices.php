<?php

use WHMCS\Application\Support\Facades\HookMgr;
use WHMCS\Application\Support\Facades\Lang;

function createInvoices($func_userid = "", $noemails = "", $nocredit = "", $specificitems = "", WHMCS\Scheduling\Task\TaskInterface $task = NULL)
{
    global $whmcs;
    global $CONFIG;
    global $_LANG;
    global $invoicecount;
    global $invoiceid;
    global $continuous_invoicing_active_only;
    $continvoicegen = WHMCS\Config\Setting::getValue("ContinuousInvoiceGeneration");
    $createInvoiceDaysBefore = (int) WHMCS\Config\Setting::getValue("CreateInvoiceDaysBefore");
    $invoicedate = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $createInvoiceDaysBefore, date("Y")));
    $invoicedatemonthly = WHMCS\Config\Setting::getValue("CreateInvoiceDaysBeforeMonthly") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateInvoiceDaysBeforeMonthly"], date("Y"))) : $invoicedate;
    $invoicedatequarterly = WHMCS\Config\Setting::getValue("CreateInvoiceDaysBeforeQuarterly") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateInvoiceDaysBeforeQuarterly"], date("Y"))) : $invoicedate;
    $invoicedatesemiannually = WHMCS\Config\Setting::getValue("CreateInvoiceDaysBeforeSemiAnnually") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateInvoiceDaysBeforeSemiAnnually"], date("Y"))) : $invoicedate;
    $invoicedateannually = WHMCS\Config\Setting::getValue("CreateInvoiceDaysBeforeAnnually") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateInvoiceDaysBeforeAnnually"], date("Y"))) : $invoicedate;
    $invoicedatebiennially = WHMCS\Config\Setting::getValue("CreateInvoiceDaysBeforeBiennially") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateInvoiceDaysBeforeBiennially"], date("Y"))) : $invoicedate;
    $invoicedatetriennially = WHMCS\Config\Setting::getValue("CreateInvoiceDaysBeforeTriennially") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateInvoiceDaysBeforeTriennially"], date("Y"))) : $invoicedate;
    $domaininvoicedate = 0 < WHMCS\Config\Setting::getValue("CreateDomainInvoiceDaysBefore") ? date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $CONFIG["CreateDomainInvoiceDaysBefore"], date("Y"))) : $invoicedate;
    $matchfield = $continvoicegen ? "nextinvoicedate" : "nextduedate";
    run_hook("PreInvoicingGenerateInvoiceItems", []);
    $statusfilter = "'Pending','Active'";
    if (!$continuous_invoicing_active_only) {
        $statusfilter .= ",'Suspended'";
    }
    $hostingquery = "domainstatus IN (" . $statusfilter . ") AND billingcycle!='Free' AND billingcycle!='Free Account' AND nextduedate!='00000000' AND nextinvoicedate!='00000000' AND ((billingcycle='Monthly' AND " . $matchfield . "<='" . $invoicedatemonthly . "') OR (billingcycle='Quarterly' AND " . $matchfield . "<='" . $invoicedatequarterly . "') OR (billingcycle='Semi-Annually' AND " . $matchfield . "<='" . $invoicedatesemiannually . "') OR (billingcycle='Annually' AND " . $matchfield . "<='" . $invoicedateannually . "') OR (billingcycle='Biennially' AND " . $matchfield . "<='" . $invoicedatebiennially . "') OR (billingcycle='Triennially' AND " . $matchfield . "<='" . $invoicedatetriennially . "') OR (billingcycle='One Time'))";
    $domainquery = "(donotrenew='' OR `status`='Pending') AND `status` IN (" . $statusfilter . ") AND " . $matchfield . "<='" . $domaininvoicedate . "'";
    $hostingaddonsquery = "tblhostingaddons.billingcycle!='Free' AND tblhostingaddons.billingcycle!='Free Account' AND tblhostingaddons.status IN (" . $statusfilter . ") AND tblhostingaddons.nextduedate!='00000000' AND tblhostingaddons.nextinvoicedate!='00000000' AND ((tblhostingaddons.billingcycle='Monthly' AND tblhostingaddons." . $matchfield . "<='" . $invoicedatemonthly . "') OR (tblhostingaddons.billingcycle='Quarterly' AND tblhostingaddons." . $matchfield . "<='" . $invoicedatequarterly . "') OR (tblhostingaddons.billingcycle='Semi-Annually' AND tblhostingaddons." . $matchfield . "<='" . $invoicedatesemiannually . "') OR (tblhostingaddons.billingcycle='Annually' AND tblhostingaddons." . $matchfield . "<='" . $invoicedateannually . "') OR (tblhostingaddons.billingcycle='Biennially' AND tblhostingaddons." . $matchfield . "<='" . $invoicedatebiennially . "') OR (tblhostingaddons.billingcycle='Triennially' AND tblhostingaddons." . $matchfield . "<='" . $invoicedatetriennially . "') OR (tblhostingaddons.billingcycle='One Time'))";
    $i = 0;
    $billableitemqry = "";
    if ($func_userid != "") {
        $hostingquery .= " AND userid=" . (int) $func_userid;
        $domainquery .= " AND userid=" . (int) $func_userid;
        $billableitemqry = " AND userid=" . (int) $func_userid;
    }
    if (is_array($specificitems)) {
        $hostingquery = $domainquery = $hostingaddonsquery = "";
        if (empty($specificitems["upgradeOrder"])) {
            if (!empty($specificitems["serviceUsage"])) {
                $hostingquery .= "(id IN (" . db_build_in_array($specificitems["serviceUsage"]) . ") AND billingcycle!='Free' AND billingcycle!='Free Account')";
            } else {
                if (!empty($specificitems["products"])) {
                    $spProducts = db_build_in_array($specificitems["products"]);
                    $hostingquery .= "(id IN (" . $spProducts . ") AND billingcycle!='Free'" . " AND billingcycle!='Free Account')";
                }
            }
            if (!empty($specificitems["addons"])) {
                $spAddons = db_build_in_array($specificitems["addons"]);
                $hostingaddonsquery .= "tblhostingaddons.id IN (" . $spAddons . ")" . " AND tblhostingaddons.billingcycle!='Free'" . " AND tblhostingaddons.billingcycle!='Free Account'";
            }
            if (!empty($specificitems["domains"])) {
                $domainquery .= "id IN (" . db_build_in_array($specificitems["domains"]) . ")";
            }
        }
    }
    $AddonsArray = $AddonSpecificIDs = [];
    $gateways = new WHMCS\Gateways();
    $cancellationreqids = WHMCS\Database\Capsule::table("tblcancelrequests")->distinct()->pluck("relid")->toArray();
    if ($hostingquery) {
        $result = select_query("tblhosting", implode(",", ["tblhosting.id", "tblhosting.userid", "tblhosting.nextduedate", "tblhosting.nextinvoicedate", "tblhosting.billingcycle", "tblhosting.regdate", "tblhosting.firstpaymentamount", "tblhosting.amount", "tblhosting.domain", "tblhosting.paymentmethod", "tblhosting.packageid", "tblhosting.promoid", "tblhosting.domainstatus", "tblhosting.qty"]), $hostingquery, "domain", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $id = $serviceid = $data["id"];
            if (!in_array($serviceid, $cancellationreqids) || !empty($specificitems["serviceUsage"]) && in_array($serviceid, $specificitems["serviceUsage"])) {
                $userid = $data["userid"];
                $nextduedate = $data[$matchfield];
                $billingcycle = $data["billingcycle"];
                $status = $data["domainstatus"];
                $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Hosting", "relid" => $serviceid, "duedate" => $nextduedate]);
                $contblock = false;
                if (!$num_rows && $continvoicegen && $status == "Pending") {
                    $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Hosting", "relid" => $serviceid]);
                    $contblock = true;
                }
                if ($num_rows == 0) {
                    $regdate = $data["regdate"];
                    $amount = $regdate == $nextduedate ? $data["firstpaymentamount"] : $data["amount"];
                    $domain = $data["domain"];
                    $paymentmethod = $data["paymentmethod"];
                    if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
                        $paymentmethod = ensurePaymentMethodIsSet($userid, $id, "tblhosting");
                    }
                    $pid = $data["packageid"];
                    $promoid = $data["promoid"];
                    $productdetails = getInvoiceProductDetails($id, $pid, $regdate, $nextduedate, $billingcycle, $domain, $userid);
                    $description = $productdetails["description"];
                    $tax = $productdetails["tax"];
                    $recurringcycles = $productdetails["recurringcycles"];
                    $recurringfinished = false;
                    if ($recurringcycles) {
                        $num_rows3 = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Hosting", "relid" => $id]);
                        if ($recurringcycles <= $num_rows3) {
                            WHMCS\Database\Capsule::table("tblhosting")->where("id", "=", $id)->update(["domainstatus" => "Completed", "completed_date" => WHMCS\Carbon::today()->toDateString()]);
                            run_hook("ServiceRecurringCompleted", ["serviceid" => $id, "recurringinvoices" => $num_rows3]);
                            $recurringfinished = true;
                        }
                    }
                    if (!$recurringfinished) {
                        $promovals = getInvoiceProductPromo($amount, $promoid, $userid, $id);
                        if (isset($promovals["description"])) {
                            $amount -= $promovals["amount"];
                        }
                        $isUsageInvoice = empty($specificitems["serviceUsage"]) ? false : true;
                        if (!$isUsageInvoice) {
                            insert_query("tblinvoiceitems", ["userid" => $userid, "type" => "Hosting", "relid" => $id, "description" => $description, "amount" => $amount, "taxed" => $tax, "duedate" => $nextduedate, "paymentmethod" => $paymentmethod]);
                        }
                        cancelUnpaidUpgrade((int) $id);
                        if (!$isUsageInvoice && isset($promovals["description"])) {
                            insert_query("tblinvoiceitems", ["userid" => $userid, "type" => "PromoHosting", "relid" => $id, "description" => $promovals["description"], "amount" => $promovals["amount"], "taxed" => $tax, "duedate" => $nextduedate, "paymentmethod" => $paymentmethod]);
                        }
                        if (WHMCS\UsageBilling\MetricUsageSettings::isInvoicingEnabled()) {
                            $serviceUsage = new WHMCS\UsageBilling\Invoice\ServiceUsage($id);
                            if ($isUsageInvoice) {
                                $mode = $serviceUsage::getAllUsageMode();
                            } else {
                                $mode = $serviceUsage::getRecurringInvoiceMode();
                            }
                            $serviceUsage->generateInvoiceItems($mode, $nextduedate, $tax);
                        }
                    }
                } else {
                    if (!$contblock && $continvoicegen && $billingcycle != "One Time") {
                        update_query("tblhosting", ["nextinvoicedate" => getInvoicePayUntilDate($nextduedate, $billingcycle, true)], ["id" => $id]);
                    }
                }
            }
            if ($hostingaddonsquery) {
                $result3 = WHMCS\Service\Addon::with(["service", "productAddon"])->ofService($id)->whereRaw($hostingaddonsquery)->orderBy("name");
                if (!is_array($specificitems) && $func_userid !== "") {
                    $result3->whereHas("client", function (Illuminate\Database\Eloquent\Builder $query) use($func_userid) {
                        $query->where("id", $func_userid);
                    });
                }
                foreach ($result3->get() as $data) {
                    $id = $data["id"];
                    $userid = $data->service->clientId;
                    $nextduedate = $data[$matchfield];
                    $status = $data["status"];
                    $quantity = $data["qty"];
                    $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Addon", "relid" => $id, "duedate" => $nextduedate]);
                    $contblock = false;
                    if (!$num_rows && $continvoicegen && $status == "Pending") {
                        $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Addon", "relid" => $id]);
                        $contblock = true;
                    }
                    if ($num_rows == 0) {
                        $hostingid = $serviceid = $data["hostingid"];
                        $addonid = $data["addonid"];
                        $regdate = $data->registrationDate;
                        $setupfee = $data["setupfee"];
                        $amount = $data["recurring"];
                        $paymentmethod = $data["paymentmethod"];
                        $billingcycle = $data["billingcycle"];
                        if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
                            $paymentmethod = ensurePaymentMethodIsSet($userid, $id, "tblhostingaddons");
                        }
                        $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Addon", "relid" => $id, "duedate" => $nextduedate]);
                        if ($num_rows == 0) {
                            if (!in_array($serviceid, $cancellationreqids)) {
                                if ($regdate->eq(parent::parse($nextduedate))) {
                                    $amount = $amount + $setupfee;
                                    if (!empty($data->productAddon) && $data->productAddon->prorate) {
                                        $amount = $data->firstPaymentAmount;
                                    }
                                }
                                $invoiceAddonDetails = getInvoiceAddonDetails($data);
                                insert_query("tblinvoiceitems", ["userid" => $userid, "type" => "Addon", "relid" => $id, "description" => $invoiceAddonDetails["description"], "amount" => $amount, "taxed" => $invoiceAddonDetails["tax"], "duedate" => $nextduedate, "paymentmethod" => $paymentmethod]);
                                $AddonSpecificIDs[] = $id;
                            }
                        } else {
                            if (!$contblock && $continvoicegen) {
                                update_query("tblhostingaddons", ["nextinvoicedate" => getInvoicePayUntilDate($nextduedate, $billingcycle, true)], ["id" => $id]);
                            }
                        }
                    }
                }
            }
        }
    }
    if ($hostingaddonsquery) {
        if (count($AddonSpecificIDs)) {
            $hostingaddonsquery .= " AND tblhostingaddons.id NOT IN (" . db_build_in_array($AddonSpecificIDs) . ")";
        }
        $result = WHMCS\Service\Addon::with(["service", "productAddon"])->whereRaw($hostingaddonsquery)->orderBy("name");
        if (!is_array($specificitems) && $func_userid !== "") {
            $result->whereHas("client", function (Illuminate\Database\Eloquent\Builder $query) use($func_userid) {
                $query->where("id", $func_userid);
            });
        }
        foreach ($result->get() as $data) {
            $id = $data["id"];
            $userid = $data->service->clientId;
            $nextduedate = $data[$matchfield];
            $status = $data["status"];
            $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Addon", "relid" => $id, "duedate" => $nextduedate]);
            $contblock = false;
            if (!$num_rows && $continvoicegen && $status == "Pending") {
                $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["userid" => $userid, "type" => "Addon", "relid" => $id]);
                $contblock = true;
            }
            if ($num_rows == 0) {
                $hostingid = $serviceid = $data["hostingid"];
                $addonid = $data["addonid"];
                $regdate = $data->registrationDate;
                $setupfee = $data["setupfee"];
                $amount = $data["recurring"];
                $paymentmethod = $data["paymentmethod"];
                if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
                    $paymentmethod = ensurePaymentMethodIsSet($userid, $id, "tblhostingaddons");
                }
                $billingcycle = $data["billingcycle"];
                if (!in_array($serviceid, $cancellationreqids)) {
                    if ($regdate->eq(WHMCS\Carbon::parse($nextduedate))) {
                        $amount = $amount + $setupfee;
                        if ($data->productAddon->prorate) {
                            $amount = $data->firstPaymentAmount;
                        }
                    } else {
                        if ($billingcycle == WHMCS\Billing\Cycles::DISPLAY_ONETIME) {
                            $amount = $amount + $setupfee;
                        }
                    }
                    $invoiceAddonDetails = getInvoiceAddonDetails($data, true);
                    insert_query("tblinvoiceitems", ["userid" => $userid, "type" => "Addon", "relid" => $id, "description" => $invoiceAddonDetails["description"], "amount" => $amount, "taxed" => $invoiceAddonDetails["tax"], "duedate" => $nextduedate, "paymentmethod" => $paymentmethod]);
                }
            } else {
                if (!$contblock && $continvoicegen) {
                    update_query("tblhostingaddons", ["nextinvoicedate" => getInvoicePayUntilDate($nextduedate, $billingcycle, true)], ["id" => $id]);
                }
            }
        }
    }
    if ($domainquery) {
        $result = select_query("tbldomains", "", $domainquery, "domain", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $userid = $data["userid"];
            $nextduedate = $data[$matchfield];
            $status = $data["status"];
            $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", "userid=" . (int) $userid . " AND type IN ('Domain','DomainRegister','DomainTransfer') AND relid=" . (int) $id . " AND duedate='" . db_escape_string($nextduedate) . "'");
            $contblock = false;
            if (!$num_rows && $continvoicegen && $status == "Pending") {
                $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", "userid=" . (int) $userid . " AND type IN ('Domain','DomainRegister','DomainTransfer') AND relid=" . (int) $id);
                $contblock = true;
            }
            $registrationperiod = $data["registrationperiod"];
            if ($num_rows == 0) {
                $type = $data["type"];
                $domain = $data["domain"];
                $regdate = $data["registrationdate"];
                $expirydate = $data["expirydate"];
                $paymentmethod = $data["paymentmethod"];
                if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
                    $paymentmethod = ensurePaymentMethodIsSet($userid, $id, "tbldomains");
                }
                $dnsmanagement = $data["dnsmanagement"];
                $emailforwarding = $data["emailforwarding"];
                $idprotection = $data["idprotection"];
                $promoid = $data["promoid"];
                getUsersLang($userid);
                if ($expirydate == "0000-00-00") {
                    $expirydate = $nextduedate;
                }
                if ($regdate == $nextduedate) {
                    $amount = $data["firstpaymentamount"];
                    if ($type == "Transfer") {
                        $domaindesc = $_LANG["domaintransfer"];
                    } else {
                        $domaindesc = $_LANG["domainregistration"];
                        $type = "Register";
                    }
                } else {
                    $amount = $data["recurringamount"];
                    $domaindesc = $_LANG["domainrenewal"];
                    $type = "";
                }
                $tax = $CONFIG["TaxEnabled"] && $CONFIG["TaxDomains"] ? "1" : "0";
                $domaindesc .= " - " . $domain . " - " . $registrationperiod . " " . $_LANG["orderyears"];
                if ($type != "Transfer") {
                    $domaindesc .= " (" . fromMySQLDate($expirydate) . " - " . fromMySQLDate(getInvoicePayUntilDate($expirydate, $registrationperiod)) . ")";
                }
                if ($dnsmanagement) {
                    $domaindesc .= "\n + " . $_LANG["domaindnsmanagement"];
                }
                if ($emailforwarding) {
                    $domaindesc .= "\n + " . $_LANG["domainemailforwarding"];
                }
                if ($idprotection) {
                    $domaindesc .= "\n + " . $_LANG["domainidprotection"];
                }
                $promo_description = $promo_amount = 0;
                if ($promoid) {
                    $data = get_query_vals("tblpromotions", "", ["id" => $promoid]);
                    $promo_id = $data["id"];
                    if ($promo_id) {
                        $promo_code = $data["code"];
                        $promo_type = $data["type"];
                        $promo_recurring = $data["recurring"];
                        $promo_value = (double) $data["value"];
                        if ($promo_recurring || !$promo_recurring && $regdate == $nextduedate) {
                            if ($promo_type == "Percentage") {
                                $promo_amount = !valueIsZero($promo_value - 0) ? round($amount / (1 - $promo_value / 100), 2) - $amount : $amount;
                                $promo_value .= "%";
                            } else {
                                if ($promo_type == "Fixed Amount") {
                                    $promo_amount = $promo_value;
                                    $currency = getCurrency($userid);
                                    $promo_value = formatCurrency($promo_value);
                                }
                            }
                            $amount += $promo_amount;
                            $promo_recurring = $promo_recurring ? $_LANG["recurring"] : $_LANG["orderpaymenttermonetime"];
                            $promo_description = $_LANG["orderpromotioncode"] . ": " . $promo_code . " - " . $promo_value . " " . $promo_recurring . " " . $_LANG["orderdiscount"];
                            $promo_amount *= -1;
                        }
                    }
                }
                insert_query("tblinvoiceitems", ["userid" => $userid, "type" => "Domain" . $type, "relid" => $id, "description" => $domaindesc, "amount" => $amount, "taxed" => $tax, "duedate" => $nextduedate, "paymentmethod" => $paymentmethod]);
                if ($promo_description) {
                    insert_query("tblinvoiceitems", ["userid" => $userid, "type" => "PromoDomain", "relid" => $id, "description" => $promo_description, "amount" => $promo_amount, "taxed" => $tax, "duedate" => $nextduedate, "paymentmethod" => $paymentmethod]);
                }
            } else {
                if (!$contblock && $continvoicegen) {
                    $year = substr($nextduedate, 0, 4);
                    $month = substr($nextduedate, 5, 2);
                    $day = substr($nextduedate, 8, 2);
                    $new_time = mktime(0, 0, 0, $month, $day, $year + $registrationperiod);
                    $nextinvoicedate = date("Y-m-d", $new_time);
                    update_query("tbldomains", ["nextinvoicedate" => $nextinvoicedate], ["id" => $id]);
                }
            }
            getUsersLang(0);
        }
    }
    if (!is_array($specificitems)) {
        $billableitemstax = $CONFIG["TaxEnabled"] && $CONFIG["TaxBillableItems"] ? "1" : "0";
        $result = select_query("tblbillableitems", "", "((invoiceaction='1' AND invoicecount='0') OR (invoiceaction='3' AND invoicecount='0' AND duedate<='" . $invoicedate . "') OR (invoiceaction='4' AND duedate<='" . $invoicedate . "' AND (recurfor='0' OR invoicecount<recurfor)))" . $billableitemqry);
        while ($data = mysql_fetch_array($result)) {
            $paymentmethod = getClientsPaymentMethod($data["userid"]);
            if ($data["invoiceaction"] != "4") {
                insert_query("tblinvoiceitems", ["userid" => $data["userid"], "type" => "Item", "relid" => $data["id"], "description" => $data["description"], "amount" => $data["amount"], "taxed" => $billableitemstax, "duedate" => $data["duedate"], "paymentmethod" => $paymentmethod]);
            }
            $updatearray = ["invoicecount" => "+1"];
            if ($data["invoiceaction"] == "4") {
                $num_rows = get_query_val("tblinvoiceitems", "COUNT(id)", ["type" => "Item", "relid" => $data["id"], "duedate" => $data["duedate"]]);
                if ($num_rows == 0) {
                    insert_query("tblinvoiceitems", ["userid" => $data["userid"], "type" => "Item", "relid" => $data["id"], "description" => $data["description"], "amount" => $data["amount"], "taxed" => $billableitemstax, "duedate" => $data["duedate"], "paymentmethod" => $paymentmethod]);
                }
                $adddays = $addmonths = $addyears = 0;
                if ($data["recurcycle"] == "Days") {
                    $adddays = $data["recur"];
                } else {
                    if ($data["recurcycle"] == "Weeks") {
                        $adddays = $data["recur"] * 7;
                    } else {
                        if ($data["recurcycle"] == "Months") {
                            $addmonths = $data["recur"];
                        } else {
                            if ($data["recurcycle"] == "Years") {
                                $addyears = $data["recur"];
                            }
                        }
                    }
                }
                $year = substr($data["duedate"], 0, 4);
                $month = substr($data["duedate"], 5, 2);
                $day = substr($data["duedate"], 8, 2);
                $updatearray["duedate"] = date("Y-m-d", mktime(0, 0, 0, $month + $addmonths, $day + $adddays, $year + $addyears));
            }
            update_query("tblbillableitems", $updatearray, ["id" => $data["id"]]);
        }
    }
    run_hook("AfterInvoicingGenerateInvoiceItems", []);
    $invoicecount = $invoiceid = 0;
    $capsuleQuery = WHMCS\Database\Capsule::table("tblinvoiceitems")->join("tblclients", "tblclients.id", "=", "tblinvoiceitems.userid")->leftJoin("tblclientgroups", "tblclientgroups.id", "=", "tblclients.groupid")->where("tblinvoiceitems.invoiceid", "=", "0")->orderBy("tblinvoiceitems.duedate")->orderBy("tblinvoiceitems.id");
    if ($func_userid) {
        $capsuleQuery = $capsuleQuery->where("tblinvoiceitems.userid", "=", (int) $func_userid);
    }
    if (!is_array($specificitems)) {
        $capsuleQuery = $capsuleQuery->where("tblclients.separateinvoices", "=", "0")->where(function ($query) {
            $query->whereIn("tblclientgroups.separateinvoices", ["0", ""])->orWhereNull("tblclientgroups.separateinvoices");
        });
    }
    $results = $capsuleQuery->distinct()->get(["tblinvoiceitems.userid", "tblinvoiceitems.duedate", "tblinvoiceitems.paymentmethod"]);
    foreach ($results as $result) {
        createInvoicesProcess((array) $result, $noemails, $nocredit, $task);
    }
    if (!is_array($specificitems)) {
        $capsuleQuery = WHMCS\Database\Capsule::table("tblinvoiceitems")->join("tblclients", "tblclients.id", "=", "tblinvoiceitems.userid")->leftJoin("tblclientgroups", "tblclientgroups.id", "=", "tblclients.groupid")->where("tblinvoiceitems.invoiceid", "=", "0")->where(function ($query) {
            $query->whereIn("tblclients.separateinvoices", ["on", "1"])->orWhere("tblclientgroups.separateinvoices", "=", "on");
        })->orderBy("tblinvoiceitems.duedate")->orderBy("tblinvoiceitems.id");
        if ($func_userid) {
            $capsuleQuery = $capsuleQuery->where("tblinvoiceitems.userid", "=", (int) $func_userid);
        }
        $results = $capsuleQuery->get(["tblinvoiceitems.id", "tblinvoiceitems.userid", "tblinvoiceitems.type", "tblinvoiceitems.relid", "tblinvoiceitems.duedate", "tblinvoiceitems.paymentmethod"]);
        foreach ($results as $result) {
            createInvoicesProcess((array) $result, $noemails, $nocredit, $task);
        }
    }
    if ($task) {
        $task->output("invoice.created")->write(count($task->getSuccesses()));
        $task->output("action.detail")->write(json_encode($task->getDetail()));
    }
    if ($func_userid) {
        return $invoiceid;
    }
}

function createInvoicesProcess($data, $noemails = "", $nocredit = "", WHMCS\Scheduling\Task\TaskInterface $task = NULL)
{
    global $whmcs;
    global $CONFIG;
    global $_LANG;
    global $invoicecount;
    global $invoiceid;
    $itemid = $data["id"] ?? NULL;
    $userid = $data["userid"] ?? NULL;
    $type = $data["type"] ?? NULL;
    $relid = $data["relid"] ?? NULL;
    $duedate = $data["duedate"] ?? NULL;
    $paymentmethod = $invpaymentmethod = $data["paymentmethod"];
    $gateways = new WHMCS\Gateways();
    if (!$invpaymentmethod || !$gateways->isActiveGateway($invpaymentmethod)) {
        $invpaymentmethod = ensurePaymentMethodIsSet($userid, $itemid, "tblinvoiceitems");
    }
    $where = ["userid" => $userid, "duedate" => $duedate, "paymentmethod" => $paymentmethod, "invoiceid" => "0"];
    if (!empty($itemid)) {
        $where["id"] = $itemid;
    }
    if (is_null(get_query_val("tblinvoiceitems", "id", $where))) {
        return false;
    }
    unset($where);
    $invoice = WHMCS\Billing\Invoice::newInvoice($userid, $invpaymentmethod);
    $invoice->duedate = $duedate;
    $invoice->setStatusUnpaid()->save();
    $invoiceid = $invoice->id;
    if ($paymentmethod != $invpaymentmethod) {
        logActivity(sprintf("Invalid payment method updated on invoice generation from '%s' to '%s' for Invoice ID: %d", $paymentmethod, $invpaymentmethod, $invoiceid), $userid);
    }
    if ($itemid) {
        update_query("tblinvoiceitems", ["invoiceid" => $invoiceid], ["invoiceid" => "0", "userid" => $userid, "type" => "Promo" . $type, "relid" => $relid]);
        $where = ["id" => $itemid];
    } else {
        $where = ["invoiceid" => "", "duedate" => $duedate, "userid" => $userid, "paymentmethod" => $paymentmethod];
    }
    update_query("tblinvoiceitems", ["invoiceid" => $invoiceid], $where);
    logActivity("Created Invoice - Invoice ID: " . $invoiceid, $userid);
    $billableitemstax = $CONFIG["TaxEnabled"] && $CONFIG["TaxBillableItems"] ? "1" : "0";
    $result2 = select_query("tblbillableitems", "", ["userid" => $userid, "invoiceaction" => "2", "invoicecount" => "0"]);
    while ($data = mysql_fetch_array($result2)) {
        insert_query("tblinvoiceitems", ["invoiceid" => $invoiceid, "userid" => $userid, "type" => "Item", "relid" => $data["id"], "description" => $data["description"], "amount" => $data["amount"], "taxed" => $billableitemstax]);
        update_query("tblbillableitems", ["invoicecount" => "+1"], ["id" => $data["id"]]);
    }
    updateInvoiceTotal($invoiceid);
    $invoiceLineItems = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", $invoiceid)->get()->all();
    $isaddfundsinvoice = 0 < count(array_filter($invoiceLineItems, function ($lineItem) {
        return (bool) in_array($lineItem->type, ["AddFunds", "Invoice"]);
    }));
    $groupid = get_query_val("tblclients", "groupid", ["id" => $userid]);
    if ($groupid && !$isaddfundsinvoice) {
        $discountPercent = get_query_val("tblclientgroups", "discountpercent", ["id" => $groupid]);
        if (0 < $discountPercent) {
            foreach ($invoiceLineItems as $lineItem) {
                $discountAmount = $lineItem->amount * $discountPercent / 100 * -1;
                insert_query("tblinvoiceitems", ["invoiceid" => $invoiceid, "userid" => $userid, "type" => "GroupDiscount", "description" => $_LANG["clientgroupdiscount"] . " - " . $lineItem->description, "amount" => $discountAmount, "taxed" => $lineItem->taxed]);
            }
            updateInvoiceTotal($invoiceid);
        }
    }
    if (WHMCS\Config\Setting::getValue("ContinuousInvoiceGeneration")) {
        $result2 = select_query("tblinvoiceitems", "", ["invoiceid" => $invoiceid]);
        $today = WHMCS\Carbon::today();
        while ($data = mysql_fetch_array($result2)) {
            $type = $data["type"];
            $relid = $data["relid"];
            $itemAmount = $data["amount"];
            $nextinvoicedate = $data["duedate"];
            $year = substr($nextinvoicedate, 0, 4);
            $month = substr($nextinvoicedate, 5, 2);
            $day = substr($nextinvoicedate, 8, 2);
            $billingcycle = NULL;
            $nextinvoicedate = NULL;
            $tableForItem = NULL;
            if ($type == "Hosting") {
                $tableForItem = "tblhosting";
                $data = get_query_vals("tblhosting", "billingcycle,packageid,regdate,nextduedate", ["id" => $relid]);
                $billingcycle = $data["billingcycle"];
                $packageid = $data["packageid"];
                $regdate = $data["regdate"];
                $nextduedate = $data["nextduedate"];
                $data = get_query_vals("tblproducts", "proratabilling,proratadate,proratachargenextmonth", ["id" => $packageid]);
                $proratabilling = $data["proratabilling"];
                $proratadate = $data["proratadate"];
                $proratachargenextmonth = $data["proratachargenextmonth"];
                $proratamonths = getBillingCycleMonths($billingcycle);
                if ($regdate == $nextduedate && $proratabilling) {
                    $prorateValues = getProrataValues($billingcycle, $itemAmount, $proratadate, $proratachargenextmonth, $today->day, $today->month, $today->year, $userid);
                    $nextinvoicedate = $prorateValues["date"];
                } else {
                    $nextinvoicedate = date("Y-m-d", mktime(0, 0, 0, $month + $proratamonths, $day, $year));
                }
            } else {
                if ($type == "Domain" || $type == "DomainRegister" || $type == "DomainTransfer") {
                    $tableForItem = "tbldomains";
                    $data = get_query_vals("tbldomains", "registrationperiod,nextduedate", ["id" => $relid]);
                    $registrationperiod = $data["registrationperiod"];
                    $nextduedate = explode("-", $data["nextduedate"]);
                    $billingcycle = "";
                    $nextinvoicedate = date("Y-m-d", mktime(0, 0, 0, $nextduedate[1], $nextduedate[2], $nextduedate[0] + $registrationperiod));
                } else {
                    if ($type == "Addon") {
                        $tableForItem = "tblhostingaddons";
                        $billingcycle = get_query_val("tblhostingaddons", "billingcycle", ["id" => $relid]);
                        $proratamonths = getBillingCycleMonths($billingcycle);
                        $nextinvoicedate = date("Y-m-d", mktime(0, 0, 0, $month + $proratamonths, $day, $year));
                    }
                }
            }
            if ($billingcycle == "One Time") {
                $nextinvoicedate = "0000-00-00";
            }
            if (!empty($tableForItem)) {
                WHMCS\Database\Capsule::table($tableForItem)->where("id", $relid)->update(["nextinvoicedate" => $nextinvoicedate]);
            }
        }
    }
    $invoice = WHMCS\Billing\Invoice::with("client")->find($invoiceid);
    $invoice->save();
    if (WHMCS\UsageBilling\MetricUsageSettings::isInvoicingEnabled()) {
        WHMCS\UsageBilling\Invoice\ServiceUsage::markUsageAsInvoiced($invoiceid, $invoiceLineItems);
    }
    $invoice->runCreationHooks("autogen");
    $credit = $invoice->client->credit;
    $total = $invoice->total;
    $doprocesspaid = false;
    $inShoppingCart = defined("SHOPPING_CART");
    if (!$nocredit && $credit != "0.00" && ($inShoppingCart && App::getFromRequest("applycredit") || !$inShoppingCart && !WHMCS\Config\Setting::getValue("NoAutoApplyCredit"))) {
        if ($total <= $credit) {
            $creditleft = $credit - $total;
            $credit = $total;
            $doprocesspaid = true;
        } else {
            $creditleft = 0;
        }
        if (!$inShoppingCart) {
            logActivity("Credit Automatically Applied at Invoice Creation - Invoice ID: " . $invoiceid . " - Amount: " . $credit, $userid);
        } else {
            logActivity("Credit Applied at Client Request on Checkout - Invoice ID: " . $invoiceid . " - Amount: " . $credit, $userid);
        }
        insert_query("tblcredit", ["clientid" => $userid, "date" => "now()", "description" => "Credit Applied to Invoice #" . $invoiceid, "amount" => $credit * -1]);
        $invoice->client->credit = $creditleft;
        $invoice->client->save();
        $invoice->credit = $credit;
        $invoice->save();
        $invoice->updateInvoiceTotal();
    }
    $invoiceArr = ["source" => "autogen", "user" => WHMCS\Session::get("adminid") ?: "system", "invoiceid" => $invoiceid, "status" => "Unpaid"];
    $paymenttype = WHMCS\Module\GatewaySetting::getTypeFor($invpaymentmethod);
    if ($noemails != "true") {
        run_hook("InvoiceCreationPreEmail", $invoiceArr);
        $emailName = "Invoice Created";
        if ($paymenttype == WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD) {
            $emailName = "Credit Card Invoice Created";
        }
        sendMessage($emailName, $invoiceid);
    }
    HookMgr::run("InvoiceCreated", $invoiceArr);
    if (valueIsZero($invoice->total)) {
        $doprocesspaid = true;
    }
    WHMCS\Session::set("InOrderButNeedProcessPaidInvoiceAction", false);
    if ($doprocesspaid) {
        if (defined("INORDERFORM")) {
            WHMCS\Session::set("InOrderButNeedProcessPaidInvoiceAction", true);
        } else {
            processPaidInvoice($invoiceid);
        }
    }
    $invoicecount++;
    if ($task) {
        $task->addSuccess(["invoice", $invoiceid, ""]);
    }
    WHMCS\Invoices::adjustIncrementForNextInvoice($invoiceid);
}

function getInvoiceAddonDetails($serviceAddon, $sslCompetitiveUpgrade = false)
{
    $productAddon = $serviceAddon->productAddon;
    $regDate = $serviceAddon->registrationDate;
    if (WHMCS\Config\Setting::getValue("ContinuousInvoiceGeneration")) {
        $dueDate = $serviceAddon->nextInvoiceDate;
    } else {
        $dueDate = $serviceAddon->nextDueDate;
    }
    $nextDueDate = WHMCS\Carbon::safeCreateFromMySqlDate($dueDate) ?: WHMCS\Carbon::now();
    $billingCycle = $serviceAddon->billingCycle;
    $tax = $serviceAddon->applyTax;
    $name = $serviceAddon->name ?: $productAddon->name;
    $domain = $serviceAddon->domain ?: $serviceAddon->service->domain;
    $client = $serviceAddon->client;
    $clientId = $client->id ?? NULL;
    $tax = $tax && WHMCS\Config\Setting::getValue("TaxEnabled") ? 1 : 0;
    $isProrated = false;
    if ($productAddon && $productAddon->prorate) {
        $isProrated = true;
    }
    $payDates = "";
    if ($regDate || $nextDueDate) {
        if ($regDate->isSameDay($nextDueDate) && $isProrated && $serviceAddon->proratadate != "0000-00-00") {
            $addonChargeNextMonthDay = $serviceAddon->service->product->proRataBilling ? $serviceAddon->service->product->proRataChargeNextMonthAfterDay : 32;
            $serviceNextDueDate = WHMCS\Carbon::safeCreateFromMySqlDate($serviceAddon->service->nextDueDate);
            $prorataUntilDate = $serviceAddon->service->billingCycle == $billingCycle ? $serviceNextDueDate : NULL;
            $prorataValues = getProrataValues($billingCycle, 0, $serviceAddon->prorataDate->day, $addonChargeNextMonthDay, $regDate->day, $regDate->month, $regDate->year, $clientId, $prorataUntilDate);
            $invoicePayUntilDate = $prorataValues["invoicedate"];
        } else {
            $invoicePayUntilDate = getInvoicePayUntilDate($nextDueDate->toDateString(), $billingCycle);
        }
        if ($billingCycle != "One Time") {
            $payDates = "(" . fromMySQLDate($nextDueDate) . " - " . fromMySQLDate($invoicePayUntilDate) . ")";
        }
    }
    $domainDescription = $quantityDescription = $sslCompetitiveUpgradeDesc = "";
    if (1 < $serviceAddon->qty) {
        $quantityDescription = $serviceAddon->qty . " x ";
    }
    if ($domain) {
        $domainDescription = "(" . $domain . ") ";
    }
    $name = !empty($name) ? $name . " " : "";
    if ($sslCompetitiveUpgrade) {
        $sslCompetitiveUpgradeAddons = WHMCS\Session::get("SslCompetitiveUpgradeAddons");
        if (is_array($sslCompetitiveUpgradeAddons) && in_array($serviceAddon->id, $sslCompetitiveUpgradeAddons)) {
            $sslCompetitiveUpgradeDesc = "<br><small>" . Lang::trans("store.ssl.competitiveUpgradeQualified") . "</small>";
            array_flip($sslCompetitiveUpgradeAddons);
            unset($sslCompetitiveUpgradeAddons[$serviceAddon->id]);
            array_flip($sslCompetitiveUpgradeAddons);
            WHMCS\Session::set("SslCompetitiveUpgradeAddons", $sslCompetitiveUpgradeAddons);
        }
    }
    $itemDescription = $quantityDescription . Lang::trans("orderaddon") . " " . $domainDescription . "- " . $name . $payDates . $sslCompetitiveUpgradeDesc;
    return ["description" => $itemDescription, "tax" => $tax];
}

function getInvoiceProductDetails($id, $pid, $regdate, $nextduedate, $billingcycle, $domain, $userid)
{
    global $CONFIG;
    global $_LANG;
    global $currency;
    $product = WHMCS\Product\Product::find($pid);
    $type = $product->type;
    $package = $product->name;
    $tax = $product->applyTax;
    $proratabilling = $product->proRataBilling;
    $proratadate = $product->proRataChargeDayOfCurrentMonth;
    $proratachargenextmonth = $product->proRataChargeNextMonthAfterDay;
    $recurringcycles = $product->recurringCycleLimit;
    $allowQuantities = $product->allowMultipleQuantities;
    $service = WHMCS\Service\Service::with("client", "client.currencyrel")->find($id);
    $userid = $service->clientId;
    $clientLanguage = $service->client->language ?: NULL;
    $currency = $service->client->currencyrel;
    if ($tax && $CONFIG["TaxEnabled"]) {
        $tax = "1";
    } else {
        $tax = "0";
    }
    $paydates = "";
    if ($regdate || $nextduedate) {
        if ($regdate == $nextduedate && $proratabilling) {
            $orderyear = substr($regdate, 0, 4);
            $ordermonth = substr($regdate, 5, 2);
            $orderday = substr($regdate, 8, 2);
            $proratavalues = getProrataValues($billingcycle, 0, $proratadate, $proratachargenextmonth, $orderday, $ordermonth, $orderyear, $userid);
            $invoicepayuntildate = $proratavalues["invoicedate"];
        } else {
            $invoicepayuntildate = getInvoicePayUntilDate($nextduedate, $billingcycle);
        }
        if ($billingcycle != "One Time") {
            $paydates = " (" . fromMySQLDate($nextduedate) . " - " . fromMySQLDate($invoicepayuntildate) . ")";
        }
    }
    $description = $package;
    if ($allowQuantities === 2 && 1 < $service->qty) {
        $description = $service->qty . " x " . $description;
    }
    if ($domain) {
        $description .= " - " . $domain;
    }
    $description .= $paydates;
    $configbillingcycle = $billingcycle;
    if ($configbillingcycle == "One Time" || $configbillingcycle == "Free Account") {
        $configbillingcycle = "monthly";
    }
    $configbillingcycle = strtolower(str_replace("-", "", $configbillingcycle));
    $query = "SELECT tblproductconfigoptions.id, tblproductconfigoptions.optionname AS confoption, tblproductconfigoptions.optiontype AS conftype, tblproductconfigoptionssub.optionname, tblhostingconfigoptions.qty,tblhostingconfigoptions.optionid FROM tblhostingconfigoptions INNER JOIN tblproductconfigoptions ON tblproductconfigoptions.id = tblhostingconfigoptions.configid INNER JOIN tblproductconfigoptionssub ON tblproductconfigoptionssub.id = tblhostingconfigoptions.optionid INNER JOIN tblhosting ON tblhosting.id=tblhostingconfigoptions.relid INNER JOIN tblproductconfiglinks ON tblproductconfiglinks.gid=tblproductconfigoptions.gid WHERE tblhostingconfigoptions.relid=" . (int) $id . " AND tblproductconfigoptions.hidden='0' AND tblproductconfigoptionssub.hidden='0' AND tblproductconfiglinks.pid=tblhosting.packageid ORDER BY tblproductconfigoptions.`order`,tblproductconfigoptions.id ASC";
    $result = full_query($query);
    while ($data = mysql_fetch_array($result)) {
        $confoption = $data["confoption"];
        $conftype = $data["conftype"];
        if (strpos($confoption, "|")) {
            $confoption = explode("|", $confoption);
            $confoption = trim($confoption[1]);
        }
        $optionname = $data["optionname"];
        $optionqty = $data["qty"];
        $optionid = $data["optionid"];
        if (strpos($optionname, "|")) {
            $optionname = explode("|", $optionname);
            $optionname = trim($optionname[1]);
        }
        if ($conftype == 3) {
            if ($optionqty) {
                $optionname = $_LANG["yes"];
            } else {
                $optionname = $_LANG["no"];
            }
        } else {
            if ($conftype == 4) {
                $optionname = $optionqty . " x " . $optionname . " ";
                $qtyprice = get_query_val("tblpricing", $configbillingcycle, ["type" => "configoptions", "currency" => $currency["id"], "relid" => $optionid]);
                $optionname .= formatCurrency($qtyprice);
            }
        }
        $description .= "\n" . $confoption . ": " . $optionname;
    }
    $customFields = WHMCS\CustomField::with("CustomFieldValues")->where("type", "product")->where("relid", $pid)->where("showinvoice", "on")->get();
    foreach ($customFields as $customField) {
        $customFieldValue = $customField->customFieldValues->where("relid", $id)->first();
        if ($customFieldValue) {
            $description .= "\n" . WHMCS\CustomField::getFieldName($customField->id, $customField->fieldName, $clientLanguage) . ": " . $customFieldValue->value;
        }
    }
    return ["description" => $description, "tax" => $tax, "recurringcycles" => $recurringcycles];
}

function getInvoiceProductPromo($amount, $promoid, $userid = 0, $serviceid = 0, $orderamt = 0, $cartQty = 1)
{
    global $_LANG;
    global $currency;
    if (!$promoid) {
        return [];
    }
    $continvoicegen = WHMCS\Config\Setting::getValue("ContinuousInvoiceGeneration");
    $data = get_query_vals("tblpromotions", "", ["id" => $promoid]);
    $promo_id = $data["id"];
    if (!$promo_id) {
        return [];
    }
    $promo_code = $data["code"];
    $promo_type = $data["type"];
    $promo_recurring = $data["recurring"];
    $promo_value = $data["value"];
    $promo_recurfor = $data["recurfor"];
    $promoAppliesTo = explode(",", $data["appliesto"]);
    if ($userid) {
        $currency = getCurrency($userid);
    }
    $allowQuantity = 0;
    if ($serviceid) {
        $serviceModel = WHMCS\Service\Service::with("product")->find($serviceid);
        $pid = $serviceModel->packageid;
        $regdate = $serviceModel->regdate;
        $nextduedate = $continvoicegen ? $serviceModel->nextInvoiceDate : $serviceModel->nextduedate;
        $firstpaymentamount = $serviceModel->firstpaymentamount;
        $billingcycle = $serviceModel->billingcycle;
        $billingcycle = str_replace("-", "", strtolower($billingcycle));
        $allowQuantity = $serviceModel->product->allowMultipleQuantities;
        if ($billingcycle == "one time") {
            $billingcycle = "monthly";
        }
    }
    if (!empty($serviceModel) && $serviceModel->isRecurring() && $promo_recurring && 0 < $promo_recurfor) {
        $promo_recurringcount = $serviceModel->promotionCount;
        if (is_null($promo_recurringcount)) {
            $promo_recurringcount = WHMCS\Database\Capsule::table("tblinvoiceitems")->where(["userid" => $userid, "type" => "PromoHosting", "relid" => $serviceid])->count("id");
            $serviceModel->promotionCount = $promo_recurringcount;
        }
        if ($promo_recurfor - 1 <= $promo_recurringcount) {
            $fullAmount = getInvoiceProductDefaultPrice($pid, $billingcycle, $regdate, $nextduedate);
            if (!function_exists("getCartConfigOptions")) {
                require ROOTDIR . "/includes/configoptionsfunctions.php";
            }
            $configoptions = getCartConfigOptions($pid, "", $billingcycle, $serviceid);
            foreach ($configoptions as $configoption) {
                $fullAmount += $configoption["selectedrecurring"];
            }
            $serviceModel->recurringAmount = $fullAmount;
            $serviceModel->promotionId = "0";
            $serviceModel->promotionCount = "0";
        }
        if ($serviceModel->isDirty()) {
            $serviceModel->save();
        }
    }
    if (!$promo_id) {
        return [];
    }
    if (!$serviceid || $promo_recurring || !$promo_recurring && $regdate == $nextduedate) {
        if ($promo_type == "Percentage") {
            if ($promo_value != 100) {
                $promo_amount = round($amount / (1 - $promo_value / 100), 2) - $amount;
            } else {
                $promo_amount = 0;
            }
            if ($orderamt) {
                $promoAmountCheck = $promo_amount + $amount;
                $skipPromoAmountCheck = false;
                if (!empty($_SESSION["cart"]["products"])) {
                    foreach ($_SESSION["cart"]["products"] as $product) {
                        if (in_array($product["pid"], $promoAppliesTo)) {
                            $qty = $product["qty"] ?? 1;
                            if (1 < $qty) {
                                $skipPromoAmountCheck = true;
                            }
                        }
                    }
                }
                if ($promoAmountCheck < $orderamt && !$skipPromoAmountCheck) {
                    $promo_amount = $promo_amount + $orderamt - $promoAmountCheck;
                }
            }
            if (0 < $promo_value && $promo_amount <= 0) {
                $promo_amount = $orderamt ? $orderamt : getInvoiceProductDefaultPrice($pid, $billingcycle, $regdate, $nextduedate);
            }
            $promo_value .= "%";
        } else {
            if ($promo_type == "Fixed Amount") {
                if ($currency["id"] != 1) {
                    $promo_value = convertCurrency($promo_value, null, $currency["id"], 1);
                }
                $default_price = "";
                $default_price = getInvoiceProductDefaultPrice($pid, $billingcycle, $regdate, $nextduedate, $serviceid, $userid);
                if ($default_price < $promo_value) {
                    $promo_value = $default_price;
                }
                $default_price = "";
                $promo_amount = $promo_value;
                $promo_value = formatCurrency($promo_value);
            } else {
                if ($promo_type == "Price Override") {
                    if ($currency["id"] != 1) {
                        $promo_value = convertCurrency($promo_value, null, $currency["id"], 1);
                    }
                    $promo_amount = $orderamt ? $orderamt : getInvoiceProductDefaultPrice($pid, $billingcycle, $regdate, $nextduedate);
                    $promo_amount -= $promo_value;
                    $promo_value = formatCurrency($promo_value) . " " . $_LANG["orderpromopriceoverride"];
                } else {
                    if ($promo_type == "Free Setup") {
                        if ($orderamt && 1 < $cartQty && $allowQuantity === WHMCS\Cart\CartCalculator::QUANTITY_MULTIPLE) {
                            $orderamt /= $cartQty;
                        }
                        $promo_amount = $orderamt ? $orderamt : getInvoiceProductDefaultPrice($pid, $billingcycle, $regdate, $nextduedate);
                        $promo_amount -= $firstpaymentamount;
                        $promo_value = $_LANG["orderpromofreesetup"];
                    }
                }
            }
        }
        getUsersLang($userid);
        $promo_recurring = $promo_recurring ? $_LANG["recurring"] : $_LANG["orderpaymenttermonetime"];
        $promo_description = $_LANG["orderpromotioncode"] . ": " . $promo_code . " - " . $promo_value . " " . $promo_recurring . " " . $_LANG["orderdiscount"];
        getUsersLang(0);
        if (!empty($serviceModel) && 0 < $serviceModel->promotionId) {
            $serviceModel->increment("promocount");
        }
        return ["description" => $promo_description, "amount" => $promo_amount * -1];
    } else {
        return [];
    }
}

function getInvoiceProductDefaultPrice($pid, $billingCycle, $regDate, $nextDueDate, $serviceID = 0, $userID = 0)
{
    global $currency;
    $data = WHMCS\Database\Capsule::table("tblpricing")->where("type", "=", "product")->where("currency", "=", $currency["id"])->where("relid", "=", $pid)->first();
    $amount = 0;
    switch ($billingCycle) {
        case "one time":
        case "monthly":
            $setupFieldName = "msetupfee";
            $amount = $data->monthly;
            break;
        case "quarterly":
            $setupFieldName = "qsetupfee";
            $amount = $data->quarterly;
            break;
        case "semiannually":
            $setupFieldName = "ssetupfee";
            $amount = $data->semiannually;
            break;
        case "annually":
            $setupFieldName = "asetupfee";
            $amount = $data->annually;
            break;
        case "biennially":
            $setupFieldName = "bsetupfee";
            $amount = $data->biennially;
            break;
        case "triennially":
            $setupFieldName = "tsetupfee";
            $amount = $data->triennally;
            if ($regDate == $nextDueDate && isset($setupFieldName)) {
                $amount += $data->{$setupFieldName};
            }
            if ($serviceID) {
                if (!function_exists("recalcRecurringProductPrice")) {
                    require ROOTDIR . "/includes/clientfunctions.php";
                }
                if ($billingCycle == "semiannually") {
                    $billingCycle = "Semi-Annually";
                } else {
                    $billingCycle = ucfirst($billingCycle);
                }
                $includeSetup = false;
                if ($regDate == $nextDueDate) {
                    $includeSetup = true;
                }
                $amount = recalcRecurringProductPrice($serviceID, $userID, $pid, $billingCycle, "empty", 0, $includeSetup);
            }
            return $amount;
            break;
        default:
            throw new WHMCS\Exception("Unable to obtain pricing for billing cycle");
    }
}

function cancelUnpaidUpgrade($serviceId)
{
    if (empty($serviceId) || !is_int($serviceId)) {
        return false;
    }
    if (!function_exists("changeOrderStatus")) {
        include ROOTDIR . "/includes/orderfunctions.php";
    }
    if (!is_array($cancelledStatuses)) {
        $cancelledStatuses = WHMCS\Database\Capsule::table("tblorderstatuses")->where("showcancelled", 1)->pluck("title")->all();
        $cancelledStatuses[] = "Fraud";
    }
    $upgrades = WHMCS\Database\Capsule::table("tblupgrades")->leftJoin("tblorders", "tblorders.id", "=", "tblupgrades.orderid")->join("tblinvoices", "tblinvoices.id", "=", "tblorders.invoiceid")->where("tblupgrades.relid", "=", $serviceId)->where("tblupgrades.paid", "=", "N")->whereNotIn("tblorders.status", $cancelledStatuses)->get()->all();
    foreach ($upgrades as $upgrade) {
        changeOrderStatus($upgrade->orderid, "Cancelled");
        $extraData = ["order_id" => $upgrade->orderid, "order_number" => get_query_val("tblorders", "ordernum", ["id" => $upgrade->orderid]), "upgrade_type" => $upgrade->type, "order_date" => fromMySQLDate($upgrade->date, "", true), "order_amount" => formatCurrency($upgrade->amount), "recurring_amount_change" => formatCurrency($upgrade->recurringchange)];
        sendMessage("Upgrade Order Cancelled", $serviceId, $extraData);
    }
    return true;
}
