<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$acceptedValues = ["type" => [WHMCS\Product\Product::TYPE_SHARED, WHMCS\Product\Product::TYPE_RESELLER, WHMCS\Product\Product::TYPE_SERVERS, WHMCS\Product\Product::TYPE_OTHER], "payType" => [WHMCS\Product\Product::PAYMENT_FREE, WHMCS\Product\Product::PAYMENT_ONETIME, WHMCS\Product\Product::PAYMENT_RECURRING], "autoSetup" => [WHMCS\Product\Product::AUTO_SETUP_ACCEPT, WHMCS\Product\Product::AUTO_SETUP_DISABLED, WHMCS\Product\Product::AUTO_SETUP_ORDER, WHMCS\Product\Product::AUTO_SETUP_PAYMENT]];
$name = App::getFromRequest("name");
if (!$name) {
    $apiresults = ["result" => "error", "message" => "You must supply a name for the product"];
    return false;
}
$type = App::getFromRequest("type");
if (!$type) {
    $type = "other";
} else {
    if (!in_array($type, $acceptedValues["type"])) {
        $apiresults = ["result" => "error", "message" => "Invalid product type. Must be one of \"hostingaccount\", \"reselleraccount\", \"server\" or \"other\""];
        return false;
    }
}
$gid = App::getFromRequest("gid");
if ($gid) {
    try {
        $group = WHMCS\Product\Group::findOrFail($gid);
    } catch (Exception $e) {
        $apiresults = ["result" => "error", "message" => "You must supply a valid Product Group ID"];
        return false;
    }
    $qty = App::getFromRequest("qty");
    $stockcontrol = App::getFromRequest("stockcontrol");
    if ($stockcontrol || $qty) {
        $stockcontrol = "1";
    } else {
        $stockcontrol = "0";
    }
    $paytype = App::getFromRequest("paytype");
    if (!$paytype) {
        $paytype = WHMCS\Product\Product::PAYMENT_FREE;
    } else {
        if (!in_array($paytype, $acceptedValues["payType"])) {
            $apiresults = ["result" => "error", "message" => "Invalid pay type. Must be one of \"free\", \"onetime\" or \"recurring\""];
            return false;
        }
    }
    $welcomeemail = App::getFromRequest("welcomeemail");
    if (!$welcomeemail) {
        $welcomeemail = 0;
    } else {
        try {
            $template = WHMCS\Mail\Template::findOrFail($welcomeemail);
        } catch (Exception $e) {
            $apiresults = ["result" => "error", "message" => "You must supply a valid welcome email ID"];
            return false;
        }
    }
    $autosetup = App::getFromRequest("autosetup");
    if (!$autosetup) {
        $autosetup = "";
    } else {
        if (!in_array($autosetup, $acceptedValues["autoSetup"])) {
            $apiresults = ["result" => "error", "message" => "Invalid autosetup value. Must be one of \"\", \"on\", \"order\" or \"payment\""];
            return false;
        }
    }
    $servergroupid = App::getFromRequest("servergroupid");
    if (!$servergroupid) {
        $servergroupid = 0;
    } else {
        $serverIdCheck = WHMCS\Database\Capsule::table("tblservergroups")->where("id", $servergroupid)->count();
        if ($servergroupid < 0 || $serverIdCheck === 0) {
            $apiresults = ["result" => "error", "message" => "Invalid server group ID"];
            return false;
        }
    }
    $color = App::getFromRequest("color");
    if ($color) {
        preg_match("/^#[0-9A-Fa-f]{3,6}\$/", $color, $matches);
        if (count($matches) == 0) {
            $apiresults = ["result" => "error", "message" => "The color must be a valid hexadecimal value."];
            return false;
        }
    }
    $slug = App::getFromRequest("slug");
    $description = App::getFromRequest("description");
    $shortdescription = App::getFromRequest("shortdescription");
    $tagline = App::getFromRequest("tagline");
    $hidden = (int) (bool) App::getFromRequest("hidden");
    $showdomainoptions = (int) (bool) App::getFromRequest("showdomainoptions");
    $tax = (int) (bool) App::getFromRequest("tax");
    $isFeatured = (int) (bool) App::getFromRequest("isFeatured");
    $proratabilling = (int) (bool) App::getFromRequest("proratabilling");
    $proratadate = App::getFromRequest("proratadate");
    $proratachargenextmonth = App::getFromRequest("proratachargenextmonth");
    $subdomain = App::getFromRequest("subdomain");
    $module = App::getFromRequest("module");
    $configoption1 = App::getFromRequest("configoption1");
    $configoption2 = App::getFromRequest("configoption2");
    $configoption3 = App::getFromRequest("configoption3");
    $configoption4 = App::getFromRequest("configoption4");
    $configoption5 = App::getFromRequest("configoption5");
    $configoption6 = App::getFromRequest("configoption6");
    $order = App::getFromRequest("order");
    $product = new WHMCS\Product\Product();
    $product->type = $type;
    $product->productGroupId = $gid;
    $product->name = $name;
    if ($slug) {
        try {
            $product->validateSlugIsUnique($slug);
        } catch (WHMCS\Exception\Validation\DuplicateValue $e) {
            $apiresults = ["result" => "error", "message" => "Product slug must be unique"];
            return false;
        }
    }
    $product->description = WHMCS\Input\Sanitize::decode($description);
    $product->shortDescription = WHMCS\Input\Sanitize::decode($shortdescription);
    $product->tagline = WHMCS\Input\Sanitize::decode($tagline);
    $product->isHidden = $hidden;
    $product->showDomainOptions = $showdomainoptions;
    $product->welcomeEmailTemplateId = $welcomeemail;
    $product->stockControlEnabled = $stockcontrol;
    $product->quantityInStock = $qty;
    $product->proRataBilling = $proratabilling;
    $product->proRataChargeDayOfCurrentMonth = $proratadate;
    $product->proRataChargeNextMonthAfterDay = $proratachargenextmonth;
    $product->paymentType = $paytype;
    $product->freeSubDomains = explode(",", $subdomain);
    $product->autoSetup = $autosetup;
    $product->module = $module;
    $product->serverGroupId = $servergroupid;
    $product->moduleConfigOption1 = $configoption1;
    $product->moduleConfigOption2 = $configoption2;
    $product->moduleConfigOption3 = $configoption3;
    $product->moduleConfigOption4 = $configoption4;
    $product->moduleConfigOption5 = $configoption5;
    $product->moduleConfigOption6 = $configoption6;
    $product->applyTax = $tax;
    $product->displayOrder = $order;
    $product->isFeatured = $isFeatured;
    $product->save();
    if (!$slug) {
        $slug = $product->autoGenerateUniqueSlug();
    }
    $product->slugs()->create(["group_id" => $product->productGroupId, "group_slug" => $product->productGroup->slug, "slug" => $slug, "active" => true]);
    $pid = $product->id;
    if (isset($pricing) && is_array($pricing)) {
        $validCurrencies = WHMCS\Database\Capsule::table("tblcurrencies")->pluck("id")->all();
        foreach ($pricing as $currency => $values) {
            if (in_array($currency, $validCurrencies)) {
                $cycleValues = $feeValues = [];
                foreach ((new WHMCS\Billing\Cycles())->getSystemBillingCycles(true) as $cycle) {
                    if (key_exists($cycle, $values)) {
                        $cycleValues[$cycle] = (double) $values[$cycle];
                    } else {
                        $cycleValues[$cycle] = 0;
                    }
                }
                foreach ((new WHMCS\Billing\Pricing())->setupFields() as $fee) {
                    if (key_exists($fee, $values)) {
                        $feeValues[$fee] = (double) $values[$fee];
                    } else {
                        $feeValues[$fee] = 0;
                    }
                }
                $data = array_merge(["type" => "product", "currency" => $currency, "relid" => $pid], $feeValues, $cycleValues);
                WHMCS\Database\Capsule::table("tblpricing")->insert($data);
            }
        }
    }
    if (isset($recommendations) && is_array($recommendations)) {
        foreach ($recommendations as $recommendation) {
            try {
                $recommendedProduct = WHMCS\Product\Product::findOrFail($recommendation["id"]);
            } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $apiresults = ["result" => "error", "message" => "The recommendation product ID is invalid. This must be an existing product ID."];
                return false;
            }
            if (!isset($recommendation["order"]) || !is_int($recommendation["order"])) {
                $recommendation["order"] = 0;
            }
            $product->recommendations()->attach([$recommendedProduct->id => ["sortorder" => $recommendation["order"]]]);
        }
    }
    $apiresults = ["result" => "success", "pid" => $pid];
} else {
    $apiresults = ["result" => "error", "message" => "You must supply a valid Product Group ID"];
    return false;
}
