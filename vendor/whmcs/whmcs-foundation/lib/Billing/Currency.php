<?php

namespace WHMCS\Billing;

class Currency extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblcurrencies";
    public $timestamps = false;
    protected $fillable = ["rate"];
    const DEFAULT_CURRENCY_ID = 1;

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Billing\\Observers\\CurrencyObserver");
    }

    public function scopeDefaultCurrency($query)
    {
        return $query->where("default", 1);
    }

    public function scopeDefaultSorting($query)
    {
        return $query->orderBy("default", "desc")->orderBy("code");
    }

    public static function validateCurrencyCode(&$currencyCode)
    {
        $currencyCode = strtoupper(trim($currencyCode));
        return (bool) preg_match("/^[A-Z]{2,4}\$/", $currencyCode);
    }

    public static function factoryForClientArea()
    {
        $currencyId = \Auth::client() ? \Auth::client()->currencyId : \WHMCS\Session::get("currency");
        if (!$currencyId) {
            try {
                $currencyModel = self::defaultCurrency()->firstOrFail();
                return $currencyModel;
            } catch (\Throwable $e) {
                $currencyId = self::DEFAULT_CURRENCY_ID;
            }
        }
        return self::find((int) $currencyId);
    }

    public function convertTo($amount, Currency $currency)
    {
        $amount /= $this->rate;
        $amount *= $currency->rate;
        return (double) format_as_currency($amount);
    }
}
