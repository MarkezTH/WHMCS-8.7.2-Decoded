<?php

namespace WHMCS\Module\Gateway;

interface BalanceInterface extends \JsonSerializable
{
    public function getAmount($Price);

    public function getColor();

    public function getCurrencyCode();

    public function getCurrencyObject($Currency);

    public function getLabel();

    public static function factory($BalanceInterface, $amount, $currencyCode, $label, $color);
}
