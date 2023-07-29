<?php

define("ADMINAREA", true);
require "../init.php";
$action = App::getFromRequest("action");
$userId = (int) App::getFromRequest("userid");
$status = App::getFromRequest("status");
if ($action == "view") {
    $reqperm = "View Order Details";
} else {
    $reqperm = "View Orders";
}
$aInt = new WHMCS\Admin($reqperm);
$aInt->title = $aInt->lang("orders", "manage");
$aInt->sidebar = "orders";
$aInt->icon = "orders";
$aInt->helplink = "Order Management";
$aInt->requiredFiles(["gatewayfunctions", "orderfunctions", "modulefunctions", "domainfunctions", "invoicefunctions", "processinvoices", "clientfunctions", "ccfunctions", "registrarfunctions"]);
if ($action == "resendVerificationEmail") {
    check_token("WHMCS.admin.default");
    $userId = App::getFromRequest("userid");
    $user = WHMCS\User\Client::find($userId)->owner();
    if (!is_null($user)) {
        $user->sendEmailVerification();
    }
    $aInt->jsonResponse(["success" => true]);
}
$massStatusArray = explode(",", $whmcs->get_req_var("massstatus"));
$massSuccesses = (int) ($massStatusArray[0] ?? 0);
$massFailures = (int) ($massStatusArray[1] ?? 0);
if ($whmcs->get_req_var("masssuccess") == 1) {
    infoBox($aInt->lang("orders", "statusmassaccept"), $massSuccesses . " " . $aInt->lang("orders", "statusmassacceptmsg"), "success");
} else {
    if (0 < $massFailures) {
        $massErrors = explode(",", $whmcs->get_req_var("masserror"));
        foreach ($massErrors as $key => $value) {
            $massErrors[$key] = (int) $value;
        }
        $massErrors = implode(", ", $massErrors);
        infoBox($aInt->lang("orders", "statusmassfailures"), sprintf($aInt->lang("orders", "statusmassfailuresmsg"), $massSuccesses, $massFailures, $massErrors) . "  <a href=\"systemactivitylog.php\">" . $aInt->lang("system", "activitylog") . "</a>", "error");
    }
}
if ($whmcs->get_req_var("noDelete")) {
    infoBox($aInt->lang("global", "error"), $aInt->lang("orders", "noDelete"), "error");
    $action = "view";
}
if ($whmcs->get_req_var("massDeleteError")) {
    infoBox($aInt->lang("global", "error"), $aInt->lang("orders", "massDeleteError"), "error");
}
if ($whmcs->get_req_var("rerunfraudcheck")) {
    check_token("WHMCS.admin.default");
    $order = WHMCS\Order\Order::find($orderid);
    $fraud = new WHMCS\Module\Fraud();
    if ($fraud->load($order->fraudmodule)) {
        $response = $fraud->doFraudCheck($order->id, $order->userid, $order->ipaddress);
        $output = $fraud->processResultsForDisplay($order->id, $response["fraudoutput"]);
    } else {
        $output = "Unable to load fraud module";
    }
    $aInt->jsonResponse(["output" => $output]);
}
if ($action == "affassign") {
    if (isset($orderid) && isset($affid)) {
        check_token("WHMCS.admin.default");
        try {
            $affiliate = WHMCS\User\Client\Affiliate::findOrFail($affid);
            $order = WHMCS\Order\Order::find($orderid);
            if ($order->userId == $affiliate->clientId) {
                throw new InvalidArgumentException("orders.selfReferral");
            }
        } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $aInt->jsonResponse(["errorMsg" => AdminLang::trans("orders.invalidAffiliate"), "errorMsgTitle" => ""]);
        } catch (Throwable $t) {
            $aInt->jsonResponse(["errorMsg" => AdminLang::trans($t->getMessage()), "errorMsgTitle" => ""]);
        }
        $relServices = WHMCS\Service\Service::where("orderid", $orderid)->get();
        foreach ($relServices as $service) {
            WHMCS\Database\Capsule::table("tblaffiliatesaccounts")->insert(["affiliateid" => $affid, "relid" => $service->id]);
            $userId = $service->clientId;
        }
        logActivity("Manually Assigned Affiliate to Order - Affiliate ID: " . $affid . " - Order ID: " . $orderid, $userId);
        $affiliateName = $affiliate->client->fullName;
        $aInt->jsonResponse(["successMsg" => AdminLang::trans("orders.referralSuccess"), "successMsgTitle" => "", "dismiss" => true, "body" => "<script>\njQuery(\"#affiliatefield\").text('" . $affiliateName . "');\n</script>"]);
    }
    $affDropdown = new WHMCS\Admin\ApplicationSupport\View\Html\Helper\AffiliateSearchDropDown("affid", "", [], "", "aff_id");
    $tokenInput = generate_token();
    $aInt->jsonResponse(["body" => "<div class=\"alert alert-danger admin-modal-error\" style=\"display: none;\"></div>\n<form id=\"frmAffiliateAssign\">\n    " . $tokenInput . "\n    <input type=\"hidden\" name=\"action\" value=\"affassign\">\n    <input type=\"hidden\" name=\"orderid\" value=\"" . $orderid . "\">\n    " . $aInt->lang("orders", "chooseaffiliate") . "\n    " . $affDropdown->getFormattedBodyContent() . "\n    " . $affDropdown->getFormattedHtmlHeadContent() . "\n</form>"]);
}
if ($action == "ajaxchangeorderstatus") {
    check_token("WHMCS.admin.default");
    $id = get_query_val("tblorders", "id", ["id" => $id]);
    $result = select_query("tblorderstatuses", "title", "", "sortorder", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $statusesarr[] = $data["title"];
    }
    if (in_array($status, $statusesarr) && $id) {
        update_query("tblorders", ["status" => $status], ["id" => $id]);
        echo $id;
    } else {
        echo 0;
    }
    exit;
}
if ($action == "ajaxCanOrderBeDeleted") {
    check_token("WHMCS.admin.default");
    $id = App::getFromRequest("id");
    echo canOrderBeDeleted((int) $id);
    exit;
}
$filters = new WHMCS\Filter();
if ($action == "delete" && $id) {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Order");
    if (canOrderBeDeleted($id)) {
        deleteOrder($id);
        $aInt->flash(AdminLang::trans("global.changesuccessdeleted"), AdminLang::trans("orders.deleteSuccess"));
        $filters->redir();
    } else {
        $aInt->flash(AdminLang::trans("global.error"), AdminLang::trans("orders.noDelete"), "error");
        $filters->redir("id=" . $id);
    }
}
if ($action == "cancel" && $id) {
    check_token("WHMCS.admin.default");
    checkPermission("View Order Details");
    changeOrderStatus($id, "Cancelled");
    $filters->redir();
}
if ($action == "cancelDelete" && $id) {
    check_token("WHMCS.admin.default");
    checkPermission("View Order Details");
    changeOrderStatus($id, "Cancelled");
    checkPermission("Delete Order");
    if (canOrderBeDeleted($id)) {
        deleteOrder($id);
        $aInt->flash(AdminLang::trans("global.changesuccessdeleted"), AdminLang::trans("orders.deleteSuccess"));
        $filters->redir();
    } else {
        $aInt->flash(AdminLang::trans("global.error"), AdminLang::trans("orders.noDelete"), "error");
        $filters->redir("id=" . $id);
    }
}
if ($whmcs->get_req_var("massaccept")) {
    check_token("WHMCS.admin.default");
    checkPermission("View Order Details");
    $acceptErrors = [];
    $successes = $failures = 0;
    if (is_array($selectedorders)) {
        foreach ($selectedorders as $orderid) {
            $errors = acceptOrder($orderid);
            if (empty($errors)) {
                $successes++;
            } else {
                $acceptErrors[] = $orderid;
                $failures++;
            }
        }
    }
    if (empty($acceptErrors)) {
        $massStatus = "&masssuccess=1";
    } else {
        $massStatus = "&masserror=" . implode(",", $acceptErrors);
    }
    $filters->redir("massstatus=" . $successes . "," . $failures . $massStatus);
}
if ($whmcs->get_req_var("masscancel")) {
    check_token("WHMCS.admin.default");
    checkPermission("View Order Details");
    if (is_array($selectedorders)) {
        foreach ($selectedorders as $orderid) {
            changeOrderStatus($orderid, "Cancelled");
        }
    }
    $aInt->flash(AdminLang::trans("global.changesuccessdeleted"), AdminLang::trans("orders.deleteSuccess"));
    $filters->redir();
}
if ($whmcs->get_req_var("massdelete")) {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Order");
    $deleteError = "";
    if (is_array($selectedorders)) {
        foreach ($selectedorders as $orderid) {
            if (canOrderBeDeleted($orderid)) {
                deleteOrder($orderid);
            } else {
                $deleteError = "massDeleteError=true";
            }
        }
    }
    $filters->redir($deleteError);
}
if ($whmcs->get_req_var("sendmessage") && is_array($selectedorders) && 0 < count($selectedorders)) {
    check_token("WHMCS.admin.default");
    $clientslist = "";
    $result = select_query("tblorders", "DISTINCT userid", "id IN (" . db_build_in_array($selectedorders) . ")");
    while ($data = mysql_fetch_array($result)) {
        $clientslist .= "selectedclients[]=" . $data["userid"] . "&";
    }
    redir("type=general&multiple=true&" . substr($clientslist, 0, -1), "sendmessage.php");
}
ob_start();
if (!$action) {
    echo $infobox;
    WHMCS\Session::release();
    echo $aInt->beginAdminTabs([$aInt->lang("global", "searchfilter")]);
    $client = $filters->get("client");
    $clientid = $filters->get("clientid");
    if (!$clientid && $client) {
        $clientid = $client;
    }
    $clientname = $filters->get("clientname");
    echo "\n<form action=\"";
    echo $whmcs->getPhpSelf();
    echo "\" method=\"post\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
    echo $aInt->lang("fields", "orderid");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"orderid\" class=\"form-control input-100\" value=\"";
    echo $orderid = $filters->get("orderid");
    echo "\"></td><td width=\"15%\" class=\"fieldlabel\">";
    echo $aInt->lang("fields", "client");
    echo "</td><td class=\"fieldarea\">";
    echo $aInt->clientsDropDown($clientid, false, "clientid", true);
    echo "</td></tr>\n<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "ordernum");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"ordernum\" class=\"form-control input-150\" value=\"";
    echo $ordernum = $filters->get("ordernum");
    echo "\"></td><td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "paymentstatus");
    echo "</td><td class=\"fieldarea\"><select name=\"paymentstatus\" class=\"form-control select-inline\">\n<option value=\"\">";
    echo $aInt->lang("global", "any");
    echo "</option>\n<option value=\"Paid\"";
    $paymentstatus = $filters->get("paymentstatus");
    if ($paymentstatus == "Paid") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "paid");
    echo "</option>\n<option value=\"Unpaid\"";
    if ($paymentstatus == "Unpaid") {
        echo " selected";
    }
    echo ">";
    echo $aInt->lang("status", "unpaid");
    echo "</option>\n</select></td></tr>\n<tr>\n    <td class=\"fieldlabel\">\n        ";
    echo AdminLang::trans("fields.daterange");
    echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputOrderDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputOrderDate\"\n                   type=\"text\"\n                   name=\"orderdate\"\n                   value=\"";
    echo $orderdate = $filters->get("orderdate");
    echo "\"\n                   class=\"form-control date-picker-search\"\n            />\n        </div>\n    </td>\n    <td class=\"fieldlabel\">\n        ";
    echo AdminLang::trans("fields.status");
    echo "    </td>\n    <td class=\"fieldarea\">\n        <select name=\"status\" class=\"form-control select-inline\">\n<option value=\"\">";
    echo $aInt->lang("global", "any");
    echo "</option>\n";
    $status = $filters->get("status");
    $result = select_query("tblorderstatuses", "", "", "sortorder", "ASC");
    while ($data = mysql_fetch_array($result)) {
        echo "<option value=\"" . $data["title"] . "\" style=\"color:" . $data["color"] . "\"";
        if ($status == $data["title"]) {
            echo " selected";
        }
        echo ">" . ($aInt->lang("status", strtolower($data["title"])) ? $aInt->lang("status", strtolower($data["title"])) : $data["title"]) . "</option>";
    }
    echo "</select></td></tr>\n<tr><td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "amount");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"amount\" value=\"";
    echo $amount = $filters->get("amount");
    echo "\" class=\"form-control input-100\"></td><td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "ipaddress");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"orderip\" value=\"";
    echo $orderip = $filters->get("orderip");
    echo "\" class=\"form-control input-150\"></td></tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("global", "search");
    echo "\" class=\"btn btn-default\" />\n</div>\n\n</form>\n\n";
    echo $aInt->endAdminTabs();
    echo "\n<br>\n\n";
    $selectors = "input[name='massaccept'],input[name='masscancel'],";
    $selectors .= "input[name='massdelete'],input[name='sendmessage']";
    $errorTitle = AdminLang::trans("global.error");
    $errorText = AdminLang::trans("global.pleaseSelectForMassAction");
    $jquerycode = "var name = '',\n    selectedItems = '';\njQuery(document).on('click', \"" . $selectors . "\", function(event) {\n    var massAction = '';\n    selectedItems = \$(\"input[name='selectedorders[]']\");\n    name = jQuery(this).attr('name');        \n    switch(name) {\n        case 'massaccept':\n            massAction = 'acceptMass';\n            break;\n        case 'masscancel':\n            massAction = 'cancelMass';\n            break;\n        case 'massdelete':\n            massAction = 'deleteMass';\n            break;\n        case 'sendmessage':\n            massAction = 'messageMass';\n            break;\n    }\n    if (selectedItems.filter(':checked').length == 0) {\n        event.preventDefault();\n        swal(\n            '" . $errorTitle . "',\n            '" . $errorText . "',\n            'error'\n          );\n    } else {\n        event.preventDefault();\n        jQuery('#' + massAction).modal('show');\n    }\n}).on('click', 'button[id\$=\"Mass-ok\"]', function(event) {\n    event.preventDefault();\n    var form = jQuery('input[name=\"' + name + '\"]').closest('form');\n    form.attr('action', function(i, value) {\n        return value + '&' + name + '=true'\n    })\n    form.submit();\n       \n});";
    $name = "orders";
    $orderby = "id";
    $sort = "DESC";
    $pageObj = new WHMCS\Pagination($name, $orderby, $sort);
    $pageObj->digestCookieData();
    $filters->store();
    $tbl = new WHMCS\ListTable($pageObj, 0, $aInt);
    $tbl->setColumns(["checkall", ["id", $aInt->lang("fields", "id")], ["ordernum", $aInt->lang("fields", "ordernum")], ["date", $aInt->lang("fields", "date")], $aInt->lang("fields", "clientname"), ["paymentmethod", $aInt->lang("fields", "paymentmethod")], ["amount", $aInt->lang("fields", "total")], $aInt->lang("fields", "paymentstatus"), ["status", $aInt->lang("fields", "status")], ""]);
    $criteria = ["clientid" => $clientid, "amount" => $amount, "orderid" => $orderid, "ordernum" => $ordernum, "orderip" => $orderip, "orderdate" => $orderdate, "clientname" => $clientname, "paymentstatus" => $paymentstatus, "status" => $status];
    $ordersModel = new WHMCS\Orders($pageObj);
    $ordersModel->execute($criteria);
    $numresults = $pageObj->getNumResults();
    if ($filters->isActive() && $numresults == 1) {
        $order = $pageObj->getOne();
        redir("action=view&id=" . $order["id"]);
    } else {
        $orderlist = $pageObj->getData();
        foreach ($orderlist as $order) {
            if (canOrderBeDeleted($order["id"], $order["status"])) {
                $function = "delete";
                $alt = $aInt->lang("global", "delete");
            } else {
                $function = "cancelDelete";
                $alt = $aInt->lang("global", "cancelAndDelete");
            }
            $deleteOrderId = $order["id"];
            $deleteIcon = "<a href=\"#\" class=\"delete-order\" data-order-id=\"" . $deleteOrderId . "\" data-delete-type=\"" . $function . "\">\n<img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $alt . "\">\n</a>";
            $tbl->addRow(["<input type='checkbox' name='selectedorders[]' value='" . $order["id"] . "' class='checkall'>", "<a href='?action=view&id=" . $order["id"] . "'><b>" . $order["id"] . "</b></a>", $order["ordernum"], $order["date"], $order["clientname"], $order["paymentmethod"], $order["amount"], $order["paymentstatusformatted"], $order["statusformatted"], $deleteIcon]);
        }
        $massActionButtons = "<input type=\"submit\" name=\"massaccept\" value=\"" . $aInt->lang("orders", "accept") . "\" class=\"btn btn-success\" />\n <input type=\"submit\" name=\"masscancel\" value=\"" . $aInt->lang("orders", "cancel") . "\" class=\"btn btn-default\" />\n <input type=\"submit\" name=\"massdelete\" value=\"" . $aInt->lang("orders", "delete") . "\" class=\"btn btn-danger\" />\n <input type=\"submit\" name=\"sendmessage\" value=\"" . $aInt->lang("global", "sendmessage") . "\" class=\"btn btn-default\" />\n <input type=\"hidden\" name=\"action\" value=\"\">";
        $tbl->setMassActionBtns($massActionButtons);
        echo $tbl->output();
        unset($orderlist);
        unset($ordersModel);
    }
} else {
    if ($action == "view") {
        if ($whmcs->get_req_var("activate")) {
            check_token("WHMCS.admin.default");
            $vars = !empty($vars) && is_array($vars) ? $vars : [];
            $errors = acceptOrder($id, $vars);
            WHMCS\Cookie::set("OrderAccept", $errors);
            redir("action=view&id=" . $id . "&activated=true");
        }
        if ($whmcs->get_req_var("cancel")) {
            check_token("WHMCS.admin.default");
            $queryStr = "action=view&id=" . $id . "&cancelled=true";
            $cancelSubscription = (bool) $whmcs->get_req_var("cancelsub");
            $errMsg = changeOrderStatus($id, "Cancelled", $cancelSubscription);
            if (0 < strlen($errMsg)) {
                redir($queryStr . "&error=" . $errMsg);
            } else {
                redir($queryStr);
            }
        }
        if ($whmcs->get_req_var("fraud")) {
            check_token("WHMCS.admin.default");
            $queryStr = "action=view&id=" . $id . "&frauded=true";
            $cancelSubscription = (bool) $whmcs->get_req_var("cancelsub");
            $errMsg = changeOrderStatus($id, "Fraud", $cancelSubscription);
            if (0 < strlen($errMsg)) {
                redir($queryStr . "&error=" . $errMsg);
            } else {
                redir($queryStr);
            }
        }
        if ($whmcs->get_req_var("pending")) {
            check_token("WHMCS.admin.default");
            changeOrderStatus($id, "Pending");
            redir("action=view&id=" . $id . "&backpending=true");
        }
        if ($whmcs->get_req_var("cancelrefund")) {
            check_token("WHMCS.admin.default");
            checkPermission("Refund Invoice Payments");
            $error = cancelRefundOrder($id);
            redir("action=view&id=" . $id . "&cancelledrefunded=true&error=" . $error);
        }
        if ($whmcs->get_req_var("activated") && isset($_COOKIE["WHMCSOrderAccept"])) {
            $errors = WHMCS\Cookie::get("OrderAccept", 1);
            WHMCS\Cookie::delete("OrderAccept");
            if (count($errors)) {
                infoBox($aInt->lang("orders", "statusaccepterror"), implode("<br>", $errors), "error");
            } else {
                infoBox($aInt->lang("orders", "statusaccept"), $aInt->lang("orders", "statusacceptmsg"), "success");
            }
        }
        if ($whmcs->get_req_var("cancelled")) {
            $error = $whmcs->get_req_var("error");
            if ($error == "subcancelfailed") {
                infoBox($aInt->lang("orders", "statusCancelledFailed"), $aInt->lang("orders", "subCancelFailed"), "error");
            } else {
                infoBox($aInt->lang("orders", "statuscancelled"), $aInt->lang("orders", "statuschangemsg"));
            }
        }
        if ($whmcs->get_req_var("frauded")) {
            $error = $whmcs->get_req_var("error");
            if ($error == "subcancelfailed") {
                infoBox($aInt->lang("orders", "statusCancelledFailed"), $aInt->lang("orders", "subCancelFailed"), "error");
            } else {
                infoBox($aInt->lang("orders", "statusfraud"), $aInt->lang("orders", "statuschangemsg"));
            }
        }
        if ($whmcs->get_req_var("backpending")) {
            infoBox($aInt->lang("orders", "statuspending"), $aInt->lang("orders", "statuschangemsg"));
        }
        if ($whmcs->get_req_var("cancelledrefunded")) {
            $error = $whmcs->get_req_var("error");
            if ($error == "noinvoice") {
                infoBox($aInt->lang("orders", "statusrefundfailed"), $aInt->lang("orders", "statusrefundnoinvoice"), "error");
            } else {
                if ($error == "notpaid") {
                    infoBox($aInt->lang("orders", "statusrefundfailed"), $aInt->lang("orders", "statusrefundnotpaid"), "error");
                } else {
                    if ($error == "alreadyrefunded") {
                        infoBox($aInt->lang("orders", "statusrefundfailed"), $aInt->lang("orders", "statusrefundalready"), "error");
                    } else {
                        if ($error == "refundfailed") {
                            infoBox($aInt->lang("orders", "statusrefundfailed"), $aInt->lang("orders", "statusrefundfailedmsg"), "error");
                        } else {
                            if ($error == "manual") {
                                infoBox($aInt->lang("orders", "statusrefundfailed"), $aInt->lang("orders", "statusrefundnoauto"), "error");
                            } else {
                                infoBox($aInt->lang("orders", "statusrefundsuccess"), $aInt->lang("orders", "statusrefundsuccessmsg"), "success");
                            }
                        }
                    }
                }
            }
        }
        if ($whmcs->get_req_var("updatenotes")) {
            check_token("WHMCS.admin.default");
            update_query("tblorders", ["notes" => $notes], ["id" => $id]);
            exit;
        }
        echo $infobox;
        $gatewaysarray = getGatewaysArray();
        $countries = new WHMCS\Utility\Country();
        try {
            $order = WHMCS\Order\Order::with("client", "invoice")->findOrFail($id);
        } catch (Exception $e) {
            WHMCS\Terminus::getInstance()->doDie("Order not found... Exiting...");
        }
        $id = $order->id;
        $ordernum = $order->orderNumber;
        $userid = $order->userId;
        $orderClient = $order->client;
        $aInt->assertClientBoundary($userid);
        $ownerUser = $orderClient->owner();
        if ($ownerUser->isEmailVerificationEnabled() && !$ownerUser->emailVerified()) {
            echo "\n            <div class=\"verification-banner email-verification alert-warning\" role=\"alert\">\n                <i class=\"fas fa-exclamation-triangle\"></i>\n                &nbsp;\n                " . $aInt->lang("global", "emailAddressNotVerified") . "\n                <div class=\"pull-right\">\n                    <button id=\"btnResendVerificationEmail\" class=\"btn btn-default btn-sm\" data-clientid=\"" . $userid . "\" data-successmsg=\"" . AdminLang::trans("global.emailSent") . "\" data-errormsg=\"" . AdminLang::trans("global.erroroccurred") . "\">\n                        " . $aInt->lang("global", "resendEmail") . "\n                    </button>\n                </div>\n            </div>\n        ";
        }
        $date = $order->date->toAdminDateTimeFormat();
        $amount = $order->amount;
        $paymentmethod = $order->paymentMethod;
        $paymentmethod = $gatewaysarray[$paymentmethod];
        $orderstatus = $order->status;
        $showpending = get_query_val("tblorderstatuses", "showpending", ["title" => $orderstatus]);
        $client = $aInt->outputClientLink($orderClient->id, $orderClient->firstName, $orderClient->lastName, $orderClient->companyName, $orderClient->groupId);
        $address = $orderClient->address1;
        if ($orderClient->address2) {
            $address .= ", " . $orderClient->address2;
        }
        $address .= "<br />" . $orderClient->city . ", " . $orderClient->state . ", " . $orderClient->postcode . "<br />" . $orderClient->countryName;
        $ipaddress = $order->ipAddress;
        $clientemail = $orderClient->email;
        $invoiceid = $order->invoiceId;
        $nameservers = $order->nameservers;
        $nameservers = explode(",", $nameservers);
        $transfersecret = $order->transferSecret;
        $transfersecret = $transfersecret ? safe_unserialize($transfersecret) : [];
        $renewals = $order->renewals;
        $promocode = $order->promoCode;
        $promotype = $order->promoType;
        $promovalue = $order->promoValue;
        $orderdata = $order->orderData;
        $fraudmodule = $order->fraudModule;
        $fraudoutput = $order->fraudOutput;
        $notes = $order->notes;
        $contactid = $order->contactId;
        $invoicestatus = $order->invoice->status ?? NULL;
        $currency = getCurrency($userid);
        $amount = formatCurrency($amount);
        $jquerycode .= "\$(\"#ajaxchangeorderstatus\").change(function() {\n        var newstatus = \$(\"#ajaxchangeorderstatus\").val();\n        WHMCS.http.jqClient.post(\"" . $_SERVER["PHP_SELF"] . "?action=ajaxchangeorderstatus&id=" . $id . "\",\n        { status: newstatus, token: \"" . generate_token("plain") . "\" },\n       function(data) {\n         if(data == " . $id . "){\n             \$(\"#orderstatusupdated\").fadeIn().fadeOut(5000);\n             if (newstatus === " . WHMCS\Utility\Status::PENDING . "){\n                \$(\"#btnAcceptOrder\").removeAttr(\"disabled\");\n             }\n         }\n       });\n    });";
        $statusoptions = "<select id=\"ajaxchangeorderstatus\" class=\"form-control select-inline\">";
        $result = select_query("tblorderstatuses", "", "", "sortorder", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $statusoptions .= "<option style=\"color:" . $data["color"] . "\" value=\"" . $data["title"] . "\"";
            if ($orderstatus == $data["title"]) {
                $statusoptions .= " selected";
            }
            $statusoptions .= ">" . ($aInt->lang("status", strtolower($data["title"])) ? $aInt->lang("status", strtolower($data["title"])) : $data["title"]) . "</option>";
        }
        $statusoptions .= "</select>&nbsp;<span id=\"orderstatusupdated\" style=\"display:none;padding-top:14px;\"><img src=\"images/icons/tick.png\" /></span>";
        if ($invoiceid == "0") {
            $paymentstatus = "<span class=\"textgreen\">" . $aInt->lang("orders", "noinvoicedue") . "</span>";
        } else {
            if (!$invoicestatus) {
                $paymentstatus = "<span class=\"textred\">Invoice Deleted</span>";
            } else {
                if ($invoicestatus == "Paid") {
                    $paymentstatus = "<span class=\"textgreen\">" . $aInt->lang("status", "complete") . "</span>";
                } else {
                    if ($invoicestatus == "Unpaid") {
                        $paymentstatus = "<span class=\"textred\">" . $aInt->lang("status", "incomplete") . "</span>";
                    } else {
                        $paymentstatus = getInvoiceStatusColour($invoicestatus);
                    }
                }
            }
        }
        run_hook("ViewOrderDetailsPage", ["orderid" => $id, "ordernum" => $ordernum, "userid" => $userid, "amount" => $amount, "paymentmethod" => $paymentmethod, "invoiceid" => $invoiceid, "status" => $orderstatus]);
        $markup = new WHMCS\View\Markup\Markup();
        $clientnotes = [];
        $result = select_query("tblnotes", "tblnotes.*,(SELECT CONCAT(firstname,' ',lastname) FROM tbladmins WHERE tbladmins.id=tblnotes.adminid) AS adminuser", ["userid" => $userid, "sticky" => "1"], "modified", "DESC");
        while ($data = mysql_fetch_assoc($result)) {
            $markupFormat = $markup->determineMarkupEditor("client_note", "", $data["modified"]);
            $data["note"] = $markup->transform($data["note"], $markupFormat);
            $data["created"] = fromMySQLDate($data["created"], 1);
            $data["modified"] = fromMySQLDate($data["modified"], 1);
            $clientnotes[] = $data;
        }
        if ($clientnotes) {
            echo $aInt->formatImportantClientNotes($clientnotes);
        }
        echo "\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "date");
        echo "</td><td class=\"fieldarea\">";
        echo $date;
        echo "</td><td width=\"15%\" class=\"fieldlabel\">";
        echo $aInt->lang("fields", "paymentmethod");
        echo "</td><td class=\"fieldarea\">";
        echo $paymentmethod;
        echo "</td></tr>\n    <tr><td width=\"15%\" class=\"fieldlabel\">";
        echo $aInt->lang("fields", "ordernum");
        echo "</td><td class=\"fieldarea\">";
        echo $ordernum . " (ID: " . $id . ")";
        echo "</td><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "amount");
        echo "</td><td class=\"fieldarea\">";
        echo $amount;
        echo "</td></tr>\n    <tr><td class=\"fieldlabel\" rowspan=\"3\" valign=\"top\">";
        echo $aInt->lang("fields", "client");
        echo "</td><td class=\"fieldarea\" rowspan=\"3\" valign=\"top\">\n        <a href=\"clientssummary.php?userid=";
        echo $userid;
        echo "\">";
        echo $client;
        echo "</a>\n        ";
        if (isset($isEmailAddressVerified) && $isEmailAddressVerified) {
            echo "<span class=\"label label-success\">&nbsp;" . AdminLang::trans("clients.emailVerified") . "&nbsp;</span>";
        }
        echo "        <br />\n        ";
        echo $address;
        $bannedIPDate = WHMCS\Carbon::now()->addYears(3);
        echo "    </td><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "invoicenum");
        echo "</td><td class=\"fieldarea\">";
        if ($invoiceid) {
            echo "<a href=\"invoices.php?action=edit&id=" . $invoiceid . "\">" . $invoiceid . "</a>";
        } else {
            echo $aInt->lang("orders", "noInvoice");
        }
        echo "</td></tr>\n    <tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "status");
        echo "</td><td class=\"fieldarea\">";
        echo $statusoptions;
        echo "</td></tr>\n    <tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "ipaddress");
        echo "</td><td class=\"fieldarea\">";
        echo $ipaddress;
        echo " - ";
        echo WHMCS\Utility\GeoIp::getLookupHtmlAnchor($ipaddress, NULL, $aInt->lang("orders", "iplookup"));
        echo " | <a href=\"orders.php?orderip=";
        echo $ipaddress;
        echo "\">";
        echo $aInt->lang("gatewaytranslog", "filter");
        echo "</a> | <a href=\"configbannedips.php?ip=";
        echo $ipaddress;
        echo "&reason=Banned due to Orders&year=";
        echo $bannedIPDate->format("Y");
        echo "&month=";
        echo $bannedIPDate->format("m");
        echo "&day=";
        echo $bannedIPDate->format("d");
        echo "&hour=23&minutes=59";
        echo generate_token("link");
        echo "\">";
        echo $aInt->lang("orders", "ipban");
        echo "</a></td></tr>\n    <tr><td class=\"fieldlabel\" rowspan=\"2\" valign=\"top\">";
        echo AdminLang::trans("orders.placedBy");
        echo "</td><td class=\"fieldarea\" rowspan=\"2\" valign=\"top\">\n        ";
        if ($order->requestor) {
            echo "            ";
            echo AdminLang::trans("fields.user");
            echo ": ";
            echo $order->requestor->fullName;
            echo " (ID: ";
            echo $order->requestor->id;
            echo ")<br/>\n            <em>";
            echo $order->requestor->email;
            echo "</em>\n        ";
        } else {
            if ($order->adminRequestor) {
                echo "            ";
                echo AdminLang::trans("fields.admin");
                echo ": ";
                echo $order->adminRequestor->fullName;
                echo " (ID: ";
                echo $order->adminRequestor->id;
                echo ")<br/>\n            <em>";
                echo $order->adminRequestor->email;
                echo "</em>\n        ";
            } else {
                echo "            ";
                echo AdminLang::trans("global.notRecorded");
                echo "        ";
            }
        }
        echo "    </td><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "promocode");
        echo "</td><td class=\"fieldarea\">";
        if ($promocode) {
            if (strpos($promotype, "Percentage") !== false) {
                echo $promocode . " - " . $promovalue . "% " . str_replace("Percentage", "", $promotype);
            } else {
                echo $promocode . " - " . formatCurrency($promovalue) . " " . str_replace("Fixed Amount", "", $promotype);
            }
            echo "<br />";
        }
        if (is_array($orderdata)) {
            if (array_key_exists("bundleids", $orderdata) && is_array($orderdata["bundleids"])) {
                foreach ($orderdata["bundleids"] as $bid) {
                    $bundlename = get_query_val("tblbundles", "name", ["id" => $bid]);
                    if (!$bundlename) {
                        $bundlename = "Bundle Has Been Deleted";
                    }
                    echo "Bundle ID " . $bid . " - " . $bundlename . "<br />";
                }
            }
        } else {
            if (!$promocode) {
                echo "None";
            }
        }
        echo "</td>\n    <tr><td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "affiliate");
        echo "</td><td class=\"fieldarea\" id=\"affiliatefield\">";
        $affid = get_query_val("tblaffiliatesaccounts", "affiliateid", ["tblhosting.orderid" => $id], "", "", "1", "tblhosting on tblhosting.id = tblaffiliatesaccounts.relid");
        if ($affid) {
            $result = select_query("tblaffiliates", "tblaffiliates.id,firstname,lastname", ["tblaffiliates.id" => $affid], "", "", "", "tblclients ON tblclients.id=tblaffiliates.clientid");
            $data = mysql_fetch_array($result);
            $affid = $data["id"];
            $afffirstname = $data["firstname"];
            $afflastname = $data["lastname"];
            echo "<a href=\"affiliates.php?action=edit&id=" . $affid . "\">" . $afffirstname . " " . $afflastname . "</a>";
        } else {
            $noAffiliate = AdminLang::trans("orders.affnone");
            $href = "orders.php?action=affassign&orderid=" . $id;
            $affAssignTitle = AdminLang::trans("orders.affassign");
            $submitButton = AdminLang::trans("global.save");
            $manualAssign = AdminLang::trans("orders.affmanualassign");
            echo "    " . $noAffiliate . " - <a href=\"" . $href . "\"\n                        id=\"showaffassign\"\n                        class=\"open-modal\"\n                        data-modal-size=\"modal-sm\"\n                        data-modal-class=\"static\"\n                        data-modal-title=\"" . $affAssignTitle . "\"\n                        data-btn-submit-id=\"btnSaveAffiliate\"\n                        data-btn-submit-label=\"" . $submitButton . "\"\n    >" . $manualAssign . "</a>";
        }
        echo "</td></tr>\n    </table>\n\n    ";
        $userValidation = DI::make("userValidation");
        $validationUser = NULL;
        if ($userValidation->isEnabled()) {
            if ($order->requestor) {
                $validationUser = $order->requestor;
            } else {
                $validationUser = $order->client ? $order->client->owner() : NULL;
            }
        }
        if ($validationUser) {
            $validationResultsTemplate = view("admin.orders.validation.results", ["validationUser" => $validationUser]);
            echo "    <div class=\"validation-container-order\">\n        " . $validationResultsTemplate . "\n    </div>";
        }
        echo "\n    <div id=\"togglenotesbtnholder\" style=\"float:right;margin:10px;\"><input type=\"button\" value=\"";
        echo $aInt->lang("orders", $notes ? "hideNotes" : "addNotes");
        echo "\" class=\"btn btn-link\" id=\"togglenotesbtn\" /></div>\n\n    <br />\n\n    <h2>";
        echo $aInt->lang("orders", "items");
        echo "</h2>\n\n    <form method=\"post\" action=\"whois.php\" target=\"_blank\" id=\"frmWhois\">\n    <input type=\"hidden\" name=\"domain\" value=\"\" id=\"frmWhoisDomain\" />\n    </form>\n\n    <form method=\"post\" action=\"";
        echo $_SERVER["PHP_SELF"];
        echo "?action=view&id=";
        echo $id;
        echo "&activate=true\">\n\n    <div class=\"tablebg\">\n    <table class=\"datatable\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">\n    <tr><th>";
        echo $aInt->lang("fields", "item");
        echo "</th><th>";
        echo $aInt->lang("fields", "description");
        echo "</th><th>";
        echo $aInt->lang("fields", "billingcycle");
        echo "</th><th>";
        echo $aInt->lang("fields", "amount");
        echo "</th><th>";
        echo $aInt->lang("fields", "status");
        echo "</th><th>";
        echo $aInt->lang("fields", "paymentstatus");
        echo "</th></tr>\n    ";
        $serverList = [];
        $orderHasASubscription = false;
        $services = WHMCS\Service\Service::with("product", "product.productGroup", "client")->where("orderid", $id)->get();
        foreach ($services as $numericIndex => $service) {
            if (0 < strlen($service->subscriptionId)) {
                $orderHasASubscription = true;
            }
            $hostingid = $service->id;
            $domain = $service->domain;
            $billingcycle = $service->billingCycle;
            $hostingstatus = $service->domainStatus;
            $quantity = $service->qty;
            if (1 < $quantity) {
                $quantity = $quantity . " x ";
            } else {
                $quantity = "";
            }
            $firstpaymentamount = formatCurrency($service->firstPaymentAmount);
            $recurringamount = $service->recurringAmount;
            $packageid = $service->packageId;
            $server = $service->serverId;
            $regdate = $service->registrationDate;
            $nextduedate = $service->nextDueDate;
            $serverusername = $service->username;
            $serverpassword = decrypt($service->password);
            $groupname = $service->product->productGroup->name;
            $productname = $service->product->name;
            $producttype = $service->product->type;
            $welcomeemail = $service->product->welcomeEmailTemplateId;
            $autosetup = $service->product->autoSetup;
            $servertype = $service->product->module;
            $serverInterface = WHMCS\Module\Server::factoryFromModel($service);
            if ($serverInterface->getMetaDataValue("AutoGenerateUsernameAndPassword") !== false && $hostingstatus === WHMCS\Service\Service::STATUS_PENDING) {
                if (!$serverusername) {
                    $serverusername = createServerUsername($domain);
                }
                if (!$serverpassword) {
                    $serverpassword = $serverInterface->generateRandomPasswordForModule();
                }
                if ($serverusername != $service->username || $serverpassword != decrypt($service->password)) {
                    $service->username = $serverusername;
                    $service->password = encrypt($serverpassword);
                    $service->save();
                }
            }
            if ($domain && $producttype != "other") {
                $domain .= "<br />(<a href=\"https://" . $domain . "\" target=\"_blank\" style=\"color:#cc0000\">www</a> <a href=\"#\" onclick=\"\$('#frmWhoisDomain').val('" . addslashes($domain) . "');\$('#frmWhois').submit();return false\">" . $aInt->lang("domains", "whois") . "</a> <a href=\"https://www.intodns.com/" . $domain . "\" target=\"_blank\" style=\"color:#006633\">intoDNS</a>)";
            }
            echo "<tr><td align=\"center\"><a href=\"clientsservices.php?userid=" . $userid . "&id=" . $hostingid . "\"><b>";
            if ($producttype == "hostingaccount") {
                echo $aInt->lang("orders", "sharedhosting");
            } else {
                if ($producttype == "reselleraccount") {
                    echo $aInt->lang("orders", "resellerhosting");
                } else {
                    if ($producttype == "server") {
                        echo $aInt->lang("orders", "server");
                    } else {
                        if ($producttype == "other") {
                            echo $aInt->lang("orders", "other");
                        }
                    }
                }
            }
            $cycle = AdminLang::trans("billingcycles." . str_replace(["-", "account", " "], "", strtolower($billingcycle)));
            $status = AdminLang::trans("status." . strtolower($hostingstatus));
            echo "</b></a></td><td>" . $quantity . $groupname . " - " . $productname . "<br>" . $domain . "</td>" . "<td>" . $cycle . "</td><td>" . $firstpaymentamount . "</td>" . "<td>" . $status . "</td>" . "<td><b>" . $paymentstatus . "</td></tr>";
            if ($showpending && $hostingstatus == "Pending") {
                echo "<tr><td style=\"background-color:#EFF2F9;text-align:center;\" colspan=\"6\">";
                if ($servertype) {
                    echo "" . AdminLang::trans("fields.username") . ": <input type=\"text\" name=\"vars[products][" . $hostingid . "][username]\" value=\"" . $serverusername . "\" class=\"form-control input-inline input-150\"> " . AdminLang::trans("fields.password") . ": <input type=\"text\" name=\"vars[products][" . $hostingid . "][password]\" value=\"" . $serverpassword . "\" class=\"form-control input-inline input-150\"> ";
                    if ($serverInterface->getMetaDataValue("RequiresServer") !== false) {
                        echo AdminLang::trans("fields.server") . ": <select name=\"vars[products][" . $hostingid . "][server]\" class=\"form-control select-inline\"><option value=\"\">" . AdminLang::trans("global.none") . "</option>";
                        if (!in_array($servertype, $serverList)) {
                            $serverList[$servertype] = WHMCS\Product\Server::enabled()->ofModule($servertype)->get();
                        }
                        foreach ($serverList[$servertype] as $listedServer) {
                            $selectedServer = $listedServer->id == $server ? " selected" : "";
                            $serverName = $listedServer->name;
                            if ($listedServer->disabled) {
                                $serverName .= " (" . AdminLang::trans("emailtpls.disabled") . ")";
                            }
                            echo "    <option value=\"" . $listedServer->id . "\"" . $selectedServer . ">\n        " . $serverName . " (" . $listedServer->activeAccountsCount . "/" . $listedServer->maxAccounts . ")\n    </option>";
                        }
                    }
                    echo "</select> <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[products][" . $hostingid . "][runcreate]\" id=\"serviceRunModuleCreate" . $numericIndex . "\"";
                    if ($hostingstatus == "Pending" && $autosetup) {
                        echo " checked";
                    }
                    echo "> " . $aInt->lang("orders", "runmodule") . "</label> ";
                }
                echo "<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[products][" . $hostingid . "][sendwelcome]\"";
                if ($hostingstatus == "Pending" && $welcomeemail) {
                    echo " checked";
                }
                echo "> " . $aInt->lang("orders", "sendwelcome") . "</label></td></tr>";
            }
        }
        $hostingAddons = WHMCS\Service\Addon::with("productAddon", "service")->where("orderid", $id)->get();
        $lang = ["orders.addon" => AdminLang::trans("orders.addon"), "orders.addonFeature" => AdminLang::trans("orders.addonFeature"), "orders.sendwelcome" => AdminLang::trans("orders.sendwelcome"), "orders.runmodule" => AdminLang::trans("orders.runmodule"), "fields.password" => AdminLang::trans("fields.password"), "fields.username" => AdminLang::trans("fields.username"), "fields.server" => AdminLang::trans("fields.server"), "global.none" => AdminLang::trans("global.none")];
        foreach ($hostingAddons as $numericIndex => $hostingAddon) {
            $aId = $hostingAddon->id;
            $hostingId = $hostingAddon->serviceId;
            $addonId = $hostingAddon->addonId;
            $name = $hostingAddon->name;
            $domain = $hostingAddon->serviceProperties->get("Domain");
            if (!$domain) {
                $domain = $hostingAddon->service->domain;
            }
            if ($domain) {
                $domain = " - " . $domain;
            }
            if (!$name && $hostingAddon->addonId) {
                $name = $hostingAddon->productAddon->name;
            }
            $quantity = $hostingAddon->qty;
            if (1 < $quantity) {
                $quantity = $quantity . " x ";
            } else {
                $quantity = "";
            }
            $billingCycle = $hostingAddon->billingCycle;
            $addonAmount = $hostingAddon->setupFee + $hostingAddon->recurringFee;
            $addonStatus = $hostingAddon->status;
            $regDate = $hostingAddon->registrationDate;
            $nextDueDate = $hostingAddon->nextDueDate;
            $addonAmount = formatCurrency($addonAmount);
            $serverType = "";
            if ($hostingAddon->addonId) {
                $serverType = $hostingAddon->productAddon->module;
            }
            $cleanedCycleName = "billingcycles." . str_replace(["-", "account", " "], "", strtolower($billingCycle));
            $cleanedStatus = "status." . strtolower($addonStatus);
            if (!array_key_exists($cleanedCycleName, $lang)) {
                $lang[$cleanedCycleName] = AdminLang::trans($cleanedCycleName);
            }
            if (!array_key_exists($cleanedStatus, $lang)) {
                $lang[$cleanedStatus] = AdminLang::trans($cleanedStatus);
            }
            $langType = $lang["orders.addon"];
            if ($hostingAddon->provisioningType !== WHMCS\Product\Addon::PROVISIONING_TYPE_STANDARD) {
                $langType = $lang["orders.addonFeature"];
            }
            echo "    <tr>\n        <td align=\"center\">\n            <a href=\"clientsservices.php?userid=" . $userid . "&id=" . $hostingId . "&aid=" . $aId . "\"><b>" . $langType . "</b></a>\n        </td>\n        <td>" . $quantity . $name . $domain . "</td>\n        <td>" . $lang[$cleanedCycleName] . "</td>\n        <td>" . $addonAmount . "</td>\n        <td>" . $lang[$cleanedStatus] . "</td>\n        <td>" . $paymentstatus . "</td>\n    </tr>";
            if ($addonStatus == WHMCS\Utility\Status::PENDING) {
                $serverOutput = "";
                if ($serverType) {
                    $addonUsername = $addonPassword = "";
                    $serverInterface = WHMCS\Module\Server::factoryFromModel($hostingAddon);
                    if ($serverInterface->getMetaDataValue("AutoGenerateUsernameAndPassword") !== false && $hostingAddon->provisioningType === WHMCS\Product\Addon::PROVISIONING_TYPE_STANDARD) {
                        $addonUsername = $hostingAddon->serviceProperties->get("Username");
                        $addonPassword = $hostingAddon->serviceProperties->get("Password");
                        if (!$serverusername) {
                            $addonUsername = createServerUsername($domain);
                        }
                        if (!$addonPassword) {
                            $addonPassword = $serverInterface->generateRandomPasswordForModule();
                        }
                        if ($addonUsername != $hostingAddon->serviceProperties->get("Username") || $addonPassword != $hostingAddon->serviceProperties->get("Password")) {
                            $hostingAddon->serviceProperties->save(["Username" => $addonUsername, "Password" => $addonPassword]);
                        }
                    }
                    if ($hostingAddon->provisioningType === WHMCS\Product\Addon::PROVISIONING_TYPE_STANDARD) {
                        $serverOutput .= "    " . $lang["fields.username"] . ": <input type=\"text\" name=\"vars[addons][" . $aId . "][username]\" value=\"" . $addonUsername . "\" class=\"form-control input-inline input-150\">\n    " . $lang["fields.password"] . ": <input type=\"text\" name=\"vars[addons][" . $aId . "][password]\" value=\"" . $addonPassword . "\" class=\"form-control input-inline input-150\">&nbsp;";
                        if ($serverInterface->getMetaDataValue("RequiresServer") !== false) {
                            if (!in_array($serverType, $serverList)) {
                                $serverList[$serverType] = WHMCS\Product\Server::enabled()->ofModule($serverType)->get();
                            }
                            $serverListOutput = "";
                            foreach ($serverList[$serverType] as $listedServer) {
                                $selectedServer = $listedServer->id == $hostingAddon->serverId ? " selected" : "";
                                $serverName = $listedServer->name;
                                if ($listedServer->disabled) {
                                    $serverName .= " (" . AdminLang::trans("emailtpls.disabled") . ")";
                                }
                                $serverListOutput = "    <option value=\"" . $listedServer->id . "\"" . $selectedServer . ">\n        " . $serverName . " (" . $listedServer->activeAccountsCount . "/" . $listedServer->maxAccounts . ")\n    </option>";
                            }
                            $serverOutput .= "    " . $lang["fields.server"] . ": <select name=\"vars[addons][" . $aId . "][server]\" class=\"form-control select-inline\">\n        <option value=\"\">" . $lang["global.none"] . "</option>\n        " . $serverListOutput . "\n    </select>&nbsp;";
                        }
                    }
                    if ($hostingAddon->provisioningType === WHMCS\Product\Addon::PROVISIONING_TYPE_STANDARD && $serverInterface->functionExists("CreateAccount") || $hostingAddon->provisioningType !== WHMCS\Product\Addon::PROVISIONING_TYPE_STANDARD && $serverInterface->functionExists("ProvisionAddOnFeature")) {
                        $runCreatedChecked = "";
                        if ($hostingAddon->productAddon && $hostingAddon->productAddon->autoActivate) {
                            $runCreatedChecked = " checked=\"checked\"";
                        }
                        $serverOutput .= "    <label class=\"checkbox-inline\">\n        <input type=\"checkbox\" name=\"vars[addons][" . $aId . "][runcreate]\" id=\"addonRunModuleCreate" . $numericIndex . "\"" . $runCreatedChecked . ">" . $lang["orders.runmodule"] . "\n    </label>";
                    }
                }
                $welcomeEmailCheckbox = "";
                if ($hostingAddon->productAddon && $hostingAddon->productAddon->welcomeEmailTemplateId) {
                    $welcomeEmailCheckbox = " <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[addons][" . $aId . "][sendwelcome]\" checked=\"checked\"> " . $lang["orders.sendwelcome"] . "</label>";
                }
                echo "    <tr>\n        <td style=\"background-color:#EFF2F9;text-align:center;\" colspan=\"6\">\n            " . $serverOutput . "\n            " . $welcomeEmailCheckbox . "\n        </td>\n    </tr>";
            }
        }
        $result = select_query("tbldomains", "", ["orderid" => $id]);
        while ($data = mysql_fetch_array($result)) {
            if (0 < strlen($data["subscriptionid"])) {
                $orderHasASubscription = true;
            }
            $domainid = $data["id"];
            $type = $data["type"];
            $domain = $data["domain"];
            $registrationperiod = $data["registrationperiod"];
            $status = $data["status"];
            $regdate = $data["registrationdate"];
            $nextduedate = $data["nextduedate"];
            $domainamount = formatCurrency($data["firstpaymentamount"]);
            $domainregistrar = $data["registrar"];
            $dnsmanagement = $data["dnsmanagement"];
            $emailforwarding = $data["emailforwarding"];
            $idprotection = $data["idprotection"];
            $type = $aInt->lang("domains", strtolower($type));
            echo "<tr><td align=\"center\"><a href=\"clientsdomains.php?userid=" . $userid . "&domainid=" . $domainid . "\"><b>" . $aInt->lang("fields", "domain") . "</b></a></td><td>" . $type . " - " . $domain . "<br>";
            if ($contactid) {
                $result2 = select_query("tblcontacts", "firstname,lastname", ["id" => $contactid]);
                $data = mysql_fetch_array($result2);
                echo $aInt->lang("domains", "registrant") . ": <a href=\"clientscontacts.php?userid=" . $userid . "&contactid=" . $contactid . "\">" . $data["firstname"] . " " . $data["lastname"] . " (" . $contactid . ")</a><br>";
            }
            if ($dnsmanagement) {
                echo " + " . $aInt->lang("domains", "dnsmanagement") . "<br>";
            }
            if ($emailforwarding) {
                echo " + " . $aInt->lang("domains", "emailforwarding") . "<br>";
            }
            if ($idprotection) {
                echo " + " . $aInt->lang("domains", "idprotection") . "<br>";
            }
            if (isset($transfersecret[$domain]) && $transfersecret[$domain]) {
                echo sprintf("%s: %s", $aInt->lang("domains", "eppcode"), WHMCS\Input\Sanitize::makeSafeForOutput($transfersecret[$domain]));
            }
            $regperiods = 1 < $registrationperiod ? "s" : "";
            echo "</td><td>" . $registrationperiod . " " . $aInt->lang("domains", "year" . $regperiods) . "</td><td>" . $domainamount . "</td><td>" . $aInt->lang("status", strtolower(str_replace(" ", "", $status))) . "</td><td><b>" . $paymentstatus . "</td></tr>";
            if ($showpending && $status == "Pending") {
                echo "<tr><td style=\"background-color:#EFF2F9;text-align:center;\" colspan=\"6\">" . $aInt->lang("fields", "registrar") . ": " . getRegistrarsDropdownMenu("", "vars[domains][" . $domainid . "][registrar]") . " <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[domains][" . $domainid . "][sendregistrar]\" checked> " . $aInt->lang("orders", "sendtoregistrar") . "</label> <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[domains][" . $domainid . "][sendemail]\" checked> " . $aInt->lang("orders", "sendconfirmation") . "</label></td></tr>";
            }
        }
        if ($renewals) {
            $renewals = explode(",", $renewals);
            foreach ($renewals as $renewal) {
                $renewal = explode("=", $renewal);
                list($domainid, $registrationperiod) = $renewal;
                $result = select_query("tbldomains", "", ["id" => $domainid]);
                $data = mysql_fetch_array($result);
                $domainid = $data["id"];
                $type = $data["type"];
                $domain = $data["domain"];
                $registrar = $data["registrar"];
                $status = $data["status"];
                $regdate = $data["registrationdate"];
                $nextduedate = $data["nextduedate"];
                $domainamount = formatCurrency($data["recurringamount"]);
                $domainregistrar = $data["registrar"];
                $dnsmanagement = $data["dnsmanagement"];
                $emailforwarding = $data["emailforwarding"];
                $idprotection = $data["idprotection"];
                echo "<tr><td><a href=\"clientsdomains.php?userid=" . $userid . "&domainid=" . $domainid . "\"><b>" . $aInt->lang("fields", "domain") . "</b></a></td><td>" . $aInt->lang("domains", "renewal") . " - " . $domain . "<br>";
                if ($dnsmanagement) {
                    echo " + " . $aInt->lang("domains", "dnsmanagement") . "<br>";
                }
                if ($emailforwarding) {
                    echo " + " . $aInt->lang("domains", "emailforwarding") . "<br>";
                }
                if ($idprotection) {
                    echo " + " . $aInt->lang("domains", "idprotection") . "<br>";
                }
                $regperiods = 1 < $registrationperiod ? "s" : "";
                echo "</td><td>" . $registrationperiod . " " . $aInt->lang("domains", "year" . $regperiods) . "</td><td>" . $domainamount . "</td><td>" . $aInt->lang("status", strtolower($status)) . "</td><td><b>" . $paymentstatus . "</td></tr>";
                if ($showpending) {
                    $checkstatus = $registrar && !$CONFIG["AutoRenewDomainsonPayment"] ? " checked" : " disabled";
                    echo "<tr><td style=\"background-color:#EFF2F9\" colspan=\"6\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[renewals][" . $domainid . "][sendregistrar]\"" . $checkstatus . " /> Send to Registrar</label> <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[renewals][" . $domainid . "][sendemail]\"" . $checkstatus . " /> Send Confirmation Email</label></td></tr>";
                }
            }
        }
        if (substr($promovalue, 0, 2) == "DR") {
            $domainid = substr($promovalue, 2);
            $result = select_query("tbldomains", "", ["id" => $domainid]);
            $data = mysql_fetch_array($result);
            $domainid = $data["id"];
            $type = $data["type"];
            $domain = $data["domain"];
            $registrar = $data["registrar"];
            $registrationperiod = $data["registrationperiod"];
            $status = $data["status"];
            $regdate = $data["registrationdate"];
            $nextduedate = $data["nextduedate"];
            $domainamount = formatCurrency($data["firstpaymentamount"]);
            $domainregistrar = $data["registrar"];
            $dnsmanagement = $data["dnsmanagement"];
            $emailforwarding = $data["emailforwarding"];
            $idprotection = $data["idprotection"];
            echo "<tr><td><a href=\"clientsdomains.php?userid=" . $userid . "&domainid=" . $domainid . "\"><b>" . $aInt->lang("fields", "domain") . "</b></a></td><td>" . $aInt->lang("domains", "renewal") . " - " . $domain . "<br>";
            if ($dnsmanagement) {
                echo " + " . $aInt->lang("domains", "dnsmanagement") . "<br>";
            }
            if ($emailforwarding) {
                echo " + " . $aInt->lang("domains", "emailforwarding") . "<br>";
            }
            if ($idprotection) {
                echo " + " . $aInt->lang("domains", "idprotection") . "<br>";
            }
            $regperiods = 1 < $registrationperiod ? "s" : "";
            echo "</td><td>" . $registrationperiod . " " . $aInt->lang("domains", "year" . $regperiods) . "</td><td>" . $domainamount . "</td><td>" . $aInt->lang("status", strtolower($status)) . "</td><td><b>" . $paymentstatus . "</td></tr>";
            if ($showpending) {
                echo "<tr><td style=\"background-color:#EFF2F9\" colspan=\"6\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[domains][" . $domainid . "][sendregistrar]\"";
                if ($registrar && !$CONFIG["AutoRenewDomainsonPayment"]) {
                    echo " checked";
                } else {
                    echo " disabled";
                }
                echo "> Send to Registrar</label> <label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"vars[domains][" . $domainid . "][sendemail]\"";
                if ($registrar) {
                    echo " checked";
                } else {
                    echo " disabled";
                }
                echo "> Send Confirmation Email</label></td></tr>";
            }
        }
        foreach (WHMCS\Service\Upgrade\Upgrade::where("orderid", $id)->get() as $upgrade) {
            if ($upgrade->type == "package") {
                $newValue = explode(",", $upgrade->newValue);
                list($upgrade->newValue, $upgrade->newCycle) = $newValue;
                $upgradeType = AdminLang::trans("orders.productUpgrade");
                $description = $upgrade->originalProduct->productGroup->name . " - " . $upgrade->originalProduct->name . " => " . $upgrade->newProduct->name;
                if ($upgrade->service->domain) {
                    $description .= "<br>" . $upgrade->service->domain;
                }
                $manageLink = "clientsservices.php?userid=" . $upgrade->userId . "&id=" . $upgrade->entityId;
            } else {
                if ($upgrade->type == "configoptions") {
                    $upgradeType = AdminLang::trans("orders.optionsUpgrade");
                    $result2 = select_query("tblhosting", "tblproducts.name AS productname,domain,tblhosting.userid", ["tblhosting.id" => $upgrade->relid], "", "", "", "tblproducts ON tblproducts.id=tblhosting.packageid");
                    $data = mysql_fetch_array($result2);
                    $productname = $data["productname"];
                    $domain = $data["domain"];
                    $userId = $data["userid"];
                    if (!$upgrade->userid) {
                        $upgrade->userid = $userId;
                        $upgrade->save();
                    }
                    $tempvalue = explode("=>", $upgrade->originalValue);
                    list($configid, $oldoptionid) = $tempvalue;
                    $result2 = select_query("tblproductconfigoptions", "", ["id" => $configid]);
                    $data = mysql_fetch_array($result2);
                    $configname = $data["optionname"];
                    if (strpos($configname, "|") !== false) {
                        $configname = explode("|", $configname);
                        $configname = $configname[1];
                    }
                    $optiontype = $data["optiontype"];
                    if ($optiontype == 1 || $optiontype == 2) {
                        $result2 = select_query("tblproductconfigoptionssub", "", ["id" => $oldoptionid]);
                        $data = mysql_fetch_array($result2);
                        $oldoptionname = $data["optionname"];
                        if (strpos($oldoptionname, "|") !== false) {
                            $oldoptionname = explode("|", $oldoptionname);
                            $oldoptionname = $oldoptionname[1];
                        }
                        $result2 = select_query("tblproductconfigoptionssub", "", ["id" => $upgrade->newValue]);
                        $data = mysql_fetch_array($result2);
                        $newoptionname = $data["optionname"];
                        if (strpos($newoptionname, "|") !== false) {
                            $newoptionname = explode("|", $newoptionname);
                            $newoptionname = $newoptionname[1];
                        }
                    } else {
                        if ($optiontype == 3) {
                            if ($oldoptionid) {
                                $oldoptionname = "Yes";
                                $newoptionname = "No";
                            } else {
                                $oldoptionname = "No";
                                $newoptionname = "Yes";
                            }
                        } else {
                            if ($optiontype == 4) {
                                $result2 = select_query("tblproductconfigoptionssub", "", ["configid" => $configid]);
                                $data = mysql_fetch_array($result2);
                                $optionname = $data["optionname"];
                                if (strpos($optionname, "|") !== false) {
                                    $optionname = explode("|", $optionname);
                                    $optionname = $optionname[1];
                                }
                                $oldoptionname = $oldoptionid;
                                $newoptionname = $upgrade->newValue . " x " . $optionname;
                            }
                        }
                    }
                    $description = $productname . " - " . $domain . "<br>" . $configname . ": " . $oldoptionname . " => " . $newoptionname;
                    $manageLink = "clientsservices.php?userid=" . $upgrade->userId . "&id=" . $upgrade->relid;
                } else {
                    if ($upgrade->type == "service") {
                        $newQuantity = $oldQuantity = $upgrade->service->qty;
                        if (is_array($orderdata) && !empty($orderdata["upgrades"][$upgrade->id])) {
                            $newQuantity = $orderdata["upgrades"][$upgrade->id];
                        }
                        if ($newQuantity <= 1 && $oldQuantity <= 1) {
                            $newQuantity = $oldQuantity = "";
                        } else {
                            $newQuantity .= " x ";
                            $oldQuantity .= " x ";
                        }
                        $upgradeType = AdminLang::trans("orders.productUpgrade");
                        $description = $upgrade->originalProduct->productGroup->name . " - " . $oldQuantity . $upgrade->originalProduct->name . " => " . $newQuantity . $upgrade->newProduct->name;
                        if ($upgrade->service->domain) {
                            $description .= "<br>" . $upgrade->service->domain;
                        }
                        $manageLink = "clientsservices.php?userid=" . $upgrade->userId . "&id=" . $upgrade->entityId;
                    } else {
                        if ($upgrade->type == "addon") {
                            $newQuantity = $oldQuantity = $upgrade->addon->qty;
                            if (is_array($orderdata) && !empty($orderdata["upgrades"][$upgrade->id])) {
                                $newQuantity = $orderdata["upgrades"][$upgrade->id];
                            }
                            if ($newQuantity <= 1 && $oldQuantity <= 1) {
                                $newQuantity = $oldQuantity = "";
                            } else {
                                $newQuantity .= " x ";
                                $oldQuantity .= " x ";
                            }
                            $upgradeType = AdminLang::trans("orders.addonUpgrade");
                            $description = $oldQuantity . $upgrade->originalAddon->name . " => " . $newQuantity . $upgrade->newAddon->name;
                            $manageLink = "clientsservices.php?userid=" . $upgrade->userId . "&aid=" . $upgrade->entityId;
                        }
                    }
                }
            }
            echo "<tr>\n                <td align=\"center\"><a href=\"" . $manageLink . "\"><b>" . $upgradeType . "</b></a></td>\n                <td><a href=\"" . $manageLink . "\">" . $description . "</a><br>" . (in_array($upgrade->type, ["service", "addon"]) ? "<small>New Recurring Amount: " . formatCurrency($upgrade->newRecurringAmount) . " - Credit Amount: " . formatCurrency($upgrade->creditAmount) . "<br>" . "Calculation based on " . $upgrade->daysRemaining . " unused days of " . $upgrade->totalDaysInCycle . " totals days in the current billing cycle.</small></td>" : "") . "\n                <td>" . $aInt->lang("billingcycles", (new WHMCS\Billing\Cycles())->getNormalisedBillingCycle($upgrade->newCycle)) . "</td>\n                <td>" . formatCurrency($upgrade->upgradeAmount) . "</td>\n                <td>" . $aInt->lang("status", strtolower($upgrade->status)) . "</td>\n                <td><b>" . $paymentstatus . "</td>\n            </tr>";
        }
        if ($orderHasASubscription) {
            $cancelOrderButton = " cancel-order-sub";
            $buttons = [["title" => "Cancel"], ["title" => "OK", "onclick" => "window.location=\"" . $_SERVER["PHP_SELF"] . "?action=view&id=" . $id . "&cancel=true" . generate_token("link") . "\";"], ["title" => "Also Cancel Subscription", "onclick" => "window.location=\"" . $_SERVER["PHP_SELF"] . "?action=view&id=" . $id . "&cancel=true&cancelsub=true" . generate_token("link") . "\";"]];
            echo $aInt->modal("CancelOrder", "Cancel Order", $aInt->lang("orders", "confirmcancel"), $buttons);
            $fraudOrderButton = " fraud-order-sub";
            $buttons = [["title" => "Cancel"], ["title" => "OK", "onclick" => "window.location=\"" . $_SERVER["PHP_SELF"] . "?action=view&id=" . $id . "&fraud=true" . generate_token("link") . "\";"], ["title" => "Also Cancel Subscription", "onclick" => "window.location=\"" . $_SERVER["PHP_SELF"] . "?action=view&id=" . $id . "&fraud=true&cancelsub=true" . generate_token("link") . "\";"]];
            echo $aInt->modal("FraudOrder", "Set as Fraud", $aInt->lang("orders", "confirmfraud"), $buttons);
        } else {
            $cancelOrderButton = " cancel-order";
            $fraudOrderButton = " fraud-order";
        }
        if (canOrderBeDeleted($id, $status)) {
            $function = "delete";
        } else {
            $function = "cancelDelete";
        }
        echo "    <tr><th colspan=\"3\" style=\"text-align:right;\">";
        echo $aInt->lang("fields", "totaldue");
        echo ":&nbsp;</th><th>";
        echo $amount;
        echo "</th><th colspan=\"2\"></th></tr>\n    </table>\n    </div>\n\n    <div class=\"btn-container\">\n    <button type=\"submit\"\n            class=\"btn btn-success\"";
        if (!$showpending) {
            echo " disabled=\"disabled\"";
        }
        echo "            id=\"btnAcceptOrder\"\n    >\n        <i class=\"fas fa-check-circle\"></i>\n        ";
        echo $aInt->lang("orders", "accept");
        echo "    </button>\n    <input type=\"button\" value=\"";
        echo AdminLang::trans("orders.cancel");
        echo "\"\n           class=\"btn btn-default";
        echo $cancelOrderButton;
        echo "\"\n           data-order-id=\"";
        echo $id;
        echo "\"";
        echo $orderstatus == "Cancelled" ? " disabled=\"disabled\"" : "";
        echo " />\n    <input type=\"button\" value=\"";
        echo AdminLang::trans("orders.cancelrefund");
        echo "\"\n           class=\"btn btn-default cancel-refund-order\" data-order-id=\"";
        echo $id;
        echo "\"\n        ";
        echo !$invoiceid || $invoicestatus == "Refunded" ? " disabled=\"disabled\"" : "";
        echo " />\n    <input type=\"button\" value=\"";
        echo AdminLang::trans("orders.fraud");
        echo "\"\n           class=\"btn btn-default";
        echo $fraudOrderButton;
        echo "\"\n           data-order-id=\"";
        echo $id;
        echo "\"";
        echo $orderstatus == "Fraud" ? " disabled=\"disabled\"" : "";
        echo " />\n    <input type=\"button\" value=\"";
        echo AdminLang::trans("orders.pending");
        echo "\" class=\"btn btn-default pending-order\"\n           data-order-id=\"";
        echo $id;
        echo "\" ";
        echo $orderstatus == "Pending" ? " disabled=\"disabled\"" : "";
        echo " />\n    <input type=\"button\" value=\"";
        echo AdminLang::trans("orders.delete");
        echo "\" class=\"btn btn-danger delete-order\"\n           data-order-id=\"";
        echo $id;
        echo "\" data-delete-type=\"";
        echo $function;
        echo "\"/>\n    </div>\n\n    ";
        if (trim($nameservers[0])) {
            echo "<p><b>" . $aInt->lang("orders", "nameservers") . "</b></p><p>";
            foreach ($nameservers as $key => $ns) {
                if (trim($ns)) {
                    echo $aInt->lang("domains", "nameserver") . " " . ($key + 1) . ": " . $ns . "<br />";
                }
            }
            echo "</p>";
        }
        echo "<div class=\"bottom-margin-20 clearfix\" id=\"notesholder\"" . ($notes ? "" : " style=\"display:none\"") . ">\n    <h2>" . $aInt->lang("orders", "notes") . "</h2>\n        <div class=\"col-sm-8 col-sm-offset-1\">\n            <textarea rows=\"4\" id=\"notes\" class=\"form-control\">" . $notes . "</textarea>\n        </div>\n        <div class=\"col-sm-2\">\n            <br />\n            <input type=\"button\" value=\"" . $aInt->lang("orders", "updateSaveNotes") . "\" id=\"savenotesbtn\" class=\"btn btn-primary btn-sm btn-block\" />\n        </div>\n    </div>";
        if ($fraudmodule && !in_array($fraudmodule, WHMCS\Module\Fraud::SKIP_MODULES)) {
            $fraud = new WHMCS\Module\Fraud();
            if ($fraud->load($fraudmodule)) {
                $fraudresults = $fraud->processResultsForDisplay($id, $fraudoutput);
                if ($fraudoutput) {
                    echo "<div class=\"clearfix\"><h2 class=\"pull-left\">" . AdminLang::trans("orders.fraudcheckresults") . "</h2>";
                    if ($fraudmodule == "maxmind" || $fraud->getMetaDataValue("SupportsRechecks")) {
                        echo "<button type=\"button\" class=\"btn btn-sm btn-primary pull-right\" id=\"btnRerunFraud\">" . AdminLang::trans("orders.fraudcheckrerun") . "</button>";
                        $jquerycode .= "\$(\"#btnRerunFraud\").click(function () {\n            \$(this).prop(\"disabled\", true).html('<i class=\"fas fa-spin fa-spinner\"></i> Performing Check...');\n            WHMCS.http.jqClient.post(\"orders.php\", { action: \"view\", rerunfraudcheck: \"true\", orderid: " . $id . ", token: \"" . generate_token("plain") . "\" },\n            function(data){\n                \$(\"#fraudresults\").html(data.output);\n                \$(\"#btnRerunFraud\").prop(\"disabled\", false).html(\"" . AdminLang::trans("orders.fraudcheckrerun") . "\");\n            }, \"json\");\n            return false;\n        });";
                    }
                    echo "</div>";
                    if ($fraudresults) {
                        echo "<div id=\"fraudresults\">" . $fraudresults . "</div>";
                    }
                }
            }
        } else {
            if ($fraudmodule) {
                switch ($fraudmodule) {
                    case "CREDIT":
                        $languageString = "orders.noFraudCheckAsCredit";
                        break;
                    case "SKIPPED":
                    default:
                        $languageString = "orders.fraudCheckSkippedDescription";
                        $text = "<strong>" . AdminLang::trans("orders.fraudCheckSkippedTitle") . "</strong>";
                        $text .= "<br>" . AdminLang::trans($languageString);
                        echo "<div id=\"fraudresults\">" . WHMCS\View\Helper::alert($text) . "</div>";
                }
            }
        }
        echo "\n    </form>\n\n    ";
        $jquerycode .= "\n    \$(\"#togglenotesbtn\").click(function() {\n        \$(\"#notesholder\").slideToggle(\"slow\", function() {\n            toggletext = \$(\"#togglenotesbtn\").attr(\"value\");\n    \n            notesVisible = \$(\"#notes\").is(\":visible\");\n    \n            hideNotesText = \"" . $aInt->lang("orders", "hideNotes") . "\";\n            addNotesText = \"" . $aInt->lang("orders", "addNotes") . "\";\n    \n            \$(\"#togglenotesbtn\").fadeOut(\"fast\",function(){ \$(\"#togglenotesbtn\").attr(\"value\", notesVisible ? hideNotesText : addNotesText); \$(\"#togglenotesbtn\").fadeIn(); });\n    \n            \$(\"#shownotesbtnholder\").slideToggle();\n        });\n        return false;\n    });\n    \$(\"#savenotesbtn\").click(function() {\n        WHMCS.http.jqClient.post(\"?action=view&id=" . $id . "\", { updatenotes: true, notes: \$('#notes').val(), token: \"" . generate_token("plain") . "\" });\n        \$(\"#savenotesbtn\").attr(\"value\",\"" . $aInt->lang("orders", "notesSaved") . "\");\n        return false;\n    });\n    \$(\"#notes\").keyup(function() {\n        \$(\"#savenotesbtn\").attr(\"value\",\"" . $aInt->lang("orders", "saveNotes") . "\");\n    });";
    }
}
$jquerycode .= "var deleteType = '',\n    orderId = '';\njQuery(document).on('click', '.delete-order', function() {\n    deleteType = jQuery(this).data('delete-type');\n    orderId = jQuery(this).data('order-id');\n    jQuery('#' + deleteType).modal('show');\n}).on('click', 'button[id\$=\"Delete-ok\"],button[id\$=\"delete-ok\"]', function(e) {\n    e.preventDefault();\n    var url = 'orders.php?action=' + deleteType + '&id=' + orderId + '&token=' + csrfToken;\n    window.location.replace(url)\n}).on('click', '.cancel-order-sub', function() {\n    jQuery('#modalCancelOrder').modal('show');\n}).on('click', '.cancel-order', function() {\n    orderId = jQuery(this).data('order-id');\n    jQuery('#cancel').modal('show');\n}).on('click', 'button[id\$=\"cancel-ok\"]', function(e) {\n    e.preventDefault();\n    var url = 'orders.php?action=view&id=' + orderId + '&cancel=true&token=' + csrfToken;\n    window.location.replace(url)\n}).on('click', '.fraud-order-sub', function() {\n    jQuery('#modalFraudOrder').modal('show');\n}).on('click', '.fraud-order', function() {\n    orderId = jQuery(this).data('order-id');\n    jQuery('#fraud').modal('show');\n}).on('click', 'button[id\$=\"fraud-ok\"]', function(e) {\n    e.preventDefault();\n    var url = 'orders.php?action=view&id=' + orderId + '&fraud=true&token=' + csrfToken;\n    window.location.replace(url)\n}).on('click', '.cancel-refund-order', function() {\n    orderId = jQuery(this).data('order-id');\n    jQuery('#cancelRefund').modal('show');\n}).on('click', 'button[id\$=\"cancelRefund-ok\"]', function(e) {\n    e.preventDefault();\n    var url = 'orders.php?action=view&id=' + orderId + '&cancelrefund=true&token=' + csrfToken;\n    window.location.replace(url)\n}).on('click', '.pending-order', function() {\n    orderId = jQuery(this).data('order-id');\n    jQuery('#pending').modal('show');\n}).on('click', 'button[id\$=\"pending-ok\"]', function(e) {\n    e.preventDefault();\n    var url = 'orders.php?action=view&id=' + orderId + '&pending=true&token=' + csrfToken;\n    window.location.replace(url)\n});";
$aInt->jquerycode = $jquerycode;
echo WHMCS\View\Helper::confirmationModal("delete", AdminLang::trans("orders.confirmdelete"));
echo WHMCS\View\Helper::confirmationModal("cancel", AdminLang::trans("orders.confirmcancel"));
echo WHMCS\View\Helper::confirmationModal("pending", AdminLang::trans("orders.confirmpending"));
echo WHMCS\View\Helper::confirmationModal("fraud", AdminLang::trans("orders.confirmfraud"));
echo WHMCS\View\Helper::confirmationModal("cancelRefund", AdminLang::trans("orders.confirmcancelrefund"));
echo WHMCS\View\Helper::confirmationModal("cancelDelete", AdminLang::trans("orders.confirmCancelDelete"));
echo WHMCS\View\Helper::confirmationModal("acceptMass", AdminLang::trans("orders.acceptconfirm"));
echo WHMCS\View\Helper::confirmationModal("cancelMass", AdminLang::trans("orders.confirmcancel"));
echo WHMCS\View\Helper::confirmationModal("deleteMass", AdminLang::trans("orders.deleteconfirm"));
echo WHMCS\View\Helper::confirmationModal("messageMass", AdminLang::trans("orders.sendMessage"));
$content = $aInt->getFlashAsInfobox();
$content .= ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->display();
