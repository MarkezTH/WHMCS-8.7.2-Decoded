<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Clients Products/Services");
$aInt->requiredFiles(["clientfunctions", "gatewayfunctions", "modulefunctions", "customfieldfunctions", "configoptionsfunctions", "invoicefunctions", "processinvoices"]);
$aInt->setClientsProfilePresets();
$aInt->setHelpLink("Clients:Products/Services Tab");
$jscode = "";
$id = (int) $whmcs->get_req_var("id");
$hostingid = (int) $whmcs->get_req_var("hostingid");
$userid = (int) $whmcs->get_req_var("userid");
$aid = $whmcs->get_req_var("aid");
$action = $whmcs->get_req_var("action");
$modop = $whmcs->get_req_var("modop");
$server = (int) $whmcs->get_req_var("server");
if ($whmcs->getFromRequest("productselect")) {
    if (substr($whmcs->getFromRequest("productselect"), 0, 1) == "a") {
        $aid = (int) substr($whmcs->getFromRequest("productselect"), 1);
    } else {
        $id = (int) $whmcs->getFromRequest("productselect");
    }
}
$errors = [];
$jQueryCode = "";
$moduleInterface = NULL;
$provisioningType = "standard";
if ($modop) {
    checkPermission("Perform Server Operations");
    define("NO_QUEUE", true);
}
if (!$id && $hostingid) {
    $id = $hostingid;
}
if (!$id && $aid) {
    $addon = WHMCS\Service\Addon::with("service")->find($aid);
    if ($addon) {
        $id = $addon->serviceId;
        if (!$addon->clientId) {
            $addon->clientId = $addon->service->clientId;
            $addon->save();
        }
    }
}
if (!$userid && !$id) {
    $userid = get_query_val("tblclients", "id", "", "id", "ASC", "0,1");
}
if ($userid && !$id) {
    $aInt->valUserID($userid);
    if (!$userid) {
        $aInt->gracefulExit("Invalid User ID");
    }
    $id = get_query_val("tblhosting", "id", ["userid" => $userid], "domain", "ASC", "0,1");
}
if (!$id) {
    $aInt->gracefulExit($aInt->lang("services", "noproductsinfo") . " <a href=\"ordersadd.php?userid=" . $userid . "\">" . $aInt->lang("global", "clickhere") . "</a> " . $aInt->lang("orders", "toplacenew"));
}
try {
    $service_data = WHMCS\Service\Service::with("product")->findOrFail($id);
} catch (Exception $e) {
    $aInt->gracefulExit("Service ID Not Found");
}
$id = $service_data["id"];
if (!$id) {
    $aInt->gracefulExit("Service ID Not Found");
}
if ($service_data["userid"] != $userid) {
    $userid = $service_data["userid"];
    $aInt->valUserID($userid);
}
$aInt->setClientsProfilePresets($userid);
$aInt->assertClientBoundary($userid);
$producttype = $service_data->product->type;
$module = $service_data->product->module;
$orderid = $service_data["orderid"];
$packageid = $service_data["packageid"];
$server = $service_data["server"];
$regdate = $service_data["regdate"];
$terminationDate = $service_data["termination_date"];
$completedDate = $service_data["completed_date"];
$domain = $service_data["domain"];
$domainPunycode = "";
if ($domain) {
    try {
        $wildCard = false;
        if (substr($domain, 0, 2) === "*.") {
            $wildCard = true;
            $domain = substr($domain, 2);
        }
        $domainPunycode = WHMCS\Domains\Idna::toPunycode($domain);
        if ($wildCard) {
            $domain = "*." . $domain;
            $domainPunycode = "*." . $domainPunycode;
        }
    } catch (Exception $e) {
        $domainPunycode = $e->getMessage();
    }
}
$paymentmethod = $service_data["paymentmethod"];
$createServerOptionForNone = false;
$serverModule = new WHMCS\Module\Server();
if (!empty($aid) && is_numeric($aid)) {
    $serverModule->setAddonId($aid);
} else {
    if (is_numeric($id)) {
        $serverModule->setServiceId($id);
    }
}
if ($module && !$aid) {
    if ($serverModule->load($module)) {
        if ($serverModule->isMetaDataValueSet("RequiresServer") && !$serverModule->getMetaDataValue("RequiresServer")) {
            $createServerOptionForNone = true;
        }
    } else {
        logActivity("Required Product Module '" . $serverModule->getServiceModule() . "' Missing - Service ID: " . $id, $userid);
    }
}
$gateways = new WHMCS\Gateways();
if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
    $paymentmethod = ensurePaymentMethodIsSet($userid, $id, "tblhosting");
}
$quantity = $service_data["qty"];
$allowQuantity = $service_data->product->allowMultipleQuantities;
$firstpaymentamount = $service_data["firstpaymentamount"];
$amount = $service_data["amount"];
$billingcycle = $serviceBillingCycle = $service_data["billingcycle"];
$nextduedate = $service_data["nextduedate"];
$domainstatus = $service_data["domainstatus"];
$username = $service_data["username"];
$password = decrypt($service_data["password"]);
$notes = $service_data["notes"];
$subscriptionid = $service_data["subscriptionid"];
$promoid = $service_data["promoid"];
$promocount = $service_data["promocount"];
$suspendreason = $service_data["suspendreason"];
$overideautosuspend = $service_data["overideautosuspend"];
$ns1 = $service_data["ns1"];
$ns2 = $service_data["ns2"];
$dedicatedip = $service_data["dedicatedip"];
$assignedips = $service_data["assignedips"];
$diskusage = $service_data["diskusage"];
$disklimit = $service_data["disklimit"];
$bwusage = $service_data["bwusage"];
$bwlimit = $service_data["bwlimit"];
$lastupdate = $service_data["lastupdate"];
$overidesuspenduntil = $service_data["overidesuspenduntil"];
$welcomeEmail = $service_data->product->welcomeEmailTemplateId;
$addonModule = "";
$addonDetails = NULL;
if ($aid && is_numeric($aid)) {
    try {
        $addonDetails = WHMCS\Service\Addon::with("productAddon", "service")->where("id", "=", $aid)->whereIn("userid", [0, $userid])->firstOrFail();
        if (!$addonDetails->clientId) {
            $addonDetails->clientId = $addonDetails->service->clientId;
            $addonDetails->save();
        }
        if ($addonDetails->productAddon) {
            $welcomeEmail = $addonDetails->productAddon->welcomeEmailTemplateId;
        }
    } catch (Exception $e) {
        redir("userid=" . $userid . "&id=" . $id);
    }
    $addonModule = $addonDetails->productAddon->module;
    $provisioningType = $addonDetails->provisioningType;
}
$canResendWelcomeEmail = false;
if ($welcomeEmail != 0 && $module != "marketconnect") {
    $canResendWelcomeEmail = true;
}
$frm = new WHMCS\Form();
$adminServicesTabFieldsSaveErrors = NULL;
if ($frm->issubmitted()) {
    checkPermission("Edit Clients Products/Services");
    $packageid = $whmcs->get_req_var("packageid");
    $oldserviceid = $whmcs->get_req_var("oldserviceid");
    $addonid = $whmcs->get_req_var("addonid");
    $name = $whmcs->get_req_var("name");
    $setupfee = $whmcs->get_req_var("setupfee");
    $recurring = $whmcs->get_req_var("recurring");
    $quantity = App::getFromRequest("qty");
    $billingcycle = $whmcs->get_req_var("billingcycle");
    $status = $whmcs->get_req_var("domainstatus");
    $regdate = $whmcs->get_req_var("regdate");
    $terminationDate = $whmcs->get_req_var("termination_date");
    $oldnextduedate = $whmcs->get_req_var("oldnextduedate");
    $nextduedate = $whmcs->get_req_var("nextduedate");
    $overidesuspenduntil = $whmcs->get_req_var("overidesuspenduntil");
    $paymentmethod = $whmcs->get_req_var("paymentmethod");
    $tax = $whmcs->get_req_var("tax");
    $promoid = $whmcs->get_req_var("promoid");
    $subscriptionid = App::getFromRequest("subscriptionid");
    $notes = $whmcs->get_req_var("notes");
    $configoption = $whmcs->get_req_var("configoption");
    $server = $whmcs->get_req_var("server");
    $autorecalc = App::getFromRequest("autorecalc");
    $terminationDateValid = true;
    $queryStr = "userid=" . $userid . "&id=" . $id;
    if (is_string($terminationDate) && trim($terminationDate) == "") {
        $terminationDate = preg_replace("/[MDY]/i", "0", WHMCS\Config\Setting::getValue("DateFormat"));
    }
    if (is_string($overidesuspenduntil) && trim($overidesuspenduntil) == "") {
        $overidesuspenduntil = preg_replace("/[MDY]/i", "0", WHMCS\Config\Setting::getValue("DateFormat"));
    }
    if ($aid) {
        if (in_array($billingcycle, ["Free", WHMCS\Billing\Cycles::DISPLAY_FREE, WHMCS\Billing\Cycles::DISPLAY_ONETIME])) {
            if ($billingcycle !== WHMCS\Billing\Cycles::DISPLAY_ONETIME) {
                $setupfee = $recurring = 0;
            }
            $nextduedate = fromMySQLDate("0000-00-00");
        }
        if (is_numeric($aid)) {
            $status = $whmcs->get_req_var("status");
            try {
                $addonDetails = WHMCS\Service\Addon::where("id", "=", $aid)->where("userid", "=", $userid)->firstOrFail();
                $queryStr .= "&aid=" . $aid;
            } catch (Exception $e) {
                redir($queryStr);
            }
            $oldStatus = $addonDetails->status;
            $oldAddonId = $addonDetails->addonId;
            if (!in_array(toMySQLDate($terminationDate), ["0000-00-00", "1970-01-01"]) && !in_array($status, ["Terminated", "Cancelled"]) && !in_array($addonDetails->status, ["Terminated", "Cancelled"])) {
                $terminationDateValid = false;
                $queryStr .= "&terminationdateinvalid=1";
            }
            if (in_array($status, ["Terminated", "Cancelled"]) && in_array(toMySQLDate($terminationDate), ["0000-00-00", "1970-01-01"])) {
                $terminationDate = fromMySQLDate(date("Y-m-d"));
            } else {
                if (!in_array($status, ["Terminated", "Cancelled"]) && !in_array(toMySQLDate($terminationDate), ["0000-00-00", "1970-01-01"])) {
                    $terminationDate = fromMySQLDate("0000-00-00");
                }
            }
            $changelog = [];
            $forceServerReset = false;
            $newAddon = NULL;
            $newServer = 0;
            if ($id != $addonDetails->serviceId) {
                $changelog[] = "Transferred Addon from Service ID: " . $addonDetails->serviceId . " to Service ID: " . $id;
                $addonDetails->serviceId = $id;
            }
            if ($addonid != $addonDetails->addonId) {
                $addonsCollections = WHMCS\Product\Addon::whereIn("id", [$addonid, $addonDetails->addonId])->get();
                $addonModules = [];
                foreach ($addonsCollections as $addonsCollection) {
                    $addonModules[$addonsCollection->id] = $addonsCollection;
                }
                $oldServerModule = "";
                $newServerModule = "";
                $oldQuantityAllowed = $newQuantityAllowed = 0;
                if ($addonDetails->addonId) {
                    $oldQuantityAllowed = $addonModules[$addonDetails->addonId]->allowMultipleQuantities;
                    $oldServerModule = $addonModules[$addonDetails->addonId]->servertype;
                }
                if ($addonid) {
                    $newQuantityAllowed = $addonModules[$addonid]->allowMultipleQuantities;
                    $newServerModule = $addonModules[$addonid]->servertype;
                }
                if ($oldServerModule != $newServerModule) {
                    $forceServerReset = true;
                    $newAddon = $addonModules[$addonid];
                }
                unset($addonModules);
                $changelog[] = "Addon Id changed from " . $addonDetails->addonId . " to " . $addonid;
                $addonDetails->addonId = $addonid;
                if ($newQuantityAllowed !== 2 || !$newQuantityAllowed && $oldQuantityAllowed !== 2) {
                    $quantity = 1;
                }
            }
            if ($addonDetails->name != $name) {
                $changelog[] = "Addon Name changed from " . $addonDetails->name . " to " . $name;
                $addonDetails->name = $name;
            }
            if ($addonDetails->billingCycle != $billingcycle) {
                $changelog[] = "Billing Cycle changed from " . $addonDetails->billingCycle . " to " . $billingcycle;
                $addonDetails->billingCycle = $billingcycle;
            }
            if ($addonDetails->setupFee != $setupfee) {
                $changelog[] = "Setup Fee changed from " . $addonDetails->setupFee . " to " . $setupfee;
                $addonDetails->setupFee = $setupfee;
            }
            if ($addonDetails->recurringFee != $recurring) {
                $changelog["recurringFee"] = sprintf("Recurring Fee changed from %s to %s", $addonDetails->recurringFee, $recurring);
                $addonDetails->recurringFee = $recurring;
            }
            if ($addonDetails->status != $status) {
                $changelog[] = "Status changed from " . $addonDetails->status . " to " . $status;
                $addonDetails->status = $status;
            }
            if (fromMySQLDate($addonDetails->registrationDate) != $regdate) {
                $changelog[] = "Registration Date changed from " . fromMySQLDate($addonDetails->registrationDate) . " to " . $regdate;
                $addonDetails->registrationDate = toMySQLDate($regdate);
            }
            if (fromMySQLDate($addonDetails->nextDueDate) != $nextduedate) {
                $changelog[] = "Next Due Date changed from " . fromMySQLDate($addonDetails->nextDueDate) . " to " . $nextduedate;
                $addonDetails->nextDueDate = toMySQLDate($nextduedate);
                $addonDetails->nextInvoiceDate = toMySQLDate($nextduedate);
            }
            if (fromMySQLDate($addonDetails->terminationDate) != $terminationDate) {
                $changelog[] = "Termination Date changed from " . fromMySQLDate($addonDetails->terminationDate) . " to " . $terminationDate;
                $addonDetails->terminationDate = toMySQLDate($terminationDate);
            }
            if ($addonDetails->paymentGateway != $paymentmethod) {
                $changelog[] = "Payment Gateway changed from " . $addonDetails->paymentGateway . " to " . $paymentmethod;
                $addonDetails->paymentGateway = $paymentmethod;
            }
            if ($addonDetails->applyTax != (int) $tax) {
                $taxEnabledDisabled = "Disabled";
                if ($tax) {
                    $taxEnabledDisabled = "Enabled";
                }
                $changelog[] = "Tax " . $taxEnabledDisabled;
                $addonDetails->applyTax = (int) $tax;
            }
            if ($addonDetails->subscriptionId != $subscriptionid) {
                $changelog[] = "Subscription ID Changed from " . $addonDetails->subscriptionId . " to " . $subscriptionid;
                $addonDetails->subscriptionId = $subscriptionid;
            }
            if ($addonDetails->notes != $notes) {
                $changelog[] = "Addon Notes changed";
                $addonDetails->notes = $notes;
            }
            if ($addonDetails->qty != $quantity) {
                $changelog[] = "Quantity changed from " . $addonDetails->qty . " to " . $quantity;
                $addonDetails->qty = $quantity;
            }
            if ($forceServerReset) {
                $server = getServerID($newAddon->module, $newAddon->serverGroupId);
                $changelog[] = "Server Id automatically changed from " . $addonDetails->serverId . " to " . $server;
                $addonDetails->serverId = $server;
            } else {
                if ($provisioningType === "standard" && $addonDetails->serverId != $server) {
                    $changelog[] = "Server Id changed from " . $addonDetails->serverId . " to " . $server;
                    $addonDetails->serverId = $server;
                }
            }
            if ($autorecalc) {
                $addonDetails->saving(function (WHMCS\Service\Addon $o) use($changelog) {
                    $calculatedRecurring = $o->recalculateRecurringPrice();
                    if ($o->recurringFee != $calculatedRecurring) {
                        $changelog["recurringFee"] = sprintf("Recurring Fee changed from %s to %s (calculated)", $o->getOriginal("recurring"), $calculatedRecurring);
                        $o->recurringFee = $calculatedRecurring;
                    }
                });
            }
            migrateCustomFieldsBetweenProductsOrAddons($aid, $addonid, $oldAddonId, true, true);
            $addonDetails->save();
            if (!empty($changelog)) {
                logActivity("Modified Addon - " . implode(", ", $changelog) . " - User ID: " . $userid . " - Addon ID: " . $aid, $userid);
            }
            $moduleInterface = new WHMCS\Module\Server();
            $moduleInterface->loadByAddonId($aid);
            if ($moduleInterface->functionExists("AdminServicesTabFieldsSave")) {
                $moduleParams = $moduleInterface->buildParams();
                $adminServicesTabFieldsSaveErrors = $moduleInterface->call("AdminServicesTabFieldsSave", $moduleParams);
                if ($adminServicesTabFieldsSaveErrors && !is_array($adminServicesTabFieldsSaveErrors) && $adminServicesTabFieldsSaveErrors != "success") {
                    WHMCS\Session::set("adminServicesTabFieldsSaveErrors", $adminServicesTabFieldsSaveErrors);
                }
            }
            HookMgr::run("AdminClientServicesTabFieldsSave", $_REQUEST);
            if ($oldStatus == "Suspended" && $status == "Active") {
                run_hook("AddonUnsuspended", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
            } else {
                if ($oldStatus != "Active" && $status == "Active") {
                    run_hook("AddonActivated", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
                } else {
                    if ($oldStatus != "Suspended" && $status == "Suspended") {
                        run_hook("AddonSuspended", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
                    } else {
                        if ($oldStatus != "Terminated" && $status == "Terminated") {
                            run_hook("AddonTerminated", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
                        } else {
                            if ($oldStatus != "Cancelled" && $status == "Cancelled") {
                                run_hook("AddonCancelled", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
                            } else {
                                if ($oldStatus != "Fraud" && $status == "Fraud") {
                                    run_hook("AddonFraud", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
                                } else {
                                    run_hook("AddonEdit", ["id" => $aid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
                                }
                            }
                        }
                    }
                }
            }
        } else {
            checkPermission("Add New Order");
            $predefname = "";
            $geninvoice = $whmcs->getFromRequest("geninvoice");
            $addonIsProrated = false;
            if ($addonid) {
                $productAddon = WHMCS\Product\Addon::find($addonid);
                $addonid = $productAddon->id;
                $predefname = $productAddon->name;
                $tax = $productAddon->applyTax;
                if ($productAddon->allowMultipleQuantities !== 2) {
                    $quantity = 1;
                }
                if ($whmcs->get_req_var("defaultpricing")) {
                    $availableCycleTypes = $productAddon->billingCycle;
                    $currency = getCurrency($userid);
                    $pricing = new WHMCS\Pricing();
                    $pricing->loadPricing("addon", $addonid, $currency);
                    switch ($availableCycleTypes) {
                        case "recurring":
                            $availableCycles = $pricing->getAvailableBillingCycles();
                            $billingcycle = (new WHMCS\Billing\Cycles())->getNormalisedBillingCycle($billingcycle);
                            if (!in_array($billingcycle, $availableCycles)) {
                                $billingcycle = $pricing->getFirstAvailableCycle();
                            }
                            $setupfee = $pricing->getSetup($billingcycle);
                            $recurring = $pricing->getPrice($billingcycle);
                            $billingcycle = (new WHMCS\Billing\Cycles())->getPublicBillingCycle($billingcycle);
                            break;
                        case "free":
                            $billingcycle = WHMCS\Billing\Cycles::DISPLAY_FREE;
                            $setupfee = $recurring = 0;
                            $nextduedate = fromMySQLDate("0000-00-00");
                            break;
                        case "onetime":
                            $billingcycle = WHMCS\Billing\Cycles::DISPLAY_ONETIME;
                            $setupfee = $pricing->getSetup("monthly");
                            $recurring = $pricing->getPrice("monthly");
                            $nextduedate = fromMySQLDate("0000-00-00");
                            break;
                        default:
                            $billingcycle = $availableCycleTypes;
                            $setupfee = $pricing->getSetup("monthly");
                            $recurring = $pricing->getPrice("monthly");
                            if ($productAddon->prorate && (new WHMCS\Billing\Cycles())->isRecurring($billingcycle)) {
                                $addonIsProrated = true;
                            }
                    }
                }
                if ($addonIsProrated) {
                    $parentService = WHMCS\Service\Service::find($id);
                    $regDateCarbon = $nextDueDateCarbon = WHMCS\Carbon::now();
                    $addonChargeNextMonthDay = $parentService->product->proRataBilling ? $parentService->product->proRataChargeNextMonthAfterDay : 32;
                    $addonProrataDate = $parentService->product->proRataBilling ? $parentService->product->proRataChargeDayOfCurrentMonth : WHMCS\Carbon::safeCreateFromMySqlDate($parentService->nextDueDate)->day;
                    $serviceNextDueDate = WHMCS\Carbon::safeCreateFromMySqlDate($parentService->nextDueDate);
                    $prorataUntilDate = $parentService->billingCycle == $billingcycle ? $serviceNextDueDate : NULL;
                    $prorataValues = getProrataValues($billingcycle, $recurring, $addonProrataDate, $addonChargeNextMonthDay, $regDateCarbon->day, $regDateCarbon->month, $regDateCarbon->year, $userid, $prorataUntilDate);
                    $proratedAmount = $prorataValues["amount"];
                    $regdate = $regDateCarbon->toAdminDateFormat();
                    $nextduedate = $nextDueDateCarbon->toAdminDateFormat();
                }
                if ($productAddon->allowMultipleQuantities === WHMCS\Cart\CartCalculator::QUANTITY_SCALING) {
                    $recurring = $recurring * $quantity;
                }
            }
            $firstPaymentAmount = $setupfee + ($addonIsProrated ? $proratedAmount : $recurring);
            $status = $whmcs->get_req_var("status");
            $newAddon = new WHMCS\Service\Addon();
            $newAddon->serviceId = $id;
            $newAddon->addonId = $addonid;
            $newAddon->clientId = $userid;
            $newAddon->name = $name;
            $newAddon->setupFee = $setupfee;
            $newAddon->qty = $quantity;
            $newAddon->firstPaymentAmount = $firstPaymentAmount;
            $newAddon->recurringFee = $recurring;
            $newAddon->billingCycle = $billingcycle;
            $newAddon->status = $status;
            $newAddon->registrationDate = toMySQLDate($regdate);
            $newAddon->nextDueDate = toMySQLDate($nextduedate);
            $newAddon->nextInvoiceDate = toMySQLDate($nextduedate);
            $newAddon->terminationDate = in_array($status, ["Terminated", "Cancelled"]) ? date("Y-m-d") : "0000-00-00";
            $newAddon->paymentGateway = $paymentmethod;
            $newAddon->applyTax = (int) $tax;
            $newAddon->notes = $notes;
            if ($addonIsProrated) {
                $newAddon->prorataDate = $prorataValues["date"];
            }
            $newAddon->subscriptionId = $subscriptionid;
            $newAddon->save();
            $newaddonid = $newAddon->id;
            logActivity("Added New Addon - " . $name . $predefname . " - Addon ID: " . $newaddonid . " - Service ID: " . $id, $userid);
            if ($geninvoice) {
                if ($addonIsProrated) {
                    $invoiceAddonDetails = getInvoiceAddonDetails($newAddon);
                    WHMCS\Billing\Invoice\Item::create(["type" => "Addon", "relid" => $newaddonid, "description" => $invoiceAddonDetails["description"], "amount" => $firstPaymentAmount, "userid" => $userid, "taxed" => $invoiceAddonDetails["tax"], "duedate" => $nextDueDateCarbon->toDateString(), "paymentmethod" => $paymentmethod]);
                }
                $invoiceid = createInvoices($userid, "", "", ["addons" => [$newaddonid]]);
            }
            run_hook("AddonAdd", ["id" => $newaddonid, "userid" => $userid, "serviceid" => $id, "addonid" => $addonid]);
        }
        if ($terminationDateValid) {
            $queryStr .= "&success=true";
        }
        redir($queryStr);
    } else {
        if (toMySQLDate($terminationDate) != "0000-00-00" && !in_array($status, ["Terminated", "Cancelled"])) {
            $oldstatus = $service_data["domainstatus"];
            if (!in_array($oldstatus, ["Terminated", "Cancelled"])) {
                $terminationDateValid = false;
                $queryStr .= "&terminationdateinvalid=1";
            }
        }
    }
    if (!$whmcs->get_req_var("packageid") && !$whmcs->get_req_var("billingcycle")) {
        redir($queryStr);
    }
    $currency = getCurrency($userid);
    HookMgr::run("PreServiceEdit", ["serviceid" => $id]);
    HookMgr::run("PreAdminServiceEdit", ["serviceid" => $id]);
    $changelog = [];
    $configoptions = getCartConfigOptions($packageid, $configoption, $billingcycle);
    $configoptionsrecurring = 0;
    foreach ($configoptions as $configoption) {
        $configoptionsrecurring += $configoption["selectedrecurring"];
        $result = select_query("tblhostingconfigoptions", "COUNT(*)", ["relid" => $id, "configid" => $configoption["id"]]);
        $data = mysql_fetch_array($result);
        if (!$data[0]) {
            insert_query("tblhostingconfigoptions", ["relid" => $id, "configid" => $configoption["id"]]);
        }
        update_query("tblhostingconfigoptions", ["optionid" => $configoption["selectedvalue"], "qty" => $configoption["selectedqty"]], ["relid" => $id, "configid" => $configoption["id"]]);
    }
    $newamount = $autorecalc ? recalcRecurringProductPrice($id, $userid, $packageid, $billingcycle, $configoptionsrecurring, $promoid, false, false, $quantity) : "-1";
    $oldCustomFieldValues = getCustomFields("product", $service_data["packageid"], $id, true);
    foreach ($oldCustomFieldValues as $oldVal) {
        $newVal = App::getFromRequest("customfield", $oldVal["id"]);
        if ($oldVal["value"] != $newVal) {
            $values = [$oldVal["value"], $newVal];
            switch ($oldVal["type"]) {
                case "link":
                    $newLink = "<a href=\"" . $values[1] . "\" target=\"_blank\">" . $values[1] . "</a>";
                    if ($values[0] != $newLink) {
                        $changelog[] = $oldVal["name"] . " changed to " . $values[1];
                    }
                    break;
                case "password":
                    $changelog[] = $oldVal["name"] . " changed";
                    break;
                case "dropdown":
                case "tickbox":
                    $valueMap = ["dropdown" => "None", "tickbox" => "off"];
                    foreach ($values as $k => $v) {
                        if ($v == "") {
                            $values[$k] = $valueMap[$oldVal["type"]];
                        }
                    }
                    break;
                default:
                    $changelog[] = $oldVal["name"] . " changed from " . $values[0] . " to " . $values[1];
            }
        }
    }
    migrateCustomFieldsBetweenProductsOrAddons($id, $packageid, $service_data["packageid"], true);
    $logchangefields = ["regdate" => "Registration Date", "packageid" => "Product/Service", "server" => "Server", "domain" => "Domain", "dedicatedip" => "Dedicated IP", "paymentmethod" => "Payment Method", "firstpaymentamount" => "First Payment Amount", "qty" => "Quantity", "amount" => "Recurring Amount", "billingcycle" => "Billing Cycle", "nextduedate" => "Next Due Date", "domainstatus" => "Status", "termination_date" => "Termination Date", "username" => "Username", "password" => "Password", "notes" => "Admin Notes", "subscriptionid" => "Subscription ID", "promoid" => "Promotion Code ID", "overideautosuspend" => "Override Auto-Suspend", "overidesuspenduntil" => "Override Auto-Suspend Until Date"];
    $forceServerReset = false;
    $resetQuantity = false;
    $newProduct = NULL;
    $newServer = 0;
    foreach ($logchangefields as $fieldname => $displayname) {
        $newval = App::getFromRequest($fieldname);
        $oldval = $service_data[$fieldname];
        if (($fieldname == "nextduedate" || $fieldname == "overidesuspenduntil" || $fieldname == "termination_date") && !$newval) {
            $newval = "0000-00-00";
        } else {
            if ($fieldname == "regdate" || $fieldname == "nextduedate" || $fieldname == "overidesuspenduntil" || $fieldname == "termination_date") {
                $newval = toMySQLDate($newval);
            } else {
                if ($fieldname == "password") {
                    if ($newval != decrypt($oldval)) {
                        $changelog[] = $displayname . " changed";
                    }
                } else {
                    if ($fieldname == "amount" && 0 <= $newamount) {
                        $newval = $newamount;
                    } else {
                        if ($fieldname == "packageid" && $newval != $oldval) {
                            $productsCollections = WHMCS\Product\Product::whereIn("id", [$newval, $oldval])->get();
                            $productModules = [];
                            foreach ($productsCollections as $productsCollection) {
                                $productModules[$productsCollection->id] = $productsCollection;
                            }
                            if ($productModules[$newval]->servertype != $productModules[$oldval]->servertype) {
                                $forceServerReset = true;
                                $newProduct = $productModules[$newval];
                            }
                            if ($productModules[$newval]->allowMultipleQuantities !== 2 && $productModules[$oldval]->allowMultipleQuantities === 2) {
                                $resetQuantity = true;
                            }
                            unset($productModules);
                        } else {
                            if ($fieldname == "server" && $forceServerReset) {
                                $newval = getServerID($newProduct->module, $newProduct->serverGroupId);
                                $newServer = $newval;
                            } else {
                                if ($fieldname == "overideautosuspend" && $newval == "") {
                                    $newval = "0";
                                } else {
                                    if ($fieldname == "qty" && $resetQuantity) {
                                        $changelog[] = $displayname . " reset due to package change";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($newval != $oldval) {
            $changelog[] = $displayname . " changed from " . $oldval . " to " . $newval;
        }
    }
    $updatearr = [];
    $updatefields = ["server", "packageid", "domain", "paymentmethod", "qty", "firstpaymentamount", "amount", "billingcycle", "regdate", "nextduedate", "username", "password", "notes", "subscriptionid", "promoid", "overideautosuspend", "overidesuspenduntil", "ns1", "ns2", "domainstatus", "termination_date", "dedicatedip", "assignedips"];
    foreach ($updatefields as $fieldname) {
        $newval = App::getFromRequest($fieldname);
        if ($fieldname !== "password") {
            $newval = trim($newval);
        }
        if (in_array($fieldname, ["termination_date", "overidesuspenduntil"]) && is_string($newval) && trim($newval) == "") {
            $newval = preg_replace("/[MDY]/i", "0", WHMCS\Config\Setting::getValue("DateFormat"));
        }
        if ($fieldname == "domainstatus" && $newval == "Completed" && $service_data["domainstatus"] != "Completed") {
            $updatearr["completed_date"] = WHMCS\Carbon::today()->toDateString();
        }
        if ($fieldname == "regdate" || $fieldname == "nextduedate" || $fieldname == "overidesuspenduntil" || $fieldname == "termination_date") {
            if ($fieldname == "nextduedate" && in_array($billingcycle, ["Free Account", "One Time"])) {
                $newval = "0000-00-00";
            } else {
                if ($fieldname == "termination_date" && !in_array(toMySQLDate($newval), ["0000-00-00", "1970-01-01"]) && !in_array($status, ["Terminated", "Cancelled"])) {
                    $newval = "0000-00-00";
                    $changelog[] = "Termination Date reset to " . $newval;
                } else {
                    if ($fieldname == "termination_date" && in_array(toMySQLDate($newval), ["0000-00-00", "1970-01-01"]) && $service_data["termination_date"] == "0000-00-00" && in_array($status, ["Terminated", "Cancelled"])) {
                        $newval = date("Y-m-d");
                        $terminationDate = date("Y-m-d");
                        $updatearr["termination_date"] = date("Y-m-d");
                    } else {
                        if (validateDateInput($newval) || in_array($fieldname, ["overidesuspenduntil", "termination_date"]) && (!$newval || in_array(toMySQLDate($newval), ["0000-00-00", "1970-01-01"]))) {
                            $newval = toMySQLDate($newval);
                        } else {
                            $errors[] = "The " . $logchangefields[$fieldname] . " you entered is invalid";
                        }
                    }
                }
            }
        } else {
            if ($fieldname == "password") {
                $newval = encrypt($newval);
            } else {
                if ($fieldname == "amount" && 0 <= $newamount) {
                    $newval = $newamount;
                } else {
                    if ($fieldname == "server" && $forceServerReset) {
                        $newval = $newServer;
                    } else {
                        if ($fieldname == "promoid" && $newval != $service_data["promoid"]) {
                            $updatearr["promocount"] = "0";
                        } else {
                            if ($fieldname == "qty" && $resetQuantity) {
                                $newval = 1;
                            }
                        }
                    }
                }
            }
        }
        $updatearr[$fieldname] = $newval;
    }
    if (toMySQLDate($whmcs->get_req_var("oldnextduedate")) != $updatearr["nextduedate"]) {
        $updatearr["nextinvoicedate"] = $updatearr["nextduedate"];
    }
    if (count($errors) == 0) {
        if ($updatearr) {
            update_query("tblhosting", $updatearr, ["id" => $id]);
        }
        if ($changelog) {
            logActivity("Modified Product/Service - " . implode(", ", $changelog) . " - User ID: " . $userid . " - Service ID: " . $id, $userid);
        }
        $cancelid = WHMCS\Database\Capsule::table("tblcancelrequests")->where("relid", $id)->orderBy("id", "desc")->first();
        if (isset($autoterminateendcycle) && $autoterminateendcycle) {
            if ($cancelid && $cancelid->type == "Immediate") {
                WHMCS\Database\Capsule::table("tblcancelrequests")->where("id", $cancelid->id)->update(["reason" => $autoterminatereason, "type" => "End of Billing Period"]);
            } else {
                if (!$cancelid) {
                    createCancellationRequest($userid, $id, $autoterminatereason, "End of Billing Period");
                }
            }
        } else {
            if ($cancelid && $cancelid->type == "End of Billing Period") {
                WHMCS\Database\Capsule::table("tblcancelrequests")->where("id", $cancelid->id)->delete($cancelid->id);
                logActivity("Removed Automatic Cancellation for End of Current Cycle - Service ID: " . $id, $userid);
            }
        }
        $module = get_query_val("tblproducts", "servertype", ["id" => $packageid]);
        if ($module) {
            $moduleInterface = new WHMCS\Module\Server();
            if ($moduleInterface->loadByServiceID($id) && $moduleInterface->functionExists("AdminServicesTabFieldsSave")) {
                $moduleParams = $moduleInterface->buildParams();
                $adminServicesTabFieldsSaveErrors = $moduleInterface->call("AdminServicesTabFieldsSave", $moduleParams);
                if ($adminServicesTabFieldsSaveErrors && !is_array($adminServicesTabFieldsSaveErrors) && $adminServicesTabFieldsSaveErrors != "success") {
                    WHMCS\Session::set("adminServicesTabFieldsSaveErrors", $adminServicesTabFieldsSaveErrors);
                }
            }
        }
        HookMgr::run("AdminClientServicesTabFieldsSave", $_REQUEST);
        HookMgr::run("AdminServiceEdit", ["userid" => $userid, "serviceid" => $id]);
        HookMgr::run("ServiceEdit", ["userid" => $userid, "serviceid" => $id]);
        if ($terminationDateValid) {
            $queryStr .= "&success=true";
        }
        redir($queryStr);
    }
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Clients Products/Services");
    try {
        $service = WHMCS\Service\Service::with("product", "customFieldValues", "customFieldValues.customField", "addons", "addons.customFieldValues", "addons.customFieldValues.customField")->findOrFail($id);
        if ($service->product->stockControlEnabled) {
            $service->product->quantityInStock++;
            $service->product->save();
        }
        foreach ($service->addons as $serviceAddon) {
            foreach ($serviceAddon->customFieldValues as $customFieldValue) {
                if ($customFieldValue->customField->type == "addon") {
                    $customFieldValue->delete();
                }
            }
            $serviceAddon->delete();
        }
        foreach ($service->customFieldValues as $customFieldValue) {
            if ($customFieldValue->customField->type == "product") {
                $customFieldValue->delete();
            }
        }
        $service->delete();
        delete_query("tblhostingconfigoptions", ["relid" => $id]);
        delete_query("tblaffiliatesaccounts", ["relid" => $id]);
        logActivity("Deleted Product/Service - User ID: " . $userid . " - Service ID: " . $id, $userid);
    } catch (Exception $e) {
    }
    redir("userid=" . $userid);
}
if ($action == "deladdon") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Clients Products/Services");
    run_hook("AddonDeleted", ["id" => $aid]);
    $addon = WHMCS\Service\Addon::find($aid);
    if ($addon) {
        $addon->delete();
    }
    logActivity("Deleted Addon - User ID: " . $userid . " - Service ID: " . $id . " - Addon ID: " . $aid, $userid);
    redir("userid=" . $userid . "&id=" . $id);
}
if ($action == "refreshStats") {
    $field = $serverModule->getMetaDataValue("ListAccountsUniqueIdentifierField");
    $serviceModel = WHMCS\Service\Service::find($id);
    $tenant = $serviceModel->getUniqueIdentifierValue($field);
    $table = "";
    $errorMsg = "";
    if ($tenant && $server) {
        try {
            $data = WHMCS\Product\Server::findOrFail($server)->syncTenantUsage($tenant);
            $helper = new WHMCS\UsageBilling\Service\ViewHelper();
            $table = $helper->serverTenantUsageTable($serviceModel->metrics());
            $aInt->jsonResponse(["success" => true, "body" => $table]);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    } else {
        $errorMsg = "Tenant Identifier or Server ID missing";
    }
    $aInt->jsonResponse(["success" => false, "error" => $errorMsg]);
}
ob_start();
$adminbuttonarray = "";
if ($module && !(int) $aid && $serverModule->functionExists("AdminCustomButtonArray")) {
    $moduleParams = $serverModule->buildParams();
    $adminbuttonarray = $serverModule->call("AdminCustomButtonArray", $moduleParams);
}
if ($modop) {
    check_token("WHMCS.admin.default");
    $extra = "";
    if ((int) $aid) {
        $extra = "&aid=" . $aid;
    }
    $allowAccess = false;
    if (in_array($modop, ["singlesignon", "singlesignon-feature"])) {
        $serverId = (int) $server;
        if ($addonDetails) {
            $serverId = $addonDetails->serverId;
        }
        $allowedRoleIds = WHMCS\Database\Capsule::table("tblserversssoperms")->where("server_id", "=", $serverId)->pluck("role_id")->all();
        if (count($allowedRoleIds) == 0) {
            $allowAccess = true;
        } else {
            $allowAccess = false;
            $adminAuth = new WHMCS\Auth();
            $adminAuth->getInfobyID(WHMCS\Session::get("adminid"));
            $adminRoleId = $adminAuth->getAdminRoleId();
            if (in_array($adminRoleId, $allowedRoleIds)) {
                $allowAccess = true;
            }
        }
        if (!$allowAccess) {
            WHMCS\Cookie::set("ModCmdResult", "You do not have permission to sign-in to this server. If you feel this message to be an error, please contact the system administrator.");
        }
    }
    switch ($modop) {
        case "create":
            try {
                if (0 < $aid) {
                    $result = WHMCS\Service\Addon::findOrFail($aid)->legacyProvision();
                } else {
                    $result = WHMCS\Service\Service::findOrFail($id)->legacyProvision();
                }
            } catch (Exception $e) {
                $result = $e->getMessage();
            }
            WHMCS\Cookie::set("ModCmdResult", $result);
            break;
        case "renew":
            $result = ServerRenew($id, (int) $aid);
            WHMCS\Cookie::set("ModCmdResult", $result);
            break;
        case "suspend":
            $suspreason = App::getFromRequest("suspreason");
            $suspemail = App::getFromRequest("suspemail");
            $result = ServerSuspendAccount($id, $suspreason, (int) $aid);
            WHMCS\Cookie::set("ModCmdResult", $result);
            if ($result == "success" && $suspemail == "true") {
                $emailTemplate = WHMCS\Mail\Template::where("type", "=", "product")->where("name", "=", "Service Suspension Notification")->get()->first();
                if (!is_null($emailTemplate)) {
                    $isDisabled = $emailTemplate->disabled;
                    if ($isDisabled) {
                        $emailTemplate->disabled = 0;
                        $emailTemplate->save();
                    }
                    sendMessage("Service Suspension Notification", $id);
                    if ($isDisabled) {
                        $emailTemplate->disabled = $isDisabled;
                        $emailTemplate->save();
                    }
                }
            }
            break;
        case "unsuspend":
            $sendEmail = App::getFromRequest("unsuspended_email");
            $result = ServerUnsuspendAccount($id, (int) $aid);
            WHMCS\Cookie::set("ModCmdResult", $result);
            if ($result == "success" && $sendEmail == "true") {
                $emailTemplate = WHMCS\Mail\Template::where("type", "=", "product")->where("name", "=", "Service Unsuspension Notification")->get()->first();
                if (!is_null($emailTemplate)) {
                    $isDisabled = $emailTemplate->disabled;
                    if ($isDisabled) {
                        $emailTemplate->disabled = 0;
                        $emailTemplate->save();
                    }
                    sendMessage("Service Unsuspension Notification", $id);
                    if ($isDisabled) {
                        $emailTemplate->disabled = $isDisabled;
                        $emailTemplate->save();
                    }
                }
            }
            break;
        case "terminate":
            $keepZone = App::getFromRequest("keep_zone") === "true";
            $invoiceUsage = App::getFromRequest("invoice_usage") === "true";
            $result = ModuleCallFunction("Terminate", $id, ["keepZone" => $keepZone, "invoiceUsage" => $invoiceUsage], $aid);
            WHMCS\Cookie::set("ModCmdResult", $result);
            break;
        case "changepackage":
            $result = ServerChangePackage($id, (int) $aid);
            WHMCS\Cookie::set("ModCmdResult", $result);
            break;
        case "changepw":
            $result = ServerChangePassword($id, (int) $aid);
            WHMCS\Cookie::set("ModCmdResult", $result);
            break;
        case "manageapplinks":
            $moduleInterface = new WHMCS\Module\Server();
            if ((int) $aid) {
                $moduleInterface->loadByAddonId((int) $aid);
            } else {
                $moduleInterface->loadByServiceID($id);
            }
            try {
                $moduleInterface->doSingleApplicationLinkCall(App::getFromRequest("command"));
                $success = true;
                $errorMsg = [];
            } catch (Exception $e) {
                $success = false;
                $errorMsg = $e->getMessage();
            }
            $aInt->setBodyContent(["success" => $success, "errorMsg" => $errorMsg]);
            $aInt->output();
            WHMCS\Terminus::getInstance()->doExit();
            break;
        case "singlesignon":
            if ($allowAccess) {
                $redirectUrl = "";
                try {
                    $moduleInterface = new WHMCS\Module\Server();
                    if ((int) $aid) {
                        $moduleInterface->loadByAddonId((int) $aid);
                    } else {
                        $moduleInterface->loadByServiceID($id);
                    }
                    $redirectUrl = $moduleInterface->getSingleSignOnUrlForService();
                } catch (WHMCS\Exception\Module\SingleSignOnError $e) {
                    WHMCS\Cookie::set("ModCmdResult", $e->getMessage());
                } catch (Exception $e) {
                    logActivity("Single Sign-On Request Failed with a Fatal Error: " . $e->getMessage(), $userid);
                    WHMCS\Cookie::set("ModCmdResult", AdminLang::trans("sso.fatalerror"));
                }
                echo "window|" . $redirectUrl;
                WHMCS\Terminus::getInstance()->doExit();
            }
            break;
        case "provision":
        case "deprovision":
        case "unsuspend-feature":
            if ($addonDetails) {
                $actionsToCall = ["provision" => "provisionAddOnFeature", "deprovision" => "deprovisionAddOnFeature", "unsuspend-feature" => "unsuspendAddOnFeature"];
                $result = WHMCS\Service\Automation\AddonAutomation::factory($addonDetails)->{$actionsToCall}[$modop]();
                WHMCS\Cookie::set("ModCmdResult", $result);
            } else {
                WHMCS\Cookie::set("ModCmdResult", "Invalid Request");
            }
            break;
        case "suspend-feature":
            if ($addonDetails) {
                $suspendReason = App::getFromRequest("suspreason");
                $result = WHMCS\Service\Automation\AddonAutomation::factory($addonDetails)->suspendAddOnFeature();
                WHMCS\Cookie::set("ModCmdResult", $result);
            } else {
                WHMCS\Cookie::set("ModCmdResult", "Invalid Request");
            }
            break;
        case "singlesignon-feature":
            if ($allowAccess) {
                $redirectUrl = "";
                try {
                    $redirectUrl = WHMCS\Service\Automation\AddonAutomation::factory($addonDetails)->singleSignOnAddOnFeature();
                } catch (WHMCS\Exception\Module\SingleSignOnError $e) {
                    WHMCS\Cookie::set("ModCmdResult", $e->getMessage());
                } catch (Exception $e) {
                    logActivity("Single Sign-On Request Failed with a Fatal Error: " . $e->getMessage(), $userid);
                    WHMCS\Cookie::set("ModCmdResult", AdminLang::trans("sso.fatalerror"));
                }
                echo "window|" . $redirectUrl;
                WHMCS\Terminus::getInstance()->doExit();
            }
            break;
        case "custom":
            $ac = $whmcs->getFromRequest("ac");
            $result = ServerCustomFunction($id, $ac, (int) $aid);
            if (!$ac) {
                $aInt->setBodyContent(["success" => false, "errorMsg" => "Invalid Request"]);
                $aInt->output();
                WHMCS\Terminus::getInstance()->doExit();
            }
            if (isset($result["jsonResponse"])) {
                $result = $result["jsonResponse"];
            }
            if (is_array($result)) {
                if (count($result) == 1 && array_key_exists("error", $result)) {
                    $result = $result["error"];
                } else {
                    if (!(count($result) == 1 && array_key_exists("success", $result))) {
                        $aInt->jsonResponse($result);
                    }
                }
            } else {
                if (substr($result, 0, 9) == "redirect|" || substr($result, 0, 7) == "window|") {
                    echo $result;
                    WHMCS\Terminus::getInstance()->doExit();
                }
            }
            WHMCS\Cookie::set("ModCmdResult", $result);
            break;
        default:
            WHMCS\Cookie::set("ModCmdResult", "Invalid Request");
            redir("userid=" . $userid . "&id=" . $id . $extra . "&act=" . $modop . "&ajaxupdate=1");
    }
}
if (in_array($whmcs->get_req_var("act"), ["create", "renew", "suspend", "unsuspend", "terminate", "provision", "deprovision", "suspend-feature", "unsuspend-feature", "updown", "changepw", "custom", "singlesignon", "singlesignon-feature", "changepackage"]) && ($result = WHMCS\Cookie::get("ModCmdResult"))) {
    $result2 = WHMCS\Cookie::get("ModCmdResult", true);
    if ($result2 && is_array($result2) && array_key_exists("error", $result2)) {
        infoBox(AdminLang::trans("services.moduleerror"), nl2br(WHMCS\Input\Sanitize::makeSafeForOutput($result2["error"])));
    } else {
        if ($result2 && is_array($result2) && array_key_exists("success", $result2)) {
            infoBox(AdminLang::trans("services.modulesuccess"), nl2br(WHMCS\Input\Sanitize::makeSafeForOutput($result2["success"])));
        } else {
            if ($result != "success") {
                infoBox(AdminLang::trans("services.moduleerror"), WHMCS\Input\Sanitize::makeSafeForOutput($result), "error");
            } else {
                infoBox(AdminLang::trans("services.modulesuccess"), AdminLang::trans("services." . $act . "success"), "success");
            }
        }
    }
    WHMCS\Cookie::delete("ModCmdResult");
}
if ($whmcs->get_req_var("success")) {
    infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("global", "changesuccessdesc"), "success");
} else {
    if ($whmcs->get_req_var("terminationdateinvalid")) {
        infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("clients", "terminationdateinvalid"), "success");
    } else {
        if (count($errors)) {
            $errormsg = "";
            foreach ($errors as $error) {
                $errormsg .= $error . "<br />";
            }
            infoBox($aInt->lang("global", "followingerrorsoccurred"), $errormsg, "error");
        }
    }
}
if (!count($errors)) {
    $regdate = fromMySQLDate($regdate);
    $terminationDate = fromMySQLDate($terminationDate);
    $nextduedate = fromMySQLDate($nextduedate);
    $overidesuspenduntil = fromMySQLDate($overidesuspenduntil);
}
if ($disklimit == "0") {
    $disklimit = $aInt->lang("global", "unlimited");
}
if ($bwlimit == "0") {
    $bwlimit = $aInt->lang("global", "unlimited");
}
$currency = getCurrency($userid);
$cancelid = NULL;
$canceltype = NULL;
$autoterminatereason = NULL;
$data = WHMCS\Database\Capsule::table("tblcancelrequests")->where("relid", $id)->orderBy("id", "desc")->first(["id", "type", "reason"]);
if ($data) {
    $cancelid = $data->id ?? NULL;
    $canceltype = $data->type ?? NULL;
    $autoterminatereason = $data->reason ?? NULL;
}
$autoterminateendcycle = false;
if ($canceltype == "End of Billing Period") {
    $autoterminateendcycle = (bool) $cancelid;
}
if (!$server) {
    $server = get_query_val("tblservers", "id", ["type" => $module, "active" => "1"]);
    if ($server) {
        update_query("tblhosting", ["server" => $server], ["id" => $id]);
    }
}
$makeSubscriptionManagementHtml = function ($subscriptionId, $gatewayModule = "", $infoRoute = "") use($frm) {
    $controlsHtml = "";
    $inactiveGatewayHtml = "";
    if ($subscriptionId) {
        $gateway = new WHMCS\Module\Gateway();
        if ($gatewayModule && $gateway->load($gatewayModule) && $gateway->isLoadedModuleActive()) {
            $manageSubButtons = [];
            if ($gateway->functionExists("get_subscription_info")) {
                $title = AdminLang::trans("subscription.info");
                $getInfoText = AdminLang::trans("global.getSubscriptionInfo");
                $manageSubButtons[] = "<a href=\"" . $infoRoute . "\"\n    class=\"btn btn-default open-modal\"\n    data-modal-title=\"" . $title . "\"\n    >" . $getInfoText . "</a>";
            }
            if ($gateway->functionExists("cancelSubscription")) {
                $cancelSubscriptionText = AdminLang::trans("services.cancelSubscription");
                $manageSubButtons[] = "<button type=\"button\"\n    class=\"btn btn-default\"\n    onclick=\"jQuery('#modalCancelSubscription').modal('show');\"\n    id=\"btnCancel_Subscription\"\n    style=\"margin-left:-3px;\"\n    >" . $cancelSubscriptionText . "</button>";
            }
            if (0 < count($manageSubButtons)) {
                $controlsHtml = sprintf("<span class=\"input-group-btn\">%s</span>", implode("\n", $manageSubButtons));
                unset($buttons);
            }
        }
    }
    $inputHtml = "";
    $subscriptionInput = $frm->text("subscriptionid", $subscriptionId, "25", false, "form-control");
    if (!empty($controlsHtml)) {
        $inputHtml = "<div class=\"input-group\">\n    " . $subscriptionInput . "\n    " . $controlsHtml . "\n</div>";
    } else {
        $inputHtml = $subscriptionInput;
    }
    $workingText = AdminLang::trans("global.working");
    return "<div id=\"subscription\">\n    <div class=\"form-inline\">\n        <div class=\"form-group\">" . $inputHtml . "</div>\n    </div>\n</div>\n<div id=\"subscriptionworking\" style=\"display:none;text-align:center;\">\n    <img src=\"images/loader.gif\" />&nbsp; " . $workingText . "\n</div>";
};
$makeAddonSubscriptionManagementHtml = function ($addonId, $subscriptionId, $gatewayModule = "") {
    return $makeSubscriptionManagementHtml($subscriptionId, $gatewayModule, routePathWithQuery("admin-addons-subscription-info", [$addonId], ["token" => generate_token("plain")]));
};
$makeServiceSubscriptionManagementHtml = function ($serviceId, $subscriptionId, $gatewayModule = "") {
    return $makeSubscriptionManagementHtml($subscriptionId, $gatewayModule, routePathWithQuery("admin-services-subscription-info", [$serviceId], ["token" => generate_token("plain")]));
};
$csrfLinkToken = generate_token("link");
$jscode .= "function doDeleteAddon(id) {\n    if (confirm(\"" . $aInt->lang("addons", "areYouSureDelete", 1) . "\")) {\n        window.location='?userid=" . $userid . "&id=" . $id . "&action=deladdon&aid=' + id + '" . $csrfLinkToken . "';\n    }\n}";
unset($csrfLinkToken);
$cancelRoute = "";
if (App::isInRequest("aid") && $aid) {
    $cancelRoute = routePath("admin-addons-cancel-subscription", $aid);
} else {
    $cancelRoute = routePath("admin-services-cancel-subscription", $id);
}
$anError = addslashes(AdminLang::trans("global.erroroccurred"));
$jscode .= "function cancelSubscription() {\n    var subscription = \$(\"#subscription\"),\n        subscriptionWorking = \$(\"#subscriptionworking\");\n    \$(\"#modalCancelSubscription\").modal(\"hide\");\n\n    subscription.css(\"filter\", \"alpha(opacity=20)\");\n    subscription.css(\"-moz-opacity\", \"0.2\");\n    subscription.css(\"-khtml-opacity\", \"0.2\");\n    subscription.css(\"opacity\", \"0.2\");\n    var position = subscription.position();\n\n    subscriptionWorking.css(\"position\", \"absolute\");\n    subscriptionWorking.css(\"top\", position.top);\n    subscriptionWorking.css(\"left\", position.left);\n    subscriptionWorking.css(\"padding\", \"9px 50px 0\");\n    subscriptionWorking.fadeIn();\n\n    WHMCS.http.jqClient.jsonPost({\n        url: \"" . $cancelRoute . "\",\n        data: {\n            token: csrfToken\n        },\n        success: function(data) {\n            if (data.successMsg) {\n                jQuery.growl.notice({ title: data.successMsgTitle, message: data.successMsg });\n                subscription.find(\"input\").val(\"\").change();\n                subscription.find(\"span.input-group-btn\").remove();\n            }\n            if (data.errorMsg) {\n                jQuery.growl.warning({title: data.errorMsgTitle, message: data.errorMsg});\n            }\n        },\n        error: function(data) {\n            jQuery.growl.warning({\n                title: \"" . $anError . "\",\n                message: data\n            });\n        },\n        always: function() {\n            subscriptionWorking.fadeOut();\n            subscription.css(\"filter\", \"alpha(opacity=100)\");\n            subscription.css(\"-moz-opacity\", \"1\");\n            subscription.css(\"-khtml-opacity\", \"1\");\n            subscription.css(\"opacity\", \"1\");\n        }\n    });\n}";
unset($cancelRoute);
unset($anError);
if ($module || $addonModule) {
    $token = generate_token("link");
    $addonRequest = "";
    if ($addonModule) {
        $addonRequest = "&aid=" . $aid;
    }
    $jscode .= "function runModuleCommand(cmd,custom,extraVars) {\n    \$('#growls').fadeOut('fast').remove();\n    \$('.successbox,.errorbox').slideUp('fast').remove();\n    // Hide the modal that was activated.\n    jQuery(\"[id^=modalModule]\").modal(\"hide\");\n    let commandButtons = jQuery('#modcmdbtns');\n    let commandWorking = jQuery('#modcmdworking');\n    \n    disableModuleCommandsButtons(commandButtons, commandWorking);\n    \n    var reqstr = \"userid=" . $userid . "&id=" . $id . $addonRequest . "&modop=\"+cmd+\"&ajax=1" . $token . "\";\n    if (custom) {\n        reqstr += \"&ac=\"+custom;\n    } else if (cmd == \"suspend\") {\n        reqstr += \"&suspreason=\"+encodeURIComponent(\$(\"#suspreason\").val())+\"&suspemail=\"+\$(\"#suspemail\").is(\":checked\");\n    } else if (cmd == \"unsuspend\") {\n        reqstr += \"&unsuspended_email=\" + jQuery(\"#unsuspended_email\").is(\":checked\");\n    } else if (cmd === \"terminate\" ) {\n        if (jQuery('#inputKeepCPanelDnsZone').length !== 0) {\n            reqstr += \"&keep_zone=\" + jQuery(\"#inputKeepCPanelDnsZone\").is(\":checked\");\n        }\n        if (jQuery('#inputInvoiceUsage').length !== 0) {\n            reqstr += \"&invoice_usage=\" + jQuery(\"#inputInvoiceUsage\").is(\":checked\");\n        }\n    } else if (cmd === \"suspend-feature\") {\n        reqstr += \"&suspreason=\"+encodeURIComponent(\$(\"#suspreason\").val());\n    }\n\n    if (extraVars) {\n        reqstr += extraVars;\n    }\n\n    WHMCS.http.jqClient.post(\"clientsservices.php\", reqstr, function(data) {\n        if (data.success && data.redirect) {\n            data = data.redirect;\n        }\n        if (data.body) {\n            data = data.body;\n        }\n\n        if (data.substr(0,9)==\"redirect|\") {\n            window.location = data.substr(9);\n        } else if (data.substr(0,7)==\"window|\") {\n            window.open(data.substr(7), '_blank');\n        } else {\n            \$(\"#servicecontent\").html(data);\n            \$('html, body').animate({\n                scrollTop: \$('.client-tabs').offset().top - 10\n            }, 500);\n        }\n    }).fail(function (xhr) {\n        var response = (xhr.responseText != '' ? xhr.responseText : xhr.statusText);\n        console.error('[WHMCS] Error: ' + response);\n    }).always(function() {\n        reenableModuleCommandsButtons(commandButtons, commandWorking);       \n    }); \n}\n\nfunction disableModuleCommandsButtons(commandButtons, commandWorking)\n{\n    commandButtons.css({\n        \"filter\": \"alpha(opacity=20)\",\n        \"-moz-opacity\": \"0.2\",\n        \"-khtml-opacity\": \"0.2\",\n        \"opacity\": \"0.2\"\n    })\n    .children('button')\n    .each((i, button) => {\n        jQuery(button).prop('disabled','disabled');\n    });\n    \n    \n    let position = commandButtons.position();\n\n    commandWorking.css({\n        \"position\": \"absolute\",\n        \"top\": position.top,\n        \"left\": position.left,\n        \"padding\": \"9px 50px 0\"\n    })\n    .fadeIn();\n}\n\nfunction reenableModuleCommandsButtons(commandButtons, commandWorking)\n{\n        commandButtons.css({\n            \"filter\": \"alpha(opacity=100)\",\n            \"-moz-opacity\": \"1\",\n            \"-khtml-opacity\": \"1\",\n            \"opacity\": \"1\"\n        })\n        .children('button')\n        .each((i, button) => {\n            jQuery(button).prop('disabled','');\n        });\n        commandWorking.fadeOut();\n}\n\nfunction submitServiceChange()\n{\n    let commandButtons = jQuery(\"#modcmdbtns\");\n    \n    if (commandButtons.children('button').length > 0){\n        let commandWorking = jQuery(\"#modcmdworking\");\n        disableModuleCommandsButtons(commandButtons, commandWorking);\n    }\n    \n    jQuery(event.target).parents('form').submit();\n}";
}
$aInt->jscode = $jscode;
echo "<div class=\"context-btn-container\">\n    <div class=\"row\">\n        <div class=\"col-sm-7 text-left\">";
$addonServices = [];
$hostingAddonCollection = WHMCS\Service\Addon::leftJoin("tbladdons", "tbladdons.id", "=", "tblhostingaddons.addonid")->where("tblhostingaddons.userid", $userid)->orderBy("name")->get(["tblhostingaddons.status", "tblhostingaddons.name as name", "tblhostingaddons.hostingid", "tblhostingaddons.id", "tbladdons.name as addonName"]);
foreach ($hostingAddonCollection as $hostingAddon) {
    switch ($hostingAddon->status) {
        case "Pending":
            $color = "#FFFFCC";
            break;
        case "Suspended":
            $color = "#CCFF99";
            break;
        case "Terminated":
        case "Cancelled":
        case "Fraud":
            $color = "#FF9999";
            break;
        case "Completed":
            $color = "#CCC";
            break;
        default:
            $color = "#FFF";
            $addonName = $hostingAddon->addonName;
            if (!$addonName) {
                $addonName = $hostingAddon->name;
            }
            $value = [$color, "- " . $addonName];
            $addonServices[$hostingAddon->serviceId]["a" . $hostingAddon->id] = $value;
    }
}
$allServices = [];
$servicesarr = [];
$result = select_query("tblhosting", "tblhosting.id,tblhosting.domain,tblproducts.name,tblhosting.domainstatus", ["userid" => $userid], "domain", "ASC", "", "tblproducts ON tblhosting.packageid=tblproducts.id");
while ($data = mysql_fetch_array($result)) {
    $servicelist_id = $data["id"];
    $servicelist_product = $data["name"];
    $servicelist_domain = $data["domain"];
    $servicelist_status = $data["domainstatus"];
    if ($servicelist_domain) {
        $servicelist_product .= " - " . $servicelist_domain;
    }
    switch ($servicelist_status) {
        case "Pending":
            $color = "#FFFFCC";
            break;
        case "Suspended":
            $color = "#CCFF99";
            break;
        case "Terminated":
        case "Cancelled":
        case "Fraud":
            $color = "#FF9999";
            break;
        case "Completed":
            $color = "#CCC";
            break;
        default:
            $color = "#FFF";
            $servicesarr[$servicelist_id] = [$color, $servicelist_product];
            $allServices[$servicelist_id] = [$color, $servicelist_product];
            if (array_key_exists($servicelist_id, $addonServices)) {
                foreach ($addonServices[$servicelist_id] as $addonServiceKey => $addonService) {
                    $allServices[$addonServiceKey] = $addonService;
                }
            }
    }
}
if ($aid && is_numeric($aid)) {
    $itemToSelect = "a" . $aid;
} else {
    $itemToSelect = $id;
}
$frmsub = new WHMCS\Form("frm2");
echo $frmsub->form("", "", "", "get", true) . $frmsub->hidden("userid", $userid) . $frmsub->dropdown("productselect", $allServices, $itemToSelect, "", "", "", "", "", "form-control selectize-select selectize-float selectize-auto-submit") . $frmsub->submit($aInt->lang("global", "go"), "btn btn-default selectize-float-btn") . $frmsub->close();
echo "</div>";
$sslOutput = "";
if (!$aid) {
    $isDomain = filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    if ($producttype == "other") {
        $isDomain = false;
    }
    if ($isDomain !== false) {
        $sslStatus = WHMCS\Domain\Ssl\Status::factory($userid, $domain);
        $html = "<img src=\"%s\"\n               class=\"%s\"\n               data-toggle=\"tooltip\"\n               title=\"%s\"\n               data-domain=\"%s\"\n               data-user-id=\"%s\"\n               >";
        $sslOutput = sprintf($html, $sslStatus->getImagePath(), $sslStatus->getClass(), $sslStatus->getTooltipContent(), $domain, $userid);
    }
}
$viewInvoicesLabel = AdminLang::trans("invoices.viewinvoices");
$upgradeLabel = AdminLang::trans("services.createupgorder");
$upgradeLink = "clientsupgrade.php?id=" . $id;
$upgradeModalTitle = AdminLang::trans("services.upgradedowngrade");
$transferLabel = AdminLang::trans("clients.transferownership");
$sendMessageLabel = AdminLang::trans("global.sendmessage");
$deleteLabel = AdminLang::trans("global.delete");
$resendWelcomeEmailBtn = "";
if ($canResendWelcomeEmail) {
    $resendWelcomeEmailBtn = "<li>\n        <a href=\"#\" id=\"btnResendWelcomeEmail\">\n            <i class=\"fas fa-star fa-fw\"></i>\n            " . AdminLang::trans("emails.senddefaultproductwelcome") . "\n        </a>\n    </li>";
}
$newAddonBtn = $deleteLink = $upgradeBtn = $transferButton = $returnToService = "";
if (!empty($aid) && is_numeric($aid)) {
    $viewInvoicesLink = "clientsinvoices.php?userid=" . $userid . "&addonid=" . $aid;
    $deleteLink = "<a href=\"#\" onclick=\"doDeleteAddon('" . $aid . "');return false\">";
    $returnToServiceString = AdminLang::trans("clientsservices.returnToService");
    $returnToService = "<a href=\"clientsservices.php?userid=" . $userid . "&id=" . $id . "\"\n    class=\"btn btn-default\">\n        <i class=\"fas fa-undo fa-fw\"></i>" . $returnToServiceString . "\n    </a>";
} else {
    $viewInvoicesLink = "clientsinvoices.php?userid=" . $userid . "&serviceid=" . $id;
    $deleteLink = "<a href=\"#\" data-toggle=\"modal\" data-target=\"#modalDelete\">";
    $newAddonBtn = "<a href=\"clientsservices.php?userid=" . $userid . "&id=" . $id . "&aid=add\" class=\"btn btn-default\"><i class=\"fas fa-plus fa-fw\"></i> New Addon</a>";
    $upgradeBtn = "<li>\n        <a href=\"" . $upgradeLink . "\" class=\"open-modal\" data-modal-title=\"" . $upgradeModalTitle . "\">\n            <i class=\"fas fa-arrow-circle-up fa-fw\"></i>\n            " . $upgradeLabel . "\n        </a>\n    </li>";
    $transferButton = "<li>\n        <a href=\"#\" onclick=\"window.open('clientsmove.php?type=hosting&id=" . $id . "','movewindow','width=500,height=500,top=100,left=100,scrollbars=yes');return false\">\n            <i class=\"fas fa-random fa-fw\"></i>\n            " . $transferLabel . "\n        </a>\n    </li>";
}
$viewInvoicesBtn = "<li>\n        <a href=\"" . $viewInvoicesLink . "\">\n            <i class=\"fas fa-file-invoice fa-fw\"></i>\n            " . $viewInvoicesLabel . "\n        </a>\n    </li>";
$loginLinkSsoOutput = "";
$ssoButton = "";
if ($serverModule->functionExists("ServiceSingleSignOn")) {
    $btnLabel = $serverModule->getMetaDataValue("ServiceSingleSignOnLabel");
    $ssoButton = sprintf("<button type=\"button\" onclick=\"runModuleCommand('%s'); return false\" class=\"btn btn-primary\"><i class=\"fas fa-sign-in fa-fw\"></i> %s</button>", "singlesignon", $btnLabel ? $btnLabel : $aInt->lang("sso", "servicelogin"));
} else {
    if ($serverModule->functionExists("LoginLink")) {
        $loginLinkSsoOutput = "<div id=\"loginLinkSsoOutput\" class=\"hidden\">" . $serverModule->call("LoginLink") . "</div>";
        $ssoButton = "<button type=\"button\" id=\"btnLoginLinkTrigger\" class=\"btn btn-primary\"><i class=\"fas fa-sign-in fa-fw\"></i> " . AdminLang::trans("sso.servicelogin") . "</button>";
    }
}
$buttonsOutput = !empty($aid) && $aid == "add" ? "" : "<div class=\"btn-group\" style=\"margin-left:10px;\">\n    " . $ssoButton . "\n    " . $newAddonBtn . "\n    " . $returnToService . "\n    <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">\n        More\n        <span class=\"caret\"></span>\n        <span class=\"sr-only\">Toggle Dropdown</span>\n    </button>\n    <ul class=\"dropdown-menu dropdown-menu-right\">\n    " . $viewInvoicesBtn . "\n    " . $upgradeBtn . "\n    " . $transferButton . "\n    <li role=\"separator\" class=\"divider\"></li>\n    <li>\n        <a href=\"#\" data-toggle=\"modal\" data-target=\"#modalSendEmail\">\n            <i class=\"fas fa-envelope fa-fw\"></i>\n            " . $sendMessageLabel . "\n        </a>\n    </li>\n    " . $resendWelcomeEmailBtn . "\n    <li role=\"separator\" class=\"divider\"></li>\n    <li>\n        " . $deleteLink . "\n            <i class=\"fas fa-trash fa-fw\"></i>\n            " . $deleteLabel . "\n        </a>\n    </li>\n  </ul>\n</div>";
echo "<div class=\"col-sm-5\">" . $sslOutput . $buttonsOutput . "\n        </div>\n    </div>\n</div>\n\n<div id=\"modcmdresult\" style=\"display:none;\"></div>\n";
if ($cancelid && !$infobox) {
    infoBox($aInt->lang("services", "cancrequest"), $aInt->lang("services", "cancrequestinfo") . "<br />" . $aInt->lang("fields", "reason") . ": " . $autoterminatereason, "info");
}
echo $infobox;
$serviceModel = WHMCS\Service\Service::find($id);
$metrics = $serviceModel->metrics(true);
foreach ($metrics as $serviceMetric) {
    if (!is_null($serviceMetric->usageItem())) {
        $units = $serviceMetric->units();
        if ($serviceMetric->systemName() == "diskusage") {
            $diskusage = $units->roundForType($serviceMetric->usage()->value() * 1024);
            $lastupdate = $serviceMetric->usage()->collectedAt()->toDateTimeString();
        } else {
            if ($serviceMetric->systemName() == "bandwidthusage") {
                $bwusage = $units->roundForType($serviceMetric->usage()->value() * 1024);
                $lastupdate = $serviceMetric->usage()->collectedAt()->toDateTimeString();
            }
        }
    }
}
if ($lastupdate && $lastupdate != "0000-00-00 00:00:00") {
    echo "<div class=\"contentbox\">\n<strong>" . $aInt->lang("services", "diskusage") . ":</strong> " . $diskusage . " " . $aInt->lang("fields", "mb") . ", <strong>" . $aInt->lang("services", "disklimit") . ":</strong> " . $disklimit . " " . $aInt->lang("fields", "mb") . ", ";
    if (!($diskusage == $aInt->lang("global", "unlimited") || $disklimit == $aInt->lang("global", "unlimited"))) {
        echo "<strong>" . round($diskusage / $disklimit * 100, 0) . "% " . $aInt->lang("services", "used") . "</strong> :: ";
    }
    echo "<strong>" . $aInt->lang("services", "bwusage") . ":</strong> " . $bwusage . " " . $aInt->lang("fields", "mb") . ", <strong>" . $aInt->lang("services", "bwlimit") . ":</strong> " . $bwlimit . " " . $aInt->lang("fields", "mb") . ", ";
    if (!($bwusage == $aInt->lang("global", "unlimited") || $bwlimit == $aInt->lang("global", "unlimited"))) {
        echo "<strong>" . round($bwusage / $bwlimit * 100, 0) . "% " . $aInt->lang("services", "used") . "</strong><br>";
    }
    echo "<small>(" . $aInt->lang("services", "lastupdated") . ": " . fromMySQLDate($lastupdate, "time") . ")</small>\n</div>\n<br />\n";
}
echo $frm->form("?userid=" . $userid . "&id=" . $id . ($aid ? "&aid=" . $aid : ""));
echo "<style>\n.bootstrap-switch.bootstrap-switch-mini .bootstrap-switch-handle-off, \n.bootstrap-switch.bootstrap-switch-mini .bootstrap-switch-handle-on,\n.bootstrap-switch.bootstrap-switch-off .bootstrap-switch-label,\n.bootstrap-switch.bootstrap-switch-on .bootstrap-switch-label, \n.bootstrap-switch.bootstrap-switch-inverse.bootstrap-switch-on .bootstrap-switch-label,\n.bootstrap-switch.bootstrap-switch-inverse.bootstrap-switch-off .bootstrap-switch-label {\n    padding: 1px 3px;\n    font-size: 10px;\n    line-height: 1.0;\n}\n.font-mouse {\n    font-size: 10px;\n    line-height: 1.0;\n}\n.line-through {\n    text-decoration-line: line-through;\n}\n.service-field-inline {\n    float: left;\n    max-width: 110px;\n    padding-right: 5px;\n}\n.service-field-inline input[type=checkbox] {\n    margin: 0;\n}\n</style>";
$txtRecalcSave = AdminLang::trans("services.autorecalc");
$txtRecalcOn = AdminLang::trans("global.yes");
$txtRecalcOff = AdminLang::trans("global.no");
$aInt->addHeadJqueryCode("    jQuery('#inputAutorecalc').on('switchChange.bootstrapSwitch',\n        function (event, state) {\n            var element = jQuery('#inputAmount, #inputRecurring');\n            element.prop('readonly', state).toggleClass('readonly').toggleClass('line-through');\n        }\n    );");
if ($aid) {
    if ($aid == "add") {
        checkPermission("Add New Order");
        $managetitle = $aInt->lang("addons", "addnew");
        $setupfee = "0.00";
        $recurring = "0.00";
        $regdate = $nextduedate = getTodaysDate();
        $notes = $customname = "";
        $addonid = 0;
        $status = "Pending";
        $billingcycle = $serviceBillingCycle ? $serviceBillingCycle : "Free Account";
        $tax = "";
        $subscriptionid = "";
        $cancelSubscription = "";
        $serversArray = [];
        $allowQuantity = 0;
        $quantity = 1;
    } else {
        $managetitle = $aInt->lang("addons", "editaddon");
        $aid = $addonDetails->id;
        $id = $addonDetails->serviceId;
        $addonid = $addonDetails->addonId;
        $customname = $addonDetails->name;
        $recurring = $addonDetails->recurringFee;
        $setupfee = $addonDetails->setupFee;
        $billingcycle = $addonDetails->billingCycle;
        $status = $addonDetails->status;
        $regdate = $addonDetails->registrationDate;
        $nextduedate = $addonDetails->nextDueDate;
        $paymentmethod = $addonDetails->paymentGateway;
        $terminationDate = $addonDetails->terminationDate;
        $allowQuantity = $addonDetails->productAddon->allowMultipleQuantities;
        $quantity = $addonDetails->qty;
        if (!$paymentmethod || !$gateways->isActiveGateway($paymentmethod)) {
            $paymentmethod = ensurePaymentMethodIsSet($userid, $aid, "tblhostingaddons");
        }
        $tax = (int) $addonDetails->applyTax;
        $subscriptionid = $addonDetails->subscriptionId;
        $notes = $addonDetails->notes;
        $server = $addonDetails->serverId;
        $regdate = fromMySQLDate($regdate);
        $nextduedate = fromMySQLDate($nextduedate);
        $terminationDate = fromMySQLDate($terminationDate);
        $moduleInterface = new WHMCS\Module\Server();
        $moduleInterface->loadByAddonId($aid);
        $serversArray = $moduleInterface->getServerListForModule();
        if (!$server && $serversArray) {
            $server = key($serversArray);
            $addonDetails->serverId = $server;
            $addonDetails->save();
        }
    }
    echo "<h2 style=\"margin:15px;\">" . $managetitle . "</h2>";
    $tbl = new WHMCS\Table();
    $tbl->add($aInt->lang("fields", "parentProduct"), $frm->hidden("oldserviceid", $id) . $frm->dropdown("id", [$id => $servicesarr[$id]], $id, "", "", "", "", "addonServiceId", "form-control selectize selectize-service-search", ["search-url" => routePath("admin-client-service-search", $userid)]), false, 2);
    $jQueryCode .= "WHMCS.selectize.serviceSearch();";
    $tbl->add(AdminLang::trans("fields.quantity"), $frm->hidden("qty", $quantity) . $frm->text("qty", $quantity, 30, $allowQuantity !== 2 && $aid !== "add", "form-control input-100", "number"));
    $tbl->add($aInt->lang("fields", "setupfee"), $frm->text("setupfee", $setupfee, "10", false, "form-control input-100"));
    $tbl->add($aInt->lang("fields", "regdate"), $frm->date("regdate", $regdate));
    $inputRecurring = $frm->text("recurring", $recurring, "10", false, "form-control input-100 input-inline");
    $checkbox = "";
    if ($aid === "add") {
        $useDefaultText = AdminLang::trans("addons.usedefault");
        $checkbox = "<div class=\"service-field-inline\">\n    <div class=\"font-mouse\">" . $useDefaultText . "</div>\n    <div>\n        <input type=\"checkbox\"\n             class=\"slide-toggle auto-recalc-checkbox\"\n             id=\"inputAutorecalc\"\n             name=\"defaultpricing\"\n             data-size=\"mini\" \n             data-on-text=\"" . $txtRecalcOn . "\" \n             data-on-color=\"info\" \n             data-off-text=\"" . $txtRecalcOff . "\"\n             checked=\"checked\"\n        >\n    </div>\n</div>";
    } else {
        if (0 < $addonid) {
            $checkbox = "<div class=\"service-field-inline\">\n    <div class=\"font-mouse\">" . $txtRecalcSave . "</div>\n    <div>\n        <input type=\"checkbox\"\n             class=\"slide-toggle auto-recalc-checkbox\"\n             id=\"inputAutorecalc\"\n             name=\"autorecalc\"\n             data-size=\"mini\" \n             data-on-text=\"" . $txtRecalcOn . "\" \n             data-on-color=\"info\" \n             data-off-text=\"" . $txtRecalcOff . "\"\n        >\n    </div>\n</div>";
        }
    }
    $controlRecalc = "<div style=\"width: 100%\">\n    <div class=\"service-field-inline\">\n    " . $inputRecurring . "\n    </div>\n    " . $checkbox;
    $tbl->add(AdminLang::trans("global.recurring"), $controlRecalc);
    $predefaddons = WHMCS\Product\Addon::getAddonDropdownValues($addonDetails->addonId ?? 0);
    $tbl->add($aInt->lang("addons", "predefinedaddon"), $frm->dropdown("addonid", $predefaddons, $addonid, "", "", true));
    $tbl->add(AdminLang::trans("fields.billingcycle"), $aInt->cyclesDropDown($billingcycle, "", "Free", "billingcycle", "", "selectBillingCycle"));
    $tbl->add($aInt->lang("addons", "customname"), $frm->text("name", $customname, "40", false, "form-control input-80percent"));
    $notAvailableSpanHtml = "<span id=\"notAvailableSpan\">" . AdminLang::trans("global.na") . "</span>";
    $tbl->add($aInt->lang("fields", "nextduedate"), $frm->date("nextduedate", $nextduedate, 12, false, "form-control date-picker-single future") . $notAvailableSpanHtml);
    $tbl->add($aInt->lang("fields", "status"), $aInt->productStatusDropDown($status));
    $tbl->add(AdminLang::trans("fields.terminationDate"), $frm->date("termination_date", strpos($terminationDate, "0000") === false ? $terminationDate : ""));
    $tbl->add($aInt->lang("fields", "paymentmethod"), paymentMethodsSelection());
    $tbl->add($aInt->lang("addons", "taxaddon"), $frm->checkbox("tax", "", $tax));
    if ($serversArray && $provisioningType !== "feature") {
        $createAddonServerOptionForNone = false;
        if ($moduleInterface->isMetaDataValueSet("RequiresServer") && !$moduleInterface->getMetaDataValue("RequiresServer")) {
            $createAddonServerOptionForNone = true;
        }
        $tbl->add(AdminLang::trans("fields.server"), $frm->dropdown("server", $serversArray, $server, "", "", $createAddonServerOptionForNone), 1);
    }
    $moduleButtons = [];
    if ($moduleInterface) {
        $moduleParams = $moduleInterface->buildParams();
        if ($provisioningType !== "feature") {
            if ($moduleInterface->functionExists("CreateAccount")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.create"), "jQuery('#modalModuleCreate').modal('show');");
            }
            if ($moduleInterface->functionExists("Renew")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.renew"), "jQuery('#modalModuleRenew').modal('show');");
            }
            if ($moduleInterface->functionExists("SuspendAccount")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.suspend"), "jQuery('#modalModuleSuspend').modal('show');");
            }
            if ($moduleInterface->functionExists("UnsuspendAccount")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.unsuspend"), "jQuery('#modalModuleUnsuspend').modal('show');");
            }
            if ($moduleInterface->functionExists("TerminateAccount")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.terminate"), "jQuery('#modalModuleTerminate').modal('show');");
            }
            if ($moduleInterface->functionExists("ChangePackage")) {
                $moduleButtons[] = $frm->button($moduleInterface->getMetaDataValue("ChangePackageLabel") ?: AdminLang::trans("modulebuttons.changepackage"), "jQuery('#modalModuleChangePackage').modal('show');");
            }
            if ($moduleInterface->functionExists("ChangePassword")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.changepassword"), "runModuleCommand('changepw')");
            }
            if ($moduleInterface->functionExists("ServiceSingleSignOn")) {
                $btnLabel = $moduleInterface->getMetaDataValue("ServiceSingleSignOnLabel");
                if (!$btnLabel) {
                    $btnLabel = AdminLang::trans("sso.servicelogin");
                }
                $moduleButtons[] = $frm->button($btnLabel, "runModuleCommand('singlesignon')");
            }
            if ($moduleInterface->isApplicationLinkingEnabled() && $moduleInterface->isApplicationLinkSupported()) {
                $moduleButtons[] = $frm->modalButton(AdminLang::trans("modulebuttons.manageAppLinks"), "modalmanageAppLinks");
            }
            $adminButtonArray = [];
            if ($moduleInterface->functionExists("AdminCustomButtonArray")) {
                $adminButtonArray = $moduleInterface->call("AdminCustomButtonArray", $moduleParams);
            }
        } else {
            if ($moduleInterface->functionExists("ProvisionAddOnFeature")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.provision"), "jQuery('#modalModuleProvisionAddOnFeature').modal('show');");
            }
            if ($moduleInterface->functionExists("SuspendAddOnFeature")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.suspend"), "jQuery('#modalModuleSuspendAddOnFeature').modal('show');");
            }
            if ($moduleInterface->functionExists("UnsuspendAddOnFeature")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.unsuspend"), "jQuery('#modalModuleUnsuspendAddOnFeature').modal('show');");
            }
            if ($moduleInterface->functionExists("DeprovisionAddOnFeature")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.deprovision"), "jQuery('#modalModuleDeprovisionAddOnFeature').modal('show');");
            }
            if ($moduleInterface->functionExists("AddOnFeatureSingleSignOn")) {
                $moduleButtons[] = $frm->button(AdminLang::trans("modulebuttons.login"), "runModuleCommand('singlesignon-feature')");
            }
        }
        $moduleButtons = buildcustommodulebuttons($moduleButtons, $adminButtonArray);
        if ($moduleButtons) {
            $tbl->add(AdminLang::trans("services.modulecommands"), "<div id=\"modcmdbtns\">" . implode(" ", $moduleButtons) . "</div><div id=\"modcmdworking\" style=\"display:none;text-align:center;\"><img src=\"images/loader.gif\" /> &nbsp; Working...</div>", 1);
        }
        if ($moduleInterface->functionExists("AdminServicesTabFields")) {
            if (!$moduleParams) {
                $moduleParams = $moduleInterface->buildParams();
            }
            $fieldsArray = $moduleInterface->call("AdminServicesTabFields", $moduleParams);
            if ($adminServicesTabFieldsSaveErrors = WHMCS\Session::getAndDelete("adminServicesTabFieldsSaveErrors")) {
                $tbl->add(AdminLang::trans("global.error"), $adminServicesTabFieldsSaveErrors, 1);
            }
            if (is_array($fieldsArray)) {
                foreach ($fieldsArray as $k => $v) {
                    $tbl->add($k, $v, 1);
                }
            }
        }
    }
    if ($addonid) {
        $customFields = getCustomFields("addon", $addonid, $aid, true);
        foreach ($customFields as $customField) {
            $tbl->add($customField["name"], $customField["input"], 1);
        }
    }
    $tbl->add(AdminLang::trans("fields.subscriptionid"), $makeAddonSubscriptionManagementHtml($aid, $subscriptionid, $paymentmethod), true);
    $tbl->add($aInt->lang("fields", "adminnotes"), $frm->textarea("notes", $notes, "4", "100%"), 1);
    echo $tbl->output();
    if ($aid == "add") {
        echo "<div class=\"text-center\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"geninvoice\" id=\"geninvoice\" checked /> " . $aInt->lang("addons", "geninvoice") . "</label></div>";
    }
    echo "<div class=\"btn-container\">" . $frm->submit($aInt->lang("global", "savechanges"), "btn btn-primary") . " " . $frm->button($aInt->lang("global", "cancel"), "window.location='?userid=" . $userid . "&id=" . $id . "'") . "</div>";
} else {
    $moduleInterface = new WHMCS\Module\Server();
    $moduleInterface->loadByServiceID($id);
    $moduleParams = $moduleInterface->buildParams();
    $serversarr = $moduleInterface->getServerListForModule();
    if (empty($serversarr)) {
        $createServerOptionForNone = true;
    }
    $infoLabel = "";
    if ($domain !== $domainPunycode) {
        $infoLabel .= " <span class=\"label label-info\">" . AdminLang::trans("global.idnDomain") . "</span>";
    }
    $tbl = new WHMCS\Table();
    $tbl->add($aInt->lang("fields", "ordernum"), $orderid . " - <a href=\"orders.php?action=view&id=" . $orderid . "\">" . $aInt->lang("orders", "vieworder") . "</a>" . $infoLabel);
    $tbl->add($aInt->lang("fields", "regdate"), $frm->date("regdate", $regdate));
    $tbl->add($aInt->lang("fields", "product"), $frm->hidden("oldpackageid", $packageid) . $frm->dropdown("packageid", $aInt->productDropDown($packageid), "", "submitServiceChange()", "", "", "", "", "form-control select-inline-long"), false, 2);
    $tbl->add(AdminLang::trans("fields.quantity"), $frm->hidden("qty", $quantity) . $frm->text("qty", $quantity, 30, $allowQuantity !== 2, "form-control input-100", "number"));
    $tbl->add($aInt->lang("fields", "firstpaymentamount"), $frm->text("firstpaymentamount", $firstpaymentamount, "20", false, "form-control input-100"));
    $tbl->add($aInt->lang("fields", "server"), $frm->dropdown("server", $serversarr, $server, "submitServiceChange()", "", $createServerOptionForNone));
    $inputRecurring = $frm->text("amount", $amount, "20", false, "form-control input-100");
    $controlRecalc = "<div style=\"width: 100%\">\n    <div class=\"service-field-inline\">\n    " . $inputRecurring . "\n    </div>\n    <div class=\"service-field-inline\">\n        <div class=\"font-mouse\">" . $txtRecalcSave . "</div>\n        <div>\n            <input type=\"checkbox\"\n                 class=\"slide-toggle auto-recalc-checkbox\"\n                 id=\"inputAutorecalc\"\n                 name=\"autorecalc\"\n                 data-size=\"mini\" \n                 data-on-text=\"" . $txtRecalcOn . "\" \n                 data-on-color=\"info\" \n                 data-off-text=\"" . $txtRecalcOff . "\"\n             />\n        </div>\n    </div>";
    $tbl->add($aInt->lang("fields", "recurringamount"), $controlRecalc);
    $extra = "";
    if ($domainPunycode && $domain !== $domainPunycode) {
        $extra = "<input type=\"text\" value=\"" . $domainPunycode . "\"" . " class=\"form-control input-300 domain-input disabled\" readonly>";
    }
    $tbl->add($producttype == "server" ? $aInt->lang("fields", "hostname") : $aInt->lang("fields", "domain"), "<div class=\"input-group input-300\">\n        <input type=\"text\" name=\"domain\" value=\"" . $domain . "\" class=\"form-control domain-input\">\n        <div class=\"input-group-btn\">\n            <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\" style=\"margin-left:-3px;\">\n                <span class=\"caret\"></span>\n            </button>\n            <ul class=\"dropdown-menu dropdown-menu-right\">\n                <li><a href=\"https://" . $domain . "\" target=\"_blank\">www</a></li>\n                <li><a href=\"#\" onclick=\"\$('#frmWhois').submit();return false\">" . $aInt->lang("domains", "whois") . "</a></li>\n                <li><a href=\"https://www.intodns.com/" . $domain . "\" target=\"_blank\">intoDNS</a></li>\n            </ul>\n        </div>\n    </div>" . $extra);
    $notAvailableSpanHtml = "<span id=\"notAvailableSpan\">" . AdminLang::trans("global.na") . "</span>";
    $tbl->add($aInt->lang("fields", "nextduedate"), $frm->hidden("oldnextduedate", $nextduedate) . $frm->date("nextduedate", $nextduedate, 12, false, "form-control date-picker-single future") . $notAvailableSpanHtml);
    $tbl->add($aInt->lang("fields", "dedicatedip"), $frm->text("dedicatedip", $dedicatedip, "25", false, "form-control input-200"));
    $tbl->add($aInt->lang("fields", "terminationDate"), $frm->date("termination_date", strpos($terminationDate, "0000") === false ? $terminationDate : ""));
    $usernameOutput = $frm->text("username", $username, "20", false, "form-control input-200 input-inline");
    $tbl->add($aInt->lang("fields", "username"), $usernameOutput);
    $tbl->add($aInt->lang("fields", "billingcycle"), $aInt->cyclesDropDown($billingcycle, "", "", "billingcycle", "", "selectBillingCycle"));
    $tbl->add($aInt->lang("fields", "password"), $frm->text("password", $password, "20", false, "form-control input-200"));
    $tbl->add($aInt->lang("fields", "paymentmethod"), paymentMethodsSelection());
    $statusExtra = "";
    if ($domainstatus == "Suspended") {
        $statusExtra = " (" . AdminLang::trans("services.suspendreason") . ": " . (!$suspendreason ? Lang::trans("suspendreasonoverdue") : $suspendreason) . ")";
    } else {
        if ($domainstatus == "Completed") {
            $statusExtra = $completedDate != "0000-00-00" ? " (" . fromMySQLDate($completedDate) . ")" : "";
        }
    }
    $tbl->add($aInt->lang("fields", "status"), $aInt->productStatusDropDown($domainstatus, false, "domainstatus", "prodstatus") . $statusExtra);
    $recurLimit = $recurCountString = "";
    if (0 < $promoid) {
        $recurPromo = WHMCS\Database\Capsule::table("tblpromotions")->where("id", $promoid)->first();
        if ($recurPromo && !is_null($promocount)) {
            $recurLimit = 0 < $recurPromo->recurfor ? "/" . $recurPromo->recurfor : "";
            $recurCountString = $recurPromo->recurring ? " (" . AdminLang::trans("services.recurCount") . ": " . $promocount . $recurLimit . ")" : "";
        }
    }
    $promoJs = "var otherPromos = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("promos.allpromos")) . "';";
    $aInt->addHeadJsCode($promoJs);
    $promoData = preparePromotionDataForSelection(WHMCS\Product\Promotion::getApplicableToObject($serviceModel), $promoid, false);
    $fieldData = "<div id=\"nonApplicablePromoWarning\" class=\"alert alert-info text-center\" style=\"display: none;\">" . AdminLang::trans("promos.nonApplicableSelected") . "</div>" . "<div style=\"max-width:300px\" class=\"form-field-width-container\">" . $frm->dropdownWithOptGroups("promoid", $promoData, $promoid, "", "", true, 1, "promoid", "form-control select-inline selectize-promo") . "</div>";
    $tbl->add($aInt->lang("fields", "promocode") . " <a href=\"#\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . $aInt->lang("services", "noaffect") . "\"><i class=\"fa fa-info-circle\"></i></a>" . "<br />" . $recurCountString, $fieldData);
    if ($producttype == "server") {
        $tbl->add($aInt->lang("fields", "assignedips"), $frm->textarea("assignedips", $assignedips, "4", "30"), 1);
        $tbl->add($aInt->lang("fields", "nameserver") . " 1", $frm->text("ns1", $ns1, "35", false, "form-control input-500"), 1);
        $tbl->add($aInt->lang("fields", "nameserver") . " 2", $frm->text("ns2", $ns2, "35", false, "form-control input-500"), 1);
    }
    $configoptions = [];
    $configoptions = getCartConfigOptions($packageid, "", $billingcycle, $id);
    if ($configoptions) {
        foreach ($configoptions as $configoption) {
            $optionid = $configoption["id"];
            $optionhidden = $configoption["hidden"];
            $optionname = $optionhidden ? $configoption["optionname"] . " <i>(" . $aInt->lang("global", "hidden") . ")</i>" : $configoption["optionname"];
            $optiontype = $configoption["optiontype"];
            $selectedvalue = $configoption["selectedvalue"];
            $selectedqty = $configoption["selectedqty"];
            if ($optiontype == "1") {
                $inputcode = "<select name=\"configoption[" . $optionid . "]\" class=\"form-control select-inline\">";
                foreach ($configoption["options"] as $option) {
                    $inputcode .= "<option value=\"" . $option["id"] . "\"";
                    if ($option["hidden"]) {
                        $inputcode .= " style='color:#ccc;'";
                    }
                    if ($selectedvalue == $option["id"]) {
                        $inputcode .= " selected";
                    }
                    $inputcode .= ">" . $option["name"] . "</option>";
                }
                $inputcode .= "</select>";
            } else {
                if ($optiontype == "2") {
                    $inputcode = "";
                    foreach ($configoption["options"] as $option) {
                        $inputcode .= "<label class=\"radio-inline\"><input type=\"radio\" name=\"configoption[" . $optionid . "]\" value=\"" . $option["id"] . "\"";
                        if ($selectedvalue == $option["id"]) {
                            $inputcode .= " checked";
                        }
                        if ($option["hidden"]) {
                            $inputcode .= "> <span style='color:#ccc;'>" . $option["name"] . "</span></label><br />";
                        } else {
                            $inputcode .= "> " . $option["name"] . "</label><br />";
                        }
                    }
                } else {
                    if ($optiontype == "3") {
                        $inputcode = "<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"configoption[" . $optionid . "]\" value=\"1\"";
                        if ($selectedqty) {
                            $inputcode .= " checked";
                        }
                        $inputcode .= "> " . $configoption["options"][0]["name"] . "</label>";
                    } else {
                        if ($optiontype == "4") {
                            $inputcode = "<input type=\"text\" name=\"configoption[" . $optionid . "]\" value=\"" . $selectedqty . "\" class=\"form-control input-50 input-inline\"> x " . $configoption["options"][0]["name"];
                        }
                    }
                }
            }
            $tbl->add($optionname, $inputcode, 1);
        }
    }
    if ($module) {
        $modulebtns = [];
        if ($moduleInterface->functionExists("CreateAccount")) {
            $modulebtns[] = $frm->button($aInt->lang("modulebuttons", "create"), "jQuery('#modalModuleCreate').modal('show');");
        }
        if ($moduleInterface->functionExists("Renew")) {
            $modulebtns[] = $frm->button(AdminLang::trans("modulebuttons.renew"), "jQuery('#modalModuleRenew').modal('show');");
        }
        if ($moduleInterface->functionExists("SuspendAccount")) {
            $modulebtns[] = $frm->button($aInt->lang("modulebuttons", "suspend"), "jQuery('#modalModuleSuspend').modal('show');");
        }
        if ($moduleInterface->functionExists("UnsuspendAccount")) {
            $modulebtns[] = $frm->button($aInt->lang("modulebuttons", "unsuspend"), "jQuery('#modalModuleUnsuspend').modal('show');");
        }
        if ($moduleInterface->functionExists("TerminateAccount")) {
            $modulebtns[] = $frm->button($aInt->lang("modulebuttons", "terminate"), "jQuery('#modalModuleTerminate').modal('show');");
        }
        if ($moduleInterface->functionExists("ChangePackage")) {
            $modulebtns[] = $frm->button($moduleInterface->getMetaDataValue("ChangePackageLabel") ?: AdminLang::trans("modulebuttons.changepackage"), "jQuery('#modalModuleChangePackage').modal('show');");
        }
        if ($moduleInterface->functionExists("ChangePassword")) {
            $modulebtns[] = $frm->button($aInt->lang("modulebuttons", "changepassword"), "runModuleCommand('changepw')");
        }
        if ($moduleInterface->isApplicationLinkingEnabled() && $moduleInterface->isApplicationLinkSupported()) {
            $modulebtns[] = $frm->modalButton($aInt->lang("modulebuttons", "manageAppLinks"), "modalmanageAppLinks");
        }
        $modulebtns = buildcustommodulebuttons($modulebtns, $adminbuttonarray);
        $tbl->add($aInt->lang("services", "modulecommands"), "<div id=\"modcmdbtns\">" . implode(" ", $modulebtns) . "</div><div id=\"modcmdworking\" style=\"display:none;text-align:center;\"><img src=\"images/loader.gif\" /> &nbsp; Working...</div>", 1);
        if ($moduleInterface->functionExists("AdminServicesTabFields")) {
            if ($adminServicesTabFieldsSaveErrors = WHMCS\Session::getAndDelete("adminServicesTabFieldsSaveErrors")) {
                $tbl->add(AdminLang::trans("global.error"), $adminServicesTabFieldsSaveErrors, 1);
            }
            $fieldsArray = $moduleInterface->call("AdminServicesTabFields", $moduleParams);
            if ($fieldsArray && is_array($fieldsArray)) {
                foreach ($fieldsArray as $fieldName => $fieldValue) {
                    $tbl->add($fieldName, $fieldValue, 1);
                }
            }
        }
        if (WHMCS\UsageBilling\MetricUsageSettings::isCollectionEnable() && $serviceModel->getMetricProvider()) {
            $helper = new WHMCS\UsageBilling\Service\ViewHelper();
            $table = $helper->serverTenantUsageTable($serviceModel->metrics());
            $html = "<div id=\"containerStats\">\n    " . $table . "\n</div>\n<div class=\"text-right\">\n    <button type=\"button\" id=\"btnRefreshStats\" class=\"btn btn-xs btn-default\">\n        <i class=\"fas fa-sync\"></i>\n        Refresh Now\n    </button>\n</div>";
            $tbl->add("Metric Statistics", $html, 1);
            $jQueryCode .= "\njQuery('#btnRefreshStats').on('click', function (e) {\n   e.preventDefault();\n   var \$btnTarget = \$(this);\n   \$btnTarget.find('i').addClass('fa-spin');\n   WHMCS.http.jqClient.jsonPost({\n        url: \"clientsservices.php\",\n        data: {\n            action: 'refreshStats',\n            userid: '" . $userid . "',\n            id: '" . $id . "'\n        },\n        success: function(data) {\n            if (data.success) {\n                jQuery(\"#containerStats\").html(data.body);\n            }\n        },\n        error: function (data) {\n            swal({\n                title: '" . $anError . "',\n                text: data,\n                type: 'error',\n            });\n        },\n        always: function() {\n            \$btnTarget.find('i').removeClass('fa-spin');\n        }\n    });\n});";
        }
    }
    $hookret = run_hook("AdminClientServicesTabFields", ["id" => $id]);
    foreach ($hookret as $hookdat) {
        foreach ($hookdat as $k => $v) {
            $tbl->add($k, $v, 1);
        }
    }
    $addonshtml = "";
    $aInt->sortableTableInit("nopagination");
    $service = new WHMCS\Service($id);
    $addons = $service->getAddons();
    foreach ($addons as $vals) {
        $tabledata[] = [$vals["regdate"], $vals["name"], $vals["pricing"], $vals["status"], $vals["nextduedate"], "<a href=\"?userid=" . $userid . "&id=" . $id . "&aid=" . $vals["id"] . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Edit\"></a>", "<a href=\"#\" onClick=\"doDeleteAddon('" . $vals["id"] . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Delete\"></a>"];
    }
    $addonshtml = "<div class=\"addons-service-table\">" . $aInt->sortableTable([$aInt->lang("addons", "regdate"), $aInt->lang("addons", "name"), $aInt->lang("global", "pricing"), $aInt->lang("fields", "status"), $aInt->lang("fields", "nextduedate"), "", ""], $tabledata) . "</div>";
    $tbl->add($aInt->lang("addons", "title"), $addonshtml, 1);
    $customfields = getCustomFields("product", $packageid, $id, true);
    foreach ($customfields as $customfield) {
        $tbl->add($customfield["name"], $customfield["input"], 1);
    }
    $tbl->add($aInt->lang("fields", "subscriptionid"), $makeServiceSubscriptionManagementHtml($id, $subscriptionid, $paymentmethod), true);
    $suspendValue = strpos($overidesuspenduntil, "0000") === false ? $overidesuspenduntil : "";
    $checkbox = $frm->checkbox("overideautosuspend", $aInt->lang("services", "nosuspenduntil"), $overideautosuspend) . " &nbsp;";
    $tbl->add(AdminLang::trans("services.overrideautosusp"), "<div class=\"form-group date-picker-prepend-icon\">\n    " . $checkbox . "\n    <label for=\"inputOverideSuspendUntil\" class=\"field-icon\">\n        <i class=\"fal fa-calendar-alt\"></i>\n    </label>\n    <input type=\"text\"\n           name=\"overidesuspenduntil\"\n           value=\"" . $suspendValue . "\"\n           class=\"form-control input-inline date-picker-single future\"\n           id=\"inputOverideSuspendUntil\"\n    >\n</div>", 1);
    $tbl->add($aInt->lang("services", "endofcycle"), $frm->checkbox("autoterminateendcycle", $aInt->lang("services", "reason"), $autoterminateendcycle) . " " . $frm->text("autoterminatereason", $autoterminatereason, "60", false, "form-control input-inline input-400"), 1);
    $tbl->add($aInt->lang("fields", "adminnotes"), $frm->textarea("notes", $notes, "4", "100%", "form-control"), 1);
    echo $tbl->output();
    echo "<div class=\"btn-container\">\n    " . $frm->submit($aInt->lang("global", "savechanges"), "btn btn-primary") . "\n    " . $frm->reset($aInt->lang("global", "cancelchanges")) . "\n</div>";
    if ($moduleInterface->isApplicationLinkingEnabled() && $moduleInterface->isApplicationLinkSupported()) {
        $message = "<p>" . $aInt->lang("services", "manageAppLinks") . "</p>\n        <p class=\"text-center margin-top-bottom-20\">\n            <button type=\"button\" id=\"manageAppLinks-Create\" name=\"Create\" class=\"manageAppLinks btn btn-default\">\n                " . $aInt->lang("modulebuttons", "create") . "\n            </button>";
        if ($moduleInterface->functionExists("UpdateApplicationLink")) {
            $message .= "\n            <button type=\"button\" id=\"manageAppLinks-Update\" name=\"Update\" class=\"manageAppLinks btn btn-default\">\n                " . $aInt->lang("modulebuttons", "update") . "\n            </button>";
        }
        $message .= "\n            <button type=\"button\" id=\"manageAppLinks-Delete\" name=\"Delete\" class=\"manageAppLinks btn btn-default\">\n                " . $aInt->lang("global", "delete") . "\n            </button>\n        </p>\n        <div id=\"modalAjaxOutput\" class=\"alert alert-info hidden text-center\">\n            <i class=\"fas fa-spinner fa-spin\"></i> Communicating with server. Please wait...\n        </div>\n";
        echo $aInt->modal("manageAppLinks", $aInt->lang("modulebuttons", "manageAppLinks"), $message, [["title" => $aInt->lang("global", "cancel")]]);
        $jQueryCode .= "\n        jQuery(\".manageAppLinks\").click(function() {\n            jQuery(\"#modalAjaxOutput\").addClass(\"alert-info\").removeClass(\"alert-success\").removeClass(\"alert-danger\").html(\"<i class=\\\"fas fa-spinner fa-spin\\\"></i> Communicating with server. Please wait...\");\n            if (!jQuery(\"#modalAjaxOutput\").is(\":visible\")) {\n                jQuery(\"#modalAjaxOutput\").hide().removeClass(\"hidden\").slideDown();\n            }\n\n            var appLinkAction = jQuery(this).attr(\"name\");\n\n            // Prevent the cancel buttons from\n            // affecting the modal's content.\n            if (appLinkAction !== undefined) {\n                WHMCS.http.jqClient.post(\n                    \"clientsservices.php\",\n                    {\n                        modop: \"manageapplinks\",\n                        command: appLinkAction,\n                        id: \"" . (int) $id . "\",\n                        token: \"" . generate_token("plain") . "\"\n                    },\n                    function(data) {\n                        if (data.success) {\n                            jQuery(\"#modalAjaxOutput\").addClass(\"alert-success\").removeClass(\"alert-info\").removeClass(\"alert-danger\").html(\"Action Completed Successfully!\");\n                        } else {\n                            jQuery(\"#modalAjaxOutput\").addClass(\"alert-danger\").removeClass(\"alert-info\").removeClass(\"alert-success\").html(\"Error: \" + data.errorMsg);\n                        }\n\n                    },\n                    \"json\"\n                );\n            }\n        })\n    ";
    }
}
echo $frm->close() . "\n\n<form method=\"post\" action=\"whois.php\" target=\"_blank\" id=\"frmWhois\">\n<input type=\"hidden\" name=\"domain\" value=\"" . $domain . "\" />\n</form>\n";
$content = ob_get_contents();
ob_end_clean();
$modSuspendMessage = "";
if ($whmcs->get_req_var("ajaxupdate")) {
    $content = preg_replace("/(<form\\W[^>]*\\bmethod=('|\"|)POST('|\"|)\\b[^>]*>)/i", "\\1\n" . generate_token(), $content);
    $jQueryCodeContent = "<script>\n    jQuery(document).ready(function() {\n        jQuery('[data-toggle=\"tooltip\"]').tooltip();\n        initDateRangePicker();\n    });\n</script>";
    $aInt->jsonResponse(["body" => $jQueryCodeContent . $content]);
} else {
    $modSuspendMessage = $aInt->lang("services", "suspendsure") . "<br />\n<div class=\"margin-top-bottom-20 text-center\">\n    " . $aInt->lang("services", "suspensionreason") . ":\n    <input type=\"text\" id=\"suspreason\" class=\"form-control input-inline input-300\" /><br /><br />\n    <label class=\"checkbox-inline\">\n        <input type=\"checkbox\" id=\"suspemail\" />\n        " . $aInt->lang("services", "suspendsendemail") . "\n    </label>\n</div>";
    $unsuspendSure = AdminLang::trans("services.unsuspendsure");
    $unsuspendEmail = AdminLang::trans("automation.sendAutoUnsuspendEmail");
    $modUnsuspendMessage = $unsuspendSure . "<br />\n<div class=\"margin-top-bottom-20 text-center\">\n    <label class=\"checkbox-inline\">\n        <input type=\"checkbox\" id=\"unsuspended_email\" />\n        " . $unsuspendEmail . "\n    </label>\n</div>";
    $content = "<div id=\"servicecontent\">" . $content . "</div>";
    if ($moduleInterface) {
        if ($provisioningType !== "feature") {
            if ($moduleInterface->functionExists("CreateAccount")) {
                $content .= $aInt->modal("ModuleCreate", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.createsure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"create\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("Renew")) {
                $content .= $aInt->modal("ModuleRenew", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.renewSure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"renew\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("SuspendAccount")) {
                $content .= $aInt->modal("ModuleSuspend", AdminLang::trans("services.confirmcommand"), $modSuspendMessage, [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"suspend\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("UnsuspendAccount")) {
                $content .= $aInt->modal("ModuleUnsuspend", AdminLang::trans("services.confirmcommand"), $modUnsuspendMessage, [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"unsuspend\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("TerminateAccount")) {
                $additional = "";
                if ($moduleInterface->getLoadedModule() == "cpanel") {
                    $href = "https://docs.whmcs.com/CPanel/WHM#Keep_DNS_Zone_on_Termination";
                    $keep = AdminLang::trans("services.keepDnsZone") . " " . "(<a href='" . $href . "' class='autoLinked'>" . AdminLang::trans("global.learnMore") . "</a>)";
                    $additional .= "<br>\n<br>\n<label class=\"checkbox-inline\" for=\"inputKeepCPanelDnsZone\">\n    <input type=\"checkbox\" class=\"checkbox-inline\" id=\"inputKeepCPanelDnsZone\">\n    " . $keep . "\n</label>";
                }
                $serviceUsage = new WHMCS\UsageBilling\Invoice\ServiceUsage($id);
                if ($serviceUsage->hasUsageForInvoicing()) {
                    $href = "https://docs.whmcs.com/Usage_Billing#Invoicing_and_Billing";
                    $labelText = AdminLang::trans("services.invoiceUsage") . " " . "(<a href='" . $href . "' class='autoLinked'>" . AdminLang::trans("global.learnMore") . "</a>)";
                    $additional .= "<br>\n<br>\n<label class=\"checkbox-inline\" for=\"inputInvoiceUsage\">\n    <input type=\"checkbox\" class=\"checkbox-inline\" id=\"inputInvoiceUsage\" checked=\"checked\">\n    " . $labelText . "\n</label>";
                }
                $content .= $aInt->modal("ModuleTerminate", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.terminatesure") . $additional, [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"terminate\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("ChangePackage")) {
                $content .= $aInt->modal("ModuleChangePackage", AdminLang::trans("service.confirmcommand"), AdminLang::trans("services.chgpacksure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"changepackage\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
        } else {
            if ($moduleInterface->functionExists("ProvisionAddOnFeature")) {
                $content .= $aInt->modal("ModuleProvisionAddOnFeature", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.provisionSure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"provision\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("SuspendAddOnFeature")) {
                $content .= $aInt->modal("ModuleSuspendAddOnFeature", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.suspendAddOnFeatureSure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"suspend-feature\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("UnsuspendAddOnFeature")) {
                $content .= $aInt->modal("ModuleUnsuspendAddOnFeature", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.unsuspendAddOnFeatureSure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"unsuspend-feature\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
            if ($moduleInterface->functionExists("DeprovisionAddOnFeature")) {
                $content .= $aInt->modal("ModuleDeprovisionAddOnFeature", AdminLang::trans("services.confirmcommand"), AdminLang::trans("services.deprovisionSure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "runModuleCommand(\"deprovision\")", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
            }
        }
    }
    $content .= $aInt->modal("Delete", AdminLang::trans("services.deleteproduct"), AdminLang::trans("services.proddeletesure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "window.location=\"?userid=" . $userid . "&id=" . $id . "&action=delete" . generate_token("link") . "\"", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
    $content .= $aInt->modal("CancelSubscription", AdminLang::trans("services.cancelSubscription"), AdminLang::trans("services.cancelSubscriptionSure"), [["title" => AdminLang::trans("global.yes"), "onclick" => "cancelSubscription()", "class" => "btn-primary"], ["title" => AdminLang::trans("global.no")]]);
}
$emailModalContent = $frmsub->hidden("action", "send") . $frmsub->hidden("type", "product") . $frmsub->hidden("id", $id);
if (App::isInRequest("aid")) {
    $emailModalContent .= $frmsub->hidden("aid", App::getFromRequest("aid"));
}
$emailarr = [];
$emailarr[0] = $aInt->lang("emails", "newmessage");
$mailTemplates = WHMCS\Mail\Template::where("type", "=", "product")->where("language", "=", "")->orderBy("name")->get();
foreach ($mailTemplates as $template) {
    $emailarr[$template->id] = $template->custom ? ["#efefef", $template->name] : $template->name;
}
$emailModalContent .= AdminLang::trans("global.chooseMessage") . ":<br>" . $frmsub->dropdown("messageID", $emailarr, "", "", "", "", "1", "", "form-control");
$frmsub = new WHMCS\Form("frmSendEmail");
$content .= $frmsub->form("clientsemails.php?userid=" . $userid) . $aInt->modal("SendEmail", AdminLang::trans("global.sendmessage"), $emailModalContent, [["title" => AdminLang::trans("global.cancel")], ["type" => "submit", "title" => AdminLang::trans("global.sendmessage"), "class" => "btn-primary", "onclick" => ""]]) . $frmsub->close();
if ($canResendWelcomeEmail) {
    $frmsub = new WHMCS\Form("frmResendWelcomeEmail");
    $content .= $frmsub->form("clientsemails.php?userid=" . $userid);
    $content .= $frmsub->hidden("action", "send");
    $content .= $frmsub->hidden("type", "product");
    $content .= $frmsub->hidden("id", $id);
    if (!empty($aid)) {
        $content .= $frmsub->hidden("aid", $aid);
    }
    $content .= $frmsub->hidden("messageID", $welcomeEmail);
    $content .= $frmsub->hidden("messagename", "defaultnewacc");
    $content .= $frmsub->close();
    $jQueryCode .= "\njQuery(document).on('click', '#btnResendWelcomeEmail', function(e) {\n    e.preventDefault();\n    \$('#frmResendWelcomeEmail').submit();\n});";
}
if ($loginLinkSsoOutput) {
    $content .= $loginLinkSsoOutput;
    $jQueryCode .= "\$('#btnLoginLinkTrigger').click(function(e) {\n    e.preventDefault();\n    window.open(\$(loginLinkSsoOutput).find('a').attr('href'));\n});";
}
$jQueryCode .= "function toggleNextDueDate(billingCycle) {\n    var nextDueDateField = jQuery('#inputNextduedate'),\n        nonRecurringCycles = ['Free Account', 'One Time'],\n        notAvailableSpan = jQuery('#notAvailableSpan');\n    if (nonRecurringCycles.includes(billingCycle)) {\n        nextDueDateField.closest('div').hide();\n        notAvailableSpan.show();\n    } else {\n        nextDueDateField.closest('div').show();\n        notAvailableSpan.hide();\n    }\n}\nvar billingCycleField = jQuery('#selectBillingCycle'),\n    previousValue = billingCycleField.val();\ntoggleNextDueDate(previousValue);\nbillingCycleField.change(function() {\n    var targetDatePicker = jQuery('#inputNextduedate').data('daterangepicker'),\n        nonRecurringCycles = ['Free Account', 'One Time'],\n        newValue = this.value;\n    if (!nonRecurringCycles.includes(newValue) && nonRecurringCycles.includes(previousValue)) {\n        targetDatePicker.setStartDate(moment());\n        targetDatePicker.setEndDate(moment());\n        targetDatePicker.clickApply();\n    }\n    toggleNextDueDate(newValue);\n    previousValue = newValue;\n});";
$aInt->jquerycode = $jQueryCode;
$content .= "\n<style>\n.addons-service-table .tablebg {\n    margin: 0;\n}\n.addons-service-table table {\n    margin: 0;\n}\n</style>\n";
$aInt->content = $content;
$aInt->display();
function buildCustomModuleButtons($modulebtns, $adminbuttonarray)
{
    global $frm;
    global $id;
    global $userid;
    global $aid;
    if ($adminbuttonarray) {
        foreach ($adminbuttonarray as $displayLabel => $options) {
            if (is_array($options)) {
                $href = isset($options["href"]) ? $options["href"] : "?userid=" . $userid . "&id=" . $id;
                if ($aid) {
                    $href .= "&aid=" . $aid;
                }
                if (isset($options["customModuleAction"]) && $options["customModuleAction"]) {
                    $href .= "&modop=custom&ac=" . $options["customModuleAction"] . "&token=" . generate_token("plain");
                }
                $submitLabel = isset($options["submitLabel"]) ? $options["submitLabel"] : "";
                $submitId = isset($options["submitId"]) ? $options["submitId"] : "";
                $modalClass = isset($options["modalClass"]) ? $options["modalClass"] : "";
                $modalSize = isset($options["modalSize"]) ? $options["modalSize"] : "";
                $disabled = isset($options["disabled"]) && $options["disabled"] ? " disabled=\"disabled\"" : "";
                if ($disabled && isset($options["disabledTooltip"]) && $options["disabledTooltip"]) {
                    $disabled .= " data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . $options["disabledTooltip"] . "\"";
                }
                if (isset($options["modal"]) && $options["modal"] === true) {
                    $modulebtns[] = "<a href=\"" . $href . "\" class=\"btn btn-default open-modal\" data-modal-title=\"" . $options["modalTitle"] . "\" data-modal-size=\"" . $modalSize . "\" data-modal-class=\"" . $modalClass . "\"" . $disabled . ($submitLabel ? " data-btn-submit-label=\"" . $submitLabel . "\" data-btn-submit-id=\"" . $submitId . "\"" : "") . ">" . $displayLabel . "</a>";
                } else {
                    $modulebtns[] = "<a href=\"" . $href . "\" class=\"btn btn-default" . $options["class"] . "\">" . $displayLabel . "</a>";
                }
            } else {
                $modulebtns[] = $frm->button($displayLabel, "runModuleCommand('custom','" . $options . "')");
            }
        }
    }
    return $modulebtns;
}
