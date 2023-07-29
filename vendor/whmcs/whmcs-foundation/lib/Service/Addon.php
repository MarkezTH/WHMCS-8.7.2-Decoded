<?php

namespace WHMCS\Service;

class Addon extends \WHMCS\Model\AbstractModel implements \WHMCS\ServiceInterface
{
    use \WHMCS\Domains\Traits\DomainTraits;
    use Traits\ProvisioningTraits;
    protected $table = "tblhostingaddons";
    protected $columnMap = ["orderId" => "orderid", "serviceId" => "hostingid", "clientId" => "userid", "recurringFee" => "recurring", "registrationDate" => "regdate", "prorataDate" => "proratadate", "applyTax" => "tax", "terminationDate" => "termination_date", "paymentGateway" => "paymentmethod", "serverId" => "server", "productId" => "addonid", "subscriptionId" => "subscriptionid", "firstPaymentAmount" => "firstpaymentamount"];
    protected $dates = ["regDate", "registrationDate", "nextdueDate", "nextinvoiceDate", "terminationDate", "prorataDate"];
    protected $appends = ["domainPunycode", "serviceProperties", "provisioningType"];

    public function getServiceActual($Service)
    {
        return $this->service;
    }

    public function getServiceSurrogate($Service)
    {
        return $this->getServiceActual();
    }

    public function hasServiceSurrogate()
    {
        return false;
    }

    public function getServiceClient($Client)
    {
        return $this->client;
    }

    public function getServiceProperties($Properties)
    {
        return $this->serviceProperties;
    }

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Service\\Observers\\SslOrderAddonObserver");
    }

    public function scopeUserId(\Illuminate\Database\Eloquent\Builder $query, $userId)
    {
        return $query->where("userid", "=", $userId);
    }

    public function scopeOfService(\Illuminate\Database\Eloquent\Builder $query, $serviceId)
    {
        return $query->where("hostingid", $serviceId);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("status", Service::STATUS_ACTIVE);
    }

    public function scopeMarketConnect(\Illuminate\Database\Eloquent\Builder $query)
    {
        $marketConnectAddonIds = \WHMCS\Product\Addon::marketConnect()->pluck("id");
        return $query->whereIn("addonid", $marketConnectAddonIds);
    }

    public function scopeIsConsideredActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereIn("status", [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED]);
    }

    public function scopeIsNotRecurring($query)
    {
        return $query->whereIn("billingcycle", ["Free", "Free Account", "One Time"]);
    }

    public function service()
    {
        return $this->belongsTo("WHMCS\\Service\\Service", "hostingid");
    }

    public function productAddon()
    {
        return $this->belongsTo("WHMCS\\Product\\Addon", "addonid");
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function customFieldValues()
    {
        return $this->hasMany("WHMCS\\CustomField\\CustomFieldValue", "relid");
    }

    protected function getCustomFieldType()
    {
        return "addon";
    }

    protected function getCustomFieldRelId()
    {
        return $this->addonId;
    }

    public function order()
    {
        return $this->belongsTo("WHMCS\\Order\\Order", "orderid");
    }

    public function paymentGateway()
    {
        return $this->hasMany("WHMCS\\Module\\GatewaySetting", "gateway", "paymentmethod");
    }

    public function getServicePropertiesAttribute()
    {
        return new Properties($this);
    }

    public function ssl()
    {
        return $this->hasMany("WHMCS\\Service\\Ssl");
    }

    public function invoices($BelongsToMany)
    {
        return $this->belongsToMany("WHMCS\\Billing\\Invoice", "tblinvoiceitems", "relid", "invoiceid")->wherePivot("type", \WHMCS\Billing\Invoice\Item::TYPE_SERVICE_ADDON);
    }

    public function canBeUpgraded()
    {
        return $this->status == "Active";
    }

    public function isService()
    {
        return false;
    }

    public function isAddon()
    {
        return true;
    }

    public function serverModel()
    {
        return $this->hasOne("\\WHMCS\\Product\\Server", "id", "server");
    }

    public function failedActions()
    {
        return $this->hasMany("WHMCS\\Module\\Queue", "service_id")->where("service_type", "=", "addon");
    }

    public function moduleConfiguration()
    {
        return $this->hasMany("WHMCS\\Config\\Module\\ModuleConfiguration", "entity_id", "addonid")->where("entity_type", "=", "addon");
    }

    public function legacyProvision()
    {
        try {
            if (!function_exists("ModuleCallFunction")) {
                require_once ROOTDIR . "/includes/modulefunctions.php";
            }
            return ModuleCallFunction("Create", $this->serviceId, [], $this->id);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function isRecurring()
    {
        return !in_array($this->billingCycle, [\WHMCS\Billing\Cycles::DISPLAY_FREE, \WHMCS\Billing\Cycles::DISPLAY_ONETIME]);
    }

    public function getLink()
    {
        return \App::get_admin_folder_name() . "/clientsservices.php?productselect=a" . $this->id;
    }

    public function recalculateRecurringPrice()
    {
        try {
            if (!$this->addonId) {
                throw new \InvalidArgumentException();
            }
            $this->loadMissing(["productAddon", "service", "service.client"]);
            $pricing = $this->productAddon->pricing($this->service->client->currencyrel->toArray());
            $price = $pricing->byCycle($this->billingCycle);
            if ($price instanceof \WHMCS\Product\Pricing\Price) {
                $price = $price->price()->getValue();
            }
            if (valueIsZero($price) || $price < 0) {
                $price = 0;
            }
            return $price * $this->qty;
        } catch (\Throwable $t) {
            return $this->recurringFee;
        }
    }

    public function getProvisioningTypeAttribute()
    {
        $provisioningType = "standard";
        $moduleConfiguration = $this->moduleConfiguration()->where("setting_name", "provisioningType")->first();
        if ($moduleConfiguration) {
            $provisioningType = $moduleConfiguration->value;
        }
        return $provisioningType;
    }
}
