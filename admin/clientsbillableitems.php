<?php

define("ADMINAREA", true);
require "../init.php";
$requestOptionalArray = function ($requestVar) {
    $value = [];
    if (App::isInRequest($requestVar)) {
        $value = App::getFromRequest($requestVar);
        if (!is_array($value)) {
            throw new UnexpectedValueException($requestVar);
        }
    }
    return $value;
};
$action = App::getFromRequest("action");
$bitem = $requestOptionalArray("bitem");
if (!$action) {
    $reqperm = "View Billable Items";
} else {
    $reqperm = "Manage Billable Items";
}
$aInt = new WHMCS\Admin($reqperm);
$aInt->setClientsProfilePresets();
$aInt->requiredFiles(["invoicefunctions", "processinvoices", "gatewayfunctions", "clientfunctions"]);
$aInt->setHelpLink("Clients:Billable Items Tab");
$id = (int) App::getFromRequest("id");
$token = generate_token("plain");
if ($action == "getproddesc") {
    check_token("WHMCS.admin.default");
    $userId = (int) $whmcs->get_req_var("user");
    $productId = (int) $whmcs->get_req_var("id");
    $clientLanguage = NULL;
    if ($userId) {
        $clientLanguage = WHMCS\User\Client::find($userId, ["language"])->language ?: NULL;
    }
    echo strip_tags(WHMCS\Input\Sanitize::decode(WHMCS\Product\Product::getProductName($productId, "", $clientLanguage)));
    $description = strip_tags(WHMCS\Input\Sanitize::decode(WHMCS\Product\Product::getProductDescription($productId, "", $clientLanguage)));
    if ($description) {
        echo " - " . $description;
    }
    WHMCS\Terminus::getInstance()->doExit();
}
if ($action == "getprodprice") {
    check_token("WHMCS.admin.default");
    if (!$currency) {
        $currency = getCurrency()["id"];
    }
    $result = select_query("tblpricing", "", ["type" => "product", "currency" => $currency, "relid" => $id]);
    $data = mysql_fetch_array($result);
    if (0 < $data["monthly"]) {
        echo $data["monthly"];
    } else {
        if (0 < $data["quarterly"]) {
            echo $data["quarterly"];
        } else {
            if (0 < $data["semiannually"]) {
                echo $data["semiannually"];
            } else {
                if (0 < $data["annually"]) {
                    echo $data["annually"];
                } else {
                    if (0 < $data["biennially"]) {
                        echo $data["biennially"];
                    } else {
                        if (0 < $data["triennially"]) {
                            echo $data["triennially"];
                        } else {
                            echo "0.00";
                        }
                    }
                }
            }
        }
    }
    exit;
}
$userId = $aInt->valUserID($whmcs->get_req_var("userid"));
getUsersLang($userid);
$aInt->assertClientBoundary($userid);
if ($action == "addtime") {
    check_token("WHMCS.admin.default");
    for ($i = 0; $i <= 9; $i++) {
        if ($description[$i]) {
            if ($description[$i]) {
                $desc = $description[$i];
            }
            $amount = $rate[$i];
            if ($hours[$i] != 0) {
                $desc .= " - " . $hours[$i] . " " . Lang::trans("billableitemshours") . " @ " . $rate[$i] . "/" . Lang::trans("billableitemshour");
                $amount = $amount * $hours[$i];
            }
            insert_query("tblbillableitems", ["userid" => $userid, "description" => $desc, "hours" => $hours[$i], "amount" => $amount, "recur" => 0, "recurcycle" => 0, "recurfor" => 0, "invoiceaction" => 0, "duedate" => "now()"]);
        }
    }
    redir("userid=" . $userid);
}
if ($action == "save") {
    check_token("WHMCS.admin.default");
    $duedate = toMySQLDate($duedate);
    $qtyText = $_LANG["billableitemsquantity"];
    $eachText = $_LANG["billableitemseach"];
    $hourText = $_LANG["billableitemshour"];
    $hoursText = $_LANG["billableitemshours"];
    $roundedWarning = "";
    if ($hours != round($hours, 2)) {
        $roundedWarning = "&rounded=1";
        $hours = round($hours, 2);
    }
    if ($id) {
        if ($hours != 0) {
            if (preg_match("/ " . $hoursText . " @/", $description) || preg_match("/ " . $qtyText . " [0-9]+(\\.[0-9]{1,2})? @/", $description)) {
                $title = substr($description, 0, strrpos($description, " - "));
                if ($unit == 0) {
                    $description = sprintf("%s - %0.2f %s @ %0.2f/%s", $title, $hours, $hoursText, $amount, $hourText);
                } else {
                    $description = sprintf("%s - %s %0.2f @ %0.2f/%s", $title, $qtyText, $hours, $amount, $eachText);
                }
            }
            $amount = $amount * $hours;
        }
        WHMCS\Database\Capsule::table("tblbillableitems")->where("id", $id)->update(["userid" => $userid, "description" => $description, "hours" => $hours, "amount" => $amount, "recur" => $recur, "recurcycle" => $recurcycle, "recurfor" => $recurfor, "invoiceaction" => $invoiceaction, "unit" => $unit, "duedate" => $duedate, "invoicecount" => $invoicecount]);
    } else {
        if ($hours != 0) {
            if ($unit == 0) {
                $description .= sprintf(" - %0.2f %s @ %0.2f/%s", $hours, $hoursText, $amount, $hourText);
            } else {
                $description .= sprintf(" - %s %0.2f @ %0.2f/%s", $qtyText, $hours, $amount, $eachText);
            }
            $amount = $amount * $hours;
        }
        $id = WHMCS\Database\Capsule::table("tblbillableitems")->insertGetId(["userid" => $userid, "description" => $description, "hours" => $hours, "amount" => $amount, "recur" => $recur, "recurcycle" => $recurcycle, "recurfor" => $recurfor, "invoiceaction" => $invoiceaction, "unit" => $unit, "duedate" => $duedate]);
    }
    redir("userid=" . $userid . $roundedWarning);
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    delete_query("tblbillableitems", ["id" => $id, "userid" => $userId]);
    redir("userid=" . $userid);
}
$currency = getCurrency($userid);
ob_start();
if (!$action) {
    if (App::getFromRequest("invoice") && is_array($bitem)) {
        check_token("WHMCS.admin.default");
        checkPermission("Manage Billable Items");
        foreach ($bitem as $id => $v) {
            update_query("tblbillableitems", ["invoiceaction" => "1", "duedate" => "now()"], ["id" => $id]);
        }
        $invoiceid = createInvoices($userid);
        infoBox($aInt->lang("invoices", "gencomplete"), $aInt->lang("billableitems", "itemsinvoiced") . " <a href=\"invoices.php?action=edit&id=" . $invoiceid . "\" target=\"_blank\">" . $aInt->lang("fields", "invoicenum") . $invoiceid . "</a>");
        echo $infobox;
    }
    if (App::getFromRequest("delete") && is_array($bitem)) {
        check_token("WHMCS.admin.default");
        checkPermission("Manage Billable Items");
        foreach ($bitem as $id => $v) {
            delete_query("tblbillableitems", ["id" => $id]);
        }
        infoBox($aInt->lang("billableitems", "itemsdeleted"), $aInt->lang("billableitems", "itemsdeleteddesc"));
        echo $infobox;
    }
    $aInt->deleteJSConfirm("doDelete", "billableitems", "itemsdeletequestion", "clientsbillableitems.php?userid=" . $userid . "&action=delete&id=");
    $result = select_query("tblbillableitems", "COUNT(id),SUM(amount)", ["userid" => $userid, "invoicecount" => "0"]);
    $data = mysql_fetch_array($result);
    $unbilledcount = $data[0];
    $unbilledamount = formatCurrency($data[1]);
    echo " <div class=\"context-btn-container\">\n    <button type=\"button\" class=\"btn btn-default\" onClick=\"window.location='clientsbillableitems.php?userid=";
    echo $userid;
    echo "&action=timebilling'\">\n        ";
    echo $aInt->lang("billableitems", "addtimebilling");
    echo "    </button>\n    <button type=\"button\" class=\"btn btn-primary\" onClick=\"window.location='clientsbillableitems.php?userid=";
    echo $userid;
    echo "&action=manage'\">\n        <i class=\"fas fa-plus\"></i>\n        ";
    echo $aInt->lang("billableitems", "additem");
    echo "    </button>\n</div>\n";
    if (App::isInRequest("rounded")) {
        echo infoBox("Billable Item Saved", "Hours/Qty values are rounded to two decimal places.");
    }
    echo "<h2>";
    echo $aInt->lang("billableitems", "uninvoiced");
    echo " - <span class=\"textred\">";
    echo $unbilledamount;
    echo "</span> (";
    echo $unbilledcount;
    echo ")</h2>\n";
    $aInt->sortableTableInit("nopagination");
    $result = select_query("tblbillableitems", "COUNT(*)", ["userid" => $userid, "invoicecount" => "0"]);
    $data = mysql_fetch_array($result);
    $numrows = $data[0];
    $tabledata = [];
    $result = select_query("tblbillableitems", "", ["userid" => $userid, "invoicecount" => "0"]);
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $label = $data["label"];
        $description = $data["description"];
        $hours = $data["hours"];
        $amount = $data["amount"];
        $invoiceaction = $data["invoiceaction"];
        $invoicecount = $data["invoicecount"];
        $amount = formatCurrency($amount);
        if ($invoiceaction == "0") {
            $invoiceaction = $aInt->lang("billableitems", "dontinvoice");
        } else {
            if ($invoiceaction == "1") {
                $invoiceaction = $aInt->lang("billableitems", "nextcronrun");
            } else {
                if ($invoiceaction == "2") {
                    $invoiceaction = $aInt->lang("billableitems", "nextinvoice");
                } else {
                    if ($invoiceaction == "3") {
                        $invoiceaction = $aInt->lang("billableitems", "invoiceduedate");
                    } else {
                        if ($invoiceaction == "4") {
                            $invoiceaction = $aInt->lang("billableitems", "recurringcycle");
                        }
                    }
                }
            }
        }
        $managelink = "<a href=\"clientsbillableitems.php?userid=" . $userid . "&action=manage&id=" . $id . "\">";
        $tabledata[] = ["<input type=\"checkbox\" name=\"bitem[" . $id . "]\" class=\"checkall\" />", $managelink . $id . "</a>", $managelink . $description . "</a>", $hours, $amount, $invoiceaction, $managelink . "<img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $id . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    $tableformurl = $_SERVER["PHP_SELF"] . "?userid=" . $userid;
    $tableformbuttons = "<input type=\"submit\" name=\"invoice\" value=\"" . $aInt->lang("billableitems", "invoiceselected") . "\" class=\"btn btn-default\" onclick=\"return confirm('" . $aInt->lang("billableitems", "invoiceselectedconfirm", "1") . "')\" /> <input type=\"submit\" name=\"delete\" value=\"" . $aInt->lang("global", "delete") . "\" class=\"btn btn-danger\" onclick=\"return confirm('" . $aInt->lang("global", "deleteconfirm", "1") . "')\" />";
    echo $aInt->sortableTable(["checkall", AdminLang::trans("fields.id"), AdminLang::trans("fields.description"), AdminLang::trans("fields.hours"), AdminLang::trans("fields.amount"), AdminLang::trans("billableitems.invoiceaction"), "", ""], $tabledata, $tableformurl, $tableformbuttons);
    echo "<h2>" . $aInt->lang("billableitems", "invoiced") . "</h2>";
    $aInt->sortableTableInit("id", "DESC");
    $result = select_query("tblbillableitems", "COUNT(*)", ["userid" => $userid, "invoicecount" => ["sqltype" => ">", "value" => "0"]]);
    $data = mysql_fetch_array($result);
    $numrows = $data[0];
    $tabledata = [];
    $result = select_query("tblbillableitems", "", ["userid" => $userid, "invoicecount" => ["sqltype" => ">", "value" => "0"]], $orderby, $order, $page * $limit . "," . $limit);
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $label = $data["label"];
        $description = $data["description"];
        $hours = $data["hours"];
        $amount = $data["amount"];
        $invoiceaction = $data["invoiceaction"];
        $invoicecount = $data["invoicecount"];
        $amount = formatCurrency($amount);
        if ($invoiceaction == "0") {
            $invoiceaction = $aInt->lang("billableitems", "dontinvoice");
        } else {
            if ($invoiceaction == "1") {
                $invoiceaction = $aInt->lang("billableitems", "nextcronrun");
            } else {
                if ($invoiceaction == "2") {
                    $invoiceaction = $aInt->lang("billableitems", "nextinvoice");
                } else {
                    if ($invoiceaction == "3") {
                        $invoiceaction = $aInt->lang("billableitems", "invoiceduedate");
                    } else {
                        if ($invoiceaction == "4") {
                            $invoiceaction = $aInt->lang("billableitems", "recurringcycle");
                        }
                    }
                }
            }
        }
        $managelink = "<a href=\"clientsbillableitems.php?userid=" . $userid . "&action=manage&id=" . $id . "\">";
        $invoicesnumbers = [];
        $invoiceData = WHMCS\Database\Capsule::table("tblinvoiceitems")->leftJoin("tblinvoices", "tblinvoiceitems.invoiceid", "=", "tblinvoices.id")->where("type", "=", "Item")->where("relid", "=", $id)->orderBy("tblinvoiceitems.invoiceid", "ASC")->get(["tblinvoiceitems.invoiceid AS linkedid", "tblinvoices.id AS existingid"])->all();
        foreach ($invoiceData as $data) {
            $invoicesnumbers[] = $data->existingid ? "<a href=\"invoices.php?action=edit&id=" . $data->linkedid . "\">" . $data->linkedid . "</a>" : "<span class=\"textgrey\">" . $data->linkedid . "</span>";
        }
        $invoicesnumbers = implode(", ", $invoicesnumbers);
        $tabledata[] = [$managelink . $id . "</a>", $managelink . $description . "</a>", $hours, $amount, $invoicesnumbers, $managelink . "<img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $id . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    $tableformbuttons = "";
    echo $aInt->sortableTable([["id", $aInt->lang("fields", "id")], ["description", $aInt->lang("fields", "description")], ["hours", $aInt->lang("fields", "hours")], ["amount", $aInt->lang("fields", "amount")], $aInt->lang("billableitems", "invoicenumbers"), "", ""], $tabledata, $tableformurl, $tableformbuttons);
} else {
    if ($action == "manage") {
        $jquery = "";
        if ($id) {
            $pagetitle = $aInt->lang("billableitems", "edititem");
            $result = select_query("tblbillableitems", "", ["id" => $id]);
            $data = mysql_fetch_array($result);
            $id = $data["id"];
            $description = $data["description"];
            $hours = $data["hours"];
            $amount = $data["amount"];
            if ($hours != 0) {
                $amount = format_as_currency($amount / $hours);
            }
            $recur = $data["recur"];
            $recurcycle = $data["recurcycle"];
            $recurfor = $data["recurfor"];
            $invoiceaction = $data["invoiceaction"];
            $unit = $data["unit"];
            $invoicecount = $data["invoicecount"];
            $duedate = fromMySQLDate($data["duedate"]);
        } else {
            $pagetitle = $aInt->lang("billableitems", "additem");
            $invoiceaction = 0;
            $unit = 0;
            $recur = 0;
            $duedate = getTodaysDate();
            $hours = "0";
            $amount = "0.00";
            $invoicecount = 0;
        }
        echo "<h2>" . $pagetitle . "</h2>";
        $curencyId = (int) $currency["id"];
        $jquerycode = "\$(\".itemselect\").change(function () {\n    var itemid = \$(this).val();\n    WHMCS.http.jqClient.post(\n        \"clientsbillableitems.php\", \n        {\n            action: \"getproddesc\",\n            id: itemid,\n            user: \"" . $userId . "\",\n            token: \"" . $token . "\"\n        },\n        function(data){\n            \$(\"#desc\").val(data);\n        }\n    );\n    WHMCS.http.jqClient.post(\n        \"clientsbillableitems.php\", \n        { \n            action: \"getprodprice\", \n            id: itemid, \n            currency: \"" . $curencyId . "\",\n            token: \"" . $token . "\"\n        },\n        function(data){\n            \$(\"#rate\").val(data);\n        }\n    );\n});\n\n\$('[name=\"hours\"]').on('change',function(e) {\n    var target = \$(e.target);\n    if (target.val()) { \n        target.val(parseFloat(target.val()).toFixed(2))\n    }\n});";
        echo "\n<form method=\"post\" action=\"";
        echo $_SERVER["PHP_SELF"];
        echo "?action=save&userid=";
        echo $userid;
        echo "&id=";
        echo $id;
        echo "\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n";
        if (!$id) {
            echo "<tr><td width=\"20%\" class=\"fieldlabel\">";
            echo $aInt->lang("fields", "product");
            echo "</td><td class=\"fieldarea\"><select name=\"pid[]\" class=\"form-control select-inline itemselect\" id=\"i'.\$i.'\">";
            echo $aInt->productDropDown(0, true);
            echo "</select></td></tr>";
        }
        echo "<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "description");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"description\" value=\"";
        echo $description;
        echo "\" class=\"form-control\" id=\"desc\" /></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("billableitems", "hoursqty");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"hours\" value=\"";
        echo $hours;
        echo "\" class=\"form-control input-100 input-inline\" />\n        <input type=\"radio\" name=\"unit\" value=\"0\" id=\"unitval0\"\n            ";
        echo $unit == "0" ? "checked" : "";
        echo ">\n        ";
        echo AdminLang::trans("billableitems.hours");
        echo "        <input type=\"radio\" name=\"unit\" value=\"1\" id=\"unitval1\"\n            ";
        echo $unit == "1" ? "checked" : "";
        echo ">\n        ";
        echo AdminLang::trans("billableitems.qty");
        echo "    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "amount");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"amount\" value=\"";
        echo $amount;
        echo "\" class=\"form-control input-100\" id=\"rate\" /></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("billableitems", "invoiceaction");
        echo "</td><td class=\"fieldarea\">\n<input type=\"radio\" name=\"invoiceaction\" value=\"0\" id=\"invac0\"";
        if ($invoiceaction == "0") {
            echo " checked";
        }
        echo " /> ";
        echo $aInt->lang("billableitems", "dontinvoicefornow");
        echo "<br />\n<input type=\"radio\" name=\"invoiceaction\" value=\"1\" id=\"invac1\"";
        if ($invoiceaction == "1") {
            echo " checked";
        }
        echo " /> ";
        echo $aInt->lang("billableitems", "invoicenextcronrun");
        echo "<br />\n<input type=\"radio\" name=\"invoiceaction\" value=\"2\" id=\"invac2\"";
        if ($invoiceaction == "2") {
            echo " checked";
        }
        echo " /> ";
        echo $aInt->lang("billableitems", "addnextinvoice");
        echo "<br />\n<input type=\"radio\" name=\"invoiceaction\" value=\"3\" id=\"invac3\"";
        if ($invoiceaction == "3") {
            echo " checked";
        }
        echo " /> ";
        echo $aInt->lang("billableitems", "invoicenormalduedate");
        echo "<br />\n<input type=\"radio\" name=\"invoiceaction\" value=\"4\" id=\"invac4\"";
        if ($invoiceaction == "4") {
            echo " checked";
        }
        echo " /> ";
        echo $aInt->lang("billableitems", "recurevery");
        echo " <input type=\"text\" name=\"recur\" value=\"";
        echo $recur;
        echo "\" class=\"form-control input-50 input-inline\"> <select name=\"recurcycle\" class=\"form-control select-inline\">\n<option value=\"\">";
        echo $aInt->lang("billableitems", "never");
        echo "</option>\n<option value=\"Days\"";
        if ($recurcycle == "Days") {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billableitems", "days");
        echo "</option>\n<option value=\"Weeks\"";
        if ($recurcycle == "Weeks") {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billableitems", "weeks");
        echo "</option>\n<option value=\"Months\"";
        if ($recurcycle == "Months") {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billableitems", "months");
        echo "</option>\n<option value=\"Years\"";
        if ($recurcycle == "Years") {
            echo " selected";
        }
        echo ">";
        echo $aInt->lang("billableitems", "years");
        echo "</option>\n</select> ";
        echo $aInt->lang("global", "for");
        echo " <input type=\"text\" name=\"recurfor\" value=\"";
        echo $recurfor;
        echo "\" class=\"form-control input-50 input-inline\"> Times<br />\n</td></tr>\n<tr id=\"duedaterow\">\n    <td class=\"fieldlabel\">\n        ";
        echo $aInt->lang("billableitems", "nextduedate");
        echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputDueDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputDueDate\"\n                   type=\"text\"\n                   name=\"duedate\"\n                   value=\"";
        echo $duedate;
        echo "\"\n                   class=\"form-control date-picker-single future\"\n            />\n        </div>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("billableitems", "invoicecount");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"invoicecount\" value=\"";
        echo $invoicecount;
        echo "\" class=\"form-control input-80\" /></td></tr>\n</table>\n\n";
        if ($id) {
            $currency = getCurrency($userid);
            $gatewaysarray = getGatewaysArray();
            $aInt->sortableTableInit("nopagination");
            $result = select_query("tblinvoiceitems", "tblinvoices.*", ["type" => "Item", "relid" => $id], "invoiceid", "ASC", "", "tblinvoices ON tblinvoices.id=tblinvoiceitems.invoiceid");
            while ($data = mysql_fetch_array($result)) {
                $invoiceid = $data["id"];
                $date = $data["date"];
                $duedate = $data["duedate"];
                $total = $data["total"];
                $paymentmethod = $data["paymentmethod"];
                $status = $data["status"];
                $date = fromMySQLDate($date);
                $duedate = fromMySQLDate($duedate);
                $total = formatCurrency($total);
                $paymentmethod = $gatewaysarray[$paymentmethod];
                $status = getInvoiceStatusColour($status);
                $invoicelink = "<a href=\"invoices.php?action=edit&id=" . $invoiceid . "\">";
                $tabledata[] = [$invoicelink . $invoiceid . "</a>", $date, $duedate, $total, $paymentmethod, $status, $invoicelink . "<img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>"];
            }
            echo "<h2>" . $aInt->lang("billableitems", "relatedinvoices") . "</h2>" . $aInt->sortableTable([$aInt->lang("fields", "invoicenum"), $aInt->lang("fields", "invoicedate"), $aInt->lang("fields", "duedate"), $aInt->lang("fields", "total"), $aInt->lang("fields", "paymentmethod"), $aInt->lang("fields", "status"), ""], $tabledata);
        }
        echo "\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
        echo $aInt->lang("global", "savechanges");
        echo "\" class=\"btn btn-default\" />\n</div>\n\n</form>\n\n";
    } else {
        if ($action == "timebilling") {
            $jquerycode = "\$(\".itemselect\").change(function () {\n    var rowid = \$(this).attr(\"id\");\n    var itemid = \$(this).val();\n    WHMCS.http.jqClient.post(\"clientsbillableitems.php\", {\n        action: \"getproddesc\",\n        id: itemid,\n        user: \"" . $userId . "\",\n        token: \"" . $token . "\"\n    },\n    function(data){\n        \$(\"#desc_\"+rowid).val(data);\n    });\n    WHMCS.http.jqClient.post(\"clientsbillableitems.php\", { action: \"getprodprice\", id: itemid, currency: \"" . (int) $currency["id"] . "\", token: \"" . $token . "\" },\n    function(data){\n        \$(\"#rate_\"+rowid).val(data);\n    });\n});";
            $options = "";
            $products = new WHMCS\Product\Products();
            $productsList = $products->getProducts();
            foreach ($productsList as $data) {
                $pid = $data["id"];
                $pname = $data["name"];
                $ptype = $data["groupname"];
                $options .= "<option value=\"" . $pid . "\"";
                if ($package == $pid) {
                    $options .= " selected";
                }
                $options .= ">" . $ptype . " - " . $pname . "</option>";
            }
            echo "<h2>" . $aInt->lang("billableitems", "addtimebilling") . "</h2>\n<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "?action=addtime&userid=" . $userid . "\">";
            $aInt->sortableTableInit("nopagination");
            $tabledata = [];
            for ($i = 1; $i <= 10; $i++) {
                $tabledata[] = ["<select name=\"pid[]\" class=\"form-control itemselect\" id=\"i" . $i . "\"><option value=\"\">" . $aInt->lang("global", "none") . "</option>" . $options . "</select>", "<input type=\"text\" name=\"description[]\" class=\"form-control\" id=\"desc_i" . $i . "\" />", "<input type=\"text\" name=\"hours[]\" value=\"0\" class=\"form-control\" />", "<input type=\"text\" name=\"rate[]\" value=\"0.00\" class=\"form-control\" id=\"rate_i" . $i . "\" />"];
            }
            echo $aInt->sortableTable([["", $aInt->lang("fields", "item"), "25%"], ["", $aInt->lang("fields", "description"), "50%"], $aInt->lang("fields", "hours"), $aInt->lang("fields", "rate")], $tabledata);
            echo "<p align=\"center\"><input type=\"submit\" value=\"" . $aInt->lang("billableitems", "addentries") . "\" class=\"btn btn-default\" /></p>\n</form>";
        }
    }
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
