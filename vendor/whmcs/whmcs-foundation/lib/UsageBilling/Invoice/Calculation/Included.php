<?php

namespace WHMCS\UsageBilling\Invoice\Calculation;

class Included extends Charge
{
    public function __construct($consumed = 0, \WHMCS\Billing\PricingInterface $price = NULL, \WHMCS\UsageBilling\Contracts\Pricing\PriceBracketInterface $bracket = NULL, $isIncluded = true)
    {
        parent::__construct($consumed, NULL, NULL, true);
    }
}
