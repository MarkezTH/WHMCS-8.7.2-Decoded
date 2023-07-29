<?php

namespace WHMCS;

class Gateways
{
    private $modulename = "";
    private static $gateways = NULL;
    private $displaynames = [];
    const CC_EXPIRY_MAX_YEARS = 20;

    public function getDisplayNames()
    {
        $this->displaynames = Module\GatewaySetting::getActiveGatewayFriendlyNames();
        foreach (array_keys($this->displaynames) as $gateway) {
            try {
                $gatewayInterface = Module\Gateway::factory($gateway);
                $this->displaynames[$gateway] = $gatewayInterface->getDisplayName();
            } catch (\Exception $e) {
            }
        }
        return $this->displaynames;
    }

    public function getDisplayName($gateway)
    {
        if (empty($this->displaynames)) {
            $this->getDisplayNames();
        }
        return array_key_exists($gateway, $this->displaynames) ? $this->displaynames[$gateway] : $gateway;
    }

    public static function isNameValid($gateway)
    {
        if (!is_string($gateway) || empty($gateway)) {
            return false;
        }
        if (!ctype_alnum(str_replace(["_", "-"], "", $gateway))) {
            return false;
        }
        return true;
    }

    public static function getActiveGateways()
    {
        if (is_array(self::$gateways)) {
            return self::$gateways;
        }
        self::$gateways = array_filter(Module\GatewaySetting::getActiveGatewayModules(), function ($gateway) {
            return Gateways::isNameValid($gateway);
        });
        return self::$gateways;
    }

    public function getAvailableGatewayInstances($onlyStoreRemote = false)
    {
        $modules = [];
        $gatewaysAggregator = new static();
        foreach (array_keys($gatewaysAggregator->getAvailableGateways()) as $name) {
            $module = new Module\Gateway();
            if ($module->isActiveGateway($name) && $module->load($name)) {
                if ($onlyStoreRemote) {
                    if ($module->functionExists("storeremote") || $module->functionExists("remoteinput")) {
                        $modules[$name] = $module;
                    }
                } else {
                    $modules[$name] = $module;
                }
            }
        }
        return $modules;
    }

    public function isActiveGateway($gateway)
    {
        $gateways = $this->getActiveGateways();
        return in_array($gateway, $gateways);
    }

    public static function makeSafeName($gateway)
    {
        $validgateways = Gateways::getActiveGateways();
        return in_array($gateway, $validgateways) ? $gateway : "";
    }

    public function getAvailableGateways($invoiceid = "")
    {
        $validgateways = Module\GatewaySetting::getVisibleGatewayFriendlyNames();
        if ($invoiceid) {
            $invoiceid = (int) $invoiceid;
            $invoicegateway = get_query_val("tblinvoices", "paymentmethod", ["id" => $invoiceid]);
            $result = select_query("tblinvoiceitems", "", ["type" => "Hosting", "invoiceid" => $invoiceid]);
            while ($data = mysql_fetch_assoc($result)) {
                $relid = $data["relid"];
                if ($relid) {
                    $result2 = full_query("SELECT pg.disabledgateways AS disabled FROM tblhosting h LEFT JOIN tblproducts p on h.packageid = p.id LEFT JOIN tblproductgroups pg on p.gid = pg.id where h.id = " . (int) $relid);
                    $data2 = mysql_fetch_assoc($result2);
                    $gateways = explode(",", $data2["disabled"]);
                    foreach ($gateways as $gateway) {
                        if (array_key_exists($gateway, $validgateways) && $gateway != $invoicegateway) {
                            unset($validgateways[$gateway]);
                        }
                    }
                }
            }
            if (array_key_exists($invoicegateway, $validgateways) === false) {
                $validgateways[$invoicegateway] = Module\GatewaySetting::getFriendlyNameFor($invoicegateway);
            }
        }
        return $validgateways;
    }

    public function getFirstAvailableGateway()
    {
        $gateways = $this->getAvailableGateways();
        return key($gateways);
    }

    public function getCCDateMonths()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = str_pad($i, 2, "0", STR_PAD_LEFT);
        }
        return $months;
    }

    public function getCCStartDateYears()
    {
        $startyears = [];
        for ($i = date("Y") - 12; $i <= date("Y"); $i++) {
            $startyears[] = $i;
        }
        return $startyears;
    }

    public function getCCExpiryDateYears()
    {
        $expiryyears = [];
        for ($i = date("Y"); $i <= date("Y") + static::CC_EXPIRY_MAX_YEARS; $i++) {
            $expiryyears[] = $i;
        }
        return $expiryyears;
    }

    public function getActiveMerchantGatewaysByType()
    {
        $groupedGateways = ["assisted" => [], "nolocalcardinput" => [], "merchant" => [], "remote" => [], "thirdparty" => [], "token" => []];
        $query = Database\Capsule::table("tblpaymentgateways as gw1")->where("gw1.setting", "type")->whereIn("gw1.value", [Module\Gateway::GATEWAY_CREDIT_CARD, Module\Gateway::GATEWAY_BANK])->leftJoin("tblpaymentgateways as gw2", "gw1.gateway", "=", "gw2.gateway")->where("gw2.setting", "visible");
        $gateways = $query->get(["gw1.gateway", "gw2.value as visible"])->all();
        $visibility = function ($gatewayData) {
            if (Auth::isLoggedIn() && defined("ADMINAREA")) {
                return true;
            }
            return (bool) $gatewayData->visible;
        };
        foreach ($gateways as $gatewayData) {
            $gateway = $gatewayData->gateway;
            $gatewayInterface = new Module\Gateway();
            $gatewayInterface->load($gateway);
            $groupedGateways[$gatewayInterface->getWorkflowType()][$gateway] = $visibility($gatewayData);
        }
        return $groupedGateways;
    }

    public function hasGatewaysSupportingManage()
    {
        $activeMerchantGateways = $this->getActiveMerchantGatewaysByType();
        $can = false;
        if (!defined("ADMINAREA")) {
            foreach ($activeMerchantGateways as &$activeMerchantGateway) {
                $activeMerchantGateway = array_filter($activeMerchantGateway);
            }
        }
        foreach ($activeMerchantGateways[Module\Gateway::WORKFLOW_TOKEN] as $gatewayName => $visible) {
            try {
                $gatewayInterface = Module\Gateway::factory($gatewayName);
                if (!$gatewayInterface->functionExists("storeremote")) {
                    unset($activeMerchantGateways[Module\Gateway::WORKFLOW_TOKEN][$gatewayName]);
                }
            } catch (\Exception $e) {
                unset($activeMerchantGateways[Module\Gateway::WORKFLOW_TOKEN][$gatewayName]);
            }
        }
        if (!empty($activeMerchantGateways[Module\Gateway::WORKFLOW_ASSISTED]) || !empty($activeMerchantGateways[Module\Gateway::WORKFLOW_REMOTE]) || !empty($activeMerchantGateways[Module\Gateway::WORKFLOW_TOKEN]) || !empty($activeMerchantGateways[Module\Gateway::WORKFLOW_MERCHANT]) || !empty($activeMerchantGateways[Module\Gateway::WORKFLOW_NOLOCALCARDINPUT]) || $this->isLocalBankAccountGatewayAvailable()) {
            $can = true;
        }
        return $can;
    }

    public function isLocalCreditCardStorageEnabled($client = true)
    {
        $merchantGateways = $this->getActiveMerchantGatewaysByType()[Module\Gateway::WORKFLOW_MERCHANT];
        if ($client) {
            $merchantGateways = array_filter($merchantGateways);
        }
        return 0 < count($merchantGateways);
    }

    public function isIssueDateAndStartNumberEnabled()
    {
        return (bool) Config\Setting::getValue("ShowCCIssueStart");
    }

    public function isLocalBankAccountGatewayAvailable()
    {
        foreach ($this->getAvailableGatewayInstances() as $gatewayInstance) {
            if ($gatewayInstance->supportsLocalBankDetails()) {
                return true;
            }
        }
        return false;
    }

    public function isBankAccountStorageAllowed()
    {
        if ($this->isLocalBankAccountGatewayAvailable()) {
            return true;
        }
        if ($this->hasGatewaysSupportingManage()) {
            $bankGateways = $this->getAvailableGatewayInstances(true);
            foreach ($bankGateways as $name => $module) {
                if ($module->getMetaDataValue("gatewayType") == Module\Gateway::GATEWAY_BANK) {
                    if ($module->requiresLocalInput()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function isCreditCardStorageAllowed()
    {
        if ($this->isLocalCreditCardStorageEnabled()) {
            return true;
        }
        if ($this->hasGatewaysSupportingManage()) {
            $cardGateways = $this->getAvailableGatewayInstances(true);
            foreach ($cardGateways as $name => $module) {
                if ($module->getMetaDataValue("gatewayType") != Module\Gateway::GATEWAY_BANK) {
                    if ($module->requiresLocalInput()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function getSupportedCardTypesForJQueryPayment()
    {
        $cardTypes = explode(",", Config\Setting::getValue("AcceptedCardTypes"));
        $return = [];
        foreach ($cardTypes as $cardType) {
            $cardType = strtolower(str_replace(" ", "", $cardType));
            if ($cardType == "americanexpress") {
                $cardType = "amex";
            }
            $return[] = $cardType;
        }
        return implode(",", $return);
    }

    public static function gatewayBalancesTotalsView($includeExpired = NULL, Http\Message\ServerRequest $request)
    {
        $allActiveGateways = Module\GatewaySetting::getActiveGatewayFriendlyNames();
        $balances = $gatewayInterfaces = [];
        $transientStore = $refreshOnLoad = $force = false;
        if ($request && $request->has("force")) {
            $force = (bool) (int) $request->get("force", 0);
        }
        $method = "retrieve";
        if ($includeExpired) {
            $method .= "IncludeExpired";
        }
        $transientData = TransientData::getInstance()->{$method}("GatewayBalanceData");
        if ($transientData) {
            $transientData = json_decode(base64_decode($transientData), true);
        }
        if (!$transientData || $transientData && $includeExpired && !TransientData::getInstance()->ifUnexpiredNameExists("GatewayBalanceData")) {
            $refreshOnLoad = true;
        }
        $loading = [];
        $hasOneBalanceEnabledGateway = false;
        foreach ($allActiveGateways as $activeGateway => $friendlyName) {
            $gatewayInterface = Module\Gateway::factory($activeGateway);
            $gatewayInterfaces[$activeGateway] = $gatewayInterface;
            $loading[$activeGateway] = false;
            if ($gatewayInterface->functionExists("account_balance")) {
                $hasOneBalanceEnabledGateway = true;
                if (!$force && !$request && empty($transientData[$activeGateway])) {
                    $loading[$activeGateway] = true;
                    $balances[$activeGateway] = Module\Gateway\BalanceCollection::factoryFromItems(Module\Gateway\Balance::factory(0, Billing\Currency::defaultCurrency()->value("code")));
                } else {
                    if (!$force && !empty($transientData[$activeGateway])) {
                        $balances[$activeGateway] = Module\Gateway\BalanceCollection::factoryFromArray($transientData[$activeGateway]);
                    } else {
                        try {
                            $balance = $gatewayInterface->call("account_balance");
                            if ($balance instanceof Module\Gateway\BalanceCollection) {
                                $balances[$activeGateway] = $balance;
                                $transientStore = true;
                            }
                        } catch (\Throwable $t) {
                            logActivity("Unable to retrieve account balance for " . $friendlyName . " - Error: " . $t->getMessage());
                        }
                    }
                }
            }
        }
        if (!$hasOneBalanceEnabledGateway) {
            return "";
        }
        if ($transientStore) {
            $balances["lastUpdated"] = Carbon::now();
            TransientData::getInstance()->store("GatewayBalanceData", base64_encode(json_encode($balances)), 900);
        } else {
            $balances["lastUpdated"] = $transientData["lastUpdated"];
            unset($transientData);
        }
        $lastUpdated = Carbon::parse($balances["lastUpdated"]);
        unset($balances["lastUpdated"]);
        return view("admin.billing.gateway.balance", ["balances" => $balances, "gatewayInterfaces" => $gatewayInterfaces, "lastUpdated" => $lastUpdated, "refreshOnLoad" => $refreshOnLoad, "gatewaysLoading" => $loading]);
    }
}
