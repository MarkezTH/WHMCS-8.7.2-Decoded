<?php

define("ADMINAREA", true);
require "../init.php";
$action = App::getFromRequest("action");
if ($action == "edit") {
    $reqperm = "Edit Transaction";
} else {
    $reqperm = "List Transactions";
}
$aInt = new WHMCS\Admin($reqperm);
$aInt->title = $aInt->lang("transactions", "title");
$aInt->sidebar = "billing";
$aInt->icon = "transactions";
$aInt->requiredFiles(["gatewayfunctions", "invoicefunctions"]);
$jscode = "";
$jquerycode = "";
if ($action == "add") {
    check_token("WHMCS.admin.default");
    checkPermission("Add Transaction");
    $paymentMethod = $whmcs->get_req_var("paymentmethod");
    $transactionID = $whmcs->get_req_var("transid");
    $amountIn = (double) $whmcs->get_req_var("amountin");
    $fees = (double) $whmcs->get_req_var("fees");
    $date = $whmcs->get_req_var("date");
    $amountOut = (double) $whmcs->get_req_var("amountout");
    $description = $whmcs->get_req_var("description");
    $addCredit = $whmcs->get_req_var("addcredit");
    $currency = $whmcs->get_req_var("currency");
    $client = $whmcs->get_req_var("client");
    $cleanedInvoiceIDs = [];
    $userInputInvoiceIDs = trim($whmcs->get_req_var("invoiceids"));
    if ($userInputInvoiceIDs) {
        $userInputInvoiceIDs = explode(",", $userInputInvoiceIDs);
        foreach ($userInputInvoiceIDs as $tmpInvID) {
            $tmpInvID = trim($tmpInvID);
            if (is_numeric($tmpInvID)) {
                $cleanedInvoiceIDs[] = (int) $tmpInvID;
            }
        }
    }
    $validationErrorDescription = [];
    if ($client) {
        $currency = 0;
    }
    if ($amountIn < 0) {
        $validationErrorDescription[] = $aInt->lang("transactions", "amountInLessThanZero") . PHP_EOL;
    }
    if ($amountOut < 0) {
        $validationErrorDescription[] = $aInt->lang("transactions", "amountOutLessThanZero") . PHP_EOL;
    }
    if (count($cleanedInvoiceIDs) == 0 && !$description) {
        $validationErrorDescription[] = $aInt->lang("transactions", "invoiceIdOrDescriptionRequired") . PHP_EOL;
    }
    if (count($cleanedInvoiceIDs)) {
        $confirmCount = WHMCS\Billing\Invoice::whereIn("id", $cleanedInvoiceIDs)->count();
        if ($confirmCount === 0) {
            $validationErrorDescription[] = AdminLang::trans("invoices.invalidInvoiceIDs", [":invoiceIds" => implode(", ", $cleanedInvoiceIDs)]) . PHP_EOL;
        }
    }
    if ((!$amountOut || $amountOut == 0) && (!$amountIn || $amountIn == 0) && (!$fees || $fees == 0)) {
        $validationErrorDescription[] = $aInt->lang("transactions", "amountInOutOrFeeRequired") . PHP_EOL;
    }
    if ($amountIn && $fees && $amountIn < $fees) {
        $validationErrorDescription[] = $aInt->lang("transactions", "feeMustBeLessThanAmountIn") . PHP_EOL;
    }
    if ($amountIn && $fees && $fees < 0) {
        $validationErrorDescription[] = $aInt->lang("transactions", "amountInFeeMustBePositive") . PHP_EOL;
    }
    if (0 < $amountIn && 0 < $amountOut) {
        $validationErrorDescription[] = AdminLang::trans("transactions.amountInAndOutSpecified") . PHP_EOL;
    }
    if ($addCredit && 0 < $amountOut) {
        $validationErrorDescription[] = $aInt->lang("transactions", "amountOutCannotBeUsedWithAddCredit") . PHP_EOL;
    }
    if ($addCredit && 0 < count($cleanedInvoiceIDs)) {
        $validationErrorDescription[] = $aInt->lang("transactions", "invoiceIDAndCreditInvalid") . PHP_EOL;
    }
    if ($transactionID && !isUniqueTransactionID($transactionID, $paymentMethod)) {
        $validationErrorDescription[] = $aInt->lang("transactions", "requireUniqueTransaction") . PHP_EOL;
    }
    if ($validationErrorDescription) {
        WHMCS\Cookie::set("ValidationError", ["invoiceid" => App::getFromRequest("invoiceids"), "transid" => $transactionID, "amountin" => $amountIn, "fees" => $fees, "paymentmethod" => $paymentMethod, "date" => $date, "amountout" => $amountOut, "description" => $description, "addcredit" => $addCredit, "validationError" => $validationErrorDescription, "userid" => $client, "currency" => $currency]);
        redir(["validation" => true, "tab" => 2]);
    }
    if (count($cleanedInvoiceIDs) <= 1) {
        $invoiceid = count($cleanedInvoiceIDs) ? $cleanedInvoiceIDs[0] : "";
        if ($transid && !isUniqueTransactionID($transactionID, $paymentMethod)) {
            WHMCS\Cookie::set("DuplicateTransaction", ["invoiceid" => $invoiceid, "transid" => $transactionID, "amountin" => $amountIn, "fees" => $fees, "paymentmethod" => $paymentMethod, "date" => $date, "amountout" => $amountOut, "description" => $description, "addcredit" => $addCredit, "userid" => $client, "currency" => $currency]);
            redir(["duplicate" => true, "tab" => 2]);
        }
        addTransaction($client, $currency, $description, $amountIn, $fees, $amountOut, $paymentMethod, $transactionID, $invoiceid, $date);
        if ($client && $addCredit && (!is_int($invoiceid) || $invoiceid == 0)) {
            if ($transactionID) {
                $description .= " (" . $aInt->lang("transactions", "transid") . ": " . $transactionID . ")";
            }
            insert_query("tblcredit", ["clientid" => $client, "date" => toMySQLDate($date), "description" => $description, "amount" => $amountIn]);
            update_query("tblclients", ["credit" => "+=" . $amountIn], ["id" => (int) $client]);
        }
        if (is_int($invoiceid)) {
            $totalPaid = get_query_val("tblaccounts", "SUM(amountin)-SUM(amountout)", ["invoiceid" => $invoiceid]);
            $invoiceData = get_query_vals("tblinvoices", "status, total", ["id" => $invoiceid]);
            $balance = $invoiceData["total"] - $totalPaid;
            if ($balance <= 0 && $invoiceData["status"] == "Unpaid") {
                processPaidInvoice($invoiceid, "", $date);
            }
        }
    } else {
        if (1 < count($cleanedInvoiceIDs)) {
            $invoicestotal = WHMCS\Billing\Invoice::whereIn("id", $cleanedInvoiceIDs)->sum("total");
            $totalleft = $amountIn;
            $fees = round($fees / count($cleanedInvoiceIDs), 2);
            $missingInvoices = [];
            $date = WHMCS\Carbon::createFromAdminDateFormat($date);
            foreach ($cleanedInvoiceIDs as $invoiceid) {
                if (0 < $totalleft) {
                    try {
                        $invoice = WHMCS\Billing\Invoice::findOrFail($invoiceid);
                        $balance = $invoice->balance;
                        if (!(valueIsZero($balance) || $balance < 0)) {
                            $paymentAmount = $balance;
                            if ($balance < $totalleft) {
                                $invoice->addPayment($balance, $transactionID, $fees, $paymentMethod, false, $date);
                                $totalleft -= $balance;
                            } else {
                                $invoice->addPayment($totalleft, $transactionID, $fees, $paymentMethod, false, $date);
                                $totalleft = 0;
                            }
                        }
                    } catch (Throwable $t) {
                        $missingInvoices[] = $invoiceid;
                    }
                }
            }
            if ($totalleft && !empty($invoice)) {
                $invoice->addPayment($totalleft, $transactionID, $fees, $paymentMethod, false, $date);
            }
            if (count($missingInvoices)) {
                WHMCS\Session::start();
                WHMCS\FlashMessages::add(AdminLang::trans("invoices.massTransactionMissingInvoice", [":invoiceIds" => implode(", ", $missingInvoices)]), "warning");
                WHMCS\Session::release();
            }
        }
    }
    redir("added=true");
}
if ($action == "save") {
    check_token("WHMCS.admin.default");
    checkPermission("Edit Transaction");
    if ($client) {
        $currency = 0;
    }
    $date = toMySQLDate($date);
    $values = ["userid" => $client, "date" => $date, "description" => $description, "amountin" => $amountin, "fees" => $fees, "amountout" => $amountout, "gateway" => $paymentmethod, "transid" => $transid, "invoiceid" => $invoiceid, "currency" => $currency];
    update_query("tblaccounts", $values, ["id" => $id]);
    logActivity("Modified Transaction - Transaction ID: " . $id, $client);
    redir("saved=true");
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Transaction");
    $transaction = WHMCS\Billing\Payment\Transaction::find($id);
    $userId = $transaction->clientId;
    $transaction->delete();
    logActivity("Deleted Transaction - Transaction ID: " . $id, $userId);
    redir("deleted=true");
}
ob_start();
if (!$action) {
    if (App::getFromRequest("added")) {
        WHMCS\Session::start();
        $flashMessage = WHMCS\FlashMessages::get();
        WHMCS\Session::release();
        $messageType = "success";
        $message = "<strong>" . AdminLang::trans("transactions.transactionadded") . "</strong><br>" . AdminLang::trans("transactions.transactionaddedinfo");
        if ($flashMessage) {
            $messageType = $flashMessage["type"];
            $message .= "<br>" . $flashMessage["text"];
        }
        echo WHMCS\View\Helper::alert($message, $messageType);
    }
    if (App::getFromRequest("saved")) {
        infoBox($aInt->lang("transactions", "transactionupdated"), $aInt->lang("transactions", "transactionupdatedinfo"));
    }
    if (App::getFromRequest("deleted")) {
        infoBox($aInt->lang("transactions", "transactiondeleted"), $aInt->lang("transactions", "transactiondeletedinfo"));
    }
    $duplicate = App::getFromRequest("duplicate");
    $validation = App::getFromRequest("validation");
    if (!empty($duplicate) || !empty($validation)) {
        if (!empty($duplicate)) {
            infobox($aInt->lang("transactions", "duplicate"), $aInt->lang("transactions", "requireUniqueTransaction"), "error");
            $cookieName = "DuplicateTransaction";
        } else {
            $cookieName = "ValidationError";
        }
        $repopulateData = WHMCS\Cookie::get($cookieName, true);
        $invoiceid = $repopulateData["invoiceid"] ? WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["invoiceid"]) : "";
        $transid = self::makeSafeForOutput($repopulateData["transid"]);
        $amountin = $repopulateData["amountin"] ? WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["amountin"]) : "0.00";
        $fees = $repopulateData["fees"] ? WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["fees"]) : "0.00";
        $paymentmethod = WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["paymentmethod"]);
        $date2 = WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["date"]);
        $amountout = $repopulateData["amountout"] ? WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["amountout"]) : "0.00";
        $description = WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["description"]);
        $addcredit = $repopulateData["addcredit"] ? " CHECKED" : "";
        $userid = $repopulateData["userid"] ? WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["userid"]) : "";
        $currency = $repopulateData["currency"] ? WHMCS\Input\Sanitize::makeSafeForOutput($repopulateData["currency"]) : "";
        if (!empty($validation)) {
            $errorMessage = "";
            foreach ($repopulateData["validationError"] as $validationError) {
                $errorMessage .= WHMCS\Input\Sanitize::makeSafeForOutput($validationError) . "<br />";
            }
            if ($errorMessage) {
                infobox($aInt->lang("global", "validationerror"), $errorMessage, "error");
            }
        }
        WHMCS\Cookie::delete($cookieName);
    }
    echo $infobox;
    $aInt->deleteJSConfirm("doDelete", "transactions", "deletesure", "?action=delete&id=");
    echo $aInt->beginAdminTabs([$aInt->lang("global", "searchfilter"), $aInt->lang("transactions", "add")]);
    $range = App::getFromRequest("range");
    if (!$range) {
        $today = WHMCS\Carbon::today();
        $lastMonth = $today->copy()->subDays(29)->toAdminDateFormat();
        $range = $lastMonth . " - " . $today->toAdminDateFormat();
    }
    $show = App::getFromRequest("show");
    $filterdescription = App::getFromRequest("filterdescription");
    $filtertransid = App::getFromRequest("filtertransid");
    $amount = App::getFromRequest("amount");
    $userid = App::getFromRequest("userid");
    $amountin = App::getFromRequest("amountin");
    $description = App::getFromRequest("description");
    $fees = App::getFromRequest("fees");
    $transid = App::getFromRequest("transid");
    $amountout = App::getFromRequest("amountout");
    $invoiceid = App::getFromRequest("invoiceid");
    $addcredit = App::getFromRequest("addcredit");
    echo "\n<form method=\"post\" action=\"transactions.php\"><input type=\"hidden\" name=\"filter\" value=\"true\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"15%\" class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("transactions.show");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <select name=\"show\" class=\"form-control select-inline\">\n                <option value=\"\">\n                    ";
    echo AdminLang::trans("transactions.allactivity");
    echo "                </option>\n                <option value=\"received\"";
    echo $show == "received" ? " selected=\"selected\"" : "";
    echo ">\n                    ";
    echo AdminLang::trans("transactions.preceived");
    echo "                </option>\n                <option value=\"sent\"";
    echo $show == "sent" ? " selected=\"selected\"" : "";
    echo ">\n                    ";
    echo AdminLang::trans("transactions.psent");
    echo "                </option>\n            </select>\n        </td>\n        <td width=\"15%\" class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("fields.daterange");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <div class=\"form-group date-picker-prepend-icon\">\n                <label for=\"inputRange\" class=\"field-icon\">\n                    <i class=\"fal fa-calendar-alt\"></i>\n                </label>\n                <input id=\"inputRange\"\n                       type=\"text\"\n                       name=\"range\"\n                       value=\"";
    echo $range;
    echo "\"\n                       class=\"form-control date-picker-search\"\n                />\n            </div>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\" width=\"15%\">\n            ";
    echo AdminLang::trans("fields.description");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\"\n                   name=\"filterdescription\"\n                   class=\"form-control input-300\"\n                   value=\"";
    echo $filterdescription;
    echo "\"\n            >\n        </td>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("fields.amount");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"amount\" class=\"form-control input-100\" value=\"";
    echo $amount;
    echo "\">\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("fields.transid");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\"\n                   name=\"filtertransid\"\n                   class=\"form-control input-300\"\n                   value=\"";
    echo $filtertransid;
    echo "\"\n            >\n        </td>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("fields.paymentmethod");
    echo "        </td>\n        <td class=\"fieldarea\">\n            ";
    echo paymentMethodsSelection(AdminLang::trans("global.any"));
    echo "        </td>\n    </tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("global", "searchfilter");
    echo "\" class=\"btn btn-default\" />\n</div>\n\n</form>\n\n";
    echo $aInt->nextAdminTab();
    echo "\n";
    $date2 = getTodaysDate();
    echo "<form method=\"post\" action=\"";
    echo $whmcs->getPhpSelf();
    echo "?action=add\" name=\"calendarfrm\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr>\n    <td width=\"15%\" class=\"fieldlabel\">\n        ";
    echo $aInt->lang("fields", "date");
    echo "    </td>\n    <td class=\"fieldarea\">\n        <div class=\"form-group date-picker-prepend-icon\">\n            <label for=\"inputDate\" class=\"field-icon\">\n                <i class=\"fal fa-calendar-alt\"></i>\n            </label>\n            <input id=\"inputDate\"\n                   type=\"text\"\n                   name=\"date\"\n                   value=\"";
    echo $date2;
    echo "\"\n                   class=\"form-control date-picker-single\"\n            />\n        </div>\n    </td>\n    <td width=\"15%\" class=\"fieldlabel\">\n        ";
    echo $aInt->lang("currencies", "currency");
    echo "    </td>\n    <td class=\"fieldarea\">\n        <select name=\"currency\" class=\"form-control select-inline\">";
    $result = select_query("tblcurrencies", "", "", "code", "ASC");
    while ($data = mysql_fetch_array($result)) {
        echo "<option value=\"" . $data["id"] . "\"";
        if (empty($currency) && $data["default"] || !empty($currency) && $currency == $data["id"]) {
            echo " selected";
        }
        echo ">" . $data["code"] . "</option>";
    }
    echo "</select> (";
    echo $aInt->lang("transactions", "nonclientonly");
    echo ")</td></tr>\n<tr>\n    <td width=\"15%\" class=\"fieldlabel\">";
    echo $aInt->lang("transactions", "relclient");
    echo "</td>\n    <td class=\"fieldarea\">";
    echo $aInt->clientsDropDown($userid, false, "client", true);
    echo "</td>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("transactions", "amountin");
    echo "</td>\n    <td class=\"fieldarea\"><input type=\"text\" name=\"amountin\" class=\"form-control input-100\" value=\"";
    echo $amountin;
    echo "\"></td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "description");
    echo "</td>\n    <td class=\"fieldarea\"><input type=\"text\" name=\"description\" class=\"form-control input-300\" value=\"";
    echo $description;
    echo "\"></td>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("transactions", "fees");
    echo "</td>\n    <td class=\"fieldarea\"><input type=\"text\" name=\"fees\" class=\"form-control input-100\" value=\"";
    echo $fees;
    echo "\"></td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "transid");
    echo "</td>\n    <td class=\"fieldarea\"><input type=\"text\" name=\"transid\" class=\"form-control input-300\" value=\"";
    echo $transid;
    echo "\"></td>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("transactions", "amountout");
    echo "</td>\n    <td class=\"fieldarea\"><input type=\"text\" name=\"amountout\" class=\"form-control input-100\" value=\"";
    echo $amountout;
    echo "\"></td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("transactions", "invoiceids");
    echo "</td>\n    <td class=\"fieldarea\">\n        <input type=\"text\" name=\"invoiceids\" class=\"form-control input-150 input-inline\" value=\"";
    echo $invoiceid;
    echo "\">\n        ";
    echo $aInt->lang("transactions", "commaseparated");
    echo "    </td>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "credit");
    echo "</td>\n    <td class=\"fieldarea\">\n        <label class=\"checkbox-inline\">\n            <input type=\"checkbox\" name=\"addcredit\"";
    echo $addcredit;
    echo ">\n            ";
    echo $aInt->lang("invoices", "refundtypecredit");
    echo "        </label>\n    </td>\n</tr>\n<tr>\n    <td class=\"fieldlabel\">";
    echo $aInt->lang("fields", "paymentmethod");
    echo "</td>\n    <td class=\"fieldarea\">";
    echo paymentMethodsSelection($aInt->lang("global", "none"));
    echo "</td>\n    <td class=\"fieldlabel\"></td>\n    <td class=\"fieldarea\"></td>\n</tr>\n</table>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("transactions", "add");
    echo "\" class=\"btn btn-default\" />\n</div>\n\n</form>\n\n";
    echo $aInt->endAdminTabs();
    echo "\n";
    $aInt->sortableTableInit("date", "DESC");
    $transactions = WHMCS\Billing\Payment\Transaction::with("client");
    $query = "";
    $startDateQueryString = "";
    $endDateQueryString = "";
    $where = [];
    if ($show == "received") {
        $where[] = "tblaccounts.amountin>0";
        $transactions->where("tblaccounts.amountin", ">", 0);
    } else {
        if ($show == "sent") {
            $where[] = "tblaccounts.amountout>0";
            $transactions->where("tblaccounts.amountout", ">", 0);
        }
    }
    if ($amount) {
        $where[] = "(tblaccounts.amountin='" . db_escape_string($amount) . "' OR tblaccounts.amountout='" . db_escape_string($amount) . "')";
        $transactions->where(function (Illuminate\Database\Eloquent\Builder $query) use($amount) {
            $query->where("tblaccounts.amountin", $amount)->orWhere("tblaccounts.amountout", $amount);
        });
    }
    $range = WHMCS\Carbon::parseDateRangeValue($range);
    $startDate = $range["from"];
    $endDate = $range["to"];
    if ($startDate) {
        $startDateQueryString = "tblaccounts.date>='" . $startDate->toDateTimeString() . "'";
        $where[] = $startDateQueryString;
        $transactions->whereDate("tblaccounts.date", ">=", $startDate);
    }
    if ($endDate) {
        $endDateQueryString = "tblaccounts.date<='" . $endDate->toDateTimeString() . "'";
        $where[] = $endDateQueryString;
        $transactions->whereDate("tblaccounts.date", "<=", $endDate);
    }
    if ($filtertransid) {
        $where[] = "tblaccounts.transid='" . db_escape_string($filtertransid) . "'";
        $transactions->where("tblaccounts.transid", $filtertransid);
    }
    if ($paymentmethod) {
        $where[] = "tblaccounts.gateway='" . db_escape_string($paymentmethod) . "'";
        $transactions->where("tblaccounts.gateway", $paymentmethod);
    }
    if ($filterdescription) {
        $where[] = "tblaccounts.description LIKE '%" . db_escape_string($filterdescription) . "%'";
        $transactions->where("tblaccounts.description", "like", "%" . $filterdescription . "%");
    }
    if (count($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    if (checkPermission("View Income Totals", true)) {
        $currency = getCurrency();
        $daysInFilter = $startDate->diffInDays($endDate->copy()->addSecond());
        if (90 < $daysInFilter) {
            $groupByFormat = "%Y-%m";
            $dateAction = "addMonth";
            $comparisonPeriod = "previous " . $daysInFilter . " days";
        } else {
            $groupByFormat = "%Y-%m-%d";
            $dateAction = "addDay";
            $comparisonPeriod = "last " . $daysInFilter . " days";
        }
        $fullquery = "SELECT SUM(amountin/rate),SUM(fees/rate),SUM(amountout/rate) FROM tblaccounts " . $query;
        $result = full_query($fullquery);
        $totals = mysql_fetch_array($result);
        $fullquery = "SELECT SUM(amountin/rate),SUM(fees/rate),SUM(amountout/rate) FROM tblaccounts " . str_replace([$startDateQueryString, $endDateQueryString], ["tblaccounts.date>='" . $startDate->copy()->subDays($daysInFilter)->toDateTimeString() . "'", "tblaccounts.date<='" . $endDate->copy()->subDays($daysInFilter)->toDateTimeString() . "'"], $query);
        $result = full_query($fullquery);
        $previoustotals = mysql_fetch_array($result);
        $salesChange = $totals[0] - $previoustotals[0];
        $salesChange = 0 < $previoustotals[0] ? round($salesChange / $previoustotals[0] * 100, 0) : 0;
        $feesChange = $totals[1] - $previoustotals[1];
        $feesChange = 0 < $previoustotals[1] ? round($feesChange / $previoustotals[1] * 100, 0) : 0;
        $expenditureChange = $totals[2] - $previoustotals[2];
        $expenditureChange = 0 < $previoustotals[2] ? round($expenditureChange / $previoustotals[2] * 100, 0) : 0;
        $values = [];
        $fullquery = "SELECT date_format(date, '" . $groupByFormat . "') as date, SUM((amountin-fees-amountout)/rate) as netamount FROM tblaccounts " . $query . " GROUP BY date_format(date, '" . $groupByFormat . "')";
        $result = full_query($fullquery);
        while ($data = mysql_fetch_array($result)) {
            $values[$data[0]] = format_as_currency($data[1]);
        }
        $phpGroupByFormat = str_replace("%", "", $groupByFormat);
        $chartLabels = [];
        $chartValues = [];
        $labelDate = $startDate->copy();
        $dateLabel = $labelDate->format($phpGroupByFormat);
        $chartLabels[] = $dateLabel;
        $chartValues[] = isset($values[$dateLabel]) ? $values[$dateLabel] : 0;
        while ($labelDate->format($phpGroupByFormat) != $endDate->format($phpGroupByFormat)) {
            $dateLabel = $labelDate->{$dateAction}()->format($phpGroupByFormat);
            $chartLabels[] = $dateLabel;
            $chartValues[] = isset($values[$dateLabel]) ? $values[$dateLabel] : 0;
        }
    }
    echo "\n<div class=\"transactions-header\">\n    <div class=\"row\">\n        <div class=\"col-lg-9\">\n            <div id=\"transactionChartWrapper\">\n                <canvas id=\"transactionChart\" height=\"250\"></canvas>\n            </div>\n        </div>\n        <div class=\"col-lg-3\">\n            <div class=\"row\">\n                <div class=\"col-sm-4 col-lg-12\">\n                    <div class=\"stat\">\n                        <div class=\"icon\">\n                            <i class=\"fas fa-coins\"></i>\n                        </div>\n                        ";
    echo AdminLang::trans("transactions.totalincome");
    echo "                        <span>";
    echo formatCurrency($totals[0]);
    echo "</span>\n                        <small class=\"";
    echo $salesChange < 0 ? "down" : "up";
    echo "\">\n                            <i class=\"fas fa-arrow-";
    echo $salesChange < 0 ? "down" : "up";
    echo "\"></i>\n                            ";
    echo $salesChange;
    echo "%\n                            ";
    echo AdminLang::trans("global.from");
    echo "                            ";
    echo $comparisonPeriod;
    echo "                        </small>\n                    </div>\n                </div>\n                <div class=\"col-sm-4 col-lg-12\">\n                    <div class=\"stat\">\n                        <div class=\"icon\">\n                            <i class=\"fas fa-dollar-sign\"></i>\n                        </div>\n                        ";
    echo AdminLang::trans("transactions.totalfees");
    echo "                        <span>";
    echo formatCurrency($totals[1]);
    echo "</span>\n                        <small class=\"";
    echo $feesChange < 0 ? "down" : "up";
    echo "\">\n                            <i class=\"fas fa-arrow-";
    echo $feesChange < 0 ? "down" : "up";
    echo "\"></i>\n                            ";
    echo $feesChange;
    echo "%\n                            ";
    echo AdminLang::trans("global.from");
    echo "                            ";
    echo $comparisonPeriod;
    echo "                        </small>\n                    </div>\n                </div>\n                <div class=\"col-sm-4 col-lg-12\">\n                    <div class=\"stat\">\n                        <div class=\"icon\">\n                            <i class=\"fas fa-calculator\"></i>\n                        </div>\n                        ";
    echo AdminLang::trans("transactions.totalexpenditure");
    echo "                        <span>";
    echo formatCurrency($totals[2]);
    echo "</span>\n                        <small class=\"";
    echo $expenditureChange < 0 ? "down" : "up";
    echo "\">\n                            <i class=\"fas fa-arrow-";
    echo $expenditureChange < 0 ? "down" : "up";
    echo "\"></i>\n                            ";
    echo $expenditureChange;
    echo "%\n                            ";
    echo AdminLang::trans("global.from");
    echo "                            ";
    echo $comparisonPeriod;
    echo "                        </small>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n    ";
    $gatewayOutput = "";
    if ($aInt->hasPermission("View Gateway Balances")) {
        $gatewayOutput = WHMCS\Gateways::gatewayBalancesTotalsView(true, WHMCS\Http\Message\ServerRequest::fromGlobals());
    }
    if ($gatewayOutput) {
        $routePath = routePath("admin-billing-gateway-balance-totals");
        $jscode .= "var gatewayBalanceDataLoaded = false,\n    gatewayBalanceForceLoad = 0;\nfunction loadGatewayBalances()\n{\n    WHMCS.http.jqClient.jsonPost(\n        {\n            url: '" . $routePath . "',\n            data: {\n                token: csrfToken,\n                force: gatewayBalanceForceLoad\n            },\n            success: function (data) {\n                var body = jQuery('#gatewayBalanceTotals');\n                if (data.body.length) {\n                    body.replaceWith(data.body);\n                    gatewayBalanceDataLoaded = true;\n                    if (body.not(':visible')) {\n                        body.slideDown();\n                    }\n                }\n            },\n            always: function () {\n                if (!gatewayBalanceDataLoaded) {\n                    jQuery('#gatewayBalanceTotals').slideUp();\n                }\n                jQuery('#balanceRefreshBtn').removeClass('disabled').prop('disabled', false)\n                    .find('i.fa-sync').removeClass('fa-spin');\n                gatewayBalanceForceLoad = 0;\n            }\n        }\n    );\n}";
    }
    echo $gatewayOutput;
    echo "</div>\n\n<script>\n\$(document).ready(function() {\n    var chartObject = null;\n    var windowResizeTimeoutId = null;\n\n    \$(window).resize(function() {\n        if (windowResizeTimeoutId) {\n            clearTimeout(windowResizeTimeoutId);\n            windowResizeTimeoutId = null;\n        }\n\n        windowResizeTimeoutId = setTimeout(function() {\n            if (typeof chartObject === 'object') {\n                chartObject.resize(false);\n            }\n        }, 250);\n    });\n\n    var lineData = {\n        type: 'line',\n        data: {\n            labels: [\"";
    echo is_array($chartLabels) ? implode("\",\"", $chartLabels) : "";
    echo "\"],\n            datasets: [{\n                data: [";
    echo is_array($chartValues) ? implode(",", $chartValues) : "";
    echo "],\n                backgroundColor: \"rgba(93,197,96,0.5)\",\n                borderColor: \"rgba(93,197,96,1)\",\n                pointBackgroundColor: \"rgba(93,197,96,1)\",\n                pointBorderColor: \"#fff\"\n            }]\n        },\n        options: {\n            responsiveAnimationDuration: 500,\n            legend: false,\n            scales: {\n                yAxes: [\n                    {\n                        scaleLabel: {\n                            display: true,\n                            labelString: '";
    echo AdminLang::trans("transactions.netrevenue");
    echo " (";
    echo $currency["code"];
    echo ")'\n                        },\n                        ticks: {\n                            beginAtZero: true\n                        }\n                    }\n                ]\n            }\n        }\n    };\n\n    var canvas = document.getElementById(\"transactionChart\");\n    var parent = document.getElementById('transactionChartWrapper');\n\n    canvas.width = parent.offsetWidth;\n    canvas.height = parent.offsetHeight;\n\n    var ctx = \$(\"#transactionChart\");\n    var chartObject = new Chart(ctx, lineData);\n});\n</script>\n\n";
    $gatewaysarray = getGatewaysArray();
    $numrows = $transactions->count();
    $transactions->orderByDesc("tblaccounts.date")->orderByDesc("tblaccounts.id")->offset((int) ($page * $limit))->limit($limit);
    $tabledata = [];
    $tableformurl = "";
    $tableformbuttons = "";
    foreach ($transactions->get() as $transaction) {
        $id = $transaction->id;
        $userid = $transaction->userid;
        $currency = $transaction->currency;
        $date = fromMySQLDate($transaction->date);
        $description = $transaction->description;
        $amountin = $transaction->amountin;
        $fees = $transaction->fees;
        $amountout = $transaction->amountout;
        $gateway = $transaction->gateway;
        $transid = $transaction->transid;
        $invoiceid = $transaction->invoiceid;
        $firstname = $transaction->client->firstname;
        $lastname = $transaction->client->lastname;
        $companyname = $transaction->client->companyname;
        $groupid = $transaction->client->groupid;
        $currencyid = $transaction->client->currency;
        $clientlink = $userid ? $aInt->outputClientLink($userid, $firstname, $lastname, $companyname, $groupid) : "-";
        $currency = $userid ? getCurrency(NULL, $currencyid) : getCurrency(NULL, $currency);
        $amountin = formatCurrency($amountin);
        $fees = formatCurrency($fees);
        $amountout = formatCurrency($amountout);
        if ($invoiceid != "0") {
            $description .= " (<a href=\"invoices.php?action=edit&id=" . $invoiceid . "\">#" . $invoiceid . "</a>)";
        }
        if ($transid != "") {
            try {
                $gatewayInterface = WHMCS\Module\Gateway::factory($gateway);
                if ($gatewayInterface->functionExists("TransactionInformation")) {
                    $transid = WHMCS\Billing\Payment\Transaction::find($transaction->id)->getLink();
                }
            } catch (Throwable $t) {
            }
            $description .= "<br>Trans ID: " . $transid;
        }
        $gateway = $gatewaysarray[$gateway];
        $tabledata[] = [$clientlink, $date, $gateway, $description, $amountin, $fees, $amountout, "<a href=\"?action=edit&id=" . $id . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $id . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    echo $aInt->sortableTable([$aInt->lang("fields", "clientname"), $aInt->lang("fields", "date"), $aInt->lang("fields", "paymentmethod"), $aInt->lang("fields", "description"), $aInt->lang("transactions", "amountin"), $aInt->lang("transactions", "fees"), $aInt->lang("transactions", "amountout"), "", ""], $tabledata, $tableformurl, $tableformbuttons);
} else {
    if ($action == "edit") {
        $result = select_query("tblaccounts", "", ["id" => $id]);
        $data = mysql_fetch_array($result);
        $id = $data["id"];
        $userid = $data["userid"];
        $date = $data["date"];
        $date = fromMySQLDate($date);
        $description = $data["description"];
        $amountin = $data["amountin"];
        $fees = $data["fees"];
        $amountout = $data["amountout"];
        $paymentmethod = $data["gateway"];
        $transid = $data["transid"];
        $invoiceid = $data["invoiceid"];
        $currency = $data["currency"];
        if (!$id) {
            $aInt->gracefulExit($aInt->lang("transactions", "notfound"));
        }
        echo "\n<h2>";
        echo $aInt->lang("transactions", "edit");
        echo "</h2>\n\n<form method=\"post\" action=\"";
        echo $whmcs->getPhpSelf();
        echo "?action=save&id=";
        echo $id;
        echo "\" name=\"calendarfrm\">\n\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"15%\" class=\"fieldlabel\">";
        echo $aInt->lang("fields", "date");
        echo "</td>\n        <td class=\"fieldarea\">\n            <div class=\"form-group date-picker-prepend-icon\">\n                <label for=\"inputDate\" class=\"field-icon\">\n                    <i class=\"fal fa-calendar-alt\"></i>\n                </label>\n                <input id=\"inputDate\"\n                       type=\"text\"\n                       name=\"date\"\n                       value=\"";
        echo $date;
        echo "\"\n                       class=\"form-control date-picker-single\"\n                />\n            </div>\n        </td>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("currencies", "currency");
        echo "</td>\n        <td class=\"fieldarea\">";
        if ($userid) {
            echo "---";
        } else {
            echo "            <select name=\"currency\" class=\"form-control select-inline\">";
            $currencies = WHMCS\Database\Capsule::table("tblcurrencies")->orderBy("code", "asc")->get()->all();
            foreach ($currencies as $dropdownCurrencyData) {
                echo "<option value=\"" . $dropdownCurrencyData->id . "\"";
                if (!$currency && $dropdownCurrencyData->default || $currency && $currency == $dropdownCurrencyData->id) {
                    echo " selected";
                }
                echo ">" . $dropdownCurrencyData->code . "</option>";
            }
            echo "</select> ";
            echo "(" . $aInt->lang("transactions", "nonclientonly") . ")";
        }
        echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("transactions", "relclient");
        echo "</td>\n        <td class=\"fieldarea\">";
        echo $aInt->clientsDropDown($userid, false, "client", true);
        echo "</td>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("transactions", "amountin");
        echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"amountin\" class=\"form-control input-100\" value=\"";
        echo $amountin;
        echo "\"></td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "description");
        echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"description\" class=\"form-control input-300\" value=\"";
        echo $description;
        echo "\"></td>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("transactions", "fees");
        echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"fees\" class=\"form-control input-100\" value=\"";
        echo $fees;
        echo "\"></td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "transid");
        echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"transid\" class=\"form-control input-300\" value=\"";
        echo $transid;
        echo "\"></td>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("transactions", "amountout");
        echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"amountout\" class=\"form-control input-100\" value=\"";
        echo $amountout;
        echo "\"></td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "invoiceid");
        echo "</td>\n        <td class=\"fieldarea\"><input type=\"text\" name=\"invoiceid\" class=\"form-control input-150\" value=\"";
        echo $invoiceid;
        echo "\"></td>\n        <td class=\"fieldlabel\">";
        echo $aInt->lang("fields", "paymentmethod");
        echo "</td>\n        <td class=\"fieldarea\">";
        echo paymentMethodsSelection($aInt->lang("global", "none"));
        echo "</td>\n    </tr>\n</table>\n\n<p align=\"center\"><input type=\"submit\" value=\"";
        echo $aInt->lang("global", "savechanges");
        echo "\" class=\"button btn btn-default\" /></p>\n\n</form>\n\n";
    }
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
