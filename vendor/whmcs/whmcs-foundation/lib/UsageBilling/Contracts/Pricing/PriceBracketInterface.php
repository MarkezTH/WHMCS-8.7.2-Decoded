<?php

namespace WHMCS\UsageBilling\Contracts\Pricing;

interface PriceBracketInterface
{
    public function schemaType();

    public function withinRange($value, $unitType);

    public function belowRange($value, $unitType);

    public function pricing();

    public function isFree();

    public function pricingForCurrencyId($id);

    public function newCollection();

    public function relationEntity();
}
