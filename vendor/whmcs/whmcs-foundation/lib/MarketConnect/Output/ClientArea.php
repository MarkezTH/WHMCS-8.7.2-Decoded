<?php

namespace WHMCS\MarketConnect\Output;

class ClientArea extends \WHMCS\ClientArea
{
    private $pricedCurrencyIds = NULL;

    protected function getCurrencyOptions()
    {
        $currencyOptions = parent::getCurrencyOptions();
        $pricedCurrencyOptions = [];
        if (is_array($currencyOptions)) {
            if (is_null($this->pricedCurrencyIds)) {
                $this->pricedCurrencyIds = \WHMCS\Database\Capsule::table("tblpricing")->pluck("currency")->all();
            }
            $pricedCurrencyOptions = array_filter($currencyOptions, function ($value) {
                return in_array($value["id"], $this->pricedCurrencyIds);
            });
        }
        return 1 < count($pricedCurrencyOptions) ? $pricedCurrencyOptions : [];
    }
}
