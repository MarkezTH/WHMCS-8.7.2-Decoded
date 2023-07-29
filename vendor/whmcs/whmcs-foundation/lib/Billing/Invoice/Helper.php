<?php

namespace WHMCS\Billing\Invoice;

class Helper
{
    public static function convertCurrency($amount, \WHMCS\Billing\Currency $currency, \WHMCS\Billing\Invoice $invoice)
    {
        $userCurrency = $invoice->client->currencyrel;
        if ($userCurrency->id != $currency->id) {
            $amount = convertCurrency($amount, $currency->id, $userCurrency->id);
            if ($invoice->total < $amount + 1 && $amount - 1 < $invoice->total) {
                $amount = $invoice->total;
            }
        }
        return $amount;
    }
}
