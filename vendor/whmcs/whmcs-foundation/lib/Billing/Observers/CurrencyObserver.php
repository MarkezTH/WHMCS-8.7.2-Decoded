<?php

namespace WHMCS\Billing\Observers;

class CurrencyObserver
{
    public function created(\WHMCS\Billing\Currency $currency)
    {
        \WHMCS\MarketConnect\MarketConnect::addPricingForNewCurrency($currency);
    }
}
