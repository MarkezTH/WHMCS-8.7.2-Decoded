<?php

define("ADMINAREA", true);
define("SHOPPING_CART", true);
require "../init.php";
$aInt = new WHMCS\Admin("Add New Order", false);
$aInt->title = $aInt->lang("orders", "addnew");
$aInt->sidebar = "orders";
$aInt->icon = "orders";
$aInt->requiredFiles(["orderfunctions", "domainfunctions", "configoptionsfunctions", "customfieldfunctions", "clientfunctions", "invoicefunctions", "processinvoices", "gatewayfunctions", "modulefunctions", "cartfunctions"]);
$action = $whmcs->get_req_var("action");
$userid = (int) $whmcs->get_req_var("userid");
$currency = getCurrency($userid);
$domains = new WHMCS\Domains();
if ($action == "getcontacts") {
    $contacts = [];
    $result = select_query("tblcontacts", "id,firstname,lastname,companyname,email", ["userid" => (int) $whmcs->get_req_var("userid")], "firstname", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $contacts[$data["id"]] = $data["firstname"] . " " . $data["lastname"];
    }
    $aInt->jsonResponse($contacts);
}
if ($action == "createpromo") {
    check_token("WHMCS.admin.default");
    try {
        if (!checkPermission("Create/Edit Promotions", true)) {
            throw new WHMCS\Exception\ProgramExit("You do not have permission to create promotional codes. If you feel this message to be an error, please contact the administrator.");
        }
        $code = App::getFromRequest("code");
        $pvalue = App::getFromRequest("pvalue");
        $type = App::getFromRequest("type");
        $recurring = App::getFromRequest("recurring");
        $recurfor = App::getFromRequest("recurfor");
        if (!$code) {
            throw new WHMCS\Exception\ProgramExit("Promotion Code is Required");
        }
        if ($pvalue <= 0) {
            throw new WHMCS\Exception\ProgramExit("Promotion Value must be greater than zero");
        }
        $duplicates = WHMCS\Database\Capsule::table("tblpromotions")->where("code", $code)->count();
        if ($duplicates) {
            throw new WHMCS\Exception\ProgramExit("Promotion Code already exists. Please try another.");
        }
        $promoid = WHMCS\Database\Capsule::table("tblpromotions")->insertGetId(["code" => $code, "type" => $type, "recurring" => $recurring, "value" => $pvalue, "maxuses" => "1", "recurfor" => $recurfor, "expirationdate" => "0000-00-00", "notes" => "Order Process One Off Custom Promo"]);
        $promo_type = $type;
        $promo_value = $pvalue;
        $promo_recurring = $recurring;
        $promo_code = $code;
        if ($promo_type == "Percentage") {
            $promo_value .= "%";
        } else {
            $promo_value = formatCurrency($promo_value);
        }
        $promo_recurring = $promo_recurring ? "Recurring" : "One Time";
        $aInt->jsonResponse(["success" => "true", "promoId" => $promoid, "promoCode" => $promo_code, "promoData" => $promo_code . " - " . $promo_value . " " . $promo_recurring]);
    } catch (Throwable $e) {
        $aInt->jsonResponse(["success" => "false", "errorMessage" => $e->getMessage()]);
    }
}
if ($action == "getconfigoptions") {
    check_token("WHMCS.admin.default");
    WHMCS\Session::release();
    if (!trim($pid)) {
        exit;
    }
    $options = "";
    $cycles = new WHMCS\Billing\Cycles();
    $cycle = App::getFromRequest("cycle");
    $cycle = $cycles->getNormalisedBillingCycle($cycle);
    $configOptions = getCartConfigOptions($pid, [], $cycle);
    if (count($configOptions)) {
        $configOptionsTitle = AdminLang::trans("setup.configoptions");
        $configTable = "";
        foreach ($configOptions as $configOption) {
            $configTableOptions = "";
            if ($configOption["optiontype"] == "1") {
                $selectOptions = "";
                foreach ($configOption["options"] as $optionData) {
                    $selected = $optionData["id"] == $configOption["selectedvalue"] ? " selected" : "";
                    $selectOptions .= "<option value=\"" . $optionData["id"] . "\"" . $selected . ">" . $optionData["name"] . "</option>";
                }
                $configTableOptions .= "<select onchange=\"updatesummary()\" class=\"form-control select-inline\"\n name=\"configoption[" . $orderid . "][" . $configOption["id"] . "]\">\n    " . $selectOptions . "\n</select>";
            } else {
                if ($configOption["optiontype"] == "2") {
                    foreach ($configOption["options"] as $optionData) {
                        $selected = $optionData["id"] == $configOption["selectedvalue"] ? " checked=\"checked\"" : "";
                        $configTableOptions .= "<label class=\"radio-inline\">\n    <input type=\"radio\" class=\"form-check-input\" onclick=\"updatesummary()\" \n    name=\"configoption[" . $orderid . "][" . $configOption["id"] . "]\" value=\"" . $optionData["id"] . "\"" . $selected . ">\n    " . $optionData["name"] . "\n</label>\n<br/>";
                    }
                } else {
                    if ($configOption["optiontype"] == "3") {
                        $selected = $configOption["selectedqty"] ? " checked=\"checked\"" : "";
                        $configTableOptions .= "<label class=\"checkbox-inline\">\n    <input type=\"checkbox\" onclick=\"updatesummary()\" name=\"configoption[" . $orderid . "][" . $configOption["id"] . "]\"\n    value=\"1\"{selected}> " . $configOption["options"][0]["name"] . "\n</label>";
                    } else {
                        if ($configOption["optiontype"] == "4") {
                            $configTableOptions .= "<input type=\"text\" onchange=\"updatesummary()\" name=\"configoption[" . $orderid . "][" . $configOption["id"] . "]\"\n class=\"form-control input-50 input-inline\" value=\"" . $configOption["selectedqty"] . "\" size=\"5\">\n  x " . $configOption["options"][0]["name"];
                        }
                    }
                }
            }
            $configTable .= "<tr>\n    <td width=\"130\" class=\"fieldlabel\">" . $configOption["optionname"] . "</td>\n    <td class=\"fieldarea\">" . $configTableOptions . "</td>\n</tr>";
        }
        $options .= "<p><strong>" . $configOptionsTitle . "</strong></p>\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    " . $configTable . "\n</table>";
    }
    $customfields = getCustomFields("product", $pid, "", true, "", $customfields ?? "");
    if (count($customfields)) {
        $options .= "<p><b>" . $aInt->lang("setup", "customfields") . "</b></p>\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">";
        foreach ($customfields as $customfield) {
            $inputfield = str_replace("name=\"customfield", "name=\"customfield[" . $orderid . "]", $customfield["input"]);
            $options .= "<tr><td width=\"130\" class=\"fieldlabel\">" . $customfield["name"] . "</td><td class=\"fieldarea\">" . $inputfield . "</td></tr>";
        }
        $options .= "</table>";
    }
    $addonshtml = "";
    $addonsarray = getAddons($pid);
    $orderItemId = App::getFromRequest("orderid");
    $marketConnect = new WHMCS\MarketConnect\MarketConnect();
    $addonsPromoOutput = $marketConnect->getAdminMarketplaceAddonPromo($addonsarray, $cycle, $orderItemId);
    $addonsarray = $marketConnect->removeMarketplaceAddons($addonsarray);
    if (count($addonsarray)) {
        foreach ($addonsarray as $addon) {
            if ($addon["allowsQuantity"] === 2) {
                $addonshtml .= "<input type=\"number\" class=\"form-control input-inline input-75\" min=\"1\" onchange=\"updatesummary()\" value=\"1\" name=\"paddonqty[" . $orderid . "][" . $addon["id"] . "]\"> x ";
            }
            $addonshtml .= "<label class=\"checkbox-inline\">" . str_replace("<input type=\"checkbox\" name=\"addons", "<input type=\"checkbox\" onclick=\"updatesummary()\" name=\"addons[" . $orderid . "]", $addon["checkbox"]) . " " . $addon["name"];
            if ($addon["description"]) {
                $addonshtml .= " - " . $addon["description"];
            }
            $addonshtml .= "</label><br />";
        }
    }
    if (count($addonsPromoOutput)) {
        foreach ($addonsPromoOutput as $addon) {
            if ($addon) {
                $addonshtml .= implode("<br>", $addon) . "<br>";
            }
        }
    }
    $aInt->jsonResponse(["options" => $options, "addons" => $addonshtml]);
}
if ($action == "getdomainaddlfields") {
    check_token("WHMCS.admin.default");
    $userInputDomain = trim($whmcs->get_req_var("domain"));
    $domainCounter = (int) $whmcs->get_req_var("domainnum");
    $invalidTld = $isIdnDomain = false;
    $additionalFieldsOutput = [];
    if ($userInputDomain) {
        $domain = new WHMCS\Domain\Domain();
        $domainParts = $domains->splitAndCleanDomainInput($userInputDomain);
        $domain->domain = $domains->fromPunycode($domainParts);
        $fields = $domain->getAdditionalFields()->getFieldsForOutput($domainCounter);
        foreach ($fields as $fieldLabel => $inputHTML) {
            $additionalFieldsOutput[] = "<tr class=\"domain-addt-fields\"><td width=\"130\" class=\"fieldlabel\">" . $fieldLabel . "</td><td class=\"fieldarea\">" . $inputHTML . "</td></tr>" . PHP_EOL;
        }
        $invalidTld = !$domain->isConfiguredTld();
        $isIdnDomain = $domain->isIdnDomain;
    }
    $aInt->jsonResponse(["invalidTld" => $invalidTld, "idn" => $isIdnDomain, "additionalFields" => implode($additionalFieldsOutput)]);
}
$previousSessionUserId = NULL;
if ($action == "validateProductsDomain") {
    $domain = App::getFromRequest("domain");
    if (empty($domain)) {
        $result = ["valid" => true];
    } else {
        try {
            if (strpos($domain, "*.") === 0) {
                $cleanDomain = $domain;
            } else {
                $domainParts = $domains->splitAndCleanDomainInput($domain);
                $cleanDomain = $domains->fromPunycode($domainParts);
            }
            $result = ["valid" => true, "cleaned" => $cleanDomain];
        } catch (Exception $e) {
            $result = ["valid" => false, "reason" => $e->getMessage()];
        }
    }
    $aInt->jsonResponse($result);
    exit;
}
if ($action == "validateOrder") {
    $missingFields = [];
    $regaction = App::getFromRequest("regaction");
    $regdomain = App::getFromRequest("regdomain");
    $domainfield = App::getFromRequest("domainfield");
    if (!is_array($regaction)) {
        $regaction = [];
    }
    foreach ($regaction as $key => $regAct) {
        if ($regAct && !empty($regdomain[$key])) {
            $domainParts = $domains->splitAndCleanDomainInput($regdomain[$key]);
            $cleanDomain = $domains->fromPunycode($domainParts);
            $domainParts = explode(".", $cleanDomain, 2);
            $additionalFields = new WHMCS\Domains\AdditionalFields();
            $additionalFields->setTLD($domainParts[1]);
            $additionalFields->setFieldValues($domainfield[$key]);
            $missingFields = $additionalFields->getMissingRequiredFields();
        }
    }
    if ($missingFields) {
        $aInt->jsonResponse(["success" => false, "title" => AdminLang::trans("orders.errors.requiredFields"), "message" => AdminLang::trans("orders.errors.requiredFieldsMsg")]);
    }
    $aInt->jsonResponse(["success" => true]);
}
$billingcycle = App::getFromRequest("billingcycle");
if (!is_array($billingcycle)) {
    $billingcycle = ["Monthly"];
}
if ($whmcs->get_req_var("submitorder")) {
    check_token("WHMCS.admin.default");
    $client = WHMCS\User\Client::find($userid);
    $qty = App::getFromRequest("qty");
    $calconly = App::getFromRequest("calconly");
    $addonQuantities = App::getFromRequest("paddonqty");
    $submittedAddons = App::getFromRequest("addons");
    $addons_radio = App::getFromRequest("addons_radio");
    $addonsRadioQuantities = App::getFromRequest("addons_quantity");
    if (!$client && !$calconly) {
        infoBox(AdminLang::trans("clients.invalidclient"), AdminLang::trans("clients.specifyclient"));
    } else {
        if (isset($client)) {
            getUsersLang($client->id);
        }
        $_SESSION["cart"] = [];
        $_SESSION["cart"]["paymentmethod"] = App::getFromRequest("paymentmethod");
        foreach ($pid as $k => $prodid) {
            if ($prodid) {
                $addons = [];
                if ($submittedAddons && is_array($submittedAddons)) {
                    foreach (array_keys($submittedAddons[$k]) as $addon) {
                        $quantity = 1;
                        if (!empty($addonQuantities[$k][$addon])) {
                            $quantity = (int) $addonQuantities[$k][$addon];
                        }
                        $addons[] = ["addonid" => $addon, "qty" => $quantity];
                    }
                }
                if (empty($addons)) {
                    $addons = [];
                }
                if ($addons_radio) {
                    foreach ($addons_radio[$k] as $addon_name => $addon_value) {
                        $quantity = 1;
                        if (!empty($addonsRadioQuantities[$k][$addon_name][$addon_value])) {
                            $quantity = (int) $addonsRadioQuantities[$k][$addon_name][$addon_value];
                        }
                        if (is_numeric($addon_value)) {
                            $addons[] = ["addonid" => $addon_value, "qty" => $quantity];
                        }
                    }
                }
                if (!$qty[$k]) {
                    $qty[$k] = 1;
                }
                $product = WHMCS\Product\Product::find($prodid);
                $cleanDomain = "";
                if (!empty($domain[$k])) {
                    try {
                        $cleanDomain = str_replace(["%", "\$", "^", "'", "#", "~", "&", "*", "(", ")", ",", "_", "£", "?", "!", "+", "=", ":", "{", "}", "[", "]", "|", "\\", "/", " ", "@"], "", $domain[$k]);
                        if ($cleanDomain === $domain[$k]) {
                            $domainParts = $domains->splitAndCleanDomainInput($domain[$k]);
                            $cleanDomain = $domains->fromPunycode($domainParts);
                        } else {
                            $cleanDomain = $domain[$k];
                        }
                    } catch (Exception $e) {
                        $cleanDomain = "";
                    }
                }
                $productarray = ["pid" => $prodid, "domain" => $cleanDomain, "strictDomain" => false, "billingcycle" => str_replace(["-", " "], "", strtolower($billingcycle[$k])), "server" => "", "configoptions" => $configoption[$k] ?? NULL, "customfields" => $customfield[$k] ?? NULL, "addons" => $addons];
                if (strlen($_POST["priceoverride"][$k])) {
                    $productarray["priceoverride"] = $_POST["priceoverride"][$k];
                }
                if ($product->allowMultipleQuantities === 2) {
                    $productarray["qty"] = $qty[$k];
                    $productarray["allowsQuantity"] = $product->allowMultipleQuantities;
                    $qty[$k] = 1;
                }
                for ($count = 1; $count <= $qty[$k]; $count++) {
                    $_SESSION["cart"]["products"][] = $productarray;
                }
            }
        }
        $validtlds = [];
        $result = select_query("tbldomainpricing", "extension", "");
        while ($data = mysql_fetch_array($result)) {
            $validtlds[] = $data[0];
        }
        $orderContainsInvalidTlds = false;
        $regaction = App::getFromRequest("regaction");
        $regperiod = App::getFromRequest("regperiod");
        $idnLanguage = App::getFromRequest("idnlanguage");
        $dnsmanagement = App::getFromRequest("dnsmanagement");
        $emailforwarding = App::getFromRequest("emailforwarding");
        $idprotection = App::getFromRequest("idprotection");
        $eppcode = App::getFromRequest("eppcode");
        $domainfield = App::getFromRequest("domainfield");
        foreach ($regaction as $k => $regact) {
            if ($regact && !empty($regdomain[$k])) {
                $domainParts = $domains->splitAndCleanDomainInput(WHMCS\Domain\Checker::adminProcessLabel($regdomain[$k]));
                $cleanDomain = $domains->fromPunycode($domainParts);
                $cleanParts = $domains->splitDomain($cleanDomain);
                if (in_array($cleanParts["tld"], $validtlds)) {
                    $domainArray = ["type" => $regact, "domain" => $cleanDomain, "regperiod" => $regperiod[$k], "idnLanguage" => $idnLanguage[$k] ?? NULL, "dnsmanagement" => $dnsmanagement[$k] ?? NULL, "emailforwarding" => $emailforwarding[$k] ?? NULL, "idprotection" => $idprotection[$k] ?? NULL, "eppcode" => $eppcode[$k], "fields" => $domainfield[$k] ?? NULL];
                    if (strlen($_POST["domainpriceoverride"][$k])) {
                        $domainArray["domainpriceoverride"] = $_POST["domainpriceoverride"][$k];
                    }
                    if (strlen($_POST["domainrenewoverride"][$k])) {
                        $domainArray["domainrenewoverride"] = $_POST["domainrenewoverride"][$k];
                    }
                    $_SESSION["cart"]["domains"][] = $domainArray;
                } else {
                    if (!empty($regdomain[$k])) {
                        $orderContainsInvalidTlds = true;
                    }
                }
            }
        }
        if ($promocode) {
            $promotionModel = WHMCS\Product\Promotion::find($promocode);
            if ($promotionModel) {
                $_SESSION["cart"]["promo"] = $promotionModel->code;
            }
        }
        $_SESSION["cart"]["orderconfdisabled"] = $adminorderconf ? false : true;
        $_SESSION["cart"]["geninvoicedisabled"] = $admingenerateinvoice ? false : true;
        if (!$adminsendinvoice) {
            $CONFIG["NoInvoiceEmailOnOrder"] = true;
        }
        $contactid = $whmcs->get_req_var("contactid");
        if ($contactid) {
            $_SESSION["cart"]["contact"] = $contactid;
        }
        if ($calconly) {
            ob_start();
            $ordervals = calcCartTotals($client, false, false);
            echo "<div class=\"ordersummarytitle\">" . $aInt->lang("orders", "orderSummary") . "</div>";
            if ($orderContainsInvalidTlds) {
                echo "<div class=\"alert alert-info text-center\" style=\"margin:15px 0;\">" . AdminLang::trans("domains.orderContainsInvalidTlds") . "</div>";
            }
            echo "<div id=\"ordersummary\">\n<table>\n";
            if (is_array($ordervals["products"])) {
                foreach ($ordervals["products"] as $cartprod) {
                    $quantity = "";
                    if ($cartprod["qty"]) {
                        $quantity = $cartprod["qty"] . " x ";
                    }
                    echo "<tr class=\"item\"><td colspan=\"2\"><div class=\"itemtitle\">" . $quantity . $cartprod["productinfo"]["groupname"] . " - " . $cartprod["productinfo"]["name"] . "</div>";
                    echo $aInt->lang("billingcycles", $cartprod["billingcycle"]);
                    if ($cartprod["domain"]) {
                        echo " - " . $cartprod["domain"];
                    }
                    echo "<div class=\"itempricing\">";
                    if (isset($cartprod["priceoverride"]) && $cartprod["priceoverride"]) {
                        echo formatCurrency($cartprod["priceoverride"]) . "*";
                    } else {
                        echo $cartprod["pricingtext"];
                    }
                    echo "</div>";
                    if ($cartprod["configoptions"]) {
                        foreach ($cartprod["configoptions"] as $cartcoption) {
                            if (!empty($cartcoption["optionname"]) && empty($cartcoption["value"])) {
                                $cartcoption["value"] = $cartcoption["optionname"];
                            }
                            if ($cartcoption["type"] == "1" || $cartcoption["type"] == "2") {
                                echo "<br />&nbsp;&raquo;&nbsp;" . $cartcoption["name"] . ": " . $cartcoption["value"];
                            } else {
                                if ($cartcoption["type"] == "3") {
                                    echo "<br />&nbsp;&raquo;&nbsp;" . $cartcoption["name"] . ": ";
                                    if ($cartcoption["qty"]) {
                                        echo $aInt->lang("global", "yes");
                                    } else {
                                        echo $aInt->lang("global", "no");
                                    }
                                } else {
                                    if ($cartcoption["type"] == "4") {
                                        echo "<br />&nbsp;&raquo;&nbsp;" . $cartcoption["name"] . ": " . $cartcoption["qty"] . " x " . $cartcoption["option"];
                                    }
                                }
                            }
                        }
                    }
                    echo "</td></tr>";
                    if ($cartprod["addons"]) {
                        foreach ($cartprod["addons"] as $addondata) {
                            if (1 < $addondata["qty"]) {
                                $addondata["name"] = $addondata["qty"] . " x " . $addondata["name"];
                            }
                            echo "<tr class=\"item\">\n    <td colspan=\"2\">\n        <div class=\"itemtitle\">\n            " . $addondata["name"] . "\n        </div>\n        <div class=\"itempricing\">\n            " . $addondata["pricingtext"] . "\n        </div>\n    </td>\n</tr>";
                        }
                    }
                }
            }
            if (is_array($ordervals["domains"])) {
                foreach ($ordervals["domains"] as $cartdom) {
                    echo "<tr class=\"item\"><td colspan=\"2\"><div class=\"itemtitle\">" . $aInt->lang("fields", "domain") . " " . $aInt->lang("domains", $cartdom["type"]) . "</div>" . $cartdom["domain"] . " (" . $cartdom["regperiod"] . " " . $aInt->lang("domains", "years") . ")";
                    if ($cartdom["dnsmanagement"]) {
                        echo "<br />&nbsp;&raquo;&nbsp;" . $aInt->lang("domains", "dnsmanagement");
                    }
                    if ($cartdom["emailforwarding"]) {
                        echo "<br />&nbsp;&raquo;&nbsp;" . $aInt->lang("domains", "emailforwarding");
                    }
                    if ($cartdom["idprotection"]) {
                        echo "<br />&nbsp;&raquo;&nbsp;" . $aInt->lang("domains", "idprotection");
                    }
                    echo "<div class=\"itempricing\">";
                    if (isset($cartdom["priceoverride"]) && $cartdom["priceoverride"]) {
                        echo formatCurrency($cartdom["priceoverride"]) . "*";
                    } else {
                        echo $cartdom["price"];
                    }
                    echo "</div>";
                }
            }
            $cartitems = 0;
            foreach (["products", "addons", "domains", "renewals"] as $k) {
                if (array_key_exists($k, $ordervals)) {
                    $cartitems += count($ordervals[$k]);
                }
            }
            if (!$cartitems) {
                echo "<tr class=\"item\"><td colspan=\"2\"><div class=\"itemtitle\" align=\"center\">" . $aInt->lang("orders", "noItemsSelected") . "</div></td></tr>";
            }
            echo "<tr class=\"subtotal\"><td>" . $aInt->lang("fields", "subtotal") . "</td><td class=\"alnright\">" . $ordervals["subtotal"] . "</td></tr>";
            if ($ordervals["promotype"]) {
                echo "<tr class=\"promo\"><td>" . $aInt->lang("orders", "promoDiscount") . "</td><td class=\"alnright promo-discount\">" . $ordervals["discount"] . "</td></tr>";
            }
            if ($ordervals["taxrate"]) {
                echo "<tr class=\"tax\" id=\"tax1\"><td>" . $ordervals["taxname"] . " @ " . $ordervals["taxrate"] . "%</td><td class=\"alnright\">" . $ordervals["taxtotal"] . "</td></tr>";
            }
            if ($ordervals["taxrate2"]) {
                echo "<tr class=\"tax\" id=\"tax2\"><td>" . $ordervals["taxname2"] . " @ " . $ordervals["taxrate2"] . "%</td><td class=\"alnright\">" . $ordervals["taxtotal2"] . "</td></tr>";
            }
            echo "<tr class=\"total\"><td width=\"140\">" . $aInt->lang("fields", "total") . "</td><td class=\"alnright\">" . $ordervals["total"] . "</td></tr>";
            if ($ordervals["totalrecurringmonthly"] || $ordervals["totalrecurringquarterly"] || $ordervals["totalrecurringsemiannually"] || $ordervals["totalrecurringannually"] || $ordervals["totalrecurringbiennially"] || $ordervals["totalrecurringtriennially"]) {
                echo "<tr class=\"recurring\"><td>Recurring</td><td class=\"alnright\">";
                if ($ordervals["totalrecurringmonthly"]) {
                    echo "" . $ordervals["totalrecurringmonthly"] . " Monthly<br />";
                }
                if ($ordervals["totalrecurringquarterly"]) {
                    echo "" . $ordervals["totalrecurringquarterly"] . " Quarterly<br />";
                }
                if ($ordervals["totalrecurringsemiannually"]) {
                    echo "" . $ordervals["totalrecurringsemiannually"] . " Semi-Annually<br />";
                }
                if ($ordervals["totalrecurringannually"]) {
                    echo "" . $ordervals["totalrecurringannually"] . " Annually<br />";
                }
                if ($ordervals["totalrecurringbiennially"]) {
                    echo "" . $ordervals["totalrecurringbiennially"] . " Biennially<br />";
                }
                if ($ordervals["totalrecurringtriennially"]) {
                    echo "" . $ordervals["totalrecurringtriennially"] . " Triennially<br />";
                }
                echo "</td></tr>";
            }
            $amountOfCredit = 0;
            $canUseCreditOnCheckout = false;
            if (isset($client)) {
                $amountOfCredit = $client->credit;
            }
            if (0 < $ordervals["total"]->toNumeric() && 0 < $amountOfCredit) {
                $creditBalance = new WHMCS\View\Formatter\Price($amountOfCredit, $currency);
                $checked = App::isInRequest("applycredit") ? (bool) App::getFromRequest("applycredit") : true;
                if ($ordervals["total"]->toNumeric() <= $creditBalance->toNumeric()) {
                    $applyCredit = AdminLang::trans("orders.applyCreditAmountNoFurtherPayment", [":amount" => $ordervals["total"]]);
                } else {
                    $applyCredit = AdminLang::trans("orders.applyCreditAmount", [":amount" => $creditBalance]);
                }
                echo "<tr class=\"apply-credit\"><td colspan=\"2\"><div class=\"apply-credit-container\">\n<p>" . AdminLang::trans("orders.availableCreditBalance", [":amount" => $creditBalance]) . "</p>\n<label class=\"radio\">\n<input type=\"radio\" name=\"applycredit\" value=\"1\" " . ($checked ? "checked=\"checked\"" : "") . ">\n" . $applyCredit . "\n</label>\n<label class=\"radio\">\n<input id=\"skipCreditOnCheckout\" type=\"radio\" name=\"applycredit\" value=\"0\" " . (!$checked ? "checked=\"checked\"" : "") . ">\n" . AdminLang::trans("orders.applyCreditSkip", [":amount" => $creditBalance]) . "\n</label>\n</div></td></tr>";
            }
            echo "</table>\n</div>";
            $content = ob_get_contents();
            ob_end_clean();
            $aInt->jsonResponse(["body" => $content]);
        }
        $cartitems = count($_SESSION["cart"]["products"] ?? []) + count($_SESSION["cart"]["addons"] ?? []) + count($_SESSION["cart"]["domains"] ?? []) + count($_SESSION["cart"]["renewals"] ?? []);
        if (!$cartitems) {
            redir("noselections=1");
        }
        calcCartTotals($client, true, false);
        if ($orderstatus == "Active") {
            update_query("tblorders", ["status" => "Active"], ["id" => $_SESSION["orderdetails"]["OrderID"]]);
            if (is_array($_SESSION["orderdetails"]["Products"])) {
                foreach ($_SESSION["orderdetails"]["Products"] as $productid) {
                    update_query("tblhosting", ["domainstatus" => "Active"], ["id" => $productid]);
                }
            }
            if (is_array($_SESSION["orderdetails"]["Domains"])) {
                foreach ($_SESSION["orderdetails"]["Domains"] as $domainid) {
                    update_query("tbldomains", ["status" => "Active"], ["id" => $domainid]);
                }
            }
        }
        getUsersLang(0);
        redir("action=view&id=" . $_SESSION["orderdetails"]["OrderID"], "orders.php");
    }
}
WHMCS\Session::release();
$regperiods = $regperiodss = "";
for ($regperiod = 1; $regperiod <= 10; $regperiod++) {
    $regperiods .= "<option value=\"" . $regperiod . "\">" . $regperiod . " " . $aInt->lang("domains", "year" . $regperiodss) . "</option>";
    $regperiodss = "s";
}
$jquerycode = "\n\$(\"#orderfrm\").submit(function(e) {\n    if (\$(this).data(\"validated\") !== true) {\n        e.preventDefault();\n        WHMCS.http.jqClient.jsonPost({\n            url: \"ordersadd.php\",\n            data: \"action=validateOrder&\" + \$(this).serialize(),\n            success: function(data) {\n                if (data.success == true) {\n                    submitOrder();\n                } else {\n                    \$(\"#modalvalidationResults\").modal(\"show\");\n                }\n            }\n        });\n    }\n});\n\n\$(function(){\n    var prodtemplate = \$(\"#products .product:first\").clone();\n    var productsCount = 0;\n    window.addProduct = function(){\n        productsCount++;\n        var order = prodtemplate.clone().find(\"*\").each(function(){\n            var newId = this.id.substring(0, this.id.length-1) + productsCount;\n\n            \$(this).prev().attr(\"for\", newId); // update label for\n            this.id = newId; // update id\n\n        }).end()\n        .attr(\"id\", \"ord\" + productsCount)\n        .appendTo(\"#products\");\n        return false;\n    }\n    \$(\".addproduct\").click(addProduct);\n\n    \$(\".adddomain\").click(function() {\n        var domainConfigCount = \$(\".tbl-domain-config\").length;\n        \$(\"#domains .tbl-domain-config:first\")\n            .clone()\n            .attr(\"domain-counter\", domainConfigCount)\n            .find(\".domain-reg-action\")\n            .attr(\"name\", \"regaction[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".required-field-indication\")\n            .hide()\n            .end()\n            .find(\".invalid-tld\")\n            .hide()\n            .end()\n            .find(\".domain-reg-dnsmanagement\")\n            .attr(\"name\", \"dnsmanagement[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".domain-reg-emailforwarding\")\n            .attr(\"name\", \"emailforwarding[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".domain-reg-idprotection\")\n            .attr(\"name\", \"idprotection[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".domain-reg-priceoverride\")\n            .attr(\"name\", \"domainpriceoverride[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".domain-reg-renewoverride\")\n            .attr(\"name\", \"domainrenewoverride[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".idn-language\")\n            .attr(\"name\", \"idnlanguage[\" + domainConfigCount + \"]\")\n            .end()\n            .find(\".idn-language-selector\")\n            .addClass(\"hidden\")\n            .end()\n            .find(\".domain-addt-fields\")\n            .remove()\n            .end()\n            .find(\".input-reg-domain\")\n            .val(\"\")\n            .end()\n            .find(\"input:checkbox\").removeAttr(\"checked\").end()\n            .find(\"input:radio\").prop(\"checked\", false).end()\n            .find(\"input:radio:first\").click().end()\n            .appendTo(\"#domains\")\n            .find(\"*\")\n            .each(function() {\n                var id = this.id || \"\";\n                if (id) {\n                    this.id = id.substring(0, id.length - 1) + (domainConfigCount);\n                }\n            });\n        return false;\n    });\n\n    \$(\".input-domain\").keyup(function() {\n      \$(\".input-reg-domain:first\").val(\$(\".input-domain\").val());\n    });\n\n});\n\n\$(\"#selectUserid\").change(function() {\n    \$(\"#linkAddContact\").attr(\"href\", \"clientscontacts.php?userid=\" + \$(this).val() + \"&contactid=addnew\");\n    loadDomainContactOptions();\n});\n\n";
$jscode = "\n\nvar summaryUpdateTimeoutId = 0;\nvar domainUpdateTimeoutId = 0;\n\nfunction loadDomainContactOptions() {\n    var hasDomainReg = false;\n    \$(\".domain-reg-action\").filter(\":checked\").each(function() {\n        if (this.value == \"register\" || this.value == \"transfer\") {\n            hasDomainReg = true;\n        }\n    });\n    if (!hasDomainReg) {\n        \$(\"#domainContactContainer\").hide();\n        return false;\n    }\n    \$.getJSON(\"ordersadd.php\", \"action=getcontacts&userid=\" + \$(\"#selectUserid\").val(), function(data){\n        var numberOfElements = data.length;\n        if (numberOfElements === 0) {\n            \$(\"#domainContactContainer\").hide();\n        } else {\n            \$(\"#inputContactID\").empty();\n            \$(\"#inputContactID\").append(\"<option value=\\\"0\\\">" . $aInt->lang("domains", "domaincontactuseprimary", 1) . "</option>\");\n            \$.each(data, function(key, value) {\n               \$(\"#inputContactID\").append(\"<option value=\\\"\" + key + \"\\\">\" + value + \"</option>\");\n            });\n            \$(\"#domainContactContainer\").show();\n        }\n    });\n}\nfunction loadproductoptions(piddd) {\n    var ord = piddd.id.substring(3);\n    var pid = piddd.value;\n    var billingcycle = \$(\"#billingcycle\" + ord).val();\n    if (pid==0) {\n        \$(\"#productconfigoptions\"+ord).html(\"\");\n        \$(\"#addonsrow\"+ord).empty();\n        updatesummary();\n    } else {\n    \$(\"#productconfigoptions\"+ord).html(\"<p align=\\\"center\\\">" . $aInt->lang("global", "loading") . "<br>" . addslashes(trim(DI::make("asset")->imgTag("loading.gif"))) . "</p>\");\n    WHMCS.http.jqClient.post(\"ordersadd.php\", { action: \"getconfigoptions\", userid: jQuery(\"#selectUserid\").val(), pid: pid, cycle: billingcycle, orderid: ord, token: \"" . generate_token("plain") . "\" },\n    function(data){\n        if (data.addons) {\n            \$(\"#addonsrow\"+ord).show();\n            \$(\"#addonscont\"+ord).html(data.addons);\n        } else {\n            \$(\"#addonsrow\"+ord).empty();\n        }\n        \$(\"#productconfigoptions\"+ord).html(data.options);\n        updatesummary();\n    },\"json\");\n    }\n}\nfunction loaddomainoptions(domainRef) {\n    var regtype = \$(domainRef).filter(\":checked\").val();\n    var tblContainer = \$(domainRef).closest(\".tbl-domain-config\");\n    var fillDomain = false;\n    var domainField = \$(tblContainer).find(\".input-reg-domain\");\n    if (regtype == \"register\") {\n        \$(\"tr\", tblContainer).not(\".domain-eppcode\").css(\"display\", \"\");\n        \$(\"tr.domain-eppcode\", tblContainer).css(\"display\", \"none\");\n        fillDomain = true;\n    } else if (regtype == \"transfer\") {\n        \$(\"tr\", tblContainer).css(\"display\", \"\");\n        fillDomain = true;\n    } else {\n        \$(\"tr\", tblContainer).not(\"tr:first\").css(\"display\", \"none\");\n    }\n\n    if (fillDomain) {\n        if (\$(domainField).val() == \"\") {\n            var productDomain = \$(\"[name=\\\"domain[]\\\"]\").val();\n\n            if (productDomain != \"\") {\n                var numExistingEntries = \$(\".input-reg-domain\")\n                    .filter(function() {\n                        return \$(this).val() == productDomain;\n                    }).length;\n\n                if (numExistingEntries == 0) {\n                    \$(domainField).val(productDomain);\n                }\n            }\n        }\n    }\n\n    loaddomfields(domainRef);\n    loadDomainContactOptions();\n}\nfunction updatesummary() {\n    if (summaryUpdateTimeoutId) {\n        clearTimeout(summaryUpdateTimeoutId);\n        summaryUpdateTimeoutId = 0;\n    }\n\n    summaryUpdateTimeoutId = setTimeout(function() {\n        var applyCredit = \$(\"input[name='applycredit']:checked\").val();\n        if (typeof applyCredit === \"undefined\") {\n            applyCredit = 1;\n        }\n        WHMCS.http.jqClient.post(\"ordersadd.php\", \"submitorder=1&calconly=1&applycredit=\"+applyCredit+\"&\"+\$(\"#orderfrm\").serialize(),\n        function(data){\n            \$(\"#ordersumm\").html(data.body);\n        });\n    }, 300);\n}\nfunction loaddomfields(domainRef) {\n    var tblContainer = \$(domainRef).closest(\".tbl-domain-config\");\n    var domainName = \$(\".input-reg-domain\", tblContainer).val();\n    var domainCounter = \$(tblContainer).attr(\"domain-counter\");\n    if (domainName.length >= 5 && domainContainsAPeriod(domainName)) {\n        WHMCS.http.jqClient.post(\"ordersadd.php\", { action: \"getdomainaddlfields\", domain: domainName, domainnum: domainCounter, token: \"" . generate_token("plain") . "\" },\n        function(data) {\n            \$(\".domain-addt-fields\", tblContainer).remove();\n            \$(tblContainer).append(data.additionalFields);\n            if (data.additionalFields) {\n                \$(\".required-field-indication\", tblContainer).hide().removeClass(\"hidden\").fadeIn();\n            } else {\n                \$(\".required-field-indication\", tblContainer).fadeOut();\n            }\n            if (data.idn) {\n                \$(tblContainer).find(\".idn-language-selector\").removeClass(\"hidden\")\n                    .find(\"select\").prop(\"required\", true);\n            } else {\n                \$(tblContainer).find(\".idn-language-selector\").addClass(\"hidden\")\n                    .find(\"select\").prop(\"required\", false);\n            }\n\n            if (data.invalidTld) {\n                \$(\".invalid-tld\", tblContainer).hide().removeClass(\"hidden\").fadeIn();\n            } else {\n                \$(\".invalid-tld\", tblContainer).fadeOut();\n            }\n        }, \"json\");\n    }\n}\nfunction handleDomainRegInput(currentDomain) {\n    var inputDomain = \$(currentDomain).val();\n\n    if (domainUpdateTimeoutId) {\n        clearTimeout(domainUpdateTimeoutId);\n        domainUpdateTimeoutId = 0;\n    }\n\n    if (domainContainsAPeriod(inputDomain)) {\n        domainUpdateTimeoutId = setTimeout(function() {\n            loaddomfields(currentDomain);\n        }, 300);\n\n        updatesummary();\n    }\n\n}\nfunction handleProductDomainInput(currentDomain) {\n    var domain = jQuery(currentDomain);\n    var inputDomain = domain.val();\n\n    var domainEntries = \$(\".input-reg-domain:visible\");\n\n    if (\$(domainEntries).length == 1) {\n        if (!\$(domainEntries).prop(\"data-manual-input\") || (\$(domainEntries).val().trim() == \"\")) {\n            \$(domainEntries).val(inputDomain);\n        }\n\n        handleDomainRegInput(domainEntries);\n    }\n\n    if (domainContainsAPeriod(inputDomain)) {\n        updatesummary();\n    }\n    \n    if (inputDomain) {\n        validateProductsDomain(inputDomain, domain.parent().find(\".domain-feedback\"))\n    }\n}\n\nfunction validateProductsDomain(value, container)\n{\n    WHMCS.http.jqClient.post(\n        \"ordersadd.php\", \n        { \n            action: \"validateProductsDomain\",\n             domain: value, \n             token: \"" . generate_token("plain") . "\" \n         }\n    )\n    .done(function(data){\n        if (data.valid === false) {\n            container.removeClass(\"hidden\").show();\n        } else {\n            container.hide();\n        }\n    });\n}\n\nfunction domainContainsAPeriod(domain) {\n    if (domain.indexOf(\".\") > -1 ) {\n        return true;\n    } else {\n        return false;\n    }\n}\nfunction submitOrder() {\n    \$(\"#orderfrm\").data(\"validated\", true).submit();\n}\n";
ob_start();
if (!checkActiveGateway()) {
    $aInt->gracefulExit(AdminLang::trans("gateways.nonesetup", [":paymentGatewayURI" => routePath("admin-apps-category", "payments")]));
}
if ($userid && empty($paymentmethod)) {
    $paymentmethod = getClientsPaymentMethod($userid);
}
if ($whmcs->get_req_var("noselections")) {
    infoBox($aInt->lang("global", "validationerror"), $aInt->lang("orders", "noselections"));
}
echo $infobox;
echo "\n<form method=\"post\" action=\"";
echo $_SERVER["PHP_SELF"];
echo "\" id=\"orderfrm\">\n<input type=\"hidden\" name=\"submitorder\" value=\"true\" />\n\n<div class=\"row\">\n    <div class=\"col-md-8\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"130\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "client");
echo "</td><td class=\"fieldarea\"><div style=\"max-width:400px\" class=\"form-field-width-container\">";
echo $aInt->clientsDropDown($userid);
echo "</div></td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "paymentmethod");
echo "</td><td class=\"fieldarea\">";
echo paymentMethodsSelection();
echo "</td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
echo AdminLang::trans("fields.promocode");
echo "    </td>\n    <td class=\"fieldarea\">\n        <div style=\"max-width:340px;width:100%;float:left;margin-right:5px;\" class=\"form-field-width-container\">\n        ";
$form = new WHMCS\Form();
echo $form->dropdownWithOptGroups("promocode", preparePromotionDataForSelection(WHMCS\Product\Promotion::getAllForSelect()), App::getFromRequest("promocode"), "updatesummary()", false, true, 1, "promodd", "form-control selectize-promo");
echo "</div>";
if (checkPermission("Use Any Promotion Code on Order", true) && checkPermission("Create/Edit Promotions", true)) {
    $disabled = "data-toggle='modal' data-target='#modalCreatePromo' class='btn btn-default btn-sm'";
} else {
    $disabled = "data-toggle='tooltip' data-placement='auto right' class='btn btn-default btn-sm disabled' title='" . $aInt->lang("orders", "createPromoNeedPerms") . "'";
}
echo "<a href='#' type='button' id='createPromoCode' " . $disabled . " style='float:left;'><i class='fas fa-plus fa-fw'></i> " . $aInt->lang("orders", "createpromo") . "</a>";
echo "        <div style=\"clear:both;\"></div>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("orders", "status");
echo "</td><td class=\"fieldarea\"><select name=\"orderstatus\" class=\"form-control select-inline\">\n<option value=\"Pending\">";
echo $aInt->lang("status", "pending");
echo "</option>\n<option value=\"Active\">";
echo $aInt->lang("status", "active");
echo "</option>\n</select></td></tr>\n<tr><td width=\"130\" class=\"fieldlabel\"></td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"adminorderconf\" checked /> ";
echo $aInt->lang("orders", "orderconfirmation");
echo "</label> <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"admingenerateinvoice\" checked /> ";
echo $aInt->lang("orders", "geninvoice");
echo "</label> <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"adminsendinvoice\" checked /> ";
echo $aInt->lang("global", "sendemail");
echo "</label></td></tr>\n</table>\n\n<div id=\"products\">\n<div id=\"ord0\" class=\"product\">\n\n<p><b>";
echo $aInt->lang("fields", "product");
echo "</b></p>\n<style>\n    .domain-feedback {\n        float: right;\n        color: red;\n        margin: 5px;\n    }\n</style>\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"130\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "product");
echo "</td><td class=\"fieldarea\"><select name=\"pid[]\" id=\"pid0\" class=\"form-control select-inline\" onchange=\"loadproductoptions(this)\">";
echo $aInt->productDropDown(0, true);
echo "</select></td></tr>\n    <tr>\n        <td class=\"fieldlabel\">";
echo $aInt->lang("fields", "domain");
echo "</td>\n        <td class=\"fieldarea\">\n            <div class=\"domain-feedback hidden\">\n                <i class=\"far fa-exclamation-triangle\"></i>\n                ";
echo AdminLang::trans("domains.notadomain");
echo "            </div>\n            <input type=\"text\" name=\"domain[]\"\n                class=\"form-control input-300\"\n                onkeyup=\"handleProductDomainInput(this)\"\n                class=\"input-domain\"/> <span id=\"whoisresult0\"></span>\n        </td>\n    </tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "billingcycle");
echo "</td><td class=\"fieldarea\">";
echo $aInt->cyclesDropDown($billingcycle[0], "", "", "billingcycle[]", "updatesummary();loadproductoptions(jQuery('#pid' + this.id.substring(12))[0]);return false;", "billingcycle0");
echo "</td></tr>\n<tr id=\"addonsrow0\" style=\"display:none;\"><td class=\"fieldlabel\">";
echo $aInt->lang("addons", "title");
echo "</td><td class=\"fieldarea\" id=\"addonscont0\"></td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "quantity");
echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"qty[]\" value=\"1\" class=\"form-control input-50\" onkeyup=\"updatesummary()\" /></td></tr>\n<tr><td class=\"fieldlabel\">";
echo $aInt->lang("fields", "priceoverride");
echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"priceoverride[]\" class=\"form-control input-100 input-inline\" onkeyup=\"updatesummary()\" /> ";
echo $aInt->lang("orders", "priceoverridedesc");
echo "</td></tr>\n</table>\n\n<div id=\"productconfigoptions0\"></div>\n\n</div>\n</div>\n\n<p style=\"padding:10px 0 5px 20px;\"><a href=\"#\" class=\"btn btn-default btn-sm addproduct\"><img src=\"images/icons/add.png\" border=\"0\" align=\"absmiddle\" /> ";
echo $aInt->lang("orders", "anotherproduct");
echo "</a></p>\n\n<p><b>";
echo $aInt->lang("domains", "domainreg");
echo "</b></p>\n\n<div id=\"domains\">\n\n<table class=\"form tbl-domain-config\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"130\" class=\"fieldlabel\">";
echo $aInt->lang("domains", "regtype");
echo "</td>\n        <td class=\"fieldarea\">\n            <label class=\"radio-inline\"><input type=\"radio\" name=\"regaction[0]\" value=\"\" class=\"domain-reg-action\" id=\"inputDomainRegActionNone0\" onclick=\"loaddomainoptions(this);updatesummary()\" checked /> ";
echo $aInt->lang("global", "none");
echo "</label>\n            <label class=\"radio-inline\"><input type=\"radio\" name=\"regaction[0]\" value=\"register\" class=\"domain-reg-action\" id=\"inputDomainRegActionRegister0\" onclick=\"loaddomainoptions(this);updatesummary()\" /> ";
echo $aInt->lang("domains", "register");
echo "</label>\n            <label class=\"radio-inline\"><input type=\"radio\" name=\"regaction[0]\" value=\"transfer\" class=\"domain-reg-action\" id=\"inputDomainRegActionTransfer0\" onclick=\"loaddomainoptions(this);updatesummary()\" /> ";
echo $aInt->lang("domains", "transfer");
echo "</label>\n        </td>\n    </tr>\n    <tr style=\"display:none;\">\n        <td class=\"fieldlabel\">";
echo $aInt->lang("fields", "domain");
echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"regdomain[]\" id=\"inputDomainRegDomain0\" class=\"form-control input-300 input-reg-domain\" data-manual-input=\"0\" onkeyup=\"\$(this).prop('data-manual-input', 1); handleDomainRegInput(this);\" /><span id=\"spanRequiredFields0\" class=\"required-field-indication text-warning hidden\">";
echo AdminLang::trans("orders.requiredFieldIndication");
echo "</span><span id=\"spanInvalidTld0\" class=\"invalid-tld text-danger hidden\">";
echo AdminLang::trans("domains.tldNotConfiguredForSale");
echo " ";
echo AdminLang::trans("global.pleaseCheckInput");
echo "</span></td>\n    </tr>\n    <tr style=\"display:none;\">\n        <td class=\"fieldlabel\">";
echo $aInt->lang("domains", "regperiod");
echo "</td>\n        <td class=\"fieldarea\">\n            <select name=\"regperiod[]\" id=\"inputDomainRegPeriod0\" class=\"form-control select-inline\" onchange=\"updatesummary()\">\n                ";
echo $regperiods;
echo "            </select>\n        </td>\n    </tr>\n    <tr class=\"domain-eppcode\" style=\"display:none;\">\n        <td class=\"fieldlabel\">";
echo $aInt->lang("domains", "eppcode");
echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"eppcode[]\" class=\"form-control input-150\" id=\"inputDomainRegEppCode0\" /></td>\n    </tr>\n    <tr style=\"display:none;\">\n        <td class=\"fieldlabel\">";
echo $aInt->lang("domains", "addons");
echo "</td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"dnsmanagement[0]\" class=\"domain-reg-dnsmanagement\" id=\"inputDomainRegDnsManagement0\" onclick=\"updatesummary()\" /> ";
echo $aInt->lang("domains", "dnsmanagement");
echo "</label>\n            <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"emailforwarding[0]\" class=\"domain-reg-emailforwarding\" id=\"inputDomainRegEmailForwarding0\" onclick=\"updatesummary()\" /> ";
echo $aInt->lang("domains", "emailforwarding");
echo "</label>\n            <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"idprotection[0]\" class=\"domain-reg-idprotection\" id=\"inputDomainRegIdProtection0\" onclick=\"updatesummary()\" /> ";
echo $aInt->lang("domains", "idprotection");
echo "</label>\n        </td>\n    </tr>\n    <tr style=\"display:none;\">\n        <td class=\"fieldlabel\">";
echo $aInt->lang("domains", "priceOverride");
echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"domainpriceoverride[0]\" id=\"inputDomainRegPriceOverride0\" class=\"form-control input-100 input-inline domain-reg-priceoverride\" data-manual-input=\"0\" oninput=\"updatesummary()\" /> ";
echo $aInt->lang("domains", "priceOverrideWarning");
echo "</td>\n    </tr>\n    <tr style=\"display:none;\">\n        <td class=\"fieldlabel\">";
echo $aInt->lang("domains", "renewOverride");
echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"domainrenewoverride[0]\" id=\"inputDomainRenewPriceOverride0\" class=\"form-control input-100 input-inline domain-reg-renewoverride\" data-manual-input=\"0\" oninput=\"updatesummary()\" /> ";
echo $aInt->lang("domains", "priceOverrideWarning");
echo "</td>\n    </tr>\n    <tr class=\"hidden idn-language-selector\">\n        <td class=\"fieldlabel\">\n            ";
echo AdminLang::trans("domains.idnLanguage");
echo "        </td>\n        <td class=\"fieldarea\">\n            <select name=\"idnlanguage[0]\" id=\"idnlanguage0\" class=\"form-control select-inline idn-language\">\n                <option value=\"\">";
echo AdminLang::trans("domains.selectIdnLanguage");
echo "</option>\n                ";
foreach (WHMCS\Domains\Idna::getLanguages() as $languageCode => $language) {
    echo "                    <option value=\"";
    echo $languageCode;
    echo "\">";
    echo $language;
    echo "</option>\n                ";
}
echo "            </select>\n        </td>\n    </tr>\n</table>\n\n</div>\n\n<p style=\"padding:10px 0 5px 20px;\"><a href=\"#\" class=\"btn btn-default btn-sm adddomain\"><img src=\"images/icons/add.png\" border=\"0\" align=\"absmiddle\" /> ";
echo $aInt->lang("orders", "anotherdomain");
echo "</a></p>\n\n<div id=\"domainContactContainer\" style=\"display:none;\">\n\n<p><b>";
echo $aInt->lang("domains", "domainregcontact");
echo "</b></p>\n\n<p>";
echo sprintf($aInt->lang("domains", "domainregcontactorderinfo"), "<a href=\"#\" id=\"linkAddContact\">", "</a>");
echo "</p>\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"130\" class=\"fieldlabel\">";
echo $aInt->lang("domains", "domaincontactchoose");
echo "</td><td class=\"fieldarea\"><select name=\"contactid\" id=\"inputContactID\"></select></td></tr>\n</table>\n\n</div>\n\n</div>\n    <div class=\"col-md-4\">\n\n<div id=\"ordersumm\"></div>\n\n<div class=\"ordersummarytitle\">\n    <input type=\"submit\" value=\"";
echo $aInt->lang("orders", "submit");
echo " &raquo;\" id=\"btnSubmit\" class=\"btn btn-primary\" style=\"font-size:20px;padding:12px 30px;\" />\n</div>\n\n\n    </div>\n</div>\n</form>\n\n<script> updatesummary(); </script>\n\n";
echo $aInt->modal("validationResults", AdminLang::trans("orders.errors.requiredDomainFieldsTitle"), "<p>" . AdminLang::trans("orders.errors.requiredDomainFieldsMsg") . "</p>" . "<p>" . AdminLang::trans("orders.errors.requiredDomainFieldsAction") . "</p>", [["title" => AdminLang::trans("global.cancel")], ["title" => AdminLang::trans("orders.submit"), "onclick" => "submitOrder()", "class" => "btn-primary"]]);
echo $aInt->modal("CreatePromo", $aInt->lang("orders", "createpromo"), "<form id=\"createpromofrm\">\n" . generate_token("form") . "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td class=\"fieldlabel\" width=\"140\">" . $aInt->lang("fields", "promocode") . "</td><td class=\"fieldarea\"><input type=\"text\" name=\"code\" id=\"promocode\" class=\"form-control input-200\" /></td></tr>\n<tr><td class=\"fieldlabel\">" . $aInt->lang("fields", "type") . "</td><td class=\"fieldarea\"><select name=\"type\" class=\"form-control select-inline\">\n<option value=\"Percentage\">" . $aInt->lang("promos", "percentage") . "</option>\n<option value=\"Fixed Amount\">" . $aInt->lang("promos", "fixedamount") . "</option>\n<option value=\"Price Override\">" . $aInt->lang("promos", "priceoverride") . "</option>\n<option value=\"Free Setup\">" . $aInt->lang("promos", "freesetup") . "</option>\n</select></td></tr>\n<tr><td class=\"fieldlabel\">" . $aInt->lang("promos", "value") . "</td><td class=\"fieldarea\"><input type=\"text\" name=\"pvalue\"  class=\"form-control input-100\" /></td></tr>\n<tr><td class=\"fieldlabel\">" . $aInt->lang("promos", "recurring") . "</td><td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"recurring\" id=\"recurring\" value=\"1\" /> " . $aInt->lang("promos", "recurenable") . "</label> <input type=\"text\" name=\"recurfor\" value=\"0\" class=\"form-control input-50 input-inline\" /> " . $aInt->lang("promos", "recurenable2") . "</td></tr>\n</table>\n<p>* " . $aInt->lang("orders", "createpromoinfo") . "</p>\n</form>", [["title" => $aInt->lang("global", "cancel")], ["title" => $aInt->lang("global", "ok"), "onclick" => "savePromo()", "class" => "btn-primary"]]);
$activePromosText = WHMCS\Input\Sanitize::escapeSingleQuotedString(AdminLang::trans("promos.activepromos"));
$jscode .= "function savePromo() {\n    WHMCS.http.jqClient.jsonPost({\n        url:  'ordersadd.php',\n        data: 'action=createpromo&' + jQuery('#createpromofrm').serialize(),\n        success: function(data) {\n            if (data.success.length && data.success === 'true') {\n                var promoSelectize = jQuery('#promodd')[0].selectize;\n                console.log(promoSelectize.options);\n                promoSelectize.addOption(\n                    {\n                        optgroup: '" . $activePromosText . "',\n                        name: data.promoData,\n                        value: data.promoId\n                    }\n                );\n                promoSelectize.addItem(data.promoId);\n                jQuery('#modalCreatePromo').find('input').val('').end()\n                    .find('input[name=\"recurfor\"]').val('0').end().modal(\"hide\");\n            } else {\n                alert(data.errorMessage);\n            }\n        }\n    });\n}";
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
