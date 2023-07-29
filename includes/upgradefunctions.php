<?php

function SumUpPackageUpgradeOrder($id, $newproductid, $newproductbillingcycle, $promocode, $paymentmethod = "", $checkout = "")
{
    global $CONFIG;
    global $_LANG;
    global $currency;
    global $upgradeslist;
    global $orderamount;
    global $orderdescription;
    global $applytax;
    $_SESSION["upgradeids"] = [];
    $whmcs = App::self();
    $configoptionsamount = 0;
    $amountToCredit = 0;
    try {
        $service = WHMCS\Service\Service::with("product", "client")->findOrFail($id);
    } catch (Exception $e) {
        throw new WHMCS\Exception\Fatal("Invalid Current Service Id");
    }
    $client = $service->client;
    if (!$client) {
        throw new WHMCS\Exception\User\ClientRequiredException("A client is required");
    }
    $taxCalculator = new WHMCS\Billing\Tax();
    $taxCalculator->setLevel1Percentage(0)->setLevel2Percentage(0);
    if (WHMCS\Config\Setting::getValue("TaxEnabled")) {
        $taxRate = getTaxRate(1, $client->state, $client->country)["rate"];
        $taxRate2 = getTaxRate(2, $client->state, $client->country)["rate"];
        $taxCalculator->setIsInclusive(WHMCS\Config\Setting::getValue("TaxType") == "Inclusive")->setIsCompound(WHMCS\Config\Setting::getValue("TaxL2Compound"))->setLevel1Percentage($taxRate)->setLevel2Percentage($taxRate2);
    }
    $oldproductid = $service->product->id;
    $oldproductname = $service->product->name;
    $domain = $service->domain;
    $nextduedate = $service->nextDueDate;
    $billingcycle = $service->billingCycle;
    $oldamount = $service->recurringAmount;
    if ($billingcycle === "One Time") {
        $oldamount = $service->firstPaymentAmount;
    }
    $cycle = new WHMCS\Billing\Cycles();
    if (!($cycle->isValidSystemBillingCycle($newproductbillingcycle) || $cycle->isValidPublicBillingCycle($newproductbillingcycle))) {
        exit("Invalid New Billing Cycle");
    }
    if (defined("CLIENTAREA")) {
        try {
            $upgradeProductIds = $service->product->upgradeProducts()->pluck("upgrade_product_id");
        } catch (Exception $e) {
            throw new WHMCS\Exception\Fatal("Invalid Current Product ID");
        }
        if (!$upgradeProductIds->contains($newproductid)) {
            throw new WHMCS\Exception\Fatal("Invalid new product ID for upgrade");
        }
    }
    try {
        $product = WHMCS\Product\Product::findOrFail($newproductid);
        $newproductid = $product->id;
        $newproductname = $product->name;
        $applytax = $product->applyTax;
        $paytype = $product->paymentType;
        $stockControlEnabled = $product->stockControlEnabled;
        $quantityInStock = $product->quantityInStock;
    } catch (Exception $e) {
        throw new WHMCS\Exception\Fatal("Invalid New Product ID");
    }
    if ($stockControlEnabled && $quantityInStock <= 0 && $oldproductid != $newproductid) {
        throw new WHMCS\Exception\Fatal("Product Out of Stock");
    }
    $normalisedBillingCycle = $cycle->getNormalisedBillingCycle($newproductbillingcycle);
    if (!in_array($normalisedBillingCycle, $product->getAvailableBillingCycles())) {
        throw new WHMCS\Exception\Fatal("Invalid Billing Cycle Requested");
    }
    $newproductbillingcycleraw = $newproductbillingcycle;
    $newproductbillingcyclenice = ucfirst($newproductbillingcycle);
    if ($newproductbillingcyclenice == "Semiannually") {
        $newproductbillingcyclenice = "Semi-Annually";
    }
    $configoptionspricingarray = getCartConfigOptions($newproductid, "", $newproductbillingcyclenice, $id);
    if ($configoptionspricingarray) {
        foreach ($configoptionspricingarray as $configoptionkey => $configoptionvalues) {
            $configoptionsamount += $configoptionvalues["selectedrecurring"];
        }
    }
    $newproductbillingcycle = $normalisedBillingCycle;
    if ($newproductbillingcycle == "onetime") {
        $newproductbillingcycle = "monthly";
    }
    if ($newproductbillingcycle == "free") {
        $newamount = 0;
    } else {
        $newamount = WHMCS\Database\Capsule::table("tblpricing")->where("type", "product")->where("currency", $currency["id"])->where("relid", $newproductid)->value($newproductbillingcycle);
        $newamount = $taxCalculator->setTaxBase($newamount)->getTotalForClient($client);
    }
    if (($paytype == "onetime" || $paytype == "recurring") && $newamount < 0) {
        exit("Invalid New Billing Cycle");
    }
    $newamount += $configoptionsamount;
    $discount = 0;
    $promodata = [];
    $isLifetimeDiscount = false;
    if ($promocode) {
        $validatedPromoData = validateUpgradePromo($promocode);
        if (is_array($validatedPromoData)) {
            $appliesto = $validatedPromoData["appliesto"];
            $requires = $validatedPromoData["requires"];
            $cycles = $validatedPromoData["cycles"];
            $value = $validatedPromoData["value"];
            $type = $validatedPromoData["discounttype"];
            $promodesc = $validatedPromoData["desc"];
            $newProductBillingCycleRenamed = ucfirst($newproductbillingcycle);
            if ($newproductbillingcycle === "free") {
                $newProductBillingCycleRenamed = "Free Account";
            } else {
                if ($newproductbillingcycle === "onetime") {
                    $newProductBillingCycleRenamed = "One Time";
                } else {
                    if ($newproductbillingcycle === "semiannually") {
                        $newProductBillingCycleRenamed = "Semi-Annually";
                    }
                }
            }
            $promodata = ["type" => $type, "value" => $value];
            if (count($appliesto) && $appliesto[0] && !in_array($newproductid, $appliesto)) {
                $promodata = [];
            }
            if (count($requires) && $requires[0] && !in_array($oldproductid, $requires)) {
                $promodata = [];
            }
            if (count($cycles) && $cycles[0] && !in_array($newProductBillingCycleRenamed, $cycles)) {
                $promodata = [];
            }
        }
        if (empty($promodata)) {
            $lifetimePromoData = get_query_vals("tblpromotions", "type,value", ["lifetimepromo" => 1, "recurring" => 1, "code" => $promocode]);
            if (is_array($lifetimePromoData)) {
                $promodata = $lifetimePromoData;
                $isLifetimeDiscount = true;
            }
        }
        unset($newProductBillingCycleRenamed);
    }
    $promoqualifies = !empty($promodata);
    $year = substr($nextduedate, 0, 4);
    $month = substr($nextduedate, 5, 2);
    $day = substr($nextduedate, 8, 2);
    $oldCycleMonths = getBillingCycleMonths($billingcycle);
    $prevduedate = date("Y-m-d", mktime(0, 0, 0, $month - $oldCycleMonths, $day, $year));
    $totaldays = round((strtotime($nextduedate) - strtotime($prevduedate)) / 86400);
    $newCycleMonths = getBillingCycleMonths($newproductbillingcyclenice);
    $prevduedate = date("Y-m-d", mktime(0, 0, 0, $month - $newCycleMonths, $day, $year));
    $newtotaldays = round((strtotime($nextduedate) - strtotime($prevduedate)) / 86400);
    if ($newproductbillingcyclenice == "Onetime") {
        $newtotaldays = $totaldays;
    }
    if (in_array($billingcycle, [WHMCS\Billing\Cycles::DISPLAY_FREE, WHMCS\Billing\Cycles::DISPLAY_ONETIME])) {
        $days = $newtotaldays = $totaldays = getBillingCycleDays($newproductbillingcyclenice);
        $totalmonths = getBillingCycleMonths($newproductbillingcyclenice);
        $nextduedate = date("Y-m-d", mktime(0, 0, 0, date("m") + $totalmonths, date("d"), date("Y")));
        if ($promoqualifies) {
            if ($promodata["type"] === "Percentage") {
                $percent = $promodata["value"] / 100;
                $discount = $newamount * $percent;
            } else {
                $discount = $promodata["value"];
                if ($newamount < $discount) {
                    $discount = $newamount;
                }
            }
        }
        $newamount -= $discount;
        $amountdue = format_as_currency($newamount - $oldamount);
        $difference = $newamount;
    } else {
        $todaysdate = date("Ymd");
        $nextduedatetime = strtotime($nextduedate);
        $todaysdate = strtotime($todaysdate);
        $days = round(($nextduedatetime - $todaysdate) / 86400);
        $oldAmountPerMonth = round($oldamount / $oldCycleMonths, 2);
        $newAmountPerMonth = round($newamount / $newCycleMonths, 2);
        if ($oldAmountPerMonth == $newAmountPerMonth) {
            $newamount = $oldamount / $totaldays * $newtotaldays;
        }
        $daysnotused = $days / $totaldays;
        $refundamount = $oldamount * $daysnotused;
        $cyclemultiplier = $days / $newtotaldays;
        $amountdue = WHMCS\View\Formatter\Price::adjustDecimals($newamount * $cyclemultiplier, $currency["code"]);
        if ($promoqualifies) {
            if ($isLifetimeDiscount) {
                $discountBase = $amountdue;
            } else {
                $discountBase = $amountdue - $refundamount;
            }
            if ($promodata["type"] === "Percentage") {
                $percent = $promodata["value"] / 100;
                $discount = $discountBase * $percent;
            } else {
                $discount = $promodata["value"];
            }
            if ($discountBase < $discount) {
                $discount = $discountBase;
            }
            $amountdue -= $discount;
        }
        $amountdue -= $refundamount;
        if ($amountdue < 0 && !$CONFIG["CreditOnDowngrade"]) {
            $amountToCredit = $amountdue;
            $amountdue = 0;
        }
        $difference = $newamount - $oldamount;
        $amountdue = format_as_currency($amountdue);
    }
    $amountdue += $discount;
    $upgradearray[] = ["oldproductid" => $oldproductid, "oldproductname" => $oldproductname, "newproductid" => $newproductid, "newproductname" => $newproductname, "daysuntilrenewal" => $days, "totaldays" => $totaldays, "newproductbillingcycle" => $newproductbillingcycleraw, "price" => $amountdue, "discount" => $discount, "promoqualifies" => $promoqualifies];
    $hookReturns = run_hook("OrderProductUpgradeOverride", $upgradearray[0]);
    foreach ($hookReturns as $hookReturn) {
        if (is_array($hookReturn)) {
            if (isset($hookReturn["price"])) {
                $upgradearray[0]["price"] = $hookReturn["price"];
                $amountdue = $upgradearray[0]["price"];
            }
            if (isset($hookReturn["discount"])) {
                $discount = $hookReturn["discount"];
            }
            if (isset($hookReturn["promoqualifies"])) {
                if (!is_bool($hookReturn["promoqualifies"])) {
                    throw new WHMCS\Exception\Fatal("Invalid promo qualification parameter returned by hook. Must be boolean, returned " . gettype($hookReturn["promoqualifies"]));
                }
                $promoqualifies = $hookReturn["promoqualifies"];
            }
            if (isset($hookReturn["daysuntilrenewal"])) {
                $upgradearray[0]["daysuntilrenewal"] = $hookReturn["daysuntilrenewal"];
            }
            if (isset($hookReturn["totaldays"])) {
                $upgradearray[0]["totaldays"] = $hookReturn["totaldays"];
            }
            if (isset($hookReturn["newproductbillingcycle"])) {
                $upgradearray[0]["newproductbillingcycle"] = $hookReturn["newproductbillingcycle"];
            }
            try {
                if (isset($hookReturn["oldproductid"])) {
                    $product = WHMCS\Product\Product::findOrFail($oldproductid);
                    $upgradearray[0]["oldproductname"] = $product->name;
                }
                if (isset($hookReturn["newproductid"])) {
                    $product = WHMCS\Product\Product::findOrFail($newproductid);
                    $upgradearray[0]["newproductname"] = $product->name;
                }
            } catch (Exception $e) {
                throw new WHMCS\Exception\Fatal("Invalid Product ID returned by hook");
            }
        }
    }
    $upgradearray[0]["price"] = formatCurrency($upgradearray[0]["price"]);
    unset($upgradearray[0]["discount"]);
    unset($upgradearray[0]["promoqualifies"]);
    $GLOBALS["subtotal"] = $amountdue;
    $GLOBALS["qualifies"] = $promoqualifies;
    $GLOBALS["discount"] = $discount;
    $totalDue = $amountdue;
    if ($whmcs->get_config("TaxEnabled") && $applytax && !$client->taxExempt) {
        $taxRate = $taxRate / 100;
        $taxRate2 = $taxRate2 / 100;
        if ($whmcs->get_config("TaxType") == "Exclusive") {
            if ($whmcs->get_config("TaxL2Compound")) {
                $totalDue += $totalDue * $taxRate;
                $totalDue += $totalDue * $taxRate2;
            } else {
                $totalDue += $totalDue * $taxRate + $totalDue * $taxRate2;
            }
        }
    }
    if ($checkout) {
        $orderdescription = $_LANG["upgradedowngradepackage"] . ": " . $oldproductname . " => " . $newproductname . "<br>\n" . $_LANG["orderbillingcycle"] . ": " . $_LANG["orderpaymentterm" . str_replace(["-", " "], "", strtolower($newproductbillingcycle))] . "<br>\n" . $_LANG["ordertotalduetoday"] . ": " . formatCurrency($totalDue);
        $amountwithdiscount = $amountdue - $discount;
        $upgradeid = insert_query("tblupgrades", ["type" => "package", "date" => "now()", "relid" => $id, "originalvalue" => $oldproductid, "newvalue" => $newproductid . "," . $newproductbillingcycleraw, "amount" => $amountwithdiscount, "recurringchange" => $difference]);
        $upgradeslist .= $upgradeid . ",";
        $_SESSION["upgradeids"][] = $upgradeid;
        $hookReturns = run_hook("PreUpgradeCheckout", ["clientId" => (int) WHMCS\Session::get("uid"), "upgradeId" => $upgradeid, "serviceId" => $id, "amount" => $amountdue, "discount" => $discount]);
        foreach ($hookReturns as $hookReturn) {
            if (is_array($hookReturn)) {
                if (array_key_exists("amount", $hookReturn) && is_numeric($hookReturn["amount"])) {
                    $amountdue = $hookReturn["amount"];
                }
                if (array_key_exists("discount", $hookReturn) && is_numeric($hookReturn["discount"])) {
                    $discount = $hookReturn["discount"];
                }
                $amountwithdiscount = $amountdue - $discount;
                WHMCS\Database\Capsule::table("tblupgrades")->where("id", $upgradeid)->update(["amount" => $amountwithdiscount]);
            }
        }
        if (0 < $amountdue) {
            if ($domain) {
                $domain = " - " . $domain;
            }
            insert_query("tblinvoiceitems", ["userid" => $_SESSION["uid"], "type" => "Upgrade", "relid" => $upgradeid, "description" => $_LANG["upgradedowngradepackage"] . ": " . $oldproductname . $domain . "\n" . $oldproductname . " => " . $newproductname . " " . "(" . getTodaysDate() . " - " . fromMySQLDate($nextduedate) . ")", "amount" => $amountdue, "taxed" => $applytax, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
            if (0 < $discount) {
                insert_query("tblinvoiceitems", ["userid" => $_SESSION["uid"], "description" => $_LANG["orderpromotioncode"] . ": " . $promocode . " - " . $promodesc, "amount" => $discount * -1, "taxed" => $applytax, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
            }
            $orderamount += $amountwithdiscount;
        } else {
            if ($CONFIG["CreditOnDowngrade"]) {
                $creditamount = $amountdue * -1;
                insert_query("tblcredit", ["clientid" => $_SESSION["uid"], "date" => "now()", "description" => "Upgrade/Downgrade Credit", "amount" => $creditamount]);
                update_query("tblclients", ["credit" => "+=" . $creditamount], ["id" => (int) $_SESSION["uid"]]);
            } else {
                if ($amountToCredit) {
                    WHMCS\Session::set("UpgradeCredit" . $upgradeid, $amountToCredit);
                }
            }
            update_query("tblupgrades", ["paid" => "Y"], ["id" => $upgradeid]);
            doUpgrade($upgradeid);
        }
    }
    return $upgradearray;
}

function SumUpConfigOptionsOrder($id, $configoptions, $promocode, $paymentmethod = "", $checkout = "")
{
    global $CONFIG;
    global $_LANG;
    global $upgradeslist;
    global $orderamount;
    global $orderdescription;
    global $applytax;
    $amountToCredit = 0;
    $_SESSION["upgradeids"] = [];
    $whmcs = App::self();
    $result = select_query("tblhosting", "packageid,domain,nextduedate,billingcycle", ["userid" => $_SESSION["uid"], "id" => $id]);
    $data = mysql_fetch_array($result);
    $packageid = $data["packageid"];
    $domain = $data["domain"];
    $nextduedate = $data["nextduedate"];
    $billingcycle = $data["billingcycle"];
    $productInfo = WHMCS\Database\Capsule::table("tblproducts")->find($packageid, ["tax", "name", "configoptionsupgrade"]);
    $applytax = $productInfo->tax;
    $allowConfigOptionsUpgrade = $productInfo->configoptionsupgrade;
    if (defined("CLIENTAREA") && !$allowConfigOptionsUpgrade) {
        redir("type=configoptions&id=" . (int) $id, "upgrade.php");
    }
    $productname = WHMCS\Product\Product::getProductName($packageid, $productInfo->name);
    if ($domain) {
        $productname .= " - " . $domain;
    }
    $year = substr($nextduedate, 0, 4);
    $month = substr($nextduedate, 5, 2);
    $day = substr($nextduedate, 8, 2);
    $cyclemonths = getBillingCycleMonths($billingcycle);
    $prevduedate = date("Y-m-d", mktime(0, 0, 0, $month - $cyclemonths, $day, $year));
    $totaldays = round((strtotime($nextduedate) - strtotime($prevduedate)) / 86400);
    $todaysdate = date("Ymd");
    $todaysdate = strtotime($todaysdate);
    $nextduedatetime = strtotime($nextduedate);
    $days = round(($nextduedatetime - $todaysdate) / 86400);
    if ($days < 0) {
        $days = $totaldays;
    }
    $percentage = $days / $totaldays;
    $discount = 0;
    $promovalue = 0;
    $promoqualifies = true;
    $upgradeconfigoptions = [];
    $discounttype = NULL;
    if ($promocode) {
        $promodata = validateUpgradePromo($promocode);
        if (is_array($promodata)) {
            $appliesto = $promodata["appliesto"];
            $cycles = $promodata["cycles"];
            $promotype = $promodata["type"];
            $promovalue = $promodata["value"];
            $discounttype = $promodata["discounttype"];
            $upgradeconfigoptions = $promodata["configoptions"];
            $promodesc = $promodata["desc"];
            if ($promotype != "configoptions") {
                $promoqualifies = false;
            }
            if (count($appliesto) && $appliesto[0] && !in_array($packageid, $appliesto)) {
                $promoqualifies = false;
            }
            if (count($cycles) && $cycles[0] && !in_array($billingcycle, $cycles)) {
                $promoqualifies = false;
            }
            if ($discounttype == "Percentage") {
                $promovalue = $promovalue / 100;
            }
        }
        if ($promovalue == 0) {
            $promodata = get_query_vals("tblpromotions", "upgrades, upgradeconfig, type,value", ["lifetimepromo" => 1, "recurring" => 1, "code" => $promocode]);
            if (is_array($promodata)) {
                if ($promodata["upgrades"] == 1) {
                    $upgradeconfig = safe_unserialize($promodata["upgradeconfig"]);
                    if ($upgradeconfig["type"] != "configoptions") {
                        $promoqualifies = false;
                    }
                    $promovalue = $upgradeconfig["value"];
                    $discounttype = $upgradeconfig["discounttype"];
                    if ($discounttype == "Percentage") {
                        $promovalue = $promovalue / 100;
                    }
                    $promoqualifies = true;
                } else {
                    $promoqualifies = false;
                }
            }
        }
    }
    $configoptions = getCartConfigOptions($packageid, $configoptions, $billingcycle);
    $oldconfigoptions = getCartConfigOptions($packageid, "", $billingcycle, $id);
    $subtotal = 0;
    $upgradearray = [];
    foreach ($configoptions as $key => $configoption) {
        $configid = $configoption["id"];
        $configname = $configoption["optionname"];
        $optiontype = $configoption["optiontype"];
        $new_selectedvalue = $configoption["selectedvalue"];
        $new_selectedqty = $configoption["selectedqty"];
        $new_selectedname = $configoption["selectedname"];
        $new_selectedsetup = $configoption["selectedsetup"];
        $new_selectedrecurring = $configoption["selectedrecurring"];
        $old_selectedvalue = $oldconfigoptions[$key]["selectedvalue"];
        $old_selectedqty = $oldconfigoptions[$key]["selectedqty"];
        $old_selectedname = $oldconfigoptions[$key]["selectedname"];
        $old_selectedsetup = $oldconfigoptions[$key]["selectedsetup"];
        $old_selectedrecurring = $oldconfigoptions[$key]["selectedrecurring"];
        if (($optiontype == 1 || $optiontype == 2) && $new_selectedvalue != $old_selectedvalue || ($optiontype == 3 || $optiontype == 4) && $new_selectedqty != $old_selectedqty) {
            $difference = $new_selectedrecurring - $old_selectedrecurring;
            $amountdue = $difference * $percentage;
            $amountdue = format_as_currency($amountdue);
            if (!$CONFIG["CreditOnDowngrade"] && $amountdue < 0) {
                $amountToCredit = $amountdue;
                $amountdue = format_as_currency(0);
            }
            if ($optiontype == 1 || $optiontype == 2) {
                $db_orig_value = $old_selectedvalue;
                $db_new_value = $new_selectedvalue;
                $originalvalue = $old_selectedname;
                $newvalue = $new_selectedname;
            } else {
                if ($optiontype == 3) {
                    $db_orig_value = $old_selectedqty;
                    $db_new_value = $new_selectedqty;
                    if ($old_selectedqty) {
                        $originalvalue = $_LANG["yes"];
                        $newvalue = $_LANG["no"];
                    } else {
                        $originalvalue = $_LANG["no"];
                        $newvalue = $_LANG["yes"];
                    }
                } else {
                    if ($optiontype == 4) {
                        $new_selectedqty = (int) $new_selectedqty;
                        if ($new_selectedqty < 0) {
                            $new_selectedqty = 0;
                        }
                        $db_orig_value = $old_selectedqty;
                        $db_new_value = $new_selectedqty;
                        $originalvalue = $old_selectedqty;
                        $newvalue = $new_selectedqty . " x " . $configoption["options"][0]["nameonly"];
                    }
                }
            }
            $subtotal += $amountdue;
            $itemdiscount = 0;
            if ($promoqualifies && 0 < $amountdue && (!count($upgradeconfigoptions) || in_array($configid, $upgradeconfigoptions))) {
                $itemdiscount = $discounttype == "Percentage" ? round($amountdue * $promovalue, 2) : ($amountdue < $promovalue ? $amountdue : $promovalue);
            }
            $discount += $itemdiscount;
            $upgradearray[] = ["configname" => $configname, "originalvalue" => $originalvalue, "newvalue" => $newvalue, "price" => formatCurrency($amountdue)];
            $client = WHMCS\User\Client::find(WHMCS\Session::get("uid"));
            $totalDue = $amountdue;
            if ($whmcs->get_config("TaxEnabled") && $applytax && $client && !$client->taxExempt) {
                $taxData = getTaxRate(1, $client->state, $client->country);
                $taxRate = $taxData["rate"] / 100;
                $taxData = getTaxRate(2, $client->state, $client->country);
                $taxRate2 = $taxData["rate"] / 100;
                if ($whmcs->get_config("TaxType") == "Exclusive") {
                    if ($whmcs->get_config("TaxL2Compound")) {
                        $totalDue += $totalDue * $taxRate;
                        $totalDue += $totalDue * $taxRate2;
                    } else {
                        $totalDue += $totalDue * $taxRate + $totalDue * $taxRate2;
                    }
                }
            }
            if ($checkout) {
                if ($orderdescription) {
                    $orderdescription .= "<br>\n<br>\n";
                }
                $orderdescription .= $_LANG["upgradedowngradeconfigoptions"] . ": " . $configname . " - " . $originalvalue . " => " . $newvalue . "<br>\n" . $_LANG["ordertotalduetoday"] . ": " . formatCurrency($totalDue);
                $paid = "N";
                if ($amountdue <= 0) {
                    $paid = "Y";
                }
                $amountwithdiscount = $amountdue - $itemdiscount;
                $upgradeid = insert_query("tblupgrades", ["type" => "configoptions", "userid" => WHMCS\Session::get("uid"), "date" => "now()", "relid" => $id, "originalvalue" => $configid . "=>" . $db_orig_value, "newvalue" => $db_new_value, "amount" => $amountwithdiscount, "recurringchange" => $difference, "status" => "Pending", "paid" => $paid]);
                $_SESSION["upgradeids"][] = $upgradeid;
                $hookReturns = run_hook("PreUpgradeCheckout", ["clientId" => (int) WHMCS\Session::get("uid"), "upgradeId" => $upgradeid, "serviceId" => $id, "amount" => $amountdue, "discount" => $discount]);
                foreach ($hookReturns as $hookReturn) {
                    if (is_array($hookReturn)) {
                        if (array_key_exists("amount", $hookReturn) && is_numeric($hookReturn["amount"])) {
                            $amountdue = $hookReturn["amount"];
                        }
                        if (array_key_exists("discount", $hookReturn) && is_numeric($hookReturn["discount"])) {
                            $discount = $hookReturn["discount"];
                        }
                        $amountwithdiscount = $amountdue - $discount;
                        WHMCS\Database\Capsule::table("tblupgrades")->where("id", $upgradeid)->update(["amount" => $amountwithdiscount]);
                    }
                }
                if (0 < $amountdue) {
                    insert_query("tblinvoiceitems", ["userid" => $_SESSION["uid"], "type" => "Upgrade", "relid" => $upgradeid, "description" => $_LANG["upgradedowngradeconfigoptions"] . ": " . $productname . "\n" . $configname . ": " . $originalvalue . " => " . $newvalue . " (" . getTodaysDate() . " - " . fromMySQLDate($nextduedate) . ")", "amount" => $amountdue, "taxed" => $applytax, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
                    if (0 < $itemdiscount) {
                        insert_query("tblinvoiceitems", ["userid" => $_SESSION["uid"], "description" => $_LANG["orderpromotioncode"] . ": " . $promocode . " - " . $promodesc, "amount" => $itemdiscount * -1, "taxed" => $applytax, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
                    }
                    $orderamount += $amountwithdiscount;
                } else {
                    if ($CONFIG["CreditOnDowngrade"]) {
                        $creditamount = $amountdue * -1;
                        insert_query("tblcredit", ["clientid" => $_SESSION["uid"], "date" => "now()", "description" => "Upgrade/Downgrade Credit", "amount" => $creditamount]);
                        update_query("tblclients", ["credit" => "+=" . $creditamount], ["id" => (int) $_SESSION["uid"]]);
                    } else {
                        if ($amountToCredit) {
                            WHMCS\Session::set("UpgradeCredit" . $upgradeid, $amountToCredit);
                        }
                    }
                    doUpgrade($upgradeid);
                }
            }
        }
    }
    if (!count($upgradearray)) {
        if (defined("CLIENTAREA")) {
            redir("type=configoptions&id=" . (int) $id, "upgrade.php");
        } else {
            return [];
        }
    }
    $GLOBALS["subtotal"] = $subtotal;
    $GLOBALS["qualifies"] = $promoqualifies;
    $GLOBALS["discount"] = $discount;
    return $upgradearray;
}

function createUpgradeOrder($serviceId, $ordernotes, $promocode, $paymentmethod)
{
    global $CONFIG;
    global $remote_ip;
    global $orderdescription;
    global $orderamount;
    $whmcs = App::self();
    if ($promocode && !$GLOBALS["qualifies"]) {
        $promocode = "";
    }
    if ($promocode) {
        $result = select_query("tblpromotions", "upgradeconfig", ["code" => $promocode]);
        $data = mysql_fetch_array($result);
        $upgradeconfig = $data["upgradeconfig"];
        $upgradeconfig = safe_unserialize($upgradeconfig);
        $promo_type = $upgradeconfig["discounttype"];
        $promo_value = $upgradeconfig["value"];
        update_query("tblpromotions", ["uses" => "+1"], ["code" => $promocode]);
    }
    $order_number = generateUniqueID();
    $orderid = insert_query("tblorders", ["ordernum" => $order_number, "userid" => $_SESSION["uid"], "date" => "now()", "status" => "Pending", "promocode" => $promocode, "promotype" => $promo_type ?? NULL, "promovalue" => $promo_value ?? NULL, "paymentmethod" => $paymentmethod, "ipaddress" => $remote_ip, "amount" => $orderamount, "notes" => $ordernotes]);
    $additionalOrderNote = "";
    foreach ($_SESSION["upgradeids"] as $upgradeid) {
        update_query("tblupgrades", ["orderid" => $orderid], ["id" => $upgradeid]);
        $upgradeCreditAmount = WHMCS\Session::getAndDelete("UpgradeCredit" . $upgradeid);
        if ($upgradeCreditAmount && is_numeric($upgradeCreditAmount)) {
            $additionalOrderNote .= "Upgrade Order Credit Amount Calculated as: " . format_as_currency($upgradeCreditAmount * -1) . "\r\n";
        }
    }
    if ($additionalOrderNote) {
        $ordernotes .= "\r\n==========\r\nCredit on Downgrade Disabled\r\n" . $additionalOrderNote;
        WHMCS\Database\Capsule::table("tblorders")->where("id", $orderid)->update(["notes" => $ordernotes]);
    }
    sendMessage("Order Confirmation", $_SESSION["uid"], ["order_id" => $orderid, "order_number" => $order_number, "order_details" => $orderdescription]);
    logActivity("Upgrade Order Placed - Order ID: " . $orderid, $_SESSION["uid"]);
    if (!function_exists("createInvoices")) {
        include ROOTDIR . "/includes/processinvoices.php";
    }
    $invoiceid = createInvoices($_SESSION["uid"], true, "", ["upgradeOrder" => true]);
    if ($invoiceid) {
        $invoiceid = (int) WHMCS\Database\Capsule::table("tblinvoiceitems")->where("type", "Upgrade")->whereIn("relid", WHMCS\Session::get("upgradeids"))->orderByDesc("invoiceid")->limit(1)->value("invoiceid");
    }
    if ($invoiceid) {
        if ($CONFIG["OrderDaysGrace"]) {
            $new_time = mktime(0, 0, 0, date("m"), date("d") + $CONFIG["OrderDaysGrace"], date("Y"));
            $duedate = date("Y-m-d", $new_time);
            update_query("tblinvoices", ["duedate" => $duedate, "updated_at" => WHMCS\Carbon::now()->toDateTimeString()], ["id" => $invoiceid]);
        }
        if (!$CONFIG["NoInvoiceEmailOnOrder"]) {
            if ($whmcs->isClientAreaRequest()) {
                $source = "clientarea";
            } else {
                if ($whmcs->isAdminAreaRequest()) {
                    $source = "adminarea";
                } else {
                    if ($whmcs->isApiRequest()) {
                        $source = "api";
                    } else {
                        $source = "autogen";
                    }
                }
            }
            $invoiceArr = ["source" => $source, "user" => WHMCS\Session::get("adminid") ? WHMCS\Session::get("adminid") : "system", "invoiceid" => $invoiceid];
            run_hook("InvoiceCreationPreEmail", $invoiceArr);
            sendMessage("Invoice Created", $invoiceid);
        }
        update_query("tblorders", ["invoiceid" => $invoiceid], ["id" => $orderid]);
    }
    $orderEmailItems = "";
    $adminEmailItems = [];
    $result = select_query("tblclients", "firstname, lastname, companyname, email, address1, address2, city, state, postcode, country, phonenumber, ip, host", ["id" => $_SESSION["uid"]]);
    $data = mysql_fetch_array($result);
    list($firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $ip, $host) = $data;
    $nicegatewayname = WHMCS\Module\GatewaySetting::getFriendlyNameFor($paymentmethod);
    $ordertotal = get_query_val("tblinvoices", "total", ["id" => $invoiceid]);
    $totalDueToday = formatCurrency($ordertotal);
    if ($invoiceid) {
        $result = select_query("tblinvoiceitems", "description", "type='Upgrade' AND relid IN (" . db_build_in_array($_SESSION["upgradeids"]) . ")", "invoiceid", "DESC");
        while ($invoicedata = mysql_fetch_assoc($result)) {
            $orderEmailItems .= $invoicedata["description"] . "<br />";
        }
    } else {
        $orderEmailItems .= $orderdescription;
    }
    if (!$orderEmailItems) {
        $orderEmailItems = "Upgrade/Downgrade";
    }
    $emailItem = ["service" => "", "domain" => "", "addon" => "", "upgrade" => $orderEmailItems];
    $adminEmailItems[] = $emailItem;
    $customInvoiceNumber = NULL;
    if ($invoiceid) {
        $invoiceModel = WHMCS\Billing\Invoice::find($invoiceid);
        $customInvoiceNumber = $invoiceModel->invoiceNumber;
    }
    sendAdminMessage("New Order Notification", ["order_id" => $orderid, "order_number" => $order_number, "order_date" => date("d/m/Y H:i:s"), "invoice_id" => $invoiceid, "custom_invoice_number" => $customInvoiceNumber, "order_payment_method" => $nicegatewayname, "order_total" => formatCurrency($ordertotal), "client_id" => $_SESSION["uid"], "client_first_name" => $firstname, "client_last_name" => $lastname, "client_email" => $email, "client_company_name" => $companyname, "client_address1" => $address1, "client_address2" => $address2, "client_city" => $city, "client_state" => $state, "client_postcode" => $postcode, "client_country" => $country, "client_phonenumber" => $phonenumber, "order_items" => $orderEmailItems, "order_items_array" => $adminEmailItems, "order_notes" => "", "client_ip" => $ip, "client_hostname" => $host, "total_due_today" => $totalDueToday], "account");
    if (WHMCS\Config\Setting::getValue("AutoCancelSubscriptions")) {
        if (!function_exists("cancelSubscriptionForService")) {
            require ROOTDIR . "/includes/gatewayfunctions.php";
        }
        try {
            cancelSubscriptionForService($serviceId, WHMCS\Session::get("uid"));
        } catch (Exception $e) {
        }
    }
    return ["id" => $serviceId, "orderid" => $orderid, "order_number" => $order_number, "invoiceid" => $invoiceid];
}

function processUpgradePayment($upgradeid, $paidamount, $fees, $invoice = "", $gateway = "", $transid = "")
{
    update_query("tblupgrades", ["paid" => "Y"], ["id" => $upgradeid]);
    doUpgrade($upgradeid);
}

function doUpgrade($upgradeid)
{
    $newpackageid = $newbillingcycle = $billingcycle = $configid = $optiontype = "";
    $tempvalue = [];
    $upgrade = WHMCS\Service\Upgrade\Upgrade::with("order", "service", "addon")->find($upgradeid);
    $order = $upgrade->order;
    $orderid = $upgrade->orderId;
    $type = $upgrade->type;
    $relid = $upgrade->entityId;
    $originalvalue = $upgrade->originalValue;
    $newvalue = $upgrade->newValue;
    $upgradeamount = $upgrade->upgradeAmount;
    $recurringchange = $upgrade->recurringChange;
    $promocode = $order->promoCode ?? NULL;
    if (!function_exists("recalcRecurringProductPrice")) {
        require_once ROOTDIR . "/includes/clientfunctions.php";
    }
    if ($type == "package") {
        $newvalue = explode(",", $newvalue);
        list($newpackageid, $newbillingcycle) = $newvalue;
        $changevalue = "amount";
        if ($newbillingcycle == "free") {
            $newbillingcycle = "Free Account";
        } else {
            if ($newbillingcycle == "onetime") {
                $newbillingcycle = "One Time";
                $changevalue = "firstpaymentamount";
                $recurringchange = $upgradeamount;
            } else {
                if ($newbillingcycle == "monthly") {
                    $newbillingcycle = "Monthly";
                } else {
                    if ($newbillingcycle == "quarterly") {
                        $newbillingcycle = "Quarterly";
                    } else {
                        if ($newbillingcycle == "semiannually") {
                            $newbillingcycle = "Semi-Annually";
                        } else {
                            if ($newbillingcycle == "annually") {
                                $newbillingcycle = "Annually";
                            } else {
                                if ($newbillingcycle == "biennially") {
                                    $newbillingcycle = "Biennially";
                                } else {
                                    if ($newbillingcycle == "triennially") {
                                        $newbillingcycle = "Triennially";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $billingcycle = $upgrade->service->billingCycle;
        if ($billingcycle == "Free Account" || $billingcycle == "One Time") {
            $newnextdue = getInvoicePayUntilDate(date("Y-m-d"), $newbillingcycle, true);
            $upgrade->service->nextDueDate = $newnextdue;
            $upgrade->service->nextInvoiceDate = $newnextdue;
        }
        if (!function_exists("migrateCustomFieldsBetweenProducts")) {
            require ROOTDIR . "/includes/customfieldfunctions.php";
        }
        migrateCustomFieldsBetweenProductsOrAddons($relid, $newpackageid, $upgrade->service->packageId);
        $upgrade->service->packageId = $newpackageid;
        $upgrade->service->billingCycle = $newbillingcycle;
        if ($changevalue === "amount") {
            $upgrade->service->recurringAmount = recalcRecurringProductPrice($relid, "", $newpackageid, $newbillingcycle);
        } else {
            $upgrade->service->firstPaymentAmount += $recurringchange;
        }
        $upgrade->service->save();
        cancelUnpaidInvoiceForPreviousPriceAndRegenerateNewInvoiceByServiceId($relid);
        if (!function_exists("getCartConfigOptions")) {
            require ROOTDIR . "/includes/configoptionsfunctions.php";
        }
        $configoptions = getCartConfigOptions($newpackageid, [], $newbillingcycle);
        foreach ($configoptions as $configoption) {
            $data = get_query_val("tblhostingconfigoptions", "COUNT(*)", ["relid" => $relid, "configid" => $configoption["id"]]);
            if (!$data) {
                insert_query("tblhostingconfigoptions", ["relid" => $relid, "configid" => $configoption["id"], "optionid" => $configoption["selectedvalue"]]);
            }
        }
        $newProduct = $upgrade->newProduct;
        if ($newProduct->stockControlEnabled) {
            $newProduct->quantityInStock = $newProduct->quantityInStock - 1;
            $newProduct->save();
        }
        $oldProduct = $upgrade->originalProduct;
        if ($oldProduct->stockControlEnabled) {
            $oldProduct->quantityInStock = $oldProduct->quantityInStock + 1;
            $oldProduct->save();
        }
        run_hook("AfterProductUpgrade", ["upgradeid" => $upgradeid]);
        run_hook("AfterServiceUpgrade", ["upgradeId" => $upgradeid, "clientId" => $upgrade->userId, "serviceId" => $upgrade->entityId]);
    } else {
        if ($type == "configoptions") {
            $tempvalue = explode("=>", $originalvalue);
            $configid = $tempvalue[0];
            $result = select_query("tblproductconfigoptions", "", ["id" => $configid]);
            $data = mysql_fetch_array($result);
            $optiontype = $data["optiontype"];
            $result = select_query("tblhostingconfigoptions", "COUNT(*)", ["relid" => $relid, "configid" => $configid]);
            $data = mysql_fetch_array($result);
            if (!$data[0]) {
                insert_query("tblhostingconfigoptions", ["relid" => $relid, "configid" => $configid]);
            }
            if ($optiontype == 1 || $optiontype == 2) {
                update_query("tblhostingconfigoptions", ["optionid" => $newvalue], ["relid" => $relid, "configid" => $configid]);
            } else {
                if ($optiontype == 3 || $optiontype == 4) {
                    update_query("tblhostingconfigoptions", ["qty" => $newvalue], ["relid" => $relid, "configid" => $configid]);
                }
            }
            $upgrade->service->recurringAmount += $recurringchange;
            $upgrade->service->save();
            run_hook("AfterConfigOptionsUpgrade", ["upgradeid" => $upgradeid]);
        } else {
            $orderData = $order->orderData;
            $quantity = $upgrade->type === WHMCS\Service\Upgrade\Upgrade::TYPE_SERVICE ? $upgrade->service->qty : $upgrade->addon->qty;
            if (!empty($orderData["upgrades"][$upgrade->id])) {
                $quantity = $orderData["upgrades"][$upgrade->id];
            }
            $newNextDueDate = getInvoicePayUntilDate(date("Y-m-d"), $upgrade->newCycle, true);
            if (!function_exists("migrateCustomFieldsBetweenProducts")) {
                require ROOTDIR . "/includes/customfieldfunctions.php";
            }
            migrateCustomFieldsBetweenProductsOrAddons($upgrade->entityId, $upgrade->newValue, $upgrade->originalValue, false, $upgrade->type == "addon");
            if ($upgrade->type == "service") {
                $service = $upgrade->service;
                $service->qty = $quantity;
                $service->nextDueDate = $newNextDueDate;
                $service->nextInvoiceDate = $newNextDueDate;
                $service->packageId = $upgrade->newValue;
                $service->billingCycle = $upgrade->newCycle;
                $service->recurringAmount = $upgrade->newRecurringAmount;
                $service->save();
                if (!function_exists("getCartConfigOptions")) {
                    require ROOTDIR . "/includes/configoptionsfunctions.php";
                }
                $configoptions = getCartConfigOptions($upgrade->newValue, [], $upgrade->newCycle);
                foreach ($configoptions as $configoption) {
                    $result = select_query("tblhostingconfigoptions", "COUNT(*)", ["relid" => $relid, "configid" => $configoption["id"]]);
                    $data = mysql_fetch_array($result);
                    if (!$data[0]) {
                        insert_query("tblhostingconfigoptions", ["relid" => $relid, "configid" => $configoption["id"], "optionid" => $configoption["selectedvalue"]]);
                    }
                }
                $newProduct = $upgrade->newProduct;
                if ($newProduct->stockControlEnabled) {
                    $newProduct->quantityInStock = $newProduct->quantityInStock - 1;
                    $newProduct->save();
                }
                $oldProduct = $upgrade->originalProduct;
                if ($oldProduct->stockControlEnabled) {
                    $oldProduct->quantityInStock = $oldProduct->quantityInStock + 1;
                    $oldProduct->save();
                }
            } else {
                if ($upgrade->type == "addon") {
                    $addon = $upgrade->addon;
                    $addon->qty = $quantity;
                    $addon->nextDueDate = $newNextDueDate;
                    $addon->nextInvoiceDate = $newNextDueDate;
                    $addon->addonId = $upgrade->newValue;
                    $addon->billingCycle = $upgrade->newCycle;
                    $addon->recurringFee = $upgrade->newRecurringAmount;
                    $addon->save();
                }
            }
            cancelUnpaidInvoiceForPreviousPriceAndRegenerateNewInvoiceByServiceId($relid);
            if ($upgrade->type == "service") {
                run_hook("AfterProductUpgrade", ["upgradeid" => $upgradeid]);
                run_hook("AfterServiceUpgrade", ["upgradeId" => $upgradeid, "clientId" => $upgrade->userId, "serviceId" => $upgrade->entityId]);
            } else {
                if ($upgrade->type == "addon") {
                    run_hook("AfterAddonUpgrade", ["upgradeid" => $upgradeid]);
                }
            }
        }
    }
    if ($upgrade->type !== WHMCS\Service\Upgrade\Upgrade::TYPE_ADDON) {
        $upgrade->service->promotionId = 0;
        if (!is_null($upgrade->order->promotion)) {
            if ($upgrade->order->promotion->isRecurring()) {
                $promoCalculator = new WHMCS\Product\Promotion\PromotionCalculator($upgrade->order->promotion, $upgrade->order->client->currencyrel, $upgrade->service->firstPaymentAmount, $upgrade->service->recurringAmount, 0);
                $promotionDiscounts = $promoCalculator->calculate();
                $upgrade->service->recurringAmount -= $promotionDiscounts["recurringdiscount"];
                unset($promotionDiscounts);
                unset($promoCalculator);
            }
            $upgrade->service->promotionId = $upgrade->order->promotion->id;
        }
        $upgrade->service->save();
    }
    $upgradeTypes = [WHMCS\Service\Upgrade\Upgrade::TYPE_PACKAGE, WHMCS\Service\Upgrade\Upgrade::TYPE_CONFIGOPTIONS, WHMCS\Service\Upgrade\Upgrade::TYPE_SERVICE, WHMCS\Service\Upgrade\Upgrade::TYPE_ADDON];
    if (in_array($type, $upgradeTypes)) {
        if ($type === WHMCS\Service\Upgrade\Upgrade::TYPE_ADDON) {
            $upgradedService = $upgrade->addon;
            $upgradedService->refresh();
            $serverPackageId = $upgradedService->service->id;
            $serverAddonId = $upgradedService->id;
            $serverType = $upgradedService->productAddon->module;
            $upgradeEmailTemplate = NULL;
            $upgradedServiceDescription = "Addon ID: " . $relid . " - Service ID: " . $serverPackageId;
        } else {
            $upgradedService = $upgrade->service;
            $upgradedService->refresh();
            $serverPackageId = $upgradedService->id;
            $serverAddonId = 0;
            $serverType = $upgradedService->product->module;
            $upgradeEmailTemplate = $upgradedService->product->upgradeEmailTemplate;
            $upgradedServiceDescription = "Service ID: " . $relid;
        }
        $userid = $upgradedService->clientId;
        $manualUpgradeRequired = false;
        if ($serverType) {
            if (!function_exists("getModuleType")) {
                require dirname(__FILE__) . "/modulefunctions.php";
            }
            $result = ServerChangePackage($serverPackageId, $serverAddonId);
            if ($result != "success") {
                if ($result == "Function Not Supported by Module") {
                    $manualUpgradeRequired = true;
                } else {
                    logActivity("Automatic Product/Service Upgrade Failed - " . $upgradedServiceDescription, $userid);
                }
            } else {
                logActivity("Automatic Product/Service Upgrade Successful - " . $upgradedServiceDescription, $userid);
                if ($upgradeEmailTemplate) {
                    sendMessage($upgradeEmailTemplate, $relid);
                }
            }
        } else {
            $manualUpgradeRequired = true;
        }
        if ($manualUpgradeRequired) {
            $emailVars = ["client_id" => $userid, "service_id" => $relid, "order_id" => $orderid, "upgrade_id" => $upgradeid, "upgrade_type" => $type, "upgrade_amount" => $upgradeamount, "increase_recurring_value" => $recurringchange, "promomotion" => $promocode, "package_id" => $serverPackageId, "server_type" => $serverType];
            if ($type == "package") {
                $emailVars["new_package_id"] = $newpackageid;
                $emailVars["new_billing_cycle"] = $newbillingcycle;
                $emailVars["billing_cycle"] = $billingcycle;
            }
            if ($type == "configoptions") {
                $emailVars["config_id"] = $configid;
                $emailVars["option_type"] = $optiontype;
                $emailVars["current_value"] = $tempvalue[1];
                $emailVars["new_value"] = $newvalue;
            }
            sendAdminMessage("Manual Upgrade Required", $emailVars, "account");
            logActivity("Automatic Product/Service Upgrade not possible - " . $upgradedServiceDescription, $userid);
            WHMCS\Database\Capsule::table("tbltodolist")->insert(["date" => date("Y-m-d"), "title" => "Manual Upgrade Required", "description" => "Manual Upgrade Required for " . $upgradedServiceDescription, "admin" => "", "status" => "Pending", "duedate" => date("Y-m-d")]);
        }
    }
    $upgrade->status = WHMCS\Utility\Status::COMPLETED;
    $upgrade->save();
}

function validateUpgradePromo($promocode)
{
    global $_LANG;
    $result = select_query("tblpromotions", "", ["code" => $promocode]);
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    $recurringtype = $data["type"];
    $recurringvalue = $data["value"];
    $recurring = $data["recurring"];
    $cycles = $data["cycles"];
    $appliesto = $data["appliesto"];
    $requires = $data["requires"];
    $maxuses = $data["maxuses"];
    $uses = $data["uses"];
    $startdate = $data["startdate"];
    $expiredate = $data["expirationdate"];
    $existingclient = $data["existingclient"];
    $onceperclient = $data["onceperclient"];
    $upgrades = $data["upgrades"];
    $upgradeconfig = $data["upgradeconfig"];
    $upgradeconfig = safe_unserialize($upgradeconfig);
    $type = $upgradeconfig["discounttype"];
    $value = $upgradeconfig["value"];
    $configoptions = $upgradeconfig["configoptions"];
    if (!$id) {
        return $_LANG["ordercodenotfound"];
    }
    if (!$upgrades) {
        return $_LANG["promoappliedbutnodiscount"];
    }
    if ($startdate != "0000-00-00") {
        $startdate = str_replace("-", "", $startdate);
        if (date("Ymd") < $startdate) {
            return $_LANG["orderpromoprestart"];
        }
    }
    if ($expiredate != "0000-00-00") {
        $expiredate = str_replace("-", "", $expiredate);
        if ($expiredate < date("Ymd")) {
            return $_LANG["orderpromoexpired"];
        }
    }
    if (0 < $maxuses && $maxuses <= $uses) {
        return $_LANG["orderpromomaxusesreached"];
    }
    if ($onceperclient) {
        $result = select_query("tblorders", "count(*)", ["status" => "Active", "userid" => $_SESSION["uid"], "promocode" => $promocode]);
        $orderCount = mysql_fetch_array($result);
        if (0 < $orderCount[0]) {
            return $_LANG["promoonceperclient"];
        }
    }
    $promodesc = $type == "Percentage" ? $value . "%" : formatCurrency($value);
    $promodesc .= " " . $_LANG["orderdiscount"];
    if (!$recurring) {
        $recurringvalue = 0;
        $recurringtype = "";
    }
    $recurringpromodesc = $recurring && 0 < $recurringvalue ? $recurringpromodesc = $recurringtype == "Percentage" ? $recurringvalue . "%" : formatCurrency($recurringvalue) : "";
    $cycles = explode(",", $cycles);
    $appliesto = explode(",", $appliesto);
    $requires = explode(",", $requires);
    return ["id" => $id, "cycles" => $cycles, "appliesto" => $appliesto, "requires" => $requires, "type" => $upgradeconfig["type"], "value" => $upgradeconfig["value"], "discounttype" => $upgradeconfig["discounttype"], "configoptions" => $upgradeconfig["configoptions"], "desc" => $promodesc, "recurringvalue" => $recurringvalue, "recurringtype" => $recurringtype, "recurringdesc" => $recurringpromodesc];
}

function upgradeAlreadyInProgress($hostingId)
{
    $hostingId = (int) $hostingId;
    $hostingSQL = "SELECT tblinvoices.status\n                     FROM tblorders, tblupgrades, tblinvoices\n                    WHERE tblupgrades.relid = '%d'\n                      AND tblorders.id = tblupgrades.orderid\n                      AND tblorders.invoiceid = tblinvoices.id\n                      AND tblinvoices.status = 'Unpaid'";
    $result = full_query(sprintf($hostingSQL, $hostingId));
    $data = mysql_fetch_array($result);
    if (is_array($data) && $data[0]) {
        return true;
    }
    return false;
}

function cancelUnpaidInvoiceForPreviousPriceAndRegenerateNewInvoiceByServiceId($serviceId)
{
    $invoiceItems = WHMCS\Database\Capsule::table("tblinvoiceitems")->join("tblinvoices", "tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->where("type", "=", "Hosting")->where("relid", "=", $serviceId)->where(WHMCS\Database\Capsule::raw("tblinvoices.status"), "=", "Unpaid")->orderBy("invoiceid")->get(["tblinvoiceitems.*"])->all();
    foreach ($invoiceItems as $invoiceItem) {
        $invoiceId = $invoiceItem->invoiceid;
        $userId = $invoiceItem->userid;
        $dueDate = WHMCS\Carbon::createFromFormat("Y-m-d", $invoiceItem->duedate);
        $allInvoiceItems = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceId)->whereNotIn("type", ["PromoHosting", "GroupDiscount", "LateFee"])->get()->all();
        $services = $addons = $domains = $items = [];
        foreach ($allInvoiceItems as $singleInvoiceItem) {
            switch ($singleInvoiceItem->type) {
                case "Hosting":
                    $services[] = $singleInvoiceItem->relid;
                    break;
                case "Addon":
                    $addons[] = $singleInvoiceItem->relid;
                    break;
                case "Domain":
                    $domains[] = $singleInvoiceItem->relid;
                    break;
                case "Item":
                    $items[] = $singleInvoiceItem->relid;
                    break;
            }
        }
        WHMCS\Database\Capsule::table("tblinvoiceitems")->where("invoiceid", "=", $invoiceId)->update(["duedate" => $dueDate->copy()->subDay()->format("Y-m-d")]);
        WHMCS\Database\Capsule::table("tblinvoices")->where("id", $invoiceId)->update(["status" => WHMCS\Billing\Invoice::STATUS_CANCELLED, "date_cancelled" => WHMCS\Carbon::now()->toDateTimeString(), "updated_at" => WHMCS\Carbon::now()->toDateTimeString()]);
        logActivity("Cancelled Outstanding Product Renewal Invoice - " . "Invoice ID: " . $invoiceId . " - Service ID: " . $serviceId, $userId);
        run_hook("InvoiceCancelled", ["invoiceid" => $invoiceId]);
        if ($services) {
            WHMCS\Database\Capsule::table("tblhosting")->whereIn("id", $services)->update(["nextinvoicedate" => $dueDate->format("Y-m-d")]);
        }
        if ($addons) {
            WHMCS\Database\Capsule::table("tblhostingaddons")->whereIn("id", $addons)->update(["nextinvoicedate" => $dueDate->format("Y-m-d")]);
        }
        if ($domains) {
            WHMCS\Database\Capsule::table("tbldomains")->whereIn("id", $domains)->update(["nextinvoicedate" => $dueDate->format("Y-m-d")]);
        }
        if ($items) {
            WHMCS\Database\Capsule::table("tblbillableitems")->whereIn("id", $items)->decrement("invoicecount", 1, ["duedate" => $dueDate->format("Y-m-d")]);
        }
        if (!function_exists("createInvoices")) {
            require_once ROOTDIR . "/includes/processinvoices.php";
        }
        createInvoices($userId);
    }
}
