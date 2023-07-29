<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Products/Services");
$aInt->title = $aInt->lang("products", "title");
$aInt->sidebar = "config";
$aInt->icon = "configproducts";
$aInt->helplink = "Configuring Products/Services";
$aInt->requiredFiles(["modulefunctions", "gatewayfunctions"]);
$aInt->requireAuthConfirmation();
$whmcs = App::self();
$success = $whmcs->get_req_var("success");
$setupReset = $whmcs->get_req_var("setupReset");
$id = (int) $whmcs->get_req_var("id");
$ids = (int) $whmcs->get_req_var("ids");
$sub = $whmcs->get_req_var("sub");
$jscode = $jquerycode = "";
$slug = "";
if ($id && $sub != "deletegroup") {
    $product = WHMCS\Product\Product::find($id);
} else {
    if ($ids) {
        $productGroup = WHMCS\Product\Group::find($ids);
    }
}
$ajaxActions = ["module-settings" => "getModuleSettings", "toggle-metric" => "toggleMetric", "metric-pricing" => "getMetricPricing", "save-metric-pricing" => "saveMetricPricing", "validate-slug" => "validateSlug", "create-module-action-custom-field" => "createModuleActionCustomField"];
$action = (string) $whmcs->get_req_var("action");
if (array_key_exists($action, $ajaxActions)) {
    $productSetup = new WHMCS\Admin\Setup\ProductSetup();
    try {
        $actionToCall = $ajaxActions[$action];
        if (!method_exists($productSetup, $actionToCall)) {
            throw new WHMCS\Exception("Invalid action");
        }
        $request = WHMCS\Http\Message\ServerRequest::fromGlobals();
        $response = $productSetup->{$actionToCall}($product->id, $request);
        if (!is_array($response)) {
            $response = ["error" => "Invalid response"];
        }
    } catch (WHMCS\Exception $e) {
        $response = ["error" => $e->getMessage()];
    }
    $aInt->setBodyContent($response);
    $aInt->output();
    exit;
}
if ($action == "getdownloads") {
    check_token("WHMCS.admin.default");
    if (!checkPermission("Edit Products/Services", true)) {
        exit("Access Denied");
    }
    $dir = $_POST["dir"];
    $dir = preg_replace("/[^0-9]/", "", $dir);
    echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
    $result = select_query("tbldownloadcats", "", ["parentid" => $dir], "name", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $catid = $data["id"];
        $catname = $data["name"];
        echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"dir" . $catid . "/\">" . $catname . "</a></li>";
    }
    $result = select_query("tbldownloads", "", ["category" => $dir], "title", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $downid = $data["id"];
        $downtitle = $data["title"];
        $downfilename = $data["location"];
        $downfilenameSplit = explode(".", $downfilename);
        $ext = end($downfilenameSplit);
        echo "<li class=\"file ext_" . $ext . "\"><a href=\"#\" rel=\"" . $downid . "\">" . $downtitle . "</a></li>";
    }
    echo "</ul>";
    exit;
}
if ($action == "managedownloads") {
    check_token("WHMCS.admin.default");
    if (!checkPermission("Edit Products/Services", true)) {
        exit("Access Denied");
    }
    $adddl = (int) $whmcs->get_req_var("adddl");
    $remdl = (int) $whmcs->get_req_var("remdl");
    if ($adddl) {
        $download = WHMCS\Download\Download::find($adddl);
        $product->productDownloads()->attach($download);
        logAdminActivity("Product Modified - Download Attached: '" . $download->title . "' - Product ID: " . $product->id);
    }
    if ($remdl) {
        $download = WHMCS\Download\Download::find($remdl);
        $product->productDownloads()->detach($download);
        logAdminActivity("Product Modified - Download Detached: '" . $download->title . "' - Product ID: " . $product->id);
    }
    printproductdownloads($product->getDownloadIds());
    exit;
}
if ($action == "quickupload") {
    check_token("WHMCS.admin.default");
    if (!checkPermission("Edit Products/Services", true)) {
        exit("Access Denied");
    }
    $categorieslist = "";
    buildcategorieslist(0, 0);
    echo "<form method=\"post\" action=\"configproducts.php?action=uploadfile&id=" . $id . "\" id=\"quickuploadfrm\" enctype=\"multipart/form-data\">\n" . generate_token("form") . "\n<table width=\"100%\">\n<tr><td width=\"120\">Category:</td><td><select name=\"catId\" class=\"form-control\">" . $categorieslist . "</select></td></tr>\n<tr><td>Title:</td><td><input type=\"text\" name=\"title\" class=\"form-control\" /></td></tr>\n<tr><td>Description:</td><td><input type=\"text\" name=\"description\" class=\"form-control\" /></td></tr>\n<tr><td>Choose File:</td><td><input type=\"file\" name=\"uploadfile\" class=\"form-control\" /></td></tr>\n</table>\n</form>";
    exit;
}
if ($action == "uploadfile") {
    check_token("WHMCS.admin.default");
    if (!checkPermission("Edit Products/Services", true)) {
        exit("Access Denied");
    }
    try {
        foreach (WHMCS\File\Upload::getUploadedFiles("uploadfile") as $uploadedFile) {
            $filename = $uploadedFile->storeAsDownload();
            $catId = (int) $whmcs->get_req_var("catId");
            $title = $whmcs->get_req_var("title");
            $description = $whmcs->get_req_var("description");
            $download = new WHMCS\Download\Download();
            $download->downloadCategoryId = $catId;
            $download->type = "zip";
            $download->title = $title ? $title : $filename;
            $download->description = $description;
            $download->fileLocation = $filename;
            $download->clientDownloadOnly = true;
            $download->isProductDownload = true;
            $download->save();
            $product->productDownloads()->attach($download);
            logActivity("Added New Product Download - " . $title);
            logAdminActivity("Product Modified - Download Attached: '" . $download->title . "' - Product ID: " . $product->id);
        }
    } catch (WHMCS\Exception $e) {
        $aInt->gracefulExit("Could not save file: " . $e->getMessage());
    }
    redir("action=edit&id=" . $id . "&tab=8");
}
if ($action == "adddownloadcat") {
    check_token("WHMCS.admin.default");
    if (!checkPermission("Edit Products/Services", true)) {
        exit("Access Denied");
    }
    $categorieslist = "";
    buildcategorieslist(0, 0);
    echo "<form method=\"post\" action=\"configproducts.php?action=createdownloadcat&id=" . $id . "\" id=\"adddownloadcatfrm\" enctype=\"multipart/form-data\">\n" . generate_token("form") . "\n<table width=\"100%\">\n<tr><td width=\"80\">Category:</td><td><select name=\"catid\" class=\"form-control\">" . $categorieslist . "</select></td></tr>\n<tr><td>Name:</td><td><input type=\"text\" name=\"title\" class=\"form-control\" /></td></tr>\n<tr><td>Description:</td><td><input type=\"text\" name=\"description\" class=\"form-control\" /></td></tr>\n</table>\n</form>";
    exit;
}
if ($action == "createdownloadcat") {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Products/Services");
    insert_query("tbldownloadcats", ["parentid" => $catid, "name" => $title, "description" => $description, "hidden" => "0"]);
    logActivity("Added New Download Category - " . $title);
    redir("action=edit&id=" . $id . "&tab=8");
}
if ($action == "add") {
    check_token("WHMCS.admin.default");
    checkPermission("Create New Products/Services");
    $hostingProductTypes = ["hostingaccount", "reselleraccount", "server"];
    $groupId = (int) $whmcs->get_req_var("gid");
    $productType = $whmcs->getFromRequest("type");
    if (!in_array($productType, $hostingProductTypes)) {
        $productType = "other";
    }
    $newProduct = new WHMCS\Product\Product();
    $newProduct->type = $productType;
    $newProduct->productGroupId = $groupId;
    $newProduct->name = $whmcs->getFromRequest("productname");
    $newProduct->paymentType = "free";
    $newProduct->showDomainOptions = in_array($whmcs->getFromRequest("type"), $hostingProductTypes);
    $newProduct->module = $whmcs->getFromRequest("module");
    $newProduct->isHidden = (bool) $whmcs->getFromRequest("createhidden");
    $displayOrder = WHMCS\Database\Capsule::table("tblproducts")->where("gid", "=", $groupId)->max("order");
    $newProduct->displayOrder = is_null($displayOrder) ? 1 : ++$displayOrder;
    $newProduct->save();
    $slug = App::getFromRequest("slug");
    $newProduct->slugs()->create(["group_id" => $newProduct->productGroupId, "group_slug" => $newProduct->productGroup->slug, "slug" => $slug, "active" => true]);
    $productId = $newProduct->id;
    redir("action=edit&id=" . $productId);
}
if ($action == "save") {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Products/Services");
    $type = $whmcs->get_req_var("type");
    $gid = (int) $whmcs->get_req_var("gid");
    $name = $whmcs->get_req_var("name");
    $description = WHMCS\Input\Sanitize::decode($whmcs->get_req_var("description"));
    $hidden = (int) (bool) $whmcs->get_req_var("hidden");
    $showdomainops = (int) (bool) $whmcs->get_req_var("showdomainops");
    $welcomeemail = (int) $whmcs->get_req_var("welcomeemail");
    $stockcontrol = (int) (bool) $whmcs->get_req_var("stockcontrol");
    $qty = (int) $whmcs->get_req_var("qty");
    $proratabilling = (int) (bool) $whmcs->get_req_var("proratabilling");
    $proratadate = (int) $whmcs->get_req_var("proratadate");
    $proratachargenextmonth = (int) $whmcs->get_req_var("proratachargenextmonth");
    $paytype = $whmcs->get_req_var("paytype");
    $allowqty = (int) $whmcs->get_req_var("allowqty");
    $subdomain = $whmcs->get_req_var("subdomain");
    $autosetup = $whmcs->get_req_var("autosetup");
    $servertype = $whmcs->get_req_var("servertype");
    $servergroup = (int) $whmcs->get_req_var("servergroup");
    $freedomain = $whmcs->get_req_var("freedomain");
    $freedomainpaymentterms = $whmcs->get_req_var("freedomainpaymentterms");
    $freedomaintlds = $whmcs->get_req_var("freedomaintlds");
    $recurringcycles = $whmcs->get_req_var("recurringcycles");
    $autoterminatedays = (int) $whmcs->get_req_var("autoterminatedays");
    $autoterminateemail = (int) $whmcs->get_req_var("autoterminateemail");
    $configoptionsupgrade = (int) (bool) $whmcs->get_req_var("configoptionsupgrade");
    $configoptionlinks = App::getFromRequest("configoptionlinks") ?: [];
    $billingcycleupgrade = $whmcs->get_req_var("billingcycleupgrade");
    $upgradeemail = (int) $whmcs->get_req_var("upgradeemail");
    $overagesenabled = (int) (bool) $whmcs->get_req_var("overagesenabled");
    $overageunitsdisk = $whmcs->get_req_var("overageunitsdisk");
    $overageunitsbw = $whmcs->get_req_var("overageunitsbw");
    $overagesdisklimit = (int) $whmcs->get_req_var("overagesdisklimit");
    $overagesbwlimit = (int) $whmcs->get_req_var("overagesbwlimit");
    $overagesdiskprice = (double) $whmcs->get_req_var("overagesdiskprice");
    $overagesbwprice = (double) $whmcs->get_req_var("overagesbwprice");
    $tax = (int) (bool) $whmcs->get_req_var("tax");
    $affiliatepaytype = $whmcs->get_req_var("affiliatepaytype");
    $affiliatepayamount = (double) $whmcs->get_req_var("affiliatepayamount");
    $affiliateonetime = (int) (bool) $whmcs->get_req_var("affiliateonetime");
    $retired = (int) (bool) $whmcs->get_req_var("retired");
    $isFeatured = (int) (bool) $whmcs->get_req_var("isFeatured");
    $savefreedomainpaymentterms = $freedomainpaymentterms ? implode(",", $freedomainpaymentterms) : "";
    $savefreedomaintlds = $freedomaintlds ? implode(",", $freedomaintlds) : "";
    $overagesenabled = $overagesenabled ? "1," . $overageunitsdisk . "," . $overageunitsbw : "";
    $tagline = $whmcs->get_req_var("tagline");
    $shortDescription = $whmcs->get_req_var("shortDescription");
    $color = $whmcs->get_req_var("color");
    $productRecommendations = $whmcs->get_req_var("productRecommendations");
    $table = "tblproducts";
    $changes = [];
    $array = [];
    if ($type != $product->type) {
        $changes[] = "Product Type Modified: '" . $product->type . "' to '" . $type . "'";
    }
    $array["type"] = $type;
    if ($gid != $product->productGroupId) {
        $newGroup = WHMCS\Product\Group::find($gid);
        $changes[] = "Product Group Modified: '" . $product->productGroup->name . "' to '" . $newGroup->name . "'";
    }
    $array["gid"] = $gid;
    if ($name != $product->name) {
        logAdminActivity("Product Modified - Name Modified: '" . $product->name . "' to '" . $name . "' - Product ID: " . $product->id);
        $product->name = $name;
    }
    $array["name"] = $name;
    if ($description != $product->description) {
        $changes[] = "Product Description Modified";
        $array["description"] = $description;
        $product->description = $description;
    }
    $array["description"] = $description;
    if ($shortDescription != $product->shortDescription) {
        $changes[] = "Product Short Description Modified";
        $array["short_description"] = $product->shortDescription = $shortDescription;
    }
    if ($tagline != $product->tagline) {
        $changes[] = "Product Tagline Modified";
        $array["tagline"] = $product->tagline = $tagline;
    }
    if ($color != $product->color) {
        $changes[] = "Product Color Modified";
        $array["color"] = $color;
        $product->color = $array["color"];
    }
    $productRecommendations = is_array($productRecommendations) ? $productRecommendations : [];
    $existingRecommendations = $product->recommendations()->orderBy("pivot_sortorder")->get()->pluck("id")->toArray();
    if ($productRecommendations != $existingRecommendations) {
        $newRecommendationsArray = [];
        foreach ($productRecommendations as $recommendationOrder => $recommendationProductId) {
            $newRecommendationsArray[$recommendationProductId] = ["sortorder" => $recommendationOrder];
        }
        $product->recommendations()->sync($newRecommendationsArray);
        $changes[] = "Product Recommendations Modified";
    }
    if ($welcomeemail != $product->welcomeEmailTemplateId) {
        $changes[] = "Welcome Email Modified";
    }
    $array["welcomeemail"] = $welcomeemail;
    if ($showdomainops != $product->showDomainOptions) {
        if ($showdomainops) {
            $changes[] = "Require Domain Enabled";
        } else {
            $changes[] = "Require Domain Disabled";
        }
    }
    $array["showdomainoptions"] = $showdomainops;
    if ($stockcontrol != $product->stockControlEnabled) {
        if ($stockcontrol) {
            $changes[] = "Stock Control Enabled";
        } else {
            $changes[] = "Stock Control Disabled";
        }
    }
    $array["stockcontrol"] = $stockcontrol;
    if ($qty != $product->quantityInStock) {
        $changes[] = "Quantity In Stock Modified: '" . $product->quantityInStock . "' to '" . $qty . "'";
    }
    $array["qty"] = $qty;
    if ($tax != $product->applyTax) {
        if ($tax) {
            $changes[] = "Apply Tax Enabled";
        } else {
            $changes[] = "Apply Tax Disabled";
        }
    }
    $array["tax"] = $tax;
    if ($isFeatured != $product->isFeatured) {
        if ($isFeatured) {
            $changes[] = "Featured Product Enabled";
        } else {
            $changes[] = "Featured Product Disabled";
        }
    }
    $array["is_featured"] = $isFeatured;
    if ($hidden != $product->isHidden) {
        if ($hidden) {
            $changes[] = "Product Hidden";
        } else {
            $changes[] = "Product Displayed";
        }
    }
    $array["hidden"] = $hidden;
    if ($retired != $product->isRetired) {
        if ($retired) {
            $changes[] = "Product Retired";
        } else {
            $changes[] = "Product Activated";
        }
    }
    $array["retired"] = $retired;
    if ($paytype != $product->paymentType) {
        $changes[] = "Payment Type Modified: '" . $product->paymentType . "' to '" . $paytype . "'";
    }
    $array["paytype"] = $paytype;
    if ($allowqty != $product->allowMultipleQuantities) {
        if ($allowqty === 1) {
            $changes[] = "Allow Multiple Quantities Enabled";
        } else {
            if ($allowqty === 2) {
                $changes[] = "Unit Quantities Enabled";
            } else {
                $changes[] = "Allow Multiple Quantities Disabled";
            }
        }
    }
    $array["allowqty"] = $allowqty;
    if ($recurringcycles != $product->recurringCycleLimit) {
        $changes[] = "Recurring Cycles Limit Modified: '" . $product->recurringCycleLimit . "' to '" . $recurringcycles . "'";
    }
    $array["recurringcycles"] = $recurringcycles;
    if ($autoterminatedays != $product->daysAfterSignUpUntilAutoTermination) {
        if (!$autoterminatedays) {
            $changes[] = "Auto Terminate/Fixed Term Disabled";
        } else {
            if (!$product->daysAfterSignUpUntilAutoTermination) {
                $changes[] = "Auto Terminate/Fixed Term Enabled and set to: '" . $autoterminatedays . "'";
            } else {
                $changes[] = "Auto Terminate/Fixed Term Modified: " . "'" . $product->daysAfterSignUpUntilAutoTermination . "' to '" . $autoterminatedays . "'";
            }
        }
    }
    $array["autoterminatedays"] = $autoterminatedays;
    if ($autoterminateemail != $product->autoTerminationEmailTemplateId) {
        $changes[] = "Automatic Termination Email Template Modified";
    }
    $array["autoterminateemail"] = $autoterminateemail;
    $prorataDisabled = false;
    $prorataShouldBeDisabled = in_array($paytype, [WHMCS\Product\Product::PAYMENT_FREE, WHMCS\Product\Product::PAYMENT_ONETIME]);
    if ($proratabilling != $product->proRataBilling || $proratabilling && $prorataShouldBeDisabled) {
        if ($proratabilling) {
            if ($prorataShouldBeDisabled) {
                $prorataDisabled = true;
                $array["proratabilling"] = "";
                $changes[] = $proratabilling != $product->proRataBilling ? "Prorata Billing disabled due to non-recurring pricing set" : "Prorata Billing could not be enabled due to non-recurring pricing set";
            } else {
                $array["proratabilling"] = $proratabilling;
                $changes[] = "Prorata Billing Enabled";
            }
        } else {
            $array["proratabilling"] = $proratabilling;
            $changes[] = "Prorata Billing Disabled";
        }
    }
    if ($proratadate != $product->proRataChargeDayOfCurrentMonth) {
        $changes[] = "Prorata Date Modified: '" . $product->proRataChargeDayOfCurrentMonth . "' to '" . $proratadate . "'";
    }
    $array["proratadate"] = $proratadate;
    if ($proratachargenextmonth != $product->proRataChargeNextMonthAfterDay) {
        $changes[] = "Charge Next Month: '" . $product->proRataChargeNextMonthAfterDay . "' to '" . $proratachargenextmonth . "'";
    }
    $array["proratachargenextmonth"] = $proratachargenextmonth;
    $array["servertype"] = $servertype;
    if ($servergroup != $product->serverGroupId) {
        $changes[] = "Server Group Modified: '" . $product->serverGroupId . "' to '" . $servergroup . "'";
    }
    $array["servergroup"] = $servergroup;
    if (App::isInRequest("autosetup") && $autosetup != $product->autoSetup) {
        if (!$autosetup) {
            $changes[] = "Automatic Setup Disabled";
        } else {
            $changes[] = "Automatic Setup Modified: '" . ucfirst($product->autoSetup) . "' to '" . ucfirst($autosetup) . "'";
        }
        $array["autosetup"] = $autosetup;
    }
    if ($configoptionsupgrade != $product->allowConfigOptionUpgradeDowngrade) {
        if ($configoptionsupgrade) {
            $changes[] = "Configurable Options Upgrade/Downgrade Enabled";
        } else {
            $changes[] = "Configurable Options Upgrade/Downgrade Disabled";
        }
    }
    $array["configoptionsupgrade"] = $configoptionsupgrade;
    $array["billingcycleupgrade"] = $billingcycleupgrade;
    if ($upgradeemail != $product->upgradeEmailTemplateId) {
        $changes[] = "Upgrade Email Template Modified";
    }
    $array["upgradeemail"] = $upgradeemail;
    if ($freedomain != $product->freeDomain) {
        if (!$freedomain) {
            $changes[] = "Free Domain Disabled";
        } else {
            if ($freedomain == "on") {
                $changes[] = "Free Domain Renewal Modified: 'Free Renewal with Active Product'";
            } else {
                $changes[] = "Free Domain Renewal Modified: 'No Free Renewal'";
            }
        }
    }
    $array["freedomain"] = $freedomain;
    if ($savefreedomainpaymentterms != implode(",", $product->freeDomainPaymentTerms)) {
        $changes[] = "Free Domain Payment Terms Modified";
    }
    $array["freedomainpaymentterms"] = $savefreedomainpaymentterms;
    if ($savefreedomaintlds != implode(",", $product->freeDomainPaymentTerms)) {
        $changes[] = "Free Domain TLD's Modified";
    }
    $array["freedomaintlds"] = $savefreedomaintlds;
    if ($affiliatepaytype != $product->affiliatePaymentType) {
        if (!$affiliatepaytype) {
            $changes[] = "Custom Affiliate Payout Modified: Use Default";
        } else {
            switch ($affiliatepaytype) {
                case "percentage":
                    $changes[] = "Custom Affiliate Payout Modified: Percentage";
                    break;
                case "fixed":
                    $changes[] = "Custom Affiliate Payout Modified: Fixed Amount";
                    break;
                default:
                    $changes[] = "Custom Affiliate Payout Modified: No Commission";
            }
        }
    }
    $array["affiliatepaytype"] = $affiliatepaytype;
    if ($affiliatepayamount != $product->affiliatePaymentAmount) {
        $changes[] = "Affiliate Pay Amount Modified: '" . $product->affiliatePaymentAmount . "' to '" . $affiliatepayamount . "'";
    }
    $array["affiliatepayamount"] = $affiliatepayamount;
    if ($affiliateonetime != $product->affiliatePayoutOnceOnly) {
        if ($affiliateonetime) {
            $changes[] = "Affiliate One Time Payout Enabled";
        } else {
            $changes[] = "Affiliate Recurring Payout Enabled";
        }
    }
    $array["affiliateonetime"] = $affiliateonetime;
    $subdomain = WHMCS\Admin\Setup\ProductSetup::formatSubDomainValuesToEnsureLeadingDotAndUnique(explode(",", $subdomain));
    $subdomain = implode(",", $subdomain);
    if ($subdomain != implode(",", $product->freeSubDomains)) {
        $changes[] = "Subdomain Options Modified: '" . implode(",", $product->freeSubDomains) . "' to '" . $subdomain . "'";
    }
    $array["subdomain"] = $subdomain;
    if ($overagesenabled != implode(",", $product->enableOverageBillingAndUnits)) {
        if ($overagesenabled) {
            $changes[] = "Overages Billing Enabled";
        } else {
            $changes[] = "Overages Billing Disabled";
        }
    }
    $array["overagesenabled"] = $overagesenabled;
    if ($overagesdisklimit != $product->overageDiskLimit) {
        $currentDiskUnits = $product->enableOverageBillingAndUnits[1];
        $oldLimit = $product->overageDiskLimit . " " . $currentDiskUnits;
        $newLimit = $overagesdisklimit . " " . $overageunitsdisk;
        $changes[] = "Soft Limits Disk Usage Modified: '" . $oldLimit . "' to '" . $newLimit . "'";
    }
    $array["overagesdisklimit"] = $overagesdisklimit;
    if ($overagesbwlimit != $product->overageBandwidthLimit) {
        $currentBandwidthUnits = $product->enableOverageBillingAndUnits[2];
        $oldLimit = $product->overageBandwidthLimit . " " . $currentBandwidthUnits;
        $newLimit = $overagesbwlimit . " " . $overageunitsbw;
        $changes[] = "Soft Limits Bandwidth Modified: '" . $oldLimit . "' to '" . $newLimit . "'";
    }
    $array["overagesbwlimit"] = $overagesbwlimit;
    if ($overagesdiskprice != $product->overageDiskPrice) {
        $changes[] = "Disk Usage Overage Costs Modified: '" . $product->overageDiskPrice . "' to '" . $overagesdiskprice . "'";
    }
    $array["overagesdiskprice"] = $overagesdiskprice;
    if ($overagesbwprice != $product->overageBandwidthPrice) {
        $changes[] = "Bandwidth Overage Costs Modified: '" . $product->overageBandwidthPrice . "' to '" . $overagesbwprice . "'";
    }
    $array["overagesbwprice"] = $overagesbwprice;
    $hasServerTypeChanged = $servertype != $product->module;
    $server = new WHMCS\Module\Server();
    $newServer = $server->load($servertype);
    if ($hasServerTypeChanged) {
        $oldServer = new WHMCS\Module\Server();
        $oldName = $oldServer->load($product->module) ? $oldServer->getDisplayName() : "";
        $newName = $newServer ? $server->getDisplayName() : "";
        $changes[] = "Server Module Modified: '" . $oldName . "' to '" . $newName . "'";
    }
    $packageconfigoption = $whmcs->get_req_var("packageconfigoption") ?: [];
    if ($server->functionExists("ConfigOptions")) {
        $configArray = $server->call("ConfigOptions", ["producttype" => $product->type]);
        $counter = 0;
        foreach ($configArray as $key => $values) {
            $counter++;
            $mco = "moduleConfigOption" . $counter;
            if (!$whmcs->isInRequest("packageconfigoption", $counter)) {
                $packageconfigoption[$counter] = $product->{$mco};
            }
            $saveValue = is_array($packageconfigoption[$counter]) ? $packageconfigoption[$counter] : trim($packageconfigoption[$counter]);
            if (!$hasServerTypeChanged) {
                if ($values["Type"] == "password") {
                    $field = "configoption" . $counter;
                    $existingValue = $product->{$field};
                    $updatedPassword = interpretMaskedPasswordChangeForStorage($saveValue, $existingValue);
                    if ($updatedPassword !== false) {
                        if ($updatedPassword) {
                            $changes[] = $key . " Value Modified";
                        }
                    }
                } else {
                    if (is_array($saveValue)) {
                        $saveValue = json_encode($saveValue);
                        if ($saveValue != $product->{$mco}) {
                            $changes[] = $key . " Value Modified";
                        }
                    } else {
                        $saveValue = WHMCS\Input\Sanitize::decode($saveValue);
                        if ($saveValue != $product->{$mco}) {
                            $changes[] = $key . " Value Modified: '" . $product->{$mco} . "' to '" . $saveValue . "'";
                        }
                    }
                }
            } else {
                if (is_array($saveValue)) {
                    $saveValue = json_encode($saveValue);
                } else {
                    $saveValue = WHMCS\Input\Sanitize::decode($saveValue);
                }
            }
            $array["configoption" . $counter] = $saveValue;
        }
    }
    $slug = App::getFromRequest("slug");
    if ($gid !== $product->productGroupId || !$product->activeSlug || $product->activeSlug->slug !== $slug) {
        $productGroup = WHMCS\Product\Group::find($gid);
        $product->slugs()->update(["active" => false]);
        $activeSlug = $product->slugs()->where("group_id", $gid)->where("slug", $slug)->first();
        if (!$activeSlug) {
            $product->slugs()->create(["group_id" => $gid, "group_slug" => $productGroup->slug, "slug" => $slug, "active" => true]);
        } else {
            $activeSlug->active = true;
            $activeSlug->save();
        }
        $changes[] = "Product URL Modified";
    }
    $where = ["id" => $id];
    update_query($table, $array, $where);
    $product->save($array);
    $product = WHMCS\Product\Product::find($id);
    $moduleActions = App::getFromRequest("module_actions") ?: [];
    $moduleConfiguration = $product->getModuleConfigurationSetting("moduleActions");
    $moduleConfiguration->saveValue(json_encode($moduleActions));
    $moduleEventActions = $server->callIfExists("EventActions", []);
    foreach ($moduleActions as $actionName => $actionSettings) {
        $moduleActionConfig = $moduleEventActions[$actionName] ?? NULL;
        if ($moduleActionConfig) {
            $eventName = $moduleActionConfig["Events"][0] ?? "";
            if ($eventName) {
                $existingAction = WHMCS\Product\EventAction\EventAction::ofProduct($product)->onEvent($eventName)->first();
                if (!empty($actionSettings["auto"])) {
                    if (!$existingAction) {
                        $existingAction = new WHMCS\Product\EventAction\EventAction();
                        $existingAction->entityType = "product";
                        $existingAction->entityId = $product->id;
                        $existingAction->name = $actionName;
                        $existingAction->eventName = $eventName;
                        $existingAction->action = $moduleActionConfig["ModuleFunction"];
                    }
                    $existingAction->params = $moduleActions[$actionName]["params"] ?? [];
                    $existingAction->save();
                } else {
                    if ($existingAction) {
                        $existingAction->delete();
                    }
                }
            }
        }
    }
    $oldUpgradeProductIds = [];
    foreach ($product->upgradeProducts as $oldUpgradeProduct) {
        $oldUpgradeProductIds[] = $oldUpgradeProduct->id;
    }
    $upgradepackages = $whmcs->get_req_var("upgradepackages") ?: [];
    $product->upgradeProducts()->detach();
    $upgradePackagesChanged = false;
    foreach ($upgradepackages as $upgradePackageId) {
        if (!in_array($upgradePackageId, $oldUpgradeProductIds) && !$upgradePackagesChanged) {
            $upgradePackagesChanged = true;
            $changes[] = "Upgrade Packages Modified";
        }
        $product->upgradeProducts()->attach(WHMCS\Product\Product::find($upgradePackageId));
    }
    foreach ($oldUpgradeProductIds as $oldUpgradeProductId) {
        if (!in_array($oldUpgradeProductId, $upgradepackages) && !$upgradePackagesChanged) {
            $upgradePackagesChanged = true;
            $changes[] = "Upgrade Packages Modified";
        }
    }
    $pricingChanged = $setupFeeReset = false;
    foreach ($_POST["currency"] as $currency_id => $pricing) {
        if ($pricingChanged === false) {
            $oldPricing = WHMCS\Database\Capsule::table("tblpricing")->where("type", "=", "product")->where("currency", "=", $currency_id)->where("relid", "=", $id)->first();
            foreach ($pricing as $variable => $price) {
                if ($oldPricing->{$variable} != $price) {
                    $pricingChanged = true;
                    $changes[] = "Pricing Modified";
                }
            }
        }
        $setupFeeVars = ["msetupfee", "qsetupfee", "ssetupfee", "asetupfee", "bsetupfee", "tsetupfee"];
        foreach ($setupFeeVars as $setupFeeVar) {
            if ($pricing[$setupFeeVar] && $pricing[$setupFeeVar] < 0) {
                $pricing[$setupFeeVar] = 0;
                $setupFeeReset = true;
            }
        }
        update_query("tblpricing", $pricing, ["type" => "product", "currency" => $currency_id, "relid" => $id]);
    }
    $customfieldname = $whmcs->get_req_var("customfieldname");
    if ($customfieldname) {
        $customfieldtype = $whmcs->get_req_var("customfieldtype");
        $customfielddesc = $whmcs->get_req_var("customfielddesc");
        $customfieldoptions = $whmcs->get_req_var("customfieldoptions");
        $customfieldregexpr = $whmcs->get_req_var("customfieldregexpr");
        $customadminonly = $whmcs->get_req_var("customadminonly");
        $customrequired = $whmcs->get_req_var("customrequired");
        $customshoworder = $whmcs->get_req_var("customshoworder");
        $customshowinvoice = $whmcs->get_req_var("customshowinvoice");
        $customsortorder = $whmcs->get_req_var("customsortorder");
        foreach ($customfieldname as $fid => $value) {
            $thisCustomField = WHMCS\Database\Capsule::table("tblcustomfields")->find($fid);
            if ($value != $thisCustomField->fieldname) {
                $changes[] = "Custom Field Name Modified: '" . $thisCustomField->fieldname . "' to '" . $value . "'";
            }
            if ($customfieldtype[$fid] != $thisCustomField->fieldtype || $customfielddesc[$fid] != $thisCustomField->description || $customfieldoptions[$fid] != $thisCustomField->fieldoptions || $customfieldregexpr[$fid] != $thisCustomField->regexpr || $customadminonly[$fid] != $thisCustomField->adminonly || $customrequired[$fid] != $thisCustomField->required || $customshoworder[$fid] != $thisCustomField->showorder || $customshowinvoice[$fid] != $thisCustomField->showinvoice || $customsortorder[$fid] != $thisCustomField->sortorder) {
                $changes[] = "Custom Field Modified: '" . $value . "'";
            }
            update_query("tblcustomfields", ["fieldname" => $value, "fieldtype" => $customfieldtype[$fid], "description" => $customfielddesc[$fid], "fieldoptions" => $customfieldoptions[$fid], "regexpr" => WHMCS\Input\Sanitize::decode($customfieldregexpr[$fid]), "adminonly" => $customadminonly[$fid], "required" => $customrequired[$fid], "showorder" => $customshoworder[$fid], "showinvoice" => $customshowinvoice[$fid], "sortorder" => $customsortorder[$fid]], ["id" => $fid]);
        }
    }
    $addfieldname = $whmcs->get_req_var("addfieldname");
    if ($addfieldname) {
        $addfieldtype = $whmcs->get_req_var("addfieldtype");
        $addcustomfielddesc = $whmcs->get_req_var("addcustomfielddesc");
        $addfieldoptions = $whmcs->get_req_var("addfieldoptions");
        $addregexpr = $whmcs->get_req_var("addregexpr");
        $addadminonly = $whmcs->get_req_var("addadminonly");
        $addrequired = $whmcs->get_req_var("addrequired");
        $addshoworder = $whmcs->get_req_var("addshoworder");
        $addshowinvoice = $whmcs->get_req_var("addshowinvoice");
        $addsortorder = $whmcs->get_req_var("addsortorder");
        $changes[] = "Custom Field Created: '" . $addfieldname . "'";
        $customFieldIDid = insert_query("tblcustomfields", ["type" => "product", "relid" => $id, "fieldname" => $addfieldname, "fieldtype" => $addfieldtype, "description" => $addcustomfielddesc, "fieldoptions" => $addfieldoptions, "regexpr" => WHMCS\Input\Sanitize::decode($addregexpr), "adminonly" => $addadminonly, "required" => $addrequired, "showorder" => $addshoworder, "showinvoice" => $addshowinvoice, "sortorder" => $addsortorder]);
        if (WHMCS\Config\Setting::getValue("EnableTranslations")) {
            WHMCS\Language\DynamicTranslation::saveNewTranslations($customFieldIDid, ["custom_field.{id}.name", "custom_field.{id}.description"]);
        }
    }
    $productConfigOptionsChanged = false;
    $productConfigLinks = WHMCS\Database\Capsule::table("tblproductconfiglinks")->where("pid", "=", $id)->get()->all();
    $existingConfigLinks = [];
    foreach ($productConfigLinks as $productConfigLink) {
        if (!in_array($productConfigLink->gid, $configoptionlinks) && $productConfigOptionsChanged === false) {
            $productConfigOptionsChanged = true;
            $changes[] = "Assigned Configurable Option Groups Modified";
        }
        $existingConfigLinks[] = $productConfigLink->gid;
    }
    delete_query("tblproductconfiglinks", ["pid" => $id]);
    if (isset($configoptionlinks) && is_array($configoptionlinks)) {
        foreach ($configoptionlinks as $gid) {
            if (!in_array($gid, $existingConfigLinks) && $productConfigOptionsChanged === false) {
                $productConfigOptionsChanged = true;
                $changes[] = "Assigned Configurable Option Groups Modified";
            }
            insert_query("tblproductconfiglinks", ["gid" => $gid, "pid" => $id]);
        }
    }
    rebuildModuleHookCache();
    run_hook("ProductEdit", array_merge(["pid" => $id], $array));
    run_hook("AdminProductConfigFieldsSave", ["pid" => $id]);
    $redirectURL = "action=edit&id=" . $id . ($tab ? "&tab=" . $tab : "") . "&success=true";
    if ($setupFeeReset) {
        $redirectURL .= "&setupReset=true";
    }
    if ($prorataDisabled) {
        $redirectURL .= "&prorata=disabled";
    }
    if ($changes) {
        logAdminActivity("Product Configuration Modified: " . implode(". ", $changes) . ". Product ID: " . $product->id);
    }
    redir($redirectURL);
}
if ($sub == "deletecustomfield") {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Products/Services");
    $fid = (int) $whmcs->get_req_var("fid");
    $customField = WHMCS\CustomField::find($fid);
    logAdminActivity("Product Configuration Modified: Custom Field Deleted: '" . $customField->fieldName . "' - Product ID: " . $id);
    $customField->delete();
    redir("action=edit&id=" . $id . "&tab=" . $tab);
}
if ($action == "duplicatenow") {
    check_token("WHMCS.admin.default");
    checkPermission("Create New Products/Services");
    $existingproduct = (int) $whmcs->get_req_var("existingproduct");
    $newproductname = $whmcs->get_req_var("newproductname");
    try {
        $existingProduct = WHMCS\Product\Product::findOrFail($existingproduct);
        $newProduct = $existingProduct->duplicate($newproductname);
        $newProduct->displayOrder++;
        $newProduct->save();
        $newProduct->createSlug();
    } catch (WHMCS\Exception $e) {
        logAdminActivity("Failed to duplicate product ID " . $existingproduct . ": " . $e->getMessage());
        throw $e;
    }
    logAdminActivity("Product Duplicated: '" . $existingProduct->name . "' to '" . $newproductname . "' - Product ID: " . $newProduct->id);
    redir("action=edit&id=" . $newProduct->id);
}
if ($sub == "savegroup") {
    check_token("WHMCS.admin.default");
    checkPermission("Manage Product Groups");
    $ids = (int) $whmcs->get_req_var("ids");
    $name = $whmcs->get_req_var("name");
    $slug = $whmcs->get_req_var("slug");
    $orderFormTemplate = $whmcs->get_req_var("orderformtemplate");
    $hidden = (int) (bool) $whmcs->get_req_var("hidden");
    $headline = $whmcs->get_req_var("headline");
    $tagline = $whmcs->get_req_var("tagline");
    $systemOrderFormTemplate = WHMCS\Config\Setting::getValue("OrderFormTemplate");
    $groupExists = true;
    try {
        $group = WHMCS\Product\Group::findOrFail($ids);
    } catch (Exception $e) {
        $group = new WHMCS\Product\Group();
        $groupExists = false;
    }
    $slugError = NULL;
    foreach (WHMCS\Admin\Setup\ProductSetup::VALIDATION_CHECKS as $validationCheck) {
        try {
            $checkResult = $group->{$validationCheck}($slug);
        } catch (WHMCS\Exception\Validation\DuplicateValue $e) {
            $checkResult = "slugDuplicate";
        } catch (WHMCS\Exception\Validation\InvalidValue $e) {
            $checkResult = $e->getMessage();
            if (!in_array($checkResult, [WHMCS\Product\Group::INVALID_EMPTY, WHMCS\Product\Group::INVALID_HYPHEN, WHMCS\Product\Group::INVALID_NUMERIC])) {
                throw new WHMCS\Exception($checkResult);
            }
        }
        if ($checkResult !== true) {
            $slugError = $checkResult;
            if ($slugError) {
                $action = $groupExists ? "action=editgroup&ids=" . $group->id : "action=creategroup";
                redir($action . "&slugerror=" . $slugError . "&slug=" . $slug);
            }
            try {
                $orderFormTemplates = WHMCS\View\Template\OrderForm::all();
                if (!$ids || $whmcs->get_req_var("orderfrmtpl") == "custom") {
                    if ($orderFormTemplate == $systemOrderFormTemplate || !$orderFormTemplates->has($orderFormTemplate)) {
                        $orderFormTemplate = "";
                    }
                } else {
                    $orderFormTemplate = "";
                }
            } catch (WHMCS\Exception $e) {
                $aInt->gracefulExit("Order Form Templates directory is missing. Please reupload /templates/orderforms/");
            }
            $disabledGateways = [];
            $gateways2 = getGatewaysArray();
            foreach ($gateways2 as $gateway => $gatewayName) {
                if (!$gateways[$gateway]) {
                    $disabledGateways[] = $gateway;
                }
            }
            $changes = [];
            if ($ids) {
                if ($name != $group->name) {
                    $changes[] = "Name Modified: '" . $group->name . "' to '" . $name . "'";
                }
                if ($orderFormTemplate != $group->orderFormTemplate) {
                    $changes[] = "Order Form Template Modified: '" . $group->orderFormTemplate . "' to '" . $orderFormTemplate . "'";
                }
                if ($disabledGateways != $group->disabledPaymentGateways) {
                    $changes[] = "Disabled Payment Gateways Modified";
                }
                if ($hidden != $group->isHidden) {
                    if ($hidden) {
                        $changes[] = "Group Hidden";
                    } else {
                        $changes[] = "Group Displayed";
                    }
                }
                if ($headline != $group->headline) {
                    $changes[] = "Headline Modified: '" . $group->headline . "' to '" . $headline . "'";
                }
                if ($tagline != $group->tagline) {
                    $changes[] = "Tagline Modified: '" . $group->tagline . "' to '" . $tagline . "'";
                }
                if ($slug != $group->slug) {
                    $changes[] = "Product Group URL Modified: '" . $group->slug . "' to '" . $slug . "'";
                }
            } else {
                $group = new WHMCS\Product\Group();
                $group->displayOrder = WHMCS\Database\Capsule::table("tblproductgroups")->max("order") + 1;
            }
            $group->name = $name;
            $group->slug = $slug;
            $group->orderFormTemplate = $orderFormTemplate;
            $group->disabledPaymentGateways = $disabledGateways;
            $group->isHidden = $hidden;
            $group->headline = $headline;
            $group->tagline = $tagline;
            $group->save();
            if ($ids) {
                if ($changes) {
                    logAdminActivity("Product Group Modified: '" . $group->name . "' - Changes: " . implode(". ", $changes) . " - Product Group ID: " . $group->id);
                }
            } else {
                logAdminActivity("Product Group Created: '" . $group->name . "' - Product Group ID: " . $group->id);
            }
            App::redirect("configproducts.php", "action=editgroup&ids=" . $group->id . "&success=true");
        }
    }
}
if ($sub == "deletegroup") {
    check_token("WHMCS.admin.default");
    checkPermission("Manage Product Groups");
    $groupId = (int) $whmcs->get_req_var("id");
    $group = WHMCS\Product\Group::find($groupId);
    logAdminActivity("Product Group Deleted: '" . $group->name . "' - Product Group ID: " . $group->id);
    $group->delete();
    App::redirect("configproducts.php", "groupdeleted=true");
}
if ($sub == "delete") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Products/Services");
    run_hook("ProductDelete", ["pid" => $id]);
    logAdminActivity("Product Deleted: '" . $product->name . "' - Product Group ID: " . $product->id);
    $product->delete();
    delete_query("tblproductconfiglinks", ["pid" => $id]);
    WHMCS\CustomField::where("type", "=", "product")->where("relid", "=", $id)->delete();
    redir();
}
if ($action == "updatesort") {
    check_token("WHMCS.admin.default");
    $order = (array) $whmcs->get_req_var("order");
    foreach ($order as $sort => $item) {
        $properties = explode("|", $item);
        list($type, $groupId, $itemId) = $properties;
        if ($type == "group") {
            checkPermission("Manage Product Groups");
            $group = WHMCS\Product\Group::find($groupId);
            if ($group->displayOrder != $sort) {
                logAdminActivity("Group Modified: '" . $group->name . "'" . " - Display Order Modified: '" . $group->displayOrder . "' to '" . $sort . "' - Group ID: " . $group->id);
                $group->displayOrder = $sort;
                $group->save();
            }
        } else {
            if ($type == "bundle") {
                checkPermission("Edit Products/Services");
                $bundle = WHMCS\Database\Capsule::table("tblbundles")->find($itemId);
                if ($bundle->sortorder != $sort) {
                    logAdminActivity("Bundle Modified: '" . $bundle->name . "'" . " - Display Order Modified: '" . $bundle->displayOrder . "' to '" . $sort . "' - Bundle ID: " . $bundle->id);
                    WHMCS\Database\Capsule::table("tblbundles")->where("id", "=", $itemId)->update(["sortorder" => $sort]);
                }
            } else {
                checkPermission("Edit Products/Services");
                $product = WHMCS\Product\Product::find($itemId);
                if ($product->displayOrder != $sort) {
                    logAdminActivity("Product Modified: '" . $product->name . "'" . " - Display Order Modified: '" . $product->displayOrder . "' to '" . $sort . "' - Product ID: " . $product->id);
                    $product->displayOrder = $sort;
                    $product->save();
                }
            }
        }
    }
    $aInt->setBodyContent(["success" => true]);
    $aInt->output();
    WHMCS\Terminus::getInstance()->doExit();
}
if ($action == "add-feature") {
    check_token("WHMCS.admin.default");
    $groupId = (int) $whmcs->get_req_var("groupId");
    if (!$groupId) {
        WHMCS\Terminus::getInstance()->doExit();
    }
    $newFeature = $whmcs->get_req_var("feature");
    $feature = new WHMCS\Product\Group\Feature();
    $feature->productGroupId = $groupId;
    $feature->feature = $newFeature;
    $maxOrder = WHMCS\Product\Group\Feature::orderBy("order", "desc")->where("product_group_id", "=", $groupId)->first(["order"]);
    $feature->order = $maxOrder->order + 1;
    $feature->save();
    $output = [];
    $output["html"] = "<div class=\"list-group-item\" data-id=\"" . $feature->id . "\">\n    <span class=\"badge remove-feature\" data-id=\"" . $feature->id . "\">\n        <i class=\"glyphicon glyphicon-remove\"></i>\n    </span>\n    <span class=\"glyphicon glyphicon-move\" aria-hidden=\"true\"></span>\n    " . $feature->feature . "\n</div>";
    $output["message"] = AdminLang::trans("products.featureAddSuccess");
    $aInt->setBodyContent($output);
    $aInt->display();
    logAdminActivity("Product Group Modified: Feature Added: '" . $feature->feature . "' - Product Group ID: " . $groupId);
    WHMCS\Terminus::getInstance()->doExit();
}
if ($action == "remove-feature") {
    check_token("WHMCS.admin.default");
    $groupId = (int) $whmcs->get_req_var("groupId");
    $featureId = (int) $whmcs->get_req_var("feature");
    if (!$groupId || !$featureId) {
        WHMCS\Terminus::getInstance()->doExit();
    }
    $feature = WHMCS\Product\Group\Feature::find($featureId);
    logAdminActivity("Product Group Modified: Feature Removed: '" . $feature->feature . "' - Product Group ID: " . $groupId);
    $feature->delete();
    echo AdminLang::trans("products.featureDeleteSuccess");
    WHMCS\Terminus::getInstance()->doExit();
}
if ($action == "feature-sort") {
    check_token("WHMCS.admin.default");
    $order = (array) $whmcs->get_req_var("order");
    $features = WHMCS\Product\Group\Feature::whereIn("id", array_values($order))->get();
    $productGroupId = 0;
    foreach ($features as $feature) {
        $feature->order = array_search($feature->id, $order);
        $feature->save();
        $productGroupId = $feature->productGroupId;
    }
    if ($productGroupId) {
        logAdminActivity("Product Group Modified: Feature Sort Updated - Product Group ID: " . $productGroupId);
    }
    echo AdminLang::trans("products.featureSortSuccess");
    WHMCS\Terminus::getInstance()->doExit();
}
ob_start();
if ($action == "") {
    $result = select_query("tblproductgroups", "COUNT(*)", "");
    $data = mysql_fetch_array($result);
    $num_rows = $data[0];
    $result = select_query("tblproducts", "COUNT(*)", "");
    $data = mysql_fetch_array($result);
    $num_rows2 = $data[0];
    $jscode .= "var productId = 0,\n    groupId = 0,\n    bundleId = 0;";
    echo $aInt->modalWithConfirmation("doDelete", AdminLang::trans("products.deleteproductconfirm"), $whmcs->getPhpSelf() . "?sub=delete&id=", "productId");
    echo $aInt->modalWithConfirmation("doGroupDelete", AdminLang::trans("products.deletegroupconfirm"), $whmcs->getPhpSelf() . "?sub=deletegroup&id=", "groupId");
    echo $aInt->modalWithConfirmation("doBundleDelete", AdminLang::trans("bundles.deletebundleconfirm"), WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl() . "/configbundles.php?action=delete&id=", "bundleId");
    echo $aInt->modal("groupDeleteError", AdminLang::trans("products.deletegrouptitle"), AdminLang::trans("products.deletegrouperror"), [["title" => AdminLang::trans("global.ok")]]);
    echo $aInt->modal("deleteProductError", AdminLang::trans("products.deleteproducttitle"), AdminLang::trans("products.deleteproducterror"), [["title" => AdminLang::trans("global.ok")]]);
    $marketConnectInactiveServices = [];
    $showMarketConnectPromos = true;
    $dismissedProductPromotions = json_decode(WHMCS\Config\Setting::getValue("MarketConnectDismissedPromos"), true);
    if (!is_array($dismissedProductPromotions)) {
        $dismissedProductPromotions = [];
    }
    if (array_key_exists($aInt->getAdminID(), $dismissedProductPromotions)) {
        $version = App::getVersion()->getVersion();
        if (version_compare($dismissedProductPromotions[$aInt->getAdminID()], $version) != -1) {
            $showMarketConnectPromos = false;
        }
    }
    if ($showMarketConnectPromos) {
        $marketConnectInactiveServices = array_keys(WHMCS\MarketConnect\MarketConnect::getServicesToPromote());
    }
    $marketConnectPromos = [];
    $learnMore = AdminLang::trans("global.learnMore");
    foreach ($marketConnectInactiveServices as $mcService) {
        $promotionInfo = WHMCS\MarketConnect\MarketConnect::SERVICES[$mcService];
        if ($promotionInfo) {
            $logo = WHMCS\View\Asset::imgTag(file_exists("../assets/img/marketconnect/" . $mcService . "/logo-sml.svg") ? "marketconnect/" . $mcService . "/logo-sml.svg" : "marketconnect/" . $mcService . "/logo-sml.png", $promotionInfo["serviceTitle"]);
            $title = AdminLang::trans("marketConnect.add", [":product" => $promotionInfo["vendorName"] . " " . $promotionInfo["serviceTitle"]]);
            $href = "marketconnect.php?learnmore=" . $mcService;
            $marketConnectPromos[] = "<a href=\"" . $href . "\" target=\"_blank\" class=\"mc-promo bordered clearfix\">\n    <div class=\"logo\">" . $logo . "</div>\n    <div class=\"content\">\n        <h2 class=\"truncate\">" . $title . "</h2>\n        <p>" . $promotionInfo["description"] . "</p>\n    </div>\n</a>";
        }
    }
    $marketConnectPromosOutput = "";
    if (0 < count($marketConnectPromos)) {
        $marketConnectPromosOutput = "<div class=\"pull-right\">\n    <a href=\"#\" id=\"dismissPromos\"><i class=\"fal fa-times\"></i></a>\n</div>\n<div class=\"product-mc-promos\">\n    <div class=\"owl-carousel owl-theme\" id=\"mcConfigureProductPromos\">";
        foreach ($marketConnectPromos as $promo) {
            $marketConnectPromosOutput .= "<div class=\"item\">" . $promo . "</div>";
        }
        $marketConnectPromosOutput .= "</div>\n</div>";
    }
    $groupDeleted = "";
    if (App::getFromRequest("groupdeleted")) {
        $groupDeleted = infoBox(AdminLang::trans("global.changesuccess"), AdminLang::trans("global.changesuccessdesc"), "success");
    }
    $applicableProductsCount = $num_rows2 - WHMCS\Product\Product::marketConnect()->count();
    echo "\n<p>";
    echo $aInt->lang("products", "description");
    echo "</p>\n\n<div class=\"btn-group\" role=\"group\">\n    <a id=\"Create-Group-link\" href=\"";
    echo $whmcs->getPhpSelf();
    echo "?action=creategroup\" class=\"btn btn-default\"><i class=\"fas fa-plus\"></i> ";
    echo $aInt->lang("products", "createnewgroup");
    echo "</a>\n    <a id=\"Create-Product-link\" href=\"";
    echo $whmcs->getPhpSelf();
    echo "?action=create\" class=\"btn btn-default";
    if ($num_rows == 0) {
        echo " btn-disabled\" disabled=\"disabled";
    }
    echo "\"><i class=\"fas fa-plus-circle\"></i> ";
    echo $aInt->lang("products", "createnewproduct");
    echo "</a>\n    <a id=\"Duplicate-Product-link\" href=\"";
    echo $whmcs->getPhpSelf();
    echo "?action=duplicate\" class=\"btn btn-default";
    echo 0 < $applicableProductsCount ? "" : " btn-disabled disabled\" disabled=\"disabled";
    echo "\"><i class=\"fas fa-plus-square\"></i> ";
    echo $aInt->lang("products", "duplicateproduct");
    echo "</a>\n</div>\n\n";
    echo $marketConnectPromosOutput;
    echo "        \n        ";
    echo $groupDeleted;
    echo "\n<div id=\"tableBackground\" class=\"tablebg\">\n    <table class=\"datatable no-margin\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n        <tr>\n            <th style=\"width: 23%;\">";
    echo $aInt->lang("products", "productname");
    echo "</th>\n            <th style=\"width: 18%;\">";
    echo $aInt->lang("fields", "type");
    echo "</th>\n            <th style=\"width: 18%;\">";
    echo $aInt->lang("products", "paytype");
    echo "</th>\n            <th style=\"width: 17%;\">";
    echo $aInt->lang("products", "stock");
    echo "</th>\n            <th style=\"width: 18%;\">";
    echo $aInt->lang("products", "autosetup");
    echo "</th>\n            <th style=\"width: 2%;\"></th>\n            <th style=\"width: 2%;\"></th>\n            <th style=\"width: 2%;\"></th>\n        </tr>\n    </table>\n";
    $result = select_query("tblproductgroups", "", "", "order", "DESC");
    $data = mysql_fetch_array($result);
    $lastorder = $data["order"] ?? NULL;
    $result2 = select_query("tblproductgroups", "", "", "order", "ASC");
    $k = 0;
    while ($data = mysql_fetch_array($result2)) {
        $k++;
        $groupid = $data["id"];
        update_query("tblproductgroups", ["order" => $k], ["id" => $groupid]);
        $name = $data["name"];
        $hidden = $data["hidden"];
        $order = $data["order"];
        $result = select_query("tblproducts", "COUNT(*)", ["gid" => $groupid]);
        $data = mysql_fetch_array($result);
        $num_rows = $data[0];
        if (0 < $num_rows) {
            $deletelink = "jQuery('#modalgroupDeleteError').modal('show'); return false";
        } else {
            $deletelink = "doGroupDelete('" . $groupid . "')";
        }
        echo "\n    <table class=\"datatable sort-groups no-margin\" data-id=\"group|" . $groupid . "|0\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n        <tr>\n            <td colspan=\"6\" style=\"width: 96%; background-color:#f3f3f3;\">\n                <div class=\"prodGroup\" align=\"left\">\n                    &nbsp;\n                    <span class=\"glyphicon glyphicon-move\" aria-hidden=\"true\"></span>\n                    &nbsp;<strong>" . $aInt->lang("fields", "groupname") . ":</strong>\n                    " . $name . " ";
        if ($hidden) {
            echo "(Hidden) ";
        }
        echo "\n                </div>\n            </td>\n            <td style=\"width: 2%; background-color:#f3f3f3;\" align=center>\n                <a href=\"?action=editgroup&ids=" . $groupid . "\">\n                    <img src=\"images/edit.gif\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\">\n                </a>\n            </td>\n            <td style=\"width: 2%; background-color:#f3f3f3;\" align=center>\n                <a href=\"#\" onClick=\"" . $deletelink . ";return false\">\n                    <img src=\"images/delete.gif\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\">\n                </a>\n            </td>\n        </tr>\n";
        $basicProductExpression = WHMCS\Database\Capsule::connection()->raw("`id`,`type`,`name`,`paytype`,`autosetup`,`proratabilling`,`stockcontrol`,`qty`,`servertype`,`hidden`,`order`,\n(SELECT COUNT(*) FROM tblhosting WHERE tblhosting.packageid=tblproducts.id) AS usagecount");
        $basicProducts = WHMCS\Database\Capsule::table("tblproducts")->select($basicProductExpression)->where("gid", "=", $groupid)->orderBy("order")->orderBy("name")->get()->all();
        $bundleProductExpression = WHMCS\Database\Capsule::connection()->raw("id, name, sortorder as `order`");
        $bundleProducts = WHMCS\Database\Capsule::table("tblbundles")->select($bundleProductExpression)->where("gid", "=", $groupid)->orderBy("order")->orderBy("name")->get()->all();
        $fillColumns = ["type", "paytype", "autosetup", "proratabilling", "stockcontrol", "qty", "servertype", "hidden", "usagecount"];
        foreach ($bundleProducts as $row => $bundle) {
            foreach ($fillColumns as $column) {
                if ($column === "paytype") {
                    $bundle->{$column} = "-";
                } else {
                    if ($column === "type") {
                        $bundle->{$column} = "bundle";
                    } else {
                        $bundle->{$column} = "";
                    }
                }
            }
            $bundleProducts[$row] = $bundle;
        }
        $outputs = array_merge($basicProducts, $bundleProducts);
        usort($outputs, function ($a, $b) {
            $ordering = strnatcmp($a->order, $b->order);
            if ($ordering) {
                return $ordering;
            }
            return strnatcmp($a->name, $b->name);
        });
        $i = 0;
        echo "<tbody id=\"tbodyGroupProduct" . $groupid . "\" class=\"list-group\">";
        foreach ($outputs as $output) {
            $id = $output->id;
            $type = $output->type;
            $name = $output->name;
            $paytype = $output->paytype;
            $autosetup = $output->autosetup;
            $proratabilling = $output->proratabilling;
            $stockcontrol = $output->stockcontrol;
            $qty = $output->qty;
            $hidden = $output->hidden;
            $sortorder = $output->order;
            $num_rows = $output->usagecount;
            $moduleName = $output->servertype;
            if ($moduleName) {
                $module = new WHMCS\Module\Server();
                $module->load($moduleName);
                $moduleDisplayName = $module->getDisplayName();
            } else {
                $moduleDisplayName = "";
            }
            if (0 < $num_rows) {
                $deletelink = "jQuery('#modaldeleteProductError').modal('show'); return false";
            } else {
                $deletelink = "doDelete('" . $id . "')";
            }
            if ($autosetup == "on") {
                $autosetup = $aInt->lang("products", "asetupafteracceptpendingorder");
            } else {
                if ($autosetup == "order") {
                    $autosetup = $aInt->lang("products", "asetupinstantlyafterorder");
                } else {
                    if ($autosetup == "payment") {
                        $autosetup = $aInt->lang("products", "asetupafterpay");
                    } else {
                        if ($autosetup == "") {
                            $autosetup = $aInt->lang("products", "off");
                        }
                    }
                }
            }
            if ($paytype == "free") {
                $paymenttype = AdminLang::trans("billingcycles.free");
            } else {
                if ($paytype == "onetime") {
                    $paymenttype = AdminLang::trans("billingcycles.onetime");
                } else {
                    if ($paytype == "-") {
                        $paymenttype = "-";
                    } else {
                        $paymenttype = AdminLang::trans("status.recurring");
                    }
                }
            }
            if ($proratabilling) {
                $paymenttype .= " (" . $aInt->lang("products", "proratabilling") . ")";
            }
            $editLink = "<a href=\"?action=edit&id=" . $id . "\">\n        <img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("global.edit") . "\">\n    </a>";
            $deleteLink = "<a href=\"#\" onClick=\"" . $deletelink . ";return false\">\n        <img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("global.delete") . "\">\n    </a>";
            $sortOrderName = "so[" . $id . "]";
            if ($type == "hostingaccount") {
                $producttype = AdminLang::trans("products.hostingaccount");
            } else {
                if ($type == "reselleraccount") {
                    $producttype = AdminLang::trans("products.reselleraccount");
                } else {
                    if ($type == "server") {
                        $producttype = AdminLang::trans("products.dedicatedvpsserver");
                    } else {
                        if ($type == "bundle") {
                            $producttype = AdminLang::trans("products.bundle");
                            $editLink = "<a href=\"configbundles.php?action=manage&id=" . $id . "\">\n        <img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("global.edit") . "\">\n    </a>";
                            $deleteLink = "<a href=\"#\" onClick=\"doBundleDelete('" . $id . "');return false\">\n        <img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("global.delete") . "\">\n    </a>";
                            $sortOrderName = "sob[" . $id . "]";
                        } else {
                            $producttype = AdminLang::trans("products.otherproductservice");
                        }
                    }
                }
            }
            if ($moduleDisplayName) {
                $producttype .= " (" . $moduleDisplayName . ")";
            }
            if ($stockcontrol) {
                $qtystock = $qty;
            } else {
                $qtystock = "-";
            }
            if ($hidden) {
                $name .= " (Hidden)";
                $hidden = " style=\"background-color:#efefef;\"";
            } else {
                $hidden = "";
            }
            echo "    <tr class=\"product text-center\" data-id=\"" . $type . "|" . $groupid . "|" . $id . "\">\n        <td style=\"width: 23%;\" class=\"text-left\"" . $hidden . ">" . $name . "</td>\n        <td style=\"width: 18%;\" " . $hidden . ">" . $producttype . "</td>\n        <td style=\"width: 18%;\" " . $hidden . ">" . $paymenttype . "</td>\n        <td style=\"width: 17%;\" " . $hidden . ">" . $qtystock . "</td>\n        <td style=\"width: 18%;\" " . $hidden . ">" . $autosetup . "</td>\n        <td style=\"width: 2%;\" " . $hidden . ">\n            <span class=\"glyphicon glyphicon-move\" aria-hidden=\"true\"></span>\n        </td>\n        <td style=\"width: 2%;\" " . $hidden . ">" . $editLink . "</td>\n        <td style=\"width: 2%;\" " . $hidden . ">" . $deleteLink . "</td>\n    </tr>";
            $i++;
        }
        echo "\n</tbody>\n";
        if ($i == "0") {
            echo "\n            <tr>\n                <td colspan=\"8\" align=center>" . $aInt->lang("products", "noproductsingroupsetup") . "\n                </td>\n            </tr>\n        ";
        }
        echo "</table>\n";
        $i = 0;
    }
    if ($k == "0") {
        echo "\n        <table class=\"datatable no-margin\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n            <tr>\n                <td colspan=10 align=center>" . $aInt->lang("products", "nogroupssetup") . "</td>\n            </tr>\n        </table>\n    ";
    }
    echo "</div>\n\n";
    echo WHMCS\View\Asset::jsInclude("Sortable.min.js");
    echo "<script>\nvar successMsgShowing = false;\nvar sortOptions = {\n    handle: '.glyphicon-move',\n    ghostClass: 'ghost',\n    animation: 150,\n    store: {\n        /**\n         * Get the order of elements. Called once during initialization.\n         * @param   {Sortable}  sortable\n         * @returns {Array}\n         */\n        get: function (sortable) {\n            // Do nothing upon initialization.\n            return [];\n        },\n\n        /**\n         * Save the order of elements. Called onEnd (when the item is dropped).\n         * @param {Sortable}  sortable\n         */\n        set: function (sortable) {\n            var order = sortable.toArray();\n            var post = WHMCS.http.jqClient.post(\n                \"configproducts.php\",\n                {\n                    action: \"updatesort\",\n                    order: order,\n                    token: \"";
    echo generate_token("plain");
    echo "\"\n                }\n            );\n\n            post.done(\n                function(data) {\n                    ";
    echo WHMCS\View\Helper::jsGrowlNotification("success", "global.success", "global.changesuccessdesc");
    echo "                }\n            );\n        }\n    }\n};\n\n// Handle product/bundle sorting.\njQuery('*[id^=\"tbodyGroupProduct\"]').each(function(index, group) {\n    Sortable.create(group, sortOptions);\n});\n\n// Handle Group sorting.\nsortOptions.draggable = \".sort-groups\";\nsortOptions.group = { name: 'groups', pull: true, put: true };\nSortable.create(tableBackground, sortOptions);\n\n\$(document).ready(function () {\n    \$('.product-promo-carousel').owlCarousel({\n        items: 1,\n        loop: true,\n        center: true,\n        mouseDrag: true,\n        touchDrag: true,\n        autoplay: true,\n        autoplayTimeout: 4000,\n        autoplayHoverPause: true\n    });\n});\n</script>\n\n";
} else {
    if ($action == "edit") {
        echo WHMCS\View\Asset::jsInclude("Sortable.min.js");
        try {
            $product = WHMCS\Product\Product::with("activeSlug", "productGroup")->findOrFail($id);
        } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            redir("", "configproducts.php");
        }
        try {
            $defaultTemplateName = WHMCS\View\Template\OrderForm::getDefault()->getName();
        } catch (Throwable $e) {
            $defaultTemplateName = WHMCS\View\Template\OrderForm::defaultName();
            infoBox(AdminLang::trans("global.erroroccurred"), AdminLang::trans("products.orderFormsNotFound"), "error");
        }
        $id = $product->id;
        $type = $product->type;
        $groupid = $gid = $product->productGroupId;
        $groupSlug = $product->productGroup->slug;
        $name = $product->getRawAttribute("name");
        $slug = $product->activeSlug ? $product->activeSlug->slug : "";
        $description = $product->getRawAttribute("description");
        $showdomainops = $product->showDomainOptions;
        $hidden = $product->isHidden;
        $welcomeemail = $product->welcomeEmailTemplateId;
        $paytype = $product->paymentType;
        $allowqty = $product->allowMultipleQuantities;
        $subdomain = $product->freeSubDomains ? implode(",", $product->freeSubDomains) : "";
        $autosetup = $product->autoSetup;
        $servergroup = $product->serverGroupId;
        $stockcontrol = $product->stockControlEnabled;
        $qty = $product->quantityInStock;
        $proratabilling = $product->proRataBilling;
        $proratadate = $product->proRataChargeDayOfCurrentMonth;
        $proratachargenextmonth = $product->proRataChargeNextMonthAfterDay;
        $servertype = $product->module;
        $freedomain = $product->freeDomain;
        $freedomainpaymentterms = $product->freeDomainPaymentTerms;
        $freedomaintlds = $product->freeDomainTlds;
        $recurringcycles = $product->recurringCycleLimit;
        $autoterminatedays = $product->daysAfterSignUpUntilAutoTermination;
        $autoterminateemail = $product->autoTerminationEmailTemplateId;
        $tax = $product->applyTax;
        $configoptionsupgrade = $product->allowConfigOptionUpgradeDowngrade;
        $upgradeemail = $product->upgradeEmailTemplateId;
        $overagesenabled = $product->enableOverageBillingAndUnits;
        $overagesdisklimit = $product->overageDiskLimit;
        $overagesbwlimit = $product->overageBandwidthLimit;
        $overagesdiskprice = $product->overageDiskPrice;
        $overagesbwprice = $product->overageBandwidthPrice;
        $affiliatepayamount = $product->affiliatePaymentAmount;
        $affiliatepaytype = $product->affiliatePaymentType;
        $affiliateonetime = $product->affiliatePayoutOnceOnly;
        $retired = $product->isRetired;
        $isFeatured = $product->isFeatured;
        $tagline = $product->tagline;
        $color = !empty($product->color) ? $product->color : "#9abb3a";
        $shortDescription = $product->shortDescription;
        $productRecommendations = $product->recommendations()->orderBy("pivot_sortorder")->get();
        $packageconfigoption = [];
        $counter = 1;
        while ($counter <= 24) {
            $var = "moduleConfigOption" . $counter;
            $packageconfigoption[$counter] = $product->{$var} ?? NULL;
            $counter += 1;
        }
        $upgradepackages = $product->getUpgradeProductIds();
        $downloadIds = $product->getDownloadIds();
        $order = $product->displayOrder;
        $server = new WHMCS\Module\Server();
        $serverModules = $server->getListWithDisplayNames();
        if ($servertype) {
            $server->load($servertype);
        }
        echo WHMCS\View\Asset::jsInclude("jquerylq.js") . WHMCS\View\Asset::jsInclude("jqueryFileTree.js") . WHMCS\View\Asset::cssInclude("jqueryFileTree.css");
        echo "<h2>Edit Product</h2>\n<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "\" name=\"packagefrm\" id=\"frmProductEdit\">\n<input type=\"hidden\" name=\"action\" value=\"save\">\n<input type=\"hidden\" name=\"id\" value=\"" . $id . "\" id=\"inputProductId\">";
        $jquerycode .= "\$('#productdownloadsbrowser').fileTree({ root: '0', script: 'configproducts.php?action=getdownloads" . generate_token("link") . "', folderEvent: 'click', expandSpeed: 1, collapseSpeed: 1 }, function(file) {\n    WHMCS.http.jqClient.post(\"configproducts.php?action=managedownloads&id=" . $id . generate_token("link") . "&adddl=\"+file, function(data) {\n        \$(\"#productdownloadslist\").html(data);\n    });\n});\n\$(\".removedownload\").livequery(\"click\", function(event) {\n    var dlid = \$(this).attr(\"rel\");\n    WHMCS.http.jqClient.post(\"configproducts.php?action=managedownloads&id=" . $id . generate_token("link") . "&remdl=\"+dlid, function(data) {\n        \$(\"#productdownloadslist\").html(data);\n    });\n});\n\$(\"#showquickupload\").click(\n    function() {\n        \$(\"#modalQuickUpload\").modal(\"show\");\n        \$(\"#modalQuickUploadBody\").load(\"configproducts.php?action=quickupload&id=" . $id . generate_token("link") . "\");\n        return false;\n    }\n);\n\$(\"#showadddownloadcat\").click(\n    function() {\n        \$(\"#modalAddDownloadCategory\").modal(\"show\");\n        \$(\"#modalAddDownloadCategoryBody\").load(\"configproducts.php?action=adddownloadcat&id=" . $id . generate_token("link") . "\");\n        return false;\n    }\n);\n";
        if ($success) {
            infoBox($aInt->lang("global", "changesuccess"), $aInt->lang("global", "changesuccessdesc") . " <div style=\"float:right;margin-top:-15px;\"><input type=\"button\" id=\"backToProductList\" value=\"&laquo; " . $aInt->lang("products", "backtoproductlist") . "\" onClick=\"window.location='configproducts.php'\" class=\"btn btn-default btn-sm\"></div>");
        }
        echo $infobox;
        if ($setupReset == "true") {
            infoBox($aInt->lang("global", "information"), $aInt->lang("products", "setupreset"));
            echo $infobox;
        }
        if (App::isInRequest("prorata") && App::getFromRequest("prorata") === "disabled") {
            infoBox(AdminLang::trans("global.information"), AdminLang::trans("products.prorataDisabled"));
            echo $infobox;
        }
        echo $aInt->beginAdminTabs([$aInt->lang("products", "tabsdetails"), $aInt->lang("global", "pricing"), $aInt->lang("products", "tabsmodulesettings"), $aInt->lang("setup", "customfields"), $aInt->lang("setup", "configoptions"), $aInt->lang("products", "tabsupgrades"), $aInt->lang("products", "tabsfreedomain"), AdminLang::trans("products.tabsRecommendations"), $aInt->lang("setup", "other"), $aInt->lang("products", "tabslinks")], true);
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "producttype");
        echo "</td>\n    <td class=\"fieldarea\">\n        <select id=\"selectType\" name=\"type\" class=\"form-control select-inline\" onChange=\"doFieldUpdate()\">\n            <option value=\"hostingaccount\"";
        if ($type == "hostingaccount") {
            echo " SELECTED";
        }
        echo ">\n                ";
        echo $aInt->lang("products", "hostingaccount");
        echo "            </option>\n            <option value=\"reselleraccount\"";
        if ($type == "reselleraccount") {
            echo " SELECTED";
        }
        echo ">\n                ";
        echo $aInt->lang("products", "reselleraccount");
        echo "            </option>\n            <option value=\"server\"";
        if ($type == "server") {
            echo " SELECTED";
        }
        echo ">\n                ";
        echo $aInt->lang("products", "dedicatedvpsserver");
        echo "            </option>\n            <option value=\"other\"";
        if ($type == "other") {
            echo " SELECTED";
        }
        echo ">\n                ";
        echo $aInt->lang("setup", "other");
        echo "            </option>\n        </select>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("products", "productgroup");
        echo "</td>\n    <td class=\"fieldarea\">\n        <select name=\"gid\" class=\"form-control select-inline\" id=\"inputGroup\">";
        $result = select_query("tblproductgroups", "", "", "order", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $select_gid = $data["id"];
            $select_name = $data["name"];
            echo "<option value=\"" . $select_gid . "\"";
            if ($select_gid == $groupid) {
                echo " selected";
            }
            echo ">" . $select_name . "</option>";
        }
        echo "        </select>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("products", "productname");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" size=\"40\" name=\"name\" value=\"";
        echo $name;
        echo "\" class=\"form-control input-400 input-inline\" id=\"inputProductName\">\n        ";
        echo $aInt->getTranslationLink("product.name", $id);
        echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel label-top\" width=\"25%\">\n        ";
        echo AdminLang::trans("products.productTagline");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"row\">\n            <div class=\"col-sm-7\">\n                <input type=\"text\" size=\"40\" name=\"tagline\" value=\"";
        echo $tagline;
        echo "\" class=\"form-control input-400 input-inline\">\n            </div>\n            <div class=\"col-sm-5\">\n                ";
        echo $aInt->getTranslationLink("product.tagline", $id);
        echo "                <p>";
        echo AdminLang::trans("products.productUsedForCrossSells");
        echo "</p>\n            </div>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo AdminLang::trans("products.slugURL");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"inline-edit-wrapper\">\n            <span id=\"spanRoutePath\">";
        echo fqdnRoutePath("store-product-group", $groupSlug) . "/";
        echo "</span>\n            <input type=\"text\"\n                   name=\"slug\"\n                   value=\"";
        echo $slug;
        echo "\"\n                   class=\"form-control input-inline inline-edit\"\n                   id=\"inputSlug\"\n                   style=\"width:";
        echo (strlen($slug) + 1) * 8;
        echo "px\"\n                   tabindex=\"-1\"\n            <span>\n                <button class=\"btn btn-sm\"\n                        id=\"btnCopyToClipboard\"\n                        type=\"button\"\n                        ";
        echo !$slug ? "disabled=\"disabled\"" : "";
        echo "                >\n                    <img src=\"../assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">\n                </button>\n            </span>\n            <span id=\"slugLoader\" class=\"hidden\">\n                <i class=\"fa fa-spinner fa-spin\"></i>\n                ";
        echo AdminLang::trans("products.slugValidate");
        echo "            </span>\n            <span id=\"slugOk\" class=\"text-success hidden\">\n                <i class=\"fa fa-check\"></i>\n                ";
        echo AdminLang::trans("global.ok");
        echo "            </span>\n            <span class=\"text-danger hidden\" id=\"slugInvalidError\"></span>\n            <span class=\"text-info\" id=\"slug-change-warning\" style=\"display:none;\">\n                <i class=\"fad fa-exclamation-triangle\"\n                   data-toggle=\"tooltip\"\n                   data-placement=\"top\"\n                   title=\"";
        echo AdminLang::trans("products.productSlugChanged");
        echo "\"\n                   id=\"slug-change-tooltip\"\n                ></i>\n            </span>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel label-top\">\n        ";
        echo AdminLang::trans("products.productShortDesc");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"row\">\n            <div class=\"col-sm-7\">\n                <textarea name=\"shortDescription\" rows=\"2\" class=\"form-control\">";
        echo $shortDescription;
        echo "</textarea>\n            </div>\n            <div class=\"col-sm-5\">\n                ";
        echo $aInt->getTranslationLink("product.short_description", $id);
        echo "                <p>";
        echo AdminLang::trans("products.productShortDescLimitRec");
        echo "</p>\n                <p>";
        echo AdminLang::trans("products.productUsedForCrossSells");
        echo "</p>\n            </div>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("products", "productdesc");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"row\">\n            <div class=\"col-sm-7\">\n                <textarea name=\"description\" rows=\"5\" class=\"form-control\">";
        echo WHMCS\Input\Sanitize::encode($description);
        echo "</textarea>\n            </div>\n            <div class=\"col-sm-5\">\n                    ";
        echo $aInt->getTranslationLink("product.description", $id);
        echo "<br />\n                    ";
        echo $aInt->lang("products", "htmlallowed");
        echo "<br>\n                    &lt;br /&gt; ";
        echo $aInt->lang("products", "htmlnewline");
        echo "<br>\n                    &lt;strong&gt;";
        echo $aInt->lang("products", "htmlbold");
        echo "&lt;/strong&gt; <b>";
        echo $aInt->lang("products", "htmlbold");
        echo "</b><br>\n                    &lt;em&gt;";
        echo $aInt->lang("products", "htmlitalics");
        echo "&lt;/em&gt; <i>";
        echo $aInt->lang("products", "htmlitalics");
        echo "</i>\n            </div>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\" width=\"25%\">\n        ";
        echo AdminLang::trans("products.productColor");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"row\">\n            <div class=\"col-sm-7\">\n                <input type=\"color\" name=\"color\" value=\"";
        echo $color;
        echo "\" class=\"form-control input-100 input-inline\">\n            </div>\n            <div class=\"col-sm-5\">\n                ";
        echo AdminLang::trans("products.productColorInfo");
        echo "            </div>\n        </div>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "welcomeemail");
        echo "</td><td class=\"fieldarea\"><select name=\"welcomeemail\" class=\"form-control select-inline\"><option value=\"0\">";
        echo $aInt->lang("global", "none");
        echo "</option>";
        $emails = ["Hosting Account Welcome Email", "Reseller Account Welcome Email", "Dedicated/VPS Server Welcome Email", "SHOUTcast Welcome Email", "Other Product/Service Welcome Email"];
        foreach ($emails as $email) {
            $mailTemplates = WHMCS\Mail\Template::where("type", "=", "product")->where("name", "=", $email)->where("language", "=", "")->get();
            foreach ($mailTemplates as $template) {
                echo "<option value=\"" . $template->id . "\"";
                if ($template->id == $welcomeemail) {
                    echo " selected";
                }
                echo ">" . $template->name . "</option>";
            }
        }
        $customProductMailTemplates = WHMCS\Mail\Template::where("type", "=", "product")->where("custom", "=", 1)->where("language", "=", "")->orderBy("name")->get();
        foreach ($customProductMailTemplates as $template) {
            echo "<option value=\"" . $template->id . "\"";
            if ($template->id == $welcomeemail) {
                echo " selected";
            }
            echo ">" . $template->name . "</option>";
        }
        echo "</select></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "requiredomain");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"showdomainops\"";
        if ($showdomainops) {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("products", "domainregoptionstick");
        echo "</label></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "stockcontrol");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"stockcontrol\"";
        if ($stockcontrol) {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("products", "stockcontroldesc");
        echo ":</label> <input type=\"text\" name=\"qty\" value=\"";
        echo $qty;
        echo "\" class=\"form-control input-80 input-inline text-center\"></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "applytax");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"tax\"";
        if ($tax == "1") {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("products", "applytaxdesc");
        echo "</label></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo AdminLang::trans("fields.featured");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"isFeatured\"";
        if ($isFeatured) {
            echo " checked";
        }
        echo "> ";
        echo AdminLang::trans("products.featuredDescription");
        echo "</label></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "hidden");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"hidden\"";
        if ($hidden) {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("products", "hiddendesc");
        echo "</label></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("products", "retired");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <label class=\"checkbox-inline\">\n            <input id=\"inputRequired\" type=\"checkbox\" name=\"retired\" value=\"1\"";
        if ($retired) {
            echo " checked";
        }
        echo ">\n            ";
        echo $aInt->lang("products", "retireddesc");
        echo "        </label>\n    </td>\n</tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    ";
        if ($server->isMetaDataValueSet("NoEditPricing") && $server->getMetaDataValue("NoEditPricing")) {
            $configurationLink = $server->call("get_configuration_link", ["model" => $product]);
            echo "<input type=\"hidden\" name=\"paytype\" value=\"" . $paytype . "\" />" . "<div class=\"marketconnect-product-redirect\" role=\"alert\">\n                " . AdminLang::trans("products.marketConnectManageRedirectMsg") . "<br>\n                <a href=\"" . $configurationLink . "\" class=\"btn btn-default btn-sm\">" . AdminLang::trans("products.marketConnectManageRedirectBtn") . "</a>\n            </div>";
        } else {
            echo "<tr><td class=\"fieldlabel\">";
            echo $aInt->lang("products", "paymenttype");
            echo "</td><td class=\"fieldarea\"><label class=\"radio-inline\"><input type=\"radio\" name=\"paytype\" id=\"PayType-Free\" value=\"free\" onclick=\"hidePricingTable()\"";
            if ($paytype == "free") {
                echo " checked";
            }
            echo "> ";
            echo $aInt->lang("billingcycles", "free");
            echo "</label> <label class=\"radio-inline\"><input type=\"radio\" name=\"paytype\" value=\"onetime\" id=\"PayType-OneTime\" onclick=\"showPricingTable(false)\"";
            if ($paytype == "onetime") {
                echo " checked";
            }
            echo "> ";
            echo $aInt->lang("billingcycles", "onetime");
            echo "</label> <label class=\"radio-inline\"><input type=\"radio\" name=\"paytype\" value=\"recurring\" id=\"PayType-Recurring\" onclick=\"showPricingTable(true)\"";
            if ($paytype == "recurring") {
                echo " checked";
            }
            echo "> ";
            echo $aInt->lang("global", "recurring");
            echo "</label></td></tr>\n";
            $pricingTblStyle = $paytype === WHMCS\Product\Product::PAYMENT_ONETIME ? "style=\"max-width: 370px;\"" : "";
            $payTypeStyle = $paytype === WHMCS\Product\Product::PAYMENT_ONETIME ? "style=\"display: none;\"" : "";
            echo "<tr id=\"trPricing\"";
            if ($paytype == "free") {
                echo " style=\"display:none;\"";
            }
            echo "><td colspan=\"2\" align=\"center\"><br>\n    <div class=\"row\">\n        <div class=\"col-sm-10 col-sm-offset-1\">\n            <table id=\"pricingtbl\" class=\"table table-condensed\" ";
            echo $pricingTblStyle;
            echo ">\n                <tr bgcolor=\"#efefef\" style=\"text-align:center;font-weight:bold\">\n                    <td>";
            echo $aInt->lang("currencies", "currency");
            echo "</td>\n                    <td></td>\n                    <td>";
            echo $aInt->lang("billingcycles", "onetime");
            echo "/";
            echo $aInt->lang("billingcycles", "monthly");
            echo "</td>\n                    <td class=\"prod-pricing-recurring\" ";
            echo $payTypeStyle;
            echo ">";
            echo $aInt->lang("billingcycles", "quarterly");
            echo "</td>\n                    <td class=\"prod-pricing-recurring\" ";
            echo $payTypeStyle;
            echo ">";
            echo $aInt->lang("billingcycles", "semiannually");
            echo "</td>\n                    <td class=\"prod-pricing-recurring\" ";
            echo $payTypeStyle;
            echo ">";
            echo $aInt->lang("billingcycles", "annually");
            echo "</td>\n                    <td class=\"prod-pricing-recurring\" ";
            echo $payTypeStyle;
            echo ">";
            echo $aInt->lang("billingcycles", "biennially");
            echo "</td>\n                    <td class=\"prod-pricing-recurring\" ";
            echo $payTypeStyle;
            echo ">";
            echo $aInt->lang("billingcycles", "triennially");
            echo "</td>\n                </tr>\n";
            $result = select_query("tblcurrencies", "id,code", "", "code", "ASC");
            while ($data = mysql_fetch_array($result)) {
                $currency_id = $data["id"];
                $currency_code = $data["code"];
                $result2 = select_query("tblpricing", "", ["type" => "product", "currency" => $currency_id, "relid" => $id]);
                $data = mysql_fetch_array($result2);
                $pricing_id = $data["id"] ?? NULL;
                $cycles = ["monthly", "quarterly", "semiannually", "annually", "biennially", "triennially"];
                if (!$pricing_id) {
                    $insertarr = ["type" => "product", "currency" => $currency_id, "relid" => $id];
                    foreach ($cycles as $cycle) {
                        $insertarr[$cycle] = "-1";
                    }
                    insert_query("tblpricing", $insertarr);
                    $result2 = select_query("tblpricing", "", ["type" => "product", "currency" => $currency_id, "relid" => $id]);
                    $data = mysql_fetch_array($result2);
                }
                $setupfields = $pricingfields = $disablefields = "";
                foreach ($cycles as $i => $cycle) {
                    $price = $data[$cycle];
                    $class = 1 <= $i ? " class=\"prod-pricing-recurring\"" : "";
                    $style = 1 <= $i && $paytype === WHMCS\Product\Product::PAYMENT_ONETIME ? " style=\"display: none;\"" : "";
                    $setupfields .= "<td" . $class . $style . "><input type=\"text\" name=\"currency[" . $currency_id . "][" . substr($cycle, 0, 1) . "setupfee]\" id=\"setup_" . $currency_code . "_" . $cycle . "\" value=\"" . $data[substr($cycle, 0, 1) . "setupfee"] . "\"" . ($price == "-1" ? " style=\"display:none\"" : "") . " class=\"form-control input-inline input-100 text-center\" /></td>";
                    $pricingfields .= "<td" . $class . $style . "><input type=\"text\" name=\"currency[" . $currency_id . "][" . $cycle . "]\" id=\"pricing_" . $currency_code . "_" . $cycle . "\" size=\"10\" value=\"" . $price . "\"" . ($price == "-1" ? " style=\"display:none;\"\"" : "") . " class=\"form-control input-inline input-100 text-center\" /></td>";
                    $disablefields .= "<td" . $class . $style . "><input type=\"checkbox\" class=\"pricingtgl\" currency=\"" . $currency_code . "\" cycle=\"" . $cycle . "\"" . ($price == "-1" ? "" : " checked=\"checked\"") . " /></td>";
                }
                echo "<tr bgcolor=\"#ffffff\" style=\"text-align:center\">\n            <td rowspan=\"3\" bgcolor=\"#efefef\"><b>" . $currency_code . "</b></td>\n            <td>" . $aInt->lang("fields", "setupfee") . "</td>\n            " . $setupfields . "\n        </tr>\n        <tr bgcolor=\"#ffffff\" style=\"text-align:center\">\n            <td>" . $aInt->lang("fields", "price") . "</td>\n            " . $pricingfields . "\n        </tr>\n        <tr bgcolor=\"#ffffff\" style=\"text-align:center\">\n            <td>" . $aInt->lang("global", "enable") . "</td>\n            " . $disablefields . "\n        </tr>";
            }
            $jscode .= "\nfunction hidePricingTable() {\n    \$(\"#trPricing\").fadeOut();\n}\nfunction showPricingTable(recurring) {\n    if (\$(\"#trPricing\").is(\":visible\")) {\n        if (recurring) {\n            \$(\"#trPricing .table\").css(\"max-width\", \"\");\n            \$(\".prod-pricing-recurring\").fadeIn();\n        } else {\n            \$(\".prod-pricing-recurring\").fadeOut(\"fast\", function() {\n                \$(\"#trPricing .table\").css(\"max-width\", \"370px\");\n            });\n        }\n    } else {\n        \$(\"#trPricing\").fadeIn();\n        if (recurring) {\n            \$(\"#trPricing .table\").css(\"max-width\", \"\");\n            \$(\".prod-pricing-recurring\").show();\n        } else {\n            \$(\"#trPricing .table\").css(\"max-width\", \"370px\");\n            \$(\".prod-pricing-recurring\").hide();\n        }\n    }\n}\n";
            $jquerycode .= "\$(\".pricingtgl\").click(function() {\n    var cycle = \$(this).attr(\"cycle\");\n    var currency = \$(this).attr(\"currency\");\n\n    if (\$(this).is(\":checked\")) {\n\n        \$(\"#pricing_\" + currency + \"_\" + cycle).val(\"0.00\").show();\n        \$(\"#setup_\" + currency + \"_\" + cycle).show();\n    } else {\n        \$(\"#pricing_\" + currency + \"_\" + cycle).val(\"-1.00\").hide();\n        \$(\"#setup_\" + currency + \"_\" + cycle).hide();\n    }\n});";
            echo "            </table>\n        </div>\n    </div>\n</td></tr>\n    ";
        }
        echo "<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo AdminLang::trans("products.allowqty");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <label class=\"radio-inline\">\n            <input type=\"radio\" name=\"allowqty\" value=\"0\"";
        echo !$allowqty ? " checked" : "";
        echo ">\n            ";
        echo AdminLang::trans("global.no");
        echo "        </label><br>\n        <label class=\"radio-inline\">\n            <input type=\"radio\" name=\"allowqty\" value=\"1\"";
        echo $allowqty === 1 ? " checked" : "";
        echo ">\n            ";
        echo AdminLang::trans("products.allowqtydesc");
        echo "        </label><br>\n        <label class=\"radio-inline\">\n            <input type=\"radio\" name=\"allowqty\" value=\"2\"";
        echo $allowqty === 2 ? " checked" : "";
        echo ">\n            ";
        echo AdminLang::trans("products.allowUnitQuantities");
        echo "        </label>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "recurringcycleslimit");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"recurringcycles\" value=\"";
        echo $recurringcycles;
        echo "\" class=\"form-control input-80 input-inline text-center\" /> ";
        echo $aInt->lang("products", "recurringcycleslimitdesc");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "autoterminatefixedterm");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"autoterminatedays\" value=\"";
        echo $autoterminatedays;
        echo "\" class=\"form-control input-80 input-inline text-center\" /> ";
        echo $aInt->lang("products", "autoterminatefixedtermdesc");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "terminationemail");
        echo "</td><td class=\"fieldarea\"><select name=\"autoterminateemail\" class=\"form-control select-inline\"><option value=\"0\">";
        echo $aInt->lang("global", "none");
        echo "</option>";
        $productMailTemplates = WHMCS\Mail\Template::where("type", "=", "product")->where("custom", "=", 1)->where("language", "=", "")->orderBy("name")->get();
        foreach ($productMailTemplates as $template) {
            echo "<option value=\"" . $template->id . "\"";
            if ($template->id == $autoterminateemail) {
                echo " selected";
            }
            echo ">" . $template->name . "</option>";
        }
        echo "</select> ";
        echo $aInt->lang("products", "chooseemailtplfixedtermend");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "proratabilling");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" id=\"prorataBilling\" name=\"proratabilling\"";
        if ($proratabilling) {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("products", "tickboxtoenable");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "proratadate");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" id=\"prorataDate\" name=\"proratadate\" value=\"";
        echo $proratadate;
        echo "\"class=\"form-control input-80 input-inline text-center\"> ";
        echo $aInt->lang("products", "proratadatedesc");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "chargenextmonth");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" id=\"prorataChargeNextMonth\" name=\"proratachargenextmonth\" value=\"";
        echo $proratachargenextmonth;
        echo "\"class=\"form-control input-80 input-inline text-center\"> ";
        echo $aInt->lang("products", "chargenextmonthdesc");
        echo "</td></tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        if ($server->isMetaDataValueSet("NoEditModuleSettings") && $server->getMetaDataValue("NoEditModuleSettings")) {
            $configurationLink = $server->call("get_configuration_link", ["model" => $product]);
            echo "<input type=\"hidden\" name=\"servertype\" id=\"inputModule\" value=\"" . $servertype . "\" />" . "<div class=\"marketconnect-product-redirect\" role=\"alert\">\n                " . AdminLang::trans("products.marketConnectManageRedirectMsg") . "<br>\n                <a href=\"" . $configurationLink . "\" class=\"btn btn-default btn-sm\">" . AdminLang::trans("products.marketConnectManageRedirectBtn") . "</a>\n            </div>";
        } else {
            echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td class=\"fieldlabel\" width=\"20%\">\n            ";
            echo AdminLang::trans("products.modulename");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <select name=\"servertype\" id=\"inputModule\" class=\"form-control select-inline\" onchange=\"fetchModuleSettings('";
            echo $id;
            echo "', 'simple');\">\n                <option value=\"\">\n                    ";
            echo AdminLang::trans("global.none");
            echo "                </option>\n                ";
            foreach ($serverModules as $moduleName => $displayName) {
                $selected = "";
                if ($moduleName === $servertype) {
                    $selected = " selected=\"selected\"";
                }
                echo "<option value=\"" . $moduleName . "\"" . $selected . ">" . $displayName . "</option>";
            }
            echo "            </select>\n            <img src=\"images/loading.gif\" id=\"moduleSettingsLoader\" class=\"hidden\">\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
            echo AdminLang::trans("products.servergroup");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <select name=\"servergroup\" id=\"inputServerGroup\" class=\"form-control select-inline\" onchange=\"fetchModuleSettings('";
            echo $id;
            echo "', 'simple');\">\n                <option value=\"0\" data-server-types=\"\">\n                    ";
            echo AdminLang::trans("global.none");
            echo "                </option>\n            ";
            $serverTypes = "CONCAT(\",\", GROUP_CONCAT(DISTINCT tblservers.type SEPARATOR \",\"), \",\") as server_types";
            $serverGroups = WHMCS\Database\Capsule::table("tblservergroups")->join("tblservergroupsrel", "tblservergroups.id", "=", "tblservergroupsrel.groupid")->join("tblservers", "tblservergroupsrel.serverid", "=", "tblservers.id")->groupBy("tblservergroups.id")->selectRaw("tblservergroups.id,tblservergroups.name," . $serverTypes)->get()->all();
            foreach ($serverGroups as $group) {
                $selected = "";
                if ($group->id === $servergroup) {
                    $selected = " selected=\"selected\"";
                }
                $serverTypes = " data-server-types=\"" . $group->server_types . "\"";
                echo "<option value=\"" . $group->id . "\"" . $serverTypes . $selected . ">" . $group->name . "</option>";
            }
            echo "            </select>\n        </td>\n    </tr>\n</table>\n\n<div id=\"serverReturnedError\" class=\"alert alert-warning hidden\" style=\"margin:10px 0;\">\n    <i class=\"fas fa-exclamation-triangle\"></i>\n    <span id=\"serverReturnedErrorText\"></span>\n</div>\n\n<table class=\"form module-settings\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" id=\"noModuleSelectedRow\">\n    <tr>\n        <td>\n            <div class=\"no-module-selected\">\n                ";
            echo AdminLang::trans("products.moduleSettingsChooseAProduct");
            echo "            </div>\n        </td>\n    </tr>\n</table>\n\n<div id=\"divModuleSettings\">\n</div>\n<div class=\"module-settings-mode hidden\">\n    <a class=\"btn btn-sm btn-link\" id=\"mode-switch\" data-mode=\"simple\">\n        <span class=\"text-simple hidden\">\n            ";
            echo AdminLang::trans("products.switchSimple");
            echo "        </span>\n        <span class=\"text-advanced hidden\">\n            ";
            echo AdminLang::trans("products.switchAdvanced");
            echo "        </span>\n    </a>\n</div>\n<table class=\"form metric-settings hidden\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" id=\"tblMetricSettings\">\n    <tr>\n        <td width=\"150\">\n            ";
            echo AdminLang::trans("usagebilling.metricbilling");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <div class=\"config\" id=\"metricsConfig\"></div>\n        </td>\n    </tr>\n</table>\n<style>\n.metric-settings .config {\n    padding: 0 8px;\n}\n.metric-settings .row {\n    margin-left: -5px;\n    margin-right: -5px;\n}\n.metric-settings .col-md-4 {\n    padding-left: 5px;\n    padding-right: 5px;\n}\n.metric-settings .metric {\n    margin: 5px 0;\n    padding: 10px;\n    background-color: #f8f8f8;\n    border-radius: 3px;\n}\n.metric-settings .metric .toggle {\n    float: right;\n}\n.metric-settings .metric .pricing a {\n    font-size: 0.9em;\n}\n</style>\n<script>\n    \$(document).ready(function() {\n        jQuery('body').on('click', '.open-metric-pricing', function(e) {\n            e.preventDefault();\n            var url = 'configproducts.php?action=metric-pricing&id=";
            echo $id;
            echo "&module=' + \$('#inputModule').val() + '&metric=' + \$(this).data('metric');\n            openModal(url, '', '";
            echo AdminLang::trans("usagebilling.configurepricing");
            echo "', '";
            echo 4 < WHMCS\Billing\Currency::count() ? "modal-lg" : "";
            echo "', '', 'Save', 'btnMetricPricingSave', false, '');\n        });\n        jQuery('#btnMetricPricingSave').on('click', function(e){\n           e.preventDefault();\n        });\n    });\n</script>\n";
        }
        echo "<table class=\"form module-settings-automation module-settings-loading\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" id=\"tblModuleAutomationSettings\">\n    <tr>\n        <td width=\"20\">\n            <input type=\"radio\" name=\"autosetup\" value=\"order\" id=\"autosetup_order\" disabled";
        if ($autosetup == "order") {
            echo " checked";
        }
        echo ">\n        </td>\n        <td class=\"fieldarea\">\n            <label for=\"autosetup_order\" class=\"checkbox-inline\">";
        echo $aInt->lang("products", "asetupinstantlyafterorderdesc");
        echo "</label>\n        </td>\n    </tr>\n    <tr>\n        <td>\n            <input type=\"radio\" name=\"autosetup\" value=\"payment\" disabled id=\"autosetup_payment\"";
        if ($autosetup == "payment") {
            echo " checked";
        }
        echo ">\n        </td>\n        <td class=\"fieldarea\">\n            <label for=\"autosetup_payment\" class=\"checkbox-inline\">";
        echo $aInt->lang("products", "asetupafterpaydesc");
        echo "</label>\n        </td>\n    </tr>\n    <tr>\n        <td>\n            <input type=\"radio\" name=\"autosetup\" value=\"on\" disabled id=\"autosetup_on\"";
        if ($autosetup == "on") {
            echo " checked";
        }
        echo ">\n        </td>\n        <td class=\"fieldarea\">\n            <label for=\"autosetup_on\" class=\"checkbox-inline\">";
        echo $aInt->lang("products", "asetupmadesc");
        echo "</label>\n        </td>\n    </tr>\n    <tr>\n        <td>\n            <input type=\"radio\" name=\"autosetup\" value=\"\" disabled id=\"autosetup_no\"";
        if ($autosetup == "") {
            echo " checked";
        }
        echo ">\n        </td>\n        <td class=\"fieldarea\">\n            <label for=\"autosetup_no\" class=\"checkbox-inline\">";
        echo $aInt->lang("products", "noautosetupdesc");
        echo "</label>\n        </td>\n    </tr>\n</table>\n\n<script>\n\$(document).ready(function(){\n    var moduleSettingsFetched = false;\n    \$('a[data-toggle=\"tab\"]').on('shown.bs.tab', function (e) {\n        if (moduleSettingsFetched) {\n            return;\n        }\n        var href = \$(this).attr('href');\n        if (href == '#tab3') {\n            fetchModuleSettings('";
        echo $id;
        echo "');\n            moduleSettingsFetched = true;\n        }\n    });\n    if (\$('#inputModule').val() != ''\n        && ('";
        echo App::getFromRequest("tab") == 3;
        echo "' || window.location.hash == '#tab=3')\n    ) {\n        fetchModuleSettings('";
        echo $id;
        echo "');\n    }\n});\n</script>\n\n";
        echo $aInt->nextAdminTab();
        echo "\n";
        $result = select_query("tblcustomfields", "", ["type" => "product", "relid" => $id], "sortorder` ASC,`id", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $fid = $data["id"];
            $fieldname = $data["fieldname"];
            $fieldtype = $data["fieldtype"];
            $description = $data["description"];
            $fieldoptions = $data["fieldoptions"];
            $regexpr = $data["regexpr"];
            $adminonly = $data["adminonly"];
            $required = $data["required"];
            $showorder = $data["showorder"];
            $showinvoice = $data["showinvoice"];
            $sortorder = $data["sortorder"];
            echo "<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td class=\"fieldlabel\">\n        ";
            echo $aInt->lang("customfields", "fieldname");
            echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"customfieldname[";
            echo $fid;
            echo "]\" value=\"";
            echo $fieldname;
            echo "\" class=\"form-control input-inline input-400\" />\n        ";
            echo $aInt->getTranslationLink("custom_field.name", $fid, "product");
            echo "        <div class=\"pull-right\">\n            ";
            echo $aInt->lang("customfields", "order");
            echo "            <input type=\"text\" name=\"customsortorder[";
            echo $fid;
            echo "]\" value=\"";
            echo $sortorder;
            echo "\" class=\"form-control input-inline input-100 text-center\">\n        </div>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
            echo $aInt->lang("customfields", "fieldtype");
            echo "</td><td class=\"fieldarea\"><select name=\"customfieldtype[";
            echo $fid;
            echo "]\" class=\"form-control select-inline\">\n<option value=\"text\"";
            if ($fieldtype == "text") {
                echo " selected";
            }
            echo ">";
            echo $aInt->lang("customfields", "typetextbox");
            echo "</option>\n<option value=\"link\"";
            if ($fieldtype == "link") {
                echo " selected";
            }
            echo ">";
            echo $aInt->lang("customfields", "typelink");
            echo "</option>\n<option value=\"password\"";
            if ($fieldtype == "password") {
                echo " selected";
            }
            echo ">";
            echo $aInt->lang("customfields", "typepassword");
            echo "</option>\n<option value=\"dropdown\"";
            if ($fieldtype == "dropdown") {
                echo " selected";
            }
            echo ">";
            echo $aInt->lang("customfields", "typedropdown");
            echo "</option>\n<option value=\"tickbox\"";
            if ($fieldtype == "tickbox") {
                echo " selected";
            }
            echo ">";
            echo $aInt->lang("customfields", "typetickbox");
            echo "</option>\n<option value=\"textarea\"";
            if ($fieldtype == "textarea") {
                echo " selected";
            }
            echo ">";
            echo $aInt->lang("customfields", "typetextarea");
            echo "</option>\n</select></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
            echo $aInt->lang("fields", "description");
            echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"customfielddesc[";
            echo $fid;
            echo "]\" value=\"";
            echo $description;
            echo "\" class=\"form-control input-inline input-500\" />\n        ";
            echo $aInt->getTranslationLink("custom_field.description", $fid, "product");
            echo "        ";
            echo $aInt->lang("customfields", "descriptioninfo");
            echo "    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
            echo $aInt->lang("customfields", "validation");
            echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"customfieldregexpr[";
            echo $fid;
            echo "]\" value=\"";
            echo WHMCS\Input\Sanitize::encode($regexpr);
            echo "\" class=\"form-control input-inline input-500\"> ";
            echo $aInt->lang("customfields", "validationinfo");
            echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
            echo $aInt->lang("customfields", "selectoptions");
            echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"customfieldoptions[";
            echo $fid;
            echo "]\" value=\"";
            echo $fieldoptions;
            echo "\" class=\"form-control input-inline input-500\"> ";
            echo $aInt->lang("customfields", "selectoptionsinfo");
            echo "</td></tr>\n    <tr>\n        <td class=\"fieldlabel\"></td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"customadminonly[";
            echo $fid;
            echo "]\"";
            if ($adminonly == "on") {
                echo " checked";
            }
            echo ">\n                ";
            echo $aInt->lang("customfields", "adminonly");
            echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"customrequired[";
            echo $fid;
            echo "]\"";
            if ($required == "on") {
                echo " checked";
            }
            echo ">\n                ";
            echo $aInt->lang("customfields", "requiredfield");
            echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"customshoworder[";
            echo $fid;
            echo "]\"";
            if ($showorder == "on") {
                echo " checked";
            }
            echo ">\n                ";
            echo $aInt->lang("customfields", "orderform");
            echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"customshowinvoice[";
            echo $fid;
            echo "]\"";
            if ($showinvoice) {
                echo " checked";
            }
            echo ">\n                ";
            echo $aInt->lang("customfields", "showinvoice");
            echo "            </label>\n            <div class=\"pull-right\">\n                <a href=\"#\" onclick=\"deleteCustomField('";
            echo $fid;
            echo "');return false\" class=\"btn btn-danger btn-xs\">";
            echo $aInt->lang("customfields", "deletefield");
            echo "</a>\n            </div>\n        </td>\n    </tr>\n</table><br>\n";
        }
        echo "<b>";
        echo $aInt->lang("customfields", "addfield");
        echo "</b><br><br>\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("customfields", "fieldname");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"addfieldname\" class=\"form-control input-inline input-400\" />\n        ";
        echo $aInt->getTranslationLink("custom_field.name", 0, "product");
        echo "        <div class=\"pull-right\">\n            ";
        echo $aInt->lang("customfields", "order");
        echo "            <input type=\"text\" name=\"addsortorder\" value=\"0\" class=\"form-control input-inline input-100 text-center\" />\n        </div>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("customfields", "fieldtype");
        echo "</td><td class=\"fieldarea\"><select name=\"addfieldtype\" class=\"form-control select-inline\">\n<option value=\"text\">";
        echo $aInt->lang("customfields", "typetextbox");
        echo "</option>\n<option value=\"link\">";
        echo $aInt->lang("customfields", "typelink");
        echo "</option>\n<option value=\"password\">";
        echo $aInt->lang("customfields", "typepassword");
        echo "</option>\n<option value=\"dropdown\">";
        echo $aInt->lang("customfields", "typedropdown");
        echo "</option>\n<option value=\"tickbox\">";
        echo $aInt->lang("customfields", "typetickbox");
        echo "</option>\n<option value=\"textarea\">";
        echo $aInt->lang("customfields", "typetextarea");
        echo "</option>\n</select></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("fields", "description");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"addcustomfielddesc\" class=\"form-control input-inline input-500\" />\n        ";
        echo $aInt->getTranslationLink("custom_field.description", 0, "product");
        echo "        ";
        echo $aInt->lang("customfields", "descriptioninfo");
        echo "    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("customfields", "validation");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"addregexpr\" class=\"form-control input-inline input-500\"> ";
        echo $aInt->lang("customfields", "validationinfo");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("customfields", "selectoptions");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"addfieldoptions\" class=\"form-control input-inline input-500\"> ";
        echo $aInt->lang("customfields", "selectoptionsinfo");
        echo "</td></tr>\n    <tr>\n        <td class=\"fieldlabel\"></td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"addadminonly\">\n                ";
        echo $aInt->lang("customfields", "adminonly");
        echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"addrequired\">\n                ";
        echo $aInt->lang("customfields", "requiredfield");
        echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"addshoworder\">\n                ";
        echo $aInt->lang("customfields", "orderform");
        echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"addshowinvoice\">\n                ";
        echo $aInt->lang("customfields", "showinvoice");
        echo "            </label>\n        </td>\n    </tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"150\" class=\"fieldlabel\">";
        echo $aInt->lang("products", "assignedoptiongroups");
        echo "</td><td class=\"fieldarea\"><select name=\"configoptionlinks[]\" size=\"8\" class=\"form-control select-inline\" style=\"width:90%\" multiple>\n";
        $configoptionlinks = [];
        $result = select_query("tblproductconfiglinks", "", ["pid" => $id]);
        while ($data = mysql_fetch_array($result)) {
            $configoptionlinks[] = $data["gid"];
        }
        $result = select_query("tblproductconfiggroups", "", "", "name", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $confgroupid = $data["id"];
            $name = $data["name"];
            $description = $data["description"];
            echo "<option value=\"" . $confgroupid . "\"";
            if (in_array($confgroupid, $configoptionlinks)) {
                echo " selected";
            }
            echo ">" . $name . " - " . $description . "</option>";
        }
        echo "</select></td></tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "packagesupgrades");
        echo "</td><td class=\"fieldarea\"><select name=\"upgradepackages[]\" size=\"10\" class=\"form-control select-inline\" multiple>";
        $query = "SELECT tblproducts.id,tblproductgroups.name AS groupname,tblproducts.name AS productname FROM tblproducts INNER JOIN tblproductgroups ON tblproductgroups.id=tblproducts.gid ORDER BY tblproductgroups.`order`,tblproducts.`order`,tblproducts.name ASC";
        $result = full_query($query);
        while ($data = mysql_fetch_array($result)) {
            $productid = $data["id"];
            $groupname = $data["groupname"];
            $productname = $data["productname"];
            if ($id != $productid) {
                echo "<option value=\"" . $productid . "\"";
                if (@in_array($productid, $upgradepackages)) {
                    echo " selected";
                }
                echo ">" . $groupname . " - " . $productname . "</option>";
            }
        }
        echo "</select><br>";
        echo $aInt->lang("products", "usectrlclickpkgs");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("setup", "configoptions");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"configoptionsupgrade\"";
        if ($configoptionsupgrade) {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("products", "tickboxallowconfigoptupdowngrades");
        echo "</label></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "upgradeemail");
        echo "</td><td class=\"fieldarea\"><select name=\"upgradeemail\" class=\"form-control select-inline\"><option value=\"0\">";
        echo $aInt->lang("global", "none");
        echo "</option>";
        $emails = [$aInt->lang("products", "emailshostingac"), $aInt->lang("products", "emailsresellerac"), $aInt->lang("products", "emailsvpsdediserver"), $aInt->lang("products", "emailsother")];
        foreach ($emails as $email) {
            $mailTemplates = WHMCS\Mail\Template::where("type", "=", "product")->where("name", "=", $email)->where("language", "=", "")->get();
            foreach ($mailTemplates as $template) {
                echo "<option value=\"" . $template->id . "\"";
                if ($template->id == $upgradeemail) {
                    echo " selected";
                }
                echo ">" . $template->name . "</option>";
            }
        }
        foreach ($customProductMailTemplates as $template) {
            echo "<option value=\"" . $template->id . "\"";
            if ($template->id == $upgradeemail) {
                echo " selected";
            }
            echo ">" . $template->name . "</option>";
        }
        echo "</select></td></tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("products", "tabsfreedomain");
        echo "</td>\n        <td class=\"fieldarea\">\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"freedomain\" value=\"\"";
        if (!$freedomain) {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("global", "none");
        echo "            </label><br />\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"freedomain\" value=\"once\"";
        if ($freedomain == "once") {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("products", "freedomainrenewnormal");
        echo "            </label><br />\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"freedomain\" value=\"on\"";
        if ($freedomain == "on") {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("products", "freedomainfreerenew");
        echo "            </label>\n        </td>\n    </tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "freedomainpayterms");
        echo "</td><td class=\"fieldarea\"><select name=\"freedomainpaymentterms[]\" size=\"6\" class=\"form-control select-inline\" multiple>\n<option value=\"onetime\"";
        if (in_array("onetime", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "onetime");
        echo "</option>\n<option value=\"monthly\"";
        if (in_array("monthly", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "monthly");
        echo "</option>\n<option value=\"quarterly\"";
        if (in_array("quarterly", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "quarterly");
        echo "</option>\n<option value=\"semiannually\"";
        if (in_array("semiannually", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "semiannually");
        echo "</option>\n<option value=\"annually\"";
        if (in_array("annually", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "annually");
        echo "</option>\n<option value=\"biennially\"";
        if (in_array("biennially", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "biennially");
        echo "</option>\n<option value=\"triennially\"";
        if (in_array("triennially", $freedomainpaymentterms)) {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billingcycles", "triennially");
        echo "</option>\n</select><br>";
        echo $aInt->lang("products", "selectfreedomainpayterms");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "freedomaintlds");
        echo "</td>\n    <td class=\"fieldarea\"><select name=\"freedomaintlds[]\" size=\"5\" class=\"form-control select-inline\" multiple>";
        $query = "SELECT DISTINCT extension FROM tbldomainpricing ORDER BY `order` ASC";
        $result = full_query($query);
        while ($data = mysql_fetch_array($result)) {
            $extension = $data["extension"];
            echo "<option";
            if (in_array($extension, $freedomaintlds)) {
                echo " selected";
            }
            echo ">" . $extension;
        }
        echo "</select><br>";
        echo $aInt->lang("products", "usectrlclickpayterms");
        echo "</td></tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        $jquerycode .= "var productSelectize = WHMCS.selectize.productSearch();\njQuery('.product-recommendations-wrapper').on('click', '.fa-trash', function() {\n    var placeholderItem = jQuery('.product-recommendations-wrapper .placeholder-list-item');\n    jQuery(this).closest('li').remove();\n    if (jQuery('.product-recommendations-wrapper li input').length < 1) {\n        placeholderItem.removeClass('hidden');\n    }\n    productSelectize.destroy();\n    productSelectize = WHMCS.selectize.productSearch();\n    recommendationAlert();\n});\nnew Sortable(productRecommendationsList, {\n    animation: 150,\n    ghostClass: 'ghost',\n    onUpdate: function() {\n        recommendationAlert();\n    }\n});\nfunction recommendationAlert() {\n    var recommendationAlert = jQuery('div.recommendation-alert');\n        if (recommendationAlert.not(':visible')) {\n            jQuery('.recommendation-alert').removeClass('hidden');\n        }\n}";
        $recommendationItems = function () use($productRecommendations) {
            $hiddenClass = 0 < count($productRecommendations) ? " hidden" : "";
            $noRecommendationsString = AdminLang::trans("products.infoNoRecommendations");
            $outputString = "<li role=\"presentation\" class=\"hidden clonable-item\">\n    <a href=\"#\">\n        <span class=\"recommendation-name\"></span>\n        <span class=\"pull-right\">\n            <i class=\"fas fa-trash\"></i>\n            <i class=\"fas fa-arrows-alt\"></i>\n        </span>\n    </a>\n</li>\n<li role=\"presentation\" class=\"placeholder-list-item" . $hiddenClass . "\">\n    <a href=\"#\">\n        <span class=\"recommendation-name\">" . $noRecommendationsString . "</span>\n    </a>\n</li>";
            foreach ($productRecommendations as $productRecommendation) {
                $outputString .= "<li role=\"presentation\">\n    <a href=\"#\">\n        <span class=\"recommendation-name\">\n            " . $productRecommendation->productGroup->name . " - " . $productRecommendation->name . "\n        </span>\n        <span class=\"pull-right\">\n            <i class=\"fas fa-trash\"></i>\n            <i class=\"fas fa-arrows-alt\"></i>\n        </span>\n    </a>\n    <input type=\"hidden\" name=\"productRecommendations[]\" value=\"" . $productRecommendation->id . "\">\n</li>";
            }
            return $outputString;
        };
        echo "<style>\n    .product-recommendations-wrapper {\n        margin-top: 1em;\n    }\n    .product-recommendations-wrapper li a {\n        background-color: white;\n    }\n    .product-recommendations-wrapper li a:focus,\n    .product-recommendations-wrapper li a:hover {\n        background-color: white !important;\n        color: #202F60 !important;\n    }\n    .product-recommendations-wrapper li a i:first-child {\n        margin-right: 4px;\n    }\n    .product-recommendations-wrapper li {\n        margin-top: 4px !important;\n    }\n    .product-recommendations-wrapper .placeholder-list-item {\n        pointer-events: none;\n    }\n    .product-recommendations-wrapper .recommendation-alert {\n        margin-bottom: 4px;\n        display:flex;\n        align-items: center;\n    }\n    .product-recommendations-wrapper .recommendation-alert i {\n        margin-right: 5px;\n    }\n</style>\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tbody>\n        <tr>\n            <td class=\"fieldlabel\" width=\"25%\">\n                ";
        echo AdminLang::trans("products.productRecommendations");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <div class=\"row\">\n                    <div class=\"col-sm-9 col-md-8 col-lg-7\">\n                        <select class=\"form-control selectize selectize-product-search\"\n                                data-allow-empty-option=\"0\"\n                                data-search-url=\"";
        echo routePath("admin-setup-product-recommendation-search", $id);
        echo "\"\n                                placeholder=\"";
        echo AdminLang::trans("products.infoStartTyping");
        echo "\"\n                        ></select>\n                        <div class=\"product-recommendations-wrapper\">\n                            <div class=\"alert alert-warning recommendation-alert hidden\" role=\"alert\">\n                                <i class=\"fas fa-exclamation-triangle\"></i>\n                                <span>";
        echo AdminLang::trans("products.infoClickSave");
        echo "</span>\n                            </div>\n                            <ul id=\"productRecommendationsList\" class=\"nav nav-pills nav-stacked\">\n                                ";
        echo $recommendationItems();
        echo "                            </ul>\n                        </div>\n                        <p>";
        echo AdminLang::trans("products.productRecommendationsDesc");
        echo "</p>\n                    </div>\n                </div>\n            </td>\n        </tr>\n    </tbody>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n";
        $producteditfieldsarray = run_hook("AdminProductConfigFields", ["pid" => $id]);
        if (is_array($producteditfieldsarray)) {
            foreach ($producteditfieldsarray as $pv) {
                foreach ($pv as $k => $v) {
                    echo "<tr><td class=\"fieldlabel\">" . $k . "</td><td class=\"fieldarea\">" . $v . "</td></tr>";
                }
            }
        }
        echo "    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("products", "customaffiliatepayout");
        echo "</td>\n        <td class=\"fieldarea\">\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"affiliatepaytype\" value=\"\"";
        if ($affiliatepaytype == "") {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("affiliates", "usedefault");
        echo "            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"affiliatepaytype\" value=\"percentage\"";
        if ($affiliatepaytype == "percentage") {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("affiliates", "percentage");
        echo "            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"affiliatepaytype\" value=\"fixed\"";
        if ($affiliatepaytype == "fixed") {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("affiliates", "fixedamount");
        echo "            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"affiliatepaytype\" value=\"none\"";
        if ($affiliatepaytype == "none") {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("affiliates", "nocommission");
        echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("affiliates", "affiliatepayamount");
        echo "</td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"affiliatepayamount\" value=\"";
        echo $affiliatepayamount;
        echo "\" class=\"form-control input-inline input-100 text-center\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"affiliateonetime\"";
        if ($affiliateonetime) {
            echo " checked";
        }
        echo ">\n                ";
        echo $aInt->lang("affiliates", "onetimepayout");
        echo "            </label>\n        </td>\n    </tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "subdomainoptions");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"subdomain\" value=\"";
        echo $subdomain;
        echo "\" class=\"form-control input-inline input-300\"> ";
        echo $aInt->lang("products", "subdomainoptionsdesc");
        echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "associateddownloads");
        echo "</td><td class=\"fieldarea\">";
        echo $aInt->lang("products", "associateddownloadsdesc");
        echo "<br />\n<table align=\"center\"><tr><td valign=\"top\">\n<div align=\"center\"><strong>";
        echo $aInt->lang("products", "availablefiles");
        echo "</strong></div>\n<div id=\"productdownloadsbrowser\" style=\"width: 250px;height: 200px;border-top: solid 1px #BBB;border-left: solid 1px #BBB;border-bottom: solid 1px #FFF;border-right: solid 1px #FFF;background: #FFF;overflow: scroll;padding: 5px;\"></div>\n</td><td><></td><td valign=\"top\">\n<div align=\"center\"><strong>";
        echo $aInt->lang("products", "selectedfiles");
        echo "</strong></div>\n<div id=\"productdownloadslist\" style=\"width: 250px;height: 200px;border-top: solid 1px #BBB;border-left: solid 1px #BBB;border-bottom: solid 1px #FFF;border-right: solid 1px #FFF;background: #FFF;overflow: scroll;padding: 5px;\">";
        printproductdownloads($downloadIds);
        echo "</div>\n</td></tr></table>\n<div align=\"center\"><input type=\"button\" value=\"";
        echo $aInt->lang("products", "addcategory");
        echo "\" class=\"button btn btn-default\" id=\"showadddownloadcat\" /> <input type=\"button\" value=\"";
        echo $aInt->lang("products", "quickupload");
        echo "\" class=\"button btn btn-default\" id=\"showquickupload\" /></div>\n</td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "overagesbilling");
        echo "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"overagesenabled\" value=\"1\"";
        if (isset($overagesenabled[0]) && $overagesenabled[0]) {
            echo " checked";
        }
        echo "> ";
        echo $aInt->lang("global", "ticktoenable");
        echo "</label></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "overagesoftlimits");
        echo "</td><td class=\"fieldarea\">";
        echo $aInt->lang("products", "overagediskusage");
        echo " <input type=\"text\" name=\"overagesdisklimit\" value=\"";
        echo $overagesdisklimit;
        echo "\" class=\"form-control input-inline input-100 text-center\"> <select name=\"overageunitsdisk\" class=\"form-control select-inline\"><option>MB</option><option";
        if (isset($overagesenabled[1]) && $overagesenabled[1] == "GB") {
            echo " selected";
        }
        echo ">GB</option><option";
        if (isset($overagesenabled[1]) && $overagesenabled[1] == "TB") {
            echo " selected";
        }
        echo ">TB</option></select> ";
        echo $aInt->lang("products", "overagebandwidth");
        echo " <input type=\"text\" name=\"overagesbwlimit\" value=\"";
        echo $overagesbwlimit;
        echo "\" class=\"form-control input-inline input-100 text-center\"> <select name=\"overageunitsbw\" class=\"form-control select-inline\"><option>MB</option><option";
        if (isset($overagesenabled[2]) && $overagesenabled[2] == "GB") {
            echo " selected";
        }
        echo ">GB</option><option";
        if (isset($overagesenabled[2]) && $overagesenabled[2] == "TB") {
            echo " selected";
        }
        echo ">TB</option></select></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("products", "overagecosts");
        echo "</td><td class=\"fieldarea\">";
        echo $aInt->lang("products", "overagediskusage");
        echo " <input type=\"text\" name=\"overagesdiskprice\" value=\"";
        echo $overagesdiskprice;
        echo "\" class=\"form-control input-inline input-100 text-center\"> ";
        echo $aInt->lang("products", "overagebandwidth");
        echo " <input type=\"text\" name=\"overagesbwprice\" value=\"";
        echo $overagesbwprice;
        echo "\" class=\"form-control input-inline input-100 text-center\"> (";
        echo $aInt->lang("products", "priceperunit");
        echo ")</td></tr>\n</table>\n\n";
        echo $aInt->nextAdminTab();
        $directLinkWithTemplate = App::getSystemURL() . "cart.php?a=add&pid=" . $id . "&carttpl=" . $defaultTemplateName;
        $directLinkWithDomain = App::getSystemURL() . "cart.php?a=add&pid=" . $id . "&sld=whmcs&tld=.com";
        $activeSlug = $product->activeSlug;
        if (!$activeSlug) {
            $directLink = App::getSystemURL() . "cart.php?a=add&pid=" . $id;
            $groupLink = App::getSystemURL() . "cart.php?a=add&gid=" . $id;
        } else {
            $directLink = routePathWithQuery("store-product-product", [$activeSlug->groupSlug, $activeSlug->slug], [], true);
            $groupLink = fqdnRoutePath("store-product-group", $activeSlug->groupSlug);
        }
        $copyToClipboard = AdminLang::trans("global.clipboardCopy");
        echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td class=\"fieldlabel\" width=\"370\">\n        ";
        echo $aInt->lang("products", "directscartlink");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"input-group\">\n            ";
        if ($product->module === "marketconnect") {
            list($service) = explode("_", $product->moduleConfigOption1);
            $link = fqdnRoutePath("store-product-group", $product->productGroup->slug);
            echo "<input id=\"Direct-Link\" type=\"text\" class=\"form-control\" value=\"" . $link . "\" readonly>";
        } else {
            echo "<input id=\"Direct-Link\" type=\"text\" class=\"form-control\" value=\"" . $directLink . "\" readonly=\"readonly\">";
        }
        echo "            <div class=\"input-group-btn\">\n                <button type=\"button\" class=\"btn btn-default copy-to-clipboard\" data-clipboard-target=\"#Direct-Link\">\n                    <i class=\"fal fa-copy\" title=\"";
        echo $copyToClipboard;
        echo "\"></i>\n                    <span class=\"sr-only\">";
        echo $copyToClipboard;
        echo "</span>\n                </button>\n            </div>\n        </div>\n    </td>\n</tr>\n";
        if ($product->module !== "marketconnect") {
            echo "<tr>\n    <td class=\"fieldlabel\">\n        ";
            echo $aInt->lang("products", "directscarttpllink");
            echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"input-group\">\n            <input id=\"Direct-Link-With-Template\" type=\"text\" class=\"form-control\" value=\"";
            echo $directLinkWithTemplate;
            echo "\" readonly=\"readonly\">\n            <div class=\"input-group-btn\">\n                <button type=\"button\" class=\"btn btn-default copy-to-clipboard\" data-clipboard-target=\"#Direct-Link-With-Template\">\n                    <i class=\"fal fa-copy\" title=\"";
            echo $copyToClipboard;
            echo "\"></i>\n                    <span class=\"sr-only\">";
            echo $copyToClipboard;
            echo "</span>\n                </button>\n            </div>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
            echo $aInt->lang("products", "directscartdomlink");
            echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"input-group\">\n            <input id=\"Direct-Link-Including-Domain\" type=\"text\" class=\"form-control\" value=\"";
            echo $directLinkWithDomain;
            echo "\" readonly=\"readonly\">\n            <div class=\"input-group-btn\">\n                <button type=\"button\" class=\"btn btn-default copy-to-clipboard\" data-clipboard-target=\"#Direct-Link-Including-Domain\">\n                    <i class=\"fal fa-copy\" title=\"";
            echo $copyToClipboard;
            echo "\"></i>\n                    <span class=\"sr-only\">";
            echo $copyToClipboard;
            echo "</span>\n                </button>\n            </div>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
            echo $aInt->lang("products", "productgcartlink");
            echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"input-group\">\n            <input id=\"Product-Group-Cart-Link\" type=\"text\" class=\"form-control\" value=\"";
            echo $groupLink;
            echo "\" readonly=\"readonly\">\n            <div class=\"input-group-btn\">\n                <button type=\"button\" class=\"btn btn-default copy-to-clipboard\" data-clipboard-target=\"#Product-Group-Cart-Link\">\n                    <i class=\"fal fa-copy\" title=\"";
            echo $copyToClipboard;
            echo "\"></i>\n                    <span class=\"sr-only\">";
            echo $copyToClipboard;
            echo "</span>\n                </button>\n            </div>\n        </div>\n    </td>\n</tr>\n</table>\n";
        }
        echo "    <div class=\"panel panel-default\">\n        <div class=\"panel-heading\">Product URLs</div>\n        <table class=\"table datatable\">\n            <tr>\n                <th>URL</th>\n                <th>Visits</th>\n                <th style=\"width: 32px;\"></th>\n            </tr>\n            ";
        foreach ($product->slugs as $slugInfo) {
            echo "                <tr>\n                    <td>\n                        ";
            echo $slugInfo->active ? "<div class=\"input-group\">" : "";
            echo "                        <input ";
            echo $slugInfo->active ? "id=\"inputActiveSlug\"" : "";
            echo " type=\"text\" class=\"form-control\" readonly=\"readonly\" value=\"";
            echo fqdnRoutePath("store-product-product", $slugInfo->groupSlug, $slugInfo->slug);
            echo "\" />\n                        ";
            if ($slugInfo->active) {
                echo "                            <span class=\"input-group-btn\">\n                                <button class=\"btn btn-default copy-to-clipboard\" type=\"button\" data-clipboard-target=\"#inputActiveSlug\">\n                                    <i class=\"fal fa-copy\" title=\"";
                echo $copyToClipboard;
                echo "\"></i>\n                                    <span class=\"sr-only\">";
                echo $copyToClipboard;
                echo "></span>\n                                </button>\n                            </span>\n                        ";
            }
            echo "                    ";
            echo $slugInfo->active ? "</div>" : "";
            echo "                    </td>\n                    <td class=\"text-center\">";
            echo $slugInfo->clicks;
            echo "</td>\n                    <td class=\"text-center\" style=\"width: 32px;\">\n                        <button type=\"button\"\n                                class=\"btn btn-sm btn-danger delete-slug";
            echo $slugInfo->active ? " disabled" : "";
            echo "\"\n                                ";
            echo $slugInfo->active ? " disabled=\"disabled\"" : "";
            echo "                                aria-label=\"Remove product URL\"\n                                data-slug-id=\"";
            echo $slugInfo->id;
            echo "\"\n                        >\n                            <i aria-hidden=\"true\" class=\"far fa-minus-circle\"></i>\n                        </button>\n                    </td>\n                </tr>\n            ";
        }
        echo "        </table>\n    </div>\n</table>\n\n";
        echo $aInt->endAdminTabs();
        echo "\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"Save Changes\" class=\"btn btn-primary\" id=\"btnSaveProduct\"";
        echo !$product->activeSlug ? " disabled=\"disabled\"" : "";
        echo ">\n    <input type=\"button\" value=\"";
        echo $aInt->lang("global", "cancelchanges");
        echo "\" onclick=\"window.location='configproducts.php'\" class=\"btn btn-default\">\n</div>\n\n<input type=\"hidden\" name=\"tab\" id=\"tab\" value=\"";
        echo (int) ($_REQUEST["tab"] ?? 0);
        echo "\" />\n\n</form>\n\n";
        $deleteCustomFieldUrl = WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl() . "/configproducts.php?action=edit&id=" . $id . "&tab=4&sub=deletecustomfield&fid=";
        echo $aInt->modalWithConfirmation("deleteCustomField", AdminLang::trans("customfields.delsure"), $deleteCustomFieldUrl, "fieldId");
        echo WHMCS\View\Helper::confirmationModal("deleteProductSlug", AdminLang::trans("products.deleteProductSlugSure"), fqdnRoutePath("admin-setup-product-slug-remove"));
        echo $aInt->modal("QuickUpload", "Quick File Upload", AdminLang::trans("global.loading"), [["title" => AdminLang::trans("global.save"), "onclick" => "jQuery(\"#quickuploadfrm\").submit();"], ["title" => AdminLang::trans("global.cancel")]]);
        echo $aInt->modal("AddDownloadCategory", AdminLang::trans("support.addcategory"), AdminLang::trans("global.loading"), [["title" => AdminLang::trans("global.save"), "onclick" => "jQuery(\"#adddownloadcatfrm\").submit();"], ["title" => AdminLang::trans("global.cancel")]], "small");
    } else {
        if ($action == "create") {
            checkPermission("Create New Products/Services");
            $inputModule = App::getFromRequest("module");
            $productGroups = WHMCS\Product\Group::orderBy("order")->get();
            if (count($productGroups) == 0) {
                App::redirect("configproducts.php", "action=creategroup&prodcreatenogroups=1");
            }
            $jquerycode = "\$('.product-creation-types .type').click(function(e) {\n    \$('.product-creation-types .type').removeClass('active');\n    \$(this).addClass('active');\n    \$('#inputProductType').val(\$(this).data('type'));\n});\n\$('.product-creation-modules .module').click(function(e) {\n    \$('.product-creation-modules .module').removeClass('active');\n    \$(this).addClass('active');\n    \$('#inputProductModule').val(\$(this).data('module'));\n});";
            echo "\n<div class=\"admin-tabs-v2 constrained-width\">\n    <form id=\"frmAddProduct\" method=\"post\" action=\"";
            echo $whmcs->getPhpSelf();
            echo "?action=add\" class=\"form-horizontal\">\n        <div class=\"col-lg-9 col-lg-offset-3 col-md-8 col-sm-offset-4\">\n            <h2>";
            echo $aInt->lang("products", "createnewproduct");
            echo "</h2>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputGroup\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
            echo AdminLang::trans("fields.producttype");
            echo "<br>\n                <small>";
            echo AdminLang::trans("products.productTypeDescription");
            echo "</small>\n            </label>\n            <div class=\"col-lg-9 col-sm-8\">\n                <input type=\"hidden\" name=\"type\" value=\"hostingaccount\" id=\"inputProductType\">\n                <div class=\"multi-select-blocks product-creation-types clearfix\">\n                    <div class=\"block\">\n                        <div class=\"type active\" data-type=\"hostingaccount\" id=\"productTypeShared\">\n                            <i class=\"fas fa-server\"></i>\n                            <span>";
            echo AdminLang::trans("products.hostingaccount");
            echo "</span>\n                        </div>\n                    </div>\n                    <div class=\"block\">\n                        <div class=\"type\" data-type=\"reselleraccount\" id=\"productTypeReseller\">\n                            <i class=\"fas fa-cloud\"></i>\n                            <span>";
            echo AdminLang::trans("products.reselleraccount");
            echo "</span>\n                        </div>\n                    </div>\n                    <div class=\"block\">\n                        <div class=\"type\" data-type=\"server\" id=\"productTypeServer\">\n                            <i class=\"fas fa-hdd\"></i>\n                            <span>";
            echo AdminLang::trans("products.dedicatedvpsserver");
            echo "</span>\n                        </div>\n                    </div>\n                    <div class=\"block\">\n                        <div class=\"type\" data-type=\"other\" id=\"productTypeOther\">\n                            <i class=\"fas fa-cube\"></i>\n                            <span>";
            echo AdminLang::trans("products.otherproductservice");
            echo "</span>\n                        </div>\n                    </div>\n                </div>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputGroup\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
            echo AdminLang::trans("products.productgroup");
            echo "<br>\n                <small><a href=\"configproducts.php?action=creategroup\">";
            echo AdminLang::trans("products.createNewProductGroup");
            echo "</a></small>\n            </label>\n            <div class=\"col-lg-4 col-sm-4\">\n                <select name=\"gid\" id=\"inputGroup\" class=\"form-control\">\n                    ";
            foreach ($productGroups as $productGroup) {
                if (empty($groupSlug)) {
                    $groupSlug = $productGroup->slug;
                }
                echo "<option value=\"" . $productGroup->id . "\">" . $productGroup->name . "</option>";
            }
            echo "                </select>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputProductName\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
            echo AdminLang::trans("products.productname");
            echo "<br>\n                <small>";
            echo AdminLang::trans("products.productnameDescription");
            echo "</small>\n            </label>\n            <div class=\"col-lg-5 col-sm-6\">\n                <input type=\"text\" class=\"form-control new\" name=\"productname\" id=\"inputProductName\" required>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputProductName\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
            echo AdminLang::trans("products.slugURL");
            echo "<br>\n                <small>";
            echo AdminLang::trans("products.slugURLDescription");
            echo "</small>\n            </label>\n            <div class=\"col-lg-9 col-sm-8\">\n                <div class=\"inline-edit-wrapper\">\n                    <span id=\"spanRoutePath\">";
            echo fqdnRoutePath("store-product-group", $groupSlug) . "/";
            echo "</span>\n                    <input type=\"text\"\n                           name=\"slug\"\n                           value=\"";
            echo $slug;
            echo "\"\n                           class=\"form-control input-inline inline-edit\"\n                           id=\"inputSlug\"\n                           style=\"width: 8px;\"\n                           tabindex=\"-1\"\n                    <span>\n                        <button class=\"btn btn-sm\"\n                                id=\"btnCopyToClipboard\"\n                                type=\"button\"\n                                disabled=\"disabled\"\n                        >\n                            <img src=\"../assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">\n                        </button>\n                    </span>\n                    <span id=\"slugLoader\" class=\"hidden\">\n                        <i class=\"fa fa-spinner fa-spin\"></i>\n                        ";
            echo AdminLang::trans("products.slugValidate");
            echo "                    </span>\n                    <span id=\"slugOk\" class=\"text-success hidden\">\n                        <i class=\"fa fa-check\"></i>\n                        ";
            echo AdminLang::trans("global.ok");
            echo "                    </span>\n                    <span class=\"text-danger hidden\" id=\"slugInvalidError\"></span>\n                    <span class=\"text-info\" id=\"slug-change-warning\" style=\"display:none;\">\n                        <i class=\"fad fa-exclamation-triangle\"\n                           data-toggle=\"tooltip\"\n                           data-placement=\"top\"\n                           title=\"";
            echo AdminLang::trans("products.productSlugChanged");
            echo "\"\n                           id=\"slug-change-tooltip\"\n                        ></i>\n                    </span>\n                </div>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputProductModule\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
            echo AdminLang::trans("fields.module");
            echo "<br>\n                <small>";
            echo AdminLang::trans("products.moduleDescription");
            echo "</small>\n            </label>\n            <div class=\"col-lg-3 col-sm-5\">\n                <select name=\"module\" class=\"form-control\" id=\"inputProductModule\">\n                    <option value=\"\">";
            echo AdminLang::trans("global.noModule");
            echo "</option>\n                    ";
            $moduleInterface = new WHMCS\Module\Server();
            $moduleList = collect($moduleInterface->getListWithDisplayNames());
            $promotedModules = collect(["cpanel", "plesk", "directadmin", "licensing", "autorelease"]);
            echo "<optgroup label=\"" . AdminLang::trans("global.popularModules") . "\">";
            foreach ($promotedModules as $module) {
                if ($moduleList->has($module)) {
                    echo "<option value=\"" . $module . "\"" . ($module == $inputModule ? " selected" : "") . ">" . $moduleList[$module] . "</option>";
                }
            }
            echo "</optgroup>";
            echo "<optgroup label=\"" . AdminLang::trans("global.otherModules") . "\">";
            foreach ($moduleList as $module => $displayName) {
                if (!$promotedModules->contains($module)) {
                    echo "<option value=\"" . $module . "\"" . ($module == $inputModule ? " selected" : "") . ">" . $displayName . "</option>";
                }
            }
            echo "</optgroup>                </select>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputHidden\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
            echo AdminLang::trans("products.createAsHidden");
            echo "<br>\n                <small>";
            echo AdminLang::trans("products.createAsHiddenDescription");
            echo "</small>\n            </label>\n            <div class=\"col-lg-5 col-sm-6\">\n                <input type=\"checkbox\" class=\"slide-toggle\" name=\"createhidden\" id=\"inputHidden\" checked>\n            </div>\n        </div>\n\n        <div class=\"btn-container\">\n            <input type=\"submit\" value=\"";
            echo $aInt->lang("global", "continue");
            echo " &raquo;\" class=\"btn btn-primary\" id=\"btnContinue\" disabled=\"disabled\" />\n        </div>\n\n        <br>\n        <div class=\"alert alert-grey\">\n            <i class=\"fas fa-info-circle fa-fw\"></i>\n            Looking to add a MarketConnect product such as SSL, Website Builder or Backups? Visit the <a href=\"marketconnect.php\">MarketConnect Portal</a>\n        </div>\n    </form>\n</div>\n\n";
        } else {
            if ($action == "duplicate") {
                checkPermission("Create New Products/Services");
                $productsList = WHMCS\Product\Product::orderBy("order")->orderBy("name")->get()->sortBy(function ($product) {
                    return $product->productGroup->displayOrder;
                });
                $duplicableProducts = $nonDuplicableProducts = [];
                foreach ($productsList as $product) {
                    if ($product->isMarketConnectProduct()) {
                        $nonDuplicableProducts[] = $product;
                    } else {
                        $duplicableProducts[] = $product;
                    }
                }
                echo "\n<h2>";
                echo $aInt->lang("products", "duplicateproduct");
                echo "</h2>\n\n<form method=\"post\" action=\"";
                echo $whmcs->getPhpSelf();
                echo "?action=duplicatenow\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td width=150 class=\"fieldlabel\">";
                echo AdminLang::trans("products.existingproduct");
                echo "</td>\n    <td class=\"fieldarea\">\n        <select name=\"existingproduct\" class=\"form-control select-inline\">\n        ";
                if (!empty($duplicableProducts)) {
                    echo "            <optgroup label=\"";
                    echo AdminLang::trans("products.duplicable");
                    echo "\">\n                ";
                    foreach ($duplicableProducts as $product) {
                        echo "<option value=\"" . $product->id . "\">" . $product->productGroup->name . " - " . $product->name . "</option>";
                    }
                    echo "            </optgroup>\n            ";
                }
                if (!empty($nonDuplicableProducts)) {
                    echo "            <optgroup label=\"";
                    echo AdminLang::trans("products.nonDuplicable");
                    echo "\">\n                ";
                    foreach ($nonDuplicableProducts as $product) {
                        echo "<option value=\"" . $product->id . "\" disabled=\"disabled\">" . $product->productGroup->name . " - " . $product->name . "</option>";
                    }
                    echo "            </optgroup>\n            ";
                }
                echo "        </select>\n        ";
                if (!empty($nonDuplicableProducts)) {
                    echo "            <i class=\"fas fa-exclamation-triangle hidden-xs\"\n               data-toggle=\"tooltip\"\n               data-container=\"body\"\n               data-placement=\"right auto\"\n               data-trigger=\"hover\"\n               title=\"";
                    echo AdminLang::trans("products.nonDuplicableWarn");
                    echo "\"\n            ></i>\n        ";
                }
                echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
                echo AdminLang::trans("products.newproductname");
                echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" class=\"form-control input-500\" name=\"newproductname\" />\n    </td>\n</tr>\n</table>\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
                echo $aInt->lang("global", "continue");
                echo " &raquo;\" class=\"btn btn-primary\">\n</div>\n</form>\n\n";
            } else {
                if ($action == "creategroup" || $action == "editgroup") {
                    checkPermission("Manage Product Groups");
                    if ($ids) {
                        $productGroup = WHMCS\Product\Group::find($ids);
                        $data = $productGroup->toArray();
                        $ids = (int) $data["id"];
                        $name = $data["name"];
                        $slug = $data["slug"];
                        $headline = $data["headline"];
                        $tagline = $data["tagline"];
                        $orderfrmtpl = $data["orderfrmtpl"];
                        $disabledgateways = $data["disabledgateways"];
                        $hidden = $data["hidden"];
                    } else {
                        $ids = 0;
                        $name = "";
                        $slug = "";
                        $headline = "";
                        $tagline = "";
                        $orderfrmtpl = "";
                        $disabledgateways = "";
                        $hidden = "";
                    }
                    try {
                        $defaultTemplate = WHMCS\View\Template\OrderForm::getDefault();
                        $systemOrderFormTemplate = $defaultTemplate->getName();
                        $defaultTemplateDisplayName = $defaultTemplate->getDisplayName();
                    } catch (Throwable $e) {
                        $systemOrderFormTemplate = WHMCS\View\Template\OrderForm::defaultName();
                        $defaultTemplateDisplayName = "Standard Cart";
                        echo infoBox(AdminLang::trans("global.erroroccurred"), AdminLang::trans("products.orderFormsNotFound"), "error");
                    }
                    $disabledgateways = explode(",", $disabledgateways);
                    if (!$ids && WHMCS\Config\Setting::getValue("EnableTranslations")) {
                        WHMCS\Language\DynamicTranslation::whereIn("related_type", ["product_group.{id}.headline", "product_group.{id}.name", "product_group.{id}.tagline"])->where("related_id", "=", 0)->delete();
                    }
                    echo "\n<h2>";
                    echo $aInt->lang("products", $action == "creategroup" ? "creategroup" : "editgroup");
                    echo "</h2>\n\n";
                    if (App::getFromRequest("prodcreatenogroups")) {
                        echo infoBox(AdminLang::trans("products.productGroupRequired"), AdminLang::trans("products.productGroupRequiredDescription"));
                    } else {
                        if (App::getFromRequest("slugerror")) {
                            $error = "products." . App::getFromRequest("slugerror");
                            echo infoBox(AdminLang::trans("products.invalidSlug"), AdminLang::trans($error, [":slug" => WHMCS\Input\Sanitize::convertToCompatHtml(App::getFromRequest("slug"))]));
                        } else {
                            if ($success) {
                                echo infoBox(AdminLang::trans("global.changesuccess"), AdminLang::trans("global.changesuccessdesc") . " <div style=\"float:right;margin-top:-15px;\"><input type=\"button\" id=\"backToProductList\" value=\"&laquo; " . AdminLang::trans("products.backtoproductlist") . "\" onClick=\"window.location='configproducts.php'\" class=\"btn btn-default btn-sm\"></div>");
                            }
                        }
                    }
                    echo "\n<form id=\"frmAddProductGroup\" method=\"post\" action=\"";
                    echo $whmcs->getPhpSelf();
                    echo "?sub=savegroup&ids=";
                    echo $ids;
                    echo "\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td width=\"25%\" class=\"fieldlabel\">";
                    echo $aInt->lang("products", "productgroupname");
                    echo "</td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"name\" value=\"";
                    echo $name;
                    echo "\" class=\"form-control input-400 input-inline";
                    if (!$ids) {
                        echo " new";
                    }
                    echo "\" placeholder=\"";
                    echo AdminLang::trans("products.productgroupnamePlaceHolder");
                    echo "\" id=\"inputGroupName\">\n        ";
                    echo $aInt->getTranslationLink("product_group.name", $ids);
                    echo "    </td>\n</tr>\n<tr>\n    <td width=\"25%\" class=\"fieldlabel\">\n        ";
                    echo AdminLang::trans("products.slugURL");
                    echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"inline-edit-wrapper\">\n            <span id=\"spanRoutePath\">";
                    echo fqdnRoutePath("store") . "/";
                    echo "</span>\n            <input type=\"text\"\n                name=\"slug\"\n                value=\"";
                    echo $slug;
                    echo "\"\n                class=\"form-control input-inline inline-edit\"\n                id=\"inputSlug\"\n                style=\"width:";
                    echo (strlen($slug) + 1) * 8;
                    echo "px\"\n                tabindex=\"-1\"\n            <span>\n                <button class=\"btn btn-sm\"\n                        id=\"btnCopyToClipboard\"\n                        type=\"button\"\n                        ";
                    echo !$slug ? "disabled=\"disabled\"" : "";
                    echo "                >\n                    <img src=\"../assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">\n                </button>\n            </span>\n            <span id=\"slugLoader\" class=\"hidden\">\n                <i class=\"fa fa-spinner fa-spin\"></i>\n                ";
                    echo AdminLang::trans("products.slugValidate");
                    echo "            </span>\n            <span id=\"slugOk\" class=\"text-success hidden\">\n                <i class=\"fa fa-check\"></i>\n                ";
                    echo AdminLang::trans("global.ok");
                    echo "            </span>\n            <span class=\"text-danger hidden\" id=\"slugInvalidError\"></span>\n            <span class=\"text-info\" id=\"slug-change-warning\" style=\"display:none;\">\n                <i class=\"fad fa-exclamation-triangle\"\n                   data-toggle=\"tooltip\"\n                   data-placement=\"top\"\n                   title=\"";
                    echo AdminLang::trans("products.slugChanged");
                    echo "\"\n                   id=\"slug-change-tooltip\"\n                ></i>\n            </span>\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
                    echo AdminLang::trans("products.groupHeadline");
                    echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" id=\"headline\" name=\"headline\" value=\"";
                    echo $headline;
                    echo "\" class=\"form-control input-700 input-inline\" placeholder=\"";
                    echo AdminLang::trans("products.groupHeadlinePlaceHolder");
                    echo "\" />\n        ";
                    echo $aInt->getTranslationLink("product_group.headline", $ids);
                    echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
                    echo AdminLang::trans("products.groupTagline");
                    echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" id=\"tagline\" name=\"tagline\" value=\"";
                    echo $tagline;
                    echo "\" class=\"form-control input-700 input-inline\" placeholder=\"";
                    echo AdminLang::trans("products.groupTaglinePlaceHolder");
                    echo "\" />\n        ";
                    echo $aInt->getTranslationLink("product_group.tagline", $ids);
                    echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
                    echo AdminLang::trans("products.groupFeatures");
                    echo "    </td>\n    <td class=\"fieldarea\">\n        ";
                    if ($action == "editgroup") {
                        $changesSavedSuccessfully = AdminLang::trans("global.changesuccess");
                        $description = AdminLang::trans("products.groupFeaturesDescription");
                        echo "<div class=\"feature-list-desc\">\n    " . $description . "\n</div>\n<div id=\"featureList\" class=\"clearfix list-group feature-list\">";
                        $featureList = WHMCS\Product\Group\Feature::orderBy("order")->where("product_group_id", "=", $ids)->get();
                        foreach ($featureList as $feature) {
                            echo "<div class=\"list-group-item\" data-id=\"" . $feature->id . "\">\n    <span class=\"badge remove-feature\" data-id=\"" . $feature->id . "\">\n        <i class=\"glyphicon glyphicon-remove\"></i>\n    </span>\n    <span class=\"glyphicon glyphicon-move\" aria-hidden=\"true\"></span>\n    <span class=\"product-group-feature\">" . $feature->feature . "</span>\n</div>";
                        }
                        $addNewFeature = AdminLang::trans("products.addNewFeature");
                        $addNew = AdminLang::trans("global.addnew");
                        echo "</div>\n<div id=\"new-features\" class=\"input-group\">\n    <input type=\"text\" name=\"new-feature\" id=\"new-feature\" placeholder=\"" . $addNewFeature . "\" class=\"form-control\" />\n    <span class=\"input-group-btn\">\n        <button type=\"button\" id=\"new-feature-add\" class=\"btn btn-warning width-120\">\n        <i class=\"fas fa-spinner fa-spin hidden\" id=\"new-feature-add-spinner\"></i>\n            " . $addNew . "\n        </button>\n    </span>\n</div>";
                    } else {
                        echo "<div style=\"padding:7px 10px;color:#888;font-style:italic;\">" . AdminLang::trans("products.groupSave") . "</div>";
                    }
                    echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                    echo $aInt->lang("products", "orderfrmtpl");
                    echo "</td>\n    <td class=\"fieldarea\">\n        ";
                    if ($action != "creategroup") {
                        echo "            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"orderfrmtpl\" value=\"default\"";
                        if (!$orderfrmtpl) {
                            echo " checked";
                        }
                        echo " />\n                ";
                        echo $aInt->lang("products", "groupTemplateUseSystemDefault");
                        echo " (";
                        echo $defaultTemplateDisplayName;
                        echo ")\n            </label>\n            <label class=\"radio-inline\">\n                <input type=\"radio\" name=\"orderfrmtpl\" value=\"custom\"";
                        if ($orderfrmtpl) {
                            echo " checked";
                        }
                        echo " />\n                ";
                        echo $aInt->lang("products", "groupTemplateUseSpecificTemplate");
                        echo "            </label>\n        ";
                    }
                    echo "        <div id=\"orderFormTemplateOptions\" style=\"padding:15px;clear:both;\"";
                    echo $action == "editgroup" && !$orderfrmtpl ? " class=\"hidden\"" : "";
                    echo ">\n\n";
                    try {
                        $orderFormTemplates = WHMCS\View\Template\OrderForm::all();
                        $priorityOrderFormTemplates = ["standard_cart" => 100, "premium_comparison" => 101, "pure_comparison" => 99, "supreme_comparison" => 97, "universal_slider" => 96, "cloud_slider" => 95];
                        $count = 0;
                        $orderFormTemplates = $orderFormTemplates->sortBy(function (WHMCS\View\Template\OrderForm $template) use($count) {
                            $count--;
                            if (array_key_exists($template->getName(), $priorityOrderFormTemplates)) {
                                return $count - $priorityOrderFormTemplates[$template->getName()];
                            }
                            return 0;
                        });
                    } catch (WHMCS\Exception $e) {
                        $aInt->gracefulExit("Order Form Templates directory is missing. Please reupload /templates/orderforms/");
                    }
                    $radioSelectOrderForm = $orderfrmtpl;
                    if ($action == "creategroup" || !$orderfrmtpl) {
                        $radioSelectOrderForm = $systemOrderFormTemplate;
                    }
                    echo WHMCS\Admin\Setup\General\TemplateHelper::adminAreaOrderFormRadioHTML($orderFormTemplates, App::getClientAreaTemplate(), $radioSelectOrderForm);
                    echo "\n        </div>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                    echo $aInt->lang("products", "availablepgways");
                    echo "</td>\n    <td class=\"fieldarea\" style=\"padding:7px 10px;\">\n        ";
                    $gateways = getGatewaysArray();
                    foreach ($gateways as $gateway => $displayName) {
                        echo "<label class=\"checkbox-inline\">\n<input type=\"checkbox\" name=\"gateways[" . $gateway . "] pgateway_checkbox\"" . (!in_array($gateway, $disabledgateways) ? " checked" : "") . " />\n" . $displayName . "\n</label>";
                    }
                    echo "    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
                    echo $aInt->lang("fields", "hidden");
                    echo "</td>\n    <td class=\"fieldarea\">\n        <label class=\"checkbox-inline\">\n            <input type=\"checkbox\" name=\"hidden\"";
                    if ($hidden) {
                        echo " checked";
                    }
                    echo ">\n            ";
                    echo $aInt->lang("products", "hiddengroupdesc");
                    echo "        </label>\n    </td>\n</tr>\n</table>\n\n    <div class=\"btn-container\">\n        <button type=\"submit\" id=\"btnSaveProductGroup\" class=\"btn btn-primary disable-submit\">";
                    echo AdminLang::trans("global.savechanges");
                    echo "</button>\n        <input type=\"button\" value=\"";
                    echo AdminLang::trans("global.cancelchanges");
                    echo "\" onclick=\"window.location='configproducts.php'\" class=\"btn btn-default\" />\n    </div>\n</form>\n\n<script>\n    function validateSlug() {\n        var value = \$('#inputSlug').val();\n        WHMCS.http.jqClient.jsonPost({\n            url: WHMCS.adminUtils.getAdminRouteUrl('/setup/product/group/validate/slug'),\n            data: {\n                groupid: '";
                    echo $ids;
                    echo "',\n                groupname: \$('#inputGroupName').val(),\n                slug: value,\n                token: csrfToken\n            },\n            success: function(data) {\n                \$('#slugLoader').hide();\n                if (data.slug) {\n                    \$('#inputSlug').val(data.slug);\n                    adjustSlugInputSize();\n                }\n                if (data.invalidSlug) {\n                    \$('#slugInvalidError').text(data.invalidReason).removeClass('hidden').show();\n                } else {\n                    \$('#slugOk').removeClass('hidden').show();\n                    \$('#slugInvalidError').hide();\n                    \$('#btnSaveProductGroup').removeProp('disabled');\n                    \$('#btnCopyToClipboard').removeProp('disabled');\n                }\n                validateSlugChange();\n            }\n        });\n    }\n</script>\n\n";
                    if ($action == "editgroup") {
                        echo WHMCS\View\Asset::jsInclude("Sortable.min.js");
                        $token = generate_token("plain");
                        $growlNotificationAdd = WHMCS\View\Helper::jsGrowlNotification("success", "global.success", "global.changesuccessadded");
                        $growlNotificationReorder = WHMCS\View\Helper::jsGrowlNotification("success", "global.success", "global.changesuccesssorting");
                        $growlNotificationDelete = WHMCS\View\Helper::jsGrowlNotification("success", "global.success", "global.changesuccessdeleted");
                        $jquerycode .= "var successMsgShowing = false;\nSortable.create(featureList, {\n    handle: '.glyphicon-move',\n    animation: 150,\n    ghostClass: 'ghost',\n    store: {\n        /**\n         * Get the order of elements. Called once during initialization.\n         * @param   {Sortable}  sortable\n         * @returns {Array}\n         */\n        get: function (sortable) {\n            //do nothing\n            return [];\n        },\n\n        /**\n         * Save the order of elements. Called onEnd (when the item is dropped).\n         * @param {Sortable}  sortable\n         */\n        set: function (sortable) {\n            var order = sortable.toArray();\n            var post = WHMCS.http.jqClient.post(\n                \"configproducts.php\",\n                {\n                    action: \"feature-sort\",\n                    order: order,\n                    token: \"" . $token . "\"\n                }\n            );\n            post.done(\n                function(data) {\n                    " . $growlNotificationReorder . "\n                }\n            );\n        }\n    },\n    filter: \".remove-feature\",\n    onFilter: function (evt) {\n        var item = evt.item;\n        var id = jQuery(item).attr('data-id');\n        var post = WHMCS.http.jqClient.post(\n            \"configproducts.php\",\n            {\n                action: \"remove-feature\",\n                groupId: \"" . $ids . "\",\n                feature: id,\n                token: \"" . $token . "\"\n            }\n        );\n        post.done(\n            function(data) {\n                " . $growlNotificationDelete . "\n            }\n        );\n        item.parentNode.removeChild(item);\n    }\n});\njQuery(\"#new-feature\").keypress(function (e) {\n    if (e.which == 13) {\n        e.preventDefault();\n        jQuery(\"#new-feature-add\").click();\n    }\n});\njQuery(\"#new-feature-add\").on('click', function () {\n    var feature = jQuery(\"#new-feature\").val();\n    if (feature != \"\") {\n        jQuery(\"#new-feature\").val('');\n        jQuery(\"#new-feature-add-spinner\").fadeOut(10).removeClass('hidden').fadeIn(200);\n        jQuery(\"#new-feature-add\").prop('disabled', true);\n        var post = WHMCS.http.jqClient.post(\n            \"configproducts.php\",\n            {\n                action: \"add-feature\",\n                groupId: \"" . $ids . "\",\n                feature: feature,\n                token: \"" . $token . "\"\n            }\n        );\n        post.done(\n            function(data) {\n                jQuery(\"#featureList\").append(data.html);\n                jQuery(\"#new-feature-add-spinner\").fadeOut(200).addClass('hidden');\n                jQuery(\"#new-feature-add\").prop('disabled', false);\n                " . $growlNotificationAdd . "\n            }\n        );\n    }\n});";
                    }
                    $jquerycode .= "\njQuery(\"input[name='orderfrmtpl']\").change(function() {\n    if (jQuery(this).val() == \"custom\") {\n        jQuery(\"#orderFormTemplateOptions\").hide().removeClass(\"hidden\").slideDown();\n    } else {\n        jQuery(\"#orderFormTemplateOptions\").slideUp();\n    }\n})\n";
                }
            }
        }
    }
}
$jquerycode .= "jQuery('#btnCopyToClipboard').click(function() {\n    var copyButton = \$(this),\n        routePath = \$('#spanRoutePath').text(),\n        inputSlug = \$('#inputSlug').val();\n    try {\n        var tempElement = \$('<textarea>')\n            .css('position', 'fixed')\n            .css('opacity', '0')\n            .css('width', '1px')\n            .css('height', '1px')\n            .val(routePath + inputSlug);\n        copyButton.append(tempElement);\n        tempElement.focus().select();\n        document.execCommand('copy');\n    } finally {\n        tempElement.remove()\n    }\n    copyButton.tooltip({\n        trigger: 'click',\n        placement: 'bottom'\n    });\n    WHMCS.ui.toolTip.setTip(copyButton, 'Copied!');\n    WHMCS.ui.toolTip.hideTip(copyButton);\n});\n\n\$('#inputGroup').change(function() {\n    var self = \$(this);\n    \$('#slugLoader').show();\n    \$('#slugInvalidError').hide();\n    \$('#slugOk').hide();\n    WHMCS.http.jqClient.jsonPost({\n        url: WHMCS.adminUtils.getAdminRouteUrl('/setup/product/group/slug'),\n        data: {\n            groupid: self.val(),\n            token: csrfToken\n        },\n        success: function(data) {\n            \$('#slugLoader').hide();\n            if (data.slug) {\n                \$('#spanRoutePath').text(data.slug);\n            }\n            resetSlugInput(\$('#inputSlug').val());\n        }\n    });\n});\n\$(document).ready(function() {\n    \$('#inputGroupName,#inputProductName').keyup(function() {\n        if (!\$(this).hasClass('new')) {\n            return;\n        }\n        var value = \$(this).val();\n        resetSlugInput(value);\n    });\n    \$('#inputSlug').keyup(function() {\n        var value = \$(this).val();\n        resetSlugInput(value);\n    });\n});";
$slugJs = WHMCS\Input\Sanitize::escapeSingleQuotedString($slug);
$jscode .= "var typingTimer = null;\nfunction adjustSlugInputSize() {\n    var inputSlug = \$('#inputSlug');\n    \$(inputSlug).css('width', ((\$(inputSlug).val().length * 7) + 20) + 'px');\n}\nfunction resetSlugInput(value) {\n    \$('#inputSlug').val(slugify(value));\n\n    adjustSlugInputSize();\n\n    \$('#slugInvalidError').hide();\n    \$('#slugOk').hide();\n    \$('#slugLoader').removeClass('hidden').show();\n    \$('#btnSaveProductGroup,#btnSaveProduct,#btnContinue').prop('disabled', true);\n    \$('#btnCopyToClipboard').prop('disabled', true);\n    clearTimeout(typingTimer);\n    typingTimer = setTimeout(validateSlug, 1000);\n}\nfunction validateSlugChange() {\n    var validateSlugChange = false,\n        currentSlug = '" . $slugJs . "',\n        inputSlug = \$('#inputSlug').val();\n\n    if (currentSlug) {\n        validateSlugChange = true;\n    }\n\n    if (validateSlugChange && inputSlug !== currentSlug) {\n        \$('#slug-change-warning').show();\n        \$('#slug-change-tooltip').tooltip('show');\n    } else if (validateSlugChange && inputSlug === currentSlug) {\n        \$('#slug-change-warning').hide();\n        \$('#slug-change-tooltip').tooltip('hide');\n    }\n}";
if (!in_array($action, ["creategroup", "editgroup"])) {
    $jscode .= "\nfunction validateSlug() {\n    var value = \$('#inputSlug').val();\n    WHMCS.http.jqClient.jsonPost(\n        {\n            url: WHMCS.adminUtils.getAdminRouteUrl('/setup/product/validate/slug'),\n            data: {\n                productId: '" . $id . "',\n                productName: \$('#inputProductName').val(),\n                groupId: \$('#inputGroup').val(),\n                slug: value,\n                token: csrfToken\n            },\n            success: function(data) {\n                \$('#slugLoader').hide();\n                if (data.slug) {\n                    \$('#inputSlug').val(data.slug);\n                    adjustSlugInputSize();\n                }\n                if (data.invalidSlug) {\n                    \$('#slugInvalidError').text(data.invalidReason).removeClass('hidden').show();\n                } else {\n                    \$('#slugOk').removeClass('hidden').show();\n                    \$('#slugInvalidError').hide();\n                    \$('#btnSaveProduct,#btnContinue').removeProp('disabled');\n                    \$('#btnCopyToClipboard').removeProp('disabled');\n                }\n                validateSlugChange();\n            }\n        }\n    );\n}";
    $removedText = WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("products.slugRemoved"));
    $jquerycode .= "\$('#deleteProductSlug').find('form')\n    .attr('id', 'frmDeleteProductSlug')\n    .prepend('<input id=\"deletingSlugId\" type=\"hidden\" name=\"slugId\" value=\"\">');\n\$('.delete-slug').click(function() {\n    var self = jQuery(this),\n        slugId = self.data('slug-id');\n    \n    \$('#deletingSlugId').val(slugId);\n    \$('#deleteProductSlug').modal('show');\n});\n\$('#frmDeleteProductSlug').submit(function(e){\n    e.preventDefault();\n    var slugId = \$('#deletingSlugId').val();\n    WHMCS.http.jqClient.jsonPost(\n        {\n            url: WHMCS.adminUtils.getAdminRouteUrl('/setup/product/slug/remove'),\n            data: jQuery('#frmDeleteProductSlug').serialize(),\n            success: function(data) {\n                \$('.delete-slug[data-slug-id=\"' + slugId + '\"]').closest('tr').slideUp().remove();\n                jQuery.growl.notice(\n                    {\n                        title: '',\n                        message: '" . $removedText . "'\n                    }\n                );\n            },\n            always: function() {\n                \$('#deleteProductSlug').modal('hide');\n            }\n        }\n    );\n});";
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
function printProductDownloads($downloads)
{
    if (!is_array($downloads)) {
        $downloads = [];
    }
    echo "<ul class=\"jqueryFileTree\">";
    foreach ($downloads as $downloadid) {
        $result = select_query("tbldownloads", "", ["id" => $downloadid]);
        $data = mysql_fetch_array($result);
        $downid = $data["id"];
        $downtitle = $data["title"];
        $downfilename = $data["location"];
        $downfilenameSplit = explode(".", $downfilename);
        $ext = end($downfilenameSplit);
        echo "<li class=\"file ext_" . $ext . "\"><a href=\"#\" class=\"removedownload\" rel=\"" . $downid . "\">" . $downtitle . "</a></li>";
    }
    echo "</ul>";
}

function buildCategoriesList($level, $parentlevel)
{
    global $categorieslist;
    global $categories;
    $result = select_query("tbldownloadcats", "", ["parentid" => $level], "name", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $parentid = $data["parentid"];
        $category = $data["name"];
        $categorieslist .= "<option value=\"" . $id . "\">";
        for ($i = 1; $i <= $parentlevel; $i++) {
            $categorieslist .= "- ";
        }
        $categorieslist .= $category . "</option>";
        buildCategoriesList($id, $parentlevel + 1);
    }
}
