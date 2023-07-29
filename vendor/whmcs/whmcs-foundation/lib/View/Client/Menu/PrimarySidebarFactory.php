<?php

namespace WHMCS\View\Client\Menu;

class PrimarySidebarFactory extends \WHMCS\View\Menu\MenuFactory
{
    protected $rootItemName = "Primary Sidebar";

    public function clientView()
    {
        $conditionalLinks = \WHMCS\ClientArea::getConditionalLinks();
        $action = \App::get_req_var("action");
        $viewNamespace = \Menu::context("routeNamespace");
        $menuItems = [["name" => "My Details", "label" => \Lang::trans("clientareanavdetails"), "uri" => "clientarea.php?action=details", "current" => $action == "details", "order" => 10], ["name" => "Contacts/Sub-Accounts", "label" => \Lang::trans("clientareanavcontacts"), "uri" => "clientarea.php?action=contacts", "current" => $this->isOnRoutePath("account-contacts", true), "order" => 30], ["name" => "Email History", "label" => \Lang::trans("navemailssent"), "uri" => "clientarea.php?action=emails", "current" => $action == "emails", "order" => 60]];
        if (\Auth::client() && \Auth::client()->authedUserIsOwner() && !\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            $menuItems[] = ["name" => "User Management", "label" => \Lang::trans("navUserManagement"), "uri" => routePath("account-users"), "current" => $this->isOnRoutePath("account-users", true), "order" => 15];
        }
        if (!empty($conditionalLinks["updatecc"])) {
            $menuItems[] = ["name" => "Payment Methods", "label" => \Lang::trans("paymentMethods.title"), "uri" => routePath("account-paymentmethods"), "current" => $this->isOnRoutePath("account-paymentmethods", true), "order" => 20];
        }
        if (!empty($conditionalLinks["sso"])) {
            $menuItems[] = ["name" => "Account Security", "label" => \Lang::trans("navAccountSecurity"), "uri" => "clientarea.php?action=security", "current" => $action == "security", "order" => 50];
        }
        $menuStructure = [["name" => "Account", "label" => \Lang::trans("account"), "order" => 10, "icon" => "fa-address-card", "attributes" => ["class" => "panel-default panel-actions"], "children" => $menuItems]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function serviceList()
    {
        $serviceStatusCounts = ["Active" => 0, "Completed" => 0, "Pending" => 0, "Suspended" => 0, "Terminated" => 0, "Cancelled" => 0, "Fraud" => 0];
        $filterByModule = \App::get_req_var("module");
        $filterByDomain = preg_replace("/[^a-z0-9-.]/", "", strtolower(\App::get_req_var("q")));
        $client = \Menu::context("client");
        if (is_null($client)) {
            $services = new \Illuminate\Support\Collection([]);
        } else {
            if ($filterByModule) {
                $services = $client->services()->with(["product" => function ($query) {
                    $query->where("servertype", "=", \App::get_req_var("module"));
                }])->where("domain", "like", "%" . $filterByDomain . "%")->get();
            } else {
                $services = $client->services()->where("domain", "like", "%" . $filterByDomain . "%")->get();
            }
        }
        foreach ($services as $service) {
            if ($filterByModule == "" || !is_null($service->product)) {
                $serviceStatusCounts[$service->domainStatus]++;
            }
        }
        if ($serviceStatusCounts["Fraud"] == 0) {
            unset($serviceStatusCounts["Fraud"]);
        }
        if ($serviceStatusCounts["Completed"] == 0) {
            unset($serviceStatusCounts["Completed"]);
        }
        $menuItems = [];
        $i = 1;
        foreach ($serviceStatusCounts as $status => $count) {
            $menuItems[] = ["name" => $status, "icon" => "far fa-circle", "label" => "<span>" . \Lang::trans("clientarea" . str_replace(" ", "", strtolower($status))) . "</span>", "badge" => $count, "uri" => "clientarea.php?action=services" . ($filterByModule ? "&module=" . $filterByModule : "") . "#", "order" => $i * 10];
            $i++;
        }
        $menuStructure = [["name" => "My Services Status Filter", "label" => \Lang::trans("clientareahostingaddonsview"), "order" => 10, "icon" => "fa-filter", "attributes" => ["class" => "panel-default panel-actions view-filter-btns"], "children" => $menuItems]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function serviceView()
    {
        $service = \Menu::context("service");
        $serviceOverviewChildren = [];
        $actionItemChildren = [];
        if (!is_null($service)) {
            $legacyService = new \WHMCS\Service($service->id);
            $legacyService->getAddons();
            $action = \App::get_req_var("action");
            $moduleOperation = \App::get_req_var("modop");
            $moduleAction = \App::get_req_var("a");
            $inProductDetails = \App::getCurrentFilename() == "clientarea" && $action == "productdetails" && !$moduleAction;
            $linkPrefix = "clientarea.php?action=productdetails&id=" . $service->id;
            if ($inProductDetails) {
                $linkPrefix = "";
            }
            $serviceOverviewChildren[] = ["name" => "Information", "label" => \Lang::trans("information"), "uri" => $linkPrefix . "#tabOverview", "attributes" => ["dataToggleTab" => $inProductDetails], "current" => $action == "productdetails" && !$moduleOperation, "order" => 10];
            if (0 < count($legacyService->getAssociatedDownloads())) {
                $serviceOverviewChildren[] = ["name" => "Downloads", "label" => \Lang::trans("downloadstitle"), "uri" => $linkPrefix . "#tabDownloads", "attributes" => ["dataToggleTab" => $inProductDetails], "order" => 20];
            }
            if ($service->addons()->count()) {
                $serviceOverviewChildren[] = ["name" => "Addons", "label" => \Lang::trans("clientareahostingaddons"), "uri" => $linkPrefix . "#tabAddons", "attributes" => ["dataToggleTab" => $inProductDetails], "order" => 30];
            }
            foreach ($service->getCustomActionData() as $key => $actionDatum) {
                $actionItemChildren[] = ["name" => $actionDatum["identifier"], "label" => $actionDatum["display"], "uri" => "#", "attributes" => ["dataCustomAction" => ["active" => $actionDatum["active"], "identifier" => $actionDatum["identifier"], "serviceid" => $service->id]], "disabled" => !$actionDatum["active"], "order" => $key + 1];
            }
            if ($legacyService->hasFunction("ChangePassword")) {
                $actionItemChildren[] = ["name" => "Change Password", "label" => \Lang::trans("serverchangepassword"), "uri" => $linkPrefix . "#tabChangepw", "attributes" => ["dataToggleTab" => $inProductDetails], "disabled" => !$legacyService->getAllowChangePassword(), "order" => 10];
            }
            if ($service->hasAvailableUpgrades()) {
                $actionItemChildren[] = ["name" => "Upgrade/Downgrade", "label" => \Lang::trans("upgradedowngradepackage"), "uri" => "upgrade.php?type=package&amp;id=" . $service->id, "disabled" => $service->status != "Active", "order" => 80];
            }
            if ($service->product->allowConfigOptionUpgradeDowngrade) {
                $actionItemChildren[] = ["name" => "Upgrade/Downgrade Options", "label" => \Lang::trans("upgradedowngradeconfigoptions"), "uri" => "upgrade.php?type=configoptions&amp;id=" . $service->id, "disabled" => $service->status != "Active", "order" => 90];
            }
            if ($legacyService->getAllowCancellation()) {
                if (0 < $service->cancellationRequests->count()) {
                    $langIndex = "cancellationrequested";
                    $disabled = true;
                } else {
                    $langIndex = "clientareacancelrequestbutton";
                    $disabled = $service->status != "Active" && $service->status != "Suspended";
                }
                $actionItemChildren[] = ["name" => "Cancel", "label" => \Lang::trans($langIndex), "uri" => "clientarea.php?action=cancel&amp;id=" . $service->id, "order" => 100, "current" => $action == "cancel", "disabled" => $disabled];
            }
            $success = $legacyService->moduleCall("ClientAreaCustomButtonArray");
            if ($success) {
                $moduleCustomButtons = $legacyService->getModuleReturn("data");
                if (is_array($moduleCustomButtons)) {
                    $i = 1;
                    foreach ($moduleCustomButtons as $buttonLabel => $functionName) {
                        if (is_string($functionName)) {
                            $actionItemChildren[] = ["name" => "Custom Module Button " . $buttonLabel, "label" => $buttonLabel, "uri" => "clientarea.php?action=productdetails&id=" . $service->id . "&modop=custom&a=" . $functionName, "current" => $action == "productdetails" && $moduleOperation == "custom" && $moduleAction == $functionName, "disabled" => $service->status != "Active", "order" => 19 + $i];
                            $i++;
                        }
                    }
                }
            }
        }
        $menuStructure = [["name" => "Service Details Overview", "label" => \Lang::trans("overview"), "order" => 10, "icon" => "fa-star", "attributes" => ["class" => "panel-default card-light panel-actions"], "childrenAttributes" => ["class" => "list-group-tab-nav"], "children" => $serviceOverviewChildren], ["name" => "Service Details Actions", "label" => \Lang::trans("actions"), "order" => 20, "icon" => "fa-wrench", "attributes" => ["class" => "panel-default panel-actions"], "childrenAttributes" => ["class" => "list-group-tab-nav"], "children" => $actionItemChildren]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function serviceUpgrade()
    {
        $service = \Menu::context("service");
        $childItems = [];
        $productBackButton = "";
        if (!is_null($service)) {
            $childItems[] = ["name" => "Product-Service", "label" => \Lang::trans("orderproduct") . ":<br/><strong>" . $service->product->productGroup->name . " - " . $service->product->name . "</strong>", "order" => 10];
            if ($service->domain != "") {
                $childItems[] = ["name" => "Domain", "label" => \Lang::trans("clientareahostingdomain") . ":<br/>" . $service->domain . "</span>", "order" => 20];
            }
            $productBackButton = "<form method=\"post\" action=\"clientarea.php?action=productdetails\"><input type=\"hidden\" name=\"id\" value=\"" . $service->id . "\" />" . "<button type=\"submit\" class=\"btn btn-block btn-primary\">" . "<i class=\"fas fa-arrow-circle-left\"></i> " . \Lang::trans("backtoservicedetails") . "</button>" . "</form>";
        }
        $menuStructure = [["name" => "Upgrade Downgrade", "label" => \Lang::trans("upgradedowngradeshort"), "order" => 10, "icon" => "fa-expand", "children" => $childItems, "footerHtml" => $productBackButton]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function sslCertificateOrderView()
    {
        $service = \Menu::context("service");
        $addon = \Menu::context("addon");
        $additionalData = \Menu::context("displayData");
        $certificateStatus = \Menu::context("orderStatus");
        $stepNumber = \Menu::context("step");
        $stepNumber = in_array($stepNumber, [2, 3]) ? $stepNumber : 1;
        $childItems = [];
        $productBackButton = "";
        if (!is_null($addon) || !is_null($service)) {
            $i = 6;
            foreach ($additionalData as $label => $value) {
                $childItems[] = ["name" => $label, "label" => "<strong>" . $label . "</strong><br />" . $value, "order" => $i * 10];
                $i++;
            }
            if ($service->domain != "") {
                $childItems[] = ["name" => "Domain Name", "label" => "<strong>" . \Lang::trans("domainname") . "</strong><br />" . $service->domain, "order" => 30];
            }
            $productBackButton = "<a href=\"" . routePath("clientarea-ssl-certificates-manage") . "\" class=\"btn btn-block btn-primary\">" . "<i class=\"fas fa-arrow-circle-left\"></i> " . \Lang::trans("navManageSsl") . "</a>";
        }
        if (!is_null($addon)) {
            $childItems[] = ["name" => "Certificate Type", "label" => "<strong>" . \Lang::trans("sslcerttype") . "</strong><br />" . ($addon->name ?: $addon->productAddon->name), "order" => 10];
            $childItems[] = ["name" => "Order Date", "label" => "<strong>" . \Lang::trans("sslorderdate") . "</strong><br />" . fromMySQLDate($addon->registrationDate, false, true), "order" => 20];
            $childItems[] = ["name" => "Order Price", "label" => "<strong>" . \Lang::trans("orderprice") . "</strong><br /> " . formatCurrency($addon->recurringFee), "order" => 40];
            $childItems[] = ["name" => "Certificate Status", "label" => "<strong>" . \Lang::trans("clientareastatus") . "</strong><br />" . $certificateStatus, "order" => 50];
        } else {
            if (!is_null($service)) {
                $childItems[] = ["name" => "Certificate Type", "label" => "<strong>" . \Lang::trans("sslcerttype") . "</strong><br />" . $service->product->name, "order" => 10];
                $childItems[] = ["name" => "Order Date", "label" => "<strong>" . \Lang::trans("sslorderdate") . "</strong><br />" . fromMySQLDate($service->registrationDate, false, true), "order" => 20];
                $childItems[] = ["name" => "Order Price", "label" => "<strong>" . \Lang::trans("orderprice") . "</strong><br /> " . formatCurrency($service->firstPaymentAmount), "order" => 40];
                $childItems[] = ["name" => "Certificate Status", "label" => "<strong>" . \Lang::trans("clientareastatus") . "</strong><br />" . $certificateStatus, "order" => 50];
                $productBackButton = "<form method=\"post\" action=\"clientarea.php?action=productdetails\"><input type=\"hidden\" name=\"id\" value=\"" . $service->id . "\" />" . "<button type=\"submit\" class=\"btn btn-block btn-primary\">" . "<i class=\"fas fa-arrow-circle-left\"></i> " . \Lang::trans("backtoservicedetails") . "</button>" . "</form>";
            }
        }
        $menuStructure = [["name" => "Configure SSL Certificate Progress", "label" => sprintf(\Lang::trans("step"), $stepNumber) . " <span>" . "<i class=\"far fa-dot-circle\">&nbsp;</i>" . "<i class=\"far fa-" . (2 <= $stepNumber ? "dot-" : "") . "circle\">&nbsp;</i>" . "<i class=\"far fa-" . (3 <= $stepNumber ? "dot-" : "") . "circle\">&nbsp;</i>" . "</span>", "attributes" => ["class" => "panel-info"], "order" => 10, "icon" => "fa-certificate", "children" => $childItems, "footerHtml" => $productBackButton]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function domainList()
    {
        $domainStatusCounts = ["Active" => 0, "Expired" => 0, "Grace" => 0, "Redemption" => 0, "Transferred Away" => 0, "Cancelled" => 0, "Fraud" => 0, "Pending" => 0, "Pending Registration" => 0, "Pending Transfer" => 0, "Expiring Soon" => 0];
        $client = \Menu::context("client");
        $domains = is_null($client) ? new \Illuminate\Support\Collection([]) : $client->domains;
        $q = preg_replace("/[^a-z0-9-.]/", "", strtolower(\App::get_req_var("q")));
        foreach ($domains as $domain) {
            if ($q == "" || strpos($domain->domain, $q) !== false) {
                $domainStatusCounts[$domain->status]++;
                $daysUntilExpiry = $domain->expiryDate->diffInDays(\WHMCS\Carbon::now());
                if ($daysUntilExpiry <= 45 && $domain->status != "Expired") {
                    $domainStatusCounts["Expiring Soon"]++;
                }
            }
        }
        $nonKeyStatuses = ["Grace", "Redemption", "Cancelled", "Fraud", "Pending", "Pending Registration", "Pending Transfer", "Transferred Away", "Expiring Soon"];
        foreach ($nonKeyStatuses as $status) {
            if ($domainStatusCounts[$status] == 0) {
                unset($domainStatusCounts[$status]);
            }
        }
        $menuItems = [];
        $i = 1;
        foreach ($domainStatusCounts as $status => $count) {
            if (stripos($status, "Expiring") !== false) {
                $status = "domains" . str_replace(" ", "", $status);
            } else {
                $status = "clientarea" . str_replace(" ", "", strtolower($status));
            }
            $translatedStatus = \Lang::trans($status);
            $menuItems[] = ["name" => $status, "icon" => "far fa-circle", "label" => "<span>" . $translatedStatus . "</span>", "badge" => $count, "uri" => "clientarea.php?action=domains#", "order" => $i * 10];
            $i++;
        }
        $menuStructure = [["name" => "My Domains Status Filter", "label" => \Lang::trans("clientareahostingaddonsview"), "order" => 10, "icon" => "fa-filter", "attributes" => ["class" => "panel-default panel-actions view-filter-btns"], "children" => $menuItems]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function domainView()
    {
        $domain = \Menu::context("domain");
        $childItems = [];
        if (!is_null($domain)) {
            $legacyDomainService = new \WHMCS\Domains();
            $legacyDomainService->getDomainsDatabyID($domain->id);
            $managementOptions = $legacyDomainService->getManagementOptions();
            $action = \App::get_req_var("action");
            $modop = \App::get_req_var("modop");
            $customAction = \App::get_req_var("a");
            $inDomainDetails = \App::getCurrentFilename() == "clientarea" && $action == "domaindetails" && !$customAction && !$modop;
            $inDomainAddons = \App::getCurrentFilename() == "clientarea" && $action == "domainaddons";
            $linkPrefix = $inDomainDetails ? "" : "clientarea.php?action=domaindetails&id=" . $domain->id;
            $domainIsNotActive = !$legacyDomainService->isActive();
            $childItems = [["name" => "Overview", "label" => \Lang::trans("overview"), "uri" => $linkPrefix . "#tabOverview", "attributes" => ["dataToggleTab" => $inDomainDetails], "current" => $inDomainDetails, "order" => 10], ["name" => "Auto Renew Settings", "label" => \Lang::trans("domainsautorenew"), "uri" => $linkPrefix . "#tabAutorenew", "attributes" => ["dataToggleTab" => $inDomainDetails], "disabled" => $domainIsNotActive, "order" => 20]];
            if ($managementOptions["nameservers"]) {
                $childItems[] = ["name" => "Modify Nameservers", "label" => \Lang::trans("domainnameservers"), "uri" => $linkPrefix . "#tabNameservers", "attributes" => ["dataToggleTab" => $inDomainDetails], "disabled" => $domainIsNotActive, "order" => 30];
            }
            if ($managementOptions["locking"]) {
                $childItems[] = ["name" => "Registrar Lock Status", "label" => \Lang::trans("domainregistrarlock"), "uri" => $linkPrefix . "#tabReglock", "attributes" => ["dataToggleTab" => $inDomainDetails], "disabled" => $domainIsNotActive, "order" => 40];
            }
            if ($managementOptions["release"]) {
                $childItems[] = ["name" => "Release Domain", "label" => \Lang::trans("domainrelease"), "uri" => $linkPrefix . "#tabRelease", "attributes" => ["dataToggleTab" => $inDomainDetails], "disabled" => $domainIsNotActive, "order" => 60];
            }
            if ($managementOptions["addons"]) {
                $childItems[] = ["name" => "Domain Addons", "label" => \Lang::trans("clientareahostingaddons"), "uri" => $linkPrefix . "#tabAddons", "attributes" => ["dataToggleTab" => $inDomainDetails, "class" => $inDomainAddons ? "active" : ""], "disabled" => $domainIsNotActive, "order" => 70];
            }
            if ($managementOptions["contacts"]) {
                $childItems[] = ["name" => "Domain Contacts", "label" => \Lang::trans("domaincontactinfo"), "uri" => "clientarea.php?action=domaincontacts&domainid=" . $domain->id, "current" => $action == "domaincontacts", "disabled" => $domainIsNotActive, "order" => 80];
            }
            if ($managementOptions["privatens"]) {
                $childItems[] = ["name" => "Manage Private Nameservers", "label" => \Lang::trans("domainprivatenameservers"), "uri" => "clientarea.php?action=domainregisterns&domainid=" . $domain->id, "current" => $action == "domainregisterns", "disabled" => $domainIsNotActive, "order" => 90];
            }
            if ($managementOptions["dnsmanagement"]) {
                $childItems[] = ["name" => "Manage DNS Host Records", "label" => \Lang::trans("domaindnsmanagement"), "uri" => "clientarea.php?action=domaindns&domainid=" . $domain->id, "current" => $action == "domaindns", "disabled" => $domainIsNotActive, "order" => 100];
            }
            if ($managementOptions["emailforwarding"]) {
                $childItems[] = ["name" => "Manage Email Forwarding", "label" => \Lang::trans("domainemailforwarding"), "uri" => "clientarea.php?action=domainemailforwarding&domainid=" . $domain->id, "current" => $action == "domainemailforwarding", "disabled" => $domainIsNotActive, "order" => 110];
            }
            if ($managementOptions["eppcode"]) {
                $childItems[] = ["name" => "Get EPP Code", "label" => \Lang::trans("domaingeteppcode"), "uri" => "clientarea.php?action=domaingetepp&domainid=" . $domain->id, "current" => $action == "domaingetepp", "disabled" => $domainIsNotActive, "order" => 120];
            }
            $registrarCustomButtons = [];
            if ($legacyDomainService->hasFunction("ClientAreaCustomButtonArray")) {
                $success = $legacyDomainService->moduleCall("ClientAreaCustomButtonArray");
                if ($success) {
                    $functions = $legacyDomainService->getModuleReturn();
                    if (is_array($functions)) {
                        $registrarCustomButtons = array_merge($registrarCustomButtons, $functions);
                    }
                }
            }
            if ($registrarCustomButtons) {
                $count = 0;
                foreach ($registrarCustomButtons as $k => $v) {
                    $childItems[] = ["name" => $k, "label" => $k, "uri" => "clientarea.php?action=domaindetails&id=" . $domain->id . "&modop=custom&a=" . $v, "current" => $modop == "custom" && $customAction == $v, "disabled" => $domainIsNotActive, "order" => 130 + $count];
                    $count += 10;
                }
            }
        }
        $menuStructure = [["name" => "Domain Details Management", "label" => \Lang::trans("manage"), "order" => 10, "icon" => "fa-cog", "attributes" => ["class" => "panel-default panel-actions"], "childrenAttributes" => ["class" => "list-group-tab-nav"], "children" => $childItems]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function invoiceList()
    {
        $client = \Menu::context("client");
        $clientId = is_null($client) ? 0 : $client->id;
        $invoices = [];
        $conditionalLinks = \WHMCS\ClientArea::getConditionalLinks();
        $invoicesDueMessage = \Lang::trans("noinvoicesduemsg");
        $invoiceActionButtons = [];
        $invoiceActionButtonsString = "";
        $invoiceFilterChildren = [];
        $i = 1;
        $invoiceStatusCounts = [\WHMCS\Billing\Invoice::STATUS_PAID => 0, \WHMCS\Billing\Invoice::STATUS_UNPAID => 0, \WHMCS\Billing\Invoice::STATUS_CANCELLED => 0, \WHMCS\Billing\Invoice::STATUS_REFUNDED => 0];
        $invoiceTypeItemInvoiceIds = \WHMCS\Database\Capsule::table("tblinvoiceitems")->where("userid", $clientId)->where("type", "Invoice")->pluck("invoiceid")->all();
        if ($client) {
            $invoices = $client->invoices()->whereNotIn("id", $invoiceTypeItemInvoiceIds)->groupBy("status")->get(["status", \WHMCS\Database\Capsule::raw("COUNT(tblinvoices.id) as invoice_count")])->all();
        }
        foreach ($invoices as $invoiceStatus) {
            $status = $invoiceStatus->status;
            if (isset($invoiceStatusCounts[$status])) {
                $invoiceStatusCounts[$status] = $invoiceStatus->invoice_count;
            }
        }
        if (0 < $invoiceStatusCounts["Unpaid"]) {
            global $currency;
            $currency = getCurrency($clientId);
            if ($client) {
                $invoices = $client->invoices()->whereNotIn("id", $invoiceTypeItemInvoiceIds)->withCount("transactions")->with("transactions")->unpaid()->get();
            }
            $amountDue = 0;
            foreach ($invoices as $invoice) {
                $amountDue += $invoice->total - $invoice->amountPaid;
            }
            $invoicesDueMessage = sprintf(\Lang::trans("invoicesduemsg"), $invoiceStatusCounts["Unpaid"], formatCurrency($amountDue));
            if (!empty($conditionalLinks["masspay"])) {
                $massPayButtonLabel = \Lang::trans("masspayall");
                $invoiceActionButtons[] = "\n    <a href=\"clientarea.php?action=masspay&all=true\" class=\"btn btn-success btn-sm btn-block\"{\$massPayDisabled}>\n        <i class=\"fas fa-check-circle\"></i>\n        " . $massPayButtonLabel . "\n    </a>";
            }
            if (!empty($conditionalLinks["addfunds"])) {
                $addFundsButtonLabel = \Lang::trans("addfunds");
                $invoiceActionButtons[] = "\n    <a href=\"clientarea.php?action=addfunds\" class=\"btn btn-default btn-sm btn-block\">\n        <i class=\"far fa-money-bill-alt\"></i>\n        " . $addFundsButtonLabel . "\n    </a>";
            }
            if (1 < count($invoiceActionButtons)) {
                $col = 6;
                $colSize = "xs";
            } else {
                $col = 12;
                $colSize = "sm";
            }
            foreach ($invoiceActionButtons as $num => $button) {
                if ($num % 2 == 0) {
                    $side = "left";
                } else {
                    $side = "right";
                }
                $invoiceActionButtonsString .= "<div class='col-" . $colSize . "-" . $col . " col-button-" . $side . " float-" . $side . "'>" . $button . "</div>";
            }
        }
        foreach ($invoiceStatusCounts as $status => $count) {
            $invoiceFilterChildren[] = ["name" => $status, "icon" => "far fa-circle", "label" => "<span>" . \Lang::trans("invoices" . strtolower($status)) . "</span>", "badge" => $count, "uri" => "clientarea.php?action=invoices#", "order" => $i * 10];
            $i++;
        }
        $menuStructure = [["name" => "My Invoices Summary", "label" => $invoiceStatusCounts["Unpaid"] . " " . \Lang::trans("invoicesdue"), "order" => 10, "icon" => "fa-credit-card", "attributes" => ["class" => $invoiceStatusCounts["Unpaid"] == 0 ? "panel-success" : "panel-danger"], "bodyHtml" => $invoicesDueMessage, "footerHtml" => $invoiceActionButtonsString], ["name" => "My Invoices Status Filter", "label" => \Lang::trans("invoicesstatus"), "order" => 20, "icon" => "fa-filter", "attributes" => ["class" => "panel-default panel-actions view-filter-btns"], "children" => $invoiceFilterChildren]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function clientQuoteList()
    {
        $client = \Menu::context("client");
        $childItems = [];
        $i = 1;
        $quoteStatusCounts = ["Delivered" => "0", "Accepted" => "0"];
        if (!is_null($client)) {
            foreach ($client->quotes as $quote) {
                if ($quote->status != "Draft") {
                    $quoteStatusCounts[$quote->status]++;
                }
            }
        }
        foreach ($quoteStatusCounts as $status => $count) {
            $childItems[] = ["name" => $status, "icon" => "far fa-circle", "label" => "<span>" . \Lang::trans("quotestage" . str_replace(" ", "", strtolower($status))) . "</span>", "badge" => $count, "uri" => "clientarea.php?action=quotes#", "order" => $i * 10];
            $i++;
        }
        $menuStructure = [["name" => "My Quotes Status Filter", "label" => \Lang::trans("quotestage"), "order" => 10, "icon" => "fa-filter", "attributes" => ["class" => "panel-default panel-actions view-filter-btns"], "children" => $childItems]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function clientAddFunds()
    {
        $menuStructure = [["name" => "Add Funds", "label" => \Lang::trans("addfunds"), "bodyHtml" => \Lang::trans("addfundsdescription"), "attributes" => ["class" => "panel panel-info"]]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function affiliateView()
    {
        return $this->emptySidebar();
    }

    public function announcementList()
    {
        $monthsWithAnnouncements = \Menu::context("monthsWithAnnouncements");
        $view = \Menu::context("announcementView");
        $menuChildren = [];
        $i = 1;
        if (!is_null($monthsWithAnnouncements)) {
            foreach ($monthsWithAnnouncements as $month) {
                $slug = $month->format("Y-m");
                $menuChildren[] = ["name" => $month->format("M Y"), "uri" => $view == $slug ? routePath("announcement-index") : routePath("announcement-index", $slug), "order" => $i * 10, "current" => $view == $slug];
                $i++;
            }
        }
        $menuChildren[] = ["name" => "Older", "label" => \Lang::trans("announcementsolder") . "...", "uri" => routePath("announcement-index", "older"), "order" => $i * 10, "current" => $view == "older"];
        $i++;
        $menuChildren[] = ["name" => "RSS Feed", "label" => \Lang::trans("announcementsrss"), "icon" => "fa-rss icon-rss", "uri" => routePath("announcement-rss"), "order" => $i * 10];
        $menuStructure = [["name" => "Announcements Months", "label" => \Lang::trans("announcementsbymonth"), "order" => 10, "icon" => "fa-calendar-alt", "children" => $menuChildren, "extras" => ["mobileSelect" => true]]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function downloadList()
    {
        return $this->emptySidebar();
    }

    public function supportKnowledgeBase()
    {
        $menuStructure = [["name" => "Support Knowledgebase Categories", "label" => \Lang::trans("knowledgebasecategories"), "order" => 10, "icon" => "fa-info", "children" => $this->buildSupportKnowledgeBaseCategories(), "extras" => ["mobileSelect" => true]]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function support()
    {
        return $this->emptySidebar();
    }

    public function networkIssueList()
    {
        $issueStatusCounts = \Menu::context("networkIssueStatusCounts");
        $view = \App::get_req_var("view");
        $menuStructure = [["name" => "Network Status", "label" => \Lang::trans("view"), "icon" => "fa-filter", "order" => 10, "attributes" => ["class" => "panel-default panel-actions view-filter-btns"], "children" => [["name" => "Open", "label" => \Lang::trans("networkissuesstatusopen"), "uri" => "serverstatus.php" . ($view == "open" ? "" : "?view=open"), "order" => 10, "current" => $view == "open", "badge" => $issueStatusCounts["open"]], ["name" => "Scheduled", "label" => \Lang::trans("networkissuesstatusscheduled"), "uri" => "serverstatus.php" . ($view == "scheduled" ? "" : "?view=scheduled"), "order" => 20, "current" => $view == "scheduled", "badge" => $issueStatusCounts["scheduled"]], ["name" => "Resolved", "label" => \Lang::trans("networkissuesstatusresolved"), "uri" => "serverstatus.php" . ($view == "resolved" ? "" : "?view=resolved"), "order" => 30, "current" => $view == "resolved", "badge" => $issueStatusCounts["resolved"]], ["name" => "View RSS Feed", "label" => \Lang::trans("announcementsrss"), "uri" => "networkissuesrss.php", "icon" => "fa-rss icon-rss", "order" => 40]]]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function ticketList()
    {
        $ticketStatusCounts = \Menu::context("ticketStatusCounts");
        $childItems = [];
        $i = 1;
        if (is_null($ticketStatusCounts)) {
            $ticketStatusCounts = [];
        }
        foreach ($ticketStatusCounts as $status => $count) {
            $langKey = "supportticketsstatus" . str_replace([" ", "-"], "", strtolower($status));
            $translated = \Lang::trans($langKey);
            $langValue = $status;
            if ($translated != $langKey) {
                $langValue = \Lang::trans($langKey);
            }
            $childItems[] = ["name" => $status, "icon" => "far fa-circle", "label" => "<span>" . $langValue . "</span>", "badge" => $count, "uri" => "supporttickets.php#", "order" => $i * 10];
            $i++;
        }
        $menuStructure = [["name" => "Ticket List Status Filter", "label" => \Lang::trans("view"), "icon" => "fa-filter", "order" => 10, "attributes" => ["class" => "panel-default panel-actions view-filter-btns"], "children" => $childItems]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function ticketSubmit()
    {
        return $this->support();
    }

    public function ticketFeedback()
    {
        return $this->support();
    }

    public function ticketView()
    {
        $ticketId = \Menu::context("ticketId");
        $carbon = \Menu::context("carbon");
        $ticket = \Menu::context("ticket");
        if (!$ticket) {
            return $this->support();
        }
        $ticketId = $ticket->id;
        $c = $ticket->c;
        $cc = $ticket->cc;
        $departmentId = $ticket->departmentId;
        $dateOpened = $ticket->date;
        $ticketRef = $ticket->ticketNumber;
        $subject = $ticket->subject;
        $priority = $ticket->priority;
        $status = $ticket->status;
        $lastReply = $ticket->lastReply;
        $dateOpened = fromMySQLDate($dateOpened, 1, 1);
        $departmentName = getDepartmentName($departmentId);
        $priority = \Lang::trans("supportticketsticketurgency" . strtolower($priority));
        $showCloseButton = false;
        $closedTicketStatuses = [];
        $ticketStatuses = \WHMCS\Database\Capsule::table("tblticketstatuses")->get()->all();
        $ticketStatusColor = "";
        foreach ($ticketStatuses as $ticketStatus) {
            if ($ticketStatus->title == $status) {
                $ticketStatusColor = $ticketStatus->color;
            }
            if (!$ticketStatus->showactive && !$ticketStatus->showawaiting) {
                $closedTicketStatuses[] = $ticketStatus->title;
            }
        }
        if (!in_array($status, $closedTicketStatuses)) {
            $showCloseButton = true;
        }
        $statusPlain = preg_replace("/[^a-z]/i", "", strtolower($status));
        $displayStatus = \Lang::trans("supportticketsstatus" . $statusPlain);
        if ($displayStatus == "supportticketsstatus" . $statusPlain) {
            $displayStatus = $status;
        }
        $detailsChildren = [["name" => "Requestor", "label" => "<span class=\"title\">" . \Lang::trans("requestor") . "</span><br>" . $ticket->getRequestorDisplayLabel(), "attributes" => ["class" => "ticket-details-children"], "order" => 10], ["name" => "Department", "label" => "<span class=\"title\">" . \Lang::trans("supportticketsdepartment") . "</span><br>" . $departmentName, "attributes" => ["class" => "ticket-details-children"], "order" => 20], ["name" => "Date Opened", "label" => "<span class=\"title\">" . \Lang::trans("supportticketsubmitted") . "</span><br>" . $dateOpened, "attributes" => ["class" => "ticket-details-children"], "order" => 30], ["name" => "Last Updated", "label" => "<span class=\"title\">" . \Lang::trans("supportticketsticketlastupdated") . "</span><br>" . $carbon->parse($lastReply)->diffForHumans(), "attributes" => ["class" => "ticket-details-children"], "order" => 40], ["name" => "Priority", "label" => "<span class=\"title\">" . \Lang::trans("supportticketsstatus") . "/" . \Lang::trans("supportticketspriority") . "</span><br>" . "<span class=\"label\" style=\"background-color:" . $ticketStatusColor . ";\">" . $displayStatus . "</span> " . $priority, "attributes" => ["class" => "ticket-details-children"], "order" => 50]];
        $replyText = \Lang::trans("supportticketsreply");
        $ticketDetailsFooter = "<div class=\"row\">\n    <div class=\"col-6 col-xs-6 col-button-left\">\n        <button class=\"btn btn-success btn-sm btn-block\" onclick=\"jQuery('#ticketReply').click()\">\n            <i class=\"fas fa-pencil-alt\"></i> " . $replyText . "\n        </button>\n    </div>\n    <div class=\"col-6 col-xs-6 col-button-right\">\n        <button class=\"btn btn-danger btn-sm btn-block\"";
        if (!$showCloseButton) {
            $ticketDetailsFooter .= "disabled=\"disabled\"";
        }
        $ticketDetailsFooter .= " onclick=\"window.location='?tid=" . $ticketRef . "&amp;c=" . $c . "&amp;closeticket=true'\"><i class=\"fas fa-times\"></i> ";
        $ticketDetailsFooter .= $showCloseButton ? \Lang::trans("supportticketsclose") : \Lang::trans("supportticketsstatusclosed");
        $ticketDetailsFooter .= "</button></div></div>";
        $menuStructure = [["name" => "Ticket Information", "label" => \Lang::trans("ticketinfo"), "order" => 10, "icon" => "fa-ticket-alt", "children" => $detailsChildren, "footerHtml" => $ticketDetailsFooter]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function clientRegistration()
    {
        $menuStructure = [["name" => "Already Registered", "label" => \Lang::trans("alreadyregistered"), "order" => 20, "icon" => "fa-user", "children" => [["name" => "Already Registered Heading", "label" => \Lang::trans("clientareahomelogin"), "order" => 5], ["name" => "Login", "label" => \Lang::trans("login"), "icon" => "fa-user", "uri" => "login.php", "order" => 10], ["name" => "Lost Password Reset", "label" => \Lang::trans("pwreset"), "icon" => "fa-asterisk", "uri" => routePath("password-reset-begin"), "order" => 20]]]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function clientHome()
    {
        $client = \Menu::context("client");
        if (is_null($client)) {
            return $this->emptySidebar();
        }
        $details = "";
        if ($client->companyName) {
            $details .= "<strong>" . $client->companyName . "</strong><br><em>" . $client->fullName . "</em><br>";
        } else {
            $details .= "<strong>" . $client->fullName . "</strong><br>";
        }
        $details .= $client->address1 . "<br>";
        if ($client->address2) {
            $details .= $client->address2 . "<br>";
        }
        $address = [];
        if ($client->city) {
            $address[] = $client->city;
        }
        if ($client->state) {
            $address[] = $client->state;
        }
        if ($client->postcode) {
            $address[] = $client->postcode;
        }
        $details .= implode(", ", $address) . "<br>" . $client->countryName;
        if ($client->taxId) {
            $details .= "<br>" . $client->taxId;
        }
        $updateText = \Lang::trans("update");
        $clientDetailsFooter = "    <a href=\"clientarea.php?action=details\" class=\"btn btn-success btn-sm btn-block\">\n        <i class=\"fas fa-pencil-alt\"></i> " . $updateText . "\n    </a>";
        $menuStructure = [["name" => "Client Details", "label" => \Lang::trans("yourinfo"), "order" => 10, "icon" => "fa-user", "bodyHtml" => $details, "footerHtml" => $clientDetailsFooter]];
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    protected function buildSupportKnowledgeBaseCategories()
    {
        $currentCategoryId = \Menu::context("kbCategoryParentId") ? (int) \Menu::context("kbCategoryParentId") : (int) \Menu::context("kbCategoryId");
        $kbRootCategories = \Menu::context("kbRootCategories");
        if (is_null($kbRootCategories)) {
            return [];
        }
        $menuChildren = [];
        foreach ($kbRootCategories as $i => $category) {
            $uri = routePath("knowledgebase-category-view", $category["id"], $category["urlfriendlyname"]);
            $menuChildren[] = ["name" => "Support Knowledgebase Category " . $category["id"], "label" => "<div class=\"truncate\">" . $category["name"] . "</div>", "order" => $i * 10, "badge" => $category["numarticles"], "uri" => $uri, "current" => $currentCategoryId == $category["id"]];
        }
        if (empty($menuChildren)) {
            $menuChildren[] = ["name" => "No Support Knowledgebase Categories", "label" => \Lang::trans("nokbcategories"), "order" => 0, "icon" => "", "badge" => "", "uri" => "", "current" => true];
        }
        return $menuChildren;
    }

    public function orderFormView()
    {
        return $this->emptySidebar();
    }

    public function user()
    {
        $menuStructure = [["name" => "Profile", "label" => \Lang::trans("yourProfile"), "order" => 10, "icon" => "fa-user", "children" => [["name" => "Your Profile", "label" => \Lang::trans("yourProfile"), "uri" => routePath("user-profile"), "current" => $this->isOnRoutePath("user-profile"), "order" => 10], ["name" => "Change Password", "label" => \Lang::trans("clientareanavchangepw"), "uri" => routePath("user-password"), "current" => $this->isOnRoutePath("user-password"), "order" => 30], ["name" => "User Security", "label" => \Lang::trans("clientareanavsecurity"), "uri" => routePath("user-security"), "current" => $this->isOnRoutePath("user-security"), "order" => 40], ["name" => "Logout", "label" => \Lang::trans("clientareanavlogout"), "uri" => "logout.php", "current" => false, "order" => 50]]]];
        if (\Auth::hasMultipleClients()) {
            $menuStructure[0]["children"][] = ["name" => "Switch Account", "label" => \Lang::trans("navSwitchAccount"), "uri" => routePath("user-accounts"), "current" => $this->isOnRoutePath("user-accounts"), "order" => 20];
        }
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    public function userVerificationStatus()
    {
        return $this->emptySidebar();
    }
}
