<?php

namespace WHMCS;

class Invoice
{
    protected $pdf = NULL;
    protected $invoiceId = 0;
    protected $data = [];
    protected $output = [];
    protected $totalBalance = 0;
    protected $gateway = NULL;
    protected $gatewayModulesWhereCallbacksMightBeDelayed = ["paypal", "paypalcheckout"];
    protected $outputLanguage = "";

    public function __construct($invoiceId = 0)
    {
        if ($invoiceId) {
            $this->setID($invoiceId);
        }
    }

    public function setID($invoiceId)
    {
        $this->invoiceId = (int) $invoiceId;
        $loaded = $this->loadData();
        return $loaded;
    }

    public function getID()
    {
        return $this->invoiceId;
    }

    protected function loadData($force = true)
    {
        if (!$force && count($this->data)) {
            return false;
        }
        try {
            $invoiceModel = Billing\Invoice::findOrFail($this->invoiceId);
            $this->invoiceId = $invoiceModel->id;
            $invoiceData = $invoiceModel->toArray();
            $invoiceData["model"] = $invoiceModel;
            $invoiceData["invoiceid"] = $invoiceData["id"];
            $invoiceData["invoicenumorig"] = $invoiceData["invoicenum"];
            if (!$invoiceData["invoicenum"]) {
                $invoiceData["invoicenum"] = $invoiceData["id"];
            }
            $invoiceData["paymentmodule"] = $invoiceData["paymentmethod"];
            $invoiceData["paymentmethod"] = $invoiceData["paymentGatewayName"];
            $invoiceData["rawDueDate"] = $invoiceData["duedate"];
            $invoiceData["payMethod"] = $invoiceModel->payMethod;
            $payMethodDisplayName = "";
            if ($invoiceModel->payMethod) {
                $payment = $invoiceModel->payMethod->payment;
                if ($payment instanceof Payment\Contracts\PayMethodAdapterInterface) {
                    $payMethodDisplayName = $payment->getDisplayName();
                }
            }
            $invoiceData["paymethoddisplayname"] = $payMethodDisplayName;
            $invoiceData["amountpaid"] = $invoiceData["amountPaid"];
            $invoiceData["balance"] = format_as_currency($invoiceData["balance"]);
            $this->data = $invoiceData;
            return true;
        } catch (\Exception $e) {
            $this->invoiceId = 0;
            throw new Exception\Module\NotServicable("Invalid invoice id provided");
        }
    }

    public function getData($var = "")
    {
        $this->loadData(false);
        return isset($this->data[$var]) ? $this->data[$var] : $this->data;
    }

    public function getStatuses()
    {
        return [Billing\Invoice::STATUS_DRAFT, Billing\Invoice::STATUS_UNPAID, Billing\Invoice::STATUS_PAID, Billing\Invoice::STATUS_CANCELLED, Billing\Invoice::STATUS_REFUNDED, Billing\Invoice::STATUS_COLLECTIONS, Billing\Invoice::STATUS_PAYMENT_PENDING];
    }

    public function getModel()
    {
        $model = $this->getData("model");
        if ($model instanceof Billing\Invoice) {
            return $model;
        }
        return NULL;
    }

    protected function formatForOutput()
    {
        global $currency;
        $whmcs = \DI::make("app");
        $this->output = $this->data;
        $array = ["date", "duedate", "datepaid"];
        foreach ($array as $v) {
            if (!isset($this->output[$v])) {
                $this->output[$v] = "";
            }
            $this->output[$v] = substr($this->output[$v], 0, 10) != "0000-00-00" ? fromMySQLDate($this->output[$v], $v == "datepaid" ? "1" : "0", 1) : "";
        }
        $this->output["datecreated"] = $this->output["date"];
        $this->output["datedue"] = $this->output["duedate"];
        $currency = getCurrency($this->getData("userid"));
        $array = ["subtotal", "credit", "tax", "tax2", "total", "balance", "amountpaid"];
        foreach ($array as $v) {
            $this->output[$v] = formatCurrency($this->output[$v]);
        }
        if ($snapshotData = $this->getClientSnapshotData()) {
            $clientsdetails = $snapshotData["clientsdetails"];
            $customfields = [];
            foreach ($snapshotData["customfields"] as $data) {
                $data["fieldname"] = CustomField::getFieldName($data["id"], $data["fieldname"], $clientsdetails["language"]);
                $customfields[] = $data;
            }
        } else {
            if (!function_exists("getClientsDetails")) {
                require ROOTDIR . "/includes/clientfunctions.php";
            }
            $clientsdetails = getClientsDetails($this->getData("userid"), "billing");
            $customfields = [];
            $result = select_query("tblcustomfields", "tblcustomfields.id,tblcustomfields.fieldname,(SELECT value FROM tblcustomfieldsvalues WHERE tblcustomfieldsvalues.fieldid=tblcustomfields.id AND tblcustomfieldsvalues.relid=" . (int) $this->getData("userid") . ") AS value", ["type" => "client", "showinvoice" => "on"]);
            while ($data = mysql_fetch_assoc($result)) {
                if ($data["value"]) {
                    $data["fieldname"] = CustomField::getFieldName($data["id"], $data["fieldname"], $clientsdetails["language"]);
                    $customfields[] = $data;
                }
            }
        }
        $clientsdetails["country"] = $clientsdetails["countryname"];
        if (Billing\Tax\Vat::isTaxIdDisabled()) {
            $clientsdetails["tax_id"] = "";
        }
        $this->output["clientsdetails"] = $clientsdetails;
        $this->output["customfields"] = $customfields;
        if (!function_exists("getTaxRate")) {
            \App::load_function("invoice");
        }
        for ($i = 1; $i <= 2; $i++) {
            $taxLevel = $i === 1 ? "" : $i;
            if ($this->data["taxrate" . $taxLevel] != 0) {
                $taxData = getTaxRate($i, $clientsdetails["tax_state"], $clientsdetails["tax_countrycode"]);
                $taxName = $taxData["name"] != "" ? $taxData["name"] : \Lang::trans("invoicestax");
                $taxRate = $this->output["taxrate" . $taxLevel] == 0 ? "0" : $this->output["taxrate" . $taxLevel];
            } else {
                $taxName = "";
                $taxRate = "0";
            }
            $this->output["taxname" . $taxLevel] = $taxName;
            $this->output["taxrate" . $taxLevel] = $taxRate;
        }
        $this->output["taxIdLabel"] = \Lang::trans(Billing\Tax\Vat::getLabel());
        $this->output["statuslocale"] = \Lang::trans("invoices" . strtolower($this->output["status"]));
        if ($this->output["status"] == "Payment Pending") {
            $this->output["statuslocale"] = \Lang::trans("invoicesPaymentPending");
        }
        if ($this->isProformaInvoice()) {
            $this->output["pagetitle"] = \Lang::trans("proformainvoicenumber") . $this->getData("invoicenum");
        } else {
            $this->output["pagetitle"] = \Lang::trans("invoicenumber") . $this->getData("invoicenum");
        }
        $this->output["payto"] = nl2br(Config\Setting::getValue("InvoicePayTo"));
        $this->output["notes"] = nl2br($this->output["notes"]);
        $this->output["subscrid"] = get_query_val("tblinvoiceitems", "tblhosting.subscriptionid", "tblinvoiceitems.type='Hosting' AND tblinvoiceitems.invoiceid=" . $this->getData("id") . " AND tblhosting.subscriptionid!=''", "tblhosting`.`id", "ASC", "", "tblhosting ON tblhosting.id=tblinvoiceitems.relid");
        $clienttotals = get_query_vals("tblinvoices", "SUM(credit),SUM(total)", ["userid" => $this->getData("userid"), "status" => "Unpaid"]);
        $unpaidInvoiceIds = Database\Capsule::table("tblinvoices")->where("status", "Unpaid")->where("userid", (int) $this->getData("userid"))->pluck("id")->all();
        $alldueinvoicespayments = 0;
        if ($unpaidInvoiceIds) {
            $alldueinvoicespayments = get_query_val("tblaccounts", "SUM(amountin-amountout)", "tblaccounts.invoiceid IN (" . db_build_in_array($unpaidInvoiceIds) . ")");
        }
        $this->output["clienttotaldue"] = formatCurrency($clienttotals[0] + $clienttotals[1]);
        $this->output["clientpreviousbalance"] = formatCurrency($clienttotals[1] - $this->getData("total"));
        $this->output["clientbalancedue"] = formatCurrency($clienttotals[1] - $alldueinvoicespayments);
        $lastpayment = get_query_vals("tblaccounts", "(amountin-amountout),transid,gateway", ["invoiceid" => $this->getData("id")], "id", "DESC");
        if (is_array($lastpayment)) {
            $this->output["lastpaymentamount"] = formatCurrency($lastpayment[0]);
            $gateway = new Module\Gateway();
            if ($gateway->load($lastpayment[2]) && $gateway->functionExists("formatTransactionIdForDisplay")) {
                $this->output["lastpaymenttransid"] = $gateway->call("formatTransactionIdForDisplay", ["transactionId" => $lastpayment[1]]);
            } else {
                $this->output["lastpaymenttransid"] = $lastpayment[1];
            }
        } else {
            $this->output["lastpaymentamount"] = formatCurrency(NULL);
            $this->output["lastpaymenttransid"] = NULL;
        }
        $this->output["taxCode"] = Config\Setting::getValue("TaxCode");
    }

    public function getOutput($pdf = false)
    {
        $this->loadData(false);
        if ($this->outputLanguage) {
            $existingLanguage = swapLang($this->outputLanguage);
        } else {
            $existingLanguage = getUsersLang($this->data["userid"]);
        }
        $this->formatForOutput();
        if ($pdf) {
            $this->makePDFFriendly();
        }
        if ($existingLanguage) {
            swapLang($existingLanguage);
        }
        return $this->output;
    }

    public function initialiseGatewayAndParams($passedInGatewayModuleName = "")
    {
        $this->gateway = new Module\Gateway();
        if ($passedInGatewayModuleName) {
            $gatewaymodule = $passedInGatewayModuleName;
        } else {
            $gatewaymodule = $this->getData("paymentmodule");
        }
        if (!$this->gateway->isActiveGateway($gatewaymodule)) {
            if ($passedInGatewayModuleName) {
                throw new Exception\Module\NotActivated("Gateway Module '" . Input\Sanitize::makeSafeForOutput($gatewaymodule) . "' Not Activated");
            }
            $gatewaymodule = $this->gateway->getFirstAvailableGateway();
            if (!$gatewaymodule) {
                throw new Exception\Information("No Gateway Modules are Currently Active");
            }
            update_query("tblinvoices", ["paymentmethod" => $gatewaymodule], ["id" => $this->getID()]);
        }
        if (!$this->gateway->load($gatewaymodule)) {
            logActivity("Gateway Module '" . $gatewaymodule . "' is Missing");
            throw new Exception\Module\NotServicable("Gateway Module '" . Input\Sanitize::makeSafeForOutput($gatewaymodule) . "' is Missing or Invalid");
        }
        $params = $this->gateway->loadSettings();
        if (!$params) {
            throw new Exception\Module\InvalidConfiguration("No Gateway Settings Found");
        }
        $params["companyname"] = Config\Setting::getValue("CompanyName");
        $params["systemurl"] = \App::getSystemURL();
        $params["langpaynow"] = \Lang::trans("invoicespaynow");
        return $params;
    }

    public function getGatewayInvoiceParams($params = [])
    {
        if (count($params) < 1) {
            try {
                $params = $this->initialiseGatewayAndParams();
            } catch (Exception $e) {
                logActivity("Failed to initialise payment gateway module: " . $e->getMessage());
                throw new Exception\Fatal("Could not initialise payment gateway. Please contact support.");
            }
        }
        $invoiceid = $this->getID();
        $userid = $this->getData("userid");
        $invoicenum = $this->getData("invoicenum");
        $balance = $this->getData("balance");
        $invoiceModel = Billing\Invoice::find($invoiceid);
        $currency = getCurrency($userid);
        $invoice_currency_id = $currency["id"];
        $invoice_currency_code = $currency["code"];
        $params["invoiceid"] = $invoiceid;
        $params["invoicenum"] = $invoicenum;
        $params["amount"] = format_as_currency($balance);
        $params["currency"] = $invoice_currency_code;
        $params["currencyId"] = $invoice_currency_id;
        $params["description"] = sprintf("%s - %s%s", $params["companyname"], \Lang::trans("invoicenumber"), $invoicenum ?: $invoiceid);
        $params["returnurl"] = $params["systemurl"] . "viewinvoice.php?id=" . $invoiceid;
        $params["dueDate"] = $this->getData("duedate");
        $params["cart"] = $invoiceModel->cart();
        $client = new Client($userid);
        $billingContactId = NULL;
        $payMethod = NULL;
        if ($invoiceModel) {
            $payMethod = $invoiceModel->payMethod;
            if (!$payMethod) {
                $payMethods = $invoiceModel->client->payMethods()->get();
                if ($invoiceModel->paymentGateway) {
                    $payMethod = $payMethods->forGateway($invoiceModel->paymentGateway)->first();
                    if ($payMethod) {
                        $invoiceModel->payMethod()->associate($payMethod);
                        $invoiceModel->save();
                    }
                }
            }
            if ($payMethod) {
                $billingContactId = $payMethod->getContactId();
            }
        }
        if (is_null($billingContactId) && isset($params["billingcontactid"])) {
            $billingContactId = $params["billingcontactid"];
        }
        if (is_null($billingContactId)) {
            $billingContactId = "billing";
        }
        $clientsdetails = $client->getDetails($billingContactId);
        $clientsdetails["state"] = $clientsdetails["statecode"];
        if ($payMethod) {
            $payment = $payMethod->payment;
            if ($payment instanceof Payment\Contracts\RemoteTokenDetailsInterface) {
                $clientsdetails["gatewayid"] = $payment->getRemoteToken();
            }
        }
        if (strlen($clientsdetails["gatewayid"] ?? "") == 0) {
            $relevantPayMethods = $payMethod = Payment\PayMethod\Model::where("userid", $client->getID())->where("gateway_name", $params["paymentmethod"])->get();
            $payMethod = NULL;
            if ($relevantPayMethods->count()) {
                if (Session::get("cartccdetail")) {
                    $cartCcDetail = unserialize(base64_decode(decrypt(Session::get("cartccdetail"))));
                    $ccInfo = $cartCcDetail[9];
                    if (is_numeric($ccInfo)) {
                        $payMethod = $relevantPayMethods->find($ccInfo);
                        if ($payMethod && $invoiceModel) {
                            $invoiceModel->payMethod()->associate($payMethod);
                            $invoiceModel->save();
                        }
                    }
                }
                if (!$payMethod && $invoiceModel->payMethod) {
                    $payMethod = $invoiceModel->payMethod;
                }
                if (!$payMethod) {
                    $payMethod = $relevantPayMethods->first();
                }
            }
            if ($payMethod) {
                $payment = $payMethod->payment;
                if ($payment instanceof Payment\Contracts\RemoteTokenDetailsInterface) {
                    $clientsdetails["gatewayid"] = $payment->getRemoteToken();
                }
            }
        }
        $params["clientdetails"] = $clientsdetails;
        $params["gatewayid"] = $clientsdetails["gatewayid"];
        if (isset($params["convertto"]) && $params["convertto"]) {
            $result = select_query("tblcurrencies", "code", ["id" => (int) $params["convertto"]]);
            $data = mysql_fetch_array($result);
            $converto_currency_code = $data["code"];
            $converto_amount = convertCurrency($balance, $invoice_currency_id, $params["convertto"]);
            $params["amount"] = format_as_currency($converto_amount);
            $params["currency"] = $converto_currency_code;
            $params["currencyId"] = (int) $params["convertto"];
            $params["basecurrencyamount"] = format_as_currency($balance);
            $params["basecurrency"] = $invoice_currency_code;
            $params["baseCurrencyId"] = $invoice_currency_id;
        }
        return $params;
    }

    public function getPaymentLink()
    {
        try {
            $params = $this->initialiseGatewayAndParams();
        } catch (Exception $e) {
            logActivity("Failed to initialise payment gateway module: " . $e->getMessage());
            return false;
        }
        $params = $this->getGatewayInvoiceParams($params);
        if ($this->gateway->functionExists("link")) {
            $paymentButton = $this->gateway->call("link", $params);
        } else {
            $paymentButton = sprintf("<form method=\"POST\" action=\"%s\" name=\"paymentfrm\"><button type=\"submit\" class=\"btn btn-success btn-sm\" id=\"btnPayNow\" value=\"Submit\">%s</button></form>", fqdnRoutePath("invoice-pay", $params["invoiceid"]), $params["langpaynow"]);
        }
        return $paymentButton;
    }

    public function getLineItems($entityDecode = false)
    {
        if ($this->outputLanguage) {
            swapLang($this->outputLanguage);
        } else {
            getUsersLang($this->getData("userid"));
        }
        $invoiceid = $this->getID();
        $invoiceitems = [];
        if (Config\Setting::getValue("GroupSimilarLineItems")) {
            $result = full_query("SELECT COUNT(*) as qty,id,type,relid,description,amount,taxed FROM tblinvoiceitems WHERE invoiceid=" . (int) $invoiceid . " GROUP BY HEX(description),`amount` ORDER BY id ASC");
            while ($data = mysql_fetch_array($result)) {
            }
            return $invoiceitems;
        }
        $result = select_query("tblinvoiceitems", "0 as qty,id,type,relid,description,amount,taxed", ["invoiceid" => $invoiceid], "id", "ASC");
        $qty = $data["qty"];
        $description = $data["description"];
        $amount = $data["amount"];
        $taxed = $data["taxed"] ? true : false;
        if (1 < $qty) {
            $description = $qty . " x " . $description . " @ " . $amount . \Lang::trans("invoiceqtyeach");
            $amount *= $qty;
        }
        if ($entityDecode) {
            $description = htmlspecialchars(Input\Sanitize::decode($description));
        } else {
            $description = nl2br($description);
        }
        $invoiceitems[] = ["id" => (int) $data["id"], "type" => $data["type"], "relid" => (int) $data["relid"], "description" => $description, "rawamount" => $amount, "amount" => formatCurrency($amount), "taxed" => $taxed];
    }

    public function getTransactions()
    {
        $invoiceid = $this->invoiceId;
        $transactions = [];
        $result = select_query("tblaccounts", "id,date,transid,amountin,amountout,(SELECT value FROM tblpaymentgateways WHERE gateway=tblaccounts.gateway AND setting='name' LIMIT 1) AS gateway", ["invoiceid" => $invoiceid], "date` ASC,`id", "ASC");
        $gatewayModule = new Module\Gateway();
        while ($data = mysql_fetch_array($result)) {
            if ($gatewayModule->getLoadedModule() !== $data["gateway"]) {
                $gatewayModule->load($data["gateway"]);
            }
            $tid = $data["id"];
            $date = $data["date"];
            $gateway = $data["gateway"];
            $amountin = $data["amountin"];
            $amountout = $data["amountout"];
            $transid = $gatewayModule->functionExists("formatTransactionIdForDisplay") ? $gatewayModule->call("formatTransactionIdForDisplay", ["transactionId" => $data["transid"]]) : $data["transid"];
            $date = fromMySQLDate($date, 0, 1);
            if (!$gateway) {
                $gateway = "-";
            }
            $transactions[] = ["id" => $tid, "date" => $date, "gateway" => $gateway, "transid" => $transid, "amount" => formatCurrency($amountin - $amountout)];
        }
        return $transactions;
    }

    public function pdfCreate()
    {
        $this->pdf = new PDF();
        return $this->pdf;
    }

    protected function makePDFFriendly()
    {
        $this->output["companyname"] = Config\Setting::getValue("CompanyName");
        $this->output["companyurl"] = Config\Setting::getValue("Domain");
        $companyAddress = Config\Setting::getValue("InvoicePayTo");
        $this->output["companyaddress"] = explode("\n", $companyAddress);
        if (trim($this->output["notes"])) {
            $this->output["notes"] = str_replace("<br />", "", $this->output["notes"]) . "\n";
        }
        $this->output = Input\Sanitize::decode($this->output);
        return true;
    }

    public function pdfInvoicePage($invoiceId = 0)
    {
        $whmcs = \DI::make("app");
        if ($invoiceId) {
            try {
                $this->setID($invoiceId);
            } catch (\Exception $e) {
                return false;
            }
        }
        $tplvars = $this->getOutput(true);
        $tplvars["invoiceitems"] = $this->getLineItems(true);
        $tplvars["transactions"] = $this->getTransactions();
        $assetHelper = new View\Asset("");
        $tplvars["imgpath"] = $assetHelper->getFilesystemImgPath();
        $tplvars["pdfFont"] = Config\Setting::getValue("TCPDFFont");
        $this->pdfAddPage("invoicepdf.tpl", $tplvars);
        return true;
    }

    public function pdfAddPage($tplfile, $tplvars)
    {
        global $_LANG;
        $whmcs = \DI::make("app");
        $template = \App::getClientAreaTemplate();
        $templateName = $template->getName();
        $assetUtil = new View\Template\AssetUtil($template);
        $templateConfiguration = $template->getTemplateConfigValues();
        $webroot = $templateConfiguration->getWebRoot();
        if (!isValidforPath($templateName)) {
            throw new Exception\Fatal("Invalid System Template Name");
        }
        $tplFileExtension = "." . pathinfo($tplfile, PATHINFO_EXTENSION);
        $baseTplFilename = preg_replace("/" . $tplFileExtension . "\$/", "", $tplfile);
        $headerTplFile = ROOTDIR . substr($assetUtil->assetUrl($baseTplFilename . "header" . $tplFileExtension), strlen($webroot));
        $footerTplFile = ROOTDIR . substr($assetUtil->assetUrl($baseTplFilename . "footer" . $tplFileExtension), strlen($webroot));
        if (file_exists($headerTplFile)) {
            $this->pdf->setHeaderTplFile($headerTplFile);
        }
        if (file_exists($footerTplFile)) {
            $this->pdf->setFooterTplFile($footerTplFile);
        }
        $this->pdf->setTemplateVars($tplvars);
        $this->pdf->setPrintHeader(true);
        $this->pdf->setPrintFooter(true);
        $this->pdf->AddPage();
        $this->pdf->SetFont(Config\Setting::getValue("TCPDFFont"), "", 10);
        $this->pdf->SetTextColor(0);
        foreach ($tplvars as $k => $v) {
            ${$k} = $v;
        }
        $pdf =& $this->pdf;
        $path = ROOTDIR . substr($assetUtil->assetUrl($tplfile), strlen($webroot));
        include $path;
        return true;
    }

    public function pdfOutput()
    {
        return $this->pdf->Output("", "S");
    }

    public function getInvoices($status = "", $userid = 0, $orderby = "id", $sort = "DESC", $limit = "", $excludeDraftInvoices = true)
    {
        if (!function_exists("getInvoiceStatusColour")) {
            require ROOTDIR . "/includes/invoicefunctions.php";
        }
        $where = [];
        if ($status) {
            $where[] = "status = '" . db_escape_string($status) . "'";
        }
        if ($userid) {
            $where[] = "userid = " . (int) $userid;
        }
        if ($excludeDraftInvoices) {
            $where[] = "status != 'Draft'";
        }
        $where[] = "(select count(id) from tblinvoiceitems where invoiceid=tblinvoices.id and type='Invoice')<=0";
        $invoices = [];
        $result = select_query("tblinvoices", "tblinvoices.*,total-IFNULL((SELECT SUM(amountin-amountout) FROM tblaccounts WHERE tblaccounts.invoiceid=tblinvoices.id),0) AS balance", implode(" AND ", $where), $orderby, $sort, $limit);
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $invoicenum = $data["invoicenum"];
            $date = $data["date"];
            $normalisedDate = $date;
            $duedate = $data["duedate"];
            $normalisedDueDate = $duedate;
            $credit = $data["credit"];
            $total = $data["total"];
            $balance = $data["balance"];
            $status = $data["status"];
            if ($status == "Unpaid") {
                $this->totalBalance += $balance;
            }
            $date = fromMySQLDate($date, 0, 1);
            $duedate = fromMySQLDate($duedate, 0, 1);
            $rawstatus = strtolower($status);
            if (!$invoicenum) {
                $invoicenum = $id;
            }
            $totalnum = $credit + $total;
            $statusText = \Lang::trans("invoices" . $rawstatus);
            if ($rawstatus == "payment pending") {
                $statusText = \Lang::trans("invoicesPayment Pending");
            }
            $invoices[] = ["id" => $id, "invoicenum" => $invoicenum, "datecreated" => $date, "normalisedDateCreated" => $normalisedDate, "datedue" => $duedate, "normalisedDateDue" => $normalisedDueDate, "totalnum" => $totalnum, "total" => formatCurrency($totalnum), "balance" => formatCurrency($balance), "status" => getInvoiceStatusColour($status), "statusClass" => View\Helper::generateCssFriendlyClassName($status), "rawstatus" => $rawstatus, "statustext" => $statusText];
        }
        return $invoices;
    }

    public function getTotalBalance()
    {
        return $this->totalBalance;
    }

    public function getTotalBalanceFormatted()
    {
        return formatCurrency($this->getTotalBalance());
    }

    public function getEmailTemplates()
    {
        $names = ["Invoice Created", "Credit Card Invoice Created", "Invoice Payment Reminder", "First Invoice Overdue Notice", "Second Invoice Overdue Notice", "Third Invoice Overdue Notice", "Credit Card Payment Due", "Credit Card Payment Failed", "Invoice Payment Confirmation", "Credit Card Payment Confirmation", "Invoice Refund Confirmation"];
        $this->getData("status");
        switch ($this->getData("status")) {
            case Billing\Invoice::STATUS_PAID:
                $extraNames = ["Invoice Payment Confirmation", "Credit Card Payment Confirmation"];
                break;
            case Billing\Invoice::STATUS_REFUNDED:
                $extraNames = ["Invoice Refund Confirmation"];
                break;
            default:
                $extraNames = [];
                $sortedTemplates = [];
                $names = array_merge($extraNames, $names);
                $templates = Mail\Template::where("type", "=", "invoice")->where("language", "=", "")->whereIn("name", $names)->get();
                foreach ($names as $name) {
                    foreach ($templates as $i => $template) {
                        if ($template->name == $name) {
                            $sortedTemplates[] = $template;
                            unset($templates[$i]);
                        }
                    }
                }
                return $sortedTemplates;
        }
    }

    public function isAddFundsInvoice()
    {
        $numaddfunditems = get_query_val("tblinvoiceitems", "COUNT(id)", ["invoiceid" => $this->getID(), "type" => "AddFunds"]);
        $numtotalitems = get_query_val("tblinvoiceitems", "COUNT(id)", ["invoiceid" => $this->getID()]);
        return $numaddfunditems == $numtotalitems ? true : false;
    }

    public static function isValidCustomInvoiceNumberFormat($format)
    {
        $replaceValues = ["{YEAR}", "{MONTH}", "{DAY}", "{NUMBER}"];
        $replaceData = [date("Y"), date("m"), date("d"), "1"];
        $format = str_replace($replaceValues, $replaceData, $format);
        $cleanedPopulatedFormat = preg_replace("/[^[:word:] {}!@€#£\$&()-=+\\[\\]]/", "", $format);
        if ($cleanedPopulatedFormat == $format) {
            return true;
        }
        return false;
    }

    public function isProformaInvoice()
    {
        if (Config\Setting::getValue("EnableProformaInvoicing") && $this->getData("status") != "Paid") {
            return true;
        }
        return false;
    }

    public static function saveClientSnapshotData($invoiceId)
    {
        if (!Config\Setting::getValue("StoreClientDataSnapshotOnInvoiceCreation")) {
            return false;
        }
        try {
            $invoice = Billing\Invoice::findOrFail($invoiceId);
        } catch (\Exception $e) {
            \Log::debug("Invoice Save Client Data Snapshot: Got invalid invoice id or client missing");
            return false;
        }
        if (Billing\Invoice\Snapshot::where("invoiceid", $invoiceId)->count() !== 0) {
            return true;
        }
        $client = new Client($invoice->client);
        $clientsDetails = $client->getDetails("billing");
        $clientsDetails = is_array($clientsDetails) ? $clientsDetails : [];
        unset($clientsDetails["model"]);
        $customFields = [];
        $result = select_query("tblcustomfields", "tblcustomfields.id,tblcustomfields.fieldname,(SELECT value FROM tblcustomfieldsvalues WHERE tblcustomfieldsvalues.fieldid=tblcustomfields.id AND tblcustomfieldsvalues.relid=" . (int) $invoice->userId . ") AS value", ["type" => "client", "showinvoice" => "on"]);
        while ($data = mysql_fetch_assoc($result)) {
            if ($data["value"]) {
                $customFields[] = $data;
            }
        }
        $snapshot = new Billing\Invoice\Snapshot();
        $snapshot->invoiceId = $invoiceId;
        $snapshot->clientsDetails = $clientsDetails;
        $snapshot->customFields = $customFields;
        $snapshot->version = \App::getVersion()->getCanonical();
        $snapshot->save();
        return true;
    }

    protected function getClientSnapshotData()
    {
        if (!Config\Setting::getValue("StoreClientDataSnapshotOnInvoiceCreation")) {
            return NULL;
        }
        try {
            $snapshotData = Billing\Invoice\Snapshot::where("invoiceid", $this->getID())->firstOrFail();
            $clientSnapshot = ["clientsdetails" => $snapshotData->clientsDetails, "customfields" => $snapshotData->customFields];
            $this->applySnapshotVersionCorrections($clientSnapshot, $snapshotData->getVersion());
            return $clientSnapshot;
        } catch (\Exception $e) {
            return NULL;
        }
    }

    private function applySnapshotVersionCorrections($clientSnapshot, Version\SemanticVersion $version)
    {
        if (Version\SemanticVersion::compare($version, \App::getVersion(), "=")) {
            return NULL;
        }
        if (Version\SemanticVersion::compare($version, new Version\SemanticVersion("8.6.0-rc.1"), "<")) {
            $clientDetails =& $clientSnapshot["clientsdetails"];
            $clientDetails["tax_countrycode"] = $clientDetails["countrycode"];
            $clientDetails["tax_state"] = $clientDetails["state"];
        }
    }

    public function isAssignedGatewayWithDelayedCallbacks()
    {
        return in_array($this->getData("paymentmodule"), $this->gatewayModulesWhereCallbacksMightBeDelayed);
    }

    public function showPaymentSuccessAwaitingNotificationMsg($paymentSuccessful = false)
    {
        return $paymentSuccessful && in_array($this->getData("status"), [Billing\Invoice::STATUS_UNPAID, Billing\Invoice::STATUS_PAYMENT_PENDING]) && $this->isAssignedGatewayWithDelayedCallbacks();
    }

    public function setOutputLanguage($language)
    {
        $this->outputLanguage = $language;
        return $this;
    }

    public function getOutputLanguage()
    {
        return $this->outputLanguage;
    }
}
