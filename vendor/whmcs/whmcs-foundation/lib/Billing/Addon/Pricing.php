<?php

namespace WHMCS\Billing\Addon;

class Pricing extends \WHMCS\Billing\Pricing
{
    protected $columnMap = ["addonId" => "relid", "monthlySetupFee" => "msetupfee", "quarterlySetupFee" => "qsetupfee", "semiAnnualSetupFee" => "ssetupfee", "annualSetupFee" => "asetupfee", "biennialSetupFee" => "bsetupfee", "triennialSetupFee" => "tsetupfee"];
    protected $types = NULL;

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("only_addons", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->where("type", self::TYPE_ADDON)->orderBy("tblpricing.id");
        });
    }

    public function pricingType()
    {
        return self::TYPE_ADDON;
    }

    public function supportedTypes()
    {
        return $this->types;
    }

    public function addon($BelongsTo)
    {
        return $this->belongsTo("WHMCS\\Product\\Addon", "relid");
    }

    public function scopeOfAddonId(\Illuminate\Database\Eloquent\Builder $query, int $addonId)
    {
        return $query->where("relid", $addonId);
    }

    public function scopeOfCurrencyId(\Illuminate\Database\Eloquent\Builder $query, int $currencyId = 1)
    {
        return $query->where("currency", $currencyId);
    }
}
