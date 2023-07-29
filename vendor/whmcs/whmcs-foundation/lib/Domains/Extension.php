<?php

namespace WHMCS\Domains;

class Extension extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbldomainpricing";
    protected $columnMap = ["supportsDnsManagement" => "dnsmanagement", "supportsEmailForwarding" => "emailforwarding", "supportsIdProtection" => "idprotection", "requiresEppCode" => "eppcode", "autoRegistrationRegistrar" => "autoreg", "gracePeriod" => "grace_period", "gracePeriodFee" => "grace_period_fee", "redemptionGracePeriod" => "redemption_grace_period", "redemptionGracePeriodFee" => "redemption_grace_period_fee", "topLevelId" => "top_level_id"];
    protected $appends = ["defaultGracePeriod", "defaultRedemptionGracePeriod", "pricing"];
    protected $casts = ["grace_period_fee" => "float", "gracePeriodFee" => "float", "redemption_grace_period_fee" => "float", "redemptionGracePeriodFee" => "float"];
    protected $fillable = ["extension"];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tbldomainpricing.order")->orderBy("tbldomainpricing.id");
        });
    }

    public function getDefaultGracePeriodAttribute()
    {
        $tld = ltrim($this->getRawAttribute("extension"), ".");
        return \WHMCS\Domain\TopLevel\GracePeriod::getForTld($tld);
    }

    public function getDefaultRedemptionGracePeriodAttribute()
    {
        $tld = ltrim($this->getRawAttribute("extension"), ".");
        return \WHMCS\Domain\TopLevel\RedemptionGracePeriod::getForTld($tld);
    }

    public function getPricingAttribute()
    {
        return (new DomainPricing(new Domain("sample" . $this->extension, NULL, false)))->toArray();
    }

    public function getGracePeriodFeeAttribute()
    {
        if (\WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")) {
            return -1;
        }
        return $this->attributes["grace_period_fee"] ?? NULL;
    }

    public function setGracePeriodFeeAttribute($value)
    {
        $this->attributes["grace_period_fee"] = $value;
    }

    public function getRedemptionGracePeriodFeeAttribute()
    {
        if (\WHMCS\Config\Setting::getValue("DisableDomainGraceAndRedemptionFees")) {
            return -1;
        }
        return $this->attributes["redemption_grace_period_fee"] ?? NULL;
    }

    public function setRedemptionGracePeriodFeeAttribute($value)
    {
        $this->attributes["redemption_grace_period_fee"] = $value;
    }

    public function price()
    {
        return $this->hasMany("WHMCS\\Domains\\Extension\\Pricing", "relid");
    }
}
