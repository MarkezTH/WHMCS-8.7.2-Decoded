<?php

namespace WHMCS\Product;

interface PricedEntityInterface
{
    public function isFree();

    public function isOneTime();

    public function getAvailableBillingCycles();

    public function pricing($Pricing, $currency);
}
