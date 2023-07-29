<?php

define("CLIENTAREA", true);
define("SHOPPING_CART", true);
require __DIR__ . "/init.php";
require ROOTDIR . "/includes/orderfunctions.php";
require ROOTDIR . "/includes/domainfunctions.php";
require ROOTDIR . "/includes/configoptionsfunctions.php";
require ROOTDIR . "/includes/customfieldfunctions.php";
require ROOTDIR . "/includes/clientfunctions.php";
require ROOTDIR . "/includes/invoicefunctions.php";
require ROOTDIR . "/includes/processinvoices.php";
require ROOTDIR . "/includes/gatewayfunctions.php";
require ROOTDIR . "/includes/modulefunctions.php";
require ROOTDIR . "/includes/ccfunctions.php";
require ROOTDIR . "/includes/cartfunctions.php";
$nameserverRegexPattern = "/^(?!\\-)(?:[a-zA-Z\\d\\-]{0,62}[a-zA-Z\\d]\\.){2,126}(?!\\d+)[a-zA-Z\\d]{1,63}\$/";
initialiseClientArea(Lang::trans("carttitle"), Lang::trans("carttitle"), "", "", "<a href=\"cart.php\">" . Lang::trans("carttitle") . "</a>");
$orderfrm = new WHMCS\OrderForm();
$orderFormTemplate = WHMCS\View\Template\OrderForm::factory();
$orderFormTemplateName = $orderFormTemplate->getName();
$whmcs = WHMCS\Application::getInstance();
$securityqans = $whmcs->get_req_var("securityqans");
$securityqid = $whmcs->get_req_var("securityqid");
$a = $whmcs->get_req_var("a");
$gid = $whmcs->get_req_var("gid");
$pid = $whmcs->get_req_var("pid");
if (substr($pid, 0, 1) == "b") {
    $bid = (int) substr($pid, 1);
    redir("a=add&bid=" . $bid);
} else {
    $pid = (int) $pid;
}
$aid = (int) $whmcs->get_req_var("aid");
$ajax = $whmcs->get_req_var("ajax");
$addProductAjax = $whmcs->get_req_var("addproductajax");
$sld = $whmcs->get_req_var("sld");
$tld = $whmcs->get_req_var("tld");
$domains = $whmcs->get_req_var("domains");
$step = $whmcs->get_req_var("step");
$remote_ip = $whmcs->getRemoteIp();
$cartSession = $orderfrm->getCartData();
$productInfoKey = (int) $whmcs->get_req_var("i");
if ($productInfoKey < 0) {
    $productInfoKey = NULL;
}
$orderfrmtpl = $whmcs->get_config("OrderFormTemplate");
if (!isValidforPath($orderfrmtpl)) {
    exit("Invalid Order Form Template Name");
}
$orderconf = [];
$orderfrmconfig = ROOTDIR . "/templates/orderforms/" . $orderfrmtpl . "/config.php";
if (file_exists($orderfrmconfig)) {
    include $orderfrmconfig;
}
if (!$ajax && isset($orderconf["denynonajaxaccess"]) && is_array($orderconf["denynonajaxaccess"]) && in_array($a, $orderconf["denynonajaxaccess"])) {
    redir();
}
$orderform = true;
$nowrapper = false;
$errormessage = $allowcheckout = $cartApiError = "";
$user = Auth::user();
$client = Auth::client();
$userId = $user ? $user->id : 0;
$clientId = $client ? $client->id : 0;
$currencyid = isset($_SESSION["currency"]) ? $_SESSION["currency"] : "";
$currency = Currency::factoryForClientArea();
$smartyvalues["currency"] = $currency;
$smartyvalues["ipaddress"] = $remote_ip;
$smartyvalues["ajax"] = $ajax ? true : false;
$smartyvalues["taxLabel"] = Lang::trans(WHMCS\Billing\Tax\Vat::getLabel());
$smartyvalues["inShoppingCart"] = true;
$smartyvalues["action"] = $a;
$smartyvalues["numitemsincart"] = $orderfrm->getNumItemsInCart();
$smartyvalues["gid"] = "";
$smartyvalues["domain"] = "";
$captcha = new WHMCS\Utility\Captcha();
if (isset($_SESSION["cart"]["lastconfigured"])) {
    bundlesStepCompleteRedirect($_SESSION["cart"]["lastconfigured"]);
    unset($_SESSION["cart"]["lastconfigured"]);
}
if ($step == "fraudcheck") {
    $a = "fraudcheck";
}
if ($promocode = $whmcs->get_req_var("promocode")) {
    SetPromoCode($promocode);
}
if ($a == "empty") {
    unset($_SESSION["cart"]);
    redir("a=view");
}
if ($a == "startover") {
    unset($_SESSION["cart"]);
    redir();
}
if ($a == "remove" && !is_null($productInfoKey)) {
    if ($r == "p" && isset($_SESSION["cart"]["products"][$productInfoKey])) {
        unset($_SESSION["cart"]["products"][$productInfoKey]);
        $_SESSION["cart"]["products"] = array_values($_SESSION["cart"]["products"]);
    } else {
        if ($r == "a" && isset($_SESSION["cart"]["addons"][$productInfoKey])) {
            unset($_SESSION["cart"]["addons"][$productInfoKey]);
            $_SESSION["cart"]["addons"] = array_values($_SESSION["cart"]["addons"]);
        } else {
            if ($r == "d" && isset($_SESSION["cart"]["domains"][$productInfoKey])) {
                unset($_SESSION["cart"]["domains"][$productInfoKey]);
                $_SESSION["cart"]["domains"] = array_values($_SESSION["cart"]["domains"]);
            } else {
                if ($r == "r" && isset($_SESSION["cart"]["renewals"][$productInfoKey])) {
                    unset($_SESSION["cart"]["renewals"][$productInfoKey]);
                } else {
                    if ($r == "u" && isset($_SESSION["cart"]["upgrades"][$productInfoKey])) {
                        unset($_SESSION["cart"]["upgrades"][$productInfoKey]);
                        $_SESSION["cart"]["upgrades"] = array_values($_SESSION["cart"]["upgrades"]);
                    }
                }
            }
        }
    }
    if ($ajax) {
        $response = new WHMCS\Http\JsonResponse(["success" => true, "r" => $r, "i" => $productInfoKey]);
        $response->send();
        WHMCS\Terminus::getInstance()->doExit();
    }
    redir("a=view");
}
if ($a == "applypromo") {
    $promoerrormessage = SetPromoCode($promocode);
    echo $promoerrormessage;
    exit;
}
if ($a == "validateCaptcha") {
    check_token();
    $error = false;
    $alreadyComplete = WHMCS\Session::get("CaptchaComplete");
    if (!$alreadyComplete) {
        $validate = new WHMCS\Validate();
        $captcha = new WHMCS\Utility\Captcha();
        $captcha->validateAppropriateCaptcha(WHMCS\Utility\Captcha::FORM_DOMAIN_CHECKER, $validate);
        if ($validate->hasErrors()) {
            $error = Lang::trans(in_array($captcha, ["recaptcha", "invisible"]) ? "googleRecaptchaIncorrect" : "captchaverifyincorrect");
            WHMCS\Session::set("CaptchaComplete", false);
        } else {
            WHMCS\Session::set("CaptchaComplete", true);
        }
    }
    $response = new WHMCS\Http\JsonResponse();
    $response->setData(["error" => $error]);
    $response->send();
    WHMCS\Terminus::getInstance()->doExit();
}
if ($a == "checkDomain") {
    (new WHMCS\Domain\Checker())->ajaxCheck();
    WHMCS\Terminus::getInstance()->doExit();
}
if ($a == "addToCart") {
    check_token();
    $domain = App::getFromRequest("domain");
    $domain = new WHMCS\Domains\Domain($domain);
    $whoisCheck = (bool) (int) App::getFromRequest("whois");
    $response = new WHMCS\Http\JsonResponse();
    if (isset($domain)) {
        $validate = new WHMCS\Validate();
        $validate->validate("unique_domain", "unique_domain", "ordererrordomainalreadyexists", "", $domain);
        if ($validate->hasErrors()) {
            $response->setData(["result" => "unavailable"]);
            $response->send();
            WHMCS\Terminus::getInstance()->doExit();
        }
    }
    if ($whoisCheck) {
        $check = new WHMCS\Domain\Checker();
        $check->cartDomainCheck($domain, [$domain->getDotTopLevel()]);
        $searchResult = $check->getSearchResult()->offsetGet(0);
    }
    if (!$whoisCheck || isset($searchResult) && in_array($searchResult->getStatus(), [WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED, WHMCS\Domains\DomainLookup\SearchResult::STATUS_UNKNOWN])) {
        WHMCS\OrderForm::cartPreventDuplicateDomain($domain->getDomain(false));
        $tldPrice = getTLDPriceList($domain->getDotTopLevel());
        $domainArray = ["type" => "register", "domain" => $domain->getDomain(false), "regperiod" => key($tldPrice), "isPremium" => false, "idnLanguage" => App::getFromRequest("idnlanguage")];
        if (!App::getFromRequest("sideorder")) {
            $passedVariables = $_SESSION["cart"]["passedvariables"] ?? NULL;
            unset($_SESSION["cart"]["passedvariables"]);
            if (isset($passedVariables["bitem"])) {
                $domainArray["bitem"] = $passedVariables["bitem"];
            }
            if (isset($passedVariables["bnum"])) {
                $domainArray["bnum"] = $passedVariables["bnum"];
            }
        }
        $premiumData = WHMCS\Session::get("PremiumDomains", []);
        if ((bool) (int) WHMCS\Config\Setting::getValue("PremiumDomains") && array_key_exists($domain->getDomain(), $premiumData)) {
            $premiumPrice = $premiumData[$domain->getDomain()];
            if (array_key_exists("register", $premiumPrice["cost"])) {
                $domainArray["isPremium"] = true;
                $domainArray["domainpriceoverride"] = $premiumPrice["markupPrice"][1]["register"];
                $domainArray["registrarCostPrice"] = $premiumPrice["cost"]["register"];
                $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                $domainArray["domainpriceoverride"] = $domainArray["domainpriceoverride"]->toNumeric();
            }
            if (array_key_exists("renew", $premiumPrice["cost"])) {
                $domainArray["domainrenewoverride"] = $premiumPrice["markupPrice"][1]["renew"];
                $domainArray["registrarRenewalCostPrice"] = $premiumPrice["cost"]["renew"];
                $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                $domainArray["domainrenewoverride"] = $domainArray["domainrenewoverride"]->toNumeric();
            } else {
                $domainArray["isPremium"] = false;
            }
        }
        $_SESSION["cart"]["domains"][] = $domainArray;
        if (isset($domainArray["bnum"])) {
            $_SESSION["cart"]["lastconfigured"] = ["type" => "domain", "i" => count($_SESSION["cart"]["domains"]) - 1];
        }
        $cart = new WHMCS\OrderForm();
        $response->setData(["result" => "added", "period" => key($tldPrice), "cartCount" => $cart->getNumItemsInCart()]);
    } else {
        $response->setData(["result" => isset($searchResult) ? $searchResult->getStatus() : "unavailable"]);
    }
    $response->send();
    WHMCS\Terminus::getInstance()->doExit();
}
if ($a == "addDomainTransfer") {
    check_token();
    $domain = App::getFromRequest("domain");
    $eppCode = App::getFromRequest("epp");
    $domain = new WHMCS\Domains\Domain($domain);
    $searchResult = [];
    try {
        if ($captcha && $captcha->isEnabled() && WHMCS\Session::get("CaptchaComplete") !== true) {
            $validate = new WHMCS\Validate();
            $captcha->validateAppropriateCaptcha(WHMCS\Utility\Captcha::FORM_DOMAIN_CHECKER, $validate);
            if ($validate->hasErrors()) {
                throw new Exception($validate->getErrors()[0]);
            }
            WHMCS\Session::set("CaptchaComplete", true);
        }
        if ($CONFIG["AllowDomainsTwice"] && cartCheckIfDomainAlreadyOrdered($domain)) {
            throw new Exception(Lang::trans("ordererrordomainalreadyexists"));
        }
        if ($domain->getSecondLevel() && $domain->getTopLevel() && $domain->isValidDomainName($domain->getSecondLevel(), $domain->getDotTopLevel())) {
            $check = new WHMCS\Domain\Checker();
            $check->cartDomainCheck($domain, [$domain->getDotTopLevel()]);
            $searchResult = $check->getSearchResult()->offsetGet(0);
            $searchResult = $searchResult instanceof WHMCS\Domains\DomainLookup\SearchResult ? $searchResult->toArray() : [];
            if ($searchResult["isRegistered"]) {
                $extensionConfig = WHMCS\Database\Capsule::table("tbldomainpricing")->where("extension", "=", $domain->getDotTopLevel())->first();
                if (is_null($extensionConfig)) {
                    throw new Exception(Lang::trans("orderForm.domainExtensionTransferNotSupported"));
                }
                $eppCodeRequired = $extensionConfig->eppcode;
                if ($eppCodeRequired && $eppCode || !$eppCodeRequired) {
                    $tldPrice = getTLDPriceList($domain->getDotTopLevel(), false, "transfer");
                    if (!$tldPrice) {
                        throw new Exception(Lang::trans("orderForm.domainExtensionTransferPricingNotConfigured"));
                    }
                    WHMCS\OrderForm::cartPreventDuplicateDomain($domain->getDomain(false));
                    $passedVariables = $_SESSION["cart"]["passedvariables"] ?? [];
                    unset($_SESSION["cart"]["passedvariables"]);
                    $domainArray = ["type" => "transfer", "domain" => $domain->getDomain(false), "regperiod" => key($tldPrice), "eppcode" => $eppCode, "idnLanguage" => "", "isPremium" => false];
                    if (isset($passedVariables["bitem"])) {
                        $domainArray["bitem"] = $passedVariables["bitem"];
                    }
                    if (isset($passedVariables["bnum"])) {
                        $domainArray["bnum"] = $passedVariables["bnum"];
                    }
                    $premiumData = WHMCS\Session::get("PremiumDomains", []);
                    if ((bool) (int) WHMCS\Config\Setting::getValue("PremiumDomains") && array_key_exists($domain->getDomain(), $premiumData)) {
                        $premiumPrice = $premiumData[$domain->getDomain()];
                        if (array_key_exists("transfer", $premiumPrice["cost"])) {
                            $domainArray["isPremium"] = true;
                            $domainArray["domainpriceoverride"] = $premiumPrice["markupPrice"][1]["transfer"];
                            $domainArray["registrarCostPrice"] = $premiumPrice["cost"]["transfer"];
                            $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                            $domainArray["domainpriceoverride"] = $domainArray["domainpriceoverride"]->toNumeric();
                        }
                        if (array_key_exists("renew", $premiumPrice["cost"])) {
                            $domainArray["domainrenewoverride"] = $premiumPrice["markupPrice"][1]["renew"];
                            $domainArray["registrarRenewalCostPrice"] = $premiumPrice["cost"]["renew"];
                            $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                            $domainArray["domainrenewoverride"] = $domainArray["domainrenewoverride"]->toNumeric();
                        } else {
                            $domainArray["isPremium"] = false;
                        }
                    }
                    $_SESSION["cart"]["domains"][] = $domainArray;
                    $searchResult = "added";
                } else {
                    $searchResult["epp"] = $eppCodeRequired ? true : false;
                }
            } else {
                $searchResult["unavailable"] = Lang::trans("ordererrordomainnotregistered");
            }
        } else {
            $searchResult["unavailable"] = Lang::trans("ordererrordomaininvalid");
        }
    } catch (Exception $e) {
        $searchResult = ["unavailable" => $e->getMessage()];
    }
    $response = new WHMCS\Http\JsonResponse();
    $response->setData(["result" => $searchResult]);
    $response->send();
    WHMCS\Terminus::getInstance()->doExit();
}
if ($a == "updateDomainPeriod") {
    check_token();
    $domain = App::getFromRequest("domain");
    $period = App::getFromRequest("period");
    foreach ($_SESSION["cart"]["domains"] as $key => $domainItem) {
        if ($domainItem["domain"] == $domain) {
            $_SESSION["cart"]["domains"][$key]["regperiod"] = $period;
            $response = new WHMCS\Http\JsonResponse();
            $data = calcCartTotals(Auth::client(), false, false);
            $data["forceReload"] = (bool) WHMCS\Session::get("ExpressCheckoutAvailable");
            $response->setData($data);
            $response->send();
            WHMCS\Terminus::getInstance()->doExit();
        }
    }
}
if ($a == "removepromo") {
    $_SESSION["cart"]["promo"] = "";
    if ($ajax) {
        exit;
    }
    redir("a=view");
}
if ($a == "setstateandcountry") {
    $_SESSION["cart"]["user"]["state"] = $state;
    $_SESSION["cart"]["user"]["country"] = $country;
    redir("a=view");
}
if ($a == "addUpSell") {
    check_token();
    $modalLoad = App::getFromRequest("select_modal");
    $productKey = App::getFromRequest("product_key");
    $returnData = [];
    $cartSession = WHMCS\Session::get("cart");
    if (!is_array($addonMap)) {
        $addonMap = [];
    }
    if (!array_key_exists($productKey, $addonMap)) {
        $addonsCollection = WHMCS\Product\Addon::whereHas("moduleConfiguration", function ($query) use($productKey) {
            $query->where("setting_name", "=", "configoption1")->where("value", "=", $productKey);
        })->with("moduleConfiguration")->get();
        foreach ($addonsCollection as $addon) {
            if (count($addon->moduleConfiguration)) {
                $addonMap[$productKey] = $addon->id;
            }
        }
    }
    if ($modalLoad) {
        $productOptions = "";
        foreach ($cartSession["products"] as $infoKey => $product) {
            if (!in_array($addonMap[$productKey], $product["addons"])) {
                $productName = WHMCS\Product\Product::find($product["pid"])->name;
                $domain = empty($product["domain"]) ? Lang::trans("nodomain") : $product["domain"];
                $productOptions .= "<option value=\"" . $infoKey . "\">" . $productName . " - " . $domain . "</option>\r\n";
            }
        }
        $formToken = generate_token();
        $returnData["body"] = "<form action=\"cart.php\" id=\"upsellModalForm\">\n    " . $formToken . "\n    <input type=\"hidden\" name=\"a\" value=\"addUpSell\" />\n    <input type=\"hidden\" name=\"product_key\" value=\"" . $productKey . "\" />\n    <p>Please select the product you would like to add this add-on to:</p>\n    <p><select name=\"item\" class=\"form-control\">" . $productOptions . "</select></p>\n</form>\n<script type=\"text/javascript\">\nvar modalAjax = jQuery('#modalAjax');\nmodalAjax.off('hidden.bs.modal');\nmodalAjax.on('hidden.bs.modal', function (e) {\n    jQuery('#promo_" . $productKey . " .btn-add').prop('disabled', false).find('span.arrow i')\n        .addClass('fa-chevron-right').removeClass('fa-spinner fa-spin');\n})\n</script>";
    } else {
        $cartProducts = $cartSession["products"];
        foreach ($cartProducts as $key => $data) {
            $addonIds = collect($data["addons"])->pluck("addonid")->toArray();
            if (in_array($addonMap[$productKey], $addonIds)) {
                unset($cartProducts[$key]);
            }
        }
        if (1 < count($cartProducts) && !App::isInRequest("item")) {
            $returnData["modal"] = "cart.php?select_modal=true&a=addUpSell&product_key=" . $productKey . generate_token("link");
            $returnData["modalTitle"] = Lang::trans("cartproductselection");
            $returnData["modalSubmit"] = Lang::trans("orderForm.add");
            $returnData["modelSubmitId"] = App::isInRequest("checkoutModal") ? "btnAddUpSellCheckout" : "btnAddUpSell";
        } else {
            reset($cartProducts);
            $productItemKey = key($cartProducts);
            if (App::isInRequest("item")) {
                $productItemKey = App::getFromRequest("item");
                $returnData["dismiss"] = true;
            }
            $marketConnectCart = new WHMCS\MarketConnect\Promotion\Helper\Cart();
            $addonIds = collect($cartSession["products"][$productItemKey]["addons"])->pluck("addonid")->toArray();
            if (isset($cartSession["products"][$productItemKey]) && !in_array($addonMap[$productKey], $addonIds)) {
                $toAdd = true;
                foreach ($cartSession["products"][$productItemKey]["addons"] as &$addonData) {
                    $addonId = $addonData["addonid"];
                    if ($marketConnectCart->isUpSellForAddon($addonId, $addonMap[$productKey])) {
                        $addonData["addonid"] = $addonMap[$productKey];
                        $toAdd = false;
                    }
                }
                if ($toAdd) {
                    $cartSession["products"][$productItemKey]["addons"][] = ["addonid" => $addonMap[$productKey], "qty" => 1];
                }
                WHMCS\Session::set("cart", $cartSession);
            }
            $cartTotals = calcCartTotals(Auth::client(), false, false);
            $returnData = ["done" => true, "newTotal" => $cartTotals["total"], "reloadPage" => true];
        }
    }
    $response = new WHMCS\Http\JsonResponse();
    $response->setData($returnData);
    $response->send();
    WHMCS\Terminus::getInstance()->doExit();
}
if ((!$a || $a == "add" && $pid) && ($sld && $tld && !is_array($sld) || is_array($domains))) {
    if (is_array($domains)) {
        $tempdomain = $domains[0];
        $tempdomain = explode(".", $tempdomain, 2);
        $sld = $tempdomain[0];
        $tld = "." . $tempdomain[1];
    }
    $_SESSION["cartdomain"]["sld"] = $sld;
    $_SESSION["cartdomain"]["tld"] = $tld;
}
$productgroups = $orderfrm->getProductGroups();
$smarty->assign("productgroups", $productgroups);
$smartyvalues["registerdomainenabled"] = (bool) WHMCS\Config\Setting::getValue("AllowRegister");
$smartyvalues["transferdomainenabled"] = (bool) WHMCS\Config\Setting::getValue("AllowTransfer");
$smartyvalues["renewalsenabled"] = (bool) WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders");
if (!$a) {
    if ($gid == "domains") {
        redir("a=add&domain=register");
    } else {
        if ($gid == "registerdomain") {
            redir("a=add&domain=register");
        } else {
            if ($gid == "transferdomain") {
                redir("a=add&domain=transfer");
            } else {
                if ($gid == "viewcart") {
                    redir("a=view");
                } else {
                    if ($gid == "addons") {
                        Auth::requireLoginAndClient(true);
                        $smartyvalues["gid"] = "addons";
                        $templatefile = "addons";
                        $where = [];
                        $where["userid"] = $clientId;
                        $where["domainstatus"] = "Active";
                        if ($pid) {
                            $where["tblhosting.id"] = $pid;
                        }
                        $productids = [];
                        $result = select_query("tblhosting", "tblhosting.id,billingcycle,domain,packageid,tblproducts.name as product_name", $where, "", "", "", "tblproducts ON tblproducts.id=tblhosting.packageid");
                        while ($data = mysql_fetch_array($result)) {
                            $productstoids[$data["packageid"]][] = ["id" => $data["id"], "product" => WHMCS\Product\Product::getProductName($data["packageid"], $data["product_name"]), "domain" => $data["domain"]];
                            if (!in_array($data["packageid"], $productids)) {
                                $productids[] = $data["packageid"];
                            }
                        }
                        $addonids = [];
                        $result = select_query("tbladdons", "id,hidden,retired,packages", "");
                        while ($data = mysql_fetch_array($result)) {
                            if (!($data["hidden"] || $data["retired"])) {
                                $id = $data["id"];
                                $packages = $data["packages"];
                                $packages = explode(",", $packages);
                                foreach ($productids as $productid) {
                                    if (in_array($productid, $productids) && !in_array($id, $addonids)) {
                                        $addonids[] = $id;
                                    }
                                }
                            }
                        }
                        $addons = [];
                        if (count($addonids)) {
                            $addonModels = WHMCS\Product\Addon::whereIn("id", $addonids)->orderBy("weight", "ASC")->orderBy("name", "ASC")->get();
                            foreach ($addonModels as $addonModel) {
                                $addonid = $addonModel->id;
                                $packages = $addonModel->packages;
                                $name = $addonModel->name;
                                $description = $addonModel->description;
                                $billingcycle = WHMCS\ClientArea::getRawStatus($addonModel->billingCycle);
                                $free = false;
                                $result2 = select_query("tblpricing", "", ["type" => "addon", "currency" => $currency["id"], "relid" => $addonid]);
                                $data = mysql_fetch_array($result2);
                                switch ($billingcycle) {
                                    case "free":
                                    case "freeaccount":
                                        $free = true;
                                        break;
                                    case "onetime":
                                    case "monthly":
                                    case "quarterly":
                                    case "semiannually":
                                    case "annually":
                                    case "biennially":
                                    case "triennially":
                                        $setupfee = $data["msetupfee"];
                                        $recurring = $data["monthly"];
                                        break;
                                    case "recurring":
                                    default:
                                        if (0 <= $data["monthly"]) {
                                            $setupfee = $data["msetupfee"];
                                            $recurring = $data["monthly"];
                                            $billingcycle = "monthly";
                                        } else {
                                            if (0 <= $data["quarterly"]) {
                                                $setupfee = $data["qsetupfee"];
                                                $recurring = $data["quarterly"];
                                                $billingcycle = "quarterly";
                                            } else {
                                                if (0 <= $data["semiannually"]) {
                                                    $setupfee = $data["ssetupfee"];
                                                    $recurring = $data["semiannually"];
                                                    $billingcycle = "semiannually";
                                                } else {
                                                    if (0 <= $data["annually"]) {
                                                        $setupfee = $data["asetupfee"];
                                                        $recurring = $data["annually"];
                                                        $billingcycle = "annually";
                                                    } else {
                                                        if (0 <= $data["biennially"]) {
                                                            $setupfee = $data["bsetupfee"];
                                                            $recurring = $data["biennially"];
                                                            $billingcycle = "biennially";
                                                        } else {
                                                            if (0 <= $data["triennially"]) {
                                                                $setupfee = $data["tsetupfee"];
                                                                $recurring = $data["triennially"];
                                                                $billingcycle = "triennially";
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        if (!($billingcycle != WHMCS\Billing\Cycles::CYCLE_FREE && (is_null($recurring) || $recurring < 0))) {
                                            $setupfee = empty($setupfee) || $setupfee == "0.00" ? "" : new WHMCS\View\Formatter\Price($setupfee, $currency);
                                            $billingcycle = $_LANG["orderpaymentterm" . $billingcycle];
                                            $packageids = [];
                                            foreach ($packages as $packageid) {
                                                $thisaddonspackages = "";
                                                $thisaddonspackages = $productstoids[$packageid];
                                                if ($thisaddonspackages) {
                                                    $packageids = array_merge($packageids, $thisaddonspackages);
                                                }
                                            }
                                            if (count($packageids)) {
                                                $addons[] = ["id" => $addonid, "name" => $name, "description" => $description, "free" => $free, "setupfee" => $setupfee, "recurringamount" => new WHMCS\View\Formatter\Price($recurring, $currency), "billingcycle" => $billingcycle, "productids" => $packageids];
                                            }
                                        }
                                }
                            }
                        }
                        $smarty->assign("addons", $addons);
                        $smarty->assign("noaddons", count($addons) <= 0);
                    } else {
                        if ($gid == "renewals") {
                            if (!$CONFIG["EnableDomainRenewalOrders"]) {
                                redir("", "clientarea.php");
                            }
                            Auth::requireLoginAndClient(true);
                            try {
                                WHMCS\View\Template\OrderForm::factory("domain-renewals.tpl", $orderFormTemplateName);
                                header("Location: " . routePath("cart-domain-renewals"));
                                WHMCS\Terminus::getInstance()->doExit();
                            } catch (WHMCS\Exception\View\TemplateNotFound $e) {
                            } catch (Exception $e) {
                                App::redirect("clientarea.php");
                            }
                            $smartyvalues["gid"] = "renewals";
                            $templatefile = "domainrenewals";
                            $smartyvalues["productgroups"] = $productgroups;
                            $renewals = WHMCS\Domains::getRenewableDomains($clientId);
                            $smartyvalues["renewals"] = $renewals["renewals"];
                        } else {
                            if ($pid) {
                                $product = WHMCS\Product\Product::find($pid);
                                if ($product && ($routeParts = $product->getRouteParts())) {
                                    unset($_REQUEST["a"]);
                                    unset($_REQUEST["pid"]);
                                    unset($_REQUEST["gid"]);
                                    App::redirectToRoutePath($routeParts["route"], $routeParts["routeVariables"], $_REQUEST);
                                }
                            }
                            if (!$gid) {
                                $gid = $productgroups[0]["gid"];
                            }
                            $productGroup = WHMCS\Product\Group::find($gid);
                            if ($productGroup) {
                                $route = $productGroup->getRoutePath();
                            } else {
                                $route = routePath("store");
                            }
                            header("Location: " . $route);
                            exit;
                        }
                    }
                }
            }
        }
    }
}
if ($a == "add") {
    if ($pid) {
        $productModel = WHMCS\Product\Product::find($pid);
        if (!$productModel) {
            App::redirectToRoutePath("store");
        }
        if (is_null($productModel->pricing()->first())) {
            redir("a=view");
        }
        $routeParts = $productModel->getRouteParts();
        unset($_REQUEST["a"]);
        unset($_REQUEST["pid"]);
        App::redirectToRoutePath($routeParts["route"], $routeParts["routeVariables"], $_REQUEST);
    } else {
        if ($aid) {
            $requestAddonID = (int) $whmcs->get_req_var("aid");
            $requestServiceID = (int) $whmcs->get_req_var("serviceid");
            $requestProductID = (int) $whmcs->get_req_var("productid");
            if (!$requestServiceID && $requestProductID) {
                $requestServiceID = $requestProductID;
            }
            if (!$requestAddonID || !$requestServiceID) {
                redir("gid=addons");
            }
            $data = get_query_vals("tblhosting", "id,packageid", ["id" => $requestServiceID, "userid" => $clientId, "domainstatus" => "Active"]);
            $serviceid = $data["id"];
            $pid = $data["packageid"];
            if (!$serviceid) {
                redir("gid=addons");
            }
            $data = WHMCS\Database\Capsule::table("tbladdons")->where("id", $requestAddonID)->first(["id", "packages", "allowqty"]);
            $aid = $data->id;
            $packages = $data->packages;
            $allowsQuantity = (int) $data->allowqty;
            if ($allowsQuantity === WHMCS\Cart\CartCalculator::QUANTITY_MULTIPLE) {
                $allowsQuantity = 0;
            }
            if (!$aid) {
                redir("gid=addons");
            }
            $packages = explode(",", $packages);
            if (!in_array($pid, $packages)) {
                redir("gid=addons");
            }
            $_SESSION["cart"]["addons"][] = ["id" => $aid, "productid" => $serviceid, "qty" => 1, "allowsQuantity" => $allowsQuantity];
            if ($ajax) {
                exit;
            }
            redir("a=view");
        } else {
            if ($domain = App::getFromRequest("domain")) {
                $allowRegistration = WHMCS\Config\Setting::getValue("AllowRegister");
                $allowTransfers = WHMCS\Config\Setting::getValue("AllowTransfer");
                $allowRenewalOrders = WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders");
                $smartyvalues["idnLanguages"] = WHMCS\Domains\Idna::getLanguages();
                $smartyvalues["domainRegistrationEnabled"] = (bool) $allowRegistration;
                $smartyvalues["registerdomainenabled"] = $smartyvalues["domainRegistrationEnabled"];
                $smartyvalues["domainTransferEnabled"] = (bool) $allowTransfers;
                $smartyvalues["transferdomainenabled"] = $smartyvalues["domainTransferEnabled"];
                $smartyvalues["renewalsenabled"] = (bool) $allowRenewalOrders;
                if (!in_array($domain, ["register", "transfer"])) {
                    $domain = "register";
                }
                if ($domain == "register" && !$allowRegistration) {
                    redir();
                }
                if ($domain == "transfer" && !$allowTransfers) {
                    redir();
                }
                $pricing = localAPI("GetTldPricing", ["clientid" => (int) $clientId, "currencyid" => $currency["id"]]);
                $smartyvalues["pricing"] = $pricing;
                foreach ($smartyvalues["pricing"]["pricing"] as $tld => &$priceData) {
                    foreach (["register", "transfer", "renew"] as $action) {
                        if (isset($priceData[$action]) && is_array($priceData[$action])) {
                            foreach ($priceData[$action] as $term => &$price) {
                                $price = new WHMCS\View\Formatter\Price($price, (array) $smartyvalues["pricing"]["currency"]);
                            }
                        } else {
                            $priceData[$action] = [-1];
                        }
                    }
                }
                unset($price);
                unset($priceData);
                $extensions = array_keys($smartyvalues["pricing"]["pricing"]) ?: [];
                $featuredTlds = [];
                $spotlights = getSpotlightTldsWithPricing();
                foreach ($spotlights as $spotlight) {
                    if (file_exists(ROOTDIR . "/assets/img/tld_logos/" . $spotlight["tldNoDots"] . ".png")) {
                        $featuredTlds[] = $spotlight;
                    }
                }
                $smartyvalues["featuredTlds"] = $featuredTlds;
                $tldCategories = new WHMCS\Domain\TopLevel\Categories();
                $categories = $tldCategories->getCategoriesByTlds($extensions);
                $categoriesWithCounts = [];
                foreach ($categories as $category => $tlds) {
                    $categoriesWithCounts[$category] = count($tlds);
                }
                $smartyvalues["categoriesWithCounts"] = $categoriesWithCounts;
                $smartyvalues["availabilityresults"] = [];
                if ($domains) {
                    $passedvariables = $_SESSION["cart"]["passedvariables"];
                    unset($_SESSION["cart"]["passedvariables"]);
                    foreach ($domains as $domainname) {
                        WHMCS\OrderForm::cartPreventDuplicateDomain($domainname);
                        $regperiod = $domainsregperiod[$domainname];
                        $domainparts = explode(".", $domainname, 2);
                        $temppricelist = getTLDPriceList("." . $domainparts[1]);
                        if (!isset($temppricelist[$regperiod][$domain])) {
                            if (is_array($regperiods)) {
                                foreach ($regperiods as $period) {
                                    if (substr($period, 0, strlen($domainname)) == $domainname) {
                                        $regperiod = substr($period, strlen($domainname));
                                    }
                                }
                            }
                            if (!$regperiod) {
                                $tldyears = array_keys($temppricelist);
                                $regperiod = $tldyears[0];
                            }
                        }
                        $domainArray = ["type" => $domain, "domain" => $domainname, "regperiod" => $regperiod, "eppcode" => $eppcode, "isPremium" => false];
                        if (isset($passedvariables["addons"])) {
                            foreach ($passedvariables["addons"] as $domaddon) {
                                $domainArray[$domaddon] = true;
                            }
                        }
                        if (isset($passedvariables["bnum"])) {
                            $domainArray["bnum"] = $passedvariables["bnum"];
                        }
                        if (isset($passedvariables["bitem"])) {
                            $domainArray["bitem"] = $passedvariables["bitem"];
                        }
                        $premiumData = WHMCS\Session::get("PremiumDomains", []);
                        if ((bool) (int) WHMCS\Config\Setting::getValue("PremiumDomains") && array_key_exists($domainname, $premiumData)) {
                            $premiumPrice = $premiumData[$domainname];
                            if (array_key_exists("transfer", $premiumPrice["cost"])) {
                                $domainArray["isPremium"] = true;
                                $domainArray["domainpriceoverride"] = $premiumPrice["markupPrice"][1]["transfer"];
                                $domainArray["registrarCostPrice"] = $premiumPrice["cost"]["transfer"];
                                $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                                $domainArray["domainpriceoverride"] = $domainArray["domainpriceoverride"]->toNumeric();
                            }
                            if (array_key_exists("renew", $premiumPrice["cost"])) {
                                $domainArray["domainrenewoverride"] = $premiumPrice["markupPrice"][1]["renew"];
                                $domainArray["registrarRenewalCostPrice"] = $premiumPrice["cost"]["renew"];
                                $domainArray["registrarCurrency"] = $premiumPrice["markupPrice"][1]["currency"];
                                $domainArray["domainrenewoverride"] = $domainArray["domainrenewoverride"]->toNumeric();
                            } else {
                                $domainArray["isPremium"] = false;
                            }
                        }
                        $_SESSION["cart"]["domains"][] = $domainArray;
                    }
                    if ($ajax) {
                        $ajax = "&ajax=1";
                    }
                    $newdomnum = count($_SESSION["cart"]["domains"]) - 1;
                    $_SESSION["cart"]["lastconfigured"] = ["type" => "domain", "i" => $newdomnum];
                    if (!$ajax && is_array($orderconf["denynonajaxaccess"]) && in_array("confdomains", $orderconf["denynonajaxaccess"])) {
                        $smartyvalues["selecteddomains"] = $_SESSION["cart"]["domains"];
                        $smartyvalues["skipselect"] = true;
                    } else {
                        redir("a=confdomains" . $ajax);
                    }
                }
                $check = new WHMCS\Domain\Checker();
                if ($domain == "transfer") {
                    if ($orderFormTemplate->hasTemplate("domaintransfer")) {
                        $smarty->assign("captcha", $captcha);
                        $smarty->assign("captchaForm", WHMCS\Utility\Captcha::FORM_DOMAIN_CHECKER);
                        $captchaData = WHMCS\Session::getAndDelete("captchaData");
                        if ($captchaData) {
                            if (!$captchaData["invalidCaptchaError"]) {
                                $captcha->setEnabled(false);
                                $smarty->assign("captcha", $captcha);
                            } else {
                                $smarty->assign("captchaError", $captchaData["invalidCaptchaError"]);
                            }
                        } else {
                            WHMCS\Session::set("CaptchaComplete", false);
                        }
                        $templatefile = "domaintransfer";
                    } else {
                        $templatefile = "adddomain";
                    }
                } else {
                    if ($orderFormTemplate->hasTemplate("domainregister")) {
                        $showSuggestions = true;
                        if ($check->getLookupProvider() instanceof WHMCS\Domains\DomainLookup\Provider\BasicWhois && !WHMCS\Config\Setting::getValue("BulkCheckTLDs") || $check->getLookupProvider() instanceof WHMCS\Domains\DomainLookup\Provider\WhmcsWhois && !WHMCS\Config\Setting::getValue("domainLookup_WhmcsWhois_suggestTlds")) {
                            $showSuggestions = false;
                        }
                        $smarty->assign("showSuggestionsContainer", $showSuggestions);
                        $smarty->assign("captcha", $captcha);
                        $smarty->assign("captchaForm", WHMCS\Utility\Captcha::FORM_DOMAIN_CHECKER);
                        $smarty->assign("captchaError", NULL);
                        $captchaData = WHMCS\Session::getAndDelete("captchaData");
                        if ($captchaData) {
                            if (!$captchaData["invalidCaptchaError"]) {
                                $captcha->setEnabled(false);
                                $smarty->assign("captcha", $captcha);
                            } else {
                                $smarty->assign("captchaError", $captchaData["invalidCaptchaError"]);
                            }
                        } else {
                            WHMCS\Session::set("CaptchaComplete", false);
                        }
                        $templatefile = "domainregister";
                    } else {
                        $templatefile = "adddomain";
                    }
                }
                $registerTlds = getTLDList();
                $transferTlds = getTLDList("transfer");
                $smarty->assign("registertlds", $registerTlds);
                $smarty->assign("transfertlds", $transferTlds);
                $tldslist = $domain == "register" ? $registerTlds : $transferTlds;
                $smarty->assign("tlds", $tldslist);
                $smarty->assign("spotlightTlds", getSpotlightTldsWithPricing());
                $smartyvalues["domain"] = $domain;
                $sld = App::getFromRequest("sld");
                $tld = App::getFromRequest("tld");
                if ($domain == "transfer" && App::getFromRequest("sld_transfer")) {
                    $sld = App::getFromRequest("sld_transfer");
                }
                if ($domain == "transfer" && App::getFromRequest("tld_transfer")) {
                    $tld = App::getFromRequest("tld_transfer");
                }
                $lookupTerm = App::getFromRequest("query");
                if (!$lookupTerm && $sld) {
                    if ($tld && ltrim($tld, ".") == $tld) {
                        $tld = "." . $tld;
                    }
                    $lookupTerm = $sld . $tld;
                }
                if ($lookupTerm) {
                    try {
                        $passedDomain = new WHMCS\Domains\Domain($lookupTerm);
                        $sld = $passedDomain->getSecondLevel();
                        $tld = $passedDomain->getDotTopLevel();
                    } catch (Exception $e) {
                        $smartyvalues["invalid"] = true;
                    }
                }
                $smartyvalues["lookupTerm"] = $lookupTerm;
                $smartyvalues["sld"] = $sld;
                $smartyvalues["tld"] = $tld;
                if ($sld && $tld && !$errormessage && $templatefile == "adddomain") {
                    $check->cartDomainCheck(new WHMCS\Domains\Domain($sld), [$tld]);
                    $check->populateCartWithDomainSmartyVariables($domain, $smartyvalues);
                }
            } else {
                if (isset($renewals) && $renewals) {
                    if ($renewalid) {
                        $_SESSION["cart"]["renewals"][$renewalid] = $renewalperiod;
                    } else {
                        if (!count($renewalids)) {
                            redir("gid=renewals");
                        } else {
                            foreach ($renewalids as $domainid) {
                                $_SESSION["cart"]["renewals"][$domainid] = $renewalperiod[$domainid];
                            }
                        }
                    }
                    if ($ajax) {
                        exit;
                    }
                    redir("a=view");
                } else {
                    if ($bid) {
                        $data = get_query_vals("tblbundles", "", ["id" => $bid]);
                        $bid = $data["id"];
                        $validfrom = $data["validfrom"];
                        $validuntil = $data["validuntil"];
                        $uses = $data["uses"];
                        $maxuses = $data["maxuses"];
                        $itemdata = $data["itemdata"];
                        $itemdata = safe_unserialize($itemdata);
                        $vals = $itemdata[0];
                        if ($validfrom != "0000-00-00" && date("Ymd") < str_replace("-", "", $validfrom) || $validuntil != "0000-00-00" && str_replace("-", "", $validuntil) < date("Ymd")) {
                            $templatefile = "error";
                            $smartyvalues["errortitle"] = $_LANG["bundlevaliddateserror"];
                            $smartyvalues["errormsg"] = $_LANG["bundlevaliddateserrordesc"];
                            outputClientArea($templatefile);
                            exit;
                        }
                        if ($maxuses && $maxuses <= $uses) {
                            $templatefile = "error";
                            $smartyvalues["errortitle"] = $_LANG["bundlemaxusesreached"];
                            $smartyvalues["errormsg"] = $_LANG["bundlemaxusesreacheddesc"];
                            outputClientArea($templatefile);
                            exit;
                        }
                        $_SESSION["cart"]["bundle"][] = ["bid" => $bid, "step" => "0", "complete" => "0"];
                        $totalnum = count($_SESSION["cart"]["bundle"]);
                        $vals["bnum"] = $totalnum - 1;
                        $vals["bitem"] = "0";
                        $vals["billingcycle"] = str_replace(["-", " "], "", strtolower($vals["billingcycle"]));
                        $_SESSION["cart"]["passedvariables"] = $vals;
                        switch ($vals["type"]) {
                            case "product":
                                $extraVars = "pid=" . $vals["pid"];
                                break;
                            case "domain":
                                $extraVars = "domain=register";
                                break;
                            default:
                                redir();
                                redir("a=add&" . $extraVars);
                        }
                    } else {
                        redir();
                    }
                }
            }
        }
    }
}
$smartyvalues["invalid"] = NULL;
if ($a == "domainoptions") {
    $productinfo = $orderfrm->setPid($_SESSION["cart"]["domainoptionspid"]);
    $orderFormTemplateName = $productinfo["orderfrmtpl"] == "" ? $orderFormTemplateName : $productinfo["orderfrmtpl"];
    $checktype = App::getFromRequest("checktype");
    $domain = App::getFromRequest("domain");
    if ($checktype == "register" || $checktype == "transfer") {
        if ($domain) {
            $domainparts = explode(".", $domain, 2);
            list($sld, $tld) = $domainparts;
        }
        $sld = cleanDomainInput($sld);
        $tld = cleanDomainInput($tld);
        if ($tld && substr($tld, 0, 1) != ".") {
            $tld = "." . $tld;
        }
        $domainToLookup = new WHMCS\Domains\Domain($sld . $tld);
        if ($sld != "www" && $sld && $tld && WHMCS\Domains\Domain::isValidDomainName($sld, $tld)) {
            $domaincheck = false;
            $smartyvalues["alreadyindb"] = false;
            if ($CONFIG["AllowDomainsTwice"]) {
                $domainObject = new WHMCS\Domains\Domain($sld . $tld);
                $domaincheck = cartCheckIfDomainAlreadyOrdered($domainObject);
            }
            if ($domaincheck) {
                $smartyvalues["alreadyindb"] = true;
            } else {
                $regenabled = $CONFIG["AllowRegister"];
                $transferenabled = $CONFIG["AllowTransfer"];
                $owndomainenabled = $CONFIG["AllowOwnDomain"];
                $check = new WHMCS\Domain\Checker();
                $check->cartDomainCheck($domainToLookup, [$tld]);
                $searchResult = $check->getSearchResult()->offsetGet(0);
                $smartyvalues["searchResults"] = $searchResult->toArray();
                $smartyvalues["status"] = $searchResult->getLegacyStatus();
                $pricing = $searchResult->pricing()->toArray();
                if ($regenabled) {
                    $smartyvalues["regoptionscount"] = count($pricing);
                    $smartyvalues["regoptions"] = $pricing;
                }
                if ($transferenabled) {
                    $smartyvalues["transferoptionscount"] = count($pricing);
                    $smartyvalues["transferoptions"] = $pricing;
                    $transferPrice = current($pricing);
                    $smartyvalues["transferterm"] = key($pricing);
                    $smartyvalues["transferprice"] = $transferPrice["transfer"];
                }
                if (!$checktype) {
                    if ($searchResult->getStatus() == WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED) {
                        $checktype = "transfer";
                    } else {
                        $checktype = "register";
                    }
                }
                $smartyvalues["domain"] = $domainToLookup->getDomain();
                $smartyvalues["checktype"] = $checktype;
                $smartyvalues["regenabled"] = $regenabled;
                $smartyvalues["transferenabled"] = $transferenabled;
                $smartyvalues["owndomainenabled"] = $owndomainenabled;
                $smartyvalues["searchResults"]["suggestions"] = [];
                if ($checktype == "register" && $regenabled) {
                    $check->populateSuggestionsInSmartyValues($smartyvalues);
                }
            }
        } else {
            $smartyvalues["invalid"] = true;
        }
    } else {
        if ($checktype == "owndomain") {
            $tld = strtolower($tld);
            if ($sld && $tld && WHMCS\Domains\Domain::isValidDomainName($sld, $tld) && WHMCS\Domains\Domain::isSupportedTld($tld)) {
                if (substr($tld, 0, 1) != ".") {
                    $tld = "." . $tld;
                }
                if ($CONFIG["AllowDomainsTwice"]) {
                    $smartyvalues["alreadyindb"] = false;
                    $result = select_query("tblhosting", "domain", "domain='" . db_escape_string($sld . $tld) . "' AND (domainstatus!='Terminated' AND domainstatus!='Cancelled' AND domainstatus!='Fraud')");
                    while ($data = mysql_fetch_array($result)) {
                        if ($data[0] == $sld . $tld) {
                            $smartyvalues["alreadyindb"] = true;
                        }
                    }
                }
                $smartyvalues["checktype"] = $checktype;
                $smartyvalues["sld"] = $sld;
                $smartyvalues["tld"] = $tld;
            } else {
                $smartyvalues["invalid"] = true;
            }
        } else {
            if ($checktype == "subdomain") {
                if (!is_array($BannedSubdomainPrefixes)) {
                    $BannedSubdomainPrefixes = [];
                }
                if ($whmcs->get_config("BannedSubdomainPrefixes")) {
                    $bannedprefixes = $whmcs->get_config("BannedSubdomainPrefixes");
                    $bannedprefixes = explode(",", $bannedprefixes);
                    $BannedSubdomainPrefixes = array_merge($BannedSubdomainPrefixes, $bannedprefixes);
                }
                if (!WHMCS\Domains\Domain::isValidDomainName($sld, ".com")) {
                    $smartyvalues["invalid"] = true;
                } else {
                    if (in_array($sld, $BannedSubdomainPrefixes)) {
                        $smartyvalues["invalid"] = true;
                        $smartyvalues["reason"] = $_LANG["ordererrorsbudomainbanned"];
                    } else {
                        $result = select_query("tblhosting", "COUNT(*)", "domain='" . db_escape_string($sld . $tld) . "' AND (domainstatus!='Terminated' AND domainstatus!='Cancelled' AND domainstatus!='Fraud')");
                        $data = mysql_fetch_array($result);
                        $subchecks = $data[0];
                        if ($subchecks) {
                            $smartyvalues["invalid"] = true;
                            $smartyvalues["reason"] = $_LANG["ordererrorsubdomaintaken"];
                        } else {
                            $smartyvalues["checktype"] = $checktype;
                            $smartyvalues["sld"] = $sld;
                            $smartyvalues["tld"] = $tld;
                        }
                    }
                }
            } else {
                if ($checktype == "incart") {
                    $smartyvalues["checktype"] = "owndomain";
                    $domainparts = explode(".", $sld, 2);
                    list($sld, $tld) = $domainparts;
                    $smartyvalues["sld"] = $sld;
                    $smartyvalues["tld"] = $tld;
                }
            }
        }
    }
    $validate = new WHMCS\Validate();
    if ($checktype == "subdomain") {
        run_validate_hook($validate, "CartSubdomainValidation", ["subdomain" => $sld, "domain" => $tld]);
    } else {
        run_validate_hook($validate, "ShoppingCartValidateDomain", ["domainoption" => $checktype, "sld" => $sld, "tld" => $tld]);
    }
    if ($validate->hasErrors()) {
        $domainError = $validate->getHTMLErrorOutput();
        $smartyvalues["invalid"] = true;
        $smartyvalues["reason"] = $domainError;
    }
    $templatefile = "domainoptions";
}
if ($a == "cyclechange") {
    if (!is_int($productInfoKey) || !$billingcycle) {
        if ($ajax) {
            throw new WHMCS\Exception\ProgramExit($_LANG["invoiceserror"]);
        }
        redir();
    }
    if ($orderfrm->validateBillingCycle($billingcycle)) {
        $_SESSION["cart"]["products"][$productInfoKey]["billingcycle"] = $billingcycle;
    }
    $a = "confproduct";
}
if ($a == "confproduct") {
    $templatefile = "configureproduct";
    if (is_null($productInfoKey) || !isset($_SESSION["cart"]["products"][$productInfoKey]) || !is_array($_SESSION["cart"]["products"][$productInfoKey])) {
        if ($ajax) {
            exit($_LANG["invoiceserror"]);
        }
        redir();
    }
    if (isset($_SESSION["cart"]["products"][$productInfoKey]["skipConfig"]) && $_SESSION["cart"]["products"][$productInfoKey]["skipConfig"]) {
        $_SESSION["cart"]["products"][$productInfoKey]["skipConfig"] = false;
        redir("a=view");
    }
    $newproduct = isset($_SESSION["cart"]["newproduct"]) ? $_SESSION["cart"]["newproduct"] : "";
    unset($_SESSION["cart"]["newproduct"]);
    $pid = $_SESSION["cart"]["products"][$productInfoKey]["pid"];
    $productinfo = $orderfrm->setPid($pid);
    if (!$productinfo) {
        redir();
    }
    $orderFormTemplateName = $productinfo["orderfrmtpl"] == "" ? $orderFormTemplateName : $productinfo["orderfrmtpl"];
    $_SESSION["cart"]["cartsummarypid"] = $productinfo["pid"];
    $pid = $productinfo["pid"];
    $configure = $whmcs->get_req_var("configure");
    $validate = new WHMCS\Validate();
    if ($configure) {
        global $errormessage;
        $errormessage = "";
        $serverarray = [];
        if ($productinfo["type"] == "server") {
            $hostname = App::getFromRequest("hostname");
            $ns1prefix = App::getFromRequest("ns1prefix");
            $ns2prefix = App::getFromRequest("ns2prefix");
            $rootpw = App::getFromRequest("rootpw");
            $validate->validate("required", "hostname", "ordererrorservernohostname");
            if ($validate->validated("hostname")) {
                $validate->validate("unique_service_domain", "hostname", "ordererrorserverhostnameinuse");
                if ($validate->validated("hostname")) {
                    $validate->validate("hostname", "hostname", "orderErrorServerHostnameInvalid");
                    if ($validate->validated("hostname")) {
                        $validate->reverseValidate("numeric", "hostname", "orderErrorServerHostnameInvalid");
                    }
                }
            }
            $validate->validate("required", "ns1prefix", "ordererrorservernonameservers");
            if ($validate->validated("ns1prefix")) {
                $validate->validate("alphanumeric", "ns1prefix", "orderErrorServerNameserversInvalid");
                if ($validate->validated("ns1prefix")) {
                    $validate->reverseValidate("numeric", "ns1prefix", "orderErrorServerNameserversInvalid");
                }
            }
            $validate->validate("required", "ns2prefix", "ordererrorservernonameservers");
            if ($validate->validated("ns2prefix")) {
                $validate->validate("alphanumeric", "ns2prefix", "orderErrorServerNameserversInvalid");
                if ($validate->validated("ns2prefix")) {
                    $validate->reverseValidate("numeric", "ns2prefix", "orderErrorServerNameserversInvalid");
                }
            }
            $validate->validate("required", "rootpw", "ordererrorservernorootpw");
            $serverarray = ["hostname" => $hostname, "ns1prefix" => $ns1prefix, "ns2prefix" => $ns2prefix, "rootpw" => $rootpw];
        }
        $configoptionsarray = [];
        $configoption = $whmcs->get_req_var("configoption");
        if ($configoption) {
            $configOpsReturn = validateAndSanitizeQuantityConfigOptions($configoption);
            $configoptionsarray = $configOpsReturn["validOptions"];
            $errormessage .= $configOpsReturn["errorMessage"];
        }
        $addons = $whmcs->get_req_var("addons");
        $addonsarray = [];
        if (is_array($addons)) {
            foreach (array_keys($addons) as $addonId) {
                $addonsarray[] = ["addonid" => $addonId, "qty" => 1];
            }
        }
        foreach (App::getFromRequest("addons_radio") ?: [] as $addonId) {
            if (is_numeric($addonId)) {
                $addonsarray[] = ["addonid" => $addonId, "qty" => 1];
            }
        }
        $customFieldData = isset($_SESSION["cart"]["products"][$productInfoKey]["customfields"]) ? (array) $_SESSION["cart"]["products"][$productInfoKey]["customfields"] : [];
        $newCustomFieldData = (array) App::getFromRequest("customfield");
        foreach ($newCustomFieldData as $key => $value) {
            $customFieldData[$key] = $value;
        }
        $errormessage .= bundlesValidateProductConfig($productInfoKey, $billingcycle ?? NULL, $configoptionsarray, $addonsarray);
        $_SESSION["cart"]["products"][$productInfoKey]["billingcycle"] = $billingcycle ?? NULL;
        $_SESSION["cart"]["products"][$productInfoKey]["server"] = $serverarray;
        $_SESSION["cart"]["products"][$productInfoKey]["configoptions"] = $configoptionsarray;
        $_SESSION["cart"]["products"][$productInfoKey]["customfields"] = $customFieldData;
        $_SESSION["cart"]["products"][$productInfoKey]["addons"] = $addonsarray;
        if ($whmcs->get_req_var("calctotal")) {
            $productinfo = $orderfrm->setPid($_SESSION["cart"]["products"][$productInfoKey]["pid"]);
            $orderFormTemplateName = $productinfo["orderfrmtpl"] == "" ? $orderFormTemplateName : $productinfo["orderfrmtpl"];
            try {
                $orderSummaryTemplate = "/templates/orderforms/" . WHMCS\View\Template\OrderForm::factory("ordersummary.tpl", $orderFormTemplateName)->getName() . "/ordersummary.tpl";
                $cartTotals = calcCartTotals(Auth::client(), false, true);
                $templateVariables = ["producttotals" => $cartTotals["products"][$productInfoKey], "carttotals" => $cartTotals];
                header("HTTP/1.1 200 OK");
                header("Status: 200 OK");
                echo processSingleTemplate($orderSummaryTemplate, $templateVariables);
            } catch (Exception $e) {
            }
            exit;
        }
        if (!$ajax && !$addProductAjax && !$whmcs->get_req_var("nocyclerefresh") && $previousbillingcycle != $billingcycle) {
            redir("a=confproduct&i=" . $productInfoKey);
        }
        $validate->validateCustomFields("product", $pid, true);
        run_validate_hook($validate, "ShoppingCartValidateProductUpdate", $_REQUEST);
        if ($validate->hasErrors()) {
            $errormessage .= $validate->getHTMLErrorOutput();
        }
        if ($errormessage) {
            if ($ajax) {
                exit($errormessage);
            }
            $smartyvalues["errormessage"] = $errormessage;
        } else {
            unset($_SESSION["cart"]["products"][$productInfoKey]["noconfig"]);
            $_SESSION["cart"]["lastconfigured"] = ["type" => "product", "i" => $productInfoKey];
            if ($ajax) {
                header("HTTP/1.1 200 OK");
                header("Status: 200 OK");
                exit;
            }
            if ($addProductAjax) {
                redir("a=confdomains&addproductajax=1&i=" . $productInfoKey);
            }
            redir("a=confdomains");
        }
    }
    $billingcycle = $_SESSION["cart"]["products"][$productInfoKey]["billingcycle"] ?? NULL;
    $server = $_SESSION["cart"]["products"][$productInfoKey]["server"] ?? NULL;
    $customfields = $_SESSION["cart"]["products"][$productInfoKey]["customfields"] ?? NULL;
    $configoptions = $_SESSION["cart"]["products"][$productInfoKey]["configoptions"] ?? NULL;
    $addons = $_SESSION["cart"]["products"][$productInfoKey]["addons"] ?? NULL;
    if (!$addons) {
        $addons = [];
    }
    $domain = $_SESSION["cart"]["products"][$productInfoKey]["domain"] ?? NULL;
    $noconfig = $_SESSION["cart"]["products"][$productInfoKey]["noconfig"] ?? NULL;
    $billingcycle = $orderfrm->validateBillingCycle($billingcycle);
    $pricing = getPricingInfo($pid);
    $configurableoptions = getCartConfigOptions($pid, $configoptions, $billingcycle, "", true);
    $customfields = getCustomFields("product", $pid, "", "", "on", $customfields);
    $addonsarray = getAddons($pid, $addons);
    $marketConnect = new WHMCS\MarketConnect\MarketConnect();
    $addonsPromoOutput = $marketConnect->getMarketplaceConfigureProductAddonPromoHtml($addonsarray, $billingcycle);
    $addonsarray = $marketConnect->removeMarketplaceAddons($addonsarray);
    $hookResponses = run_hook("ShoppingCartConfigureProductAddonsOutput", ["billingCycle" => $billingcycle, "selectedAddons" => $addonsarray]);
    foreach ($hookResponses as $response) {
        if ($response) {
            $addonsPromoOutput[] = $response;
        }
    }
    $smartyvalues["addonsPromoOutput"] = $addonsPromoOutput;
    $recurringcycles = 0;
    if ($pricing["type"] == "recurring") {
        if (0 <= $pricing["rawpricing"]["monthly"]) {
            $recurringcycles++;
        }
        if (0 <= $pricing["rawpricing"]["quarterly"]) {
            $recurringcycles++;
        }
        if (0 <= $pricing["rawpricing"]["semiannually"]) {
            $recurringcycles++;
        }
        if (0 <= $pricing["rawpricing"]["annually"]) {
            $recurringcycles++;
        }
        if (0 <= $pricing["rawpricing"]["biennially"]) {
            $recurringcycles++;
        }
    }
    $billedMetrics = $productinfo["metrics"];
    $metricsNeedDisplay = false;
    if (WHMCS\UsageBilling\MetricUsageSettings::isCollectionEnable() && WHMCS\UsageBilling\MetricUsageSettings::isInvoicingEnabled() && $billedMetrics->count()) {
        foreach ($billedMetrics as $metric) {
            if (!$metric->pricingSchema->isFree()) {
                $metricsNeedDisplay = true;
            }
        }
    }
    if ($newproduct && $productinfo["type"] != "server" && ($pricing["type"] != "recurring" || $recurringcycles <= 1) && count($configurableoptions) < 1 && count($customfields) < 1 && count($addonsarray) < 1 && !$addonsPromoOutput && !$metricsNeedDisplay) {
        unset($_SESSION["cart"]["products"][$productInfoKey]["noconfig"]);
        $_SESSION["cart"]["lastconfigured"] = ["type" => "product", "i" => $productInfoKey];
        if ($ajax) {
            exit;
        }
        if ($addProductAjax) {
            redir("a=confdomains&addproductajax=1&i=" . $productInfoKey);
        }
        redir("a=confdomains");
    }
    $serverarray = ["hostname" => isset($server["hostname"]) ? $server["hostname"] : "", "ns1prefix" => isset($server["ns1prefix"]) ? $server["ns1prefix"] : "", "ns2prefix" => isset($server["ns2prefix"]) ? $server["ns2prefix"] : "", "rootpw" => isset($server["rootpw"]) ? $server["rootpw"] : ""];
    $requiredPricingVars = ["quarterly", "semiannually", "annually", "biennially", "triennially"];
    foreach ($requiredPricingVars as $requiredPricingVar) {
        $pricing[$requiredPricingVar] = $pricing[$requiredPricingVar] ?? NULL;
    }
    unset($requiredPricingVars);
    $smartyvalues["editconfig"] = true;
    $smartyvalues["firstconfig"] = $noconfig ? true : false;
    $smartyvalues["i"] = $productInfoKey;
    $smartyvalues["productinfo"] = $productinfo;
    $smartyvalues["pricing"] = $pricing;
    $smartyvalues["billingcycle"] = $billingcycle;
    $smartyvalues["server"] = $serverarray;
    $smartyvalues["configurableoptions"] = $configurableoptions;
    $smartyvalues["addons"] = $addonsarray;
    $smartyvalues["customfields"] = $customfields;
    $smartyvalues["domain"] = $domain;
    $metrics = [];
    if ($metricsNeedDisplay) {
        foreach ($billedMetrics as $usageItem) {
            $pricingSchema = $usageItem->pricingSchema;
            if (!$pricingSchema->isFree()) {
                $moduleMetric = $usageItem->getModuleMetric();
                $units = $moduleMetric->units();
                $freeLimit = $usageItem->included;
                if (valueIsZero($freeLimit)) {
                    $freeLimit = NULL;
                }
                if (!$freeLimit) {
                    $freeLimit = $pricingSchema->freeLimit();
                    if (valueIsZero($freeLimit)) {
                        $freeLimit = NULL;
                    }
                }
                if ($freeLimit) {
                    $freeLimit = $units->formatForType($freeLimit);
                }
                $pricing = [];
                foreach ($pricingSchema as $bracket) {
                    $floor = 0;
                    if (!valueIsZero($bracket->floor)) {
                        $floor = $bracket->floor;
                    }
                    if ($freeLimit) {
                        $floor = $floor + $freeLimit;
                    }
                    $floor = $units->formatForType($floor);
                    $currencyPrice = $bracket->pricingForCurrencyId($currency["id"]);
                    $pricing[] = ["from" => $floor, "price_per_unit" => formatCurrency($currencyPrice->monthly)];
                    $baseLangKey = "metrics.pricingschema." . $bracket->schemaType();
                }
                if (!$pricing) {
                    $baseLangKey = "metrics.pricingschema." . WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_SIMPLE;
                    $lowestPrice = "";
                } else {
                    $lowestPrice = $pricing[0]["price_per_unit"];
                }
                $metrics[] = ["type" => $moduleMetric->type(), "systemName" => $moduleMetric->systemName(), "displayName" => $moduleMetric->displayName(), "unitName" => $units->perUnitName(1), "includedQuantity" => $freeLimit, "includedQuantityUnits" => $units->perUnitName($freeLimit), "lowestPrice" => $lowestPrice, "pricingSchema" => ["info" => Lang::trans($baseLangKey . ".info"), "detail" => Lang::trans($baseLangKey . ".detail")], "pricing" => $pricing];
            }
        }
    }
    $smartyvalues["metrics"] = $metrics;
}
if ($a == "confdomains") {
    $templatefile = "configuredomains";
    $skipstep = true;
    $_SESSION["cartdomain"] = [];
    $update = $whmcs->get_req_var("update");
    $validate = $whmcs->get_req_var("validate");
    if ($update || $validate) {
        $validateHookParams = $_REQUEST;
        $domains = $_SESSION["cart"]["domains"];
        foreach ($domains as $key => $domainname) {
            if ($validate) {
                $domainfield[$key] = $_SESSION["cart"]["domains"][$key]["fields"];
            } else {
                $_SESSION["cart"]["domains"][$key]["dnsmanagement"] = $_POST["dnsmanagement"][$key] ?? NULL;
                $_SESSION["cart"]["domains"][$key]["emailforwarding"] = $_POST["emailforwarding"][$key] ?? NULL;
                $_SESSION["cart"]["domains"][$key]["idprotection"] = $_POST["idprotection"][$key] ?? NULL;
                $_SESSION["cart"]["domains"][$key]["eppcode"] = $_POST["epp"][$key] ?? NULL;
            }
            $domainparts = explode(".", $domainname["domain"], 2);
            $additflds = new WHMCS\Domains\AdditionalFields();
            $additflds->setTLD($domainparts[1]);
            $additflds->setFieldValues($domainfield[$key] ?? NULL);
            $missingfields = $additflds->getMissingRequiredFields();
            foreach ($missingfields as $missingfield) {
                $errormessage .= "<li>" . $missingfield . " " . $_LANG["clientareaerrorisrequired"] . " (" . $domainname["domain"] . ")";
            }
            $_SESSION["cart"]["domains"][$key]["fields"] = $domainfield[$key] ?? NULL;
            $validateHookParams["domainfield"][$key] = $additflds->getAsNameValueArray();
            if ($domainname["type"] !== "register") {
                $result = select_query("tbldomainpricing", "", ["extension" => "." . $domainparts[1]]);
                $data = mysql_fetch_array($result);
                if ($data["eppcode"] && !$_POST["epp"][$key]) {
                    $errormessage .= "<li>" . $_LANG["domaineppcoderequired"] . " " . $domainname["domain"];
                }
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            $ns = $whmcs->get_req_var("domainns" . $i);
            if (preg_match($nameserverRegexPattern, $ns)) {
                $_SESSION["cart"]["ns" . $i] = $ns;
            }
            if ($ns == "" && isset($_SESSION["cart"]["ns" . $i])) {
                unset($_SESSION["cart"]["ns" . $i]);
            }
        }
        $validate = new WHMCS\Validate();
        run_validate_hook($validate, "ShoppingCartValidateDomainsConfig", $validateHookParams);
        if ($validate->hasErrors()) {
            $errormessage .= $validate->getHTMLErrorOutput();
        }
        if ($ajax) {
            exit($errormessage);
        }
        if ($errormessage) {
            $smartyvalues["errormessage"] = $errormessage;
        } else {
            redir("a=view");
        }
    }
    $atleastonenohosting = App::getFromRequest("atleastonenohosting");
    $domainsarray = [];
    $domains = $_SESSION["cart"]["domains"] ?? [];
    if ($domains) {
        foreach ($domains as $key => $domainname) {
            $regperiod = $domainname["regperiod"];
            $domainparts = explode(".", $domainname["domain"], 2);
            list($sld, $tld) = $domainparts;
            $result = select_query("tbldomainpricing", "", ["extension" => "." . $tld]);
            $data = mysql_fetch_array($result);
            $domainconfigsshowing = $eppenabled = false;
            if ($data["dnsmanagement"]) {
                $domainconfigsshowing = true;
            }
            if ($data["emailforwarding"]) {
                $domainconfigsshowing = true;
            }
            if ($data["idprotection"]) {
                $domainconfigsshowing = true;
            }
            $result = select_query("tblpricing", "", ["type" => "domainaddons", "currency" => $currency["id"], "relid" => 0]);
            $data2 = mysql_fetch_array($result);
            $domaindnsmanagementprice = $data2["msetupfee"] * $regperiod;
            $domainemailforwardingprice = $data2["qsetupfee"] * $regperiod;
            $domainidprotectionprice = $data2["ssetupfee"] * $regperiod;
            $domaindnsmanagementprice = $domaindnsmanagementprice == "0.00" ? $_LANG["orderfree"] : new WHMCS\View\Formatter\Price($domaindnsmanagementprice, $currency);
            $domainemailforwardingprice = $domainemailforwardingprice == "0.00" ? $_LANG["orderfree"] : new WHMCS\View\Formatter\Price($domainemailforwardingprice, $currency);
            $domainidprotectionprice = $domainidprotectionprice == "0.00" ? $_LANG["orderfree"] : new WHMCS\View\Formatter\Price($domainidprotectionprice, $currency);
            if ($data["eppcode"] && $domainname["type"] == "transfer") {
                $eppenabled = true;
                $domainconfigsshowing = true;
            }
            $additflds = new WHMCS\Domains\AdditionalFields();
            $additflds->setTLD($tld);
            $fieldValues = isset($domainname["fields"]) ? $domainname["fields"] : [];
            $additflds->setFieldValues($fieldValues);
            $domainfields = $additflds->getFieldsForOutput($key);
            if (count($domainfields)) {
                $domainconfigsshowing = true;
            }
            $products = $_SESSION["cart"]["products"] ?? NULL;
            $hashosting = false;
            if ($products) {
                $domainProductDomain = (new WHMCS\Domains\Domain($domainname["domain"], NULL, false))->toPunycode();
                foreach ($products as $product) {
                    if (isset($product["domain"])) {
                        $otherProductDomain = (new WHMCS\Domains\Domain($product["domain"], NULL, false))->toPunycode();
                        if ($otherProductDomain == $domainProductDomain) {
                            $hashosting = true;
                        }
                    }
                }
                unset($domainProductDomain);
                unset($otherProductDomain);
            }
            if (!$hashosting) {
                $atleastonenohosting = true;
            }
            if ($atleastonenohosting) {
                $skipstep = false;
            }
            $domainAddonsCount = 0;
            if ($data["dnsmanagement"]) {
                $domainAddonsCount++;
            }
            if ($data["emailforwarding"]) {
                $domainAddonsCount++;
            }
            if ($data["idprotection"]) {
                $domainAddonsCount++;
            }
            $domainsarray[$key] = ["domain" => $domainname["domain"], "regperiod" => $domainname["regperiod"], "dnsmanagement" => $data["dnsmanagement"], "emailforwarding" => $data["emailforwarding"], "idprotection" => $data["idprotection"], "addonsCount" => $domainAddonsCount, "dnsmanagementprice" => $domaindnsmanagementprice, "emailforwardingprice" => $domainemailforwardingprice, "idprotectionprice" => $domainidprotectionprice, "dnsmanagementselected" => isset($domainname["dnsmanagement"]) ? $domainname["dnsmanagement"] : false, "emailforwardingselected" => isset($domainname["emailforwarding"]) ? $domainname["emailforwarding"] : false, "idprotectionselected" => isset($domainname["idprotection"]) ? $domainname["idprotection"] : false, "eppenabled" => $eppenabled, "eppvalue" => isset($domainname["eppcode"]) ? $domainname["eppcode"] : "", "fields" => $domainfields, "configtoshow" => $domainconfigsshowing, "hosting" => $hashosting];
            if ($domainconfigsshowing || $eppenabled || $domainfields || $data["dnsmanagement"] || $data["emailforwarding"] || $data["idprotection"]) {
                $skipstep = false;
            }
        }
    }
    $smartyvalues["domains"] = $domainsarray;
    $smartyvalues["atleastonenohosting"] = $atleastonenohosting;
    if (!$skipstep && empty($_SESSION["cart"]["ns1"]) && empty($_SESSION["cart"]["ns2"])) {
        for ($i = 1; $i <= 5; $i++) {
            $_SESSION["cart"]["ns" . $i] = isset($CONFIG["DefaultNameserver" . $i]) ? $CONFIG["DefaultNameserver" . $i] : NULL;
        }
    }
    for ($i = 1; $i <= 5; $i++) {
        $ns = isset($_SESSION["cart"]["ns" . $i]) ? $_SESSION["cart"]["ns" . $i] : "";
        $smartyvalues["domainns" . $i] = $ns;
    }
    if ($addProductAjax) {
        $lastProduct = WHMCS\Product\Product::findorFail($_SESSION["cart"]["products"][(int) App::getFromRequest("i")]["pid"]);
        if (is_null(end($_SESSION["cart"]["products"])["billingcycle"]) || $lastProduct->pricing()->first()->isFree()) {
            $lastProductPricing = $lastProduct->pricing()->first();
        } else {
            $lastProductPricing = $lastProduct->pricing()->byCycle(end($_SESSION["cart"]["products"])["billingcycle"]);
        }
        $productRecommendationsData = (new WHMCS\OrderForm())->getRecommendationsData([$lastProduct->id], collect($_SESSION["cart"]["products"])->pluck("pid")->toArray(), true);
        if (empty($productRecommendationsData["own"]) && empty($productRecommendationsData["order"])) {
            WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["href" => $whmcs->getRedirectUrl("", $skipstep ? "a=view" : "a=confdomains"), "success" => false]);
        }
        $smarty->assign("lastProduct", ["product" => $lastProduct, "pricing" => $lastProductPricing]);
        $smarty->assign("productRecommendations", $productRecommendationsData);
        $smarty->assign("recommendStyle", NULL);
        $smarty->assign("templatefile", NULL);
        $smartyOutput = $smarty->fetch(ROOTDIR . "/templates/orderforms/" . WHMCS\View\Template\OrderForm::factory("includes/product-recommendations.tpl", $orderFormTemplateName)->getName() . "/" . "includes/product-recommendations.tpl");
        WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["href" => $whmcs->getRedirectUrl("", $skipstep ? "a=view" : "a=confdomains"), "count" => (new WHMCS\OrderForm())->getNumItemsInCart(), "html" => $smartyOutput, "success" => true]);
    }
    if ($skipstep) {
        if ($ajax) {
            exit;
        }
        redir("a=view");
    }
}
if ($addProductAjax) {
    WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => false]);
}
if ($a == "checkout") {
    $cartId = App::getFromRequest("cart_id");
    if ($cartId) {
        $cart = WHMCS\Cart\Models\Cart::byTag($cartId)->first();
        if ($cart) {
            $cart->exportToSession();
        } else {
            $cartApiError = "<li>" . Lang::trans("cartapifailedtoloadcart") . "</li>";
        }
        if (App::isInRequest("access_token")) {
            $oauth2Request = OAuth2\HttpFoundationBridge\Request::createFromGlobals();
            $oauth2Response = new OAuth2\HttpFoundationBridge\Response();
            $server = DI::make("oauth2_sso");
            $server->handleSingleSignOnRequest($oauth2Request, $oauth2Response);
        }
        if (empty($cartApiError)) {
            App::redirect("cart.php", "a=checkout");
        }
    }
    if (App::getFromRequest("e") === "false") {
        $orderfrm->cancelExpressCheckout();
    }
    $domainconfigerror = false;
    $domains = $orderfrm->getCartDataByKey("domains");
    if ($domains) {
        foreach ($domains as $key => $domaindata) {
            $domainparts = explode(".", $domaindata["domain"], 2);
            $additflds = new WHMCS\Domains\AdditionalFields();
            $additflds->setTLD($domainparts[1]);
            $additflds->setFieldValues($domaindata["fields"] ?? NULL);
            if ($additflds->isMissingRequiredFields()) {
                $domainconfigerror = true;
            }
            if ($domaindata["type"] !== "register") {
                $result = select_query("tbldomainpricing", "eppcode", ["extension" => "." . $domainparts[1]]);
                $data = mysql_fetch_array($result);
                if ($data["eppcode"] && !$domaindata["eppcode"]) {
                    $domainconfigerror = true;
                }
            }
        }
    }
    if ($domainconfigerror) {
        if ($ajax) {
            $errormessage .= "<li>" . $_LANG["carterrordomainconfigskipped"];
        } else {
            redir("a=confdomains&validate=1");
        }
    }
    $credit_card_input = "";
    foreach (getAvailableOrderPaymentGateways(true) as $moduleName => $moduleConfiguration) {
        $gateway = new WHMCS\Module\Gateway();
        if ($gateway->load($moduleName) && $gateway->functionExists("credit_card_input")) {
            $credit_card_input .= $gateway->call("credit_card_input", calcCartTotals(Auth::client(), false, false));
        }
    }
    $smartyvalues["existingCards"] = Illuminate\Support\Collection::make([]);
    $smartyvalues["credit_card_input"] = $credit_card_input;
    $remoteAuth = DI::make("remoteAuth");
    $remoteAuthData = $remoteAuth->getRegistrationFormData();
    $remoteAuthData = (new WHMCS\Authentication\Remote\Management\Client\ViewHelper())->getTemplateData(WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider::HTML_TARGET_CHECKOUT);
    foreach ($remoteAuthData as $key => $value) {
        $smartyvalues[$key] = $value;
    }
    if (!empty($remoteAuthData)) {
        $userData = $_SESSION["cart"]["user"] ?? [];
        if (empty($userData["email"]) && isset($remoteAuthData["email"])) {
            $userData["email"] = $remoteAuthData["email"];
        }
        if (empty($userData["firstname"]) && isset($remoteAuthData["firstname"])) {
            $userData["firstname"] = $remoteAuthData["firstname"];
        }
        if (empty($userData["lastname"]) && isset($remoteAuthData["lastname"])) {
            $userData["lastname"] = $remoteAuthData["lastname"];
        }
        $_SESSION["cart"]["user"] = $userData;
    }
    $allowcheckout = true;
    $a = "view";
}
if ($a == "addcontact") {
    $allowcheckout = true;
    $addcontact = true;
    $a = "view";
}
if ($a == "view") {
    $templatefile = "viewcart";
    $errormessage = "";
    if (!empty($cartApiError)) {
        $errormessage = $cartApiError;
    }
    $gateways = new WHMCS\Gateways();
    $availablegateways = getAvailableOrderPaymentGateways(true);
    $securityquestions = getSecurityQuestions();
    $submit = $whmcs->get_req_var("submit");
    $checkout = $whmcs->get_req_var("checkout");
    $validatelogin = $whmcs->get_req_var("validatelogin");
    $validatepromo = $whmcs->get_req_var("validatepromo");
    $ccinfo = $whmcs->get_req_var("ccinfo");
    $cctype = $whmcs->get_req_var("cctype");
    $ccDescription = App::getFromRequest("ccdescription");
    $ccnumber = $whmcs->get_req_var("ccnumber");
    $ccexpirymonth = $whmcs->get_req_var("ccexpirymonth");
    $ccexpiryyear = $whmcs->get_req_var("ccexpiryyear");
    $ccstartmonth = $whmcs->get_req_var("ccstartmonth");
    $ccstartyear = $whmcs->get_req_var("ccstartyear");
    $ccissuenum = $whmcs->get_req_var("ccissuenum");
    $cccvvexisting = $cccvv = $whmcs->get_req_var("cccvv");
    $nostore = $whmcs->get_req_var("nostore");
    $password = $whmcs->get_req_var("password");
    $password2 = $whmcs->get_req_var("password2");
    $customfields = $whmcs->get_req_var("customfields");
    $notes = $whmcs->get_req_var("notes");
    $contact = $whmcs->get_req_var("contact");
    $addcontact = $whmcs->get_req_var("addcontact");
    $domaincontactfirstname = $whmcs->get_req_var("domaincontactfirstname");
    $domaincontactlastname = $whmcs->get_req_var("domaincontactlastname");
    $domaincontactcompanyname = $whmcs->get_req_var("domaincontactcompanyname");
    $domaincontactemail = $whmcs->get_req_var("domaincontactemail");
    $domaincontactaddress1 = $whmcs->get_req_var("domaincontactaddress1");
    $domaincontactaddress2 = $whmcs->get_req_var("domaincontactaddress2");
    $domaincontactcity = $whmcs->get_req_var("domaincontactcity");
    $domaincontactstate = $whmcs->get_req_var("domaincontactstate");
    $domaincontactpostcode = $whmcs->get_req_var("domaincontactpostcode");
    $domaincontactcountry = $whmcs->get_req_var("domaincontactcountry");
    $domaincontactphonenumber = $whmcs->get_req_var("domaincontactphonenumber");
    $domaincontactphonenumber = App::formatPostedPhoneNumber("domaincontactphonenumber");
    $domaincontactcountry = $whmcs->get_req_var("domaincontactcountry");
    $domainContactTaxId = App::getFromRequest("domaincontacttax_id");
    $selectedAccountId = App::getFromRequest("account_id");
    $clientTaxId = App::getFromRequest("tax_id");
    $loginfailed = $whmcs->get_req_var("loginfailed");
    $insufficientstock = $whmcs->get_req_var("insufficientstock");
    $loadFromRequest = ["firstname" => NULL, "lastname" => NULL, "companyname" => NULL, "email" => NULL, "address1" => NULL, "address2" => NULL, "city" => NULL, "state" => NULL, "postcode" => NULL, "country" => NULL, "phonenumber" => function ($v) {
        return App::formatPostedPhoneNumber();
    }, "marketingoptin" => function ($v) {
        return empty($v) ? 0 : 1;
    }];
    foreach ($loadFromRequest as $field => $transform) {
        ${$field} = App::getFromRequest($field);
        if (is_callable($transform)) {
            ${$field} = $transform(${$field});
        }
    }
    unset($field);
    unset($transform);
    unset($loadFromRequest);
    if ($insufficientstock) {
        $errormessage .= "<li>" . $_LANG["insufficientstockmessage"] . "</li>";
    }
    if (!$selectedAccountId && Auth::client() && Auth::hasPermission("orders")) {
        $selectedAccountId = $clientId;
    }
    if ($selectedAccountId != "new") {
        if (Auth::user()) {
            try {
                if ($selectedAccountId) {
                    Auth::setClientId($selectedAccountId);
                    if (!Auth::hasPermission("orders")) {
                        throw new WHMCS\Exception\Authorization\AccessDenied();
                    }
                }
            } catch (Exception $e) {
                $errormessage .= "<li>" . Lang::trans("switchAccount.invalidChooseAnother") . "</li>";
                $selectedAccountId = NULL;
            }
            if (!$selectedAccountId) {
                $userClients = Auth::user()->clients()->where("status", "!=", WHMCS\User\Client::STATUS_CLOSED)->orderBy("owner", "desc")->orderBy("id", "asc")->get();
                foreach ($userClients as $userClient) {
                    if ($userClient->pivot->getPermissions()->hasPermission("orders")) {
                        $selectedAccountId = $userClient->id;
                        Auth::setClientId($selectedAccountId);
                        unset($userClients);
                        unset($userClient);
                    }
                }
            }
        } else {
            $selectedAccountId = NULL;
        }
    }
    if (is_numeric($selectedAccountId) && Auth::client()) {
        $client = Auth::client();
    }
    $ccExpiryDate = $whmcs->get_req_var("ccexpirydate");
    if ($ccExpiryDate) {
        $ccExpirySplit = explode("/", $ccExpiryDate);
        $ccexpirymonth = !empty($ccExpirySplit[0]) ? $ccExpirySplit[0] : "";
        $ccexpiryyear = !empty($ccExpirySplit[1]) ? $ccExpirySplit[1] : "";
    }
    $ccexpirymonth = trim($ccexpirymonth);
    $ccexpiryyear = trim($ccexpiryyear);
    if (2 < strlen($ccexpiryyear)) {
        $ccexpiryyear = substr($ccexpiryyear, -2);
    }
    $ccStartDate = $whmcs->get_req_var("ccstartdate");
    if ($ccStartDate) {
        $ccStartSplit = explode("/", $ccStartDate);
        $ccstartmonth = !empty($ccStartSplit[0]) ? $ccStartSplit[0] : "";
        $ccstartyear = !empty($ccStartSplit[1]) ? $ccStartSplit[1] : "";
    }
    $ccstartmonth = trim($ccstartmonth);
    $ccstartyear = trim($ccstartyear);
    if (2 < strlen($ccstartmonth)) {
        $ccstartmonth = substr($ccstartmonth, -2);
    }
    if (!$cccvv && $cccvvexisting) {
        $cccvv = $cccvvexisting;
    }
    $encryptedVarNames = ["cctype", "ccnumber", "ccexpirymonth", "ccexpiryyear", "ccstartmonth", "ccstartyear", "ccissuenum", "cccvv", "nostore"];
    foreach ($encryptedVarNames as $varName) {
        if (32 < strlen(${$varName})) {
            ${$varName} = substr(${$varName}, 0, 32);
        }
    }
    $remoteAuth = DI::make("remoteAuth");
    if ($remoteAuth->isPrelinkPerformed()) {
        $password = $remoteAuth->generateRandomPassword();
    }
    $paymentmethod = App::getFromRequest("paymentmethod");
    $custtype = App::getFromRequest("custtype");
    if (($submit || $checkout || $validatelogin) && !$validatepromo) {
        if ($orderfrm->getNumItemsInCart() <= 0) {
            redir("a=view");
        }
        $paymentmethod = App::getFromRequest("paymentmethod");
        if ($orderfrm->inExpressCheckout()) {
            $paymentmethod = $orderfrm->getExpressCheckoutGateway();
        }
        $_SESSION["cart"]["paymentmethod"] = $paymentmethod;
        $_SESSION["cart"]["notes"] = $notes;
        if (!$user && ($custtype == "existing" || $validatelogin)) {
            $loginemail = $whmcs->get_req_var("loginemail");
            $loginpw = WHMCS\Input\Sanitize::decode($whmcs->get_req_var("loginpw"));
            if (!$loginpw) {
                $loginpw = WHMCS\Input\Sanitize::decode($whmcs->get_req_var("loginpassword"));
            }
            try {
                Auth::authenticate($loginemail, $loginpw);
                $user = Auth::user();
                $client = Auth::client();
                if ($client) {
                    $clientId = $selectedAccountId = $client->id;
                    $custtype = "existing";
                }
                if ($validatelogin || Auth::hasMultipleClients()) {
                    Auth::requireLoginAndClient(true);
                }
                if (!Auth::hasPermission("orders")) {
                    App::redirect("cart.php", ["a" => "checkout"]);
                }
            } catch (WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
                WHMCS\Session::set("2fafromcart", true);
                App::redirectToRoutePath("login-two-factor-challenge");
            } catch (Exception $e) {
                if ($validatelogin) {
                    App::redirect("cart.php", ["a" => "checkout", "loginfailed" => "1"]);
                }
                $errormessage .= "<li>" . $_LANG["loginincorrect"];
            }
        } else {
            if (!$user || $user && $custtype == "add") {
                $clientId = 0;
                $signup = $checkClientsProfileUneditiableFields = true;
                $emailChecks = CHECKDETAILS_EMAIL_ALL;
                if ($user && $custtype == "add") {
                    if ((new WHMCS\Validate())->validate("uniqueemail", "email", "", [$user->id, ""])) {
                        $emailChecks ^= CHECKDETAILS_EMAIL_UNIQUE_USER ^ CHECKDETAILS_EMAIL_ASSOC_CLIENT;
                    }
                    $checkClientsProfileUneditiableFields = false;
                    $signup = NULL;
                }
                $phonenumber = App::formatPostedPhoneNumber();
                $_SESSION["cart"]["user"] = ["firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber, "tax_id" => $clientTaxId];
                $errormessage .= checkDetailsareValid("", $signup, $emailChecks, false, true, $checkClientsProfileUneditiableFields, false, false, true);
                unset($emailChecks);
            }
        }
        if ($validatelogin) {
            redir("a=checkout");
        }
        if ($contact == "new") {
            redir("a=addcontact");
        }
        if ($contact == "addingnew") {
            $errormessage .= checkContactDetails("", false, "domaincontact");
        }
        if ($availablegateways[$paymentmethod]["type"] == WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD && $ccinfo) {
            $gateway = new WHMCS\Module\Gateway();
            $gateway->load($paymentmethod);
            $cvcRequired = App::isInRequest("applycredit") ? !(bool) App::getFromRequest("applycredit") : true;
            if ($client instanceof WHMCS\User\Client && !$cvcRequired) {
                $carttotals = calcCartTotals(Auth::client(), false);
                if ($client->credit < $carttotals["rawtotal"]) {
                    $cvcRequired = true;
                }
            }
            if ($gateway->functionExists("cc_validation")) {
                $params = [];
                $params["cardtype"] = $cctype;
                $params["cardnum"] = ccFormatNumbers($ccnumber);
                $params["cardexp"] = ccFormatDate(ccFormatNumbers($ccexpirymonth . $ccexpiryyear));
                $params["cardstart"] = ccFormatDate(ccFormatNumbers($ccstartmonth . $ccstartyear));
                $params["cardissuenum"] = ccFormatNumbers($ccissuenum);
                $errormessage .= $gateway->call("cc_validation", $params);
                $params = NULL;
            } else {
                if ($ccinfo == "new") {
                    $errormessage .= updateCCDetails("", $cctype, $ccnumber, $cccvv, $ccexpirymonth . $ccexpiryyear, $ccstartmonth . $ccstartyear, $ccissuenum);
                }
                if ($cvcRequired && !$cccvv) {
                    $errormessage .= "<li>" . $_LANG["creditcardccvinvalid"];
                }
            }
            $_SESSION["cartccdetail"] = encrypt(base64_encode(serialize([$cctype, $ccnumber, $ccexpirymonth, $ccexpiryyear, $ccstartmonth, $ccstartyear, $ccissuenum, $cccvv, $nostore, $ccinfo, $ccDescription])));
        }
        $errormessage .= cartValidationOnCheckout($clientId);
        $currency = Currency::factoryForClientArea();
        if ($whmcs->get_req_var("updateonly")) {
            $errormessage = "";
        }
        if ($ajax && $errormessage) {
            exit($errormessage);
        }
        if (empty($availablegateways)) {
            logActivity("Order Failed: No payment methods setup so order could not continue. Ensure at least one gateway module is active in Payment Gateway Config Page.");
            $errormessage .= "<li>" . Lang::trans("orderForm.errorNoGateways");
        } else {
            if (!isset($availablegateways[$paymentmethod])) {
                $errormessage .= "<li>" . Lang::trans("orderForm.errorUnavailableGateway");
            }
        }
        if (!$errormessage && (!isset($_POST["updateonly"]) || !$_POST["updateonly"])) {
            if (!$user) {
                $securityQuestion = App::getFromRequest("securityqid") ?: 0;
                $securityAnswer = App::getFromRequest("securityqans");
                if (!ctype_digit($securityQuestion)) {
                    $securityQuestion = 0;
                }
                $user = Auth::registerAndLogin($firstname, $lastname, $email, $password, Lang::getName(), $securityQuestion, $securityAnswer);
                unset($securityQuestion);
                unset($securityAnswer);
                $userId = $user->id;
            }
            if (!$client || $custtype === "add") {
                $client = $user->createClient($firstname, $lastname, $companyname, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, true, ["tax_id" => $clientTaxId], "", false, $marketingoptin);
                $clientId = $client->id;
            }
            if ($contact == "addingnew") {
                $contact = addContact($clientId, $domaincontactfirstname, $domaincontactlastname, $domaincontactcompanyname, $domaincontactemail, $domaincontactaddress1, $domaincontactaddress2, $domaincontactcity, $domaincontactstate, $domaincontactpostcode, $domaincontactcountry, $domaincontactphonenumber, "", "", "", "", "", "", $domainContactTaxId);
            }
            $_SESSION["cart"]["contact"] = $contact;
            define("INORDERFORM", true);
            $langBackup = NULL;
            if (Auth::client()) {
                $langBackup = getUsersLang(Auth::client()->id);
            }
            $carttotals = calcCartTotals(Auth::client(), true, false);
            if ($langBackup) {
                swapLang($langBackup);
            }
            unset($langBackup);
            $_SESSION["orderdetails"]["ccinfo"] = $ccinfo;
            $_SESSION["orderdetails"]["ccDescription"] = $ccDescription;
            if ($ccinfo == "new" && !$nostore) {
                $newPayMethod = NULL;
                updateCCDetails($clientId, $cctype, $ccnumber, $cccvv, $ccexpirymonth . $ccexpiryyear, $ccstartmonth . $ccstartyear, $ccissuenum, "", "", $paymentmethod, $newPayMethod, $ccDescription);
                if ($newPayMethod) {
                    $invoiceModel = WHMCS\Billing\Invoice::find($_SESSION["orderdetails"]["InvoiceID"]);
                    if ($invoiceModel) {
                        $invoiceModel->payMethod()->associate($newPayMethod);
                        $invoiceModel->save();
                    }
                    $_SESSION["orderdetails"]["NewPayMethodId"] = $newPayMethod->id;
                }
            }
            $orderid = $_SESSION["orderdetails"]["OrderID"];
            $order = new WHMCS\Order();
            $order->setID($orderid);
            $fraudModule = $order->getActiveFraudModule();
            if ($fraudModule && $order->shouldFraudCheckBeSkipped()) {
                $fraudModule = "";
            }
            if (!$fraudModule) {
                if ($ajax) {
                    WHMCS\Terminus::getInstance()->doExit();
                }
                redir("a=complete");
            }
            $fraud = new WHMCS\Module\Fraud();
            logActivity("Order ID " . $orderid . " Fraud Check Initiated");
            update_query("tblorders", ["status" => "Fraud"], ["id" => $orderid]);
            if ($_SESSION["orderdetails"]["Products"]) {
                foreach ($_SESSION["orderdetails"]["Products"] as $productid) {
                    update_query("tblhosting", ["domainstatus" => "Fraud"], ["id" => $productid, "domainstatus" => "Pending"]);
                }
            }
            if ($_SESSION["orderdetails"]["Addons"]) {
                foreach ($_SESSION["orderdetails"]["Addons"] as $addonid) {
                    update_query("tblhostingaddons", ["status" => "Fraud"], ["id" => $addonid, "status" => "Pending"]);
                }
            }
            if ($_SESSION["orderdetails"]["Domains"]) {
                foreach ($_SESSION["orderdetails"]["Domains"] as $domainid) {
                    update_query("tbldomains", ["status" => "Fraud"], ["id" => $domainid, "status" => "Pending"]);
                }
            }
            $invoice = WHMCS\Billing\Invoice::where("status", WHMCS\Billing\Invoice::STATUS_UNPAID)->where("id", $_SESSION["orderdetails"]["InvoiceID"])->first();
            if ($invoice) {
                $invoice->status = WHMCS\Billing\Invoice::STATUS_CANCELLED;
                $invoice->save();
            }
            if ($fraud->load($fraudModule)) {
                $results = $fraud->doFraudCheck($orderid);
                $_SESSION["orderdetails"]["fraudcheckresults"] = $results;
            }
            if ($ajax) {
                exit;
            }
            redir("a=fraudcheck");
        }
    }
    $smartyvalues["errormessage"] = $errormessage;
    if ($allowcheckout) {
        $smartyvalues["captcha"] = new WHMCS\Utility\Captcha();
        $smartyvalues["captchaForm"] = WHMCS\Utility\Captcha::FORM_CHECKOUT_COMPLETION;
        $hookResponses = run_hook("ShoppingCartCheckoutOutput", ["cart" => WHMCS\Session::get("cart")]);
        $smartyvalues["hookOutput"] = $hookResponses;
    } else {
        $hookResponses = run_hook("ShoppingCartViewCartOutput", ["cart" => WHMCS\Session::get("cart")]);
        $smartyvalues["hookOutput"] = $hookResponses;
    }
    $goToView = false;
    $insufficientstock = false;
    if (isset($_POST["qty"]) && is_array($_POST["qty"])) {
        check_token();
        $didQtyChangeRemoveProducts = false;
        $temporderfrm = new WHMCS\OrderForm();
        foreach ($_POST["qty"] as $i => $qty) {
            $i = (int) $i;
            $qty = (int) $qty;
            if (is_array($_SESSION["cart"]["products"][$i])) {
                if (0 < $qty) {
                    $productinfo = $temporderfrm->setPid($_SESSION["cart"]["products"][$i]["pid"]);
                    if (!empty($productinfo) && $productinfo["allowqty"] === WHMCS\Cart\CartCalculator::QUANTITY_MULTIPLE && $productinfo["stockcontrol"]) {
                        if (!isset($productinfo["qty"])) {
                            $productinfo["qty"] = 0;
                        }
                        if ($productinfo["qty"] < $qty) {
                            $qty = $productinfo["qty"];
                            $insufficientstock = true;
                        }
                    }
                    $_SESSION["cart"]["products"][$i]["qty"] = $qty;
                } else {
                    if ($qty == 0) {
                        unset($_SESSION["cart"]["products"][$i]);
                        $didQtyChangeRemoveProducts = true;
                    }
                }
            }
        }
        if ($didQtyChangeRemoveProducts) {
            $_SESSION["cart"]["products"] = array_values($_SESSION["cart"]["products"]);
        }
        $goToView = true;
    }
    if (App::isInRequest("paddonqty")) {
        check_token();
        $addonQuantity = App::getFromRequest("paddonqty");
        if (is_array($addonQuantity)) {
            $tempOrderForm = new WHMCS\OrderForm();
            foreach ($addonQuantity as $i => $data) {
                if (is_array($_SESSION["cart"]["products"][$i])) {
                    foreach ($data as $addonI => $qty) {
                        if (is_array($_SESSION["cart"]["products"][$i]["addons"][$addonI])) {
                            if (0 < $qty) {
                                $addonData = $_SESSION["cart"]["products"][$i]["addons"][$addonI];
                                $addonInfo = $tempOrderForm->setAddonId($addonData["addonid"]);
                                if (!empty($addonInfo) && $addonInfo["allowqty"] !== WHMCS\Cart\CartCalculator::QUANTITY_SCALING) {
                                    $qty = 1;
                                }
                                $_SESSION["cart"]["products"][$i]["addons"][$addonI]["qty"] = $qty;
                            } else {
                                unset($_SESSION["cart"]["products"][$i]["addons"][$addonI]);
                            }
                        }
                    }
                    $_SESSION["cart"]["products"][$i]["addons"] = array_values($_SESSION["cart"]["products"][$i]["addons"]);
                }
            }
            $goToView = true;
        }
    }
    if (App::isInRequest("addonqty")) {
        check_token();
        $addonQuantity = App::getFromRequest("addonqty");
        if (is_array($addonQuantity)) {
            $didQtyChangeRemoveAddons = false;
            $tempOrderForm = new WHMCS\OrderForm();
            foreach ($addonQuantity as $i => $qty) {
                if (is_array($_SESSION["cart"]["addons"][$i])) {
                    if (0 < $qty) {
                        $addonData = $_SESSION["cart"]["addons"][$i];
                        $addonInfo = $tempOrderForm->setAddonId($addonData["id"]);
                        if (!empty($addonInfo) && $addonInfo["allowqty"] !== WHMCS\Cart\CartCalculator::QUANTITY_SCALING) {
                            $qty = 1;
                        }
                        $_SESSION["cart"]["addons"][$i]["qty"] = $qty;
                    } else {
                        unset($_SESSION["cart"]["addons"][$i]);
                        $didQtyChangeRemoveAddons = true;
                    }
                }
            }
            if ($didQtyChangeRemoveAddons = true) {
                $_SESSION["cart"]["addons"] = array_values($_SESSION["cart"]["addons"]);
            }
            $goToView = true;
        }
    }
    if ($goToView) {
        redir("a=view" . ($insufficientstock ? "&insufficientstock=1" : ""));
    }
    $smartyvalues["promoaddedsuccess"] = false;
    if ($promocode) {
        $promoerrormessage = SetPromoCode($promocode);
        if ($promoerrormessage) {
            $smartyvalues["promoerrormessage"] = $promoerrormessage;
            $smartyvalues["errormessage"] = "<li>" . $promoerrormessage;
        } else {
            $smartyvalues["promoaddedsuccess"] = true;
        }
        if ($paymentmethod) {
            $_SESSION["cart"]["paymentmethod"] = $paymentmethod;
        }
        if ($ccinfo) {
            $_SESSION["cart"]["ccinfo"] = $ccinfo;
        }
        if ($notes) {
            $_SESSION["cart"]["notes"] = $notes;
        }
        if ($firstname) {
            $phonenumber = App::formatPostedPhoneNumber();
            $_SESSION["cart"]["user"] = ["firstname" => $firstname, "lastname" => $lastname, "companyname" => $companyname, "email" => $email, "address1" => $address1, "address2" => $address2, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "phonenumber" => $phonenumber];
        }
    }
    $smartyvalues["promotioncode"] = $orderfrm->getCartDataByKey("promo");
    $cartsummary = $whmcs->get_req_var("cartsummary");
    $ignorenoconfig = $cartsummary ? true : false;
    $carttotals = calcCartTotals(Auth::client(), false, $ignorenoconfig);
    $promotype = $carttotals["promotype"];
    $promovalue = $carttotals["promovalue"];
    $promorecurring = $carttotals["promorecurring"];
    if (isset($carttotals["productRemovedFromCart"]) && $carttotals["productRemovedFromCart"]) {
        $smartyvalues["errormessage"] .= "<li>" . $whmcs->get_lang("outOfStockProductRemoved") . "</li>";
    }
    $promodescription = $promotype == "Percentage" ? $promovalue . "%" : $promovalue;
    if ($promotype == "Price Override") {
        $promodescription .= " " . $_LANG["orderpromopriceoverride"];
    } else {
        if ($promotype == "Free Setup") {
            $promodescription = $_LANG["orderpromofreesetup"];
        }
    }
    $promoRecurFor = (int) WHMCS\Database\Capsule::table("tblpromotions")->where("code", $smartyvalues["promotioncode"])->pluck("recurfor")->first();
    if ($promoRecurFor == 0) {
        $promodescription .= " " . $promorecurring . " " . $_LANG["orderdiscount"];
    } else {
        $forCycles = Lang::trans("orderForm.promoCycles", [":cycles" => $promoRecurFor]);
        $promodescription .= " " . $promorecurring . " " . $_LANG["orderdiscount"] . " <br /> " . $forCycles;
        unset($forCyles);
    }
    $smartyvalues["promotiondescription"] = $promodescription;
    unset($promoRecurFor);
    unset($promodescription);
    $amountOfCredit = 0;
    $canUseCreditOnCheckout = false;
    if ($client && $client->id === $clientId) {
        $amountOfCredit = $client->credit;
        if (0 < $amountOfCredit) {
            $canUseCreditOnCheckout = true;
        }
    }
    $smartyvalues["canUseCreditOnCheckout"] = $canUseCreditOnCheckout;
    $smartyvalues["creditBalance"] = new WHMCS\View\Formatter\Price($amountOfCredit, $currency);
    $smartyvalues["applyCredit"] = App::isInRequest("applycredit") ? (bool) App::getFromRequest("applycredit") : true;
    $smartyvalues["client"] = Auth::client();
    foreach ($carttotals as $k => $v) {
        $smartyvalues[$k] = $v;
    }
    $hasProductQuantities = false;
    $hasAddonQuantities = false;
    foreach ($carttotals["products"] as $product) {
        if ($product["allowqty"]) {
            $hasProductQuantities = true;
        }
        foreach ($product["addons"] as $addon) {
            if ($addon["allowqty"]) {
                $hasAddonQuantities = true;
            }
        }
        if ($hasProductQuantities && $hasAddonQuantities) {
            if (!$hasAddonQuantities) {
                foreach ($carttotals["addons"] as $addon) {
                    if ($addon["allowqty"]) {
                        $hasAddonQuantities = true;
                    }
                    if ($hasAddonQuantities) {
                    }
                }
            }
            $smartyvalues["showqtyoptions"] = $hasProductQuantities;
            $smartyvalues["showAddonQtyOptions"] = $hasAddonQuantities;
            $smartyvalues["taxenabled"] = $CONFIG["TaxEnabled"];
            $paymentmethod = $_SESSION["cart"]["paymentmethod"] ?? NULL;
            if (!$paymentmethod) {
                foreach ($availablegateways as $k => $v) {
                    $paymentmethod = $k;
                }
            }
            $smartyvalues["selectedgateway"] = $paymentmethod;
            $smartyvalues["selectedgatewaytype"] = $availablegateways[$paymentmethod]["type"] ?? NULL;
            if (empty($_SESSION["paypalexpress"]["payerid"])) {
                $smartyvalues["gateways"] = array_filter($availablegateways, function ($item) {
                    return $item["sysname"] != "paypalexpress";
                });
            } else {
                $smartyvalues["gateways"] = array_filter($availablegateways, function ($item) {
                    return $item["sysname"] == "paypalexpress";
                });
                $smartyvalues["selectedgateway"] = "paypalexpress";
            }
            $smartyvalues["existingCards"] = Illuminate\Support\Collection::make([]);
            $smartyvalues["ccinfo"] = $ccinfo;
            $smartyvalues["cctype"] = $cctype;
            $smartyvalues["ccdescription"] = $ccDescription;
            $smartyvalues["ccnumber"] = $ccnumber;
            $smartyvalues["ccexpirymonth"] = $ccexpirymonth;
            $smartyvalues["ccexpiryyear"] = $ccexpiryyear;
            $smartyvalues["ccstartmonth"] = $ccstartmonth;
            $smartyvalues["ccstartyear"] = $ccstartyear;
            $smartyvalues["ccissuenum"] = $ccissuenum;
            $smartyvalues["cccvv"] = $cccvv;
            $smartyvalues["showccissuestart"] = $CONFIG["ShowCCIssueStart"];
            $smartyvalues["shownostore"] = $CONFIG["CCAllowCustomerDelete"];
            $smartyvalues["allowClientsToRemoveCards"] = $CONFIG["CCAllowCustomerDelete"];
            $smartyvalues["months"] = $gateways->getCCDateMonths();
            $smartyvalues["startyears"] = $gateways->getCCStartDateYears();
            $smartyvalues["years"] = $gateways->getCCExpiryDateYears();
            $smartyvalues["expiryyears"] = $smartyvalues["years"];
            $cartitems = $orderfrm->getNumItemsInCart();
            if (!$cartitems) {
                $allowcheckout = false;
            }
            $smartyvalues["cartitems"] = $cartitems;
            $smartyvalues["checkout"] = $allowcheckout;
            $smartyvalues["selectedAccountId"] = $selectedAccountId;
            $smartyvalues["productRecommendations"] = (new WHMCS\OrderForm())->getRecommendationsByLocation($carttotals["products"], $allowcheckout ? "checkout" : "viewcart");
            $smartyvalues["recommendStyle"] = WHMCS\Config\Setting::getValue("ProductRecommendationStyle");
            if ($user) {
                if (is_numeric($selectedAccountId) && Auth::client()) {
                    $clientsdetails = getClientsDetails();
                    $clientsdetails["country"] = $clientsdetails["countryname"];
                } else {
                    $clientsdetails = $_SESSION["cart"]["user"];
                }
                $custtype = "existing";
                if (!$selectedAccountId || $selectedAccountId === "new") {
                    $custtype = "add";
                }
                $smartyvalues["loggedin"] = true;
                $accounts = $user->clients()->get();
                $accountList = collect([]);
                foreach ($accounts as $accountItem) {
                    $accountItem->noPermission = !$accountItem->pivot->getPermissions()->hasPermission("orders");
                    $accountItem->isClosed = $accountItem->status === WHMCS\User\Client::STATUS_CLOSED;
                    $accountList->add($accountItem);
                }
                unset($accounts);
                $smartyvalues["accounts"] = $accountList;
            } else {
                $clientsdetails = $_SESSION["cart"]["user"];
                $_SESSION["loginurlredirect"] = "cart.php?a=login";
                if (!$custtype) {
                    $custtype = "new";
                }
                $smartyvalues["accounts"] = collect([]);
            }
            $customfields = getCustomFields("client", "", "", "", "on", $customfield ?? NULL);
            $smartyvalues["loginemail"] = App::getFromRequest("loginemail");
            if (WHMCS\Session::getAndDelete("expressExistingUser")) {
                $custtype = "existing";
                $smartyvalues["loginemail"] = $clientsdetails["email"];
            }
            if ($selectedAccountId === "new") {
                $custtype = "add";
            }
            $smartyvalues["custtype"] = $custtype;
            $requiredDetailsVars = ["firstname", "lastname", "email", "phonenumber", "companyname", "address1", "address2", "city", "state", "postcode", "tax_id"];
            foreach ($requiredDetailsVars as $requiredDetailsVar) {
                $clientsdetails[$requiredDetailsVar] = $clientsdetails[$requiredDetailsVar] ?? NULL;
            }
            unset($requiredDetailsVars);
            $smartyvalues["clientsdetails"] = $clientsdetails;
            $smartyvalues["loginfailed"] = $loginfailed;
            $countries = new WHMCS\Utility\Country();
            $smartyvalues["countries"] = $countries->getCountryNameArray();
            $smartyvalues["defaultcountry"] = WHMCS\Config\Setting::getValue("DefaultCountry");
            $country = scoalesce($country, $clientsdetails["countrycode"] ?? "", $clientsdetails["country"]);
            $smartyvalues["clientcountrydropdown"] = getCountriesDropDown($country);
            $smartyvalues["country"] = $country;
            $smartyvalues["password"] = $password;
            $smartyvalues["password2"] = $password2;
            $smartyvalues["securityqans"] = $securityqans;
            $smartyvalues["securityqid"] = $securityqid;
            $smartyvalues["customfields"] = $customfields;
            $smartyvalues["securityquestions"] = $securityquestions;
            $smartyvalues["shownotesfield"] = $CONFIG["ShowNotesFieldOnCheckout"];
            $smartyvalues["orderNotes"] = $notes;
            $smartyvalues["notes"] = 0 < strlen($notes) ? $notes : $_LANG["ordernotesdescription"];
            $smartyvalues["showMarketingEmailOptIn"] = !$client && WHMCS\Config\Setting::getValue("AllowClientsEmailOptOut");
            $smartyvalues["marketingEmailOptInMessage"] = Lang::trans("emailMarketing.optInMessage") != "emailMarketing.optInMessage" ? Lang::trans("emailMarketing.optInMessage") : WHMCS\Config\Setting::getValue("EmailMarketingOptInMessage");
            $smartyvalues["marketingEmailOptIn"] = App::isInRequest("marketingoptin") ? (bool) App::getFromRequest("marketingoptin") : (bool) (!WHMCS\Config\Setting::getValue("EmailMarketingRequireOptIn"));
            $smartyvalues["accepttos"] = $CONFIG["EnableTOSAccept"];
            $smartyvalues["tosurl"] = $CONFIG["TermsOfService"];
            $smartyvalues["domainsinorder"] = 0 < count($orderfrm->getCartDataByKey("domains", []));
            $domaincontacts = [];
            $result = select_query("tblcontacts", "", ["userid" => $clientId, "address1" => ["sqltype" => "NEQ", "value" => ""]], "firstname` ASC,`lastname", "ASC");
            while ($data = mysql_fetch_array($result)) {
                $domaincontacts[] = ["id" => $data["id"], "name" => $data["firstname"] . " " . $data["lastname"]];
            }
            $smartyvalues["domaincontacts"] = $domaincontacts;
            $smartyvalues["contact"] = $contact;
            if ($contact == "addingnew") {
                $addcontact = true;
            }
            $smartyvalues["addcontact"] = $addcontact;
            $smartyvalues["domaincontact"] = ["firstname" => $domaincontactfirstname, "lastname" => $domaincontactlastname, "companyname" => $domaincontactcompanyname, "email" => $domaincontactemail, "address1" => $domaincontactaddress1, "address2" => $domaincontactaddress2, "city" => $domaincontactcity, "state" => $domaincontactstate, "postcode" => $domaincontactpostcode, "country" => $domaincontactcountry, "phonenumber" => $domaincontactphonenumber, "tax_id" => $domainContactTaxId];
            $smartyvalues["domaincontactcountrydropdown"] = getCountriesDropDown($domaincontactcountry, "domaincontactcountry");
            $clientPayMethods = NULL;
            $client = WHMCS\User\Client::find($clientId);
            if (!is_null($client)) {
                $clientPayMethods = $client->payMethods;
            }
            unset($client);
            $gatewaysoutput = $checkoutOutput = $expressCheckoutButtons = [];
            foreach ($availablegateways as $module => $vals) {
                $gatewayModule = new WHMCS\Module\Gateway();
                $gatewayModule->load($module);
                $params = $gatewayModule->loadSettings();
                $params["amount"] = $carttotals["rawtotal"];
                $params["currency"] = $currency["code"];
                if (isset($params["convertto"]) && $params["convertto"]) {
                    $currencyCode = WHMCS\Database\Capsule::table("tblcurrencies")->where("id", "=", (int) $params["convertto"])->value("code");
                    $convertToAmount = convertCurrency($carttotals["rawtotal"], $currency["id"], $params["convertto"]);
                    $params["amount"] = format_as_currency($convertToAmount);
                    $params["currency"] = $currencyCode;
                    $params["currencyId"] = (int) $params["convertto"];
                    $params["basecurrencyamount"] = format_as_currency($carttotals["rawtotal"]);
                    $params["basecurrency"] = $currency["code"];
                    $params["baseCurrencyId"] = $currency["id"];
                }
                if (!isset($params["currency"]) || !$params["currency"]) {
                    $params["amount"] = format_as_currency($carttotals["rawtotal"]);
                    $params["currency"] = $currency["code"];
                    $params["currencyId"] = $currency["id"];
                }
                if ($clientId) {
                    $payMethod = $clientPayMethods->where("gateway_name", $module)->first();
                    $gatewayId = "";
                    if ($payMethod) {
                        $payment = $payMethod->payment;
                        if ($payment instanceof WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
                            $gatewayId = $payment->getRemoteToken();
                        }
                    }
                    $params["gatewayid"] = $gatewayId;
                }
                $params["isCheckout"] = (bool) $allowcheckout;
                if ($gatewayModule->functionExists("orderformoutput")) {
                    $output = $gatewayModule->call("orderformoutput", $params);
                    if ($output) {
                        $gatewaysoutput[] = $output;
                    }
                }
                $params["cart"] = WHMCS\Cart\CartCalculator::fromSession();
                WHMCS\Session::set("ExpressCheckoutAvailable", false);
                if ((0 < $params["cart"]->getTotal()->toNumeric() || $params["cart"]->isRecurring()) && $gatewayModule->functionExists("express_checkout_initiate")) {
                    $output = $gatewayModule->call("express_checkout_initiate", $params);
                    if ($output) {
                        WHMCS\Session::set("ExpressCheckoutAvailable", true);
                        $expressCheckoutButtons[] = $output;
                    }
                }
            }
            $smartyvalues["gatewaysoutput"] = $gatewaysoutput;
            $smartyvalues["checkoutOutput"] = $checkoutOutput;
            $smartyvalues["expressCheckoutButtons"] = $expressCheckoutButtons;
            $smartyvalues["clientsProfileOptionalFields"] = explode(",", WHMCS\Config\Setting::getValue("ClientsProfileOptionalFields"));
            $smartyvalues["showTaxIdField"] = WHMCS\Billing\Tax\Vat::isUsingNativeField();
            if ($cartsummary) {
                $ajax = "1";
                $templatefile = "cartsummary";
                $productinfo = $orderfrm->setPid($_SESSION["cart"]["cartsummarypid"]);
                $orderFormTemplateName = $productinfo["orderfrmtpl"] == "" ? $orderFormTemplateName : $productinfo["orderfrmtpl"];
            }
            $smartyvalues["inExpressCheckout"] = NULL;
            if ($orderfrm->inExpressCheckout()) {
                $smartyvalues["inExpressCheckout"] = true;
                $smartyvalues["expressCheckoutGateway"] = WHMCS\Module\Gateway::factory($orderfrm->getExpressCheckoutGateway())->getDisplayName();
                $gatewayModule = new WHMCS\Module\Gateway();
                $gatewayModule->load($orderfrm->getExpressCheckoutGateway());
                if ($gatewayModule->functionExists("express_checkout_checkout_output")) {
                    $smartyvalues["expressCheckoutOutput"] = $gatewayModule->call("express_checkout_checkout_output", $orderfrm->getExpressCheckoutData());
                }
                $smartyvalues["canUseCreditOnCheckout"] = false;
                $smartyvalues["applyCredit"] = false;
            }
        }
    }
}
if ($a == "login") {
    if ($user) {
        redir("a=checkout");
    }
    $templatefile = "login";
    $smartyvalues["captcha"] = new WHMCS\Utility\Captcha();
    $smartyvalues["captchaForm"] = WHMCS\Utility\Captcha::FORM_LOGIN;
    $smartyvalues["invalid"] = WHMCS\Session::getAndDelete("CaptchaError");
    $_SESSION["loginurlredirect"] = "cart.php?a=login";
    if ($incorrect) {
        $smartyvalues["incorrect"] = true;
    }
}
if ($a == "fraudcheck") {
    $orderid = $_SESSION["orderdetails"]["OrderID"];
    $results = isset($_SESSION["orderdetails"]["fraudcheckresults"]) ? $_SESSION["orderdetails"]["fraudcheckresults"] : "";
    unset($_SESSION["orderdetails"]["fraudcheckresults"]);
    if (!$results) {
        $order = new WHMCS\Order();
        $order->setID($orderid);
        $fraudModule = $order->getActiveFraudModule();
        if ($fraudModule && $order->shouldFraudCheckBeSkipped()) {
            $fraudModule = "";
        }
        if (!$fraudModule || !$orderid) {
            redir("a=complete");
        }
        $fraud = new WHMCS\Module\Fraud();
        if ($fraud->load($fraudModule)) {
            $results = $fraud->doFraudCheck($orderid);
        }
    }
    $isFraud = false;
    $fraudError = [];
    if (array_key_exists("error", $results) && $results["error"]) {
        $isFraud = true;
        $fraudError = $results["error"];
    }
    $hookresults = ["orderid" => $orderid, "ordernumber" => $_SESSION["orderdetails"]["OrderNumber"], "fraudresults" => $_SESSION["orderdetails"]["fraudcheckresults"], "invoiceid" => $_SESSION["orderdetails"]["InvoiceID"], "amount" => $_SESSION["orderdetails"]["TotalDue"], "fraudresults" => $results, "isfraud" => $isFraud, "frauderror" => $fraudError, "clientdetails" => getClientsDetails($clientId)];
    run_hook("AfterFraudCheck", $hookresults);
    $error = $results["error"];
    if ($results["userinput"]) {
        logActivity("Order ID " . $orderid . " Fraud Check Awaiting User Input");
        run_hook("FraudCheckAwaitingUserInput", $hookresults);
        $templatefile = "fraudcheck";
        $smarty->assign("errortitle", $results["title"]);
        $smarty->assign("error", $results["description"]);
        outputClientArea($templatefile, false, ["ClientAreaPageCartFraudCheckAwaitingUserInput"]);
        exit;
    }
    if ($error) {
        $invoiceId = $_SESSION["orderdetails"]["InvoiceID"];
        $invoice = new WHMCS\Invoice($invoiceId);
        $paymentMethod = $_SESSION["orderdetails"]["PaymentMethod"];
        logActivity("Order ID " . $orderid . " Failed Fraud Check");
        refundCreditOnStatusChange($invoice->getData("model"));
        try {
            $gateway = WHMCS\Module\Gateway::factory($paymentMethod);
            if ($invoiceId && $gateway->functionExists("fraud_check_fail")) {
                $orderDetails = WHMCS\Session::get("orderdetails");
                $invoice = new WHMCS\Invoice($invoiceId);
                $params = $invoice->initialiseGatewayAndParams();
                $params = array_merge($params, $invoice->getGatewayInvoiceParams());
                $params["invoiceStatus"] = $status;
                $params["gatewayid"] = $params["clientdetails"]["gatewayid"];
                $gateway->call("fraud_check_fail", $params);
            }
        } catch (Exception $e) {
            logActivity("An error occurred on gateway fraud check fail: " . $e->getMessage());
        }
        try {
            $orderRequestor = WHMCS\Order\Order::find($orderid)->requestor;
            $userValidation = DI::make("userValidation");
            if ($orderRequestor && $userValidation->isEnabled() && $userValidation->isAutoEnabled()) {
                $userValidation->initiateForUser($orderRequestor);
                $orderRequestor->refresh();
                $userValidation->sendVerificationEmail($orderRequestor);
            }
            $submitUrlForUser = is_null($orderRequestor) ? "" : $userValidation->getSubmitUrlForUser($orderRequestor);
        } catch (Exception $e) {
            logActivity($e->getMessage());
        }
        run_hook("FraudCheckFailed", $hookresults);
        $templatefile = "fraudcheck";
        $smarty->assign("errortitle", $error["title"]);
        $smarty->assign("error", $error["description"]);
        $smarty->assign("userValidation", $orderRequestor->validation);
        $smarty->assign("userValidationUrl", $submitUrlForUser);
        $smartyvalues["carttpl"] = $orderFormTemplateName;
        outputClientArea($templatefile, false, ["ClientAreaPageCartFraudCheckFailed"]);
        exit;
    }
    update_query("tblorders", ["status" => "Pending"], ["id" => $orderid]);
    if ($_SESSION["orderdetails"]["Products"]) {
        foreach ($_SESSION["orderdetails"]["Products"] as $productid) {
            update_query("tblhosting", ["domainstatus" => "Pending"], ["id" => $productid, "domainstatus" => "Fraud"]);
        }
    }
    if ($_SESSION["orderdetails"]["Addons"]) {
        foreach ($_SESSION["orderdetails"]["Addons"] as $addonid) {
            update_query("tblhostingaddons", ["status" => "Pending"], ["id" => $addonid, "status" => "Fraud"]);
        }
    }
    if ($_SESSION["orderdetails"]["Domains"]) {
        foreach ($_SESSION["orderdetails"]["Domains"] as $domainid) {
            update_query("tbldomains", ["status" => "Pending"], ["id" => $domainid, "status" => "Fraud"]);
        }
    }
    $invoice = WHMCS\Billing\Invoice::where("status", WHMCS\Billing\Invoice::STATUS_CANCELLED)->where("id", $_SESSION["orderdetails"]["InvoiceID"])->first();
    if ($invoice) {
        $invoice->status = WHMCS\Billing\Invoice::STATUS_UNPAID;
        $invoice->save();
    }
    logActivity("Order ID " . $orderid . " Passed Fraud Check");
    run_hook("FraudCheckPassed", $hookresults);
    redir("a=complete");
}
$smartyvalues["expressCheckoutInfo"] = NULL;
$smartyvalues["addons_html"] = NULL;
$smartyvalues["ispaid"] = NULL;
$smartyvalues["hasRecommendations"] = NULL;
$smartyvalues["expressCheckoutError"] = NULL;
if ($a == "complete") {
    $remoteAuth = DI::make("remoteAuth");
    $remoteAuth->linkRemoteAccounts();
    $remoteAuthData = (new WHMCS\Authentication\Remote\Management\Client\ViewHelper())->getTemplateData();
    foreach ($remoteAuthData as $key => $value) {
        $smartyvalues[$key] = $value;
    }
    if (!is_array($_SESSION["orderdetails"])) {
        redir();
    }
    $orderid = $_SESSION["orderdetails"]["OrderID"];
    $invoiceid = $_SESSION["orderdetails"]["InvoiceID"];
    $paymentmethod = $_SESSION["orderdetails"]["PaymentMethod"];
    if (WHMCS\Session::get("InOrderButNeedProcessPaidInvoiceAction") && 0 < (int) $invoiceid) {
        processPaidInvoice($invoiceid);
    }
    $total = 0;
    if ($invoiceid) {
        $result = select_query("tblinvoices", "id,total,paymentmethod,status", ["userid" => $clientId, "id" => $invoiceid]);
        $data = mysql_fetch_array($result);
        $invoiceid = $data["id"];
        $total = $data["total"];
        $paymentmethod = $data["paymentmethod"];
        $status = $data["status"];
        if (!$invoiceid) {
            exit("Invalid Invoice ID");
        }
        $clientsdetails = getClientsDetails($clientId);
    }
    $paymentmethod = WHMCS\Gateways::makeSafeName($paymentmethod);
    if (!$paymentmethod) {
        exit("Unexpected payment method value. Exiting.");
    }
    $services = WHMCS\Service\Service::with("product")->where("userid", $clientId)->where("orderid", $orderid)->where("domainstatus", WHMCS\Service\Status::PENDING);
    $autoProvision = getNewClientAutoProvisionStatus($clientId);
    if ($autoProvision) {
        foreach ($services->get() as $service) {
            if ($service->product->autoSetup == "order") {
                logActivity("Running Module Create on Order");
                $result = $service->legacyProvision();
                if ($result == "success" && $service->product->module != "marketconnect") {
                    sendMessage("defaultnewacc", $service->id);
                }
            }
        }
    } else {
        $service = $services->first();
        if (!is_null($service) && $service->product->autoSetup == "order") {
            logActivity("Module Create on Order Suppressed for New Client");
        }
    }
    $addons = WHMCS\Service\Addon::whereHas("productAddon", function (Illuminate\Database\Eloquent\Builder $query) {
        $query->where("autoactivate", "order");
    })->with("productAddon.welcomeEmailTemplate", "productAddon")->where("orderid", "=", $orderid)->where("status", "=", "Pending")->where("addonid", ">", 0)->get();
    foreach ($addons as $addon) {
        if ($addon->productAddon) {
            $noModule = true;
            $automationResult = false;
            if ($addon->productAddon->module) {
                $noModule = false;
                if ($autoProvision) {
                    $automationResult = WHMCS\Service\Automation\AddonAutomation::factory($addon)->provision();
                } else {
                    logActivity("Module Create on Order Suppressed for New Client");
                }
            }
            if ($noModule || $automationResult) {
                if ($addon->productAddon->welcomeEmailTemplateId) {
                    sendMessage($addon->productAddon->welcomeEmailTemplate, 0, ["addon_id" => $addon->id]);
                }
                if ($noModule) {
                    $addon->status = "Active";
                    $addon->save();
                    HookMgr::run("AddonActivation", ["id" => $addon->id, "userid" => $addon->clientId, "clientid" => $addon->clientId, "serviceid" => $id, "addonid" => $addon->addonId]);
                }
            }
        }
    }
    unset($autoProvision);
    $gateway = new WHMCS\Module\Gateway();
    $gateway->load($paymentmethod);
    if (0 < $invoiceid) {
        $invoice = new WHMCS\Invoice($invoiceid);
        try {
            $params = $invoice->initialiseGatewayAndParams();
        } catch (Exception $e) {
            logActivity("Failed to initialise payment gateway module: " . $e->getMessage());
            throw new WHMCS\Exception\Fatal("Could not initialise payment gateway. Please contact support.");
        }
        $params = array_merge($params, $invoice->getGatewayInvoiceParams());
        if ($orderfrm->inExpressCheckout()) {
            $paramsForExpressCheckout = array_merge($params, ["orderId" => $orderid, "expressCheckout" => $orderfrm->getExpressCheckoutData()["metaData"]]);
            try {
                $result = $gateway->call("express_checkout_capture", $paramsForExpressCheckout);
                if ($result["subscriptionId"]) {
                    $order = WHMCS\Order\Order::findOrFail($orderid);
                    $invoice = $order->invoice;
                    if ($invoice) {
                        $invoice->saveSubscriptionId($result["subscriptionId"]);
                    }
                }
                if ($result["status"] == "pending") {
                    $smartyvalues["expressCheckoutInfo"] = Lang::trans("expressCheckoutInfo");
                } else {
                    if ($result["status"] == "completed" && 0 < $result["amount"]) {
                        try {
                            if ($result["currency"] !== $invoice->getModel()->currencyCode) {
                                throw new Exception("Payment currency does not match invoice: " . $result["currency"]);
                            }
                            $invoice->getModel()->addPaymentIfNotExists($result["amount"], $result["transid"], $result["fees"], $paymentmethod);
                        } catch (Exception $e) {
                            logActivity("Express Checkout - Failed to Add Payment: " . $e->getMessage());
                        }
                        $orderfrm->expressCheckoutCompleted();
                        redir("a=complete&express=1");
                    } else {
                        throw new Exception("Unrecognised express checkout capture status: " . $result["status"]);
                    }
                }
            } catch (Exception $e) {
                $smartyvalues["expressCheckoutError"] = Lang::trans("expressCheckoutError");
                logActivity("Express Checkout Error: " . $e->getMessage());
            }
        }
    }
    if ($invoiceid && $gateway->functionExists("post_checkout") && !App::isInRequest("express")) {
        $payMethodId = (int) $_SESSION["orderdetails"]["ccinfo"];
        $ccDescription = $_SESSION["orderdetails"]["ccDescription"];
        $payMethod = NULL;
        if ($payMethodId && is_numeric($payMethodId)) {
            $payMethod = WHMCS\Payment\PayMethod\Model::findForClient($payMethodId, $clientId);
        }
        if ($payMethod) {
            WHMCS\Database\Capsule::table("tblinvoices")->where("id", $invoiceid)->update(["paymethodid" => $payMethod->id, "updated_at" => WHMCS\Carbon::now()->toDateTimeString()]);
        }
        $params["invoiceStatus"] = $status;
        $params["gatewayid"] = $params["clientdetails"]["gatewayid"];
        try {
            $captureResult = $gateway->call("post_checkout", $params);
        } catch (WHMCS\Exception\Gateways\RedirectToInvoice $e) {
            redir("id=" . $invoiceid, "viewinvoice.php");
        }
        if (is_array($captureResult)) {
            logTransaction($paymentmethod, $captureResult["rawdata"], ucfirst($captureResult["status"]));
            if ($captureResult["status"] == "success") {
                if (!function_exists("saveNewRemoteCardDetails")) {
                    require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ccfunctions.php";
                }
                if ($ccDescription) {
                    $captureResult["description"] = $ccDescription;
                }
                $invoiceModel = WHMCS\Billing\Invoice::find($invoiceid);
                if (array_key_exists("gatewayid", $captureResult)) {
                    if ($payMethod) {
                        if ($payMethod->payment->getRemoteToken() != $captureResult["gatewayid"]) {
                            $payMethod->payment->setRemoteToken($captureResult["gatewayid"]);
                            $payMethod->payment->save();
                        }
                    } else {
                        $newCardPayMethod = saveNewRemoteCardDetails($captureResult, $gateway, $params["clientdetails"]["userid"]);
                        if ($invoiceModel) {
                            $invoiceModel->payMethod()->associate($newCardPayMethod);
                            $invoiceModel->save();
                        }
                    }
                }
                if ($status == "Unpaid") {
                    $amount = $params["amount"];
                    if (isset($captureResult["amount"])) {
                        $amount = $captureResult["amount"];
                    }
                    $invoiceModel->addPayment($amount, $captureResult["transid"], $captureResult["fee"], $paymentmethod);
                    $_SESSION["orderdetails"]["paymentcomplete"] = true;
                    $status = "Paid";
                }
            }
        }
    }
    if ($invoiceid && $status == "Unpaid" && !$gateway->getMetaDataValue("DisableCheckoutAutoRedirect")) {
        try {
            if (!isValidforPath($paymentmethod)) {
                throw new InvalidArgumentException("Invalid Payment Gateway Name");
            }
            $gatewayInterface = WHMCS\Module\Gateway::factory($paymentmethod);
        } catch (Exception $e) {
            WHMCS\Terminus::getInstance()->doDie($e->getMessage());
        }
        $gatewaytype = $gatewayInterface->getParam("type");
        if ($gatewaytype == WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD && (WHMCS\Config\Setting::getValue("AutoRedirectoInvoice") == "on" || WHMCS\Config\Setting::getValue("AutoRedirectoInvoice") == "gateway")) {
            if (!$gatewayInterface->functionExists("nolocalcc")) {
                App::redirectToRoutePath("cart-invoice-pay-process", [$invoiceid]);
            }
        } else {
            if ($gatewaytype == WHMCS\Module\Gateway::GATEWAY_BANK && (WHMCS\Config\Setting::getValue("AutoRedirectoInvoice") == "on" || WHMCS\Config\Setting::getValue("AutoRedirectoInvoice") == "gateway")) {
                App::redirectToRoutePath("invoice-pay", [$invoiceid]);
            }
        }
        if ($CONFIG["AutoRedirectoInvoice"] == "on") {
            redir("id=" . $invoiceid, "viewinvoice.php");
        }
        if ($CONFIG["AutoRedirectoInvoice"] == "gateway") {
            if (in_array($paymentmethod, ["mailin", "banktransfer"])) {
                redir("id=" . $invoiceid, "viewinvoice.php");
            }
            $invoice = new WHMCS\Invoice($invoiceid);
            $paymentbutton = $invoice->getPaymentLink();
            unset($orderform);
            $templatefile = "forwardpage";
            $smarty->assign("message", $_LANG["forwardingtogateway"]);
            $smarty->assign("code", $paymentbutton);
            $smarty->assign("invoiceid", $invoiceid);
            outputClientArea($templatefile, false, ["ClientAreaForwardPage"]);
            exit;
        }
    }
    $amount = get_query_val("tblorders", "amount", ["userid" => $clientId, "id" => $orderid]);
    $ispaid = false;
    if ($invoiceid) {
        $invoiceStatus = get_query_val("tblinvoices", "status", ["id" => $invoiceid]);
        $ispaid = $invoiceStatus === "Paid" || $invoiceStatus === "Payment Pending" ? true : false;
        if ($ispaid) {
            $_SESSION["orderdetails"]["paymentcomplete"] = true;
        }
    }
    $hasRecommendations = false;
    $productRecommendations = [];
    if (WHMCS\Config\Setting::getValue("ProductRecommendationEnable") && WHMCS\Config\Setting::getValue("ProductRecommendationLocationOrderComplete")) {
        $productRecommendations = (new WHMCS\OrderForm())->getRecommendationsByLocation(WHMCS\Order\Order::findOrFail($orderid)->services()->pluck("packageId")->unique()->toArray(), "complete");
    }
    if (!empty($productRecommendations) && (0 < count($productRecommendations["own"]) || 0 < count($productRecommendations["order"]))) {
        $hasRecommendations = true;
    }
    $templatefile = "complete";
    $smartyvalues = array_merge($smartyvalues, ["orderid" => $orderid, "ordernumber" => $_SESSION["orderdetails"]["OrderNumber"], "invoiceid" => $invoiceid, "ispaid" => $ispaid, "amount" => $amount, "paymentmethod" => $paymentmethod, "clientdetails" => getClientsDetails($clientId), "hasRecommendations" => $hasRecommendations, "productRecommendations" => $productRecommendations, "recommendStyle" => WHMCS\Config\Setting::getValue("ProductRecommendationStyle")]);
    $addons_html = run_hook("ShoppingCartCheckoutCompletePage", $smartyvalues);
    $smartyvalues["addons_html"] = $addons_html;
}
if (!$templatefile) {
    redir();
}
$nowrapper = isset($_REQUEST["ajax"]) ? true : false;
$smartyvalues["requestedTpl"] = $orderFormTemplateName;
$smartyvalues["carttpl"] = $smartyvalues["requestedTpl"];
try {
    $smartyvalues["carttpl"] = WHMCS\View\Template\OrderForm::factory($templatefile . ".tpl", $orderFormTemplateName)->getName();
} catch (WHMCS\Exception\View\TemplateNotFound $e) {
}
$smartyvalues["phoneNumberInputStyle"] = (int) WHMCS\Config\Setting::getValue("PhoneNumberDropdown");
Menu::addContext("productGroups", $orderfrm->getProductGroups(true));
Menu::addContext("productGroupId", $smartyvalues["gid"]);
Menu::addContext("domainRegistrationEnabled", $smartyvalues["registerdomainenabled"]);
Menu::addContext("domainTransferEnabled", $smartyvalues["transferdomainenabled"]);
Menu::addContext("domainRenewalEnabled", $smartyvalues["renewalsenabled"]);
Menu::addContext("domain", $smartyvalues["domain"]);
Menu::addContext("currency", $smartyvalues["currency"]);
Menu::addContext("action", $a);
if ($whmcs->isInRequest("i")) {
    Menu::addContext("productInfoKey", $productInfoKey);
}
Menu::addContext("productId", $pid);
Menu::addContext("domainAction", $whmcs->get_req_var("domain"));
Menu::addContext("allowRemoteAuth", $allowcheckout);
Menu::primarySidebar("orderFormView");
Menu::secondarySidebar("orderFormView");
outputClientArea($templatefile, $nowrapper, ["ClientAreaPageCart"]);
