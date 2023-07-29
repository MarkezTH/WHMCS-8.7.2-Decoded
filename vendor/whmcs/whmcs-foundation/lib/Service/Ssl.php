<?php

namespace WHMCS\Service;

class Ssl extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblsslorders";
    protected $columnMap = ["certificateType" => "certtype", "configurationData" => "configdata", "authenticationData" => "authdata", "certificateExpiryDate" => "certificate_expiry_date"];
    protected $dates = ["completionDate", "certificateExpiryDate"];
    protected $appends = ["validationType"];
    protected $fillable = ["userid", "serviceid", "addon_id", "module"];
    const MC_MAX_CALLBACK_ATTEMPTS = 5;
    const STATUS_AWAITING_CONFIGURATION = "Awaiting Configuration";
    const STATUS_AWAITING_ISSUANCE = "Awaiting Issuance";
    const STATUS_CANCELLED = "Cancelled";
    const STATUS_COMPLETED = "Completed";
    const STATUS_CONFIGURATION_SUBMITTED = "Configuration Submitted";
    const STATUS_EXPIRED = "Expired";
    const STATUS_REISSUE_FAILED = "Reissue Failed";
    const STATUS_REISSUE_PENDING = "Reissue Pending";
    const STATUS_REISSUED = "Reissued";
    const SERVICE_PROP_DOMAIN = "Certificate Domain";
    const SERVICE_PROP_ORDER = "Order Number";
    const DOMAIN_VALIDATION_FILE = "fileauth";
    const DOMAIN_VALIDATION_EMAIL = "emailauth";
    const DOMAIN_VALIDATION_DNS = "dnsauth";
    const DOMAIN_VALIDATIONS = NULL;
    const DOMAIN_VALIDATIONS_AUTOCONF = NULL;
    const DOMAIN_VALIDATION_PANEL_SUPPORT = NULL;
    const GENERATE_CSR_PANEL_SUPPORT = ["cpanel", "plesk", "directadmin"];
    const EMAIL_CONFIGURATION_REQUIRED = "SSL Certificate Configuration Required";
    const EMAIL_REISSUE_DUE = "SSL Certificate Multi-Year Reissue Due";
    const EMAIL_MANUAL_VALIDATION = "SSL Certificate Validation Manual Intervention";
    const EMAIL_INSTALLED = "SSL Certificate Installed";
    const EMAIL_ISSUED = "SSL Certificate Issued";

    public static function factoryFromService($self, $serviceish = NULL, $module)
    {
        if ($serviceish instanceof Addon) {
            return static::findForAddon($serviceish, $module);
        }
        if ($serviceish instanceof Service) {
            return static::findForService($serviceish, $module);
        }
        throw new \RuntimeException(sprintf("Unable to factory for concrete type %s", get_class($serviceish)));
    }

    public static function newFromService($self, $serviceish, $module)
    {
        $ssl = new static();
        $ssl->userid = $serviceish->getServiceClient()->id;
        $ssl->serviceid = $serviceish->getServiceActual()->id;
        $ssl->addon_id = $serviceish instanceof Addon ? $serviceish->id : 0;
        $ssl->module = $module;
        return $ssl;
    }

    public static function findForService($self, $service = NULL, $module)
    {
        $query = static::clientId($service->clientId)->where("serviceid", $service->id)->where("addon_id", 0);
        if (!is_null($module)) {
            $query->where("module", $module);
        }
        return $query->first();
    }

    public static function findForAddon($self, $addon = NULL, $module)
    {
        $query = static::clientId($addon->clientId)->where("serviceid", $addon->service->id)->where("addon_id", $addon->id);
        if (!is_null($module)) {
            $query->where("module", $module);
        }
        return $query->first();
    }

    public static function findForMarketConnectOrder($self, $order)
    {
        return static::marketConnect()->where("remoteid", "=", $order)->first();
    }

    public function scopeClientId($builder, $value)
    {
        return $builder->where("userid", "=", $value);
    }

    public function scopeMarketConnect($builder)
    {
        return $builder->where("module", "=", \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT);
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function service()
    {
        return $this->belongsTo("WHMCS\\Service\\Service", "serviceid");
    }

    public function addon()
    {
        return $this->belongsTo("WHMCS\\Service\\Addon", "addon_id");
    }

    public function getConfigurationDataAttribute($value)
    {
        $jsonDecodedValue = json_decode($value, true);
        if (!is_null($jsonDecodedValue) && json_last_error() === JSON_ERROR_NONE) {
            return $jsonDecodedValue;
        }
        return safe_unserialize($value);
    }

    public function setConfigurationDataAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes["configdata"] = json_encode($value);
        } else {
            $this->attributes["configdata"] = $value;
        }
    }

    public function getAuthdataAttribute($ValidationMethod, $value)
    {
        if (strlen($value) == 0) {
            return NULL;
        }
        return Ssl\ValidationMethod::factoryFromPacked($value);
    }

    public function setAuthdataAttribute(Ssl\ValidationMethod $value)
    {
        if ($value !== NULL) {
            $value = $value->pack();
        }
        $this->attributes["authdata"] = $value;
    }

    public function getConfigurationUrl()
    {
        return \App::getSystemURL() . "configuressl.php?cert=" . md5($this->id);
    }

    public function getConfigurationLink()
    {
        $url = $this->getConfigurationUrl();
        return "<a href=\"" . $url . "\">" . $url . "</a>";
    }

    public function getManageUrl()
    {
        return \App::getSystemURL() . "clientarea.php?action=productdetails&id=" . $this->serviceId;
    }

    public function getManageLink()
    {
        $url = $this->getManageUrl();
        return "<a href=\"" . $url . "\">" . $url . "</a>";
    }

    public function getUpgradeUrl()
    {
        $symantecSlug = \WHMCS\MarketConnect\MarketConnect::getServiceProductGroupSlug(\WHMCS\MarketConnect\MarketConnect::SERVICE_SYMANTEC);
        $uri = routePath("store-product-group", $symantecSlug);
        $evCertsEnabled = \WHMCS\Product\Product::marketConnect()->whereIn("configoption1", (new \WHMCS\MarketConnect\Promotion\Service\Symantec())->getSslTypes()["ev"])->visible()->first();
        if ($evCertsEnabled) {
            $uri = routePath("store-product-group", $symantecSlug, \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_EV);
        }
        return $uri;
    }

    public function getOwningService($ServiceInterface)
    {
        if ($this->isAddon()) {
            return $this->addon;
        }
        return $this->service;
    }

    public function isAddon()
    {
        return !empty($this->addonId);
    }

    public function getProduct()
    {
        if ($this->isAddon()) {
            return $this->addon->productAddon;
        }
        return $this->service->product;
    }

    public function getOrderNumber()
    {
        return $this->getOwningService()->serviceProperties->get(static::SERVICE_PROP_ORDER);
    }

    public function setOrderNumber($self, $number)
    {
        $this->remoteId = $number;
        $this->getOwningService()->serviceProperties->save([static::SERVICE_PROP_ORDER => $number]);
        return $this;
    }

    public function moduleConfigOption1()
    {
        $value = "";
        if ($this->isAddon()) {
            $option = $this->addon->productAddon->moduleConfiguration->firstWhere("settingName", "=", "configoption1");
            if (!is_null($option)) {
                $value = $option->value;
            }
        } else {
            $value = $this->service->product->moduleConfigOption1;
        }
        return $value;
    }

    public function getDomain()
    {
        return (string) $this->service->serviceProperties->get(static::SERVICE_PROP_DOMAIN) ?: $this->service->getServiceSurrogate()->getServiceDomain();
    }

    public function getProductKey()
    {
        return $this->moduleConfigOption1();
    }

    public function getValidationTypeAttribute()
    {
        return strtoupper($this->getProductCategory($this->getProductKey()));
    }

    public function isProductCategory(...$categories)
    {
        return in_array($this->getProductCategory($this->getProductKey()), $categories);
    }

    public function getProductCategory($productKey)
    {
        foreach ((new \WHMCS\MarketConnect\Promotion\Service\Symantec())->getSslTypes() as $category => $productKeys) {
            if (in_array($productKey, $productKeys)) {
                return $category;
            }
        }
        return "";
    }

    public function isWildcard()
    {
        return in_array($this->getProductKey(), (new \WHMCS\MarketConnect\Promotion\Service\Symantec())->getSslTypes()[\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_WILDCARD]);
    }

    public static function normalizeToValidationMethod($value)
    {
        $method = NULL;
        switch ($value) {
            case "email":
            case \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_EMAIL:
            case static::DOMAIN_VALIDATION_EMAIL:
                $method = static::DOMAIN_VALIDATION_EMAIL;
                break;
            case "http-file":
            case "file":
            case \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_FILE:
            case static::DOMAIN_VALIDATION_FILE:
                $method = static::DOMAIN_VALIDATION_FILE;
                break;
            case "dns-txt-token":
            case "dns":
            case \WHMCS\MarketConnect\Ssl\Configuration::MARKETCONNECT_DCV_DNS:
            case static::DOMAIN_VALIDATION_DNS:
                $method = static::DOMAIN_VALIDATION_DNS;
                break;
            default:
                return $method;
        }
    }

    protected function getDefaultValidationMethod()
    {
        $method = static::DOMAIN_VALIDATION_FILE;
        if ($this->isProductCategory(\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_WILDCARD)) {
            $method = static::DOMAIN_VALIDATION_DNS;
        } else {
            if (!$this->isProductCategory(\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TYPE_DV)) {
                $method = static::DOMAIN_VALIDATION_EMAIL;
            }
        }
        return $method;
    }

    public function canProvisioningModuleAutoConfigure($method)
    {
        if (is_null($method)) {
            $method = $this->getDefaultValidationMethod();
        }
        return $this->isDomainControlMethodAutoConfigurable($method) && $this->canProvisioningModuleGenerateCertificateSigningRequest() && $this->doesProvisioningModuleSupportDomainControlMethod($method);
    }

    public function canProvisioningModuleGenerateCertificateSigningRequest()
    {
        return in_array($this->getProvisioningModuleName(), static::GENERATE_CSR_PANEL_SUPPORT);
    }

    public function doesProvisioningModuleSupportDomainControlMethod($method)
    {
        return in_array($method, $this->getProvisioningModuleSupportedDomainValidationMethods());
    }

    public function isDomainControlMethodAutoConfigurable($method)
    {
        return in_array($method, static::DOMAIN_VALIDATIONS_AUTOCONF);
    }

    public function getProvisioningModuleOwner($Service)
    {
        $service = $this->getOwningService();
        if ($this->isAddon()) {
            return $service->getServiceActual();
        }
        return $service->getServiceSurrogate();
    }

    public function getProvisioningModuleName()
    {
        return $this->getProvisioningModuleOwner()->getProvisioningModuleName();
    }

    public function getProvisioningModuleSupportedDomainValidationMethods()
    {
        $methods = [static::DOMAIN_VALIDATION_EMAIL];
        $module = $this->getProvisioningModuleName();
        if ($module != NULL) {
            $methods = array_merge($methods, static::getDomainValidationSupportByProvisioningModule($module));
        }
        return $methods;
    }

    public static function getDomainValidationSupportByProvisioningModule($moduleName)
    {
        $methods = [];
        foreach (static::DOMAIN_VALIDATION_PANEL_SUPPORT as $method => $modules) {
            if (in_array($moduleName, $modules)) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    public function getProvisioningModule($Server)
    {
        return \WHMCS\Module\Server::factoryFromModel($this->getProvisioningModuleOwner());
    }

    public function getOwnProvisioningModule($Server)
    {
        return \WHMCS\Module\Server::factoryFromModel($this->getOwningService());
    }

    public function canConfigure()
    {
        return ($this->isAddon() || $this->getOwningService()->getServiceActual()->hasServiceSurrogate()) && $this->canProvisioningModuleAutoConfigure();
    }

    public function statePending()
    {
        $this->certificateType = $this->getProduct()->name;
        $this->remoteId = $this->getOrderNumber();
        $this->status = "Awaiting Configuration";
        return $this;
    }

    public function stateConfigured($self, $configuration)
    {
        $this->configurationData = $configuration->cleanForPersistence();
        $this->status = "Awaiting Issuance";
        return $this;
    }

    public function stateRenewed($self, $orderNumber)
    {
        $this->setOrderNumber($orderNumber);
        $this->status = "Awaiting Issuance";
        return $this;
    }

    public function stateCancelled()
    {
        $this->status = static::STATUS_CANCELLED;
        $this->authenticationData = NULL;
        $this->certificateExpiryDate = NULL;
        return $this;
    }

    public function logReissue($self, $reissue)
    {
        $configurationData = $this->configurationData;
        if (empty($configurationData["reissues"])) {
            $configurationData["reissues"] = [];
        }
        $configurationData["reissues"][] = $reissue;
        $this->configurationData = $configurationData;
        return $this;
    }

    public function reissueAttemptFailure($self, $failureReason)
    {
        $configurationData = $this->configurationData;
        if (!isset($configurationData["reissueAttempts"])) {
            $configurationData["reissueAttempts"] = 0;
        }
        $configurationData["reissueAttempts"]++;
        $configurationData["lastReissueFailure"] = $failureReason;
        if (4 < $configurationData["reissueAttempts"]) {
            $this->status = self::STATUS_REISSUE_FAILED;
            \WHMCS\Module\Queue::add($this->addonId ? "addon" : "service", $this->addonId ?: $this->serviceId, $this->module, "reissue_certificate", $failureReason);
            $this->sendEmail(self::EMAIL_REISSUE_DUE);
        }
        $this->configurationData = $configurationData;
        return $this;
    }

    public function resetReissueAttempts()
    {
        $configurationData = $this->configurationData;
        $configurationData["reissueAttempts"] = 0;
        $configurationData["lastReissueFailure"] = "";
        $this->configurationData = $configurationData;
        \WHMCS\Module\Queue::resolve($this->addonId ? "addon" : "service", $this->addonId ?: $this->serviceId, $this->module, "reissue_certificate");
        return $this;
    }

    public function sendEmail($template, $extra = [])
    {
        if ($this->addonId) {
            $extra["addon_id"] = $this->addonId;
        }
        sendMessage($template, $this->serviceId, $extra);
    }

    public function wasInstantIssuanceEligible()
    {
        return (bool) ($this->configurationData["instant_issuance_eligible"] ?? false);
    }

    public function wasInstantIssuanceAttempted()
    {
        return (bool) ($this->configurationData["instant_issuance_attempted"] ?? false);
    }

    public function wasInstantIssuanceSuccessful()
    {
        return (bool) ($this->configurationData["instant_issuance_successful"] ?? false);
    }

    public function getInstantIssuanceError()
    {
        return $this->configurationData["instant_issuance_error"] ?? NULL;
    }

    public function canChangeApproverEmail()
    {
        return !in_array($this->status, ["Completed", "Reissued"]);
    }
}
