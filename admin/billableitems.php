<?php

define("ADMINAREA", true);
require "../init.php";
$action = App::getFromRequest("action");
$userid = App::getFromRequest("userid");
$id = App::getFromRequest("id");
$status = App::getFromRequest("status");
$amount = App::getFromRequest("amount");
$recur = App::getFromRequest("recur");
$recurcycle = App::getFromRequest("recurcycle");
$recurfor = App::getFromRequest("recurfor");
$invoice = App::getFromRequest("invoice");
$delete = App::getFromRequest("delete");
$bitem = App::getFromRequest("bitem");
$duedate = App::getFromRequest("duedate");
$hours = App::getFromRequest("hours");
$invoiceaction = App::getFromRequest("invoiceaction");
$unit = App::getFromRequest("unit");
$invoicecount = App::getFromRequest("invoicecount");
$description = trim(App::getFromRequest("description"));
if (!$action) {
    $reqperm = "View Billable Items";
} else {
    $reqperm = "Manage Billable Items";
}
$aInt = new WHMCS\Admin($reqperm);
$whmcs = WHMCS\Application::getInstance();
$aInt->title = $aInt->lang("billableitems", "title");
$aInt->sidebar = "billing";
$aInt->icon = "billableitems";
$aInt->requiredFiles(["invoicefunctions", "gatewayfunctions"]);
if ($action == "save") {
    check_token("WHMCS.admin.default");
    if (!$userid) {
        $aInt->gracefulExit($aInt->lang("billableitems", "noclientsmsg"));
    }
    $duedate = toMySQLDate($duedate);
    getUsersLang($userid);
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
    redir($roundedWarning);
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    delete_query("tblbillableitems", ["id" => $id]);
    redir();
}
ob_start();
if (!$action) {
    if ($invoice && is_array($bitem)) {
        check_token("WHMCS.admin.default");
        checkPermission("Manage Billable Items");
        foreach ($bitem as $id => $v) {
            update_query("tblbillableitems", ["invoiceaction" => "1"], ["id" => $id]);
        }
        infoBox($aInt->lang("billableitems", "invoiceitems"), $aInt->lang("billableitems", "itemswillinvoice"));
        echo $infobox;
    }
    if ($delete && is_array($bitem)) {
        check_token("WHMCS.admin.default");
        checkPermission("Manage Billable Items");
        foreach ($bitem as $id => $v) {
            delete_query("tblbillableitems", ["id" => $id]);
        }
        infoBox($aInt->lang("billableitems", "itemsdeleted"), $aInt->lang("billableitems", "itemsdeleteddesc"));
        echo $infobox;
    }
    $aInt->deleteJSConfirm("doDelete", "billableitems", "itemsdeletequestion", "billableitems.php?userid=" . $userid . "&action=delete&id=");
    echo $aInt->beginAdminTabs([$aInt->lang("global", "searchfilter")]);
    echo "\n<form action=\"";
    echo $_SERVER["PHP_SELF"];
    echo "\" method=\"get\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
    echo $aInt->lang("fields", "client");
    echo "</td><td class=\"fieldarea\">";
    echo $aInt->clientsDropDown($userid, false, "userid", true);
    echo "</td><td width=\"15%\" class=\"fieldlabel\">";
    echo $aInt->lang("fields", "amount");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"amount\" class=\"form-control input-100\" value=\"";
    echo $amount;
    echo "\"></td></tr>\n<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "description");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"description\" class=\"form-control input-300\" value=\"";
    echo $description;
    echo "\"></td><td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "status");
    echo "</td><td class=\"fieldarea\"><select name=\"status\" class=\"form-control select-inline\">\n<option value=\"\">";
    echo $aInt->lang("global", "any");
    echo "</option>\n<option";
    if ($status == "Uninvoiced") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "uninvoiced");
    echo "</option>\n<option";
    if ($status == "Invoiced") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "invoiced");
    echo "</option>\n<option";
    if ($status == "Recurring") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "recurring");
    echo "</option>\n<option";
    if ($status == "Active Recurring") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "activerecurring");
    echo "</option>\n<option";
    if ($status == "Completed Recurring") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "completedrecurring");
    echo "</option>\n</select></td></tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("global", "searchfilter");
    echo "\" class=\"btn btn-default\">\n</div>\n\n</form>\n\n";
    echo $aInt->endAdminTabs();
    echo "\n<br />\n\n";
    if (App::isInRequest("rounded")) {
        echo infoBox("Billable Item Saved", "Hours/Qty values are rounded to two decimal places.");
    }
    $aInt->sortableTableInit("id", "DESC");
    $query = WHMCS\Database\Capsule::table("tblbillableitems")->join("tblclients", "tblclients.id", "=", "tblbillableitems.userid");
    $where = [];
    if ($status == "Uninvoiced") {
        $query->where("tblbillableitems.invoicecount", "=", "0");
    } else {
        if ($status == "Invoiced") {
            $query->where("tblbillableitems.invoicecount", ">", "0");
        } else {
            if ($status == "Recurring") {
                $query->where("tblbillableitems.invoiceaction", "=", "4");
            } else {
                if ($status == "Active Recurring") {
                    $query->where("tblbillableitems.invoiceaction", "=", "4")->whereRaw("tblbillableitems.invoicecount < tblbillableitems.recurfor");
                } else {
                    if ($status == "Completed Recurring") {
                        $query->where("tblbillableitems.invoiceaction", "=", "4")->whereRaw("tblbillableitems.invoicecount >= tblbillableitems.recurfor");
                    }
                }
            }
        }
    }
    if ($description) {
        $query->where("tblbillableitems.description", "LIKE", "%" . $description . "%");
    }
    if ($amount) {
        $query->where("tblbillableitems.amount", "=", $amount);
    }
    if ($userid) {
        $query->where("tblbillableitems.userid", "=", $userid);
    }
    $numrows = $query->count("tblbillableitems.id");
    $query->orderBy($orderby, $order)->skip($page * $limit)->limit($limit);
    $rows = $query->get(["tblbillableitems.*", "tblclients.firstname", "tblclients.lastname", "tblclients.companyname", "tblclients.groupid", "tblclients.currency"])->all();
    foreach ($rows as $row) {
        $data = (array) $row;
        $id = $data["id"];
        $userid = $data["userid"];
        $firstname = $data["firstname"];
        $lastname = $data["lastname"];
        $companyname = $data["companyname"];
        $groupid = $data["groupid"];
        $currency = $data["currency"];
        $description = $data["description"];
        $hours = $data["hours"];
        $amount = $data["amount"];
        $invoiceaction = $data["invoiceaction"];
        $unit = $data["unit"];
        $invoicecount = $data["invoicecount"];
        $currency = getCurrency(NULL, $currency);
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
        if ($invoicecount) {
            $invoiced = $aInt->lang("global", "yes");
        } else {
            $invoiced = $aInt->lang("global", "no");
        }
        $managelink = "<a href=\"billableitems.php?action=manage&id=" . $id . "\">";
        $tabledata[] = ["<input type=\"checkbox\" name=\"bitem[" . $id . "]\" class=\"checkall\" />", $managelink . $id . "</a>", $aInt->outputClientLink($userid, $firstname, $lastname, $companyname, $groupid), $managelink . $description . "</a>", $hours, $amount, $invoiceaction, $invoiced, $managelink . "<img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $id . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    $tableformurl = $_SERVER["PHP_SELF"] . "?status=" . $status;
    $tableformbuttons = "<input type=\"submit\" name=\"invoice\" value=\"" . $aInt->lang("billableitems", "invoicenextcronrun") . "\" class=\"btn btn-default\" onclick=\"return confirm('" . $aInt->lang("billableitems", "invoicenextcronrunconfirm", "1") . "')\" /> <input type=\"submit\" name=\"delete\" value=\"" . $aInt->lang("global", "delete") . "\" class=\"btn btn-danger\" onclick=\"return confirm('" . $aInt->lang("global", "deleteconfirm", "1") . "')\" />";
    echo $aInt->sortableTable(["checkall", ["id", $aInt->lang("fields", "id")], $aInt->lang("fields", "clientname"), ["description", $aInt->lang("fields", "description")], ["hours", $aInt->lang("billableitems", "hours")], ["amount", $aInt->lang("fields", "amount")], ["invoiceaction", $aInt->lang("billableitems", "invoiceaction")], ["invoicecount", $aInt->lang("status", "invoiced")], "", ""], $tabledata, $tableformurl, $tableformbuttons);
} else {
    if ($action == "manage") {
        $package = App::getFromRequest("package");
        $currency = getCurrency($userid);
        $jquery = "";
        if ($id) {
            $pagetitle = $aInt->lang("billableitems", "edititem");
            $result = select_query("tblbillableitems", "", ["id" => $id]);
            $data = mysql_fetch_array($result);
            $id = $data["id"];
            $userid = $data["userid"];
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
            $clientcheck = get_query_val("tblclients", "id", "");
            if (!$clientcheck) {
                $aInt->gracefulExit($aInt->lang("billableitems", "noclientsmsg"));
            }
            $invoiceaction = 0;
            $unit = 0;
            $recur = 0;
            $duedate = getTodaysDate();
            $hours = "0.0";
            $amount = "0.00";
            $invoicecount = 0;
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
        }
        echo "<h2>" . $pagetitle . "</h2>";
        $currencyId = (int) $currency["id"];
        $jquerycode = "\$(\".itemselect\").change(function () {\n    var itemid = \$(this).val();\n    var userId = \$(\"select[name='userid']\").val();\n    WHMCS.http.jqClient.post(\"clientsbillableitems.php\", {\n        action: \"getproddesc\",\n        id: itemid,\n        user: userId,\n        token: \"{generate_token('plain')}\"\n    },\n    function(data){\n        \$(\"#desc\").val(data);\n    });\n    WHMCS.http.jqClient.post(\"clientsbillableitems.php\", { \n        action: \"getprodprice\", \n        id: itemid, \n        currency: \"" . $currencyId . "\",\n        token: \"{generate_token('plain')}\" },\n    function(data){\n        \$(\"#rate\").val(data);\n    });\n});\n\$(\"[name='hours']\").on(\"change\",function(e) {\n    var target = \$(e.target);\n    if (target.val()) { \n        target.val(parseFloat(target.val()).toFixed(2))\n    }\n});";
        echo "\n<form method=\"post\" action=\"";
        echo $_SERVER["PHP_SELF"];
        echo "?action=save&id=";
        echo $id;
        echo "\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "client");
        echo "</td><td class=\"fieldarea\">";
        echo $aInt->clientsDropDown($userid);
        echo "</td></tr>\n";
        if (!$id) {
            echo "<tr><td width=\"20%\" class=\"fieldlabel\">";
            echo $aInt->lang("fields", "product");
            echo "</td><td class=\"fieldarea\"><select name=\"pid[]\" class=\"form-control select-inline itemselect\" id=\"i'.\$i.'\"><option value=\"\">";
            echo $aInt->lang("global", "none");
            echo "</option>";
            echo $options;
            echo "</select></td></tr>";
        }
        echo "<tr><td width=\"20%\" class=\"fieldlabel\">";
        echo $aInt->lang("fields", "description");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"description\" value=\"";
        echo $description;
        echo "\" class=\"form-control input-400\" id=\"desc\" /></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
        echo AdminLang::trans("billableitems.hoursqty");
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
        echo " <input type=\"text\" name=\"recurfor\" class=\"form-control input-50 input-inline\" value=\"";
        echo $recurfor;
        echo "\" /> ";
        echo $aInt->lang("billableitems", "times");
        echo "<br />\n</td></tr>\n<tr id=\"duedaterow\">\n    <td class=\"fieldlabel\">";
        echo $aInt->lang("billableitems", "nextduedate");
        echo "</td>\n    <td class=\"fieldarea\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputDueDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputDueDate\"\n                   type=\"text\"\n                   name=\"duedate\"\n                   value=\"";
        echo $duedate;
        echo "\"\n                   class=\"form-control date-picker-single future\"\n            />\n        </div>\n    </td>\n</tr>\n<tr><td class=\"fieldlabel\">";
        echo $aInt->lang("billableitems", "invoicecount");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"invoicecount\" value=\"";
        echo $invoicecount;
        echo "\" class=\"form-control input-100\" /></td></tr>\n</table>\n\n";
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
        echo "\" class=\"btn btn-primary\" />\n</div>\n\n</form>\n\n";
    }
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
