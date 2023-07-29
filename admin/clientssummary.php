<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Clients Summary", false);
$aInt->requiredFiles(["clientfunctions", "processinvoices", "invoicefunctions", "gatewayfunctions", "affiliatefunctions", "modulefunctions"]);
$aInt->setClientsProfilePresets();
$aInt->setHelpLink("Clients:Summary Tab");
$userId = (int) App::getFromRequest("userid");
$selproducts = App::getFromRequest("selproducts") ?: [];
$seladdons = App::getFromRequest("seladdons") ?: [];
$seldomains = App::getFromRequest("seldomains") ?: [];
$action = App::getFromRequest("action");
$noemails = App::getFromRequest("noemails") === "true";
$adminUser = (new WHMCS\Authentication\CurrentUser())->admin();
try {
    $client = WHMCS\User\Client::findOrFail($userId);
    $userId = $client->id;
} catch (Exception $e) {
    $aInt->gracefulExit(AdminLang::trans("clients.invalidclient"));
}
$ownerUser = $client->owner();
$client->migratePaymentDetailsIfRequired();
$whmcs = WHMCS\Application::getInstance();
$aInt->assertClientBoundary($userId);
if ($action == "resendVerificationEmail") {
    check_token("WHMCS.admin.default");
    $ownerUser->sendEmailVerification();
    $aInt->jsonResponse(["success" => true]);
} else {
    if ($action == "massaction") {
        check_token("WHMCS.admin.default");
        $massupdate = App::getFromRequest("massupdate");
        $masscreate = App::getFromRequest("masscreate");
        $masssuspend = App::getFromRequest("masssuspend");
        $massunsuspend = App::getFromRequest("massunsuspend");
        $massterminate = App::getFromRequest("massterminate");
        $masschangepackage = App::getFromRequest("masschangepackage");
        $masschangepw = App::getFromRequest("masschangepw");
        $queryStr = "userid=" . $userId . "&massaction=true";
        $serviceDetails = ["userid" => $userId, "serviceid" => ""];
        $addonDetails = ["userid" => $userId, "id" => "", "serviceid" => "", "addonid" => ""];
        $domainDetails = ["userid" => $userId, "domainid" => ""];
        if (!empty($inv)) {
            checkPermission("Generate Due Invoices");
            $specificitems = ["products" => $selproducts, "addons" => $seladdons, "domains" => $seldomains];
            createInvoices($userId, "", "", $specificitems);
            $queryStr .= "&invoicecount=" . $invoicecount;
        }
        if (!empty($del)) {
            if ($selproducts) {
                checkPermission("Delete Clients Products/Services");
                foreach ($selproducts as $pid) {
                    $hosting = $client->services->find((int) $pid);
                    if ($hosting) {
                        $hosting->delete();
                        $activityMessage = "Deleted Product/Service - User ID: " . $userId;
                        $activityMessage .= " - Service ID: " . $hosting->id;
                        logActivity($activityMessage, $userId);
                    }
                }
            }
            if ($seladdons) {
                checkPermission("Delete Clients Products/Services");
                foreach ($seladdons as $aid) {
                    $addon = WHMCS\Service\Addon::find((int) $aid);
                    $addonUserId = $addon->service->clientId;
                    if ($addonUserId == $userId) {
                        run_hook("AddonDeleted", ["id" => $addon->id]);
                        $addon->delete();
                        logActivity("Deleted Addon ID: " . $addon->id . " - User ID: " . $userId, $userId);
                    }
                }
            }
            if ($seldomains) {
                checkPermission("Delete Clients Domains");
                foreach ($seldomains as $did) {
                    $domain = $client->domains->find((int) $did);
                    if ($domain) {
                        $domainDetails["domainid"] = $domain->id;
                        run_hook("DomainDelete", $domainDetails);
                        $domain->delete();
                        logActivity("Deleted Domain ID: " . $did . " - User ID: " . $userId, $userId);
                    }
                }
            }
            $queryStr .= "&deletesuccess=true";
        }
        if ($massupdate || $masscreate || $masssuspend || $massunsuspend || $massterminate || $masschangepackage || $masschangepw) {
            $paymentmethod = App::getFromRequest("paymentmethod");
            if ($paymentmethod && !WHMCS\Module\GatewaySetting::gateway($paymentmethod)->exists()) {
                $paymentmethod = NULL;
            }
            $proratabill = App::getFromRequest("proratabill");
            if (!empty($proratabill)) {
                checkPermission("Edit Clients Products/Services");
                $targetnextduedate = toMySQLDate($nextduedate);
                foreach ($selproducts as $serviceid) {
                    $data = get_query_vals("tblhosting", "packageid,domain,nextduedate,billingcycle,amount,paymentmethod", ["id" => $serviceid]);
                    $existingpid = $data["packageid"];
                    $domain = $data["domain"];
                    $existingnextduedate = $data["nextduedate"];
                    $billingcycle = $data["billingcycle"];
                    $price = $data["amount"];
                    if (!$paymentmethod) {
                        $paymentmethod = $data["paymentmethod"];
                    }
                    if ($recurringamount) {
                        $price = $recurringamount;
                    }
                    $totaldays = getBillingCycleDays($billingcycle);
                    $timediff = WHMCS\Carbon::createFromFormat("Y-m-d", $targetnextduedate)->diffInDays(WHMCS\Carbon::createFromFormat("Y-m-d", $existingnextduedate));
                    $percent = $timediff / $totaldays;
                    $amountdue = format_as_currency($price * $percent);
                    $invdata = getInvoiceProductDetails($serviceid, $existingpid, "", "", $billingcycle, $domain, $userId);
                    $description = $invdata["description"] . " (" . fromMySQLDate($existingnextduedate) . " - " . $nextduedate . ")";
                    $tax = $invdata["tax"];
                    insert_query("tblinvoiceitems", ["userid" => $userId, "type" => "ProrataProduct" . $targetnextduedate, "relid" => $serviceid, "description" => $description, "amount" => $amountdue, "taxed" => $tax, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
                }
                foreach ($seladdons as $aid) {
                    try {
                        $data = WHMCS\Service\Addon::with("service")->findOrFail($aid);
                    } catch (Exception $e) {
                    }
                    $serviceid = $data->serviceId;
                    $addonid = $data->addonId;
                    $name = $data->name ?: $data->productAddon->name;
                    $existingnextduedate = $data->nextDueDate;
                    $billingcycle = $data->billingCycle;
                    $price = $data->recurringFee;
                    if (!$paymentmethod) {
                        $paymentmethod = $data->paymentGateway;
                    }
                    $domain = $data->domain;
                    if ($recurringamount) {
                        $price = $recurringamount;
                    }
                    $totalDays = getBillingCycleDays($billingcycle);
                    $timeDiff = WHMCS\Carbon::createFromFormat("Y-m-d", $targetnextduedate)->diffInDays(WHMCS\Carbon::createFromFormat("Y-m-d", $existingnextduedate));
                    $percent = $timeDiff / $totalDays;
                    $amountDue = format_as_currency($price * $percent);
                    if ($domain) {
                        $domain = "(" . $domain . ") ";
                    }
                    $description = $_LANG["orderaddon"] . " " . $domain . "- ";
                    if ($name) {
                        $description .= $name;
                    }
                    $description .= " (" . fromMySQLDate($existingnextduedate) . " - " . $nextduedate . ")";
                    $invoiceItem = new WHMCS\Billing\Invoice\Item();
                    $invoiceItem->userId = $userId;
                    $invoiceItem->type = WHMCS\Billing\Invoice\Item::PSEUDO_TYPE_PRORATA_PRODUCT_ADDON . $targetnextduedate;
                    $invoiceItem->relatedEntityId = $data->id;
                    $invoiceItem->description = $description;
                    $invoiceItem->amount = $amountDue;
                    $invoiceItem->taxed = (bool) $data->applyTax;
                    $invoiceItem->dueDate = WHMCS\Carbon::now()->toDateString();
                    $invoiceItem->paymentMethod = $paymentmethod;
                    $invoiceItem->save();
                }
                createInvoices($userId);
            }
            $updateqry = [];
            if ($firstpaymentamount) {
                $updateqry["firstpaymentamount"] = $firstpaymentamount;
            }
            if ($recurringamount) {
                $updateqry["amount"] = $recurringamount;
            }
            if ($nextduedate && !$proratabill) {
                $updateqry["nextduedate"] = toMySQLDate($nextduedate);
                $updateqry["nextinvoicedate"] = toMySQLDate($nextduedate);
            }
            if ($billingcycle) {
                $updateqry["billingcycle"] = $billingcycle;
            }
            if ($paymentmethod) {
                $updateqry["paymentmethod"] = $paymentmethod;
            }
            if ($status) {
                $updateqry["domainstatus"] = $status;
                if ($status == WHMCS\Utility\Status::TERMINATED) {
                    $updateqry["termination_date"] = WHMCS\Carbon::now()->format("Y-m-d");
                }
            }
            if (!empty($overideautosuspend)) {
                $updateqry["overideautosuspend"] = "1";
                $updateqry["overidesuspenduntil"] = toMySQLDate($overidesuspenduntil);
            }
            if ($selproducts && count($updateqry)) {
                checkPermission("Edit Clients Products/Services");
                foreach ($selproducts as $pid) {
                    HookMgr::run("PreServiceEdit", ["serviceid" => $pid]);
                    WHMCS\Database\Capsule::table("tblhosting")->where("id", $pid)->update($updateqry);
                    $serviceDetails["serviceid"] = $pid;
                    HookMgr::run("ServiceEdit", $serviceDetails);
                    HookMgr::run("AdminServiceEdit", $serviceDetails);
                }
                $productIds = implode(",", $selproducts);
                logActivity("Mass Updated Products IDs: '" . $productIds . "'", $userId, ["withClientId" => true]);
            }
            unset($updateqry["amount"]);
            unset($updateqry["domainstatus"]);
            unset($updateqry["overideautosuspend"]);
            unset($updateqry["overidesuspenduntil"]);
            if ($status) {
                $updateqry["status"] = $status;
            }
            if ($seladdons) {
                $addonHook = "AddonEdit";
                unset($updateqry["firstpaymentamount"]);
                if ($recurringamount) {
                    $updateqry["recurring"] = $recurringamount;
                }
                if (count($updateqry)) {
                    checkPermission("Edit Clients Products/Services");
                    foreach ($seladdons as $aid) {
                        $addonData = get_query_vals("tblhostingaddons", "addonid, hostingid, status", ["id" => $aid]);
                        $currentStatus = $addonData["status"];
                        if ($status && $currentStatus != $status) {
                            if ($currentStatus == "Suspended" && $status == "Active") {
                                $addonHook = "AddonUnsuspended";
                            } else {
                                if ($currentStatus != "Active" && $status == "Active") {
                                    $addonHook = "AddonActivated";
                                } else {
                                    if ($currentStatus != "Suspended" && $status == "Suspended") {
                                        $addonHook = "AddonSuspended";
                                    } else {
                                        if ($currentStatus != "Terminated" && $status == "Terminated") {
                                            $addonHook = "AddonTerminated";
                                        } else {
                                            if ($currentStatus != "Cancelled" && $status == "Cancelled") {
                                                $addonHook = "AddonCancelled";
                                            } else {
                                                if ($currentStatus != "Fraud" && $status == "Fraud") {
                                                    $addonHook = "AddonFraud";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $definedAddonID = $addonData["addonid"];
                        $addonServiceID = $addonData["hostingid"];
                        $addonDetails["addonid"] = $definedAddonID;
                        $addonDetails["id"] = $aid;
                        $addonDetails["serviceid"] = $addonServiceID;
                        update_query("tblhostingaddons", $updateqry, ["id" => $aid]);
                        run_hook($addonHook, $addonDetails);
                    }
                    logActivity("Mass Updated Addons IDs: " . implode(",", $seladdons) . " - User ID: " . $userId, $userId);
                }
            }
            if ($seldomains) {
                unset($updateqry["recurring"]);
                unset($updateqry["billingcycle"]);
                if ($firstpaymentamount) {
                    $updateqry["firstpaymentamount"] = $firstpaymentamount;
                }
                if ($recurringamount) {
                    $updateqry["recurringamount"] = $recurringamount;
                }
                if ($billingcycle == "Annually") {
                    $updateqry["registrationperiod"] = "1";
                }
                if ($billingcycle == "Biennially") {
                    $updateqry["registrationperiod"] = "2";
                }
                if ($billingcycle == "Triennially") {
                    $updateqry["registrationperiod"] = "3";
                }
                if (in_array($status, ["Suspended", "Terminated", "Completed"])) {
                    $updateqry["status"] = "Expired";
                }
                if (count($updateqry)) {
                    checkPermission("Edit Clients Domains");
                    foreach ($seldomains as $did) {
                        $domainDetails["domainid"] = $did;
                        run_hook("DomainEdit", $domainDetails);
                        update_query("tbldomains", $updateqry, ["id" => $did]);
                    }
                    logActivity("Mass Updated Domains IDs: " . implode(",", $seldomains) . " - User ID: " . $userId, $userId);
                }
            }
            $moduleresults = [];
            if (!empty($masscreate)) {
                checkPermission("Perform Server Operations");
                $createSuccess = self::trans("services.createsuccess");
                foreach ($selproducts as $serviceid) {
                    try {
                        $modresult = WHMCS\Service\Service::findOrFail($serviceid)->legacyProvision();
                    } catch (Exception $e) {
                        $modresult = $e->getMessage();
                    }
                    if ($modresult != "success") {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $modresult;
                    } else {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $createSuccess;
                    }
                }
                foreach ($seladdons as $addonUniqueId) {
                    $moduleAutomation = WHMCS\Service\Automation\AddonAutomation::factory($addonUniqueId);
                    if (!$moduleAutomation->runAction("CreateAccount")) {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $moduleAutomation->getError();
                    } else {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $createSuccess;
                    }
                }
            }
            if (!empty($masssuspend)) {
                checkPermission("Perform Server Operations");
                foreach ($selproducts as $serviceid) {
                    $modresult = ServerSuspendAccount($serviceid);
                    if ($modresult != "success") {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $modresult;
                    } else {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $aInt->lang("services", "suspendsuccess");
                    }
                }
                foreach ($seladdons as $addonUniqueId) {
                    $moduleAutomation = WHMCS\Service\Automation\AddonAutomation::factory($addonUniqueId);
                    if (!$moduleAutomation->runAction("SuspendAccount")) {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $moduleAutomation->getError();
                    } else {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . AdminLang::trans("services.suspendsuccess");
                    }
                }
            }
            if (!empty($massunsuspend)) {
                checkPermission("Perform Server Operations");
                foreach ($selproducts as $serviceid) {
                    $modresult = ServerUnsuspendAccount($serviceid);
                    if ($modresult != "success") {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $modresult;
                    } else {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $aInt->lang("services", "unsuspendsuccess");
                    }
                }
                foreach ($seladdons as $addonUniqueId) {
                    $moduleAutomation = WHMCS\Service\Automation\AddonAutomation::factory($addonUniqueId);
                    if (!$moduleAutomation->runAction("UnsuspendAccount")) {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $moduleAutomation->getError();
                    } else {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . AdminLang::trans("services.unsuspendsuccess");
                    }
                }
            }
            if (!empty($massterminate)) {
                checkPermission("Perform Server Operations");
                foreach ($selproducts as $serviceid) {
                    $modresult = ServerTerminateAccount($serviceid);
                    if ($modresult != "success") {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $modresult;
                    } else {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $aInt->lang("services", "terminatesuccess");
                    }
                }
                foreach ($seladdons as $addonUniqueId) {
                    $moduleAutomation = WHMCS\Service\Automation\AddonAutomation::factory($addonUniqueId);
                    if (!$moduleAutomation->runAction("TerminateAccount")) {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $moduleAutomation->getError();
                    } else {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . AdminLang::trans("services.terminatesuccess");
                    }
                }
            }
            if (!empty($masschangepackage)) {
                checkPermission("Perform Server Operations");
                foreach ($selproducts as $serviceid) {
                    $modresult = ServerChangePackage($serviceid);
                    if ($modresult != "success") {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $modresult;
                    } else {
                        $moduleresults[] = "Service ID " . $serviceid . ": " . $aInt->lang("services", "updownsuccess");
                    }
                }
                foreach ($seladdons as $addonUniqueId) {
                    $moduleAutomation = WHMCS\Service\Automation\AddonAutomation::factory($addonUniqueId);
                    if (!$moduleAutomation->runAction("ChangePackage")) {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $moduleAutomation->getError();
                    } else {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . AdminLang::trans("services.updownsuccess");
                    }
                }
            }
            if (!empty($masschangepw)) {
                checkPermission("Perform Server Operations");
                foreach ($selproducts as $serviceid) {
                    $modresult = ServerChangePassword($serviceid);
                    if ($modresult != "success") {
                        $moduleresults[] = "Service ID \$serviceid: " . $modresult;
                    } else {
                        $moduleresults[] = "Service ID \$serviceid: " . AdminLang::trans("services.changepwsuccess");
                    }
                }
                foreach ($seladdons as $addonUniqueId) {
                    $moduleAutomation = WHMCS\Service\Automation\AddonAutomation::factory($addonUniqueId);
                    if (!$moduleAutomation->runAction("ChangePassword")) {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . $moduleAutomation->getError();
                    } else {
                        $moduleresults[] = "Addon ID: " . $addonUniqueId . ": " . AdminLang::trans("services.changepwsuccess");
                    }
                }
            }
            WHMCS\Cookie::set("moduleresults", $moduleresults);
            $queryStr .= "&massupdatecomplete=true";
        }
        redir($queryStr);
    }
}
if ($action == "uploadfile") {
    check_token("WHMCS.admin.default");
    checkPermission("Manage Clients Files");
    foreach (WHMCS\File\Upload::getUploadedFiles("uploadfile") as $uploadedFile) {
        try {
            $filename = $uploadedFile->storeAsClientFile();
        } catch (Exception $e) {
            $aInt->gracefulExit("Could not save file: " . $e->getMessage());
        }
        if (!$title) {
            $title = $uploadedFile->getCleanName();
        }
        $params = ["userid" => $userId, "title" => $title, "filename" => $filename, "adminonly" => App::getFromRequest("adminonly")];
        run_hook("AdminClientFileUpload", array_merge($params, ["origfilename" => $uploadedFile->getCleanName()]));
        insert_query("tblclientsfiles", array_merge($params, ["dateadded" => "now()"]));
        logActivity("Added Client File - Title: " . $title . " - User ID: " . $userId, $userId);
    }
    redir("userid=" . $userId);
}
if ($action == "deletefile") {
    check_token("WHMCS.admin.default");
    checkPermission("Manage Clients Files");
    $id = (int) $whmcs->get_req_var("id");
    $result = select_query("tblclientsfiles", "", ["id" => $id, "userid" => $userId]);
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    if (!$id) {
        $aInt->gracefulExit("Invalid File to Delete");
    }
    $title = $data["title"];
    $filename = $data["filename"];
    try {
        Storage::clientFiles()->deleteAllowNotPresent($filename);
    } catch (Exception $e) {
        $aInt->gracefulExit("Could not delete file: " . htmlentities($e->getMessage()));
    }
    delete_query("tblclientsfiles", ["id" => $id, "userid" => $userId]);
    logActivity("Deleted Client File - Title: " . $title . " - User ID: " . $userId, $userId);
    redir("userid=" . $userId);
}
if ($action == "closeclient") {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Clients Details");
    checkPermission("Edit Clients Products/Services");
    checkPermission("Edit Clients Domains");
    checkPermission("Manage Invoice");
    closeClient($userId);
    redir("userid=" . $userId);
}
if ($action == "deleteclient") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Client");
    run_hook("ClientDelete", ["userid" => $userId]);
    deleteClient($userId);
    redir("", "clients.php");
}
if ($action == "savenotes") {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Clients Details");
    update_query("tblclients", ["notes" => $adminnotes], ["id" => $userId]);
    logActivity("Client Summary Notes Updated - User ID: " . $userId, $userId);
    redir("userid=" . $userId);
}
if ($action == "addfunds") {
    check_token("WHMCS.admin.default");
    checkPermission("Create Add Funds Invoice");
    $addfundsamt = round($addfundsamt, 2);
    if (0 < $addfundsamt) {
        $invoiceid = createInvoices($userId);
        $paymentmethod = getClientsPaymentMethod($userId);
        insert_query("tblinvoiceitems", ["userid" => $userId, "type" => "AddFunds", "relid" => "", "description" => $_LANG["addfunds"], "amount" => $addfundsamt, "taxed" => "0", "duedate" => "now()", "paymentmethod" => $paymentmethod]);
        $invoiceid = createInvoices($userId, "", true);
        redir("userid=" . $userId . "&addfunds=true&invoiceid=" . $invoiceid);
    } else {
        redir("userid=" . $userId);
    }
}
if (isset($generateinvoices) && $generateinvoices) {
    check_token("WHMCS.admin.default");
    checkPermission("Generate Due Invoices");
    $invoiceid = createInvoices($userId, $noemails);
    $_SESSION["adminclientgeninvoicescount"] = $invoicecount;
    redir("userid=" . $userId . "&geninvoices=true");
}
if (isset($activateaffiliate) && $activateaffiliate) {
    check_token("WHMCS.admin.default");
    affiliateActivate($userId);
    redir("userid=" . $userId . "&affactivated=true");
}
if ($whmcs->get_req_var("csajaxtoggle")) {
    check_token("WHMCS.admin.default");
    if (!checkPermission("Edit Clients Details", true)) {
        throw new WHMCS\Exception\ProgramExit("Permission Denied");
    }
    $whmcs->get_req_var("csajaxtoggle");
    switch ($whmcs->get_req_var("csajaxtoggle")) {
        case "autocc":
            $fieldName = "disableautocc";
            break;
        case "taxstatus":
            $fieldName = "taxexempt";
            break;
        case "overduenotices":
            $fieldName = "overideduenotices";
            break;
        case "latefees":
            $fieldName = "latefeeoveride";
            break;
        case "splitinvoices":
            $fieldName = "separateinvoices";
            $csajaxtoggleval = get_query_val("tblclients", $fieldName, ["id" => $userId]);
            if ($csajaxtoggleval == "1") {
                update_query("tblclients", [$fieldName => 0], ["id" => $userId]);
                if ($fieldName == "taxexempt") {
                    echo "<strong class=\"textred\">" . $aInt->lang("global", "no") . "</strong>";
                } else {
                    echo "<strong class=\"textgreen\">" . $aInt->lang("global", "yes") . "</strong>";
                }
            } else {
                update_query("tblclients", [$fieldName => 1], ["id" => $userId]);
                if ($fieldName == "taxexempt") {
                    echo "<strong class=\"textgreen\">" . $aInt->lang("global", "yes") . "</strong>";
                } else {
                    echo "<strong class=\"textred\">" . $aInt->lang("global", "no") . "</strong>";
                }
            }
            $oldclientsdetails = getClientsDetails($userId);
            HookMgr::run("ClientEdit", array_merge(["userid" => $client, "isOptedInToMarketingEmails" => $client->isOptedInToMarketingEmails(), "olddata" => $oldclientsdetails], getClientsDetails($userId)));
            exit;
            break;
        default:
            throw new WHMCS\Exception\ProgramExit("Invalid Toggle Value");
    }
} else {
    WHMCS\Session::release();
    $legacyClient = new WHMCS\Client($client);
    $clientsdetails = $legacyClient->getDetails();
    if (!$ownerUser) {
        $ownerUser = $client->refresh()->owner();
    }
    $currency = getCurrency($userId);
    $aInt->deleteJSConfirm("deleteFile", "clientsummary", "filedeletesure", "?userid=" . $userId . "&action=deletefile&id=");
    $jscode = "function closeClient() {\nif (confirm(\"" . $aInt->lang("clients", "closesure") . "\")) {\nwindow.location='?userid=" . $userId . "&action=closeclient" . generate_token("link") . "';\n}}";
    $jquerycode = "\$(\"#addfile\").click(function () {\n    \$(\"#addfileform\").slideToggle();\n    return false;\n});\n\$(\".csajaxtoggle\").click(function () {\n    var csturl = \"clientssummary.php?userid=" . $userId . "&csajaxtoggle=\"+\$(this).attr(\"id\")+\"" . generate_token("link") . "\";\n    var cstelm = \"#\"+\$(this).attr(\"id\");\n    WHMCS.http.jqClient.get(csturl, function(data){\n         \$(cstelm).html(data);\n    });\n});\n";
    ob_start();
    if (isset($geninvoices) && $geninvoices) {
        infoBox($aInt->lang("invoices", "gencomplete"), (int) $_SESSION["adminclientgeninvoicescount"] . " Invoices Created");
    }
    if (isset($addfunds) && $addfunds) {
        infoBox($aInt->lang("clientsummary", "createaddfunds"), $aInt->lang("clientsummary", "createaddfundssuccess") . " - <a href=\"invoices.php?action=edit&id=" . (int) $invoiceid . "\">" . $aInt->lang("fields", "invoicenum") . $invoiceid . "</a>");
    }
    if (isset($affactivated) && $affactivated) {
        infoBox($aInt->lang("clientsummary", "activateaffiliate"), $aInt->lang("clientsummary", "affiliateactivatesuccess"));
    }
    if (DI::make("runtimeStorage")->missingOwnerCreated === true) {
        $newOwnerString = AdminLang::trans("clientsummary.newOwnerCreated");
        $newOwnerEditButtonString = AdminLang::trans("clientsummary.newOwnerCreatedEdit");
        $editUserModalTitle = AdminLang::trans("user.manageUserEmail", [":email" => $ownerUser->email]);
        $editUserModalRoutePath = routePath("admin-user-manage", $ownerUser->id);
        $saveLabel = AdminLang::trans("global.save");
        $alertString = $newOwnerString . "\n<div class=\"pull-right\">\n    <a href=\"" . $editUserModalRoutePath . "\"\n        class=\"btn btn-default open-modal\"\n        data-modal-title=\"" . $editUserModalTitle . "\"\n        data-modal-size=\"modal-lg\"\n        data-btn-submit-label=\"" . $saveLabel . "\"\n        data-btn-submit-id=\"btnSaveUser\"\n    >\n        " . $newOwnerEditButtonString . "\n    </a>\n</div>";
        echo WHMCS\View\Helper::alert($alertString);
    }
    $massaction = $whmcs->get_req_var("massaction");
    if ($massaction) {
        $deletesuccess = $whmcs->get_req_var("deletesuccess");
        $invoicecount = $whmcs->get_req_var("invoicecount");
        $massupdatecomplete = $whmcs->get_req_var("massupdatecomplete");
        if ($deletesuccess) {
            infoBox($aInt->lang("global", "success"), $aInt->lang("clientsummary", "deletesuccess"));
        } else {
            if (0 < strlen(trim($invoicecount))) {
                infoBox($aInt->lang("invoices", "gencomplete"), $invoicecount . " Invoices Created");
            } else {
                if ($massupdatecomplete) {
                    $moduleresults = WHMCS\Cookie::get("moduleresults", true);
                    WHMCS\Cookie::delete("moduleresults");
                    infoBox($aInt->lang("clientsummary", "massupdcomplete"), $aInt->lang("clientsummary", "modifysuccess") . "<br />" . implode("<br />", $moduleresults));
                }
            }
        }
    }
    echo $infobox;
    $clientstats = getClientsStats($userId, $legacyClient->getClientModel());
    $clientsdetails["status"] = $aInt->lang("status", strtolower($clientsdetails["status"]));
    $clientsdetails["autocc"] = $clientsdetails["disableautocc"] ? $aInt->lang("global", "no") : $aInt->lang("global", "yes");
    $clientsdetails["taxstatus"] = $clientsdetails["taxexempt"] ? $aInt->lang("global", "yes") : $aInt->lang("global", "no");
    $clientsdetails["overduenotices"] = $clientsdetails["overideduenotices"] ? $aInt->lang("global", "no") : $aInt->lang("global", "yes");
    $clientsdetails["latefees"] = $clientsdetails["latefeeoveride"] ? $aInt->lang("global", "no") : $aInt->lang("global", "yes");
    $clientsdetails["splitinvoices"] = $clientsdetails["separateinvoices"] ? $aInt->lang("global", "yes") : $aInt->lang("global", "no");
    $templatevars["ownerUser"] = $ownerUser;
    $templatevars["emailVerificationEnabled"] = $ownerUser->isEmailVerificationEnabled();
    $templatevars["emailVerificationPending"] = !$ownerUser->emailVerified();
    $templatevars["emailVerified"] = $ownerUser->emailVerified();
    $templatevars["showTaxIdField"] = WHMCS\Billing\Tax\Vat::isUsingNativeField();
    $templatevars["uploadMaxFileSize"] = getUploadMaxFileSize("MB");
    $clientsdetails["phonenumber"] = $clientsdetails["telephoneNumber"];
    $templatevars["clientsdetails"] = $clientsdetails;
    $countries = new WHMCS\Utility\Country();
    $templatevars["clientsdetails"]["countrylong"] = $countries->getName($clientsdetails["country"]);
    $result = select_query("tblcontacts", "", ["userid" => $userId]);
    $contacts = [];
    while ($data = mysql_fetch_array($result)) {
        $contacts[] = ["id" => $data["id"], "firstname" => $data["firstname"], "lastname" => $data["lastname"], "email" => $data["email"]];
    }
    $templatevars["contacts"] = $contacts;
    $groupname = $groupcolour = "";
    if ($clientsdetails["groupid"]) {
        $result = select_query("tblclientgroups", "", ["id" => $clientsdetails["groupid"]]);
        $data = mysql_fetch_array($result);
        $groupname = $data["groupname"];
        $groupcolour = $data["groupcolour"];
    }
    if (!$groupname) {
        $groupname = $aInt->lang("global", "none");
    }
    $templatevars["clientgroup"] = ["name" => $groupname, "colour" => $groupcolour];
    $result = select_query("tblclients", "", ["id" => $userId]);
    $data = mysql_fetch_array($result);
    $datecreated = $data["datecreated"];
    $templatevars["signupdate"] = fromMySQLDate($datecreated);
    if ($datecreated == "0000-00-00") {
        $clientfor = "Unknown";
    } else {
        $carbonDateCreated = WHMCS\Carbon::createFromFormat("Y-m-d", $datecreated)->startOfDay();
        $intervalArrays = [[$carbonDateCreated->diffInMonths(), "months", "month"], [$carbonDateCreated->diffInDays(), "days", "day"]];
        $diffValue = 1;
        $diffInterval = "day";
        foreach ($intervalArrays as $intervalArray) {
            if (!empty($intervalArray[0])) {
                $diffValue = $intervalArray[0];
                $diffInterval = 1 < $intervalArray[0] ? $intervalArray[1] : $intervalArray[2];
                $clientfor = $diffValue . " " . AdminLang::trans("calendar." . $diffInterval);
            }
        }
    }
    $templatevars["clientfor"] = $clientfor;
    if ($clientsdetails["lastlogin"]) {
        $templatevars["lastlogin"] = $clientsdetails["lastlogin"];
    } else {
        $templatevars["lastlogin"] = $aInt->lang("global", "none");
    }
    $templatevars["stats"] = $clientstats;
    $templatevars["paymethodsSummary"] = (new WHMCS\Admin\Client\PayMethod\ViewHelper($aInt))->clientProfileSummaryHtml($client);
    $result = select_query("tblemails", "", ["userid" => $userId], "id", "DESC", "0,5");
    $lastfivemail = [];
    while ($data = mysql_fetch_array($result)) {
        $lastfivemail[] = ["id" => (int) $data["id"], "date" => WHMCS\Input\Sanitize::makeSafeForOutput(fromMySQLDate($data["date"], "time")), "subject" => $data["subject"] ? WHMCS\Input\Sanitize::makeSafeForOutput($data["subject"]) : $aInt->lang("emails", "nosubject")];
    }
    $templatevars["lastfivemail"] = $lastfivemail;
    $result = select_query("tblaffiliates", "", ["clientid" => $userId]);
    $data = mysql_fetch_array($result);
    $affid = $data["id"] ?? NULL;
    $templatevars["affiliateid"] = $affid;
    if ($affid) {
        $templatevars["afflink"] = "<a href=\"affiliates.php?action=edit&id=" . $affid . "\">" . $aInt->lang("clientsummary", "viewaffiliate") . "</a><br /><br />";
    } else {
        $templatevars["afflink"] = "<a href=\"clientssummary.php?userid=" . $userId . "&activateaffiliate=true\">" . $aInt->lang("clientsummary", "activateaffiliate") . "</a><br /><br />";
    }
    $templatevars["messages"] = "<select name=\"messageID\" class=\"form-control select-inline\"><option value=\"0\">" . $aInt->lang("global", "newmessage") . "</option>";
    $mailTemplates = WHMCS\Mail\Template::where("type", "=", "general")->where("disabled", 0)->where("language", "=", "")->where("name", "!=", "Password Reset Validation")->orderBy("name")->get();
    foreach ($mailTemplates as $template) {
        $templatevars["messages"] .= "<option value=\"" . $template->id . "\"";
        if ($template->custom) {
            $templatevars["messages"] .= " style=\"background-color:#efefef\"";
        }
        $templatevars["messages"] .= ">" . $template->name . "</option>";
    }
    $templatevars["messages"] .= "</select>";
    $recordsfound = "";
    $itemStatuses = ["Pending" => $aInt->lang("status", "pending"), "Pending Registration" => $aInt->lang("status", "pendingregistration"), "Pending Transfer" => $aInt->lang("status", "pendingtransfer"), "Active" => $aInt->lang("status", "active"), "Completed" => AdminLang::trans("status.completed"), "Suspended" => $aInt->lang("status", "suspended"), "Terminated" => $aInt->lang("status", "terminated"), "Cancelled" => $aInt->lang("status", "cancelled"), "Grace" => AdminLang::trans("status.grace"), "Redemption" => AdminLang::trans("status.redemption"), "Expired" => $aInt->lang("status", "expired"), "Transferred Away" => AdminLang::trans("status.transferredaway"), "Fraud" => $aInt->lang("status", "fraud")];
    $templatevars["itemstatuses"] = $itemStatuses;
    $jscode .= "function checkAllStatusFilter() {\n    \$(\"#statusfilter\").find(\"input:checkbox\").attr(\"checked\", \$(\"#statusfiltercheckall\").prop(\"checked\"));\n}\nfunction uncheckCheckAllStatusFilter() {\n    \$(\"#statusfiltercheckall\").attr(\"checked\", false);\n}\nfunction toggleStatusFilter() {\n    \$(\"#statusfilter\").fadeToggle();\n}\nfunction updateCheckAllState() {\n    var tables = jQuery('table.filterable'),\n        statusArray = [],\n        statusFilters = jQuery(\"input[name='statusfilter[]']\"),\n        checkedFilters = jQuery('input[name=\"statusfilter[]\"]:checked'),\n        allChecked = true;\n\n    if (statusFilters.length !== checkedFilters.length) {\n        allChecked = false;\n    }\n    if (allChecked) {\n        jQuery('#statusfiltercheckall').prop('checked', true);\n        checkAllStatusFilter();\n        jQuery('#btnStatusEnabled')\n            .find('span.on').hide().end()\n            .find('span.off').show().end()\n            .removeClass('btn-success');\n    } else {\n        uncheckCheckAllStatusFilter();\n        jQuery('#btnStatusEnabled')\n            .find('span.off').hide().end()\n            .find('span.on').show().end()\n            .addClass('btn-success');\n    }\n    \n    checkedFilters.each(function() {\n        statusArray.push(jQuery(this).val());\n    });\n    tables.each(function() {\n        var thisTable = jQuery(this).DataTable(),\n            search = statusArray.join('|');\n        \n        thisTable.columns('.status')\n            .search(search, true, false);\n    });\n}";
    $jquerycode .= "jQuery('#statusfiltercheckall').change(function() {\n    jQuery('#statusfilter').find(\"input:checkbox\").prop('checked', jQuery(this).prop('checked'));\n});\n\nupdateCheckAllState();\n\njQuery('#btnApplyFilter').on('click', function() {\n    var tables = jQuery('table.filterable'),\n        statusFilters = [],\n        allChecked = true;\n    jQuery(\"input[name='statusfilter[]']:checkbox\").each(function(){\n        var checked = jQuery(this).is(':checked');\n        if (checked) {\n            statusFilters.push(jQuery(this).val());\n        }\n        if (!checked) {\n            allChecked = false;\n        }\n    });\n    \n    tables.each(function() {\n        var thisTable = jQuery(this).DataTable(),\n            search = statusFilters.join('|');\n        \n        thisTable.columns('.status')\n                .search(search, true, false)\n                .draw();\n    });\n    \n    WHMCS.http.jqClient.jsonPost({\n        url: WHMCS.adminUtils.getAdminRouteUrl(\n            '/client/summary/filter'\n        ),\n        data: {\n            token: csrfToken,\n            filters: statusFilters\n        },\n        success: function(response) {\n            updateCheckAllState();\n        },\n        warning: function(error) {\n            jQuery.growl.warning(\n                {\n                    title: '',\n                    message: error\n                }\n            );\n        },\n        always: function() {\n            jQuery(\"#statusfilter\").fadeToggle();\n        }\n    });\n});\n\njQuery(document).on('click', '#btnDeleteClient', function() {\n    jQuery('#doDeleteClient').modal('show');\n}).on('click', '#doDeleteClient-ok', function(e) {\n    e.preventDefault();\n    var deleteUsers = jQuery('#inputDeleteUsers').bootstrapSwitch('state') ? 1 : 0,\n        deleteTransactions = jQuery('#inputDeleteTransactions').bootstrapSwitch('state') ? 1 : 0;\n    WHMCS.http.jqClient.jsonPost({\n        url: WHMCS.adminUtils.getAdminRouteUrl(\n            '/client/" . $userId . "/delete'\n        ),\n        data: {\n            token: csrfToken,\n            deleteUsers: deleteUsers,\n            deleteTransactions: deleteTransactions\n        },\n        success: function(response) {\n            url = response.redirectUrl;\n            window.location.replace(url);\n        },\n        warning: function(error) {\n            jQuery.growl.warning(\n                {\n                    title: '',\n                    message: error\n                }\n            );\n        }\n    });\n});";
    $adminPreferences = $adminUser->userPreferences ?? [];
    $maxServiceRecordsToDisplay = $adminPreferences["tableLengths"]["summaryServices"] ?? 10;
    $noDomainLang = AdminLang::trans("addons.nodomain");
    $statusFilters = [];
    if (!array_key_exists("filters", $adminPreferences)) {
        $adminPreferences["filters"] = [];
    }
    if (!array_key_exists("summary", $adminPreferences["filters"])) {
        $statusFilters = "";
    } else {
        $statusFilters = $adminPreferences["filters"]["summary"];
    }
    if (!is_array($statusFilters)) {
        $statusFilters = array_keys($itemStatuses);
    }
    $productSummary = [];
    $totalServices = $filteredServices = 0;
    if (checkPermission("List Services", true)) {
        $services = WHMCS\Service\Service::whereHas("product")->where("userid", $userId)->orderBy(WHMCS\Database\Capsule::raw("CAST(domainstatus AS CHAR)"))->orderBy("id", "desc");
        $totalServices = $filteredServices = $services->count();
        $services->whereIn("domainstatus", $statusFilters);
        $filteredServices = $services->count();
        $services->limit($maxServiceRecordsToDisplay);
        foreach ($services->get() as $service) {
            if ($service->isRecurring()) {
                $amount = $service->recurringAmount;
                $nextDueDate = fromMySQLDate($service->nextDueDate);
            } else {
                $nextDueDate = "-";
                $amount = $service->firstPaymentAmount;
            }
            $registrationDate = fromMySQLDate($service->registrationDate);
            $domain = $service->domain;
            $domainLink = "";
            if ($domain !== "" && (filter_var($domain, FILTER_VALIDATE_DOMAIN) || filter_var($domain, FILTER_VALIDATE_IP))) {
                $domainLink = "https://" . $domain;
            }
            if (!$domainLink) {
                $domain = $domain ?: "(" . $noDomainLang . ")";
                $domainLink = "clientsservices.php?userid=" . $service->clientId . "&id=" . $service->id;
            }
            $translatedStatus = AdminLang::trans("status." . strtolower($service->status));
            $productSummary[] = ["id" => $service->id, "idshort" => ltrim($service->id, "0"), "regdate" => $registrationDate, "domain" => $domain, "domainLink" => $domainLink, "dpackage" => $service->product->getRawAttribute("name"), "amount" => formatCurrency($amount), "dbillingcycle" => $service->billingCycle, "nextduedate" => $nextDueDate, "domainstatus" => $translatedStatus, "domainoriginalstatus" => $service->status, "dpaymentmethod" => $service->paymentGateway];
        }
    }
    $addonSummary = [];
    $totalAddons = $filteredAddons = 0;
    $maxAddonRecordsToDisplay = $adminPreferences["tableLengths"]["summaryAddons"] ?? 10;
    if (checkPermission("List Addons", true)) {
        $predefinedAddons = WHMCS\Product\Addon::pluck("name", "id");
        $addons = WHMCS\Service\Addon::with("service", "service.product")->whereHas("service", function (Illuminate\Database\Eloquent\Builder $query) use($userId) {
            $query->where("userid", $userId);
        })->orderBy(WHMCS\Database\Capsule::raw("CAST(status AS CHAR)"))->orderBy("id", "desc");
        $totalAddons = $filteredAddons = $addons->count();
        $addons->whereIn("status", $statusFilters);
        $filteredAddons = $addons->count();
        $addons->limit($maxAddonRecordsToDisplay);
        foreach ($addons->get() as $addon) {
            $domain = $addon->service->domain;
            $domainLink = "";
            if ($domain !== "" && (filter_var($domain, FILTER_VALIDATE_DOMAIN) || filter_var($domain, FILTER_VALIDATE_IP))) {
                $domainLink = "https://" . $domain;
            }
            if (!$domainLink) {
                $domain = $domain ?: "(" . $noDomainLang . ")";
                $domainLink = "clientsservices.php?userid=" . $userId . "&aid=" . $addon->id;
            }
            $addonName = $addon->name;
            if (!$addonName && $addon->addonId) {
                $addonName = $predefinedAddons[$addon->addonId];
            }
            $dpackage = $addon->service->product->name;
            if ($addon->isRecurring()) {
                $nextDueDate = fromMySQLDate($addon->nextDueDate);
            } else {
                $nextDueDate = "-";
            }
            $registrationDate = fromMySQLDate($addon->registrationDate);
            $translatedStatus = AdminLang::trans("status." . strtolower($addon->status));
            $addonSummary[] = ["id" => $addon->id, "idshort" => ltrim($addon->id, "0"), "hostingid" => $addon->serviceId, "serviceid" => $addon->serviceId, "regdate" => fromMySQLDate($addon->registrationDate), "domain" => $domain, "domainLink" => $domainLink, "addonname" => $addonName, "dpackage" => $dpackage, "amount" => formatCurrency($addon->recurringFee), "dbillingcycle" => $addon->billingCycle, "nextduedate" => $nextDueDate, "status" => $translatedStatus, "originalstatus" => $addon->status, "dpaymentmethod" => $addon->paymentGateway];
        }
    }
    $domainSummary = [];
    $maxDomainRecordsToDisplay = $adminPreferences["tableLengths"]["summaryDomains"] ?? 10;
    $totalDomains = $filteredDomains = 0;
    if (checkPermission("List Domains", true)) {
        $domains = WHMCS\Domain\Domain::where("userid", $userId)->orderBy(WHMCS\Database\Capsule::raw("CAST(status AS CHAR)"))->orderBy("id", "desc");
        $totalDomains = $filteredDomains = $domains->count();
        $domains->whereIn("status", $statusFilters);
        $filteredDomains = $domains->count();
        $domains->limit($maxDomainRecordsToDisplay);
        foreach ($domains->get() as $domain) {
            $domainSummary[] = ["id" => $domain->id, "idshort" => ltrim($domain->id, "0"), "domain" => $domain->domain, "registrar" => $domain->getRegistrarModuleDisplayName(AdminLang::trans("global.none")), "registrationdate" => fromMySQLDate($domain->registrationDate), "nextduedate" => fromMySQLDate($domain->nextDueDate), "expirydate" => fromMySQLDate($domain->expiryDate), "status" => $domain->translatedStatus, "originalstatus" => $domain->status];
        }
    }
    $quoteSummary = [];
    $maxQuoteRecordsToDisplay = $adminPreferences["tableLengths"]["summaryQuotes"] ?? 10;
    $totalQuotes = 0;
    if (checkPermission("Manage Quotes", true)) {
        $quotes = WHMCS\Billing\Quote::where("userid", $userId)->whereDate("validuntil", ">", WHMCS\Carbon::today());
        $totalQuotes = $quotes->count();
        $quotes->limit($maxQuoteRecordsToDisplay);
        foreach ($quotes->get() as $quote) {
            $quoteSummary[] = ["id" => $quote->id, "idshort" => ltrim($quote->id, "0"), "datecreated" => fromMySQLDate($quote->dateCreated), "subject" => $quote->subject, "stage" => $quote->status, "total" => formatCurrency($quote->total), "validuntil" => $quote->validUntilDate];
        }
    }
    $templatevars["addonsummary"] = $addonSummary;
    $templatevars["domainsummary"] = $domainSummary;
    $templatevars["addonPageLength"] = $maxAddonRecordsToDisplay;
    $templatevars["domainPageLength"] = $maxDomainRecordsToDisplay;
    $templatevars["servicePageLength"] = $maxServiceRecordsToDisplay;
    $templatevars["quotePageLength"] = $maxQuoteRecordsToDisplay;
    $templatevars["productsummary"] = $productSummary;
    $templatevars["quotes"] = $quoteSummary;
    $templatevars["filteredAddons"] = $filteredAddons;
    $templatevars["totalAddons"] = $totalAddons;
    $templatevars["filteredDomains"] = $filteredDomains;
    $templatevars["totalDomains"] = $totalDomains;
    $templatevars["totalQuotes"] = $totalQuotes;
    $templatevars["filteredServices"] = $filteredServices;
    $templatevars["totalServices"] = $totalServices;
    $templatevars["statusFilterChecked"] = $statusFilters;
    $result = select_query("tblclientsfiles", "", ["userid" => $userId], "title", "ASC");
    $files = [];
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $title = $data["title"];
        $adminonly = $data["adminonly"];
        $dateadded = $data["dateadded"];
        $dateadded = fromMySQLDate($dateadded);
        $files[] = ["id" => $id, "title" => $title, "adminonly" => $adminonly, "date" => $dateadded];
    }
    $templatevars["files"] = $files;
    $paymentmethoddropdown = paymentMethodsSelection("- " . $aInt->lang("global", "nochange") . " -");
    $templatevars["paymentmethoddropdown"] = $paymentmethoddropdown;
    $markup = new WHMCS\View\Markup\Markup();
    $templatevars["notes"] = [];
    $result = select_query("tblnotes", "tblnotes.*,(SELECT CONCAT(firstname,' ',lastname) FROM tbladmins WHERE tbladmins.id=tblnotes.adminid) AS adminuser", ["userid" => $userId, "sticky" => "1"], "modified", "DESC");
    while ($data = mysql_fetch_assoc($result)) {
        $markupFormat = $markup->determineMarkupEditor("client_note", "", $data["modified"]);
        $data["note"] = $markup->transform($data["note"], $markupFormat);
        $data["created"] = fromMySQLDate($data["created"], 1);
        $data["modified"] = fromMySQLDate($data["modified"], 1);
        $templatevars["notes"][] = $data;
    }
    $addons_html = run_hook("AdminAreaClientSummaryPage", ["userid" => $userId]);
    $templatevars["addons_html"] = $addons_html;
    $tmplinks = run_hook("AdminAreaClientSummaryActionLinks", ["userid" => $userId]);
    $actionlinks = [];
    foreach ($tmplinks as $tmplinks2) {
        foreach ($tmplinks2 as $tmplinks3) {
            $actionlinks[] = $tmplinks3;
        }
    }
    $templatevars["customactionlinks"] = $actionlinks;
    $templatevars["tokenvar"] = generate_token("link");
    $templatevars["csrfToken"] = generate_token("plain");
    $aInt->templatevars = $templatevars;
    $aInt->populateStandardAdminSmartyVariables();
    if ($whmcs->get_req_var("updatestatusfilter")) {
        echo $aInt->autoAddTokensToForms($aInt->getTemplate("clientssummary"));
        exit;
    }
    echo $aInt->getTemplate("clientssummary");
    echo $aInt->modal("GenerateInvoices", $aInt->lang("invoices", "geninvoices"), $aInt->lang("invoices", "geninvoicessendemails"), [["title" => $aInt->lang("global", "yes"), "onclick" => "window.location=\"?userid=" . $userId . "&generateinvoices=true" . generate_token("link") . "\"", "class" => "btn-primary"], ["title" => $aInt->lang("global", "no"), "onclick" => "window.location=\"?userid=" . $userId . "&generateinvoices=true&noemails=true" . generate_token("link") . "\""]]);
    echo $aInt->modal("AddFunds", $aInt->lang("clientsummary", "createaddfunds"), $aInt->lang("clientsummary", "createaddfundsdesc") . "<br />" . "<div class=\"margin-top-bottom-20 text-center\">" . $aInt->lang("fields", "amount") . ": <input type=\"text\" id=\"addfundsamt\" value=\"" . $CONFIG["AddFundsMinimum"] . "\" class=\"form-control input-inline input-100\" /></div>", [["title" => $aInt->lang("global", "submit"), "onclick" => "window.location=\"?userid=" . $userId . "&action=addfunds" . generate_token("link") . "&addfundsamt=\" + jQuery(\"#addfundsamt\").val()", "class" => "btn-primary"], ["title" => $aInt->lang("global", "cancel")]]);
    $deleteUsersPrompt = "";
    $deleteTransactionsPrompt = "";
    $yesText = strtoupper(AdminLang::trans("global.yes"));
    $noText = strtoupper(AdminLang::trans("global.no"));
    if ($ownerUser->clients()->count() === 1 && $aInt->hasPermission("Delete Users")) {
        $deleteUsersText = AdminLang::trans("clients.deleteUsers");
        $deleteUsersPrompt = "<br><br>\n<label class=\"checkbox-inline\">\n    <input type=\"checkbox\"\n           id=\"inputDeleteUsers\"\n           name=\"deleteUsers\"\n           class=\"slide-toggle\"\n           data-size=\"mini\"\n           data-on-text=\"" . $yesText . "\"\n           data-off-text=\"" . $noText . "\"\n           value=\"1\"\n    >\n    " . $deleteUsersText . "\n</label>";
    }
    if (0 < $client->transactions()->count()) {
        $deleteTransactionsText = AdminLang::trans("clients.deleteTransactions");
        $deleteTransactionsPrompt = "<br><br>\n<label class=\"checkbox-inline\">\n    <input type=\"checkbox\"\n           id=\"inputDeleteTransactions\"\n           name=\"deleteTransactions\"\n           class=\"slide-toggle\"\n           data-size=\"mini\"\n           data-on-text=\"" . $yesText . "\"\n           data-off-text=\"" . $noText . "\"\n           value=\"1\"\n    >\n    " . $deleteTransactionsText . "\n</label>";
    }
    echo WHMCS\View\Helper::confirmationModal("doDeleteClient", AdminLang::trans("clients.deletesure") . $deleteTransactionsPrompt . $deleteUsersPrompt);
    $content = ob_get_contents();
    ob_end_clean();
    $aInt->content = $content;
    $aInt->jquerycode = $jquerycode;
    $aInt->jscode = $jscode;
    $aInt->display();
}
