<?php

namespace WHMCS\Domain;

class Domain extends \WHMCS\Model\AbstractModel
{
    use \WHMCS\Domains\Traits\DomainTraits;
    protected $table = "tbldomains";
    protected $dates = ["registrationdate", "expirydate", "nextduedate", "nextinvoicedate"];
    protected $columnMap = ["clientId" => "userid", "registrarModuleName" => "registrar", "promotionId" => "promoid", "paymentGateway" => "paymentmethod", "hasDnsManagement" => "dnsmanagement", "hasEmailForwarding" => "emailforwarding", "hasIdProtection" => "idprotection", "hasAutoInvoiceOnNextDueDisabled" => "donotrenew", "isSyncedWithRegistrar" => "synced", "isPremium" => "is_premium", "subscriptionId" => "subscriptionid"];
    protected $booleans = ["hasDnsManagement", "hasEmailForwarding", "hasIdProtection", "isPremium", "hasAutoInvoiceOnNextDueDisabled", "isSyncedWithRegistrar"];
    protected $characterSeparated = ["|" => ["reminders"]];
    protected $appends = ["tld", "domainPunycode", "extension", "gracePeriod", "gracePeriodFee", "redemptionGracePeriod", "redemptionGracePeriodFee"];

    public function scopeOfClient(\Illuminate\Database\Eloquent\Builder $query, $clientId)
    {
        return $query->where("userid", $clientId);
    }

    public function scopeNextDueBefore(\Illuminate\Database\Eloquent\Builder $query, \WHMCS\Carbon $date)
    {
        return $query->whereStatus("Active")->where("nextduedate", "<=", $date);
    }

    public function scopeIsConsideredActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereIn("status", [\WHMCS\Utility\Status::ACTIVE, \WHMCS\Utility\Status::PENDING_TRANSFER, \WHMCS\Utility\Status::GRACE]);
    }

    public function scopeFree(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("recurringamount", "0.00");
    }

    public function scopeNotFree(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("recurringamount", "!=", "0.00");
    }

    public function getTldAttribute()
    {
        $domainParts = explode(".", $this->domain, 2);
        return isset($domainParts[1]) ? $domainParts[1] : "";
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function additionalFields()
    {
        return $this->hasMany("WHMCS\\Domain\\AdditionalField", "domainid");
    }

    public function extra()
    {
        return $this->hasMany("WHMCS\\Domain\\Extra", "domain_id");
    }

    public function order()
    {
        return $this->belongsTo("WHMCS\\Order\\Order", "orderid");
    }

    public function paymentGateway()
    {
        return $this->hasMany("WHMCS\\Module\\GatewaySetting", "gateway", "paymentmethod");
    }

    public function invoiceItems()
    {
        return $this->hasMany("\\WHMCS\\Billing\\Invoice\\Item", "relid")->whereIn("type", ["DomainRegister", "DomainTransfer", "Domain", "DomainAddonDNS", "DomainAddonEMF", "DomainAddonIDP", "DomainGraceFee", "DomainRedemptionFee"]);
    }

    public function invoices($BelongsToMany)
    {
        return $this->belongsToMany("WHMCS\\Billing\\Invoice", "tblinvoiceitems", "relid", "invoiceid")->wherePivotIn("type", \WHMCS\Billing\Invoice\Item::TYPE_GROUP_DOMAIN);
    }

    public function getRegistrarInterface()
    {
        if (!$this->registrarModuleName) {
            throw new \WHMCS\Exception("Domain not assigned to a registrar module");
        }
        return \WHMCS\Module\Registrar::factoryFromDomain($this);
    }

    public function getDomainObject()
    {
        return new \WHMCS\Domains\Domain($this->domain);
    }

    public function setRemindersAttribute($reminderString)
    {
        $reminders = $this->asArrayFromCharacterSeparatedValue($reminderString, "|");
        if (5 < count($reminders)) {
            throw new \WHMCS\Exception("You may only store the past 5 domain reminders.");
        }
        foreach ($reminders as $reminder) {
            if (!is_numeric($reminder)) {
                throw new \WHMCS\Exception("Domain reminders must be numeric.");
            }
        }
        $this->attributes["reminders"] = $reminderString;
    }

    public function failedActions()
    {
        return $this->hasMany("WHMCS\\Module\\Queue", "service_id")->where("service_type", "=", "domain");
    }

    public function isConfiguredTld()
    {
        $tld = $this->getTldAttribute();
        return 0 < (bool) \WHMCS\Database\Capsule::table("tbldomainpricing")->where("extension", "." . $tld)->count();
    }

    public function getAdditionalFields()
    {
        return (new \WHMCS\Domains\AdditionalFields())->setDomainType($this->type)->setDomain($this->domain);
    }

    public function getExtensionAttribute()
    {
        $tld = $this->getTldAttribute();
        if ($tld && !array_key_exists($tld, $data)) {
            $data[$tld] = \WHMCS\Domains\Extension::where("extension", "." . $tld)->first();
        }
        return $data[$tld];
    }

    public function getGracePeriodAttribute()
    {
        if (\WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")) {
            return -1;
        }
        if (!array_key_exists($this->tld, $renewalGracePeriod)) {
            $domainExtensionConfiguration = $this->extension;
            if ($domainExtensionConfiguration) {
                $renewalGracePeriod[$this->tld] = $domainExtensionConfiguration->gracePeriod;
                if ($renewalGracePeriod[$this->tld] == -1) {
                    $renewalGracePeriod[$this->tld] = $domainExtensionConfiguration->defaultGracePeriod;
                }
            } else {
                $renewalGracePeriod[$this->tld] = TopLevel\GracePeriod::getForTld($this->getTldAttribute());
            }
        }
        return $renewalGracePeriod[$this->tld];
    }

    public function getGracePeriodFeeAttribute()
    {
        if (\WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")) {
            return -1;
        }
        if (!array_key_exists($this->tld, $gracePeriodFee)) {
            $domainExtensionConfiguration = $this->extension;
            $gracePeriodFee[$this->tld] = -1;
            if (0 <= $domainExtensionConfiguration->gracePeriodFee) {
                $gracePeriodFee[$this->tld] = $domainExtensionConfiguration->gracePeriodFee;
            }
        }
        return $gracePeriodFee[$this->tld];
    }

    public function getRedemptionGracePeriodAttribute()
    {
        if (\WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")) {
            return -1;
        }
        if (!array_key_exists($this->tld, $redemptionGracePeriod)) {
            $domainExtensionConfiguration = $this->extension;
            if ($domainExtensionConfiguration) {
                $redemptionGracePeriod[$this->tld] = $domainExtensionConfiguration->redemptionGracePeriod;
                if ($redemptionGracePeriod[$this->tld] == -1) {
                    $redemptionGracePeriod[$this->tld] = $domainExtensionConfiguration->defaultRedemptionGracePeriod;
                }
            } else {
                $redemptionGracePeriod[$this->tld] = TopLevel\RedemptionGracePeriod::getForTld($this->tld);
            }
        }
        return $redemptionGracePeriod[$this->tld];
    }

    public function getRedemptionGracePeriodFeeAttribute()
    {
        if (\WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")) {
            return -1;
        }
        if (!array_key_exists($this->tld, $redemptionGracePeriodFee)) {
            $domainExtensionConfiguration = $this->extension;
            $redemptionGracePeriodFee[$this->tld] = -1;
            if (0 <= $domainExtensionConfiguration->redemptionGracePeriodFee) {
                $redemptionGracePeriodFee[$this->tld] = $domainExtensionConfiguration->redemptionGracePeriodFee;
            }
        }
        return $redemptionGracePeriodFee[$this->tld];
    }

    public function getLink()
    {
        return \App::get_admin_folder_name() . "/clientsdomains.php?id=" . $this->id;
    }

    protected function getServiceByDomain()
    {
        return \WHMCS\Service\Service::where("domain", $this->domain)->whereIn("domainstatus", [\WHMCS\Utility\Status::ACTIVE, \WHMCS\Utility\Status::PENDING])->orderBy("domainstatus")->orderBy("id", "desc")->first();
    }

    protected function getDefaultNameservers()
    {
        $nameservers = [];
        for ($i = 1; $i <= 5; $i++) {
            $nameservers[] = \WHMCS\Config\Setting::getValue("DefaultNameserver" . $i);
        }
        return removeEmptyValues(arrayTrim($nameservers));
    }

    public function getBestNameserversForNewOrder()
    {
        $service = $this->getServiceByDomain();
        if ($service && 0 < $service->serverId) {
            $server = $service->serverModel;
            if ($server) {
                $nameservers = $server->getNameservers();
                if (count($nameservers) == 0) {
                    throw new \WHMCS\Exception\Module\NotServicable("No nameservers are defined for the server this domain is assigned to. Please correct and try again.");
                }
                return $nameservers;
            }
        }
        if (0 < $this->orderId) {
            $order = $this->order;
            if ($order) {
                $nameservers = $order->getNameservers();
                if (0 < count($nameservers)) {
                    return $nameservers;
                }
            }
        }
        $nameservers = $this->getDefaultNameservers();
        if (count($nameservers) == 0) {
            throw new \WHMCS\Exception\Module\NotServicable("No default nameservers are configured in the Domains tab at Configuration <i class=“far fa-wrench” aria-hidden=\"true\"></i> > System Settings > General Settings. Please correct this and try again.");
        }
        return $nameservers;
    }

    public function getTranslatedStatusAttribute()
    {
        return Status::translate($this->status);
    }

    public function scopeDueForSync(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("registrar", "!=", "")->where("synced", "=", "0")->whereIn("status", [Status::PENDING_REGISTRATION, Status::ACTIVE]);
    }

    public function isFree()
    {
        $serviceCount = \WHMCS\Service\Service::isConsideredActive()->userId($this->clientId)->domain($this->domain)->count();
        return (string) $this->recurringAmount === "0.00" && 0 < $serviceCount;
    }

    public function recalculateRecurringPrice()
    {
        if (!function_exists("getCurrency")) {
            \App::load_function("functions");
        }
        if (!function_exists("recalcPromoAmount")) {
            \App::load_function("client");
        }
        if (!function_exists("getTLDPriceList")) {
            \App::load_function("domain");
        }
        $currency = getCurrency($this->clientId);
        $domainObject = $this->getDomainObject();
        $registrationPeriod = $this->registrationPeriod;
        if ($this->isPremium) {
            $recurringAmount = (double) $this->extra()->where("name", "registrarRenewalCostPrice")->value("value");
            $recurringAmountCurrency = $this->extra()->where("name", "registrarCurrency")->value("value");
            $recurringAmount = convertCurrency($recurringAmount, $recurringAmountCurrency, $currency["id"]);
            $hookReturns = \HookMgr::run("PremiumPriceRecalculationOverride", ["domainName" => $this->domain, "tld" => $domainObject->getTopLevel(), "sld" => $domainObject->getPunycodeSecondLevel(), "renew" => $recurringAmount]);
            $skipMarkup = false;
            foreach ($hookReturns as $hookReturn) {
                if (array_key_exists("renew", $hookReturn)) {
                    $recurringAmount = $hookReturn["renew"];
                }
                if (array_key_exists("skipMarkup", $hookReturn) && $hookReturn["skipMarkup"] === true) {
                    $skipMarkup = true;
                }
            }
            if (!$skipMarkup) {
                $recurringAmount *= 1 + \WHMCS\Domains\Pricing\Premium::markupForCost($recurringAmount) / 100;
            }
        } else {
            $tempPriceList = getTLDPriceList($domainObject->getDotTopLevel(), "", true, $this->clientId);
            $recurringAmount = $tempPriceList[$registrationPeriod]["renew"] ?? NULL;
            while (is_null($recurringAmount) || $recurringAmount < 0) {
                $registrationPeriod--;
                if ($registrationPeriod === 0) {
                    $recurringAmount = $this->recurringAmount;
                    $registrationPeriod = $this->registrationPeriod;
                } else {
                    $recurringAmount = $tempPriceList[$registrationPeriod]["renew"] ?? NULL;
                }
            }
            $domainAddonPricing = \WHMCS\Database\Capsule::table("tblpricing")->where("type", "=", "domainaddons")->where("currency", $currency["id"])->where("relid", "=", 0)->select(["msetupfee", "qsetupfee", "ssetupfee"])->first();
            $dnsManagementPrice = $domainAddonPricing->msetupfee * $registrationPeriod;
            $emailForwardingPrice = $domainAddonPricing->qsetupfee * $registrationPeriod;
            $idProtectionPrice = $domainAddonPricing->ssetupfee * $registrationPeriod;
            if ($this->hasDnsManagement) {
                $recurringAmount += $dnsManagementPrice;
            }
            if ($this->hasEmailForwarding) {
                $recurringAmount += $emailForwardingPrice;
            }
            if ($this->hasIdProtection) {
                $recurringAmount += $idProtectionPrice;
            }
            if (!empty($this->promotionId)) {
                $recurringAmount -= (double) recalcPromoAmount("D." . $this->tld, $this->clientId, $this->id, $registrationPeriod . "Years", $recurringAmount, $this->promotionId);
            }
        }
        $this->recurringAmount = $recurringAmount;
        $this->registrationPeriod = $registrationPeriod;
        return $this;
    }

    public function getRegistrarModuleDisplayName($stringForNone)
    {
        try {
            $registrar = $this->getRegistrarInterface()->getDisplayName();
        } catch (\WHMCS\Exception $e) {
            $registrar = !empty($this->registrarModuleName) ? $this->registrarModuleName : $stringForNone;
        }
        return $registrar;
    }
}
