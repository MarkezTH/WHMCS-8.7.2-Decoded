<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
if (!function_exists("getCustomFields")) {
    require ROOTDIR . "/includes/customfieldfunctions.php";
}
if (!function_exists("getCartConfigOptions")) {
    require ROOTDIR . "/includes/configoptionsfunctions.php";
}
global $currency;
$currency = getCurrency();
$pid = $whmcs->get_req_var("pid");
$gid = $whmcs->get_req_var("gid");
$module = $whmcs->get_req_var("module");
$products = WHMCS\Product\Product::query();
$where = [];
if ($pid) {
    if (is_numeric($pid)) {
        $products->where("tblproducts.id", $pid);
    } else {
        $pids = [];
        foreach (explode(",", $pid) as $p) {
            $p = (int) trim($p);
            if ($p) {
                $pids[] = $p;
            }
        }
        if ($pids) {
            $products->whereIn("tblproducts.id", $pids);
        }
    }
}
if ($gid) {
    $products->where("gid", $gid);
}
if ($module && preg_match("/^[a-zA-Z0-9_\\.\\-]*\$/", $module)) {
    $products->where("servertype", $module);
}
$products->with("productGroup", "activeSlug")->join("tblproductgroups", "tblproductgroups.id", "=", "tblproducts.gid");
$products->orderBy("tblproductgroups.order")->orderBy("tblproductgroups.id")->orderBy("tblproducts.order")->orderBy("tblproducts.id");
$apiresults = ["result" => "success", "totalresults" => $products->count()];
foreach ($products->get("tblproducts.*") as $data) {
    $pid = $data["id"];
    $productarray = ["pid" => $data["id"], "gid" => $data["gid"], "type" => $data["type"], "name" => $data->getRawAttribute("name"), "slug" => $data->activeSlug ? $data->activeSlug->slug : "", "product_url" => $data->activeSlug ? fqdnRoutePath("store-product-product", $data->activeSlug->groupSlug, $data->activeSlug->slug) : "", "description" => $data->getRawAttribute("description"), "module" => $data["servertype"], "paytype" => $data["paytype"]];
    if ($language = $whmcs->get_req_var("language")) {
        $productarray["translated_name"] = WHMCS\Product\Product::getProductName($data["id"], $data["name"], $language);
        $productarray["translated_description"] = WHMCS\Product\Product::getProductDescription($data["id"], $data["description"], $language);
    }
    if ($data["stockcontrol"]) {
        $productarray["stockcontrol"] = "true";
        $productarray["stocklevel"] = $data["qty"];
    }
    $result2 = select_query("tblpricing", "tblcurrencies.code,tblcurrencies.prefix,tblcurrencies.suffix,tblpricing.msetupfee,tblpricing.qsetupfee,tblpricing.ssetupfee,tblpricing.asetupfee,tblpricing.bsetupfee,tblpricing.tsetupfee,tblpricing.monthly,tblpricing.quarterly,tblpricing.semiannually,tblpricing.annually,tblpricing.biennially,tblpricing.triennially", ["type" => "product", "relid" => $pid], "code", "ASC", "", "tblcurrencies ON tblcurrencies.id=tblpricing.currency");
    while ($data = mysql_fetch_assoc($result2)) {
        $code = $data["code"];
        unset($data["code"]);
        $productarray["pricing"][$code] = $data;
    }
    $customfieldsdata = [];
    $customfields = getCustomFields("product", $pid, "", "", "on");
    foreach ($customfields as $field) {
        $customfieldsdata[] = ["id" => $field["id"], "name" => $field["name"], "description" => $field["description"], "required" => $field["required"]];
    }
    $productarray["customfields"]["customfield"] = $customfieldsdata;
    $configoptiondata = [];
    $configurableoptions = getCartConfigOptions($pid, [], "", "", "", true);
    foreach ($configurableoptions as $option) {
        $options = [];
        foreach ($option["options"] as $op) {
            $pricing = [];
            $result4 = select_query("tblpricing", "code,msetupfee,qsetupfee,ssetupfee,asetupfee,bsetupfee,tsetupfee,monthly,quarterly,semiannually,annually,biennially,triennially", ["type" => "configoptions", "relid" => $op["id"]], "", "", "", "tblcurrencies ON tblcurrencies.id=tblpricing.currency");
            while ($oppricing = mysql_fetch_assoc($result4)) {
                $currcode = $oppricing["code"];
                unset($oppricing["code"]);
                $pricing[$currcode] = $oppricing;
            }
            $options["option"][] = ["id" => $op["id"], "name" => $op["name"], "rawName" => $op["rawName"], "recurring" => $op["recurring"], "required" => $op["required"], "pricing" => $pricing];
        }
        $configoptiondata[] = ["id" => $option["id"], "name" => $option["optionname"], "type" => $option["optiontype"], "options" => $options];
    }
    $productarray["configoptions"]["configoption"] = $configoptiondata;
    $apiresults["products"]["product"][] = $productarray;
}
$responsetype = "xml";
