<?php
//Decoded By IonCube.cc V12 [ioncube.cc] 2023-7
define("CLIENTAREA", true);
require "init.php";
require "includes/configoptionsfunctions.php";
require "includes/gatewayfunctions.php";
require "includes/invoicefunctions.php";
require "includes/clientfunctions.php";
require "includes/upgradefunctions.php";
require "includes/orderfunctions.php";
Auth::requireLoginAndClient(true);
$pagetitle = $_LANG["upgradedowngradepackage"];
$pageicon = "images/clientarea_big.gif";
$breadcrumbnav = "<a href=\"index.php\">" . $_LANG["globalsystemname"] . "</a> > <a href=\"clientarea.php\">" . $_LANG["clientareatitle"] . "</a> > <a href=\"upgrade.php\">" . $_LANG["upgradedowngradepackage"] . "</a>";
$displayTitle = Lang::trans("upgradedowngradepackage");
$tagline = "";
initialiseClientArea($pagetitle, $displayTitle, $tagline, $pageicon, $breadcrumbnav);
checkContactPermission("orders");
$currency = WHMCS\Billing\Currency::factoryForClientArea();
$templatefile = "upgrade";
$step = $whmcs->get_req_var("step");
$type = App::getFromRequest("type");
if ($step == "4") {
    foreach ($_SESSION["upgradeorder"] as $k => $v) {
        ${$k} = $v;
    }
}
$result = select_query("tblhosting", "tblhosting.id,tblhosting.domain,tblhosting.nextduedate,tblhosting.billingcycle,tblhosting.packageid,tblproducts.name as product_name, tblproductgroups.id AS group_id,tblproductgroups.name as group_name", ["userid" => Auth::client()->id, "tblhosting.id" => $id, "tblhosting.domainstatus" => "Active"], "", "", "", "tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblproductgroups ON tblproductgroups.id=tblproducts.gid");
$data = mysql_fetch_array($result);
$id = $data["id"];
if (!$id) {
    redir("", "clientarea.php");
}
$domain = $data["domain"];
$productname = WHMCS\Product\Product::getProductName($data["packageid"], $data["product_name"]);
$groupname = WHMCS\Product\Group::getGroupName($data["group_id"], $data["group_name"]);
$packageid = $data["packageid"];
$nextduedate = $data["nextduedate"];
$billingcycle = $data["billingcycle"];
$smarty->assign("id", $id);
$smarty->assign("type", $type);
$smarty->assign("groupname", $groupname);
$smarty->assign("productname", $productname);
$smarty->assign("domain", $domain);
$smartyvalues["overdueinvoice"] = false;
$smartyvalues["existingupgradeinvoice"] = false;
$smartyvalues["upgradenotavailable"] = false;
$smartyvalues["overdueinvoice"] = false;
$smartyvalues["existingupgradeinvoice"] = false;
$result = select_query("tblinvoiceitems", "invoiceid", ["type" => "Hosting", "relid" => $id, "status" => "Unpaid", "tblinvoices.userid" => Auth::client()->id], "", "", "", "tblinvoices ON tblinvoices.id=tblinvoiceitems.invoiceid");
$data = mysql_fetch_array($result);
if (is_array($data) && $data[0]) {
    Menu::addContext("service", WHMCS\Service\Service::find($id));
    Menu::primarySidebar("serviceUpgrade");
    Menu::secondarySidebar("serviceUpgrade");
    $smartyvalues["overdueinvoice"] = true;
    outputClientArea($templatefile);
    exit;
}
$errormessage = "";
if ($step == "2" && $type == "configoptions") {
    $configOpsReturn = validateAndSanitizeQuantityConfigOptions($whmcs->get_req_var("configoption"));
    if ($configOpsReturn["errorMessage"]) {
        $errormessage = $configOpsReturn["errorMessage"];
        $step = "";
    }
}
$checkUpgradeAlreadyInProgress = upgradeAlreadyInProgress($id);
Menu::addContext("service", WHMCS\Service\Service::find($id));
Menu::primarySidebar("serviceUpgrade");
Menu::secondarySidebar("serviceUpgrade");
if (!$step) {
    if (upgradeAlreadyInProgress($id)) {
        $smartyvalues["existingupgradeinvoice"] = true;
        outputClientArea($templatefile);
        exit;
    }
    $service = new WHMCS\Service($id, Auth::client()->id);
    if ($type == "package" && !$service->getAllowProductUpgrades() || $type == "configoptions" && !$service->getAllowConfigOptionsUpgrade()) {
        $redirect = "cart.php";
        $vars = "";
        if (0 < count($service->hasProductGotAddons())) {
            $vars = "gid=addons";
        }
        redirSystemURL($vars, $redirect);
    }
    if ($type == "package") {
        $upgradepackages = WHMCS\Product\Product::find($packageid)->getUpgradeProductIds();
        $result = select_query("tblproducts", "id, stockcontrol, qty", "id IN (" . db_build_in_array($upgradepackages) . ")", "order` ASC, `name", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $upgradepackageid = $data["id"];
            $stockControlEnabled = $data["stockcontrol"];
            $stockQty = $data["qty"];
            if (!$stockControlEnabled || 0 < $stockQty) {
                $upgradepackagesarray[$upgradepackageid] = getProductInfo($upgradepackageid);
                $upgradepackagesarray[$upgradepackageid]["pricing"] = getPricingInfo($upgradepackageid, "", true);
            }
        }
        $smarty->assign("upgradepackages", $upgradepackagesarray);
    } else {
        if ($type == "configoptions") {
            $result = select_query("tblhosting", "billingcycle", ["userid" => Auth::client()->id, "id" => $id]);
            $data = mysql_fetch_array($result);
            $billingcycle = $data["billingcycle"];
            $newproductbillingcycle = strtolower($billingcycle);
            $newproductbillingcycle = str_replace("-", "", $newproductbillingcycle);
            $newproductbillingcycle = str_replace("lly", "l", $newproductbillingcycle);
            if ($newproductbillingcycle == "onetime") {
                $newproductbillingcycle = "monthly";
            }
            $configoptions = [];
            $configoptions = getCartConfigOptions($packageid, "", $billingcycle, $id);
            foreach ($configoptions as $configkey => $configoption) {
                $selectedoption = $configoption["selectedoption"];
                $selectedName = $configoption["selectedname"];
                $selectedprice = $configoption["selectedrecurring"];
                $options = $configoption["options"];
                foreach ($options as $optionkey => $option) {
                    $optionname = $option["name"];
                    $optionNameOnly = $option["nameonly"];
                    $optionprice = $option["recurring"];
                    $optionprice = $optionprice - $selectedprice;
                    $configoptions[$configkey]["options"][$optionkey]["price"] = formatCurrency($optionprice);
                    if ($optionname == $selectedoption || $optionNameOnly == $selectedName && 0 < $configoption["selectedsetup"]) {
                        $configoptions[$configkey]["options"][$optionkey]["selected"] = true;
                    }
                }
            }
            $smarty->assign("configoptions", $configoptions);
            $smarty->assign("errormessage", $errormessage);
        }
    }
} else {
    if ($step == "2") {
        $templatefile = "upgradesummary";
        Menu::primarySidebar("serviceUpgrade");
        Menu::secondarySidebar("serviceUpgrade");
        $upgrades = [];
        $applytax = false;
        $serviceid = $_REQUEST["id"];
        $configoption = $whmcs->get_req_var("configoption");
        $promocode = $whmcs->get_req_var("promocode");
        $smartyvalues["promoerror"] = "";
        $smartyvalues["promorecurring"] = "";
        $smartyvalues["promodesc"] = "";
        $smartyvalues["promocode"] = "";
        if ($promocode && empty($_REQUEST["removepromo"])) {
            $promodata = validateUpgradePromo($promocode);
            if (!is_array($promodata)) {
                $promocode = "";
                $smartyvalues["promoerror"] = $promodata;
            } else {
                $smartyvalues["promocode"] = $promocode;
                if ($promodata["type"] == "configoptions" && count($promodata["configoptions"])) {
                    $promodata["desc"] .= " " . $_LANG["upgradeonselectedoptions"];
                }
                $smartyvalues["promodesc"] = $promodata["desc"];
                $smartyvalues["promorecurring"] = $promodata["recurringdesc"];
            }
        } else {
            $promodata = get_query_vals("tblpromotions", "code,type,value", ["lifetimepromo" => 1, "recurring" => 1, "id" => get_query_val("tblhosting", "promoid", ["id" => $serviceid])]);
            if (is_array($promodata)) {
                $smartyvalues["promocode"] = $promocode = $promodata["code"];
                $smartyvalues["promodesc"] = $promodata["type"] == "Percentage" ? $promodata["value"] . "%" : formatCurrency($promodata["value"]);
                $smartyvalues["promorecurring"] = $smartyvalues["promodesc"];
                $smartyvalues["promodesc"] .= " " . $_LANG["orderdiscount"];
            }
        }
        if (isset($_REQUEST["removepromo"])) {
            $promocode = "";
            unset($smartyvalues["promoerror"]);
            unset($smartyvalues["promocode"]);
            unset($smartyvalues["promodesc"]);
            unset($smartyvalues["promorecurring"]);
            $GLOBALS["discount"] = 0;
            $GLOBALS["qualifies"] = false;
        }
        if ($type == "package") {
            $newproductid = $_REQUEST["pid"];
            $newproductbillingcycle = $_REQUEST["billingcycle"];
            $upgrades = SumUpPackageUpgradeOrder($serviceid, $newproductid, $newproductbillingcycle, $promocode);
        } else {
            if ($type == "configoptions") {
                $configoptions = $_REQUEST["configoption"];
                $upgrades = SumUpConfigOptionsOrder($serviceid, $configoptions, $promocode);
            }
        }
        $subtotal = $GLOBALS["subtotal"];
        $qualifies = $GLOBALS["qualifies"];
        $discount = $GLOBALS["discount"];
        if ($promocode && !$qualifies) {
            $smartyvalues["promoerror"] = $_LANG["promoappliedbutnodiscount"];
        }
        $smarty->assign("configoptions", $configoption);
        $smarty->assign("upgrades", $upgrades);
        $gatewayslist = showPaymentGatewaysList([], Auth::client()->id);
        $paymentmethod = key($gatewayslist);
        $smarty->assign("gateways", $gatewayslist);
        $smarty->assign("allowgatewayselection", (bool) WHMCS\Config\Setting::getValue("AllowCustomerChangeInvoiceGateway"));
        $smarty->assign("selectedgateway", $paymentmethod);
        $taxrate = NULL;
        $taxname = NULL;
        $taxrate2 = NULL;
        $taxname2 = NULL;
        if ($CONFIG["TaxEnabled"]) {
            $clientsdetails = getClientsDetails(Auth::client()->id);
            $state = $clientsdetails["state"];
            $country = $clientsdetails["country"];
            $taxexempt = $clientsdetails["taxexempt"];
            if (!$taxexempt) {
                $smarty->assign("taxenabled", true);
                $taxdata = getTaxRate(1, $state, $country);
                $taxrate = $taxdata["rate"];
                $taxname = $taxdata["name"];
                $taxdata2 = getTaxRate(2, $state, $country);
                $taxrate2 = $taxdata2["rate"];
                $taxname2 = $taxdata2["name"];
                unset($taxdata);
                unset($taxdata2);
            }
        }
        $smartyvalues["subtotal"] = formatCurrency($subtotal);
        $smartyvalues["discount"] = formatCurrency($discount);
        $subtotal = $subtotal - $GLOBALS["discount"];
        $tax = $tax2 = 0;
        if ($applytax) {
            if ($taxrate) {
                if ($CONFIG["TaxType"] == "Inclusive") {
                    $inctaxrate = 1 + $taxrate / 100;
                    $tempsubtotal = $subtotal;
                    $subtotal = $subtotal / $inctaxrate;
                    $tax = $tempsubtotal - $subtotal;
                } else {
                    $tax = $subtotal * $taxrate / 100;
                }
            }
            if ($taxrate2) {
                $tempsubtotal = $subtotal;
                if ($CONFIG["TaxL2Compound"]) {
                    $tempsubtotal += $tax;
                }
                if ($CONFIG["TaxType"] == "Inclusive") {
                    $inctaxrate = 1 + $taxrate / 100;
                    $subtotal = $tempsubtotal / $inctaxrate;
                    $tax2 = $tempsubtotal - $subtotal;
                } else {
                    $tax2 = $tempsubtotal * $taxrate2 / 100;
                }
            }
            $tax = round($tax, 2);
            $tax2 = round($tax2, 2);
        }
        $tax = format_as_currency($tax);
        $tax2 = format_as_currency($tax2);
        $smarty->assign("taxenabled", $CONFIG["TaxEnabled"]);
        $smarty->assign("taxname", $taxname);
        $smarty->assign("taxrate", $taxrate);
        $smarty->assign("tax", formatCurrency($tax));
        $smarty->assign("taxname2", $taxname2);
        $smarty->assign("taxrate2", $taxrate2);
        $smarty->assign("tax2", formatCurrency($tax2));
        $total = $subtotal + $tax + $tax2;
        $total = formatCurrency($total);
        $smarty->assign("total", $total);
    } else {
        if ($step == "3") {
            check_token();
            $promocode = $whmcs->get_req_var("promocode");
            $orderdescription = "";
            $serviceid = $_POST["id"];
            $paymentmethod = $_POST["paymentmethod"];
            if ($type == "package") {
                $newproductid = $_POST["pid"];
                $newproductbillingcycle = $_POST["billingcycle"];
                $upgrades = SumUpPackageUpgradeOrder($serviceid, $newproductid, $newproductbillingcycle, $promocode, $paymentmethod, true);
            } else {
                if ($type == "configoptions") {
                    $configoptions = $_POST["configoption"];
                    $upgrades = SumUpConfigOptionsOrder($serviceid, $configoptions, $promocode, $paymentmethod, true);
                }
            }
            $ordernotes = "";
            if (isset($notes) && $notes && $notes != $_LANG["ordernotesdescription"]) {
                $ordernotes = $notes;
            }
            $_SESSION["upgradeorder"] = createUpgradeOrder($serviceid, $ordernotes, $promocode, $paymentmethod);
            $order = WHMCS\Order\Order::findOrFail($_SESSION["upgradeorder"]["orderid"]);
            $upgradeOrder = ["OrderID" => $order->id, "OrderNumber" => $order->orderNumber, "ServiceIDs" => !$order->upgrade->isAddon() ? [$order->upgrade->service->id] : [], "AddonIDs" => $order->upgrade->isAddon() ? [$order->upgrade->addon->id] : [], "DomainIDs" => [], "RenewalIDs" => [], "PaymentMethod" => $order->paymentMethod, "InvoiceID" => $order->invoiceId, "TotalDue" => $order->invoice ? $order->invoice->total : NULL];
            HookMgr::run("AfterShoppingCartCheckout", $upgradeOrder);
            redir("step=4");
        } else {
            if ($step == "4") {
                $orderfrm = new WHMCS\OrderForm();
                $invoiceid = (int) $invoiceid;
                if ($invoiceid) {
                    $result = select_query("tblinvoices", "id,total,paymentmethod", ["userid" => Auth::client()->id, "id" => $invoiceid]);
                    $data = mysql_fetch_array($result);
                    $invoiceid = $data["id"];
                    $total = $data["total"];
                    $paymentmethod = $data["paymentmethod"];
                    if ($invoiceid && 0 < $total) {
                        $paymentmethod = WHMCS\Gateways::makeSafeName($paymentmethod);
                        if (!$paymentmethod) {
                            exit("Unexpected payment method value. Exiting.");
                        }
                        if (WHMCS\Module\GatewaySetting::getTypeFor($paymentmethod) === WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD && (WHMCS\Config\Setting::getValue("AutoRedirectoInvoice") === "on" || WHMCS\Config\Setting::getValue("AutoRedirectoInvoice") === "gateway")) {
                            $gatewayInterface = new WHMCS\Module\Gateway();
                            $gatewayInterface->load($paymentmethod);
                            if (!$gatewayInterface->functionExists("link")) {
                                App::redirectToRoutePath("invoice-pay", [$invoiceid]);
                            }
                        }
                        if ($CONFIG["AutoRedirectoInvoice"] == "on") {
                            $whmcs->redirect("viewinvoice.php", "id=" . (int) $invoiceid);
                        }
                        if ($CONFIG["AutoRedirectoInvoice"] == "gateway") {
                            $clientsdetails = getClientsDetails(Auth::client()->id);
                            $params = getGatewayVariables($paymentmethod, $invoiceid, $total);
                            $paymentbutton = call_user_func($paymentmethod . "_link", $params);
                            $templatefile = "forwardpage";
                            $smarty->assign("message", $_LANG["forwardingtogateway"]);
                            $smarty->assign("code", $paymentbutton);
                            $smarty->assign("invoiceid", $invoiceid);
                            outputClientArea($templatefile);
                            exit;
                        }
                    } else {
                        $smarty->assign("ispaid", true);
                    }
                }
                $templatefile = "complete";
                $smarty->assign("orderid", (int) $orderid);
                $smarty->assign("ordernumber", $order_number);
                $smarty->assign("invoiceid", $invoiceid);
                $smarty->assign("carttpl", WHMCS\View\Template\OrderForm::factory($templatefile . ".tpl")->getName());
                $requiredSmartyVars = ["expressCheckoutInfo", "addons_html", "ispaid", "hasRecommendations", "expressCheckoutError"];
                $definedTemplateVars = $smarty->getTemplateVars();
                foreach ($requiredSmartyVars as $requiredSmartyVar) {
                    if (!isset($definedTemplateVars[$requiredSmartyVar])) {
                        $smarty->assign($requiredSmartyVar, NULL);
                    }
                }
                unset($requiredSmartyVars);
                unset($definedTemplateVars);
                $orderform = "true";
            }
        }
    }
}
outputClientArea($templatefile, false, ["ClientAreaPageUpgrade"]);
