<?php

namespace WHMCS\Module;

class Gateway extends AbstractModule
{
    protected $type = self::TYPE_GATEWAY;
    protected $usesDirectories = false;
    protected $activeList = "";
    protected $legacyGatewayParams = [];
    const WORKFLOW_ASSISTED = "assisted";
    const WORKFLOW_REMOTE = "remote";
    const WORKFLOW_NOLOCALCARDINPUT = "nolocalcardinput";
    const WORKFLOW_TOKEN = "token";
    const WORKFLOW_MERCHANT = "merchant";
    const WORKFLOW_THIRDPARTY = "thirdparty";
    const NONLOCAL_WORKFLOW_TYPES = NULL;
    const GATEWAY_BANK = "Bank";
    const GATEWAY_CREDIT_CARD = "CC";
    const GATEWAY_THIRD_PARTY = "Invoices";
    const PROCESSING_OFFLINE = "Offline";
    const PROCESSING_ONLINE = "Online";

    public function __construct()
    {
        $whmcs = \WHMCS\Application::getInstance();
        $this->addParam("companyname", $whmcs->get_config("CompanyName"));
        $this->addParam("systemurl", $whmcs->getSystemURL());
        $this->addParam("langpaynow", $whmcs->get_lang("invoicespaynow"));
        if (!function_exists("getGatewaysArray")) {
            $whmcs->load_function("gateway");
        }
        if (!function_exists("addInvoicePayment")) {
            $whmcs->load_function("invoice");
        }
    }

    public function getActiveModules()
    {
        return $this->getActiveGateways();
    }

    public function getList($type = "")
    {
        $modules = parent::getList($type);
        foreach ($modules as $key => $module) {
            if ($module == "index") {
                unset($modules[$key]);
            }
        }
        return $modules;
    }

    public static function factory($name)
    {
        $gateway = new Gateway();
        if (!$gateway->load($name)) {
            throw new \WHMCS\Exception\Fatal("Module Not Found");
        }
        if (!$gateway->isLoadedModuleActive()) {
            throw new \WHMCS\Exception\Fatal("Module Not Activated");
        }
        return $gateway;
    }

    public function getActiveGateways()
    {
        if (is_array($this->activeList)) {
            return $this->activeList;
        }
        $this->activeList = array_filter(GatewaySetting::getActiveGatewayModules(), function ($gateway) {
            return \WHMCS\Gateways::isNameValid($gateway);
        });
        return $this->activeList;
    }

    public function getMerchantGateways()
    {
        return GatewaySetting::getActiveGatewayModules(self::GATEWAY_CREDIT_CARD);
    }

    public function isActiveGateway($gateway)
    {
        $gateways = $this->getActiveGateways();
        return in_array($gateway, $gateways);
    }

    public function getDisplayName()
    {
        if ($this->getLoadedModule()) {
            $name = (string) $this->getParam("name");
            if ($this->functionExists("get_display_name")) {
                $currency = \Currency::factoryForClientArea();
                $params = $this->getParams();
                $params["name"] = $name;
                $params["currency"] = $currency;
                $name = $this->call("get_display_name", $params);
            }
            return $name;
        }
        $paymentGateways = new \WHMCS\Gateways();
        return $paymentGateways->getDisplayName($this->loadedmodule);
    }

    public function getAvailableGateways($invoiceid = "")
    {
        $validGateways = GatewaySetting::getVisibleGatewayFriendlyNames();
        if ($invoiceid) {
            $invoiceid = (int) $invoiceid;
            $invoicegateway = get_query_val("tblinvoices", "paymentmethod", ["id" => $invoiceid]);
            $disabledgateways = [];
            $result = select_query("tblinvoiceitems", "", ["type" => "Hosting", "invoiceid" => $invoiceid]);
            while ($data = mysql_fetch_assoc($result)) {
                $relid = $data["relid"];
                if ($relid) {
                    $result2 = full_query("SELECT pg.disabledgateways AS disabled FROM tblhosting h LEFT JOIN tblproducts p on h.packageid = p.id LEFT JOIN tblproductgroups pg on p.gid = pg.id where h.id = " . (int) $relid);
                    $data2 = mysql_fetch_assoc($result2);
                    $gateways = explode(",", $data2["disabled"]);
                    foreach ($gateways as $gateway) {
                        if (array_key_exists($gateway, $validGateways) && $gateway != $invoicegateway) {
                            unset($validGateways[$gateway]);
                        }
                    }
                }
            }
        }
        return $validGateways;
    }

    public function getFirstAvailableGateway()
    {
        $gateways = $this->getAvailableGateways();
        return key($gateways);
    }

    public function load($module, $globalVariable = NULL)
    {
        global $GATEWAYMODULE;
        $GATEWAYMODULE = [];
        $licensing = \DI::make("license");
        $module = \App::sanitize("0-9a-z_-", $module);
        $modulePath = $this->getModulePath($module);
        \Log::debug("Attempting to load module", ["type" => $this->getType(), "module" => $module, "path" => $modulePath]);
        $loadStatus = false;
        if (file_exists($modulePath)) {
            if (!is_null($globalVariable)) {
                ${$globalVariable} =& ${$globalVariable};
            }
            if (!function_exists($module . "_config") && !function_exists($module . "_link") && !function_exists($module . "_capture")) {
                require_once $modulePath;
            }
            $this->setLoadedModule($module);
            $this->setMetaData($this->getMetaData());
            $loadStatus = true;
        }
        $this->legacyGatewayParams[$module] = $GATEWAYMODULE;
        if ($loadStatus) {
            $this->loadSettings();
        }
        $this->legacyGatewayFields = $GATEWAYMODULE;
        return $loadStatus;
    }

    public function loadSettings()
    {
        $gateway = $this->getLoadedModule();
        $settings = ["paymentmethod" => $gateway];
        foreach (GatewaySetting::getForGateway($gateway) as $setting => $value) {
            $this->addParam($setting, $value);
            $settings[$setting] = $value;
        }
        return $settings;
    }

    public function isLoadedModuleActive()
    {
        return $this->getParam("type") ? true : false;
    }

    public function call($function, $params = [])
    {
        $this->addParam("paymentmethod", $this->getLoadedModule());
        $clientBeforeCall = NULL;
        if (array_key_exists("clientdetails", $params)) {
            $userId = $params["clientdetails"]["userid"];
            $clientBeforeCall = \WHMCS\User\Client::find($userId);
        }
        if (!$clientBeforeCall && \Auth::client()) {
            $clientBeforeCall = \Auth::client();
        }
        $result = parent::call($function, $params);
        if ($clientBeforeCall && in_array($function, ["capture", "3dsecure", "post_checkout"])) {
            $this->processClientAfterCall($clientBeforeCall, $params);
        }
        return $result;
    }

    private function migrateUpdatedCardData(\WHMCS\User\Client $client, \WHMCS\Payment\PayMethod\Model $payMethod)
    {
        if ($payMethod->payment instanceof \WHMCS\Payment\Contracts\CreditCardDetailsInterface) {
            $legacyCardData = getClientDefaultCardDetails($client->id, "forceLegacy");
            $payment = $payMethod->payment;
            if ($legacyCardData["cardnum"]) {
                $payment->setCardNumber($legacyCardData["cardnum"]);
            }
            if ($legacyCardData["cardlastfour"]) {
                $payment->setLastFour($legacyCardData["cardlastfour"]);
            }
            if ($legacyCardData["cardtype"]) {
                $payment->setCardType($legacyCardData["cardtype"]);
            }
            if ($legacyCardData["startdate"]) {
                $payment->setStartDate(\WHMCS\Carbon::createFromCcInput($legacyCardData["startdate"]));
            }
            if ($legacyCardData["expdate"]) {
                $payment->setExpiryDate(\WHMCS\Carbon::createFromCcInput($legacyCardData["expdate"]));
            }
            if ($legacyCardData["issuenumber"]) {
                $payment->setIssueNumber($legacyCardData["issuenumber"]);
            }
            $payment->save();
            $client->markCardDetailsAsMigrated();
        }
    }

    private function processClientAfterCall(\WHMCS\User\Client $clientBeforeCall, $callParams)
    {
        $clientAfterCall = $clientBeforeCall->fresh();
        $invoiceModel = \WHMCS\Billing\Invoice::find($callParams["invoiceid"]);
        if (!$invoiceModel) {
            return NULL;
        }
        if (!$invoiceModel->payMethod || $invoiceModel->payMethod->trashed()) {
            return NULL;
        }
        if ($clientAfterCall->paymentGatewayToken !== $clientBeforeCall->paymentGatewayToken && $invoiceModel->payMethod->payment instanceof \WHMCS\Payment\Contracts\RemoteTokenDetailsInterface) {
            if ($clientAfterCall->paymentGatewayToken) {
                $payment = $invoiceModel->payMethod->payment;
                $payment->setRemoteToken($clientAfterCall->paymentGatewayToken);
                $payment->save();
                $clientAfterCall->paymentGatewayToken = "";
                $clientAfterCall->save();
            } else {
                $invoiceModel->payMethod->delete();
            }
        }
        if ($clientAfterCall->creditCardType !== $clientBeforeCall->creditCardType) {
            if (!empty($clientAfterCall->creditCardType)) {
                $this->migrateUpdatedCardData($clientAfterCall, $invoiceModel->payMethod);
            } else {
                if (!$clientAfterCall->paymentGatewayToken) {
                    $invoiceModel->payMethod->delete();
                }
            }
        }
    }

    public function activate($parameters = [])
    {
        if ($this->isLoadedModuleActive()) {
            throw new \WHMCS\Exception\Module\NotActivated("Module already active");
        }
        $lastOrder = GatewaySetting::getOrderForGateway($this->getLoadedModule());
        $configData = $this->getConfiguration();
        $displayName = $configData["FriendlyName"]["Value"];
        $gatewayType = $this->getMetaDataValue("gatewayType");
        if (!in_array($gatewayType, [self::GATEWAY_BANK, self::GATEWAY_CREDIT_CARD, self::GATEWAY_THIRD_PARTY])) {
            $gatewayType = self::GATEWAY_THIRD_PARTY;
            if ($this->functionExists("capture")) {
                $gatewayType = self::GATEWAY_CREDIT_CARD;
            }
        }
        $this->saveConfigValue("name", $displayName, $lastOrder);
        $this->saveConfigValue("type", $gatewayType);
        $this->saveConfigValue("visible", "on");
        if (!empty($configData["RemoteStorage"]["Value"]) && $configData["RemoteStorage"]["Value"] === true) {
            $this->saveConfigValue("remotestorage", "1");
        }
        $hookFile = $this->getModuleDirectory($this->getLoadedModule()) . DIRECTORY_SEPARATOR . "hooks.php";
        if (file_exists($hookFile)) {
            $hooks = array_filter(explode(",", \WHMCS\Config\Setting::getValue("GatewayModuleHooks")));
            if (!in_array($this->getLoadedModule(), $hooks)) {
                $hooks[] = $this->getLoadedModule();
            }
            \WHMCS\Config\Setting::setValue("GatewayModuleHooks", implode(",", $hooks));
        }
        if (!function_exists("logAdminActivity")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "adminfunctions.php";
        }
        logAdminActivity("Gateway Module Activated: '" . $displayName . "'");
        $this->load($this->getLoadedModule());
        $this->updateConfiguration($parameters);
        return true;
    }

    public function deactivate($parameters = [])
    {
        $gateways = $this->getList();
        $oldGateway = !$this->getLoadedModule() ? $parameters["oldGateway"] : $this->getLoadedModule();
        if (!$this->isLoadedModuleActive() && in_array($oldGateway, $gateways)) {
            throw new \WHMCS\Exception\Module\NotActivated("Module not active");
        }
        if (empty($parameters["newGateway"])) {
            throw new \WHMCS\Exception\Module\NotServicable("New Module Required");
        }
        if ($oldGateway != $parameters["newGateway"]) {
            $newGatewayName = $parameters["newGatewayName"] ?? $parameters["newGateway"];
            if ($this->functionExists("deactivate")) {
                try {
                    $this->call("deactivate");
                } catch (\Exception $e) {
                    logActivity("An Error Occurred on " . $this->getDisplayName() . " Deactivate: " . $e->getMessage());
                }
            }
            $tables = ["tblaccounts", "tbldomains", "tblhosting", "tblhostingaddons", "tblinvoices", "tblorders"];
            foreach ($tables as $table) {
                $field = "paymentmethod";
                if ($table == "tblaccounts") {
                    $field = "gateway";
                }
                \WHMCS\Database\Capsule::table($table)->where($field, $oldGateway)->update([$field => $parameters["newGateway"]]);
            }
            $forceDelete = false;
            try {
                $configData = $this->getConfiguration();
                $displayName = $configData["FriendlyName"]["Value"];
            } catch (\Exception $e) {
                $displayName = $oldGateway;
                $forceDelete = true;
            }
            GatewaySetting::gateway($oldGateway)->delete();
            $hooks = array_filter(explode(",", \WHMCS\Config\Setting::getValue("GatewayModuleHooks")));
            if (in_array($oldGateway, $hooks)) {
                $hooks = array_flip($hooks);
                unset($hooks[$oldGateway]);
                $hooks = array_flip($hooks);
                \WHMCS\Config\Setting::setValue("GatewayModuleHooks", implode(",", $hooks));
            }
            if (!function_exists("logAdminActivity")) {
                require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "adminfunctions.php";
            }
            $logEntry = "Gateway Module ";
            if ($forceDelete) {
                $logEntry .= "Forcefully ";
            }
            $logEntry .= "Deactivated: " . $displayName . " to " . $newGatewayName;
            logAdminActivity($logEntry);
            return true;
        } else {
            throw new \WHMCS\Exception\Module\NotImplemented("Invalid New Module");
        }
    }

    protected function saveConfigValue($setting, $value, $order = 0)
    {
        GatewaySetting::setValue($this->getLoadedModule(), $setting, $value, $order);
        $this->addParam($setting, $value);
    }

    public function getConfiguration()
    {
        if (!$this->getLoadedModule()) {
            throw new \WHMCS\Exception("No module loaded to fetch configuration for");
        }
        if ($this->functionExists("config")) {
            return $this->call("config");
        }
        if ($this->functionExists("activate")) {
            $module = $this->getLoadedModule();
            $legacyDisplayName = isset($this->legacyGatewayParams[$module][$module . "visiblename"]) ? $this->legacyGatewayParams[$module][$module . "visiblename"] : ucfirst($module);
            $legacyNotes = isset($this->legacyGatewayParams[$module][$module . "notes"]) ? $this->legacyGatewayParams[$module][$module . "notes"] : "";
            $this->call("activate");
            $response = array_merge(["FriendlyName" => ["Type" => "System", "Value" => $legacyDisplayName]], defineGatewayFieldStorage(true));
            if (!empty($legacyNotes)) {
                $response["UsageNotes"] = ["Type" => "System", "Value" => $legacyNotes];
            }
            return $response;
        }
        throw new \WHMCS\Exception\Module\NotImplemented();
    }

    public function updateConfiguration($parameters = [])
    {
        if (!$this->isLoadedModuleActive()) {
            throw new \WHMCS\Exception\Module\NotActivated("Module not active");
        }
        if (0 < count($parameters)) {
            $configData = $this->getConfiguration();
            $displayName = $configData["FriendlyName"]["Value"];
            foreach ($parameters as $key => $value) {
                if (array_key_exists($key, $configData)) {
                    $this->saveConfigValue($key, $value);
                }
            }
            if (!function_exists("logAdminActivity")) {
                require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "adminfunctions.php";
            }
            logAdminActivity("Gateway Module Configuration Updated: '" . $displayName . "'");
        }
    }

    public function getAdminActivationForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configgateways.php")->setMethod(\WHMCS\View\Form::METHOD_POST)->setParameters(["token" => generate_token("plain"), "action" => "activate", "gateway" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.activate"))];
    }

    public function getAdminManagementForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configgateways.php")->setMethod(\WHMCS\View\Form::METHOD_POST)->setParameters(["manage" => true, "gateway" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.manage"))];
    }

    public function getOnBoardingRedirectHtml()
    {
        if (!$this->getMetaDataValue("apiOnboarding")) {
            return "";
        }
        $redirectUrl = $this->getMetaDataValue("apiOnboardingRedirectUrl");
        $callbackPath = $this->getMetaDataValue("apiOnboardingCallbackPath");
        $admin = \WHMCS\User\Admin::getAuthenticatedUser();
        $params = ["firstname" => $admin->firstName, "lastname" => $admin->lastName, "companyname" => \WHMCS\Config\Setting::getValue("CompanyName"), "email" => $admin->email, "whmcs_callback_url" => \App::getSystemUrl() . $callbackPath, "return_url" => fqdnRoutePath("admin-setup-payments-gateways-onboarding-return")];
        $buttonValue = "Click here if not redirected automatically";
        $output = "<html><head><title>Redirecting...</title></head><body onload=\"document.onboardfrm.submit()\"><p>Please wait while you are redirected...</p><form method=\"post\" action=\"" . $redirectUrl . "\" name=\"onboardfrm\">";
        foreach ($params as $key => $value) {
            $output .= "<input type=\"hidden\" name=\"" . $key . "\" value=\"" . \WHMCS\Input\Sanitize::makeSafeForOutput($value) . "\">";
        }
        $output .= "<input type=\"submit\" value=\"" . $buttonValue . "\" class=\"btn btn-default\">" . "</form>" . "</body></html>";
        return $output;
    }

    public function getWorkflowType()
    {
        if ($this->getMetaDataValue("TokenWorkflow") === true) {
            return static::WORKFLOW_TOKEN;
        }
        if ($this->functionExists("credit_card_input")) {
            return static::WORKFLOW_ASSISTED;
        }
        if ($this->functionExists("remoteinput")) {
            return static::WORKFLOW_REMOTE;
        }
        if ($this->functionExists("nolocalcc")) {
            return static::WORKFLOW_NOLOCALCARDINPUT;
        }
        if ($this->functionExists("storeremote")) {
            return static::WORKFLOW_TOKEN;
        }
        if ($this->functionExists("capture") || $this->getMetaDataValue("gatewayType") === self::GATEWAY_CREDIT_CARD && $this->getMetaDataValue("processingType") === self::PROCESSING_OFFLINE) {
            return static::WORKFLOW_MERCHANT;
        }
        return static::WORKFLOW_THIRDPARTY;
    }

    public function isTokenised()
    {
        $tokenizedWorkflows = [static::WORKFLOW_ASSISTED, static::WORKFLOW_REMOTE, static::WORKFLOW_NOLOCALCARDINPUT, static::WORKFLOW_TOKEN];
        return in_array($this->getWorkflowType(), $tokenizedWorkflows);
    }

    public function supportsLocalInput()
    {
        return $this->getWorkflowType() != self::WORKFLOW_NOLOCALCARDINPUT;
    }

    public function isRemote()
    {
        return $this->functionExists("storeremote") || $this->functionExists("remoteinput");
    }

    public function requiresLocalInput()
    {
        return $this->supportsLocalInput() || $this->isTokenised() && !$this->isRemote();
    }

    public function supportsLocalBankDetails()
    {
        return $this->functionExists("localbankdetails");
    }

    public function supportsAutoCapture()
    {
        return $this->functionExists("capture") || $this->getProcessingType() == self::PROCESSING_OFFLINE;
    }

    public function getBaseGatewayType()
    {
        $type = "3rdparty";
        if ($this->supportsAutoCapture()) {
            $type = "creditcard";
        }
        if ($this->supportsLocalBankDetails()) {
            $type = "bankaccount";
        }
        return $type;
    }

    public function getProcessingType()
    {
        if ($this->isMetaDataValueSet("processingType")) {
            return $this->getMetaDataValue("processingType");
        }
        return NULL;
    }

    public function isSupportedCurrency($currencyCode)
    {
        if (!$this->isMetaDataValueSet("supportedCurrencies") || in_array($currencyCode, $this->getMetaDataValue("supportedCurrencies"))) {
            return true;
        }
        return false;
    }
}
