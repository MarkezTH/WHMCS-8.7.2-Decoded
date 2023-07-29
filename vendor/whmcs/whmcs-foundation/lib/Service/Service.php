<?php

namespace WHMCS\Service;

class Service extends \WHMCS\Model\AbstractModel implements \WHMCS\ServiceInterface
{
    use \WHMCS\Domains\Traits\DomainTraits;
    use Traits\ProvisioningTraits;
    protected $table = "tblhosting";
    protected $columnMap = ["clientId" => "userid", "productId" => "packageid", "serverId" => "server", "registrationDate" => "regdate", "paymentGateway" => "paymentmethod", "status" => "domainstatus", "promotionId" => "promoid", "promotionCount" => "promocount", "overrideAutoSuspend" => "overideautosuspend", "overrideSuspendUntilDate" => "overidesuspenduntil", "bandwidthUsage" => "bwusage", "bandwidthLimit" => "bwlimit", "lastUpdateDate" => "lastupdate", "firstPaymentAmount" => "firstpaymentamount", "recurringAmount" => "amount", "recurringFee" => "amount", "subscriptionId" => "subscriptionid"];
    protected $dates = ["registrationDate", "overrideSuspendUntilDate", "lastUpdateDate"];
    protected $booleans = ["overideautosuspend"];
    protected $appends = ["domainPunycode", "serviceProperties"];
    protected $hidden = ["password"];
    const STATUS_PENDING = \WHMCS\Utility\Status::PENDING;
    const STATUS_ACTIVE = \WHMCS\Utility\Status::ACTIVE;
    const STATUS_SUSPENDED = \WHMCS\Utility\Status::SUSPENDED;

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Service\\Observers\\SslOrderServiceObserver");
        static::observe("WHMCS\\Service\\Observers\\ServiceHookObserver");
    }

    public function getServiceActual()
    {
        return $this;
    }

    public function getServiceSurrogate()
    {
        return $this->parentalSibling ?? $this;
    }

    public function hasServiceSurrogate()
    {
        return $this->parentalSibling !== NULL;
    }

    public function getServiceClient($Client)
    {
        return $this->client;
    }

    public function getServiceProperties($Properties)
    {
        return $this->serviceProperties;
    }

    public function scopeUserId($Builder, $query, int $userId)
    {
        return $query->where("userid", "=", $userId);
    }

    public function scopeDomain($Builder, $query, $domain)
    {
        return $query->where("domain", "=", $domain);
    }

    public function scopeActive($Builder, $query)
    {
        return $query->where("domainstatus", self::STATUS_ACTIVE);
    }

    public function scopeMarketConnect($Builder, $query)
    {
        $marketConnectProductIds = \WHMCS\Product\Product::marketConnect()->pluck("id");
        return $query->whereIn("packageid", $marketConnectProductIds);
    }

    public function scopeIsConsideredActive($Builder, $query)
    {
        return $query->whereIn("domainstatus", [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED]);
    }

    public function scopeIsNotRecurring($Builder, $query)
    {
        return $query->whereIn("billingcycle", ["Free", "Free Account", "One Time"]);
    }

    public function isRecurring()
    {
        return !in_array($this->billingcycle, [\WHMCS\Billing\Cycles::DISPLAY_FREE, \WHMCS\Billing\Cycles::DISPLAY_ONETIME]);
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function product()
    {
        return $this->belongsTo("WHMCS\\Product\\Product", "packageid");
    }

    public function paymentGateway()
    {
        return $this->hasMany("WHMCS\\Module\\GatewaySetting", "gateway", "paymentmethod");
    }

    public function addons()
    {
        return $this->hasMany("WHMCS\\Service\\Addon", "hostingid");
    }

    public function order()
    {
        return $this->belongsTo("WHMCS\\Order\\Order", "orderid");
    }

    public function promotion()
    {
        return $this->hasMany("WHMCS\\Product\\Promotion", "id", "promoid");
    }

    public function cancellationRequests()
    {
        return $this->hasMany("WHMCS\\Service\\CancellationRequest", "relid");
    }

    public function ssl()
    {
        return $this->hasMany("WHMCS\\Service\\Ssl", "serviceid")->where("addon_id", "=", 0);
    }

    public function invoices($BelongsToMany)
    {
        return $this->belongsToMany("WHMCS\\Billing\\Invoice", "tblinvoiceitems", "relid", "invoiceid")->wherePivot("type", \WHMCS\Billing\Invoice\Item::TYPE_SERVICE);
    }

    public function hasAvailableUpgrades()
    {
        return 0 < $this->product->upgradeProducts->count();
    }

    public function failedActions()
    {
        return $this->hasMany("WHMCS\\Module\\Queue", "service_id")->where("service_type", "=", "service");
    }

    public function customFieldValues()
    {
        return $this->hasMany("WHMCS\\CustomField\\CustomFieldValue", "relid");
    }

    protected function getCustomFieldType()
    {
        return "product";
    }

    protected function getCustomFieldRelId()
    {
        return $this->product->id;
    }

    public function getServicePropertiesAttribute()
    {
        return new Properties($this);
    }

    public function canBeUpgraded()
    {
        return $this->status == "Active";
    }

    public function isService()
    {
        return true;
    }

    public function isAddon()
    {
        return false;
    }

    public function serverModel()
    {
        return $this->belongsTo("WHMCS\\Product\\Server", "server");
    }

    public function legacyProvision()
    {
        try {
            if (!function_exists("ModuleCallFunction")) {
                require_once ROOTDIR . "/includes/modulefunctions.php";
            }
            return ModuleCallFunction("Create", $this->id);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getMetricProvider()
    {
        $server = $this->serverModel;
        if ($server) {
            $metricProvider = $server->getMetricProvider();
            if ($metricProvider) {
                return $metricProvider;
            }
        }
    }

    public function metrics($onlyBilledMetrics = false, $mode = NULL)
    {
        if (is_null($mode)) {
            $mode = \WHMCS\UsageBilling\Invoice\ServiceUsage::getQuickViewMode();
        }
        $serviceMetrics = [];
        $metricProvider = $this->getMetricProvider();
        if (!$metricProvider) {
            return $serviceMetrics;
        }
        $product = $this->product;
        $storedProductUsageItems = [];
        foreach ($product->metrics as $usageItem) {
            $storedProductUsageItems[$usageItem->metric] = $usageItem;
        }
        $usageTenant = $this->serverModel->usageTenantByService($this);
        foreach ($metricProvider->metrics() as $metric) {
            $currentUsage = NULL;
            $usageItem = NULL;
            $totalHistoricUsage = NULL;
            $totalHistoricSum = 0;
            $historicUsageByPeriod = [];
            $currentTenantStatId = NULL;
            if (isset($storedProductUsageItems[$metric->systemName()])) {
                $usageItem = $storedProductUsageItems[$metric->systemName()];
            }
            if (!$onlyBilledMetrics || empty($usageItem->isHidden)) {
                if ($usageTenant) {
                    $stat = new \WHMCS\UsageBilling\Metrics\Server\Stat();
                    if ($metric->type() == \WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_PERIOD_DAY) {
                        $startOfDayPeriod = \WHMCS\Carbon::now()->startOfDay();
                        $currentPeriodSum = 0;
                        $currentLastUpdated = \WHMCS\Carbon::now();
                        $currentPeriodStat = $stat->unbilledFirstAfter($startOfDayPeriod, $usageTenant, $metric);
                        if ($currentPeriodStat) {
                            $currentLastUpdated = \WHMCS\Carbon::createFromTimestamp($currentPeriodStat->measuredAt);
                            $currentPeriodSum = $currentPeriodStat->value;
                            $currentTenantStatId = $currentPeriodStat->id;
                        }
                        $metric = $metric->withUsage(new \WHMCS\UsageBilling\Metrics\Usage($currentPeriodSum, $currentLastUpdated, $startOfDayPeriod->copy(), $startOfDayPeriod->copy()->endOfDayMicro()));
                        $previousDailyMetricPeriod = $startOfDayPeriod->copy()->subDay();
                        $historicUsageEnd = $previousDailyMetricPeriod->copy()->endOfDayMicro();
                        $historicUsageStart = $previousDailyMetricPeriod->copy()->startOfDayMicro();
                        $previousStats = $stat->unbilledQueryBefore($startOfDayPeriod, $usageTenant, $metric)->get();
                        foreach ($previousStats as $previous) {
                            $measured = \WHMCS\Carbon::createFromTimestamp($previous->measuredAt);
                            $start = $measured->copy()->startOfDayMicro();
                            $end = $measured->copy()->endOfDayMicro();
                            if ($start < $historicUsageStart) {
                                $historicUsageStart = $start;
                            }
                            if ($historicUsageEnd < $end) {
                                $historicUsageEnd = $end;
                            }
                            $historicUsageByPeriod[$previous->id] = new \WHMCS\UsageBilling\Metrics\Usage($previous->value, $measured->copy(), $start, $end);
                            $totalHistoricSum += $previous->value;
                        }
                        $totalHistoricUsage = new \WHMCS\UsageBilling\Metrics\Usage($totalHistoricSum, $historicUsageEnd, $historicUsageStart, $historicUsageEnd);
                    } else {
                        if ($metric->type() == \WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_PERIOD_MONTH) {
                            $startOfMetricPeriod = \WHMCS\Carbon::now()->startOfMonth();
                            $currentPeriodSum = 0;
                            $currentLastUpdated = \WHMCS\Carbon::now();
                            $currentPeriodStat = $stat->unbilledFirstAfter($startOfMetricPeriod, $usageTenant, $metric);
                            if ($currentPeriodStat) {
                                $currentLastUpdated = \WHMCS\Carbon::createFromTimestamp($currentPeriodStat->measuredAt);
                                $currentPeriodSum = $currentPeriodStat->value;
                                $currentTenantStatId = $currentPeriodStat->id;
                            }
                            $metric = $metric->withUsage(new \WHMCS\UsageBilling\Metrics\Usage($currentPeriodSum, $currentLastUpdated, $startOfMetricPeriod->copy(), $startOfMetricPeriod->copy()->endOfMonthMicro()));
                            $previousMonthlyMetricPeriod = $startOfMetricPeriod->copy()->subMonth();
                            $historicUsageEnd = $previousMonthlyMetricPeriod->copy()->endOfMonthMicro();
                            $historicUsageStart = $previousMonthlyMetricPeriod->copy()->startOfMonthMicro();
                            $previousStats = $stat->unbilledQueryBefore($startOfMetricPeriod, $usageTenant, $metric)->get();
                            foreach ($previousStats as $previous) {
                                $measured = \WHMCS\Carbon::createFromTimestamp($previous->measuredAt);
                                $start = $measured->copy()->startOfMonthMicro();
                                $end = $measured->copy()->endOfMonthMicro();
                                if ($start < $historicUsageStart) {
                                    $historicUsageStart = $start;
                                }
                                if ($historicUsageEnd < $end) {
                                    $historicUsageEnd = $end;
                                }
                                $historicUsageByPeriod[$previous->id] = new \WHMCS\UsageBilling\Metrics\Usage($previous->value, $measured->copy(), $start, $end);
                                $totalHistoricSum += $previous->value;
                            }
                            $totalHistoricUsage = new \WHMCS\UsageBilling\Metrics\Usage($totalHistoricSum, $historicUsageEnd, $historicUsageStart, $historicUsageEnd);
                        } else {
                            if ($metric->type() == \WHMCS\UsageBilling\Contracts\Metrics\MetricInterface::TYPE_SNAPSHOT) {
                                $currentValue = 0;
                                $currentLastUpdated = NULL;
                                $currentPeriodStat = $stat->unbilledValueFirst($usageTenant, $metric);
                                if ($currentPeriodStat) {
                                    $currentLastUpdated = \WHMCS\Carbon::createFromTimestamp($currentPeriodStat->measuredAt);
                                    $currentValue = $currentPeriodStat->value;
                                    $currentTenantStatId = $currentPeriodStat->id;
                                }
                                $nextinvoicedate = $this->nextInvoiceDate;
                                if ($nextinvoicedate != "0000-00-00") {
                                    $nextinvoicedate = \WHMCS\Carbon::createFromFormat("Y-m-d", $nextinvoicedate);
                                } else {
                                    $nextinvoicedate = \WHMCS\Carbon::now();
                                }
                                $nextinvoicedate->startOfDay();
                                $periodStart = $nextinvoicedate->copy()->subMonthNoOverflow();
                                if (!is_null($currentLastUpdated)) {
                                    $metric = $metric->withUsage(new \WHMCS\UsageBilling\Metrics\Usage($currentValue, $currentLastUpdated, $periodStart, $nextinvoicedate));
                                } else {
                                    $usage = new \WHMCS\UsageBilling\Metrics\NoUsage();
                                    $metric = $metric->withUsage($usage);
                                }
                            }
                        }
                    }
                }
                if (\WHMCS\UsageBilling\Invoice\ServiceUsage::isMultiHistory($mode) && $historicUsageByPeriod) {
                    if (\WHMCS\UsageBilling\Invoice\ServiceUsage::isAllUsage($mode)) {
                        $serviceMetrics[] = \WHMCS\UsageBilling\Service\ServiceMetric::factoryFromMetric($this, $metric, NULL, $usageItem, $currentTenantStatId);
                    }
                    foreach ($historicUsageByPeriod as $tenantStatId => $usage) {
                        $serviceMetrics[] = \WHMCS\UsageBilling\Service\ServiceMetric::factoryFromMetric($this, $metric->withUsage($usage), NULL, $usageItem, $tenantStatId);
                    }
                } else {
                    $serviceMetrics[] = \WHMCS\UsageBilling\Service\ServiceMetric::factoryFromMetric($this, $metric, $totalHistoricUsage, $usageItem, $currentTenantStatId);
                }
            }
        }
        return $serviceMetrics;
    }

    public function getLink()
    {
        return \App::get_admin_folder_name() . "/clientsservices.php?productselect=" . $this->id;
    }

    public function getUniqueIdentifierValue($uniqueIdField)
    {
        $uniqueIdValue = NULL;
        if (!$uniqueIdField) {
            $uniqueIdField = "domain";
        }
        if (substr($uniqueIdField, 0, 12) == "customfield.") {
            $customFieldName = substr($uniqueIdField, 12);
            $uniqueIdValue = $this->serviceProperties->get($customFieldName);
        } else {
            $uniqueIdValue = $this->getAttribute($uniqueIdField);
        }
        return $uniqueIdValue;
    }

    public function getHexColorFromStatus()
    {
        switch ($this->status) {
            case \WHMCS\Utility\Status::PENDING:
                return "#FFFFCC";
                break;
            case \WHMCS\Utility\Status::SUSPENDED:
                return "#CCFF99";
                break;
            case \WHMCS\Utility\Status::TERMINATED:
            case \WHMCS\Utility\Status::CANCELLED:
            case \WHMCS\Utility\Status::FRAUD:
                return "#FF9999";
                break;
            case \WHMCS\Utility\Status::COMPLETED:
                return "#CCC";
                break;
            default:
                return "#FFF";
        }
    }

    public function getParentalSiblingAttribute()
    {
        return \WHMCS\MarketConnect\Provision::findRelatedHostingService($this);
    }

    public function getProvisioningModuleName()
    {
        return $this->product != NULL ? $this->product->module : "";
    }

    public function getCustomActionData()
    {
        $data = [];
        $serverObj = new \WHMCS\Module\Server();
        $serverObj->loadByServiceID($this->id);
        if ($serverObj->functionExists("CustomActions")) {
            $customActionCollection = $serverObj->call("CustomActions", $serverObj->getServerParams($this->serverModel));
            $userPermissions = (new \WHMCS\Authentication\CurrentUser())->client()->pivot->getPermissions();
            foreach ($customActionCollection as $customAction) {
                foreach ($customAction->getPermissions() as $requiredPermission) {
                    if (!$userPermissions->hasPermission($requiredPermission)) {
                    }
                }
                $data[] = ["identifier" => $customAction->getIdentifier(), "display" => \Lang::trans($customAction->getDisplay()) ?? $customAction->getDisplay, "serviceid" => $this->id, "active" => $customAction->isActive()];
            }
        }
        return $data;
    }
}
