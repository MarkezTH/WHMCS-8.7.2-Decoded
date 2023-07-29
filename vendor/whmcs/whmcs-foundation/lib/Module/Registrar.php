<?php

namespace WHMCS\Module;

class Registrar extends AbstractModule
{
    protected $type = self::TYPE_REGISTRAR;
    protected $domain = NULL;
    protected $function = NULL;
    private $settings = [];
    private $lastParams = NULL;

    public function __construct()
    {
        if (!function_exists("injectDomainObjectIfNecessary")) {
            include_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "registrarfunctions.php";
        }
    }

    public function getActiveModules()
    {
        return \WHMCS\Database\Capsule::table("tblregistrars")->distinct("registrar")->orderBy("registrar")->pluck("registrar")->all();
    }

    public static function factoryFromDomain(\WHMCS\Domain\Domain $domain)
    {
        $registrar = new self();
        $registrar->setDomain($domain);
        if (!$registrar->load($domain->registrarModuleName)) {
            throw new \WHMCS\Exception\Module\ModuleNotFound("Unable to load registrar module: " . $domain->registrarModuleName);
        }
        return $registrar;
    }

    public function setDomain(\WHMCS\Domain\Domain $domain)
    {
        $this->domain = $domain;
        return $this;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function hasDomain()
    {
        return !is_null($this->getDomain());
    }

    public function getDisplayName()
    {
        $DisplayName = $this->getMetaDataValue("DisplayName");
        if (!$DisplayName) {
            $configData = $this->call("getConfigArray");
            if (isset($configData["FriendlyName"]["Value"])) {
                $DisplayName = $configData["FriendlyName"]["Value"];
            } else {
                $DisplayName = ucfirst($this->getLoadedModule());
            }
        }
        return \WHMCS\Input\Sanitize::makeSafeForOutput($DisplayName);
    }

    public function getSettings()
    {
        $settings = $this->settings;
        if (!array_key_exists($this->getLoadedModule(), $settings)) {
            $settings[$this->getLoadedModule()] = [];
            $dbSettings = \WHMCS\Database\Capsule::table("tblregistrars")->select("setting", "value")->where("registrar", $this->getLoadedModule())->get()->all();
            foreach ($dbSettings as $dbSetting) {
                $settings[$this->getLoadedModule()][$dbSetting->setting] = decrypt($dbSetting->value);
            }
            $this->settings = $settings;
        }
        return $settings[$this->getLoadedModule()];
    }

    public function clearSettings()
    {
        if (array_key_exists($this->getLoadedModule(), $this->settings)) {
            unset($this->settings[$this->getLoadedModule()]);
        }
        return $this;
    }

    public function buildParams($additionalParams = [])
    {
        $params = $this->getSettings();
        if ($this->hasDomain()) {
            try {
                $domain = $this->getDomain();
                $domainObj = $domain->getDomainObject();
                $params["domainid"] = $domain->id;
                $params["domainname"] = $domain->domain;
                $params["domain"] = $domain->domain;
                $params["domain_punycode"] = $domain->domainPunycode;
                $params["is_idn"] = $params["domainname"] !== $params["domain_punycode"];
                $params["sld"] = $domainObj->getSecondLevel();
                $params["sld_punycode"] = $domainObj->getPunycodeSecondLevel();
                $params["tld"] = $domainObj->getTopLevel();
                $params["tld_punycode"] = $domainObj->getPunycodeTopLevel();
                $params["regtype"] = $domain->type;
                $params["regperiod"] = $domain->registrationPeriod;
                $params["registrar"] = $domain->registrarModuleName;
                $params["dnsmanagement"] = $domain->hasDnsManagement;
                $params["emailforwarding"] = $domain->hasEmailForwarding;
                $params["idprotection"] = $domain->hasIdProtection;
                $params["premiumEnabled"] = (bool) (int) \WHMCS\Config\Setting::getValue("PremiumDomains");
                $params["userid"] = $domain->clientId;
                $params["idnLanguage"] = $params["is_idn"] ? $domain->extra()->whereName("idnLanguage")->value("value") : "";
                $this->buildFunctionSpecificParams($domain, $params, $additionalParams);
                $params["domainObj"] = $domainObj;
            } catch (\Exception $e) {
                throw $e;
            }
        } else {
            if ((!isset($params["domainObj"]) || !is_object($params["domainObj"])) && !empty($params["sld"])) {
                $params["domainObj"] = new \WHMCS\Domains\Domain(sprintf("%s.%s", $params["sld"], $params["tld"]));
            }
        }
        if (is_array($additionalParams)) {
            $params = array_merge($params, $additionalParams);
        }
        $params["original"] = $params;
        if (isset($params["contactdetails"])) {
            $params["contactdetails"] = foreignChrReplace($params["contactdetails"]);
        }
        if (isset($params["original"]["original"])) {
            if (is_array($params["original"]["original"])) {
                $params["original"] = array_merge($params["original"], $params["original"]["original"]);
            }
            unset($params["original"]["original"]);
        }
        $this->lastParams = $params;
        return $params;
    }

    public function getLastParams()
    {
        return $this->lastParams;
    }

    public function call($function, $additionalParams = [])
    {
        $noDomainIdRequirement = ["MetaData", "config_validate", "getConfigArray", "CheckAvailability", "GetDomainSuggestions", "DomainSuggestionOptions", "AdditionalDomainFields", "GetTldPricing"];
        if (!in_array($function, $noDomainIdRequirement) && !$this->hasDomain()) {
            return ["error" => "Domain is required"];
        }
        try {
            $this->function = $function;
            $params = $this->buildParams($additionalParams);
            $hookResults = run_hook("PreRegistrar" . $function, ["params" => $params]);
            if (\HookMgr::processResults($this->getLoadedModule(), $function, $hookResults)) {
                return true;
            }
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
        $results = parent::call($function, $params);
        $registrar = $params["registrar"] ?? NULL;
        $functionExists = $functionSuccessful = false;
        $noArrFunctions = ["GetRegistrarLock", "CheckAvailability", "GetDomainSuggestions", "GetDomainInformation", "ClientArea", "GetTldPricing"];
        $queueFunctions = ["IDProtectToggle", "RegisterDomain", "RenewDomain", "TransferDomain"];
        $resultsForHookInput = $results;
        if ($results !== parent::FUNCTIONDOESNTEXIST) {
            $functionExists = true;
            if (!is_array($results) && !in_array($function, $noArrFunctions)) {
                $results = [];
            }
            if (!is_array($results) || empty($results["error"])) {
                if (in_array($function, $queueFunctions)) {
                    Queue::resolve("domain", $params["domainid"], $registrar, $function);
                }
                $functionSuccessful = true;
            } else {
                if (in_array($function, $queueFunctions)) {
                    Queue::add("domain", $params["domainid"], $registrar, $function, $results["error"]);
                }
            }
        } else {
            $resultsForHookInput = ["na" => true];
        }
        $vars = ["params" => $params, "results" => $resultsForHookInput, "functionExists" => $functionExists, "functionSuccessful" => $functionSuccessful];
        $hookResults = run_hook("AfterRegistrar" . $function, $vars);
        try {
            if (\HookMgr::processResults($registrar, $function, $hookResults)) {
                return [];
            }
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
        return $results;
    }

    public function isActivated()
    {
        return (bool) RegistrarSetting::registrar($this->getLoadedModule())->first();
    }

    public function activate($parameters = [])
    {
        $this->deactivate();
        $registrarSetting = new RegistrarSetting();
        $registrarSetting->registrar = $this->getLoadedModule();
        $registrarSetting->setting = "Username";
        $registrarSetting->value = "";
        $registrarSetting->save();
        $moduleSettings = $this->call("getConfigArray");
        $settingsToSave = ["Username" => ""];
        foreach ($moduleSettings as $key => $values) {
            if ($values["Type"] == "yesno" && !empty($values["Default"]) && $values["Default"] !== "off" && $values["Default"] !== "disabled") {
                $settingsToSave[$key] = $values["Default"];
            }
        }
        $logChanges = false;
        if (0 < count($parameters)) {
            foreach ($parameters as $key => $value) {
                if (array_key_exists($key, $moduleSettings)) {
                    $settingsToSave[$key] = $value;
                    $logChanges = true;
                }
            }
        }
        logAdminActivity("Registrar Activated: '" . $this->getDisplayName() . "'");
        $this->saveSettings($settingsToSave, $logChanges);
        return $this;
    }

    public function deactivate($parameters = [])
    {
        RegistrarSetting::registrar($this->getLoadedModule())->delete();
        $this->clearSettings();
        return $this;
    }

    public function saveSettings($newSettings = [], $logChanges = true)
    {
        $moduleName = $this->getLoadedModule();
        $moduleSettings = $this->call("getConfigArray");
        $previousSettings = $this->getSettings();
        $settingsToSave = [];
        $changes = [];
        foreach ($moduleSettings as $key => $values) {
            if ($values["Type"] != "System") {
                if (isset($newSettings[$key])) {
                    $settingsToSave[$key] = $newSettings[$key];
                } else {
                    if ($values["Type"] == "yesno") {
                        $settingsToSave[$key] = "";
                    } else {
                        if (isset($values["Default"])) {
                            $settingsToSave[$key] = $values["Default"];
                        }
                    }
                }
                if ($values["Type"] == "password" && isset($newSettings[$key]) && isset($previousSettings[$key])) {
                    $updatedPassword = interpretMaskedPasswordChangeForStorage($newSettings[$key], $previousSettings[$key]);
                    if ($updatedPassword === false) {
                        $settingsToSave[$key] = $previousSettings[$key];
                    } else {
                        $changes[] = "'" . $key . "' value modified";
                    }
                }
                if ($values["Type"] == "yesno") {
                    if (!empty($settingsToSave[$key]) && $settingsToSave[$key] !== "off" && $settingsToSave[$key] !== "disabled") {
                        $settingsToSave[$key] = "on";
                    } else {
                        $settingsToSave[$key] = "";
                    }
                    if (empty($previousSettings[$key])) {
                        $previousSettings[$key] = "";
                    }
                    if ($previousSettings[$key] != $settingsToSave[$key]) {
                        $newSetting = $settingsToSave[$key] ?: "off";
                        $oldSetting = $previousSettings[$key] ?: "off";
                        $changes[] = "'" . $key . "' changed from '" . $oldSetting . "' to '" . $newSetting . "'";
                    }
                } else {
                    if (empty($settingsToSave[$key])) {
                        $settingsToSave[$key] = "";
                    }
                    if (empty($previousSettings[$key])) {
                        $previousSettings[$key] = "";
                    }
                    if ($values["Type"] != "password") {
                        if (!$previousSettings[$key] && $settingsToSave[$key]) {
                            $changes[] = "'" . $key . "' set to '" . $settingsToSave[$key] . "'";
                        } else {
                            if ($previousSettings[$key] != $settingsToSave[$key]) {
                                $changes[] = "'" . $key . "' changed from '" . $previousSettings[$key] . "' to '" . $settingsToSave[$key] . "'";
                            }
                        }
                    }
                }
            }
        }
        foreach ($settingsToSave as $setting => $value) {
            $model = RegistrarSetting::registrar($moduleName)->setting($setting)->first();
            if ($model) {
                $model->value = $value;
            } else {
                $model = new RegistrarSetting();
                $model->registrar = $moduleName;
                $model->setting = $setting;
                $model->value = \WHMCS\Input\Sanitize::decode(trim($value));
            }
            $model->save();
        }
        if ($changes && $logChanges) {
            logAdminActivity("Domain Registrar Modified: '" . $this->getDisplayName() . "' - " . implode(". ", $changes) . ".");
        }
        unset($this->settings[$this->getLoadedModule()]);
        return $this;
    }

    public function getConfiguration()
    {
        return $this->call("getConfigArray");
    }

    public function updateConfiguration($parameters = [])
    {
        if (!$this->isActivated()) {
            throw new \WHMCS\Exception\Module\NotActivated("Module not active");
        }
        $moduleSettings = $this->call("getConfigArray");
        $settingsToSave = [];
        $logChanges = false;
        if (0 < count($parameters)) {
            foreach ($parameters as $key => $value) {
                if (array_key_exists($key, $moduleSettings)) {
                    $settingsToSave[$key] = $value;
                    $logChanges = true;
                }
            }
        }
        if (0 < count($settingsToSave)) {
            $this->saveSettings($settingsToSave, $logChanges);
        }
    }

    protected function buildFunctionSpecificParams(\WHMCS\Domain\Domain $domain, &$params, $additionalParams)
    {
        $premiumEnabled = (bool) (int) \WHMCS\Config\Setting::getValue("PremiumDomains");
        if (in_array($this->function, ["RegisterDomain", "TransferDomain", "SaveContactDetails", "TransferSync"])) {
            if (!function_exists("getClientsDetails")) {
                require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
            }
            $userId = $domain->clientId;
            $contactId = 0;
            if ($domain->order) {
                $contactId = $domain->order->contactId;
            }
            $clientsDetails = getClientsDetails($userId, $contactId);
            $clientsDetails["state"] = $clientsDetails["statecode"];
            $clientsDetails["fullphonenumber"] = $clientsDetails["phonenumberformatted"];
            $clientsDetails["phone-cc"] = $clientsDetails["phonecc"];
            $params["original"] = $clientsDetails;
            $clientsDetails = foreignChrReplace($clientsDetails);
            if ($premiumEnabled) {
                $registrarCostPrice = json_decode($domain->extra()->whereName("registrarCostPrice")->value("value"), true);
                if ($registrarCostPrice && is_numeric($registrarCostPrice)) {
                    $params["premiumCost"] = $registrarCostPrice;
                } else {
                    if ($registrarCostPrice && is_array($registrarCostPrice) && array_key_exists("price", $registrarCostPrice)) {
                        $params["premiumCost"] = $registrarCostPrice["price"];
                    }
                }
            }
            if (\WHMCS\Config\Setting::getValue("RegistrarAdminUseClientDetails") == "on") {
                $params["adminfirstname"] = $clientsDetails["firstname"];
                $params["adminlastname"] = $clientsDetails["lastname"];
                $params["admincompanyname"] = $clientsDetails["companyname"];
                $params["adminemail"] = $clientsDetails["email"];
                $params["adminaddress1"] = $clientsDetails["address1"];
                $params["adminaddress2"] = $clientsDetails["address2"];
                $params["admincity"] = $clientsDetails["city"];
                $params["adminfullstate"] = $clientsDetails["fullstate"];
                $params["adminstate"] = $clientsDetails["state"];
                $params["adminpostcode"] = $clientsDetails["postcode"];
                $params["admincountry"] = $clientsDetails["country"];
                $params["adminphonenumber"] = $clientsDetails["phonenumber"];
                $params["adminphonecc"] = $clientsDetails["phonecc"];
                $params["adminfullphonenumber"] = $clientsDetails["phonenumberformatted"];
            } else {
                $params["adminfirstname"] = \WHMCS\Config\Setting::getValue("RegistrarAdminFirstName");
                $params["adminlastname"] = \WHMCS\Config\Setting::getValue("RegistrarAdminLastName");
                $params["admincompanyname"] = \WHMCS\Config\Setting::getValue("RegistrarAdminCompanyName");
                $params["adminemail"] = \WHMCS\Config\Setting::getValue("RegistrarAdminEmailAddress");
                $params["adminaddress1"] = \WHMCS\Config\Setting::getValue("RegistrarAdminAddress1");
                $params["adminaddress2"] = \WHMCS\Config\Setting::getValue("RegistrarAdminAddress2");
                $params["admincity"] = \WHMCS\Config\Setting::getValue("RegistrarAdminCity");
                $params["adminfullstate"] = \WHMCS\Config\Setting::getValue("RegistrarAdminStateProvince");
                $params["adminstate"] = convertStateToCode(\WHMCS\Config\Setting::getValue("RegistrarAdminStateProvince"), \WHMCS\Config\Setting::getValue("RegistrarAdminCountry"));
                if ($params["tld"] == "ca" || substr($params["tld"], -3) == ".ca") {
                    $params["adminstate"] = convertToCiraCode($params["adminstate"]);
                }
                $params["adminpostcode"] = \WHMCS\Config\Setting::getValue("RegistrarAdminPostalCode");
                $params["admincountry"] = \WHMCS\Config\Setting::getValue("RegistrarAdminCountry");
                $phoneDetails = \WHMCS\Client::formatPhoneNumber(["phonenumber" => \WHMCS\Config\Setting::getValue("RegistrarAdminPhone"), "countrycode" => \WHMCS\Config\Setting::getValue("RegistrarAdminCountry")]);
                $params["adminphonenumber"] = $phoneDetails["phonenumber"];
                $params["adminfullphonenumber"] = $phoneDetails["phonenumberformatted"];
                $params["adminphonecc"] = $phoneDetails["phonecc"];
            }
            $nameservers = $domain->getBestNameserversForNewOrder();
            for ($i = 1; $i <= 5; $i++) {
                $params["ns" . $i] = isset($nameservers[$i - 1]) ? $nameservers[$i - 1] : "";
            }
            $params["additionalfields"] = (new \WHMCS\Domains\AdditionalFields())->setDomainType($domain->type)->getFieldValuesFromDatabase($domain->id);
            $params = array_merge($params, $clientsDetails);
            if ($this->function == "TransferDomain") {
                $eppCode = "";
                if (array_key_exists("transfersecret", $additionalParams)) {
                    $eppCode = $additionalParams["transfersecret"];
                } else {
                    if ($domain->order) {
                        $eppCode = $domain->order->getEppCodeByDomain($domain->domain);
                    }
                }
                $params["eppcode"] = $eppCode;
                $params["transfersecret"] = $params["eppcode"];
            }
        } else {
            if ($this->function == "RenewDomain") {
                $params["isInGracePeriod"] = $domain->status == \WHMCS\Domain\Status::GRACE;
                $params["isInRedemptionGracePeriod"] = $domain->status == \WHMCS\Domain\Status::REDEMPTION;
                if ($premiumEnabled && $domain->isPremium) {
                    $params["premiumCost"] = $domain->extra()->whereName("registrarRenewalCostPrice")->value("value");
                }
                $params["expiryDate"] = $domain->expiryDate;
            }
        }
    }

    public function load($module, $globalVariable = NULL)
    {
        $this->builtParams = [];
        return parent::load($module);
    }

    public function getAdminActivationForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configregistrars.php")->setMethod(\WHMCS\View\Form::METHOD_POST)->setParameters(["token" => generate_token("plain"), "action" => "activate", "module" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.activate"))];
    }

    public function getAdminManagementForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configregistrars.php#" . $moduleName)->setMethod(\WHMCS\View\Form::METHOD_GET)->setSubmitLabel(\AdminLang::trans("global.manage"))];
    }

    public function validateConfiguration($newSettings)
    {
        $this->call("config_validate", $this->prepareSettingsToValidate($newSettings));
    }
}
