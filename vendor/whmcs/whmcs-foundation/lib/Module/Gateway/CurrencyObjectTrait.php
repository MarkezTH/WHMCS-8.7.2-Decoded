<?php

namespace WHMCS\Module\Gateway;

trait CurrencyObjectTrait
{
    public function getCurrencyObject($Currency)
    {
        if (empty($currencyCache[$this->getCurrencyCode()])) {
            try {
                $currencyCache[$this->getCurrencyCode()] = \WHMCS\Billing\Currency::where("code", $this->getCurrencyCode())->firstOrFail();
            } catch (\Throwable $t) {
                return NULL;
            }
        }
        return $currencyCache[$this->getCurrencyCode()];
    }
}
