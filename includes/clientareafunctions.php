<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;
use WHMCS\Application\Support\Facades\Menu;

function initialiseLoggedInClient()
{
    global $smarty;
    global $clientsdetails;
    $client = NULL;
    $clientAlerts = [];
    $clientsdetails = [];
    $clientsstats = [];
    if (Auth::client()) {
        $client = Auth::client();
        $legacyClient = new WHMCS\Client($client);
        $clientsdetails = $legacyClient->getDetails();
        if (!function_exists("getClientsStats")) {
            require ROOTDIR . "/includes/clientfunctions.php";
        }
        $clientsstats = getClientsStats(Auth::client()->id);
        $alerts = new WHMCS\User\Client\AlertFactory($client);
        $clientAlerts = $alerts->build();
    }
    $loggedInUser = Auth::user();
    $userValidation = DI::make("userValidation");
    $smarty->assign("loggedin", !empty($client->id));
    $smarty->assign("client", $client);
    $smarty->assign("clientsdetails", $clientsdetails);
    $smarty->assign("clientAlerts", $clientAlerts);
    $smarty->assign("clientsstats", $clientsstats);
    $smarty->assign("loggedinuser", $loggedInUser);
    $smarty->assign("showEmailVerificationBanner", WHMCS\ClientArea\User\EmailVerification::shouldShowEmailVerificationBanner());
    $smarty->assign("showUserValidationBanner", $userValidation->shouldShowClientBanner());
    $smarty->assign("userValidationUrl", $loggedInUser ? $userValidation->getSubmitUrlForUser($loggedInUser) : "");
    $smarty->assign("userValidationHost", $userValidation->getSubmitHost());
    return $client;
}

function initialiseClientArea($pageTitle, $displayTitle, $tagline, $pageIcon = NULL, $breadcrumb = NULL, $smartyValues = [])
{
    global $_LANG;
    global $smarty;
    global $smartyvalues;
    if ($smartyValues) {
        $smartyvalues = array_merge($smartyvalues, $smartyValues);
    }
    if (defined("PERFORMANCE_DEBUG")) {
        define("PERFORMANCE_STARTTIME", microtime());
    }
    if (is_null($pageIcon) && is_null($breadcrumb)) {
        $pageIcon = $displayTitle;
        $displayTitle = $pageTitle;
        $breadcrumb = $tagline;
        $tagline = "";
    }
    $whmcs = App::self();
    $filename = $whmcs->getCurrentFilename();
    $smarty = new WHMCS\Smarty();
    foreach (WHMCS\ClientArea::EMPTY_PARAMETERS as $templateParam) {
        $smarty->assign($templateParam, "");
    }
    $queryString = http_build_query($_GET);
    $setlanguage = "<form method=\"post\" action=\"" . $_SERVER["PHP_SELF"] . "?" . $queryString . "\" name=\"languagefrm\" id=\"languagefrm\">\n<strong>" . $_LANG["language"] . "</strong>\n<select name=\"language\" onchange=\"languagefrm.submit()\">";
    unset($queryString);
    foreach (WHMCS\Language\ClientLanguage::getLanguages() as $lang) {
        $setlanguage .= "<option";
        if ($lang == Lang::getName()) {
            $setlanguage .= " selected=\"selected\"";
        }
        $setlanguage .= ">" . ucfirst($lang) . "</option>";
    }
    $setlanguage .= "</select></form>";
    $smarty->assign("setlanguage", $setlanguage);
    $smarty->assign("languages", Lang::getLanguages());
    $locales = Lang::getLocales();
    $smarty->assign("locales", $locales);
    $activeLocale = NULL;
    foreach ($locales as $locale) {
        if ($locale["language"] == Lang::getName()) {
            $activeLocale = $locale;
            $smarty->assign("activeLocale", $activeLocale);
            $carbonObject = new WHMCS\Carbon();
            $carbonObject->setLocale($activeLocale["languageCode"]);
            $smarty->assign("carbon", $carbonObject);
            $smarty->assign("showbreadcrumb", false);
            $smarty->assign("showingLoginPage", false);
            $smarty->assign("incorrect", false);
            $smarty->assign("backupcode", false);
            $smarty->assign("kbarticle", ["title" => ""]);
            $theme = $whmcs->getClientAreaTemplate();
            foreach ($theme->getTemplateConfigValues() as $key => $value) {
                $smarty->assign($key, $value);
            }
            $smarty->assign("language", Lang::getName());
            $smarty->assign("LANG", $_LANG);
            $smarty->assign("companyname", WHMCS\Config\Setting::getValue("CompanyName"));
            $smarty->assign("logo", WHMCS\Config\Setting::getValue("LogoURL"));
            $smarty->assign("charset", WHMCS\Config\Setting::getValue("Charset"));
            $smarty->assign("pagetitle", $pageTitle);
            $smarty->assign("displayTitle", $displayTitle);
            $smarty->assign("tagline", $tagline);
            $smarty->assign("pageicon", $pageIcon);
            $smarty->assign("filename", $filename);
            $smarty->assign("breadcrumb", breakBreadcrumbHTMLIntoParts($breadcrumb));
            $smarty->assign("breadcrumbnav", $breadcrumb);
            $smarty->assign("todaysdate", $carbonObject->format("l, jS F Y"));
            $smarty->assign("date_day", $carbonObject->format("d"));
            $smarty->assign("date_month", $carbonObject->format("m"));
            $smarty->assign("date_year", $carbonObject->format("Y"));
            $smarty->assign("token", generate_token("plain"));
            $smarty->assign("reCaptchaPublicKey", WHMCS\Config\Setting::getValue("ReCAPTCHAPublicKey"));
            $smarty->assign("servedOverSsl", $whmcs->in_ssl());
            $smarty->assign("versionHash", WHMCS\View\Helper::getAssetVersionHash());
            $smarty->assign("systemurl", $whmcs->getSystemURL());
            $smarty->assign("systemsslurl", $whmcs->getSystemURL());
            $smarty->assign("systemNonSSLURL", $whmcs->getSystemURL());
            $assetHelper = DI::make("asset");
            $smarty->assign("WEB_ROOT", $assetHelper->getWebRoot());
            $smarty->assign("BASE_PATH_CSS", $assetHelper->getCssPath());
            $smarty->assign("BASE_PATH_JS", $assetHelper->getJsPath());
            $smarty->assign("BASE_PATH_FONTS", $assetHelper->getFontsPath());
            $smarty->assign("BASE_PATH_IMG", $assetHelper->getImgPath());
            if (file_exists(ROOTDIR . "/assets/img/logo.png")) {
                $assetLogoPath = $assetHelper->getImgPath() . "/logo.png";
            } else {
                if (file_exists(ROOTDIR . "/assets/img/logo.jpg")) {
                    $assetLogoPath = $assetHelper->getImgPath() . "/logo.jpg";
                } else {
                    $assetLogoPath = "";
                }
            }
            $smarty->assign("assetLogoPath", $assetLogoPath);
            $client = initialiseloggedinclient();
            $langChangeEnabled = WHMCS\Config\Setting::getValue("AllowLanguageChange") ? true : false;
            $smarty->assign("langchange", $langChangeEnabled);
            $smarty->assign("languagechangeenabled", $langChangeEnabled);
            $smarty->assign("acceptTOS", WHMCS\Config\Setting::getValue("EnableTOSAccept"));
            $smarty->assign("tosURL", WHMCS\Config\Setting::getValue("TermsOfService"));
            $smarty->assign("currentpagelinkback", WHMCS\ClientArea::getCurrentPageLinkBack());
            $currenciesarray = WHMCS\Billing\Currency::all(["id", "code", "prefix", "suffix", "default"])->toArray();
            if (count($currenciesarray) == 1) {
                $currenciesarray = "";
            }
            $smarty->assign("currencies", $currenciesarray);
            $smarty->assign("activeCurrency", WHMCS\Billing\Currency::factoryForClientArea());
            $smarty->assign("twitterusername", (new WHMCS\Social\SocialAccounts())->get("twitter"));
            $smarty->assign("announcementsFbRecommend", WHMCS\Config\Setting::getValue("AnnouncementsFBRecommend"));
            $smarty->assign("condlinks", WHMCS\ClientArea::getConditionalLinks());
            Menu::addContext("user", Auth::user());
            Menu::addContext("client", $client);
            Menu::addContext("currencies", $currenciesarray);
            Menu::addContext("carbon", $carbonObject);
            $smartyvalues = [];
        }
    }
}

function outputClientArea($templatefile, $nowrapper = false, $hookFunctions = [], $smartyValues = [])
{
    global $CONFIG;
    global $smarty;
    global $smartyvalues;
    global $orderform;
    global $usingsupportmodule;
    if (!empty($smartyValues)) {
        $smartyvalues = $smartyValues;
    }
    $whmcs = App::self();
    $licensing = DI::make("license");
    if (!$templatefile) {
        exit("Invalid Entity Requested");
    }
    if ($licensing->getBrandingRemoval()) {
        $copyrighttext = "";
    } else {
        $copyrighttext = "<p style=\"text-align:center;\">Powered by <a href=\"https://www.whmcs.com/\" target=\"_blank\">WHMCompleteSolution</a></p>";
    }
    $loggedInClientFirstName = "";
    $loggedInUser = $smarty->tpl_vars["loggedinuser"]->value;
    if (isset($loggedInUser["firstname"])) {
        $loggedInClientFirstName = $loggedInUser["firstname"];
    }
    $conditionalLinks = WHMCS\ClientArea::getConditionalLinks();
    $primaryNavbar = Menu::primaryNavbar($loggedInClientFirstName, $conditionalLinks);
    $secondaryNavbar = Menu::secondaryNavbar($loggedInClientFirstName, $conditionalLinks);
    run_hook("ClientAreaPrimaryNavbar", $primaryNavbar);
    run_hook("ClientAreaSecondaryNavbar", $secondaryNavbar);
    run_hook("ClientAreaNavbars", NULL);
    $primarySidebar = Menu::primarySidebar();
    $secondarySidebar = Menu::secondarySidebar();
    run_hook("ClientAreaPrimarySidebar", [$primarySidebar], true);
    run_hook("ClientAreaSecondarySidebar", [$secondarySidebar], true);
    run_hook("ClientAreaSidebars", NULL);
    $smarty->assign("primaryNavbar", WHMCS\View\Menu\Item::sort($primaryNavbar));
    $smarty->assign("secondaryNavbar", WHMCS\View\Menu\Item::sort($secondaryNavbar));
    $smarty->assign("primarySidebar", WHMCS\View\Menu\Item::sort($primarySidebar));
    $smarty->assign("secondarySidebar", WHMCS\View\Menu\Item::sort($secondarySidebar));
    if (isset($GLOBALS["pagelimit"])) {
        $smartyvalues["itemlimit"] = $GLOBALS["pagelimit"];
    }
    if ($smarty->getTemplateVars("requestedTpl")) {
        $requestedOrderFormTemplateName = $smarty->getTemplateVars("requestedTpl");
    } else {
        $requestedOrderFormTemplateName = $smartyvalues["requestedTpl"] ?? "";
    }
    if ($smarty->getTemplateVars("carttpl")) {
        $orderFormTemplateName = $smarty->getTemplateVars("carttpl");
    } else {
        $orderFormTemplateName = $smartyvalues["carttpl"] ?? "";
    }
    unset($smartyvalues["requestedTpl"]);
    $cart = new WHMCS\OrderForm();
    $orderFormTemplate = coalesce(WHMCS\View\Template\OrderForm::find($requestedOrderFormTemplateName), WHMCS\View\Template\OrderForm::find($orderFormTemplateName));
    if ($orderFormTemplate) {
        foreach ($orderFormTemplate->getTemplateConfigValues() as $key => $value) {
            $smarty->assign($key, $value);
        }
    }
    $smartyvalues["cartitemcount"] = $cart->getNumItemsInCart();
    $smartyvalues["templatefile"] = $templatefile;
    $smartyvalues["adminLoggedIn"] = (bool) WHMCS\Session::get("adminid");
    $smartyvalues["adminMasqueradingAsClient"] = WHMCS\ClientArea::isAdminMasqueradingAsClient();
    $smartyvalues["supportedCardTypes"] = WHMCS\Gateways::getSupportedCardTypesForJQueryPayment();
    $networkIssuesStatuses = WHMCS\Database\Capsule::table("tblnetworkissues")->where("status", "!=", "Resolved")->get(["status"]);
    $smartyvalues["openNetworkIssueCounts"] = ["open" => $networkIssuesStatuses->where("status", "!=", "Scheduled")->count(), "scheduled" => $networkIssuesStatuses->where("status", "=", "Scheduled")->count()];
    $smartyvalues["socialAccounts"] = (new WHMCS\Social\SocialAccounts())->getConfigured();
    if ($smartyvalues) {
        $smartyvalues = array_merge($smartyvalues, WHMCS\ClientArea::calculatePwStrengthThresholds());
        foreach ($smartyvalues as $key => $value) {
            $smarty->assign($key, $value);
        }
    }
    $hookParameters = $smarty->getTemplateVars();
    unset($hookParameters["LANG"]);
    $hookFunctions = array_merge(["ClientAreaPage"], $hookFunctions);
    foreach ($hookFunctions as $hookFunction) {
        $hookResponses = run_hook($hookFunction, $hookParameters);
        foreach ($hookResponses as $hookTemplateVariables) {
            foreach ($hookTemplateVariables as $k => $v) {
                $hookParameters[$k] = $v;
                if (isset($smartyvalues[$k])) {
                    $smartyvalues[$k] = $v;
                }
                $smarty->assign($k, $v);
            }
        }
    }
    $sidebarVarsToCleanup = [$smarty->tpl_vars["primarySidebar"], $smarty->tpl_vars["secondarySidebar"]];
    foreach ($sidebarVarsToCleanup as $var) {
        if ($var && $var->value instanceof WHMCS\View\Menu\Item) {
            Menu::removeEmptyChildren($var->value);
        }
    }
    $hookResponses = run_hook("ClientAreaHeadOutput", $hookParameters);
    $headOutput = "";
    foreach ($hookResponses as $response) {
        if ($response) {
            $headOutput .= $response . "\n";
        }
    }
    $smarty->assign("headoutput", $headOutput);
    $hookResponses = run_hook("ClientAreaHeaderOutput", $hookParameters);
    $headerOutput = "";
    foreach ($hookResponses as $response) {
        if ($response) {
            $headerOutput .= $response . "\n";
        }
    }
    $smarty->assign("headeroutput", $headerOutput);
    $hookResponses = run_hook("ClientAreaFooterOutput", $hookParameters);
    $footerOutput = "";
    foreach ($hookResponses as $response) {
        if ($response) {
            $footerOutput .= $response . "\n";
        }
    }
    if (array_key_exists("credit_card_input", $smartyvalues) && $smartyvalues["credit_card_input"]) {
        $footerOutput .= $smartyvalues["credit_card_input"];
        $smarty->clearAssign("credit_card_input");
    }
    $smarty->assign("footeroutput", $footerOutput);
    $activeTemplate = $whmcs->getClientAreaTemplate();
    $requiredSmartyVars = ["captcha", "containerClass", "hasLinkedProvidersEnabled", "inShoppingCart", "skipMainBodyContainer", "phoneNumberInputStyle", "promoerrormessage", "proratadate", "productRecommendations"];
    $definedTemplateVars = $smarty->getTemplateVars();
    foreach ($requiredSmartyVars as $requiredSmartyVar) {
        if (!isset($definedTemplateVars[$requiredSmartyVar])) {
            $smarty->assign($requiredSmartyVar, NULL);
        }
    }
    if (!isset($definedTemplateVars["client"])) {
        $smarty->assign("client", ["companyname" => NULL, "fullName" => NULL]);
    }
    unset($requiredSmartyVars);
    unset($definedTemplateVars);
    if (!$nowrapper) {
        $header_file = $smarty->fetch($activeTemplate->resolveFilePath("/header.tpl"));
        $footer_file = $smarty->fetch($activeTemplate->resolveFilePath("/footer.tpl"));
    }
    $clientArea = new WHMCS\ClientArea();
    $licenseBannerHtml = $clientArea->getLicenseBannerHtml();
    $clientAreaTemplatePath = $activeTemplate->resolveFilePath("/" . $templatefile . ".tpl");
    if ($orderform) {
        try {
            $body_file = $smarty->fetch(ROOTDIR . "/templates/orderforms/" . WHMCS\View\Template\OrderForm::factory($templatefile . ".tpl", $orderFormTemplateName)->getName() . "/" . $templatefile . ".tpl");
        } catch (WHMCS\Exception\View\TemplateNotFound $e) {
            if ($templatefile == "login") {
                $body_file = $smarty->fetch($clientAreaTemplatePath);
            } else {
                logActivity("Unable to load the " . $templatefile . ".tpl file from the " . $orderFormTemplateName . " order form template or any of its parents.");
                $body_file = "<p>" . Lang::trans("unableToLoadShoppingCart") . "</p>";
            }
        }
    } else {
        if ($usingsupportmodule) {
            $body_file = $smarty->fetch(ROOTDIR . "/templates/" . $CONFIG["SupportModule"] . "/" . $templatefile . ".tpl");
        } else {
            if (substr($templatefile, 0, 1) == "/" || substr($templatefile, 0, 1) == "\\") {
                $body_file = $smarty->fetch(ROOTDIR . $templatefile);
            } else {
                $body_file = $smarty->fetch($clientAreaTemplatePath);
            }
        }
    }
    if ($nowrapper) {
        $template_output = $body_file;
    } else {
        $template_output = $header_file . PHP_EOL . $licenseBannerHtml . PHP_EOL . $body_file . PHP_EOL . $copyrighttext . PHP_EOL . $footer_file;
    }
    if (!in_array($templatefile, ["3dsecure", "forwardpage", "viewinvoice"])) {
        $template_output = preg_replace("/(<form\\W[^>]*\\bmethod=('|\"|)POST('|\"|)\\b[^>]*>)/i", "\\1\n" . generate_token(), $template_output);
        $template_output = WHMCS\View\Asset::conditionalFontawesomeCssInclude($template_output);
    }
    echo $template_output;
    if (defined("PERFORMANCE_DEBUG")) {
        global $query_count;
        $exectime = microtime() - PERFORMANCE_STARTTIME;
        echo "<p>Performance Debug: " . $exectime . " Queries: " . $query_count . "</p>";
    }
}

function processSingleTemplate($templatepath, $templatevars)
{
    global $smarty;
    global $smartyvalues;
    if ($smartyvalues) {
        foreach ($smartyvalues as $key => $value) {
            $smarty->assign($key, $value);
        }
    }
    foreach ($templatevars as $key => $value) {
        $smarty->assign($key, $value);
    }
    $templatecode = $smarty->fetch(ROOTDIR . $templatepath);
    return $templatecode;
}

function processSingleSmartyTemplate($smarty, $templatepath, $values)
{
    foreach ($values as $key => $value) {
        $smarty->assign($key, $value);
    }
    $templatecode = $smarty->fetch(ROOTDIR . $templatepath);
    return $templatecode;
}

function CALinkUpdateCC($forceReload = false)
{
    $can = WHMCS\Session::get("calinkupdatecc");
    if ($can !== "" && !$forceReload) {
        return $can;
    }
    $gatewaysHelper = new WHMCS\Gateways();
    $can = $gatewaysHelper->hasGatewaysSupportingManage();
    WHMCS\Session::set("calinkupdatecc", $can);
    return $can;
}

function CALinkUpdateSQ()
{
    $get_sq_count = get_query_val("tbladminsecurityquestions", "COUNT(id)", "");
    if (0 < $get_sq_count) {
        $_SESSION["calinkupdatesq"] = 1;
        return true;
    }
    if (1 <= WHMCS\ApplicationLink\ApplicationLink::whereIsEnabled(1)->count()) {
        $_SESSION["calinkupdatesq"] = 1;
        return true;
    }
    $_SESSION["calinkupdatesq"] = 0;
    return false;
}

function clientAreaTableInit($name, $defaultorderby, $defaultsort, $numitems)
{
    $whmcs = App::self();
    $requestedLimit = $whmcs->get_req_var("itemlimit");
    $orderby = $whmcs->get_req_var("orderby");
    $page = (int) $whmcs->get_req_var("page");
    $useServerSidePagination = true;
    $template = $whmcs->getClientAreaTemplate();
    if (!is_null($template)) {
        $properties = $template->getProperties();
        $useServerSidePagination = isset($properties["serverSidePagination"]) ? (bool) $properties["serverSidePagination"] : true;
    }
    $limitToApply = 10;
    if (!$useServerSidePagination) {
        $limitToApply = -1;
    } else {
        if (strtolower($requestedLimit) == "all") {
            WHMCS\Cookie::set("ItemsPerPage", -1);
            $limitToApply = -1;
        } else {
            if (is_numeric($requestedLimit)) {
                WHMCS\Cookie::set("ItemsPerPage", $requestedLimit);
                $limitToApply = $requestedLimit;
            } else {
                if (is_numeric($cookieStoredLimit = WHMCS\Cookie::get("ItemsPerPage"))) {
                    $limitToApply = $cookieStoredLimit;
                }
            }
        }
    }
    $GLOBALS["pagelimit"] = $limitToApply;
    if ($page < 1 || $numitems < ($page - 1) * $limitToApply || $limitToApply < 0) {
        $page = 1;
    }
    $GLOBALS["page"] = $page;
    if (!isset($_SESSION["ca" . $name . "orderby"])) {
        $_SESSION["ca" . $name . "orderby"] = $defaultorderby;
    }
    if (!isset($_SESSION["ca" . $name . "sort"])) {
        $_SESSION["ca" . $name . "sort"] = $defaultsort;
    }
    if ($_SESSION["ca" . $name . "orderby"] == $orderby) {
        if ($_SESSION["ca" . $name . "sort"] == "ASC") {
            $_SESSION["ca" . $name . "sort"] = "DESC";
        } else {
            $_SESSION["ca" . $name . "sort"] = "ASC";
        }
    }
    if ($orderby) {
        $_SESSION["ca" . $name . "orderby"] = $_REQUEST["orderby"];
    }
    $orderby = preg_replace("/[^a-z0-9]/", "", $_SESSION["ca" . $name . "orderby"]);
    $sort = $_SESSION["ca" . $name . "sort"];
    if (!in_array($sort, ["ASC", "DESC"])) {
        $sort = "ASC";
    }
    if ($useServerSidePagination && 0 < $limitToApply) {
        $limit = ($page - 1) * $limitToApply . "," . $limitToApply;
    } else {
        $limit = "";
    }
    return [$orderby, $sort, $limit];
}

function clientAreaTablePageNav($numitems)
{
    $numitems = (int) $numitems;
    $pagenumber = (int) $GLOBALS["page"];
    $pagelimit = (int) $GLOBALS["pagelimit"];
    if (0 < $pagelimit) {
        $totalpages = ceil($numitems / $pagelimit);
    } else {
        $totalpages = 1;
    }
    $prevpage = $pagenumber != 1 ? $pagenumber - 1 : "";
    $nextpage = $pagenumber != $totalpages && $numitems ? $pagenumber + 1 : "";
    if (!$totalpages) {
        $totalpages = 1;
    }
    return ["numitems" => $numitems, "numproducts" => $numitems, "pagenumber" => $pagenumber, "itemsperpage" => $pagelimit, "itemlimit" => 0 < $pagelimit ? $pagelimit : "99999999", "totalpages" => $totalpages, "prevpage" => $prevpage, "nextpage" => $nextpage];
}

function clientAreaInitCaptcha()
{
    $captcha = "";
    if (WHMCS\Config\Setting::getValue("CaptchaSetting") == "on" || WHMCS\Config\Setting::getValue("CaptchaSetting") == "offloggedin" && !Auth::user()) {
        if (in_array(WHMCS\Config\Setting::getValue("CaptchaType"), ["recaptcha", "invisible"])) {
            require ROOTDIR . "/includes/recaptchalib.php";
            $captcha = WHMCS\Config\Setting::getValue("CaptchaType");
        } else {
            $captcha = "default";
        }
    }
    $GLOBALS["captcha"] = $captcha;
    $GLOBALS["capatacha"] = $captcha;
    return $captcha;
}

function clientAreaReCaptchaHTML()
{
    if (empty($GLOBALS["captcha"]) || $GLOBALS["captcha"] != "recaptcha") {
        return "";
    }
    if (!function_exists("recaptcha_get_html")) {
        App::load_function("recaptchalib");
    }
    $publickey = WHMCS\Config\Setting::getValue("ReCAPTCHAPublicKey");
    return recaptcha_get_html($publickey);
}

function breakBreadcrumbHTMLIntoParts($breadcrumbHTML)
{
    $breadcrumb = [];
    $parts = explode(" > ", $breadcrumbHTML);
    foreach ($parts as $part) {
        $parts2 = explode("\">", $part, 2);
        $link = str_replace("<a href=\"", "", $parts2[0]);
        $breadcrumb[] = ["link" => $link, "label" => strip_tags($parts2[1])];
    }
    return $breadcrumb;
}
