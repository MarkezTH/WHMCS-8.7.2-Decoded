<?php
//Decoded By IonCube.cc V12 [ioncube.cc] 2023-7
define("CLIENTAREA", true);
require __DIR__ . "/init.php";
require_once ROOTDIR . "/includes/clientfunctions.php";
require_once ROOTDIR . "/includes/gatewayfunctions.php";
require_once ROOTDIR . "/includes/ccfunctions.php";
require_once ROOTDIR . "/includes/domainfunctions.php";
require_once ROOTDIR . "/includes/registrarfunctions.php";
require_once ROOTDIR . "/includes/customfieldfunctions.php";
require_once ROOTDIR . "/includes/invoicefunctions.php";
require_once ROOTDIR . "/includes/configoptionsfunctions.php";
Auth::requireLoginAndClient(true);
$action = $whmcs->get_req_var("action");
$sub = $whmcs->get_req_var("sub");
$id = (int) $whmcs->get_req_var("id");
$modop = $whmcs->get_req_var("modop");
$submit = $whmcs->get_req_var("submit");
$save = $whmcs->get_req_var("save");
$q = $whmcs->get_req_var("q");
$userid = App::getFromRequest("userid");
$paymentmethod = WHMCS\Gateways::makeSafeName($whmcs->get_req_var("paymentmethod"));
$params = [];
$addRenewalToCart = $whmcs->get_req_var("addRenewalToCart");
$today = WHMCS\Carbon::today();
if ($addRenewalToCart) {
    check_token();
    $renewID = $whmcs->get_req_var("renewID");
    $renewalPeriod = $whmcs->get_req_var("period");
    $_SESSION["cart"]["renewals"][$renewID] = $renewalPeriod;
    WHMCS\Terminus::getInstance()->doExit();
} else {
    if ($action == "parseMarkdown") {
        check_token();
        $markup = new WHMCS\View\Markup\Markup();
        echo json_encode(["body" => $markup->transform($whmcs->get_req_var("content"), "markdown")]);
        WHMCS\Terminus::getInstance()->doExit();
    } else {
        if ($action === "installWordpress") {
            check_token();
            $serviceId = (int) App::getFromRequest("serviceId");
            $service = WHMCS\Service\Service::find($serviceId);
            $productModuleActionSettings = json_decode($service->product->getModuleConfigurationSetting("moduleActions")->value, true) ?? [];
            if (empty($productModuleActionSettings["InstallWordPress"]["client"])) {
                echo json_encode(["error" => "Access Denied"]);
                WHMCS\Terminus::getInstance()->doExit();
            }
            if (!$service || $service->clientId !== Auth::client()->id) {
                echo json_encode(["status" => false, "error" => "Access Denied"]);
                WHMCS\Terminus::getInstance()->doExit();
            }
            $server = new WHMCS\Module\Server();
            $server->loadByServiceID($serviceId);
            $response = $server->call("InstallWordPress", ["blog_title" => App::getFromRequest("blog_title"), "blog_path" => App::getFromRequest("path"), "admin_pass" => App::getFromRequest("admin_pass")]);
            if (empty($response["error"])) {
                $jsonResponse = ["status" => true, "blogTitle" => $response["site-title"], "instanceUrl" => $response["protocol"] . "://" . $response["domain"] . "/" . $response["path"], "path" => rtrim("/" . $response["path"], "/")];
            } else {
                $jsonResponse["error"] = $response["error"];
            }
            echo json_encode($jsonResponse);
            WHMCS\Terminus::getInstance()->doExit();
        } else {
            if ($action == "manage-service") {
                check_token();
                $serviceId = App::getFromRequest("service-id");
                $sub = App::getFromRequest("sub");
                $server = new WHMCS\Module\Server();
                if (substr($serviceId, 0, 1) == "a") {
                    $server->loadByAddonId((int) substr($serviceId, 1));
                    $errorPrependText = "An error occurred when managing Service Addon ID: " . (int) substr($serviceId, 1) . ": ";
                } else {
                    $serviceId = (int) $serviceId;
                    $server->loadByServiceID($serviceId);
                    $errorPrependText = "An error occurred when managing Service ID: " . $serviceId . ": ";
                }
                $serviceServerParams = $server->buildParams();
                $allowedModuleFunctions = [];
                $clientAreaAllowedFunctions = $server->call("ClientAreaAllowedFunctions");
                if (is_array($clientAreaAllowedFunctions) && !array_key_exists("error", $clientAreaAllowedFunctions)) {
                    foreach ($clientAreaAllowedFunctions as $functionName) {
                        if (is_string($functionName)) {
                            $allowedModuleFunctions[] = $functionName;
                        }
                    }
                }
                $clientAreaCustomButtons = $server->call("ClientAreaCustomButtonArray");
                if (is_array($clientAreaCustomButtons) && !array_key_exists("error", $clientAreaAllowedFunctions)) {
                    foreach ($clientAreaCustomButtons as $buttonLabel => $functionName) {
                        if (is_string($functionName)) {
                            $allowedModuleFunctions[] = $functionName;
                        }
                    }
                }
                $response = ["error" => "An unknown error occurred"];
                if (Auth::client()->id == $serviceServerParams["userid"]) {
                    if (in_array("manage_order", $allowedModuleFunctions) && $server->functionExists("manage_order") && $sub !== "manage") {
                        if (!checkContactPermission("productsso", true)) {
                            $response = ["error" => Lang::trans("subaccountSsoDenied")];
                        } else {
                            $apiResponse = $server->call("manage_order");
                            if (is_array($apiResponse) && isset($apiResponse["jsonResponse"])) {
                                $apiResponse = $apiResponse["jsonResponse"];
                                if (!empty($apiResponse["success"]) && isset($apiResponse["redirect"])) {
                                    $response = ["redirect" => $apiResponse["redirect"]];
                                } else {
                                    if (isset($apiResponse["error"])) {
                                        $response = ["error" => $apiResponse["error"]];
                                    }
                                }
                            }
                            unset($apiResponse);
                        }
                    } else {
                        if ($sub === "manage") {
                            if (!empty($serviceServerParams["addonId"])) {
                                App::redirectToRoutePath("module-custom-action-addon", [$serviceServerParams["serviceid"], $serviceServerParams["addonId"], "manage"]);
                            } else {
                                App::redirectToRoutePath("module-custom-action", [$serviceServerParams["serviceid"], "manage"]);
                            }
                        } else {
                            $response = ["error" => "Function Not Allowed"];
                        }
                    }
                } else {
                    $response = ["error" => "Access Denied"];
                }
                echo json_encode($response);
                WHMCS\Terminus::getInstance()->doExit();
            }
        }
    }
}
$activeLanguage = WHMCS\Session::get("Language");
if ($action == "changesq" || $whmcs->get_req_var("2fasetup")) {
    $action = "security";
}
$ca = new WHMCS\ClientArea();
$ca->setPageTitle($whmcs->get_lang("clientareatitle"));
$ca->addToBreadCrumb("index.php", $whmcs->get_lang("globalsystemname"))->addToBreadCrumb("clientarea.php", $whmcs->get_lang("clientareatitle"));
$ca->initPage();
$ca->requireLogin();
$legacyClient = new WHMCS\Client($ca->getClient());
if ($action == "hosting") {
    $ca->addToBreadCrumb("clientarea.php?action=hosting", $whmcs->get_lang("clientareanavhosting"));
}
if (in_array($action, ["products", "services", "cancel"])) {
    $ca->addToBreadCrumb("clientarea.php?action=products", $whmcs->get_lang("clientareaproducts"));
}
if (in_array($action, ["domains", "domaindetails", "domaincontacts", "domaindns", "domainemailforwarding", "domaingetepp", "domainregisterns", "domainaddons"])) {
    $ca->addToBreadCrumb("clientarea.php?action=domains", $whmcs->get_lang("clientareanavdomains"));
}
if ($action == "invoices") {
    $ca->addToBreadCrumb("clientarea.php?action=invoices", $whmcs->get_lang("invoices"));
}
if ($action == "emails") {
    $ca->addToBreadCrumb("clientarea.php?action=emails", $whmcs->get_lang("clientareaemails"));
}
if ($action == "addfunds") {
    $ca->addToBreadCrumb("clientarea.php?action=addfunds", $whmcs->get_lang("addfunds"));
}
if ($action == "masspay") {
    $ca->addToBreadCrumb("clientarea.php?action=masspay" . ($whmcs->get_req_var("all") ? "&all=true" : "") . "#", $whmcs->get_lang("masspaytitle"));
}
if ($action == "quotes") {
    $ca->addToBreadCrumb("clientarea.php?action=quotes", $whmcs->get_lang("quotestitle"));
}
$currency = WHMCS\Billing\Currency::factoryForClientArea();
if (substr($action, 0, 6) == "domain" && $action != "domains") {
    $domainID = $whmcs->get_req_var("id");
    if (!$domainID) {
        $domainID = $whmcs->get_req_var("domainid");
    }
    $domains = new WHMCS\Domains();
    $domainData = $domains->getDomainsDatabyID($domainID);
    if (!$domainData) {
        redir("action=domains", "clientarea.php");
    }
    $domainModel = WHMCS\Domain\Domain::find($domainData["id"]);
    $ca->setDisplayTitle(Lang::trans("managing") . " " . $domainData["domain"]);
    $domainName = new WHMCS\Domains\Domain($domainData["domain"]);
    $managementOptions = $domains->getManagementOptions();
    try {
        $registrar = $domainModel->getRegistrarInterface();
        $params = $registrar->getSettings();
    } catch (Exception $e) {
        $registrar = NULL;
        $params = [];
    }
    $ca->assign("managementoptions", $managementOptions);
}
$ca->assign("action", $action);
$ca->assign("clientareaaction", $action);
if ($action == "") {
    $templateVars = $ca->getTemplateVariables();
    $ca->setDisplayTitle(Lang::trans("welcomeback") . ", " . Auth::user()->firstName);
    $ca->setTemplate("clientareahome");
    if (App::getFromRequest("inviteaccepted")) {
        WHMCS\FlashMessages::add(Lang::trans("accountInvite.acceptSuccess"), "success");
    }
    $clientId = $ca->getClient()->id;
    $panels = [];
    if (checkContactPermission("invoices", true)) {
        $invoiceTypeItemInvoiceIds = WHMCS\Database\Capsule::table("tblinvoiceitems")->where("userid", $userid)->where("type", "Invoice")->pluck("invoiceid")->all();
        $invoices = WHMCS\Database\Capsule::table("tblinvoices")->where("tblinvoices.userid", $clientId)->where("status", "Unpaid")->where("duedate", "<", WHMCS\Carbon::now()->toDateString())->whereNotIn("tblinvoices.id", $invoiceTypeItemInvoiceIds)->leftJoin("tblaccounts", "tblaccounts.invoiceid", "=", "tblinvoices.id")->groupBy("tblinvoices.id")->get([WHMCS\Database\Capsule::raw("IFNULL(total, 0) as total"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountin), 0) as amount_in"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountout), 0) as amount_out")])->all();
        $invoiceCount = count($invoices);
        if ($invoiceCount) {
            $invoices = collect($invoices);
            $msg = Lang::trans("clientHomePanels.overdueInvoicesMsg", [":numberOfInvoices" => $invoiceCount, ":balanceDue" => formatCurrency($invoices->sum(function ($invoice) {
                return $invoice->total - $invoice->amount_in + $invoice->amount_out;
            }))]);
            $panels[] = ["name" => "Overdue Invoices", "label" => Lang::trans("clientHomePanels.overdueInvoices"), "icon" => "fa-calculator", "attributes" => ["id" => "overdueInvoicesPanel"], "extras" => ["color" => "red", "btn-icon" => "fas fa-arrow-right", "btn-link" => "clientarea.php?action=masspay&all=true", "btn-text" => Lang::trans("invoicespaynow")], "bodyHtml" => "<p>" . $msg . "</p>", "order" => "10"];
        } else {
            $invoices = WHMCS\Database\Capsule::table("tblinvoices")->where("tblinvoices.userid", $clientId)->where("status", "Unpaid")->whereNotIn("tblinvoices.id", $invoiceTypeItemInvoiceIds)->leftJoin("tblaccounts", "tblaccounts.invoiceid", "=", "tblinvoices.id")->groupBy("tblinvoices.id")->get([WHMCS\Database\Capsule::raw("IFNULL(SUM(DISTINCT total), 0) as total"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountin), 0) as amount_in"), WHMCS\Database\Capsule::raw("IFNULL(SUM(amountout), 0) as amount_out")])->all();
            $invoiceCount = count($invoices);
            if ($invoiceCount) {
                $invoices = collect($invoices);
                $msg = Lang::trans("clientHomePanels.unpaidInvoicesMsg", [":numberOfInvoices" => $invoiceCount, ":balanceDue" => formatCurrency($invoices->sum(function ($invoice) {
                    return $invoice->total - $invoice->amount_in + $invoice->amount_out;
                }))]);
                $panels[] = ["name" => "Unpaid Invoices", "label" => Lang::trans("clientHomePanels.unpaidInvoices"), "icon" => "fa-calculator", "attributes" => ["id" => "unpaidInvoicesPanel"], "extras" => ["color" => "red", "btn-icon" => "fas fa-arrow-right", "btn-link" => "clientarea.php?action=invoices", "btn-text" => Lang::trans("viewAll")], "bodyHtml" => "<p>" . $msg . "</p>", "order" => "10"];
            }
        }
    }
    if (checkContactPermission("domains", true)) {
        $domainsDueWithin45Days = $ca->getClient()->domains()->nextDueBefore(WHMCS\Carbon::now()->addDays(45))->notFree()->count();
        if (0 < $domainsDueWithin45Days) {
            $msg = Lang::trans("clientHomePanels.domainsExpiringSoonMsg", [":days" => 45, ":numberOfDomains" => $domainsDueWithin45Days]);
            $extras = [];
            if (WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders")) {
                $extras = ["btn-icon" => "fas fa-sync", "btn-link" => routePath("cart-domain-renewals"), "btn-text" => Lang::trans("domainsrenewnow")];
            }
            $extras["color"] = "midnight-blue";
            $panels[] = ["name" => "Domains Expiring Soon", "label" => Lang::trans("clientHomePanels.domainsExpiringSoon"), "icon" => "fa-globe", "attributes" => ["id" => "expiringDomainsPanel"], "extras" => $extras, "bodyHtml" => "<p>" . $msg . "</p>", "order" => "50"];
        }
    }
    if (checkContactPermission("products", true)) {
        $servicesList = [];
        $services = $ca->getClient()->services()->whereIn("domainstatus", ["Active", "Suspended"])->orderBy("domainstatus", "asc")->orderBy("id", "desc")->limit(101)->get();
        $statusProperties = [WHMCS\Service\Status::ACTIVE => ["icon" => "fa-check-circle", "modifier" => "success", "translation" => Lang::trans("clientareaactive")], WHMCS\Service\Status::SUSPENDED => ["icon" => "fa-exclamation-triangle", "modifier" => "warning", "translation" => Lang::trans("clientareasuspended")]];
        uasort($statusProperties, function ($a, $b) {
            return strlen($a["translation"]) < strlen($b["translation"]);
        });
        foreach ($services as $service) {
            $buttonData = $service->getCustomActionData();
            $label = $ca->getSingleTPLOutput("includes/active-products-services-item", ["ca" => $ca, "buttonData" => $buttonData, "primaryServiceBtn" => array_shift($buttonData), "secondaryButtons" => $buttonData, "service" => $service, "statusProperties" => $statusProperties]);
            $servicesList[] = ["label" => $label];
        }
        $servicesPanel = ["name" => "Active Products/Services", "label" => Lang::trans("clientHomePanels.activeProductsServices"), "icon" => "fa-cube", "attributes" => ["id" => "servicesPanel"], "extras" => ["color" => "gold", "btn-icon" => "fas fa-arrow-right", "btn-link" => "clientarea.php?action=services", "btn-text" => Lang::trans("clientareanavservices"), "colspan" => true], "footerHtml" => "<a href=\"#\" class=\"btn-view-more pull-right float-right" . (4 < $services->count() ? "\">" : " disabled\" aria-disabled=\"true\">") . Lang::trans("viewMore") . "</a><div class=\"clearfix\"></div>", "children" => $servicesList, "order" => "100"];
        $bodyHtml = "";
        if (count($servicesList) == 0) {
            $bodyHtml .= "<p>" . Lang::trans("clientHomePanels.activeProductsServicesNone") . "</p>";
        } else {
            if (100 < count($servicesList)) {
                unset($servicesPanel["children"][100]);
                $bodyHtml .= "<p>" . Lang::trans("clientHomePanels.showingRecent100") . ".</p>";
            }
        }
        if ($bodyHtml) {
            $servicesPanel["bodyHtml"] = $bodyHtml;
        }
        $panels[] = $servicesPanel;
    }
    if (checkContactPermission("orders", true) && (WHMCS\Config\Setting::getValue("AllowRegister") || WHMCS\Config\Setting::getValue("AllowTransfer"))) {
        $bodyContent = "<form method=\"post\" action=\"domainchecker.php\">\n            <div class=\"input-group margin-10 m-0 px-2 pb-2\">\n                <input type=\"text\" name=\"domain\" class=\"form-control\" />\n                <div class=\"input-group-btn input-group-append\">";
        if (WHMCS\Config\Setting::getValue("AllowRegister")) {
            $bodyContent .= "\n                    <input type=\"submit\" value=\"" . Lang::trans("domainsregister") . "\" class=\"btn btn-success\" />";
        }
        if (WHMCS\Config\Setting::getValue("AllowTransfer")) {
            $bodyContent .= "\n                    <input type=\"submit\" name=\"transfer\" value=\"" . Lang::trans("domainstransfer") . "\" class=\"btn btn-default\" />";
        }
        $bodyContent .= "\n                </div>\n            </div>\n        </form>";
        $panels[] = ["name" => "Register a New Domain", "label" => Lang::trans("navregisterdomain"), "icon" => "fa-globe", "attributes" => ["id" => "registerDomainPanel"], "extras" => ["color" => "emerald"], "bodyHtml" => $bodyContent, "order" => "200"];
    }
    if (WHMCS\Config\Setting::getValue("AffiliateEnabled") && checkContactPermission("affiliates", true) && !is_null($affiliate = $ca->getClient()->affiliate)) {
        $currencyLimit = convertCurrency(WHMCS\Config\Setting::getValue("AffiliatePayout"), 1, $currency["id"]);
        $amountUntilWithdrawal = $currencyLimit - $affiliate->balance;
        if (0 < $amountUntilWithdrawal) {
            $msgTemplate = "clientHomePanels.affiliateSummary";
        } else {
            $msgTemplate = "clientHomePanels.affiliateSummaryWithdrawalReady";
        }
        $msg = Lang::trans($msgTemplate, [":commissionBalance" => formatCurrency($affiliate->balance), ":amountUntilWithdrawalLevel" => formatCurrency($amountUntilWithdrawal)]);
        $panels[] = ["name" => "Affiliate Program", "label" => Lang::trans("clientHomePanels.affiliateProgram"), "icon" => "fa-users", "attributes" => ["id" => "affiliatesPanel"], "extras" => ["color" => "teal", "btn-icon" => "fas fa-arrow-right", "btn-link" => "affiliates.php", "btn-text" => Lang::trans("moreDetails")], "bodyHtml" => "<p>" . $msg . "</p>", "order" => "300"];
    }
    if (!function_exists("AddNote")) {
        require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
    }
    $tickets = [];
    $statusfilter = [];
    $result = select_query("tblticketstatuses", "title", ["showactive" => "1"]);
    while ($data = mysql_fetch_array($result)) {
        $statusfilter[] = $data[0];
    }
    $result = select_query("tbltickets", "", ["userid" => (int) $legacyClient->getID(), "status" => ["sqltype" => "IN", "values" => $statusfilter], "merged_ticket_id" => 0], "lastreply", "DESC");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $tid = $data["tid"];
        $c = $data["c"];
        $deptid = $data["did"];
        $date = $data["date"];
        $date = fromMySQLDate($date, 1, 1);
        $subject = $data["title"];
        $status = $data["status"];
        $urgency = $data["urgency"];
        $lastreply = $data["lastreply"];
        $lastreply = fromMySQLDate($lastreply, 1, 1);
        $clientunread = $data["clientunread"];
        $htmlFormattedStatus = getStatusColour($status);
        $dept = getDepartmentName($deptid);
        $urgency = Lang::trans("supportticketsticketurgency" . strtolower($urgency));
        $statusClass = WHMCS\View\Helper::generateCssFriendlyClassName($status);
        $tickets[] = ["id" => $id, "tid" => $tid, "c" => $c, "date" => $date, "department" => $dept, "subject" => $subject, "status" => $htmlFormattedStatus, "statusClass" => $statusClass, "urgency" => $urgency, "lastreply" => $lastreply, "unread" => $clientunread];
    }
    $ca->assign("tickets", $tickets);
    if (checkContactPermission("tickets", true)) {
        $ticketsList = [];
        $rawStatusColors = WHMCS\Database\Capsule::table("tblticketstatuses")->get()->all();
        $ticketRows = WHMCS\Database\Capsule::table("tbltickets")->where("userid", "=", $legacyClient->getID())->where("merged_ticket_id", "=", "0")->orderBy("lastreply", "DESC")->limit(10)->get()->all();
        foreach ($ticketRows as $data) {
            $id = $data->id;
            $tid = $data->tid;
            $c = $data->c;
            $subject = $data->title;
            $status = $data->status;
            $lastreply = $data->lastreply;
            $clientunread = $data->clientunread;
            $lastreply = fromMySQLDate($lastreply, 1, 1);
            $statusColors = [];
            foreach ($rawStatusColors as $color) {
                $statusColors[$color->title] = $color->color;
            }
            $langStatus = preg_replace("/[^a-z]/i", "", strtolower($status));
            if (Lang::trans("supportticketsstatus" . $langStatus) != "supportticketsstatus" . $langStatus) {
                $statusText = Lang::trans("supportticketsstatus" . $langStatus);
            } else {
                $statusText = $status;
            }
            $ticketsList[] = ["uri" => "viewticket.php?tid=" . $tid . "&c=" . $c, "label" => ($clientunread ? "<strong>" : "") . "#" . $tid . " - " . $subject . ($clientunread ? "</strong> " : " ") . "<label class=\"label\" style=\"background-color: " . $statusColors[$status] . "\">" . $statusText . "</label><br />" . "<small>" . Lang::trans("supportticketsticketlastupdated") . ": " . $lastreply . "</small>"];
        }
        $ticketsPanel = ["name" => "Recent Support Tickets", "label" => Lang::trans("clientHomePanels.recentSupportTickets"), "icon" => "fa-comments", "attributes" => ["id" => "ticketsPanel"], "extras" => ["color" => "blue", "btn-icon" => "fas fa-plus", "btn-link" => "submitticket.php", "btn-text" => Lang::trans("opennewticket")], "children" => $ticketsList, "order" => "150"];
        if (count($ticketsList) == 0) {
            $ticketsPanel["bodyHtml"] = "<p>" . Lang::trans("clientHomePanels.recentSupportTicketsNone") . "</p>";
        }
        $panels[] = $ticketsPanel;
    }
    $files = $legacyClient->getFiles();
    if (0 < count($files)) {
        $filesList = [];
        foreach ($files as $file) {
            $filesList[] = ["label" => $file["title"] . "<br /><small>" . $file["date"] . "</small>", "uri" => "dl.php?type=f&id=" . $file["id"]];
        }
        $panels[] = ["name" => "Your Files", "label" => Lang::trans("clientareafiles"), "icon" => "fa-download", "attributes" => ["id" => "filesPanel"], "extras" => ["color" => "purple"], "children" => $filesList, "order" => "250"];
    }
    $announcementsList = [];
    $announcements = WHMCS\Announcement\Announcement::wherePublished(true)->orderBy("date", "DESC")->take(3)->get();
    foreach ($announcements as $announcement) {
        $announcementTitle = $announcement->title;
        $announcementContent = $announcement->announcement;
        if ($activeLanguage) {
            try {
                $announcementLocal = WHMCS\Announcement\Announcement::whereParentid($announcement->id)->whereLanguage($activeLanguage)->firstOrFail();
                $announcementTitle = $announcementLocal->title;
                $announcementContent = $announcementLocal->announcement;
            } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            }
        }
        $uri = getModRewriteFriendlyString($announcementTitle);
        $announcementsList[] = ["id" => $announcement->id, "date" => fromMySQLDate($announcement->date, 0, 1), "title" => $announcementTitle, "urlfriendlytitle" => $uri, "text" => $announcementContent, "label" => $announcementTitle . "<br /><span class=\"text-last-updated\">" . fromMySQLDate($announcement->publishDate, 0, 1) . "</span>", "uri" => routePath("announcement-view", $announcement->id, $uri)];
    }
    $smartyvalues["announcements"] = $announcementsList;
    $panels[] = ["name" => "Recent News", "label" => Lang::trans("clientHomePanels.recentNews"), "icon" => "far fa-newspaper", "attributes" => ["id" => "announcementsPanel"], "extras" => ["color" => "asbestos", "btn-icon" => "fas fa-arrow-right", "btn-link" => routePath("announcement-index"), "btn-text" => Lang::trans("viewAll")], "children" => $announcementsList, "order" => "500"];
    $smartyvalues["registerdomainenabled"] = (bool) WHMCS\Config\Setting::getValue("AllowRegister");
    $smartyvalues["transferdomainenabled"] = (bool) WHMCS\Config\Setting::getValue("AllowTransfer");
    $smartyvalues["owndomainenabled"] = (bool) WHMCS\Config\Setting::getValue("AllowOwnDomain");
    $captcha = new WHMCS\Utility\Captcha();
    $smartyvalues["captcha"] = $captcha;
    $smartyvalues["captchaForm"] = WHMCS\Utility\Captcha::FORM_REGISTRATION;
    $smartyvalues["recaptchahtml"] = clientAreaReCaptchaHTML();
    $smartyvalues["contacts"] = $legacyClient->getContacts();
    $addons_html = run_hook("ClientAreaHomepage", []);
    $ca->assign("addons_html", $addons_html);
    $factory = new WHMCS\View\Menu\MenuFactory();
    $item = $factory->getLoader()->load(["name" => "ClientAreaHomePagePanels", "children" => $panels]);
    run_hook("ClientAreaHomepagePanels", [$item], true);
    $smartyvalues["panels"] = WHMCS\View\Menu\Item::sort($item);
    $ca->addOutputHookFunction("ClientAreaPageHome");
} else {
    if ($action == "details") {
        checkContactPermission("profile");
        $ca->setDisplayTitle(Lang::trans("clientareanavdetails"));
        $ca->setTemplate("clientareadetails");
        $ca->addToBreadCrumb("clientarea.php?action=details", Lang::trans("clientareanavdetails"));
        $optionalFields = explode(",", WHMCS\Config\Setting::getValue("ClientsProfileOptionalFields"));
        $uneditablefields = explode(",", WHMCS\Config\Setting::getValue("ClientsProfileUneditableFields"));
        $smartyvalues["optionalFields"] = $optionalFields;
        $smartyvalues["uneditablefields"] = $uneditablefields;
        $e = "";
        $exdetails = [];
        $ca->assign("successful", false);
        if ($save) {
            check_token();
            $emailFlags = CHECKDETAILS_EMAIL_ALL ^ CHECKDETAILS_EMAIL_UNIQUE_USER;
            if (in_array("email", $uneditablefields)) {
                $emailFlags = CHECKDETAILS_EMAIL_NONE;
            } else {
                if (App::getFromRequest("email") === $legacyClient->getDetails()["email"]) {
                    $emailFlags ^= CHECKDETAILS_EMAIL_BANNED_DOMAIN;
                }
            }
            $e = checkDetailsareValid($legacyClient->getID(), false, $emailFlags);
            unset($emailFlags);
            if ($e) {
                $ca->assign("errormessage", $e);
            } else {
                $legacyClient->updateClient();
                redir("action=details&success=1");
            }
        }
        if ($whmcs->get_req_var("success")) {
            $ca->assign("successful", true);
        }
        $exdetails = $legacyClient->getDetails();
        $countries = new WHMCS\Utility\Country();
        $ca->assign("clientfirstname", $whmcs->get_req_var_if($e, "firstname", $exdetails));
        $ca->assign("clientlastname", $whmcs->get_req_var_if($e, "lastname", $exdetails));
        $ca->assign("clientcompanyname", $whmcs->get_req_var_if($e, "companyname", $exdetails));
        $ca->assign("clientemail", $whmcs->get_req_var_if($e, "email", $exdetails));
        $ca->assign("clientaddress1", $whmcs->get_req_var_if($e, "address1", $exdetails));
        $ca->assign("clientaddress2", $whmcs->get_req_var_if($e, "address2", $exdetails));
        $ca->assign("clientcity", $whmcs->get_req_var_if($e, "city", $exdetails));
        $ca->assign("clientstate", $whmcs->get_req_var_if($e, "state", $exdetails));
        $ca->assign("clientpostcode", $whmcs->get_req_var_if($e, "postcode", $exdetails));
        $ca->assign("clientcountry", $countries->getName($whmcs->get_req_var_if($e, "country", $exdetails)));
        $ca->assign("clientcountriesdropdown", getCountriesDropDown($whmcs->get_req_var_if($e, "country", $exdetails), "", "", false, in_array("country", $uneditablefields)));
        $phoneNumber = $e ? App::formatPostedPhoneNumber() : $exdetails["telephoneNumber"];
        $ca->assign("clientphonenumber", $phoneNumber);
        $ca->assign("clientTaxId", $whmcs->get_req_var_if($e, "tax_id", $exdetails));
        $ca->assign("customfields", getCustomFields("client", "", $legacyClient->getID(), "", "", $whmcs->get_req_var("customfield")));
        $ca->assign("contacts", $legacyClient->getContacts());
        $ca->assign("billingcid", $whmcs->get_req_var_if($e, "billingcid", $exdetails));
        $ca->assign("paymentmethods", showPaymentGatewaysList([], $legacyClient->getID()));
        $ca->assign("taxIdLabel", WHMCS\Billing\Tax\Vat::getLabel());
        $ca->assign("showTaxIdField", WHMCS\Billing\Tax\Vat::isUsingNativeField());
        $ca->assign("showMarketingEmailOptIn", WHMCS\Config\Setting::getValue("AllowClientsEmailOptOut"));
        $ca->assign("marketingEmailOptInMessage", Lang::trans("emailMarketing.optInMessage") != "emailMarketing.optInMessage" ? Lang::trans("emailMarketing.optInMessage") : WHMCS\Config\Setting::getValue("EmailMarketingOptInMessage"));
        $ca->assign("marketingEmailOptIn", App::isInRequest("marketingoptin") ? (bool) App::getFromRequest("marketingoptin") : $legacyClient->getClientModel()->isOptedInToMarketingEmails());
        $ca->assign("defaultpaymentmethod", $whmcs->get_req_var_if($e, "paymentmethod", $exdetails, "defaultgateway"));
        $emailPreferences = [];
        foreach (WHMCS\Mail\Emailer::CLIENT_EMAILS as $emailType) {
            $emailPreferences[$emailType] = App::get_req_var_if($e, "email_preferences", $exdetails, "", $emailType);
        }
        $ca->assign("emailPreferencesEnabled", !WHMCS\Config\Setting::getValue("DisableClientEmailPreferences"));
        $ca->assign("emailPreferences", $emailPreferences);
        $ca->assign("clientLanguage", $exdetails["language"]);
        $ca->assign("languages", WHMCS\Language\ClientLanguage::getLanguages());
        foreach ($uneditablefields as $field) {
            $ca->assign("client" . $field, $exdetails[$field] ?? NULL, true);
        }
        $ca->addOutputHookFunction("ClientAreaPageProfile");
    } else {
        if ($action == "contacts") {
            App::redirectToRoutePath("account-contacts", [], ["contactid" => App::getFromRequest("id")]);
        } else {
            if ($action == "addcontact") {
                App::redirectToRoutePath("account-contacts");
            } else {
                if ($action == "creditcard") {
                    App::redirectToRoutePath("account-paymentmethods");
                } else {
                    if ($action == "changepw") {
                        App::redirectToRoutePath("user-password");
                    } else {
                        if ($action == "security") {
                            $ca->setDisplayTitle(Lang::trans("navAccountSecurity"));
                            $ca->setTemplate("clientareasecurity");
                            $ca->addToBreadCrumb("clientarea.php?action=details", $whmcs->get_lang("clientareanavdetails"));
                            $ca->addToBreadCrumb("clientarea.php?action=security", $whmcs->get_lang("navAccountSecurity"));
                            if ($whmcs->get_req_var("toggle_sso")) {
                                check_token();
                                $client = $ca->getClient();
                                $client->allowSso = (bool) $whmcs->get_req_var("allow_sso");
                                $client->save();
                                exit;
                            }
                            $smartyvalues["showSsoSetting"] = 1 <= WHMCS\ApplicationLink\ApplicationLink::whereIsEnabled(1)->count();
                            $smartyvalues["isSsoEnabled"] = $ca->getClient()->allowSso;
                            $ca->addOutputHookFunction("ClientAreaPageSecurity");
                        } else {
                            if (in_array($action, ["hosting", "products", "services"])) {
                                checkContactPermission("products");
                                $ca->setDisplayTitle(Lang::trans("clientareaproducts"));
                                $ca->setTemplate("clientareaproducts");
                                $table = "tblhosting";
                                $fields = "COUNT(*)";
                                $where = "userid='" . db_escape_string($legacyClient->getID()) . "'";
                                if ($q) {
                                    $q = preg_replace("/[^a-z0-9-.]/", "", strtolower($q));
                                    $where .= " AND domain LIKE '%" . db_escape_string($q) . "%'";
                                    $smartyvalues["q"] = $q;
                                }
                                if ($module = $whmcs->get_req_var("module")) {
                                    $where .= " AND tblproducts.servertype='" . db_escape_string($module) . "'";
                                }
                                $innerjoin = "tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblproductgroups ON tblproductgroups.id=tblproducts.gid";
                                $result = select_query($table, $fields, $where, "", "", "", $innerjoin);
                                $data = mysql_fetch_array($result);
                                $numitems = $data[0];
                                list($orderby, $sort, $limit) = clientAreaTableInit("prod", "product", "ASC", $numitems);
                                $smartyvalues["orderby"] = $orderby;
                                $smartyvalues["sort"] = strtolower($sort);
                                if ($orderby == "price") {
                                    $orderby = "amount";
                                } else {
                                    if ($orderby == "billingcycle") {
                                        $orderby = "billingcycle";
                                    } else {
                                        if ($orderby == "nextduedate") {
                                            $orderby = "nextduedate";
                                        } else {
                                            if ($orderby == "status") {
                                                $orderby = "domainstatus";
                                            } else {
                                                $orderby = "domain` " . $sort . ",`tblproducts`.`name";
                                            }
                                        }
                                    }
                                }
                                $clientSslStatuses = WHMCS\Domain\Ssl\Status::where("user_id", $legacyClient->getID())->get();
                                $productCache = [];
                                $accounts = [];
                                $fields = "tblhosting.*,tblproductgroups.id AS group_id,tblproducts.name as product_name,tblproducts.tax,tblproductgroups.name as group_name,tblproducts.servertype,tblproducts.type";
                                $result = select_query($table, $fields, $where, $orderby, $sort, $limit, $innerjoin);
                                while ($data = mysql_fetch_array($result)) {
                                    $id = $data["id"];
                                    $productId = $data["packageid"];
                                    $regdate = $data["regdate"];
                                    $domain = $data["domain"];
                                    $firstpaymentamount = $data["firstpaymentamount"];
                                    $recurringamount = $data["amount"];
                                    $nextduedate = $data["nextduedate"];
                                    $billingcycle = $data["billingcycle"];
                                    $status = $data["domainstatus"];
                                    $tax = $data["tax"];
                                    $server = $data["server"];
                                    $username = $data["username"];
                                    $module = $data["servertype"];
                                    if (!isset($productCache["downloads"][$productId])) {
                                        $productCache["downloads"][$productId] = WHMCS\Product\Product::find($productId)->getDownloadIds();
                                    }
                                    if (!isset($productCache["upgrades"][$productId])) {
                                        $productCache["upgrades"][$productId] = WHMCS\Product\Product::find($productId)->getUpgradeProductIds();
                                    }
                                    if (!isset($productCache["groupNames"][$data["group_id"]])) {
                                        $productCache["groupNames"][$data["group_id"]] = WHMCS\Product\Group::getGroupName($data["group_id"], $data["group_name"]);
                                    }
                                    if (!isset($productCache["productNames"][$data["packageid"]])) {
                                        $productCache["productNames"][$data["packageid"]] = WHMCS\Product\Product::getProductName($data["packageid"], $data["product_name"]);
                                    }
                                    if (0 < $server && !isset($productCache["servers"][$server])) {
                                        $productCache["servers"][$server] = get_query_vals("tblservers", "", ["id" => $server]);
                                    }
                                    $downloads = $productCache["downloads"][$productId];
                                    $upgradepackages = $productCache["upgrades"][$productId];
                                    $productgroup = $productCache["groupNames"][$data["group_id"]];
                                    $productname = $productCache["productNames"][$data["packageid"]];
                                    $serverarray = 0 < $server ? $productCache["servers"][$server] : [];
                                    $normalisedRegDate = $regdate;
                                    $regdate = fromMySQLDate($regdate, 0, 1, "-");
                                    $normalisedNextDueDate = $nextduedate;
                                    $nextduedate = fromMySQLDate($nextduedate, 0, 1, "-");
                                    $langbillingcycle = $ca->getRawStatus($billingcycle);
                                    $rawstatus = $ca->getRawStatus($status);
                                    $legacyClassTplVar = $status;
                                    if (!in_array($legacyClassTplVar, ["Active", "Completed", "Pending", "Suspended"])) {
                                        $legacyClassTplVar = "Terminated";
                                    }
                                    $amount = $billingcycle == "One Time" ? $firstpaymentamount : $recurringamount;
                                    $isDomain = filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
                                    if ($data["type"] == "other") {
                                        $isDomain = false;
                                    }
                                    $isActive = in_array($status, ["Active", "Completed"]);
                                    $sslStatus = NULL;
                                    if ($isDomain !== false && $isActive) {
                                        $sslStatus = $clientSslStatuses->where("domain_name", $domain)->first();
                                        if (is_null($sslStatus)) {
                                            $sslStatus = WHMCS\Domain\Ssl\Status::factory($legacyClient->getID(), $domain);
                                        }
                                    }
                                    $accounts[] = ["id" => $id, "regdate" => $regdate, "normalisedRegDate" => $normalisedRegDate, "group" => $productgroup, "product" => $productname, "module" => $module, "server" => $serverarray, "domain" => $domain, "firstpaymentamount" => formatCurrency($firstpaymentamount), "recurringamount" => formatCurrency($recurringamount), "amountnum" => $amount, "amount" => formatCurrency($amount), "nextduedate" => $nextduedate, "normalisedNextDueDate" => $normalisedNextDueDate, "billingcycle" => Lang::trans("orderpaymentterm" . $langbillingcycle), "username" => $username, "status" => $status, "statusClass" => WHMCS\View\Helper::generateCssFriendlyClassName($status), "statustext" => Lang::trans("clientarea" . $rawstatus), "rawstatus" => $rawstatus, "class" => strtolower($legacyClassTplVar), "addons" => get_query_val("tblhostingaddons", "id", ["hostingid" => $id], "id", "DESC") ? true : false, "packagesupgrade" => 0 < count($upgradepackages), "downloads" => 0 < count($downloads), "showcancelbutton" => (bool) WHMCS\Config\Setting::getValue("ShowCancellationButton"), "sslStatus" => $sslStatus, "isActive" => $isActive];
                                }
                                $ca->assign("services", $accounts);
                                $smartyvalues = array_merge($smartyvalues, clientAreaTablePageNav($numitems));
                                $ca->addOutputHookFunction("ClientAreaPageProductsServices");
                            } else {
                                if ($action == "productdetails") {
                                    checkContactPermission("products");
                                    $ca->setDisplayTitle(Lang::trans("manageproduct"));
                                    $ca->setTemplate("clientareaproductdetails");
                                    $service = new WHMCS\Service($id, $legacyClient->getID());
                                    if ($service->isNotValid()) {
                                        redir("action=products", "clientarea.php");
                                    }
                                    $serviceModel = WHMCS\Service\Service::find($service->getID());
                                    $ca->addToBreadCrumb("clientarea.php?action=products", $whmcs->get_lang("clientareaproducts"));
                                    $ca->addToBreadCrumb("clientarea.php?action=productdetails#", $whmcs->get_lang("clientareaproductdetails"));
                                    $customfields = $service->getCustomFields();
                                    $domainIds = WHMCS\Domain\Domain::where("userid", $legacyClient->getID())->where("domain", $service->getData("domain"))->where("status", "Active")->pluck("id")->all();
                                    if (count($domainIds) < 1) {
                                        $domainIds = WHMCS\Domain\Domain::where("userid", $legacyClient->getID())->where("domain", $service->getData("domain"))->where("status", "!=", "Fraud")->pluck("id")->all();
                                    }
                                    if (count($domainIds) < 1) {
                                        $domainIds = WHMCS\Domain\Domain::where("userid", $legacyClient->getID())->where("domain", $service->getData("domain"))->where("status", "Fraud")->pluck("id")->all();
                                    }
                                    if (count($domainIds) < 1) {
                                        $domainId = "";
                                    } else {
                                        $domainId = array_shift($domainIds);
                                    }
                                    $ca->assign("id", $service->getData("id"));
                                    $ca->assign("domainId", $domainId);
                                    $ca->assign("serviceid", $service->getData("id"));
                                    $ca->assign("pid", $service->getData("packageid"));
                                    $ca->assign("producttype", $service->getData("type"));
                                    $ca->assign("type", $service->getData("type"));
                                    $ca->assign("regdate", fromMySQLDate($service->getData("regdate"), 0, 1, "-"));
                                    $ca->assign("modulename", $service->getModule());
                                    $ca->assign("module", $service->getModule());
                                    $ca->assign("serverdata", $service->getServerInfo());
                                    $ca->assign("domain", $service->getData("domain"));
                                    $ca->assign("domainValid", str_replace(".", "", $service->getData("domain")) != $service->getData("domain"));
                                    $ca->assign("groupname", $service->getData("groupname"));
                                    $ca->assign("product", $service->getData("productname"));
                                    $ca->assign("paymentmethod", $service->getPaymentMethod());
                                    $ca->assign("firstpaymentamount", formatCurrency($service->getData("firstpaymentamount")));
                                    $ca->assign("recurringamount", formatCurrency($service->getData("amount")));
                                    $ca->assign("billingcycle", $service->getBillingCycleDisplay());
                                    $ca->assign("nextduedate", fromMySQLDate($service->getData("nextduedate"), 0, 1, "-"));
                                    $ca->assign("systemStatus", $service->getData("status"));
                                    $ca->assign("status", $service->getStatusDisplay());
                                    $ca->assign("rawstatus", strtolower($service->getData("status")));
                                    $ca->assign("dedicatedip", $service->getData("dedicatedip"));
                                    $ca->assign("assignedips", $service->getData("assignedips"));
                                    $ca->assign("ns1", $service->getData("ns1"));
                                    $ca->assign("ns2", $service->getData("ns2"));
                                    $ca->assign("packagesupgrade", $service->getAllowProductUpgrades());
                                    $ca->assign("configoptionsupgrade", $service->getAllowConfigOptionsUpgrade());
                                    $ca->assign("customfields", $customfields);
                                    $ca->assign("productcustomfields", $customfields);
                                    $ca->assign("suspendreason", $service->getSuspensionReason());
                                    $ca->assign("subscriptionid", $service->getData("subscriptionid"));
                                    $serviceModel = $service->getData("serviceModel");
                                    $ca->assign("quantitySupported", $serviceModel->product->allowMultipleQuantities === WHMCS\Cart\CartCalculator::QUANTITY_SCALING);
                                    $ca->assign("quantity", $service->getData("qty"));
                                    $isDomain = str_replace(".", "", $service->getData("domain")) != $service->getData("domain");
                                    if ($service->getData("type") == "other") {
                                        $isDomain = false;
                                    }
                                    $sslStatus = NULL;
                                    if ($isDomain) {
                                        $sslStatus = WHMCS\Domain\Ssl\Status::factory($legacyClient->getID(), $service->getData("domain"));
                                    }
                                    $ca->assign("sslStatus", $sslStatus);
                                    $diskstats = $service->getDiskUsageStats();
                                    foreach ($diskstats as $k => $v) {
                                        $ca->assign($k, $v);
                                    }
                                    $availableAddonIds = [];
                                    $availableAddonProducts = [];
                                    if ($service->getData("status") == "Active") {
                                        $predefinedAddonProducts = $service->getPredefinedAddonsOnce();
                                        $availableAddonIds = $service->hasProductGotAddons();
                                        $availableAddonModels = WHMCS\Product\Addon::whereIn("id", $availableAddonIds)->isNotHidden()->isNotRetired()->get();
                                        foreach ($availableAddonModels as $addonModel) {
                                            $availableAddonProducts[$addonModel->id] = $addonModel->name;
                                        }
                                    }
                                    $ca->assign("showcancelbutton", $service->getAllowCancellation());
                                    $ca->assign("configurableoptions", $service->getConfigurableOptions());
                                    $ca->assign("addons", $service->getAddons());
                                    $ca->assign("addonsavailable", $availableAddonIds);
                                    $ca->assign("availableAddonProducts", $availableAddonProducts);
                                    $ca->assign("downloads", $service->getAssociatedDownloads());
                                    $ca->assign("pendingcancellation", $service->hasCancellationRequest());
                                    $ca->assign("username", $service->getData("username"));
                                    $ca->assign("password", $service->getData("password"));
                                    $metrics = $serviceModel->metrics(true);
                                    $metricStats = [];
                                    if (WHMCS\UsageBilling\MetricUsageSettings::isCollectionEnable()) {
                                        foreach ($metrics as $serviceMetric) {
                                            if ($serviceMetric->isEnabled()) {
                                                $units = $serviceMetric->units();
                                                $historicalUsage = $serviceMetric->historicUsage();
                                                if ($historicalUsage) {
                                                    $postPeriodDateRange = $historicalUsage->startAt()->toAdminDateFormat() . " - " . $historicalUsage->endAt()->toAdminDateFormat();
                                                    $postPeriodValue = $units->decorate($units->roundForType($historicalUsage->value()));
                                                } else {
                                                    $postPeriodDateRange = "";
                                                    $postPeriodValue = "-";
                                                }
                                                $currentValue = $units->decorate($units->roundForType($serviceMetric->usage()->value()));
                                                $pricing = [];
                                                $pricingSchema = $serviceMetric->usageItem()->pricingSchema;
                                                $freeLimit = $serviceMetric->usageItem()->included;
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
                                                $usage = $serviceMetric->usage();
                                                if ($usage instanceof WHMCS\UsageBilling\Contracts\Metrics\UsageStubInterface) {
                                                    $currentValue = $lastUpdated = "&mdash;";
                                                } else {
                                                    $lastUpdated = $usage->collectedAt();
                                                }
                                                $metricStats[] = ["type" => $serviceMetric->type(), "systemName" => $serviceMetric->systemName(), "displayName" => $serviceMetric->displayName(), "unitName" => $units->perUnitName(1), "includedQuantity" => $freeLimit, "includedQuantityUnits" => $units->perUnitName($freeLimit), "lowestPrice" => $lowestPrice, "pricingSchema" => ["info" => Lang::trans($baseLangKey . ".info"), "detail" => Lang::trans($baseLangKey . ".detail")], "pricing" => $pricing, "postPeriodValue" => $postPeriodValue, "postPeriodDateRange" => $postPeriodDateRange, "currentValue" => $currentValue, "lastUpdated" => $lastUpdated];
                                            }
                                        }
                                    }
                                    $ca->assign("metricStats", $metricStats);
                                    $hookResponses = run_hook("ClientAreaProductDetailsOutput", ["service" => $serviceModel]);
                                    $ca->assign("hookOutput", $hookResponses);
                                    $hookResponses = run_hook("ClientAreaProductDetailsPreModuleTemplate", $ca->getTemplateVariables());
                                    foreach ($hookResponses as $hookTemplateVariables) {
                                        foreach ($hookTemplateVariables as $k => $v) {
                                            $ca->assign($k, $v);
                                        }
                                    }
                                    $tplOverviewTabOutput = "";
                                    $moduleClientAreaOutput = "";
                                    $clientAreaCustomButtons = [];
                                    $ca->assign("modulecustombuttonresult", "");
                                    if (App::isInRequest("addonId") && 0 < (int) App::getFromRequest("addonId") && App::getFromRequest("modop") == "custom") {
                                        $service = new WHMCS\Addon();
                                        $service->setAddonId(App::getFromRequest("addonId"));
                                    }
                                    $smartyvalues["modulechangepwresult"] = NULL;
                                    if ($service->getModule()) {
                                        $moduleInterface = new WHMCS\Module\Server();
                                        if ($service instanceof WHMCS\Addon) {
                                            $moduleInterface->loadByAddonId($service->getID());
                                        } else {
                                            $moduleInterface->loadByServiceID($service->getID());
                                        }
                                        if ($whmcs->get_req_var("dosinglesignon") && checkContactPermission("productsso", true)) {
                                            if ($service->getData("status") == "Active") {
                                                try {
                                                    $redirectUrl = $moduleInterface->getSingleSignOnUrlForService();
                                                    header("Location: " . $redirectUrl);
                                                    exit;
                                                } catch (WHMCS\Exception\Module\SingleSignOnError $e) {
                                                    $ca->assign("modulecustombuttonresult", $whmcs->get_lang("ssounabletologin"));
                                                } catch (Exception $e) {
                                                    logActivity("Single Sign-On Request Failed with a Fatal Error: " . $e->getMessage());
                                                    $ca->assign("modulecustombuttonresult", $whmcs->get_lang("ssofatalerror"));
                                                }
                                            } else {
                                                $ca->assign("modulecustombuttonresult", Lang::trans("productMustBeActiveForModuleCmds"));
                                            }
                                        } else {
                                            if ($whmcs->get_req_var("dosinglesignon")) {
                                                $ca->assign("modulecustombuttonresult", Lang::trans("subaccountSsoDenied"));
                                            }
                                        }
                                        if (App::getFromRequest("doaddonsignon") && checkContactPermission("productsso", true)) {
                                            if ($service->getData("status") === WHMCS\Utility\Status::ACTIVE) {
                                                try {
                                                    $addonAutomation = WHMCS\Service\Automation\AddonAutomation::factory((int) App::getFromRequest("addonId"));
                                                    $redirectUrl = $addonAutomation->singleSignOnAddOnFeature();
                                                    header("Location: " . $redirectUrl);
                                                    WHMCS\Terminus::getInstance()->doExit();
                                                } catch (Exception $e) {
                                                    logActivity("Single Sign-On Request Failed with a Fatal Error: " . $e->getMessage());
                                                    $ca->assign("modulecustombuttonresult", Lang::trans("ssofatalerror"));
                                                }
                                            }
                                        } else {
                                            if (App::getFromRequest("doaddonsignon")) {
                                                $ca->assign("modulecustombuttonresult", Lang::trans("subaccountSsoDenied"));
                                            }
                                        }
                                        if (App::getFromRequest("customaction_error")) {
                                            $ca->assign("modulecustombuttonresult", WHMCS\Session::getAndDelete("customaction_error"));
                                        } else {
                                            if (App::getFromRequest("customaction_ajax_error")) {
                                                $ca->assign("modulecustombuttonresult", Lang::trans("customActionGenericError"));
                                            }
                                        }
                                        $moduleFolderPath = $moduleInterface->getBaseModuleDir() . DIRECTORY_SEPARATOR . $service->getModule();
                                        $moduleFolderPath = substr($moduleFolderPath, strlen(ROOTDIR));
                                        $allowedModuleFunctions = [];
                                        $success = $service->moduleCall("ClientAreaAllowedFunctions");
                                        if ($success) {
                                            $clientAreaAllowedFunctions = $service->getModuleReturn("data");
                                            if (is_array($clientAreaAllowedFunctions)) {
                                                foreach ($clientAreaAllowedFunctions as $functionName) {
                                                    if (is_string($functionName)) {
                                                        $allowedModuleFunctions[] = $functionName;
                                                    }
                                                }
                                            }
                                        }
                                        $success = $service->moduleCall("ClientAreaCustomButtonArray");
                                        if ($success) {
                                            $clientAreaCustomButtons = $service->getModuleReturn("data");
                                            if (is_array($clientAreaCustomButtons)) {
                                                foreach ($clientAreaCustomButtons as $buttonLabel => $functionName) {
                                                    if (is_string($functionName)) {
                                                        $allowedModuleFunctions[] = $functionName;
                                                    }
                                                }
                                            }
                                        }
                                        $moduleOperation = $whmcs->get_req_var("modop");
                                        $moduleAction = $whmcs->get_req_var("a");
                                        if ($serverAction = $whmcs->get_req_var("serveraction")) {
                                            $moduleOperation = $serverAction;
                                        }
                                        if ($moduleOperation == "custom" && in_array($moduleAction, $allowedModuleFunctions)) {
                                            if ($service->getData("status") == "Active") {
                                                checkContactPermission("manageproducts");
                                                $success = $service->moduleCall($moduleAction);
                                                if ($success) {
                                                    $data = $service->getModuleReturn("data");
                                                    if (is_array($data)) {
                                                        if (isset($data["jsonResponse"])) {
                                                            $response = new WHMCS\Http\JsonResponse();
                                                            $response->setData($data["jsonResponse"]);
                                                            $response->send();
                                                            exit;
                                                        }
                                                        if (isset($data["overrideDisplayTitle"])) {
                                                            $ca->setDisplayTitle($data["overrideDisplayTitle"]);
                                                        }
                                                        if (isset($data["overrideBreadcrumb"]) && is_array($data["overrideBreadcrumb"])) {
                                                            $ca->resetBreadCrumb()->addToBreadCrumb("index.php", $whmcs->get_lang("globalsystemname"))->addToBreadCrumb("clientarea.php", $whmcs->get_lang("clientareatitle"));
                                                            foreach ($data["overrideBreadcrumb"] as $breadcrumb) {
                                                                $ca->addToBreadCrumb($breadcrumb[0], $breadcrumb[1]);
                                                            }
                                                        }
                                                        if (isset($data["appendToBreadcrumb"]) && is_array($data["appendToBreadcrumb"])) {
                                                            foreach ($data["appendToBreadcrumb"] as $breadcrumb) {
                                                                $ca->addToBreadCrumb($breadcrumb[0], $breadcrumb[1]);
                                                            }
                                                        }
                                                        if (isset($data["outputTemplateFile"])) {
                                                            $ca->setTemplate($moduleInterface->findTemplate($data["outputTemplateFile"]));
                                                        } else {
                                                            if (isset($data["templatefile"])) {
                                                                $ca->setTemplate($moduleInterface->findTemplate($data["templatefile"] . ".tpl"));
                                                            }
                                                        }
                                                        if (isset($data["breadcrumb"]) && is_array($data["breadcrumb"])) {
                                                            foreach ($data["breadcrumb"] as $href => $label) {
                                                                $ca->addToBreadCrumb($href, $label);
                                                            }
                                                        }
                                                        if (is_array($data["templateVariables"]) || is_array($data["vars"])) {
                                                            $templateVars = isset($data["templateVariables"]) ? $data["templateVariables"] : $data["vars"];
                                                            foreach ($templateVars as $key => $value) {
                                                                $ca->assign($key, $value);
                                                            }
                                                        }
                                                    } else {
                                                        $ca->assign("modulecustombuttonresult", "success");
                                                    }
                                                } else {
                                                    $ca->assign("modulecustombuttonresult", $service->getLastError());
                                                }
                                            } else {
                                                $ca->assign("modulecustombuttonresult", Lang::trans("productMustBeActiveForModuleCmds"));
                                            }
                                        }
                                        $smartyvalues["modulechangepwresult"] = "";
                                        if ($service->getData("status") == "Active" && $service->hasFunction("ChangePassword") && $service->getAllowChangePassword()) {
                                            $ca->assign("serverchangepassword", true);
                                            $ca->assign("modulechangepassword", true);
                                            $modulechangepasswordmessage = "";
                                            $modulechangepassword = $whmcs->get_req_var("modulechangepassword");
                                            if ($whmcs->get_req_var("serverchangepassword")) {
                                                $modulechangepassword = true;
                                            }
                                            if ($modulechangepassword) {
                                                check_token();
                                                checkContactPermission("manageproducts");
                                                $newpwfield = "newpw";
                                                $newpassword1 = $whmcs->get_req_var("newpw");
                                                $newpassword2 = $whmcs->get_req_var("confirmpw");
                                                foreach (["newpassword1", "newserverpassword1"] as $key) {
                                                    if (!$newpassword1 && $whmcs->get_req_var($key)) {
                                                        $newpwfield = $key;
                                                        $newpassword1 = $whmcs->get_req_var($key);
                                                    }
                                                }
                                                foreach (["newpassword2", "newserverpassword2"] as $key) {
                                                    if ($whmcs->get_req_var($key)) {
                                                        $newpassword2 = $whmcs->get_req_var($key);
                                                    }
                                                }
                                                $validate = new WHMCS\Validate();
                                                if ($validate->validate("match_value", "newpw", "clientareaerrorpasswordnotmatch", [$newpassword1, $newpassword2])) {
                                                    $validate->validate("pwstrength", $newpwfield, "pwstrengthfail");
                                                }
                                                if ($validate->hasErrors()) {
                                                    $modulechangepwresult = "error";
                                                    $modulechangepasswordmessage = $validate->getHTMLErrorOutput();
                                                } else {
                                                    update_query("tblhosting", ["password" => encrypt($newpassword1)], ["id" => $id]);
                                                    $updatearr = ["password" => WHMCS\Input\Sanitize::decode($newpassword1)];
                                                    $success = $service->moduleCall("ChangePassword", $updatearr);
                                                    if ($success) {
                                                        logActivity("Module Change Password Successful - Service ID: " . $id);
                                                        run_hook("AfterModuleChangePassword", ["serviceid" => $id, "oldpassword" => $service->getData("password"), "newpassword" => $updatearr["password"]]);
                                                        $modulechangepwresult = "success";
                                                        $modulechangepasswordmessage = Lang::trans("serverchangepasswordsuccessful");
                                                        $ca->assign("password", $newpassword1);
                                                    } else {
                                                        $modulechangepwresult = "error";
                                                        $modulechangepasswordmessage = Lang::trans("serverchangepasswordfailed");
                                                        update_query("tblhosting", ["password" => encrypt($service->getData("password"))], ["id" => $id]);
                                                    }
                                                }
                                                $smartyvalues["modulechangepwresult"] = $modulechangepwresult;
                                                $smartyvalues["modulechangepasswordmessage"] = $modulechangepasswordmessage;
                                            }
                                        }
                                        $customTemplateVariables = $ca->getTemplateVariables();
                                        $customTemplateVariables["moduleParams"] = $moduleInterface->buildParams();
                                        $moduleTemplateVariables = [];
                                        $tabOverviewModuleDirectOutputContent = "";
                                        $tabOverviewModuleOutputTemplate = "";
                                        $tabOverviewReplacementTemplate = "";
                                        if ($service->hasFunction("ClientArea")) {
                                            $inputParams = ["clientareatemplate" => App::getClientAreaTemplate()->getName(), "templatevars" => $customTemplateVariables, "whmcsVersion" => App::getVersion()->getCanonical()];
                                            $success = $service->moduleCall("ClientArea", $inputParams);
                                            $data = $service->getModuleReturn("data");
                                            if (is_array($data)) {
                                                if (isset($data["overrideDisplayTitle"])) {
                                                    $ca->setDisplayTitle($data["overrideDisplayTitle"]);
                                                }
                                                if (isset($data["overrideBreadcrumb"]) && is_array($data["overrideBreadcrumb"])) {
                                                    $ca->resetBreadCrumb()->addToBreadCrumb("index.php", $whmcs->get_lang("globalsystemname"))->addToBreadCrumb("clientarea.php", $whmcs->get_lang("clientareatitle"));
                                                    foreach ($data["overrideBreadcrumb"] as $breadcrumb) {
                                                        $ca->addToBreadCrumb($breadcrumb[0], $breadcrumb[1]);
                                                    }
                                                }
                                                if (isset($data["appendToBreadcrumb"]) && is_array($data["appendToBreadcrumb"])) {
                                                    foreach ($data["appendToBreadcrumb"] as $breadcrumb) {
                                                        $ca->addToBreadCrumb($breadcrumb[0], $breadcrumb[1]);
                                                    }
                                                }
                                                if (isset($data["tabOverviewModuleOutputTemplate"])) {
                                                    $tabOverviewModuleOutputTemplate = $moduleInterface->findTemplate($data["tabOverviewModuleOutputTemplate"]);
                                                } else {
                                                    if (isset($data["templatefile"])) {
                                                        $tabOverviewModuleOutputTemplate = $moduleInterface->findTemplate($data["templatefile"]);
                                                    }
                                                }
                                                if (isset($data["tabOverviewReplacementTemplate"])) {
                                                    $tabOverviewReplacementTemplate = $moduleInterface->findTemplate($data["tabOverviewReplacementTemplate"]);
                                                }
                                                if (isset($data["templateVariables"]) && is_array($data["templateVariables"])) {
                                                    $moduleTemplateVariables = $data["templateVariables"];
                                                } else {
                                                    if (isset($data["vars"]) && is_array($data["vars"])) {
                                                        $moduleTemplateVariables = $data["vars"];
                                                    }
                                                }
                                            } else {
                                                $tabOverviewModuleDirectOutputContent = $data != WHMCS\Module\Server::FUNCTIONDOESNTEXIST ? $data : "";
                                            }
                                        }
                                        if ($service->getData("status") == "Active" && checkContactPermission("manageproducts", true)) {
                                            if ($tabOverviewModuleOutputTemplate) {
                                                if (file_exists(ROOTDIR . $tabOverviewModuleOutputTemplate)) {
                                                    $moduleClientAreaOutput = $ca->getSingleTPLOutput($tabOverviewModuleOutputTemplate, $moduleInterface->prepareParams(array_merge($customTemplateVariables, $customTemplateVariables["moduleParams"], $moduleTemplateVariables)));
                                                } else {
                                                    $moduleClientAreaOutput = "Template File \"" . WHMCS\Input\Sanitize::makeSafeForOutput($tabOverviewModuleOutputTemplate) . "\" Not Found";
                                                }
                                            } else {
                                                if ($tabOverviewModuleDirectOutputContent) {
                                                    $tabOverviewModuleOutputTemplate = "";
                                                    $moduleClientAreaOutput = $tabOverviewModuleDirectOutputContent;
                                                } else {
                                                    if (file_exists(ROOTDIR . $moduleFolderPath . DIRECTORY_SEPARATOR . "clientarea.tpl")) {
                                                        $tplPath = $moduleFolderPath . DIRECTORY_SEPARATOR . "clientarea.tpl";
                                                        $moduleClientAreaOutput = $ca->getSingleTPLOutput($tplPath, $moduleInterface->prepareParams(array_merge($customTemplateVariables, $customTemplateVariables["moduleParams"], $moduleTemplateVariables)));
                                                    }
                                                }
                                            }
                                        }
                                        if ($tabOverviewReplacementTemplate) {
                                            if (file_exists(ROOTDIR . $tabOverviewReplacementTemplate)) {
                                                $tplOverviewTabOutput = $ca->getSingleTPLOutput($tabOverviewReplacementTemplate, $moduleInterface->prepareParams(array_merge($customTemplateVariables, $moduleTemplateVariables)));
                                            } else {
                                                $tplOverviewTabOutput = "Template File \"" . WHMCS\Input\Sanitize::makeSafeForOutput($tabOverviewReplacementTemplate) . "\" Not Found";
                                            }
                                        }
                                    }
                                    $ca->assign("tplOverviewTabOutput", $tplOverviewTabOutput);
                                    $ca->assign("modulecustombuttons", $clientAreaCustomButtons);
                                    $ca->assign("servercustombuttons", $clientAreaCustomButtons);
                                    $ca->assign("moduleclientarea", $moduleClientAreaOutput);
                                    $ca->assign("serverclientarea", $moduleClientAreaOutput);
                                    $invoice = WHMCS\Database\Capsule::table("tblinvoices")->join("tblinvoiceitems", function (Illuminate\Database\Query\JoinClause $join) use($service) {
                                        $join->on("tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->where("tblinvoiceitems.type", "=", "Hosting")->where("tblinvoiceitems.relid", "=", $service->getData("id"));
                                    })->where("tblinvoices.status", "Unpaid")->orderBy("tblinvoices.duedate", "asc")->first(["tblinvoices.id", "tblinvoices.duedate"]);
                                    $invoiceId = NULL;
                                    $overdue = false;
                                    $ca->assign("unpaidInvoiceMessage", "");
                                    if ($invoice) {
                                        $invoiceId = $invoice->id;
                                        $dueDate = WHMCS\Carbon::createFromFormat("Y-m-d", $invoice->duedate);
                                        $overdue = $today->gt($dueDate);
                                        $languageString = "unpaidInvoiceAlert";
                                        if ($overdue) {
                                            $languageString = "overdueInvoiceAlert";
                                        }
                                        $ca->assign("unpaidInvoiceMessage", Lang::trans($languageString));
                                    }
                                    $ca->assign("unpaidInvoice", $invoiceId);
                                    $ca->assign("unpaidInvoiceOverdue", $overdue);
                                    run_hook("ClientAreaProductDetails", ["service" => $serviceModel]);
                                    $ca->addOutputHookFunction("ClientAreaPageProductDetails");
                                } else {
                                    if ($action == "domains") {
                                        checkContactPermission("domains");
                                        $ca->setDisplayTitle(Lang::trans("clientareanavdomains"));
                                        $ca->setTemplate("clientareadomains");
                                        $warnings = "";
                                        if (isset($error)) {
                                            if ($error == "noDomainsSelected") {
                                                $warnings .= Lang::trans("actionRequiresAtLeastOneDomainSelected");
                                            }
                                            if ($error == "nonActiveDomainsSelected") {
                                                $warnings .= Lang::trans("domainCannotBeManagedUnlessActive");
                                            }
                                        }
                                        $where = "userid='" . db_escape_string($legacyClient->getID()) . "'";
                                        if ($q) {
                                            $q = preg_replace("/[^a-z0-9-.]/", "", strtolower($q));
                                            $where .= " AND domain LIKE '%" . db_escape_string($q) . "%'";
                                            $smartyvalues["q"] = $q;
                                        }
                                        $result = select_query("tbldomains", "COUNT(*)", $where);
                                        $data = mysql_fetch_array($result);
                                        $numitems = $data[0];
                                        list($orderby, $sort, $limit) = clientAreaTableInit("dom", "domain", "ASC", $numitems);
                                        $smartyvalues["orderby"] = $orderby;
                                        $smartyvalues["sort"] = strtolower($sort);
                                        if ($orderby == "price") {
                                            $orderby = "recurringamount";
                                        } else {
                                            if ($orderby == "regdate") {
                                                $orderby = "registrationdate";
                                            } else {
                                                if ($orderby == "nextduedate") {
                                                    $orderby = "nextduedate";
                                                } else {
                                                    if ($orderby == "status") {
                                                        $orderby = "status";
                                                    } else {
                                                        if ($orderby == "autorenew") {
                                                            $orderby = "donotrenew";
                                                        } else {
                                                            $orderby = "domain";
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $storedDomains = [];
                                        $result = select_query("tbldomains", "*, (DATEDIFF(expirydate, now())) as days_until_expiry, (DATEDIFF(nextduedate, now())) as days_until_next_due", $where, $orderby, $sort, $limit);
                                        $nameserverManagement = [];
                                        while ($data = mysql_fetch_array($result)) {
                                            $id = $data["id"];
                                            if (!array_key_exists($data["registrar"], $nameserverManagement)) {
                                                $reg = new WHMCS\Module\Registrar();
                                                $exists = false;
                                                if ($reg->load($data["registrar"])) {
                                                    $exists = $reg->functionExists("SaveNameservers");
                                                }
                                                $nameserverManagement[$data["registrar"]] = $exists;
                                            }
                                            $manageNS = $nameserverManagement[$data["registrar"]];
                                            $registrationdate = $data["registrationdate"];
                                            $domain = $data["domain"];
                                            $amount = $data["recurringamount"];
                                            $nextduedate = $data["nextduedate"];
                                            $expirydate = $data["expirydate"];
                                            $daysUntilExpiry = (int) $data["days_until_expiry"];
                                            if ($expirydate == "0000-00-00") {
                                                $expirydate = $nextduedate;
                                                $daysUntilExpiry = (int) $data["days_until_next_due"];
                                            }
                                            $status = $data["status"];
                                            $donotrenew = $data["donotrenew"];
                                            $rawstatus = $ca->getRawStatus($status);
                                            $autorenew = $donotrenew ? false : true;
                                            $normalisedRegistrationDate = $registrationdate;
                                            $registrationdate = fromMySQLDate($registrationdate, 0, 1, "-");
                                            $normalisedNextDueDate = $nextduedate;
                                            $nextduedate = fromMySQLDate($nextduedate, 0, 1, "-");
                                            $normalisedExpiryDate = $expirydate;
                                            $expirydate = fromMySQLDate($expirydate, 0, 1, "-");
                                            $isDomain = true;
                                            $isActive = in_array($status, [WHMCS\Utility\Status::ACTIVE, WHMCS\Utility\Status::GRACE]);
                                            $sslStatus = NULL;
                                            if ($isDomain && $isActive) {
                                                $sslStatus = WHMCS\Domain\Ssl\Status::factory($legacyClient->getID(), $domain);
                                            }
                                            $storedDomains[] = ["id" => $id, "domain" => $domain, "amount" => formatCurrency($amount), "registrationdate" => $registrationdate, "normalisedRegistrationDate" => $normalisedRegistrationDate, "nextduedate" => $nextduedate, "normalisedNextDueDate" => $normalisedNextDueDate, "expirydate" => $expirydate, "normalisedExpiryDate" => $normalisedExpiryDate, "daysUntilExpiry" => $daysUntilExpiry, "status" => $status, "statusClass" => WHMCS\View\Helper::generateCssFriendlyClassName($status), "rawstatus" => $rawstatus, "statustext" => Lang::trans("clientarea" . $rawstatus), "autorenew" => $autorenew, "expiringSoon" => $daysUntilExpiry <= 45 && $status != "Expired", "managens" => $manageNS, "canDomainBeManaged" => in_array($status, [WHMCS\Utility\Status::ACTIVE, WHMCS\Utility\Status::GRACE, WHMCS\Utility\Status::REDEMPTION]), "sslStatus" => $sslStatus, "isActive" => $isActive];
                                        }
                                        $ca->assign("domains", $storedDomains);
                                        $selectedIDs = $whmcs->get_req_var("domids");
                                        $ca->assign("selectedIDs", $selectedIDs);
                                        $smartyvalues = array_merge($smartyvalues, clientAreaTablePageNav($numitems));
                                        $smartyvalues["allowrenew"] = $whmcs->get_config("EnableDomainRenewalOrders") ? true : false;
                                        $smartyvalues["warnings"] = $warnings;
                                        $ca->addOutputHookFunction("ClientAreaPageDomains");
                                    } else {
                                        if ($action == "domaindetails") {
                                            checkContactPermission("domains");
                                            $ca->setTemplate("clientareadomaindetails");
                                            $domain_data = $domains->getDomainsDatabyID($domainID);
                                            $ca->assign("changeAutoRenewStatusSuccessful", false);
                                            if ($domains->getData("status") == "Active") {
                                                $autorenew = $whmcs->get_req_var("autorenew");
                                                if ($autorenew == "enable") {
                                                    check_token();
                                                    checkContactPermission("managedomains");
                                                    update_query("tbldomains", ["donotrenew" => ""], ["id" => $id, "userid" => $legacyClient->getID()]);
                                                    logActivity("Client Enabled Domain Auto Renew - Domain ID: " . $id . " - Domain: " . $domainName->getDomain());
                                                    $ca->assign("updatesuccess", true);
                                                    $ca->assign("changeAutoRenewStatusSuccessful", true);
                                                } else {
                                                    if ($autorenew == "disable") {
                                                        check_token();
                                                        checkContactPermission("managedomains");
                                                        disableAutoRenew($id);
                                                        $ca->assign("updatesuccess", true);
                                                        $ca->assign("changeAutoRenewStatusSuccessful", true);
                                                    }
                                                }
                                                $domain_data = $domains->getDomainsDatabyID($domainID);
                                            }
                                            $domain = $domains->getData("domain");
                                            $firstpaymentamount = $domains->getData("firstpaymentamount");
                                            $recurringamount = $domains->getData("recurringamount");
                                            $nextduedate = $domains->getData("nextduedate");
                                            $expirydate = $domains->getData("expirydate");
                                            $paymentmethod = $domains->getData("paymentmethod");
                                            $domainstatus = $domains->getData("status");
                                            $registrationperiod = $domains->getData("registrationperiod");
                                            $registrationdate = $domains->getData("registrationdate");
                                            $donotrenew = $domains->getData("donotrenew");
                                            $dnsmanagement = $domains->getData("dnsmanagement");
                                            $emailforwarding = $domains->getData("emailforwarding");
                                            $idprotection = $domains->getData("idprotection");
                                            $registrar = $domains->getModule();
                                            $gatewaysarray = getGatewaysArray();
                                            $paymentmethod = $gatewaysarray[$paymentmethod];
                                            $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domain_data["id"], $domain);
                                            $registrationdate = fromMySQLDate($registrationdate, 0, 1, "-");
                                            $nextduedate = fromMySQLDate($nextduedate, 0, 1, "-");
                                            $expirydate = fromMySQLDate($expirydate, 0, 1, "-");
                                            $rawstatus = $ca->getRawStatus($domainstatus);
                                            $allowrenew = false;
                                            if ($whmcs->get_config("EnableDomainRenewalOrders") && in_array($domainstatus, ["Active", "Grace", "Redemption", "Expired"]) && 0 < $recurringamount) {
                                                $allowrenew = true;
                                            }
                                            $autorenew = $donotrenew ? false : true;
                                            $ca->assign("domainid", $domains->getData("id"));
                                            $ca->assign("domain", $domain);
                                            $ca->assign("firstpaymentamount", formatCurrency($firstpaymentamount));
                                            $ca->assign("recurringamount", formatCurrency($recurringamount));
                                            $ca->assign("registrationdate", $registrationdate);
                                            $ca->assign("nextduedate", $nextduedate);
                                            $ca->assign("expirydate", $expirydate);
                                            $ca->assign("registrationperiod", $registrationperiod);
                                            $ca->assign("paymentmethod", $paymentmethod);
                                            $ca->assign("systemStatus", $domainstatus);
                                            $ca->assign("canDomainBeManaged", in_array($domainstatus, [WHMCS\Utility\Status::ACTIVE, WHMCS\Utility\Status::GRACE, WHMCS\Utility\Status::REDEMPTION]));
                                            $ca->assign("status", Lang::trans("clientarea" . $rawstatus));
                                            $ca->assign("rawstatus", $rawstatus);
                                            $ca->assign("donotrenew", $donotrenew);
                                            $ca->assign("autorenew", $autorenew);
                                            $ca->assign("subaction", $sub);
                                            $ca->assign("addonstatus", ["dnsmanagement" => $dnsmanagement, "emailforwarding" => $emailforwarding, "idprotection" => $idprotection]);
                                            if ($allowrenew) {
                                                $ca->assign("renew", $allowrenew);
                                            }
                                            $tlddata = get_query_vals("tbldomainpricing", "", ["extension" => "." . $domainName->getTLD()]);
                                            $ca->assign("addons", ["dnsmanagement" => $tlddata["dnsmanagement"], "emailforwarding" => $tlddata["emailforwarding"], "idprotection" => $tlddata["idprotection"]]);
                                            $addonscount = 0;
                                            if ($tlddata["dnsmanagement"]) {
                                                $addonscount++;
                                            }
                                            if ($tlddata["emailforwarding"]) {
                                                $addonscount++;
                                            }
                                            if ($tlddata["idprotection"]) {
                                                $addonscount++;
                                            }
                                            $ca->assign("addonscount", $addonscount);
                                            $result = select_query("tblpricing", "", ["type" => "domainaddons", "currency" => $currency["id"], "relid" => 0]);
                                            $data = mysql_fetch_array($result);
                                            $domaindnsmanagementprice = $data["msetupfee"];
                                            $domainemailforwardingprice = $data["qsetupfee"];
                                            $domainidprotectionprice = $data["ssetupfee"];
                                            $ca->assign("addonspricing", ["dnsmanagement" => formatCurrency($domaindnsmanagementprice), "emailforwarding" => formatCurrency($domainemailforwardingprice), "idprotection" => formatCurrency($domainidprotectionprice)]);
                                            $smartyvalues["updatesuccess"] = false;
                                            $ca->assign("registrarcustombuttonresult", "");
                                            if ($domainstatus == "Active" && $domains->getModule()) {
                                                $registrarclientarea = "";
                                                $ca->assign("registrar", $registrar);
                                                if ($sub == "savens") {
                                                    check_token();
                                                    checkContactPermission("managedomains");
                                                    $nameservers = $nschoice == "default" ? $domains->getDefaultNameservers() : ["ns1" => $ns1, "ns2" => $ns2, "ns3" => $ns3, "ns4" => $ns4, "ns5" => $ns5];
                                                    $success = $domains->moduleCall("SaveNameservers", $nameservers);
                                                    if ($success) {
                                                        $smartyvalues["updatesuccess"] = true;
                                                    } else {
                                                        $smartyvalues["error"] = Lang::trans("domainDetails.error.saveNs");
                                                    }
                                                }
                                                if ($sub == "savereglock") {
                                                    check_token();
                                                    checkContactPermission("managedomains");
                                                    $newlockstatus = $whmcs->get_req_var("reglock") ? "locked" : "unlocked";
                                                    $success = $domains->moduleCall("SaveRegistrarLock", ["lockenabled" => $newlockstatus]);
                                                    if ($success) {
                                                        $smartyvalues["updatesuccess"] = true;
                                                    } else {
                                                        $smartyvalues["error"] = Lang::trans("domainDetails.error.saveRegLock");
                                                    }
                                                }
                                                $alerts = [];
                                                if ($sub == "resendirtpemail" && $domains->hasFunction("ResendIRTPVerificationEmail")) {
                                                    check_token();
                                                    checkContactPermission("managedomains");
                                                    $success = $domains->moduleCall("ResendIRTPVerificationEmail");
                                                    if ($success) {
                                                        $alerts[] = ["title" => Lang::trans("domains.resendNotification"), "description" => Lang::trans("domains.resendNotificationSuccess"), "type" => "success"];
                                                    } else {
                                                        $error = Lang::trans("domainDetails.error.resendNotification");
                                                        $alerts[] = ["title" => Lang::trans("domains.resendNotification"), "description" => $error, "type" => "danger"];
                                                    }
                                                }
                                                $nameserversArray = [];
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $nameserversArray[$i] = ["num" => $i, "label" => $whmcs->get_lang("domainnameserver" . $i), "value" => ""];
                                                }
                                                $smartyvalues["defaultns"] = false;
                                                $smartyvalues["nameservers"] = [];
                                                $showResendIRTPVerificationEmail = false;
                                                try {
                                                    $domainInformation = $domains->getDomainInformation();
                                                    $nsValues = $domainInformation->getNameservers();
                                                    unset($nsValues["success"]);
                                                    $i = 1;
                                                    foreach ($nsValues as $nameserver) {
                                                        $ca->assign("ns" . $i, $nameserver);
                                                        $nameserversArray[$i]["value"] = $nameserver;
                                                        $i++;
                                                    }
                                                    $smartyvalues["managens"] = true;
                                                    $smartyvalues["nameservers"] = $nameserversArray;
                                                    $defaultNameservers = [];
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if (trim(WHMCS\Config\Setting::getValue("DefaultNameserver" . $i))) {
                                                            $defaultNameservers[] = strtolower(trim(WHMCS\Config\Setting::getValue("DefaultNameserver" . $i)));
                                                        }
                                                    }
                                                    $isDefaultNs = true;
                                                    foreach ($nameserversArray as $nsInfo) {
                                                        $ns = $nsInfo["value"];
                                                        if ($ns && !in_array($ns, $defaultNameservers)) {
                                                            $isDefaultNs = false;
                                                            $smartyvalues["defaultns"] = $isDefaultNs;
                                                            if ($managementOptions["locking"]) {
                                                                $lockStatus = "unlocked";
                                                                if ($domainInformation->getTransferLock()) {
                                                                    $lockStatus = "locked";
                                                                }
                                                                $ca->assign("lockstatus", $lockStatus);
                                                            }
                                                            if ($domainInformation->isIrtpEnabled() && $domainInformation->isContactChangePending()) {
                                                                $title = Lang::trans("domains.contactChangePending");
                                                                $descriptionLanguageString = "domains.contactsChanged";
                                                                if ($domainInformation->getPendingSuspension()) {
                                                                    $title = Lang::trans("domains.verificationRequired");
                                                                    $descriptionLanguageString = "domains.newRegistration";
                                                                }
                                                                $parameters = [];
                                                                if ($domainInformation->getDomainContactChangeExpiryDate()) {
                                                                    $descriptionLanguageString .= "Date";
                                                                    $parameters = [":date" => $domainInformation->getDomainContactChangeExpiryDate()->toClientDateFormat()];
                                                                }
                                                                $resendButton = Lang::trans("domains.resendNotification");
                                                                $description = Lang::trans($descriptionLanguageString, $parameters);
                                                                $description .= "<br>\n<form method=\"post\" action=\"?action=domaindetails#tabOverview\">\n    <input type=\"hidden\" name=\"id\" value=\"" . $domain_data["id"] . "\">\n    <input type=\"hidden\" name=\"sub\" value=\"resendirtpemail\" />\n    <button type=\"submit\" class=\"btn btn-sm btn-primary\">" . $resendButton . "</button>\n</form>";
                                                                $alerts[] = ["title" => $title, "description" => $description, "type" => "info"];
                                                                $showResendIRTPVerificationEmail = true;
                                                            }
                                                            if ($domainInformation->isIrtpEnabled() && $domainInformation->getIrtpTransferLock()) {
                                                                $title = Lang::trans("domains.irtpLockEnabled");
                                                                $descriptionLanguageString = Lang::trans("domains.irtpLockDescription");
                                                                if ($domainInformation->getIrtpTransferLockExpiryDate()) {
                                                                    $descriptionLanguageString = Lang::trans("domains.irtpLockDescriptionDate", [":date" => $domainInformation->getIrtpTransferLockExpiryDate()->toClientDateFormat()]);
                                                                }
                                                                $alerts[] = ["title" => $title, "description" => $descriptionLanguageString, "type" => "info"];
                                                            }
                                                        }
                                                    }
                                                } catch (Exception $e) {
                                                    $smartyvalues["nameservererror"] = Lang::trans("domainDetails.error.getNs");
                                                }
                                                if ($alerts) {
                                                    $ca->assign("alerts", $alerts);
                                                }
                                                $ca->assign("showResendVerificationEmail", $showResendIRTPVerificationEmail);
                                                $domainReleaseSuccess = false;
                                                if ($managementOptions["release"]) {
                                                    $allowrelease = false;
                                                    if (isset($params["AllowClientTAGChange"]) && !$params["AllowClientTAGChange"]) {
                                                        $managementOptions["release"] = false;
                                                        $ca->assign("managementOptions", $managementOptions);
                                                    }
                                                    if ($managementOptions["release"]) {
                                                        $smartyvalues["releasedomain"] = true;
                                                        if ($sub == "releasedomain") {
                                                            check_token();
                                                            checkContactPermission("managedomains");
                                                            $success = $domains->moduleCall("ReleaseDomain", ["transfertag" => $transtag]);
                                                            if ($success) {
                                                                WHMCS\Database\Capsule::table("tbldomains")->where("id", $domains->getData("id"))->update(["status" => "Transferred Away"]);
                                                                $ca->assign("status", $whmcs->get_lang("clientareatransferredaway"));
                                                                $domainReleaseSuccess = true;
                                                                logActivity("Client Requested Domain Release to Tag " . $transtag);
                                                            } else {
                                                                $smartyvalues["error"] = Lang::trans("domainDetails.error.releaseDomain");
                                                            }
                                                        }
                                                    } else {
                                                        $smartyvalues["releasedomain"] = false;
                                                    }
                                                }
                                                $ca->assign("releaseDomainSuccessful", $domainReleaseSuccess);
                                                $allowedclientregistrarfunctions = [];
                                                if ($domains->hasFunction("ClientAreaAllowedFunctions")) {
                                                    $success = $domains->moduleCall("ClientAreaAllowedFunctions");
                                                    $registrarallowedfunctions = $domains->getModuleReturn();
                                                    if (is_array($registrarallowedfunctions)) {
                                                        foreach ($registrarallowedfunctions as $v) {
                                                            $allowedclientregistrarfunctions[] = $v;
                                                        }
                                                    }
                                                }
                                                if ($domains->hasFunction("ClientAreaCustomButtonArray")) {
                                                    $success = $domains->moduleCall("ClientAreaCustomButtonArray");
                                                    $registrarcustombuttons = $domains->getModuleReturn();
                                                    if (is_array($registrarcustombuttons)) {
                                                        foreach ($registrarcustombuttons as $k => $v) {
                                                            $allowedclientregistrarfunctions[] = $v;
                                                        }
                                                    }
                                                    $ca->assign("registrarcustombuttons", $registrarcustombuttons);
                                                }
                                                if ($modop == "custom" && in_array($a, $allowedclientregistrarfunctions)) {
                                                    checkContactPermission("managedomains");
                                                    $success = $domains->moduleCall($a);
                                                    $data = $domains->getModuleReturn();
                                                    if (is_array($data)) {
                                                        if (isset($data["templatefile"])) {
                                                            if (!isValidforPath($registrar)) {
                                                                throw new WHMCS\Exception\Fatal("Invalid Registrar Module Name");
                                                            }
                                                            if (!isValidforPath($data["templatefile"])) {
                                                                throw new WHMCS\Exception\Fatal("Invalid Template Filename");
                                                            }
                                                            $ca->setTemplate("/modules/registrars/" . $registrar . "/" . $data["templatefile"] . ".tpl");
                                                        }
                                                        if (isset($data["breadcrumb"]) && is_array($data["breadcrumb"])) {
                                                            foreach ($data["breadcrumb"] as $k => $v) {
                                                                $ca->addToBreadCrumb($k, $v);
                                                            }
                                                        }
                                                        if (is_array($data["vars"])) {
                                                            foreach ($data["vars"] as $k => $v) {
                                                                $smartyvalues[$k] = $v;
                                                            }
                                                        }
                                                    } else {
                                                        if (!$data || $data == "success") {
                                                            $ca->assign("registrarcustombuttonresult", "success");
                                                        } else {
                                                            $ca->assign("registrarcustombuttonresult", $data);
                                                        }
                                                    }
                                                }
                                                if (checkContactPermission("managedomains", true)) {
                                                    $moduletemplatefile = "";
                                                    $result = select_query("tbldomains", "idprotection", ["id" => $domains->getData("id")]);
                                                    $data = mysql_fetch_assoc($result);
                                                    $idprotection = $data["idprotection"] ? true : false;
                                                    $success = $domains->moduleCall("ClientArea", ["protectenable" => $idprotection]);
                                                    $result = $domains->getModuleReturn();
                                                    if (is_array($result)) {
                                                        if (isset($result["templatefile"])) {
                                                            if (!isValidforPath($registrar)) {
                                                                throw new WHMCS\Exception\Fatal("Invalid Registrar Module Name");
                                                            }
                                                            if (!isValidforPath($result["templatefile"])) {
                                                                throw new WHMCS\Exception\Fatal("Invalid Template Filename");
                                                            }
                                                            $moduletemplatefile = "/modules/registrars/" . $registrar . "/" . $result["templatefile"] . ".tpl";
                                                        }
                                                    } else {
                                                        $registrarclientarea = $result;
                                                    }
                                                    if (!$moduletemplatefile && isValidforPath($registrar) && file_exists(ROOTDIR . "/modules/registrars/" . $registrar . "/clientarea.tpl")) {
                                                        $moduletemplatefile = "/modules/registrars/" . $registrar . "/clientarea.tpl";
                                                    }
                                                    if ($moduletemplatefile) {
                                                        if (is_array($result["vars"])) {
                                                            foreach ($result["vars"] as $k => $v) {
                                                                $params[$k] = $v;
                                                            }
                                                        }
                                                        $registrarclientarea = $ca->getSingleTPLOutput($moduletemplatefile, $moduleparams);
                                                    }
                                                }
                                                $smartyvalues["registrarclientarea"] = $registrarclientarea;
                                            }
                                            $sslStatus = WHMCS\Domain\Ssl\Status::factory($legacyClient->getID(), $domain);
                                            $ca->assign("sslStatus", $sslStatus);
                                            $invoice = WHMCS\Database\Capsule::table("tblinvoices")->join("tblinvoiceitems", function (Illuminate\Database\Query\JoinClause $join) use($domainData) {
                                                $join->on("tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->whereIn("tblinvoiceitems.type", ["DomainRegister", "DomainTransfer", "Domain"])->where("tblinvoiceitems.relid", "=", $domainData["id"]);
                                            })->where("tblinvoices.status", "Unpaid")->orderBy("tblinvoices.duedate", "asc")->first(["tblinvoices.id", "tblinvoices.duedate"]);
                                            $invoiceId = NULL;
                                            $overdue = false;
                                            $ca->assign("unpaidInvoiceMessage", "");
                                            if ($invoice) {
                                                $invoiceId = $invoice->id;
                                                $dueDate = WHMCS\Carbon::createFromFormat("Y-m-d", $invoice->duedate);
                                                $overdue = $today->gt($dueDate);
                                                $languageString = "unpaidInvoiceAlert";
                                                if ($overdue) {
                                                    $languageString = "overdueInvoiceAlert";
                                                }
                                                $ca->assign("unpaidInvoiceMessage", Lang::trans($languageString));
                                            }
                                            $ca->assign("unpaidInvoice", $invoiceId);
                                            $ca->assign("unpaidInvoiceOverdue", $overdue);
                                            run_hook("ClientAreaDomainDetails", ["domain" => $domainModel]);
                                            $hookResponses = run_hook("ClientAreaDomainDetailsOutput", ["domain" => $domainModel]);
                                            $ca->assign("hookOutput", $hookResponses);
                                            $ca->addOutputHookFunction("ClientAreaPageDomainDetails");
                                        } else {
                                            if ($action == "domaincontacts") {
                                                checkContactPermission("managedomains");
                                                $ca->setTemplate("clientareadomaincontactinfo");
                                                $contactsarray = $legacyClient->getContactsWithAddresses();
                                                $smartyvalues["contacts"] = $contactsarray;
                                                if (!$domainData || !$domains->isActive() || !$domains->hasFunction("GetContactDetails")) {
                                                    redir("action=domains", "clientarea.php");
                                                }
                                                $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domainData["id"], $domainData["domain"]);
                                                $ca->addToBreadCrumb("#", $whmcs->get_lang("domaincontactinfo"));
                                                $smartyvalues["successful"] = false;
                                                $smartyvalues["pending"] = false;
                                                $smartyvalues["error"] = "";
                                                $pendingData = [];
                                                if ($sub == "save") {
                                                    check_token();
                                                    try {
                                                        $sel = [];
                                                        if (App::isInRequest("sel")) {
                                                            $sel = App::getFromRequest("sel");
                                                            if (!is_array($sel)) {
                                                                $sel = [];
                                                            }
                                                        }
                                                        $result = $domains->saveContactDetails($legacyClient, App::getFromRequest("contactdetails") ?: [], App::getFromRequest("wc") ?: [], $sel);
                                                        $contactdetails = $result["contactDetails"];
                                                        if ($result["status"] == "pending") {
                                                            $smartyvalues["pending"] = true;
                                                            if (!empty($result["pendingData"])) {
                                                                $pendingData = $result["pendingData"];
                                                            }
                                                        } else {
                                                            $smartyvalues["successful"] = true;
                                                        }
                                                    } catch (Exception $e) {
                                                        $smartyvalues["error"] = Lang::trans("domainDetails.error.saveContact");
                                                        $contactdetails = WHMCS\Input\Sanitize::decode($contactdetails);
                                                        if (is_array($contactdetails)) {
                                                            array_walk_recursive($contactdetails, function (&$value) {
                                                                $value = htmlentities(strip_tags($value), ENT_COMPAT);
                                                            });
                                                            unset($value);
                                                        } else {
                                                            unset($contactdetails);
                                                        }
                                                    }
                                                }
                                                $success = $domains->moduleCall("GetContactDetails");
                                                if ($success) {
                                                    if ($sub == "save" && $smartyvalues["successful"] === false && isset($contactdetails)) {
                                                        $contactDetails = $contactdetails;
                                                    } else {
                                                        $contactDetails = $domains->getModuleReturn();
                                                    }
                                                    $smartyvalues["contactdetails"] = $domains->normalisePhoneNumberInContactDetails($contactDetails);
                                                    $smartyvalues["contactdetailstranslations"] = $domains->getContactFieldNameTranslations($contactDetails);
                                                    try {
                                                        $domainInformation = $domains->getDomainInformation();
                                                    } catch (Exception $e) {
                                                        $domainInformation = NULL;
                                                    }
                                                    $smartyvalues["domainInformation"] = $domainInformation;
                                                    $smartyvalues["irtpFields"] = [];
                                                    if ($domainInformation instanceof WHMCS\Domain\Registrar\Domain && $domainInformation->isIrtpEnabled()) {
                                                        $smartyvalues["irtpFields"] = $domainInformation->getIrtpVerificationTriggerFields();
                                                    }
                                                    if ($domainInformation instanceof WHMCS\Domain\Registrar\Domain && $smartyvalues["pending"]) {
                                                        $message = "domains.changePending";
                                                        $replacement = [":email" => $domainInformation->getRegistrantEmailAddress()];
                                                        if ($domainInformation->getDomainContactChangeExpiryDate()) {
                                                            $message = "domains.changePendingDate";
                                                            $replacement[":days"] = $domainInformation->getDomainContactChangeExpiryDate()->diffInDays();
                                                        }
                                                        if (!empty($pendingData)) {
                                                            $message = $pendingData["message"];
                                                            $replacement = $pendingData["replacement"];
                                                        }
                                                        $smartyvalues["pendingMessage"] = Lang::trans($message, $replacement);
                                                    }
                                                } else {
                                                    $smartyvalues["error"] = Lang::trans("domainDetails.error.getContact");
                                                }
                                                $smartyvalues["domainid"] = $domains->getData("id");
                                                $smartyvalues["domain"] = $domains->getData("domain");
                                                $smartyvalues["contacts"] = $legacyClient->getContactsWithAddresses();
                                                $ca->addOutputHookFunction("ClientAreaPageDomainContacts");
                                            } else {
                                                if ($action == "domainemailforwarding") {
                                                    checkContactPermission("managedomains");
                                                    $ca->setTemplate("clientareadomainemailforwarding");
                                                    if (!$domainData["emailforwarding"] || !$domains->isActive() || !$domains->hasFunction("GetEmailForwarding")) {
                                                        redir("action=domains", "clientarea.php");
                                                    }
                                                    $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domainData["id"], $domainData["domain"]);
                                                    $ca->addToBreadCrumb("#", $whmcs->get_lang("domainemailforwarding"));
                                                    if ($sub == "save") {
                                                        check_token();
                                                        $key = 0;
                                                        $vars = [];
                                                        if ($whmcs->get_req_var("emailforwarderprefix")) {
                                                            $vars["prefix"] = $whmcs->get_req_var("emailforwarderprefix");
                                                            $vars["forwardto"] = $whmcs->get_req_var("emailforwarderforwardto");
                                                        }
                                                        if ($whmcs->get_req_var("emailforwarderprefixnew")) {
                                                            $vars["prefix"][] = $whmcs->get_req_var("emailforwarderprefixnew");
                                                            $vars["forwardto"][] = $whmcs->get_req_var("emailforwarderforwardtonew");
                                                        }
                                                        $success = $domains->moduleCall("SaveEmailForwarding", $vars);
                                                        if (!$success) {
                                                            $smartyvalues["error"] = Lang::trans("domainDetails.error.saveEmailFwd");
                                                        }
                                                    }
                                                    $smartyvalues["domainid"] = $domainData["id"];
                                                    $smartyvalues["domain"] = $domainData["domain"];
                                                    $success = $domains->moduleCall("GetEmailForwarding");
                                                    if (!$success && empty($smartyvalues["error"])) {
                                                        $smartyvalues["error"] = Lang::trans("domainDetails.error.getEmailFwd");
                                                    }
                                                    $ca->assign("external", false);
                                                    $ca->assign("emailforwarders", []);
                                                    if ($success) {
                                                        if ($domains->getModuleReturn("external")) {
                                                            $ca->assign("external", true);
                                                            $ca->assign("code", $domains->getModuleReturn("code"));
                                                        } else {
                                                            $ca->assign("emailforwarders", $domains->getModuleReturn());
                                                        }
                                                    }
                                                    $ca->addOutputHookFunction("ClientAreaPageDomainEmailForwarding");
                                                } else {
                                                    if ($action == "domaindns") {
                                                        checkContactPermission("managedomains");
                                                        $ca->setTemplate("clientareadomaindns");
                                                        if (!$domainData["dnsmanagement"] || !$domains->isActive() || !$domains->hasFunction("GetDNS")) {
                                                            redir("action=domains", "clientarea.php");
                                                        }
                                                        $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domainData["id"], $domainData["domain"]);
                                                        $ca->addToBreadCrumb("#", $whmcs->get_lang("domaindnsmanagement"));
                                                        if ($sub == "save") {
                                                            check_token();
                                                            $vars = [];
                                                            foreach ($_POST["dnsrecordhost"] as $num => $dnshost) {
                                                                $vars[] = ["hostname" => $dnshost, "type" => $_POST["dnsrecordtype"][$num], "address" => WHMCS\Input\Sanitize::decode($_POST["dnsrecordaddress"][$num]), "priority" => $_POST["dnsrecordpriority"][$num], "recid" => $_POST["dnsrecid"][$num]];
                                                            }
                                                            $success = $domains->moduleCall("SaveDNS", ["dnsrecords" => $vars]);
                                                            if (!$success) {
                                                                $smartyvalues["error"] = Lang::trans("domainDetails.error.saveDns");
                                                            }
                                                        }
                                                        $success = $domains->moduleCall("GetDNS");
                                                        if (!$success && empty($smartyvalues["error"])) {
                                                            $smartyvalues["error"] = Lang::trans("domainDetails.error.getDns");
                                                        }
                                                        $smartyvalues["domainid"] = $domainData["id"];
                                                        $smartyvalues["domain"] = $domainData["domain"];
                                                        if ($domains->getModuleReturn("external")) {
                                                            $ca->assign("external", true);
                                                            $ca->assign("code", $domains->getModuleReturn("code"));
                                                        } else {
                                                            $records = [];
                                                            $returnedRecords = $domains->getModuleReturn();
                                                            if (!array_key_exists("error", $returnedRecords)) {
                                                                foreach ($returnedRecords as $key => $record) {
                                                                    $record["hostname"] = WHMCS\Input\Sanitize::encode($record["hostname"]);
                                                                    $record["address"] = WHMCS\Input\Sanitize::encode($record["address"]);
                                                                    $records[$key] = $record;
                                                                }
                                                                unset($record);
                                                            }
                                                            unset($returnedRecords);
                                                            $ca->assign("dnsrecords", $records);
                                                        }
                                                        $ca->addOutputHookFunction("ClientAreaPageDomainDNSManagement");
                                                    } else {
                                                        if ($action == "domaingetepp") {
                                                            checkContactPermission("managedomains");
                                                            $ca->setTemplate("clientareadomaingetepp");
                                                            if (!$domainData || !$domains->isActive() || !$domains->hasFunction("GetEPPCode")) {
                                                                redir("action=domains", "clientarea.php");
                                                            }
                                                            $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domainData["id"], $domainData["domain"]);
                                                            $ca->addToBreadCrumb("#", $whmcs->get_lang("domaingeteppcode"));
                                                            $smartyvalues["domainid"] = $domainData["id"];
                                                            $smartyvalues["domain"] = $domainData["domain"];
                                                            $success = $domains->moduleCall("GetEPPCode");
                                                            if (!$success) {
                                                                $smartyvalues["error"] = $domains->getLastError();
                                                            } else {
                                                                $smartyvalues["eppcode"] = htmlspecialchars($domains->getModuleReturn("eppcode"));
                                                            }
                                                            $ca->addOutputHookFunction("ClientAreaPageDomainEPPCode");
                                                        } else {
                                                            if ($action == "domainregisterns") {
                                                                checkContactPermission("managedomains");
                                                                $ca->setTemplate("clientareadomainregisterns");
                                                                if (!$domainData || !$domains->isActive() || !$domains->hasFunction("RegisterNameserver")) {
                                                                    redir("action=domains", "clientarea.php");
                                                                }
                                                                $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domainData["id"], $domainData["domain"]);
                                                                $ca->addToBreadCrumb("#", $whmcs->get_lang("domainregisterns"));
                                                                $smartyvalues["domainid"] = $domainData["id"];
                                                                $smartyvalues["domain"] = $domainData["domain"];
                                                                $result = "";
                                                                $vars = [];
                                                                $ns = $whmcs->get_req_var("ns");
                                                                if ($sub == "register") {
                                                                    check_token();
                                                                    $ipaddress = $whmcs->get_req_var("ipaddress");
                                                                    $nameserver = $ns . "." . $domainData["domain"];
                                                                    $vars["nameserver"] = $nameserver;
                                                                    $vars["ipaddress"] = $ipaddress;
                                                                    $success = $domains->moduleCall("RegisterNameserver", $vars);
                                                                    $result = $success ? Lang::trans("domainregisternsregsuccess") : Lang::trans("domainDetails.error.registerNs");
                                                                } else {
                                                                    if ($sub == "modify") {
                                                                        check_token();
                                                                        $nameserver = $ns . "." . $domainData["domain"];
                                                                        $currentipaddress = $whmcs->get_req_var("currentipaddress");
                                                                        $newipaddress = $whmcs->get_req_var("newipaddress");
                                                                        $vars["nameserver"] = $nameserver;
                                                                        $vars["currentipaddress"] = $currentipaddress;
                                                                        $vars["newipaddress"] = $newipaddress;
                                                                        $success = $domains->moduleCall("ModifyNameserver", $vars);
                                                                        $result = $success ? Lang::trans("domainregisternsmodsuccess") : Lang::trans("domainDetails.error.modifyNs");
                                                                    } else {
                                                                        if ($sub == "delete") {
                                                                            check_token();
                                                                            $nameserver = $ns . "." . $domainData["domain"];
                                                                            $vars["nameserver"] = $nameserver;
                                                                            $success = $domains->moduleCall("DeleteNameserver", $vars);
                                                                            $result = $success ? Lang::trans("domainregisternsdelsuccess") : Lang::trans("domainDetails.error.deleteNs");
                                                                        }
                                                                    }
                                                                }
                                                                $smartyvalues["result"] = $result;
                                                                $ca->addOutputHookFunction("ClientAreaPageDomainRegisterNameservers");
                                                            } else {
                                                                if ($action == "domainrenew") {
                                                                    checkContactPermission("orders");
                                                                    redir("gid=renewals", "cart.php");
                                                                } else {
                                                                    if ($action == "invoices") {
                                                                        checkContactPermission("invoices");
                                                                        $ca->setDisplayTitle(Lang::trans("invoices"));
                                                                        $ca->setTagLine(Lang::trans("invoicesintro"));
                                                                        $ca->setTemplate("clientareainvoices");
                                                                        $numitems = get_query_val("tblinvoices", "COUNT(*)", ["userid" => $legacyClient->getID()]);
                                                                        list($orderby, $sort, $limit) = clientAreaTableInit("inv", "default", "ASC", $numitems);
                                                                        $smartyvalues["orderby"] = $orderby;
                                                                        $smartyvalues["sort"] = strtolower($sort);
                                                                        switch ($orderby) {
                                                                            case "date":
                                                                            case "duedate":
                                                                            case "total":
                                                                            case "status":
                                                                            case "invoicenum":
                                                                                $orderby = "invoicenum` " . $sort . ", `id";
                                                                                break;
                                                                            default:
                                                                                $orderby = "status` DESC, `duedate";
                                                                                $invoice = new WHMCS\Invoice();
                                                                                $invoices = $invoice->getInvoices("", $legacyClient->getID(), $orderby, $sort, $limit);
                                                                                $ca->assign("invoices", $invoices);
                                                                                $smartyvalues = array_merge($smartyvalues, clientAreaTablePageNav($numitems));
                                                                                $ca->addOutputHookFunction("ClientAreaPageInvoices");
                                                                        }
                                                                    } else {
                                                                        if ($action == "emails") {
                                                                            checkContactPermission("emails");
                                                                            $ca->setDisplayTitle(Lang::trans("clientareaemails"));
                                                                            $ca->setTagLine(Lang::trans("clientareaemaildesc"));
                                                                            $ca->setTemplate("clientareaemails");
                                                                            $data = WHMCS\Mail\Log::ofClient($legacyClient->getID());
                                                                            $numitems = $data->count();
                                                                            list($orderby, $sort, $limit) = clientAreaTableInit("emails", "date", "DESC", $numitems);
                                                                            $smartyvalues["orderby"] = $orderby;
                                                                            $smartyvalues["sort"] = strtolower($sort);
                                                                            if ($orderby == "subject") {
                                                                                $orderby = "subject";
                                                                            } else {
                                                                                $orderby = "date";
                                                                            }
                                                                            $emails = [];
                                                                            $data->orderBy($orderby, $sort);
                                                                            if ($limit) {
                                                                                $limit = explode(",", $limit);
                                                                                $data->skip($limit[0])->take($limit[1]);
                                                                            }
                                                                            foreach ($data->get() as $email) {
                                                                                $date = $email->getRawAttribute("date");
                                                                                $subject = $email->subject;
                                                                                $emails[] = ["id" => $email->id, "date" => WHMCS\Input\Sanitize::makeSafeForOutput(fromMySQLDate($date, true, true)), "normalisedDate" => $date, "subject" => WHMCS\Input\Sanitize::makeSafeForOutput($email->subject), "attachmentCount" => count($email->attachments ?? [])];
                                                                            }
                                                                            $ca->assign("emails", $emails);
                                                                            $smartyvalues = array_merge($smartyvalues, clientAreaTablePageNav($numitems));
                                                                            $ca->addOutputHookFunction("ClientAreaPageEmails");
                                                                        } else {
                                                                            if ($action == "cancel") {
                                                                                checkContactPermission("orders");
                                                                                $service = new WHMCS\Service($id, $legacyClient->getID());
                                                                                if ($service->isNotValid()) {
                                                                                    redir("action=products", "clientarea.php");
                                                                                }
                                                                                $serviceModel = WHMCS\Service\Service::find($service->getID());
                                                                                $allowedstatuscancel = ["Active", "Suspended"];
                                                                                if (!in_array($service->getData("status"), $allowedstatuscancel)) {
                                                                                    redir("action=productdetails&id=" . $id);
                                                                                }
                                                                                $ca->setDisplayTitle(Lang::trans("clientareacancelrequest"));
                                                                                $ca->setTemplate("clientareacancelrequest");
                                                                                $ca->addToBreadCrumb("clientarea.php?action=productdetails&id=" . $id, $whmcs->get_lang("clientareaproductdetails"));
                                                                                $ca->addToBreadCrumb("cancel&id=" . $id, $whmcs->get_lang("clientareacancelrequest"));
                                                                                $clientsdetails = getClientsDetails($legacyClient->getID());
                                                                                $smartyvalues["id"] = $service->getData("id");
                                                                                $smartyvalues["groupname"] = $service->getData("groupname");
                                                                                $smartyvalues["productname"] = $service->getData("productname");
                                                                                $smartyvalues["domain"] = $service->getData("domain");
                                                                                $cancelrequests = get_query_val("tblcancelrequests", "COUNT(*)", ["relid" => $id]);
                                                                                if ($cancelrequests) {
                                                                                    $smartyvalues["invalid"] = true;
                                                                                } else {
                                                                                    $smartyvalues["invalid"] = false;
                                                                                    $smartyvalues["error"] = false;
                                                                                    $smartyvalues["requested"] = false;
                                                                                    if ($sub == "submit") {
                                                                                        check_token();
                                                                                        if (!trim($cancellationreason)) {
                                                                                            $smartyvalues["error"] = true;
                                                                                        }
                                                                                        if (!$smartyvalues["error"]) {
                                                                                            if (!in_array($type, ["Immediate", "End of Billing Period"])) {
                                                                                                $type = "End of Billing Period";
                                                                                            }
                                                                                            createCancellationRequest($legacyClient->getID(), $id, $cancellationreason, $type);
                                                                                            if (isset($canceldomain) && $canceldomain) {
                                                                                                $domainid = get_query_val("tbldomains", "id", ["userid" => $legacyClient->getID(), "domain" => $service->getData("domain")]);
                                                                                                if ($domainid) {
                                                                                                    disableAutoRenew($domainid);
                                                                                                }
                                                                                            }
                                                                                            sendMessage("Cancellation Request Confirmation", $id);
                                                                                            sendAdminMessage("New Cancellation Request", ["client_id" => $legacyClient->getID(), "clientname" => $clientsdetails["firstname"] . " " . $clientsdetails["lastname"], "service_id" => $id, "product_name" => $service->getData("productname"), "service_cancellation_type" => $type, "service_cancellation_reason" => $cancellationreason], "account");
                                                                                            $smartyvalues["requested"] = true;
                                                                                        }
                                                                                    }
                                                                                    $smartyvalues["domainid"] = NULL;
                                                                                    if ($service->getData("domain")) {
                                                                                        $data = get_query_vals("tbldomains", "id,recurringamount,registrationperiod,nextduedate", ["userid" => $legacyClient->getID(), "domain" => $service->getData("domain"), "status" => "Active", "donotrenew" => ""]);
                                                                                        if ($data) {
                                                                                            $smartyvalues["domainid"] = $data["id"];
                                                                                            $smartyvalues["domainprice"] = formatCurrency($data["recurringamount"]);
                                                                                            $smartyvalues["domainregperiod"] = $data["registrationperiod"];
                                                                                            $smartyvalues["domainnextduedate"] = fromMySQLDate($data["nextduedate"], 0, 1);
                                                                                        }
                                                                                    }
                                                                                }
                                                                                $ca->addOutputHookFunction("ClientAreaPageCancellation");
                                                                            } else {
                                                                                if ($action == "addfunds") {
                                                                                    checkContactPermission("invoices");
                                                                                    $ca->setDisplayTitle(Lang::trans("addfunds"));
                                                                                    $ca->setTagLine(Lang::trans("addfundsintro"));
                                                                                    $clientsdetails = getClientsDetails();
                                                                                    $addfundsmaxbal = convertCurrency(WHMCS\Config\Setting::getValue("AddFundsMaximumBalance"), 1, $clientsdetails["currency"]);
                                                                                    $addfundsmax = convertCurrency(WHMCS\Config\Setting::getValue("AddFundsMaximum"), 1, $clientsdetails["currency"]);
                                                                                    $addfundsmin = convertCurrency(WHMCS\Config\Setting::getValue("AddFundsMinimum"), 1, $clientsdetails["currency"]);
                                                                                    $result = select_query("tblorders", "COUNT(*)", ["userid" => $legacyClient->getID(), "status" => "Active"]);
                                                                                    $data = mysql_fetch_array($result);
                                                                                    $numactiveorders = $data[0];
                                                                                    $smartyvalues["addfundsdisabled"] = false;
                                                                                    $smartyvalues["notallowed"] = false;
                                                                                    if (!WHMCS\Config\Setting::getValue("AddFundsRequireOrder")) {
                                                                                        $numactiveorders = 1;
                                                                                    }
                                                                                    if (!WHMCS\Config\Setting::getValue("AddFundsEnabled")) {
                                                                                        $smartyvalues["addfundsdisabled"] = true;
                                                                                    } else {
                                                                                        if (!$numactiveorders) {
                                                                                            $smartyvalues["notallowed"] = true;
                                                                                        } else {
                                                                                            $amount = $whmcs->get_req_var("amount");
                                                                                            if ($amount) {
                                                                                                check_token();
                                                                                                $totalcredit = $clientsdetails["credit"] + $amount;
                                                                                                if ($addfundsmaxbal < $totalcredit) {
                                                                                                    $errormessage = Lang::trans("addfundsmaximumbalanceerror") . " " . formatCurrency($addfundsmaxbal);
                                                                                                }
                                                                                                if ($addfundsmax < $amount) {
                                                                                                    $errormessage = Lang::trans("addfundsmaximumerror") . " " . formatCurrency($addfundsmax);
                                                                                                }
                                                                                                if ($amount < $addfundsmin) {
                                                                                                    $errormessage = Lang::trans("addfundsminimumerror") . " " . formatCurrency($addfundsmin);
                                                                                                }
                                                                                                if ($errormessage) {
                                                                                                    $ca->assign("errormessage", $errormessage);
                                                                                                } else {
                                                                                                    $paymentmethods = getGatewaysArray();
                                                                                                    if (!array_key_exists($paymentmethod, $paymentmethods)) {
                                                                                                        $paymentmethod = getClientsPaymentMethod($legacyClient->getID());
                                                                                                    }
                                                                                                    $paymentmethod = WHMCS\Gateways::makeSafeName($paymentmethod);
                                                                                                    if (!$paymentmethod) {
                                                                                                        exit("Unexpected payment method value. Exiting.");
                                                                                                    }
                                                                                                    require ROOTDIR . "/includes/processinvoices.php";
                                                                                                    $legacyClientId = $legacyClient->getID();
                                                                                                    $invoiceid = createInvoices($legacyClientId);
                                                                                                    $previousClientLanguage = getUsersLang($legacyClientId);
                                                                                                    $descriptionLanguageString = Lang::trans("addfunds");
                                                                                                    if ($previousClientLanguage) {
                                                                                                        swapLang($previousClientLanguage);
                                                                                                    }
                                                                                                    WHMCS\Billing\Invoice\Item::create(["type" => "AddFunds", "relid" => "", "description" => $descriptionLanguageString, "amount" => $amount, "userid" => $legacyClientId, "taxed" => 0, "duedate" => WHMCS\Carbon::now(), "paymentmethod" => $paymentmethod]);
                                                                                                    $invoiceid = createInvoices($legacyClientId, "", true);
                                                                                                    if (WHMCS\Module\GatewaySetting::getTypeFor($paymentmethod) === WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD) {
                                                                                                        $gatewayInterface = new WHMCS\Module\Gateway();
                                                                                                        $gatewayInterface->load($paymentmethod);
                                                                                                        if (!$gatewayInterface->functionExists("link")) {
                                                                                                            App::redirectToRoutePath("invoice-pay", [$invoiceid]);
                                                                                                        }
                                                                                                    }
                                                                                                    $invoice = new WHMCS\Invoice($invoiceid);
                                                                                                    $paymentbutton = $invoice->getPaymentLink();
                                                                                                    $ca->setTemplate("forwardpage");
                                                                                                    $ca->assign("message", Lang::trans("forwardingtogateway"));
                                                                                                    $ca->assign("code", $paymentbutton);
                                                                                                    $ca->assign("invoiceid", $invoiceid);
                                                                                                    $ca->output();
                                                                                                    exit;
                                                                                                }
                                                                                            } else {
                                                                                                $amount = $addfundsmin;
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                    $ca->setTemplate("clientareaaddfunds");
                                                                                    $ca->assign("minimumamount", formatCurrency($addfundsmin));
                                                                                    $ca->assign("maximumamount", formatCurrency($addfundsmax));
                                                                                    $ca->assign("maximumbalance", formatCurrency($addfundsmaxbal));
                                                                                    $ca->assign("amount", format_as_currency($amount));
                                                                                    $gatewayslist = showPaymentGatewaysList([], $legacyClient->getID());
                                                                                    $ca->assign("gateways", $gatewayslist);
                                                                                    $ca->addOutputHookFunction("ClientAreaPageAddFunds");
                                                                                } else {
                                                                                    if ($action == "masspay") {
                                                                                        checkContactPermission("invoices");
                                                                                        $ca->setDisplayTitle(Lang::trans("masspaytitle"));
                                                                                        $ca->setTagLine(Lang::trans("masspayintro"));
                                                                                        $ca->setTemplate("masspay");
                                                                                        if (!WHMCS\Config\Setting::getValue("EnableMassPay")) {
                                                                                            redir("action=invoices");
                                                                                        }
                                                                                        if (isset($all) && $all) {
                                                                                            $invoiceids = [];
                                                                                            $result = full_query("SELECT id FROM tblinvoices WHERE userid = " . $legacyClient->getID() . " AND status='Unpaid' AND (select count(id) from tblinvoiceitems where invoiceid=tblinvoices.id and type='Invoice')<=0 ORDER BY id DESC");
                                                                                            while ($data = mysql_fetch_array($result)) {
                                                                                                $invoiceids[] = $data["id"];
                                                                                            }
                                                                                        } else {
                                                                                            $tmp_invoiceids = db_escape_numarray($invoiceids);
                                                                                            $invoiceids = [];
                                                                                            $result = select_query("tblinvoices", "id", ["userid" => $legacyClient->getID(), "status" => "Unpaid", "id" => ["sqltype" => "IN", "values" => $tmp_invoiceids]], "id", "DESC");
                                                                                            while ($data = mysql_fetch_array($result)) {
                                                                                                $invoiceids[] = $data["id"];
                                                                                            }
                                                                                        }
                                                                                        if (count($invoiceids) == 0) {
                                                                                            redir();
                                                                                        } else {
                                                                                            if (count($invoiceids) == 1) {
                                                                                                redir(["id" => (int) $invoiceids[0]], "viewinvoice.php");
                                                                                            }
                                                                                        }
                                                                                        $xmasspays = [];
                                                                                        $result = select_query("tblinvoiceitems", "invoiceid,relid", ["tblinvoiceitems.userid" => $legacyClient->getID(), "tblinvoiceitems.type" => "Invoice", "tblinvoices.status" => "Unpaid"], "", "", "", "tblinvoices ON tblinvoices.id=tblinvoiceitems.invoiceid");
                                                                                        while ($data = mysql_fetch_array($result)) {
                                                                                            $xmasspays[$data[0]][$data[1]] = 1;
                                                                                        }
                                                                                        if (count($xmasspays)) {
                                                                                            $numsel = count($invoiceids);
                                                                                            foreach ($xmasspays as $iid => $vals) {
                                                                                                if (count($vals) == $numsel) {
                                                                                                    foreach ($invoiceids as $z) {
                                                                                                        unset($vals[$z]);
                                                                                                    }
                                                                                                    if (!count($vals)) {
                                                                                                        redir("id=" . (int) $iid, "viewinvoice.php");
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                        $geninvoice = $whmcs->get_req_var("geninvoice");
                                                                                        if ($geninvoice) {
                                                                                            check_token();
                                                                                        }
                                                                                        $paymentmethods = getGatewaysArray();
                                                                                        if (!count($paymentmethods)) {
                                                                                            redir("", "clientarea.php");
                                                                                        }
                                                                                        if (!array_key_exists($paymentmethod, $paymentmethods)) {
                                                                                            $paymentmethod = getClientsPaymentMethod($legacyClient->getID());
                                                                                        }
                                                                                        $paymentmethod = WHMCS\Gateways::makeSafeName($paymentmethod);
                                                                                        if (!$paymentmethod) {
                                                                                            exit("Unexpected payment method value. Exiting.");
                                                                                        }
                                                                                        $subtotal = $credit = $tax = $tax2 = $total = $partialpayments = 0;
                                                                                        $invoiceitems = [];
                                                                                        foreach ($invoiceids as $invoiceid) {
                                                                                            $invoiceid = (int) $invoiceid;
                                                                                            $result = select_query("tblinvoices", "", ["id" => $invoiceid, "userid" => $legacyClient->getID()]);
                                                                                            $data = mysql_fetch_array($result);
                                                                                            $invoiceid = (int) $data["id"];
                                                                                            if ($invoiceid) {
                                                                                                $invoiceNumber = $data["invoicenum"];
                                                                                                $subtotal += $data["subtotal"];
                                                                                                $credit += $data["credit"];
                                                                                                $tax += $data["tax"];
                                                                                                $tax2 += $data["tax2"];
                                                                                                $thistotal = $data["total"];
                                                                                                $total += $thistotal;
                                                                                                $result = select_query("tblaccounts", "SUM(amountin)", ["invoiceid" => $invoiceid]);
                                                                                                $data = mysql_fetch_array($result);
                                                                                                $thispayments = $data[0];
                                                                                                $partialpayments += $thispayments;
                                                                                                $thistotal = $thistotal - $thispayments;
                                                                                                if ($geninvoice) {
                                                                                                    $description = Lang::trans("invoicenumber") . $invoiceid;
                                                                                                    if ($invoiceNumber) {
                                                                                                        $description = Lang::trans("invoicenumber") . $invoiceNumber;
                                                                                                    }
                                                                                                    insert_query("tblinvoiceitems", ["userid" => $legacyClient->getID(), "type" => "Invoice", "relid" => $invoiceid, "description" => $description, "amount" => $thistotal, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
                                                                                                }
                                                                                                $result = select_query("tblinvoiceitems", "", ["invoiceid" => $invoiceid]);
                                                                                                while ($data = mysql_fetch_array($result)) {
                                                                                                    $invoiceitems[$invoiceid][] = ["invoicenum" => $invoiceNumber, "id" => $data["id"], "description" => nl2br($data["description"]), "amount" => formatCurrency($data["amount"])];
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                        if ($geninvoice) {
                                                                                            foreach ($xmasspays as $iid => $vals) {
                                                                                                update_query("tblinvoices", ["status" => "Cancelled", "updated_at" => WHMCS\Carbon::now()->toDateTimeString()], ["id" => $iid, "userid" => $legacyClient->getID()]);
                                                                                            }
                                                                                            require ROOTDIR . "/includes/processinvoices.php";
                                                                                            $invoiceid = createInvoices($legacyClient->getID(), true, true, ["invoices" => $invoiceids]);
                                                                                            $invoiceid = (int) $invoiceid;
                                                                                            $paymentmethod = WHMCS\Gateways::makeSafeName($paymentmethod);
                                                                                            if (WHMCS\Module\GatewaySetting::getTypeFor($paymentmethod) === WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD) {
                                                                                                $gatewayInterface = new WHMCS\Module\Gateway();
                                                                                                $gatewayInterface->load($paymentmethod);
                                                                                                if (!$gatewayInterface->functionExists("link")) {
                                                                                                    App::redirectToRoutePath("invoice-pay", [$invoiceid]);
                                                                                                }
                                                                                            }
                                                                                            $invoice = new WHMCS\Invoice($invoiceid);
                                                                                            $paymentbutton = $invoice->getPaymentLink();
                                                                                            $ca->setTemplate("forwardpage");
                                                                                            $ca->assign("message", Lang::trans("forwardingtogateway"));
                                                                                            $ca->assign("code", $paymentbutton);
                                                                                            $ca->assign("invoiceid", (int) $invoice->getID());
                                                                                            $ca->output();
                                                                                            exit;
                                                                                        } else {
                                                                                            $smartyvalues["subtotal"] = formatCurrency($subtotal);
                                                                                            $smartyvalues["credit"] = $credit ? formatCurrency($credit) : "";
                                                                                            $smartyvalues["tax"] = $tax ? formatCurrency($tax) : "";
                                                                                            $smartyvalues["tax2"] = $tax2 ? formatCurrency($tax2) : "";
                                                                                            $smartyvalues["partialpayments"] = $partialpayments ? formatCurrency($partialpayments) : "";
                                                                                            $smartyvalues["total"] = formatCurrency($total - $partialpayments);
                                                                                            $smartyvalues["invoiceitems"] = $invoiceitems;
                                                                                            $gatewayslist = showPaymentGatewaysList([], $legacyClient->getID());
                                                                                            $smartyvalues["gateways"] = $gatewayslist;
                                                                                            $smartyvalues["defaultgateway"] = key($gatewayslist);
                                                                                            $smartyvalues["taxname1"] = "";
                                                                                            $smartyvalues["taxrate1"] = "";
                                                                                            $smartyvalues["taxname2"] = "";
                                                                                            $smartyvalues["taxrate2"] = "";
                                                                                            if (WHMCS\Config\Setting::getValue("TaxEnabled")) {
                                                                                                $taxdata = getTaxRate(1, $legacyClient->getClientModel()->state, $legacyClient->getClientModel()->country);
                                                                                                $taxdata2 = getTaxRate(2, $legacyClient->getClientModel()->state, $legacyClient->getClientModel()->country);
                                                                                                $smartyvalues["taxname1"] = $taxdata["name"];
                                                                                                $smartyvalues["taxrate1"] = $taxdata["rate"];
                                                                                                $smartyvalues["taxname2"] = $taxdata2["name"];
                                                                                                $smartyvalues["taxrate2"] = $taxdata2["rate"];
                                                                                            }
                                                                                            $ca->addOutputHookFunction("ClientAreaPageMassPay");
                                                                                        }
                                                                                    } else {
                                                                                        if ($action == "quotes") {
                                                                                            checkContactPermission("quotes");
                                                                                            $ca->setDisplayTitle(Lang::trans("quotestitle"));
                                                                                            $ca->setTagLine(Lang::trans("quotesdesc"));
                                                                                            $ca->setTemplate("clientareaquotes");
                                                                                            require ROOTDIR . "/includes/quotefunctions.php";
                                                                                            $result = select_query("tblquotes", "COUNT(*)", ["userid" => $legacyClient->getID()]);
                                                                                            $data = mysql_fetch_array($result);
                                                                                            $numitems = $data[0];
                                                                                            list($orderby, $sort, $limit) = clientAreaTableInit("quote", "id", "DESC", $numitems);
                                                                                            if (!in_array($orderby, ["id", "date", "duedate", "total", "stage"])) {
                                                                                                $orderby = "validuntil";
                                                                                            }
                                                                                            $smartyvalues["orderby"] = $orderby;
                                                                                            $smartyvalues["sort"] = strtolower($sort);
                                                                                            $quoteStatus = ["Delivered" => "0", "Accepted" => "0"];
                                                                                            $quotes = [];
                                                                                            $result = select_query("tblquotes", "", ["userid" => $legacyClient->getID(), "stage" => ["sqltype" => "NEQ", "value" => "Draft"]], $orderby, $sort, $limit);
                                                                                            while ($data = mysql_fetch_assoc($result)) {
                                                                                                $data["normalisedDateCreated"] = $data["datecreated"];
                                                                                                $data["datecreated"] = fromMySQLDate($data["datecreated"], 0, 1);
                                                                                                $data["normalisedValidUntil"] = $data["validuntil"];
                                                                                                $data["validuntil"] = fromMySQLDate($data["validuntil"], 0, 1);
                                                                                                $data["normalisedLastModified"] = $data["lastmodified"];
                                                                                                $data["lastmodified"] = fromMySQLDate($data["lastmodified"], 0, 1);
                                                                                                $data["stageClass"] = WHMCS\View\Helper::generateCssFriendlyClassName($data["stage"]);
                                                                                                $data["stage"] = getQuoteStageLang($data["stage"]);
                                                                                                $quoteStatus[$data["stage"]]++;
                                                                                                $quotes[] = $data;
                                                                                            }
                                                                                            $smartyvalues["quotes"] = $quotes;
                                                                                            $smartyvalues["quotestatus"] = $quoteStatus;
                                                                                            $smartyvalues = array_merge($smartyvalues, clientAreaTablePageNav($numitems));
                                                                                            $ca->addOutputHookFunction("ClientAreaPageQuotes");
                                                                                        } else {
                                                                                            if ($action == "bulkdomain") {
                                                                                                checkContactPermission("managedomains");
                                                                                                $ca->setTemplate("bulkdomainmanagement");
                                                                                                if (empty($domids)) {
                                                                                                    redir("action=domains&error=noDomainsSelected#");
                                                                                                }
                                                                                                $domids = App::getFromRequest("domids");
                                                                                                $domainIds = db_build_in_array(db_escape_numarray($domids));
                                                                                                $queryfilter = "userid=" . (int) $legacyClient->getID() . " AND id IN (" . $domainIds . ")";
                                                                                                $storedDomains = $domainids = $errors = [];
                                                                                                $result = select_query("tbldomains", "id,domain", $queryfilter, "domain", "ASC");
                                                                                                while ($data = mysql_fetch_assoc($result)) {
                                                                                                    $domainids[] = $data["id"];
                                                                                                    $storedDomains[] = $data["domain"];
                                                                                                }
                                                                                                if (!count($domainids)) {
                                                                                                    redir("action=domains&error=noDomainsSelected#");
                                                                                                }
                                                                                                $queryfilter2 = $queryfilter . " AND status != \"Active\"";
                                                                                                $numNonActiveDomains = get_query_val("tbldomains", "COUNT(\"id\")", $queryfilter2);
                                                                                                if ($numNonActiveDomains != 0) {
                                                                                                    redir("action=domains&error=nonActiveDomainsSelected#");
                                                                                                }
                                                                                                if (!$update) {
                                                                                                    if ($nameservers) {
                                                                                                        $update = "nameservers";
                                                                                                    } else {
                                                                                                        if ($autorenew) {
                                                                                                            $update = "autorenew";
                                                                                                        } else {
                                                                                                            if ($reglock) {
                                                                                                                $update = "reglock";
                                                                                                            } else {
                                                                                                                if ($contactinfo) {
                                                                                                                    $update = "contactinfo";
                                                                                                                } else {
                                                                                                                    if ($renew) {
                                                                                                                        $update = "renew";
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                }
                                                                                                switch ($update) {
                                                                                                    case "nameservers":
                                                                                                        $ca->setDisplayTitle(Lang::trans("domainmanagens"));
                                                                                                        break;
                                                                                                    case "autorenew":
                                                                                                        $ca->setDisplayTitle(Lang::trans("domainautorenewstatus"));
                                                                                                        break;
                                                                                                    case "reglock":
                                                                                                        $ca->setDisplayTitle(Lang::trans("domainreglockstatus"));
                                                                                                        break;
                                                                                                    case "contactinfo":
                                                                                                        $ca->setDisplayTitle(Lang::trans("domaincontactinfoedit"));
                                                                                                        break;
                                                                                                    default:
                                                                                                        redir();
                                                                                                        $smartyvalues["domainids"] = $domainids;
                                                                                                        $smartyvalues["domains"] = $storedDomains;
                                                                                                        $smartyvalues["update"] = $update;
                                                                                                        $smartyvalues["save"] = $save;
                                                                                                        $currpage = $_SERVER["PHP_SELF"] . "?action=bulkdomain";
                                                                                                        $ca->addToBreadCrumb("clientarea.php?action=domains", $whmcs->get_lang("clientareanavdomains"));
                                                                                                        if ($update == "nameservers") {
                                                                                                            $ca->addToBreadCrumb($currpage, $whmcs->get_lang("domainmanagens"));
                                                                                                            if ($save) {
                                                                                                                check_token();
                                                                                                                foreach ($domainids as $domainid) {
                                                                                                                    $data = get_query_vals("tbldomains", "domain,registrar", ["id" => $domainid, "userid" => $legacyClient->getID()]);
                                                                                                                    $domain = $data["domain"];
                                                                                                                    $registrar = $data["registrar"];
                                                                                                                    $domainparts = explode(".", $domain, 2);
                                                                                                                    $params = [];
                                                                                                                    $params["domainid"] = $domainid;
                                                                                                                    list($params["sld"], $params["tld"]) = $domainparts;
                                                                                                                    $params["registrar"] = $registrar;
                                                                                                                    if ($nschoice == "default") {
                                                                                                                        $params = RegGetDefaultNameservers($params, $domain);
                                                                                                                    } else {
                                                                                                                        $params["ns1"] = $ns1;
                                                                                                                        $params["ns2"] = $ns2;
                                                                                                                        $params["ns3"] = $ns3;
                                                                                                                        $params["ns4"] = $ns4;
                                                                                                                        $params["ns5"] = $ns5;
                                                                                                                    }
                                                                                                                    $values = RegSaveNameservers($params);
                                                                                                                    if (!function_exists($registrar . "_SaveNameservers")) {
                                                                                                                        $errors[] = $domain . " " . Lang::trans("domaincannotbemanaged");
                                                                                                                    }
                                                                                                                    if ($values["error"]) {
                                                                                                                        $errors[] = $domain . " - " . $values["error"];
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        } else {
                                                                                                            if ($update == "autorenew") {
                                                                                                                $ca->addToBreadCrumb($currpage . "#", $whmcs->get_lang("domainautorenewstatus"));
                                                                                                                if ($save) {
                                                                                                                    check_token();
                                                                                                                    foreach ($domainids as $domainid) {
                                                                                                                        if (App::isInRequest("enable")) {
                                                                                                                            update_query("tbldomains", ["donotrenew" => ""], ["id" => $domainid, "userid" => $legacyClient->getID()]);
                                                                                                                        } else {
                                                                                                                            disableAutoRenew($domainid);
                                                                                                                        }
                                                                                                                    }
                                                                                                                }
                                                                                                            } else {
                                                                                                                if ($update == "reglock") {
                                                                                                                    $ca->addToBreadCrumb($currpage . "#", $whmcs->get_lang("domainreglockstatus"));
                                                                                                                    if ($save) {
                                                                                                                        check_token();
                                                                                                                        foreach ($domainids as $domainid) {
                                                                                                                            $data = get_query_vals("tbldomains", "domain,registrar", ["id" => $domainid, "userid" => $legacyClient->getID()]);
                                                                                                                            $domain = $data["domain"];
                                                                                                                            $registrar = $data["registrar"];
                                                                                                                            $domainparts = explode(".", $domain, 2);
                                                                                                                            $params = [];
                                                                                                                            $params["domainid"] = $domainid;
                                                                                                                            list($params["sld"], $params["tld"]) = $domainparts;
                                                                                                                            $params["registrar"] = $registrar;
                                                                                                                            $newlockstatus = $_POST["enable"] ? "locked" : "unlocked";
                                                                                                                            $params["lockenabled"] = $newlockstatus;
                                                                                                                            $values = RegSaveRegistrarLock($params);
                                                                                                                            if (!function_exists($registrar . "_SaveRegistrarLock")) {
                                                                                                                                $errors[] = $domain . " " . Lang::trans("domaincannotbemanaged");
                                                                                                                            }
                                                                                                                            if ($values["error"]) {
                                                                                                                                $errors[] = $domain . " - " . $values["error"];
                                                                                                                            }
                                                                                                                        }
                                                                                                                    }
                                                                                                                } else {
                                                                                                                    if ($update == "contactinfo") {
                                                                                                                        if (!is_array($domainids) || count($domainids) <= 0) {
                                                                                                                            exit("Invalid Access Attempt");
                                                                                                                        }
                                                                                                                        $ca->addToBreadCrumb($currpage . "#", $whmcs->get_lang("domaincontactinfoedit"));
                                                                                                                        if ($save) {
                                                                                                                            check_token();
                                                                                                                            $domainToUpdate = new WHMCS\Domains();
                                                                                                                            $wc = $whmcs->get_req_var("wc");
                                                                                                                            $contactdetails = $whmcs->get_req_var("contactdetails");
                                                                                                                            foreach ($wc as $wc_key => $wc_val) {
                                                                                                                                if ($wc_val == "contact") {
                                                                                                                                    $selctype = $sel[$wc_key][0];
                                                                                                                                    $selcid = $selctype == "c" ? substr($sel[$wc_key], 1) : "";
                                                                                                                                    $tmpContactDetails = $legacyClient->getDetails($selcid);
                                                                                                                                    $contactdetails[$wc_key] = $domainToUpdate->buildWHOISSaveArray($tmpContactDetails);
                                                                                                                                }
                                                                                                                            }
                                                                                                                            foreach ($domainids as $domainid) {
                                                                                                                                $domainToUpdate = new WHMCS\Domains();
                                                                                                                                $domain_data = $domainToUpdate->getDomainsDatabyID($domainid);
                                                                                                                                if (!$domain_data) {
                                                                                                                                    redir("action=domains", "clientarea.php");
                                                                                                                                }
                                                                                                                                $success = $domainToUpdate->moduleCall("SaveContactDetails", ["contactdetails" => $contactdetails]);
                                                                                                                                if (!$success) {
                                                                                                                                    if ($domainToUpdate->getLastError() == "Function not found") {
                                                                                                                                        $errors[] = $domain . " " . Lang::trans("domaincannotbemanaged");
                                                                                                                                    } else {
                                                                                                                                        $errors[] = $domainToUpdate->getLastError();
                                                                                                                                    }
                                                                                                                                }
                                                                                                                            }
                                                                                                                        }
                                                                                                                        $smartyvalues["contacts"] = $legacyClient->getContactsWithAddresses();
                                                                                                                        $domainToFetch = new WHMCS\Domains();
                                                                                                                        $domain_data = $domainToFetch->getDomainsDatabyID($domainids[0]);
                                                                                                                        if (!$domain_data) {
                                                                                                                            redir("action=domains", "clientarea.php");
                                                                                                                        }
                                                                                                                        $success = $domainToFetch->moduleCall("GetContactDetails");
                                                                                                                        if ($success) {
                                                                                                                            if ($save && $errors && isset($contactdetails)) {
                                                                                                                                $contactDetails = $contactdetails;
                                                                                                                            } else {
                                                                                                                                $contactDetails = $domainToFetch->getModuleReturn();
                                                                                                                            }
                                                                                                                            $smartyvalues["contactdetails"] = $domainToFetch->normalisePhoneNumberInContactDetails($contactDetails);
                                                                                                                            $smartyvalues["contactdetailstranslations"] = $domainToFetch->getContactFieldNameTranslations($contactDetails);
                                                                                                                        }
                                                                                                                    } else {
                                                                                                                        if ($update == "renew") {
                                                                                                                            redir("gid=renewals", "cart.php");
                                                                                                                        } else {
                                                                                                                            redir("action=domains");
                                                                                                                        }
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                        $smartyvalues["errors"] = $errors;
                                                                                                        $ca->addOutputHookFunction("ClientAreaPageBulkDomainManagement");
                                                                                                }
                                                                                            } else {
                                                                                                if ($action == "domainaddons") {
                                                                                                    check_token();
                                                                                                    $ca->setTemplate("clientareadomainaddons");
                                                                                                    $domainid = $domainData["id"];
                                                                                                    $domain = $domainData["domain"];
                                                                                                    if (!$domainid) {
                                                                                                        redir();
                                                                                                    }
                                                                                                    $smartyvalues["domainid"] = $domainid;
                                                                                                    $smartyvalues["domain"] = $domainData["domain"];
                                                                                                    $ca->addToBreadCrumb("clientarea.php?action=domaindetails&id=" . $domainData["id"], $domainData["domain"]);
                                                                                                    $ca->addToBreadCrumb("#", $whmcs->get_lang("clientareahostingaddons"));
                                                                                                    $domainparts = explode(".", $domainData["domain"], 2);
                                                                                                    $result = select_query("tblpricing", "", ["type" => "domainaddons", "currency" => $currency["id"], "relid" => 0]);
                                                                                                    $pricingdata = mysql_fetch_array($result);
                                                                                                    $domaindnsmanagementprice = $pricingdata["msetupfee"];
                                                                                                    $domainemailforwardingprice = $pricingdata["qsetupfee"];
                                                                                                    $domainidprotectionprice = $pricingdata["ssetupfee"];
                                                                                                    $ca->assign("addonspricing", ["dnsmanagement" => formatCurrency($domaindnsmanagementprice), "emailforwarding" => formatCurrency($domainemailforwardingprice), "idprotection" => formatCurrency($domainidprotectionprice)]);
                                                                                                    if ($disable) {
                                                                                                        $smartyvalues["action"] = "disable";
                                                                                                        $smartyvalues["addon"] = $disable;
                                                                                                        $where = [];
                                                                                                        $where["id"] = $domainData["id"];
                                                                                                        $where["userid"] = $legacyClient->getID();
                                                                                                        if ($disable == "dnsmanagement") {
                                                                                                            if (!$domainData["dnsmanagement"]) {
                                                                                                                redir();
                                                                                                            }
                                                                                                            if ($confirm) {
                                                                                                                check_token();
                                                                                                                update_query("tbldomains", ["dnsmanagement" => "", "recurringamount" => "-=" . $domaindnsmanagementprice], $where);
                                                                                                                $smartyvalues["success"] = true;
                                                                                                            }
                                                                                                        } else {
                                                                                                            if ($disable == "emailfwd") {
                                                                                                                if (!$domainData["emailforwarding"]) {
                                                                                                                    redir();
                                                                                                                }
                                                                                                                if ($confirm) {
                                                                                                                    check_token();
                                                                                                                    update_query("tbldomains", ["emailforwarding" => "", "recurringamount" => "-=" . $domainemailforwardingprice], $where);
                                                                                                                    $smartyvalues["success"] = true;
                                                                                                                }
                                                                                                            } else {
                                                                                                                if ($disable == "idprotect") {
                                                                                                                    if (!$domainData["idprotection"]) {
                                                                                                                        redir();
                                                                                                                    }
                                                                                                                    if ($confirm) {
                                                                                                                        check_token();
                                                                                                                        update_query("tbldomains", ["idprotection" => "", "recurringamount" => "-=" . $domainidprotectionprice], $where);
                                                                                                                        $domainparts = explode(".", $domain, 2);
                                                                                                                        $params = [];
                                                                                                                        $params["domainid"] = $domainData["id"];
                                                                                                                        list($params["sld"], $params["tld"]) = $domainparts;
                                                                                                                        $params["regperiod"] = $domainData["registrationperiod"];
                                                                                                                        $params["registrar"] = $domainData["registrar"];
                                                                                                                        $params["regtype"] = $domainData["type"];
                                                                                                                        $values = RegIDProtectToggle($params);
                                                                                                                        if ($values["error"]) {
                                                                                                                            $smartyvalues["error"] = true;
                                                                                                                        } else {
                                                                                                                            $smartyvalues["success"] = true;
                                                                                                                        }
                                                                                                                    }
                                                                                                                } else {
                                                                                                                    if ($id) {
                                                                                                                        redir("action=domaindetails&id=" . $id);
                                                                                                                    } else {
                                                                                                                        redir();
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                    if ($buy) {
                                                                                                        $smartyvalues["action"] = "buy";
                                                                                                        $smartyvalues["addon"] = $buy;
                                                                                                        $paymentmethod = getClientsPaymentMethod($legacyClient->getID());
                                                                                                        $domaintax = $whmcs->get_config("TaxDomains") ? 1 : 0;
                                                                                                        $invdesc = "";
                                                                                                        if ($buy == "dnsmanagement") {
                                                                                                            if ($confirm) {
                                                                                                                $invdesc = Lang::trans("domainaddons") . " (" . Lang::trans("domainaddonsdnsmanagement") . ") - " . $domain . " - 1 " . Lang::trans("orderyears");
                                                                                                                $invamt = $domaindnsmanagementprice;
                                                                                                                $addontype = "DNS";
                                                                                                            }
                                                                                                        } else {
                                                                                                            if ($buy == "emailfwd") {
                                                                                                                if ($confirm) {
                                                                                                                    $invdesc = Lang::trans("domainaddons") . " (" . Lang::trans("domainemailforwarding") . ") - " . $domain . " - 1 " . Lang::trans("orderyears");
                                                                                                                    $invamt = $domainemailforwardingprice;
                                                                                                                    $addontype = "EMF";
                                                                                                                }
                                                                                                            } else {
                                                                                                                if ($buy == "idprotect") {
                                                                                                                    if ($confirm) {
                                                                                                                        $invdesc = Lang::trans("domainaddons") . " (" . Lang::trans("domainidprotection") . ") - " . $domain . " - 1 " . Lang::trans("orderyears");
                                                                                                                        $invamt = $domainidprotectionprice;
                                                                                                                        $addontype = "IDP";
                                                                                                                    }
                                                                                                                } else {
                                                                                                                    if ($id) {
                                                                                                                        redir("action=domaindetails&id=" . $id);
                                                                                                                    } else {
                                                                                                                        redir();
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                        if ($invdesc) {
                                                                                                            check_token();
                                                                                                            insert_query("tblinvoiceitems", ["userid" => $legacyClient->getID(), "type" => "DomainAddon" . $addontype, "relid" => $domainid, "description" => $invdesc, "amount" => $invamt, "taxed" => $domaintax, "duedate" => "now()", "paymentmethod" => $paymentmethod]);
                                                                                                            if (!function_exists("createInvoices")) {
                                                                                                                require ROOTDIR . "/includes/processinvoices.php";
                                                                                                            }
                                                                                                            $invoiceid = createInvoices($legacyClient->getID());
                                                                                                            if ($invoiceid) {
                                                                                                                redir("id=" . $invoiceid, "viewinvoice.php");
                                                                                                            }
                                                                                                            redir();
                                                                                                        }
                                                                                                    }
                                                                                                    $ca->addOutputHookFunction("ClientAreaPageDomainAddons");
                                                                                                } else {
                                                                                                    if ($action == "kbsearch") {
                                                                                                        $knowledgebaseController = new WHMCS\Knowledgebase\Controller\Knowledgebase();
                                                                                                        $request = Laminas\Diactoros\ServerRequestFactory::fromGlobals();
                                                                                                        $ca = $knowledgebaseController->search($request);
                                                                                                        (new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($ca);
                                                                                                        exit;
                                                                                                    }
                                                                                                    redir();
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
switch ($action) {
    case "":
        $sidebarName = "clientHome";
        break;
    case "details":
    case "creditcard":
    case "contacts":
    case "addcontact":
    case "changepw":
    case "security":
    case "emails":
        $sidebarName = "clientView";
        break;
    case "hosting":
    case "products":
    case "services":
        $sidebarName = "serviceList";
        break;
    case "productdetails":
    case "cancel":
        Menu::addContext("service", $serviceModel);
        $sidebarName = "serviceView";
        break;
    case "domains":
    case "bulkdomain":
        $sidebarName = "domainList";
        break;
    case "domaindetails":
    case "domaincontacts":
    case "domaindns":
    case "domainemailforwarding":
    case "domaingetepp":
    case "domainregisterns":
    case "domainaddons":
        Menu::addContext("domain", $domainModel);
        $sidebarName = "domainView";
        break;
    case "addfunds":
        $sidebarName = "clientAddFunds";
        break;
    case "invoices":
    case "masspay":
        $sidebarName = "invoiceList";
        break;
    case "quotes":
        $sidebarName = "clientQuoteList";
        break;
    default:
        $sidebarName = "clientHome";
        break;
}
Menu::primarySidebar($sidebarName);
Menu::secondarySidebar($sidebarName);
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($ca);
