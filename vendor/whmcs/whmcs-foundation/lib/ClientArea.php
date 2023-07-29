<?php

namespace WHMCS;

class ClientArea extends Http\Message\AbstractViewableResponse
{
    protected $renderedOutput = "";
    private $pageTitle = "";
    private $displayTitle = "";
    private $tagLine = "";
    private $breadcrumb = [];
    private $breadCrumbHtml = "";
    private $templateFile = "";
    private $templateVariables = [];
    private $wrappedWithHeaderFooter = true;
    private $inorderform = false;
    private $insupportmodule = false;
    protected $skipMainBodyContainer = false;
    protected $baseUrl = "";
    protected $outputHooks = ["ClientAreaPage"];
    protected $client = NULL;
    private $smarty = "";
    const EMPTY_PARAMETERS = ["displayTitle", "tagline", "type", "textcenter", "hide", "additionalClasses", "idname", "errorshtml", "title", "msg", "desc", "errormessage", "error", "livehelpjs", "editLink"];

    public function __construct($data = "", $status = 200, $headers = [])
    {
        parent::__construct($data, $status, $headers);
        if (defined("PERFORMANCE_DEBUG")) {
            define("PERFORMANCE_STARTTIME", microtime());
        }
        $this->initializeView();
    }

    public function isInOrderForm()
    {
        $this->inorderform = true;
    }

    public function resetRenderedOutput()
    {
        $this->renderedOutput = "";
        return $this;
    }

    protected function initializeView()
    {
        $this->resetRenderedOutput();
        $this->baseUrl = Utility\Environment\WebHelper::getBaseUrl(ROOTDIR, $_SERVER["SCRIPT_NAME"]);
        global $smartyvalues;
        $preBuiltSmartyValues = $smartyvalues;
        $smartyvalues = [];
        $this->startSmarty();
        if (is_array($preBuiltSmartyValues)) {
            foreach ($preBuiltSmartyValues as $key => $value) {
                $this->smarty->assign($key, $value);
            }
        }
    }

    public function setPageTitle($title)
    {
        $this->pageTitle = $title;
        return $this;
    }

    public function getPageTitle()
    {
        return $this->pageTitle;
    }

    public function addToBreadCrumb($link, $text)
    {
        if ($link instanceof \Psr\Http\Message\UriInterface) {
            $uri = (string) $link;
        } else {
            if (preg_match("#^http(s)?://#", $link)) {
                $uri = $link;
            } else {
                if ($link != "/" && ($this->baseUrl === "" || strpos($link, $this->baseUrl) !== 0)) {
                    if (strpos($link, "/") === 0) {
                        $link = substr($link, 1);
                    }
                    $uri = $this->baseUrl . "/" . $link;
                } else {
                    $uri = $link;
                }
            }
        }
        $this->breadcrumb[] = ["link" => $uri, "label" => $text];
        return $this;
    }

    public function resetBreadCrumb()
    {
        $this->breadcrumb = [];
        return $this;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function isLoggedIn()
    {
        return !is_null(\Auth::user());
    }

    public function requireLogin()
    {
        \Auth::requireLoginAndClient(true);
    }

    public function setTemplate($template)
    {
        $this->templateFile = $template;
        return $this;
    }

    public function getTemplateFile()
    {
        return $this->templateFile;
    }

    public function assign($key, $value, $allowOverride = true)
    {
        if ($allowOverride || !isset($this->templateVariables[$key]) || !$this->smarty->tpl_vars[$key]) {
            $this->templateVariables[$key] = $value;
            $this->smarty->assign($key, $value);
        }
        return $this;
    }

    public function setTemplateVariables($data)
    {
        $this->templateVariables = array_merge($this->templateVariables, $data);
        return $this;
    }

    public function retrieve($key)
    {
        $vars = $this->getTemplateVariables();
        return !empty($vars[$key]) ? $vars[$key] : "";
    }

    public function getTemplateVariables()
    {
        return $this->templateVariables;
    }

    public static function getRawStatus($val)
    {
        $val = strtolower($val);
        $val = str_replace(" ", "", $val);
        $val = str_replace("-", "", $val);
        return $val;
    }

    protected function startSmartyIfNotStarted()
    {
        if (is_object($this->smarty)) {
            return true;
        }
        return $this->startSmarty();
    }

    protected function startSmarty()
    {
        global $smarty;
        if (!$smarty) {
            $smarty = new Smarty();
        }
        $this->smarty =& $smarty;
        $this->initEmptyTemplateVars();
        return true;
    }

    protected function initEmptyTemplateVars()
    {
        foreach (self::EMPTY_PARAMETERS as $templateParam) {
            if (!isset($this->smarty->tpl_vars[$templateParam])) {
                $this->assign($templateParam, "", false);
            }
        }
        $this->assign("showbreadcrumb", false, false);
        $this->assign("showingLoginPage", false, false);
        $this->assign("incorrect", false, false);
        $this->assign("backupcode", false, false);
        $this->assign("kbarticle", ["title" => ""], false);
    }

    public function setDisplayTitle($displayTitle)
    {
        $this->displayTitle = $displayTitle;
        $this->assign("displayTitle", $displayTitle);
        return $this;
    }

    public function getDisplayTitle()
    {
        return $this->displayTitle;
    }

    public function setTagLine($tagline)
    {
        $this->tagLine = $tagline;
        $this->assign("tagline", $tagline);
        return $this;
    }

    public function getCurrentPageName()
    {
        $filename = $_SERVER["PHP_SELF"];
        $filename = substr($filename, strrpos($filename, "/"));
        $filename = str_replace("/", "", $filename);
        $filename = explode(".", $filename);
        $filename = $filename[0];
        return $filename;
    }

    protected function registerDefaultTPLVars()
    {
        global $_LANG;
        $this->assign("languages", \Lang::getLanguages());
        $locales = \Lang::getLocales();
        $this->assign("locales", $locales);
        $activeLocale = NULL;
        foreach ($locales as $locale) {
            if ($locale["language"] == \Lang::getName()) {
                $activeLocale = $locale;
                $this->assign("activeLocale", $activeLocale);
                $carbonObject = new Carbon();
                $carbonObject->setLocale($activeLocale["languageCode"]);
                $this->assign("carbon", $carbonObject);
                \Menu::addContext("carbon", $carbonObject);
                \Menu::addContext("menuAction", \App::getFromRequest("a"));
                $template = \App::getClientAreaTemplate();
                $themeVars = $template->getTemplateConfigValues();
                foreach ($themeVars as $key => $value) {
                    $this->assign($key, $value);
                }
                $this->assign("language", \Lang::getName());
                $this->assign("LANG", $_LANG);
                $this->assign("companyname", Config\Setting::getValue("CompanyName"));
                $this->assign("logo", Config\Setting::getValue("LogoURL"));
                $this->assign("charset", Config\Setting::getValue("Charset"));
                $this->assign("pagetitle", $this->pageTitle);
                $this->assign("filename", $this->getCurrentPageName());
                $this->assign("token", generate_token("plain"));
                $this->assign("reCaptchaPublicKey", Config\Setting::getValue("ReCAPTCHAPublicKey"));
                $this->assign("servedOverSsl", \App::in_ssl());
                $this->assign("versionHash", View\Helper::getAssetVersionHash());
                if (\App::getSystemURL() != "http://www.example.com/whmcs/") {
                    $this->assign("systemurl", \App::getSystemURL());
                }
                $this->assign("systemsslurl", \App::getSystemURL());
                $this->assign("systemNonSSLURL", \App::getSystemURL());
                $assetHelper = \DI::make("asset");
                $this->assign("WEB_ROOT", $assetHelper->getWebRoot());
                $this->assign("BASE_PATH_CSS", $assetHelper->getCssPath());
                $this->assign("BASE_PATH_JS", $assetHelper->getJsPath());
                $this->assign("BASE_PATH_FONTS", $assetHelper->getFontsPath());
                $this->assign("BASE_PATH_IMG", $assetHelper->getImgPath());
                $this->assign("todaysdate", $carbonObject->format("l, jS F Y"));
                $this->assign("date_day", $carbonObject->format("d"));
                $this->assign("date_month", $carbonObject->format("m"));
                $this->assign("date_year", $carbonObject->format("Y"));
                if (file_exists(ROOTDIR . "/assets/img/logo.png")) {
                    $assetLogoPath = $assetHelper->getImgPath() . "/logo.png";
                } else {
                    if (file_exists(ROOTDIR . "/assets/img/logo.jpg")) {
                        $assetLogoPath = $assetHelper->getImgPath() . "/logo.jpg";
                    } else {
                        $assetLogoPath = "";
                    }
                }
                $this->assign("assetLogoPath", $assetLogoPath);
                $this->assign("skipMainBodyContainer", $this->skipMainBodyContainer);
                $langChangeEnabled = Config\Setting::getValue("AllowLanguageChange") ? true : false;
                $this->assign("langchange", $langChangeEnabled);
                $this->assign("languagechangeenabled", $langChangeEnabled);
                $this->assign("acceptTOS", Config\Setting::getValue("EnableTOSAccept"));
                $this->assign("tosURL", Config\Setting::getValue("TermsOfService"));
            }
        }
    }

    protected function getCurrencyOptions()
    {
        $currenciesarray = Billing\Currency::all(["id", "code", "prefix", "suffix", "default"])->toArray();
        if (count($currenciesarray) == 1) {
            $currenciesarray = [];
        }
        \Menu::addContext("currencies", $currenciesarray);
        return $currenciesarray;
    }

    protected function getLanguageSwitcherHTML()
    {
        if (!Config\Setting::getValue("AllowLanguageChange")) {
            return false;
        }
        $formTitle = \Lang::trans("language");
        $formAction = rtrim(urldecode($this->getCurrentPageLinkBack()), "&?");
        $formOptions = "";
        foreach (Language\ClientLanguage::getLanguages() as $lang) {
            $selected = "";
            if ($lang == \Lang::getName()) {
                $selected = " selected=\"selected\"";
            }
            $languageName = ucfirst($lang);
            $formOptions .= "<option" . $selected . ">" . $languageName . "</option>";
        }
        return "<form method=\"post\" action=\"" . $formAction . "\" name=\"languagefrm\" id=\"languagefrm\">\n<strong>" . $formTitle . "</strong>\n<select name=\"language\" onchange=\"languagefrm.submit()\">\n" . $formOptions . "\n</select>\n</form>";
    }

    public function initPage()
    {
        global $_LANG;
        global $clientsdetails;
        $this->resetRenderedOutput();
        $this->startSmartyIfNotStarted();
        $client = NULL;
        $clientAlerts = [];
        $clientsdetails = [];
        $clientsstats = [];
        if ($this->isLoggedIn()) {
            \Menu::addContext("user", \Auth::user());
            $this->client = $client = \Auth::client();
            if ($client) {
                $legacyClient = new Client($client);
                $clientsdetails = $legacyClient->getDetails();
                if (!function_exists("getClientsDetails")) {
                    require ROOTDIR . "/includes/clientfunctions.php";
                }
                $clientsstats = getClientsStats($client->id);
                $alerts = new User\Client\AlertFactory($client);
                $clientAlerts = $alerts->build();
                \Menu::addContext("client", $client);
            }
        }
        $loggedInUser = \Auth::user();
        $this->assign("loggedin", $this->isLoggedIn());
        $this->assign("client", $client);
        $this->assign("clientsdetails", $clientsdetails);
        $this->assign("clientAlerts", $clientAlerts);
        $this->assign("clientsstats", $clientsstats);
        $this->assign("loggedinuser", $loggedInUser);
        $this->assign("phoneNumberInputStyle", (int) Config\Setting::getValue("PhoneNumberDropdown"));
        if (!isset($this->templateVariables["showEmailVerificationBanner"])) {
            $this->assign("showEmailVerificationBanner", ClientArea\User\EmailVerification::shouldShowEmailVerificationBanner());
        }
        if (!isset($this->templateVariables["showUserValidationBanner"])) {
            $userValidation = \DI::make("userValidation");
            $this->assign("showUserValidationBanner", $userValidation->shouldShowClientBanner());
            $this->assign("userValidationUrl", $loggedInUser ? $userValidation->getSubmitUrlForUser($loggedInUser) : "");
            $this->assign("userValidationHost", $userValidation->getSubmitHost());
        }
    }

    public function getSingleTPLOutput($templatepath, $templateVariables = [])
    {
        global $smartyvalues;
        $this->startSmartyIfNotStarted();
        $this->registerDefaultTPLVars();
        if (is_array($smartyvalues)) {
            foreach ($smartyvalues as $key => $value) {
                $this->assign($key, $value);
            }
        }
        foreach ($this->templateVariables as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        if (is_array($templateVariables)) {
            foreach ($templateVariables as $key => $value) {
                $this->smarty->assign($key, $value);
            }
        }
        if (substr($templatepath, 0, 1) == "/" || substr($templatepath, 0, 1) == "\\") {
            $templatecode = $this->smarty->fetch(ROOTDIR . $templatepath);
        } else {
            $clientAreaTemplatePath = \App::getClientAreaTemplate()->resolveFilePath("/" . $templatepath . ".tpl");
            $templatecode = $this->smarty->fetch($clientAreaTemplatePath);
        }
        $this->smarty->clear_all_assign();
        return $templatecode;
    }

    protected function runClientAreaOutputHook($hookName)
    {
        $hookResponses = run_hook($hookName, $this->templateVariables);
        $output = "";
        foreach ($hookResponses as $response) {
            if ($response) {
                $output .= $response . "\n";
            }
        }
        return $output;
    }

    public static function getConditionalLinks()
    {
        include_once ROOTDIR . "/includes" . DIRECTORY_SEPARATOR . "clientareafunctions.php";
        $client = \Auth::client();
        $caLinkUpdateCC = CALinkUpdateCC();
        $security = Session::get("calinkupdatesq") ?: CALinkUpdateSQ();
        $massPayEnabled = Config\Setting::getValue("EnableMassPay");
        $massPayInvoices = 0;
        if ($client && $massPayEnabled) {
            $massPayInvoices = $client->invoices()->massPay(false)->unpaid()->count();
        }
        if (!$security) {
            $twoFactor = new TwoFactorAuthentication();
            if ($twoFactor->isActiveClients() || \DI::make("remoteAuth")->getEnabledProviders()) {
                $security = true;
            }
        }
        $sso = 1 <= ApplicationLink\ApplicationLink::whereIsEnabled(1)->count();
        return ["updatecc" => $caLinkUpdateCC, "updatesq" => $security, "security" => $security, "sso" => $sso, "allowClientRegistration" => Config\Setting::getValue("AllowClientRegister"), "addfunds" => Config\Setting::getValue("AddFundsEnabled"), "masspay" => $massPayEnabled && $massPayInvoices, "affiliates" => Config\Setting::getValue("AffiliateEnabled"), "domainreg" => Config\Setting::getValue("AllowRegister"), "domaintrans" => Config\Setting::getValue("AllowTransfer"), "domainown" => Config\Setting::getValue("AllowOwnDomain"), "pmaddon" => Module\Addon\Setting::module("project_management")->where("setting", "clientenable")->pluck("value")->first()];
    }

    protected function buildBreadCrumbHtml()
    {
        $breadcrumb = [];
        foreach ($this->breadcrumb as $vals) {
            $breadcrumb[] = "<a href=\"" . $vals["link"] . "\">" . $vals["label"] . "</a>";
        }
        return implode(" > ", $breadcrumb);
    }

    public function getBreadCrumbHtml()
    {
        if ($this->breadCrumbHtml) {
            return $this->breadCrumbHtml;
        }
        return $this->buildBreadCrumbHtml();
    }

    public function setBreadCrumbHtml($breadCrumbHtml)
    {
        $this->breadCrumbHtml = $breadCrumbHtml;
        return $this;
    }

    public static function getCurrentPageLinkBack()
    {
        $currentPageLinkBack = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) . "?";
        $filterVars = ["language", "currency"];
        $filtered = array_filter($_GET, function ($k) use($filterVars) {
            return !in_array($k, $filterVars);
        }, ARRAY_FILTER_USE_KEY);
        if (!empty($filtered)) {
            $currentPageLinkBack .= http_build_query($filtered);
            $currentPageLinkBack .= "&";
        }
        return $currentPageLinkBack;
    }

    public static function isAdminMasqueradingAsClient()
    {
        $isAdmin = Session::get("adminid");
        return $isAdmin && \Auth::client();
    }

    public static function calculatePwStrengthThresholds()
    {
        $pwStrengthRequired = Config\Setting::getValue("RequiredPWStrength");
        if (is_numeric($pwStrengthRequired)) {
            $pwStrengthRequired = (int) $pwStrengthRequired;
        } else {
            $pwStrengthRequired = 50;
        }
        if ($pwStrengthRequired < 0) {
            $pwStrengthRequired = 0;
        }
        if (100 < $pwStrengthRequired) {
            $pwStrengthRequired = 100;
        }
        if (0 < $pwStrengthRequired) {
            $pwStrengthAdvised = $pwStrengthRequired + round(100 - $pwStrengthRequired) / 2;
        } else {
            $pwStrengthAdvised = 0;
        }
        return ["pwStrengthErrorThreshold" => $pwStrengthRequired, "pwStrengthWarningThreshold" => $pwStrengthAdvised];
    }

    public function outputWithoutExit()
    {
        global $licensing;
        global $smartyvalues;
        $templateFile = $this->getTemplateFile();
        if (!$templateFile) {
            exit("Missing Template File '" . $templateFile . "'");
        }
        $this->registerDefaultTPLVars();
        $cart = new OrderForm();
        $this->assign("cartitemcount", $cart->getNumItemsInCart());
        $this->assign("breadcrumb", $this->breadcrumb);
        $this->assign("breadcrumbnav", $this->getBreadCrumbHtml());
        $this->assign("currentpagelinkback", static::getCurrentPageLinkBack());
        $this->assign("setlanguage", $this->getLanguageSwitcherHTML());
        $this->assign("currencies", $this->getCurrencyOptions());
        $this->assign("activeCurrency", Billing\Currency::factoryForClientArea());
        $this->assign("twitterusername", (new Social\SocialAccounts())->get("twitter"));
        $this->assign("announcementsFbRecommend", Config\Setting::getValue("AnnouncementsFBRecommend"));
        $this->assign("condlinks", self::getConditionalLinks());
        $this->assign("templatefile", $templateFile);
        $this->assign("adminLoggedIn", (bool) Session::get("adminid"));
        $this->assign("adminMasqueradingAsClient", static::isAdminMasqueradingAsClient());
        $this->assign("supportedCardTypes", Gateways::getSupportedCardTypesForJQueryPayment());
        $networkIssuesStatuses = Database\Capsule::table("tblnetworkissues")->where("status", "!=", "Resolved")->get(["status"]);
        $this->assign("openNetworkIssueCounts", ["open" => $networkIssuesStatuses->where("status", "!=", "Scheduled")->count(), "scheduled" => $networkIssuesStatuses->where("status", "=", "Scheduled")->count()]);
        $this->assign("socialAccounts", (new Social\SocialAccounts())->getConfigured());
        $orderFormTemplateName = NULL;
        if (!empty($smartyvalues["carttpl"])) {
            $orderFormTemplateName = $smartyvalues["carttpl"];
        } else {
            if (!empty($this->templateVariables["carttpl"])) {
                $orderFormTemplateName = $this->templateVariables["carttpl"];
            }
        }
        if (!is_null($orderFormTemplateName)) {
            try {
                $orderFormTemplate = View\Template\OrderForm::find($orderFormTemplateName);
                if (is_null($orderFormTemplate)) {
                    throw new Exception("Invalid Template name");
                }
            } catch (\Throwable $e) {
                $orderFormTemplate = View\Template\OrderForm::getDefault();
            }
            foreach ($orderFormTemplate->getTemplateConfigValues() as $key => $value) {
                $this->assign($key, $value);
            }
        }
        if (is_array($smartyvalues)) {
            $smartyvalues = array_merge($smartyvalues, static::calculatePwStrengthThresholds());
            foreach ($smartyvalues as $key => $value) {
                $this->assign($key, $value);
            }
        }
        $loggedInClientFirstName = "";
        $loggedInUser = $this->templateVariables["loggedinuser"];
        if (isset($loggedInUser["firstname"])) {
            $loggedInClientFirstName = $loggedInUser["firstname"];
        }
        $conditionalLinks = static::getConditionalLinks();
        $primaryNavbar = \Menu::primaryNavbar($loggedInClientFirstName, $conditionalLinks);
        $secondaryNavbar = \Menu::secondaryNavbar($loggedInClientFirstName, $conditionalLinks);
        run_hook("ClientAreaPrimaryNavbar", $primaryNavbar);
        run_hook("ClientAreaSecondaryNavbar", $secondaryNavbar);
        run_hook("ClientAreaNavbars", NULL);
        $primarySidebar = \Menu::primarySidebar();
        $secondarySidebar = \Menu::secondarySidebar();
        run_hook("ClientAreaPrimarySidebar", [$primarySidebar], true);
        run_hook("ClientAreaSecondarySidebar", [$secondarySidebar], true);
        run_hook("ClientAreaSidebars", NULL);
        $this->assign("primaryNavbar", View\Menu\Item::sort($primaryNavbar));
        $this->assign("secondaryNavbar", View\Menu\Item::sort($secondaryNavbar));
        $this->assign("primarySidebar", View\Menu\Item::sort($primarySidebar));
        $this->assign("secondarySidebar", View\Menu\Item::sort($secondarySidebar));
        if (empty($this->templateVariables["displayTitle"])) {
            $this->templateVariables["displayTitle"] = $this->templateVariables["pagetitle"];
        }
        foreach ($this->templateVariables as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        if (isset($GLOBALS["pagelimit"])) {
            $smartyvalues["itemlimit"] = $GLOBALS["pagelimit"];
        }
        if (isset($this->templateVariables["loginpage"]) && $this->templateVariables["loginpage"] === true) {
            $pageLoginVariables = run_hook("ClientAreaPageLogin", $this->templateVariables);
            foreach ($pageLoginVariables as $loginVariables) {
                foreach ($loginVariables as $key => $value) {
                    $this->assign($key, $value);
                }
            }
        }
        $sidebarsToCleanup = [$this->templateVariables["primarySidebar"], $this->templateVariables["secondarySidebar"]];
        foreach ($sidebarsToCleanup as $sidebar) {
            if ($sidebar && $sidebar instanceof View\Menu\Item) {
                \Menu::removeEmptyChildren($sidebar);
            }
        }
        $this->runOutputHooks()->assign("headoutput", $this->runClientAreaOutputHook("ClientAreaHeadOutput"))->assign("headeroutput", $this->runClientAreaOutputHook("ClientAreaHeaderOutput"));
        $footerOutput = $this->runClientAreaOutputHook("ClientAreaFooterOutput");
        if (array_key_exists("credit_card_input", $this->templateVariables) && $this->templateVariables["credit_card_input"]) {
            $footerOutput .= $this->templateVariables["credit_card_input"];
            $this->smarty->clearAssign("credit_card_input");
        }
        $this->assign("footeroutput", $footerOutput);
        $licenseBannerHtml = $this->getLicenseBannerHtml();
        $activeTemplate = \App::getClientAreaTemplate();
        $requiredSmartyVars = ["captcha", "containerClass", "hasLinkedProvidersEnabled", "inShoppingCart", "skipMainBodyContainer", "phoneNumberInputStyle", "promoerrormessage", "productRecommendations"];
        $definedTemplateVars = $this->smarty->getTemplateVars();
        foreach ($requiredSmartyVars as $requiredSmartyVar) {
            if (!isset($definedTemplateVars[$requiredSmartyVar])) {
                $this->smarty->assign($requiredSmartyVar, NULL);
            }
        }
        if (!isset($definedTemplateVars["client"])) {
            $this->assign("client", ["companyname" => NULL, "fullName" => NULL]);
        }
        unset($requiredSmartyVars);
        unset($definedTemplateVars);
        if ($this->isWrappedWithHeaderFooter()) {
            $header_file = $this->smarty->fetch($activeTemplate->resolveFilePath("/header.tpl"));
            $footer_file = $this->smarty->fetch($activeTemplate->resolveFilePath("/footer.tpl"));
        }
        if ($this->inorderform) {
            try {
                $body_file = $this->smarty->fetch(ROOTDIR . "/templates/orderforms/" . View\Template\OrderForm::factory($templateFile . ".tpl", $orderFormTemplateName)->getName() . "/" . $templateFile . ".tpl");
            } catch (Exception\View\TemplateNotFound $e) {
                logActivity("Unable to load the " . $templateFile . ".tpl file from the " . $orderFormTemplateName . " order form template or any of its parents.");
                $body_file = "<p>" . \Lang::trans("unableToLoadShoppingCart") . "</p>";
            }
        } else {
            if ($this->insupportmodule) {
                $body_file = $this->smarty->fetch(ROOTDIR . "/templates/" . Config\Setting::getValue("SupportModule") . "/" . $templateFile . ".tpl");
            } else {
                if (substr($templateFile, 0, 1) == "/" || substr($templateFile, 0, 1) == "\\") {
                    $body_file = $this->smarty->fetch(ROOTDIR . $templateFile);
                } else {
                    $body_file = $this->smarty->fetch($activeTemplate->resolveFilePath("/" . $templateFile . ".tpl"));
                }
            }
        }
        $this->smarty->clearAllAssign();
        $copyrighttext = $licensing->getBrandingRemoval() ? "" : "<p style=\"text-align:center;\">Powered by <a href=\"https://www.whmcs.com/\" target=\"_blank\">WHMCompleteSolution</a></p>";
        if (!$this->isWrappedWithHeaderFooter()) {
            $template_output = $body_file;
        } else {
            $template_output = $header_file . PHP_EOL . $licenseBannerHtml . PHP_EOL . $body_file . PHP_EOL . $copyrighttext . PHP_EOL . $footer_file;
        }
        if (!in_array($templateFile, ["3dsecure", "forwardpage", "viewinvoice"])) {
            $template_output = preg_replace("/(<form\\W[^>]*\\bmethod=('|\"|)POST('|\"|)\\b[^>]*>)/i", "\\1\n" . generate_token(), $template_output);
            if ($this instanceof View\LinkDecoratorInterface) {
                $template_output = $this->decorateLinksInText($template_output);
            }
            $template_output = View\Asset::conditionalFontawesomeCssInclude($template_output);
        }
        echo $template_output;
        if (defined("PERFORMANCE_DEBUG")) {
            global $query_count;
            $exectime = microtime() - PERFORMANCE_STARTTIME;
            echo "<p>Performance Debug: " . $exectime . " Queries: " . $query_count . "</p>";
        }
    }

    public function output()
    {
        $this->outputWithoutExit();
        exit;
    }

    public function getOutputContent()
    {
        if (!$this->renderedOutput) {
            ob_start();
            $this->initPage();
            $this->outputWithoutExit();
            $this->renderedOutput = ob_get_clean();
        }
        return $this->renderedOutput;
    }

    public function getLicenseBannerMessage()
    {
        return \DI::make("license")->getBanner();
    }

    public function getLicenseBannerHtml()
    {
        $licenseBannerMsg = $this->getLicenseBannerMessage();
        return $licenseBannerMsg ? "<div style=\"margin:0 0 10px 0;padding:10px 35px;background-color:#ffffd2;color:#555;font-size:16px;text-align:center;\">" . $licenseBannerMsg . "</div>" : "";
    }

    public function disableHeaderFooterOutput()
    {
        $this->wrappedWithHeaderFooter = false;
        return $this;
    }

    public function isWrappedWithHeaderFooter()
    {
        return $this->wrappedWithHeaderFooter;
    }

    public function addOutputHookFunction($name)
    {
        $this->outputHooks[] = $name;
        return $this;
    }

    protected function runOutputHooks()
    {
        $hookParameters = $this->templateVariables;
        unset($hookParameters["LANG"]);
        foreach ($this->outputHooks as $hookFunction) {
            $hookResponses = run_hook($hookFunction, $hookParameters);
            foreach ($hookResponses as $hookTemplateVariables) {
                foreach ($hookTemplateVariables as $k => $v) {
                    $this->assign($k, $v);
                    $hookParameters[$k] = $v;
                }
            }
        }
        return $this;
    }

    public function skipMainBodyContainer()
    {
        $this->skipMainBodyContainer = true;
    }

    public function getUserID()
    {
        if (\Auth::client()) {
            return (int) \Auth::client()->id;
        }
        return 0;
    }
}
