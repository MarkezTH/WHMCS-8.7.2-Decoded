<?php

namespace WHMCS\UsageBilling\Pricing\Fixed;

class Pricing extends \WHMCS\Billing\Pricing
{
    protected $table = "tblpricing_fixed";

    public function bracket()
    {
        $this->belongsTo("WHMCS\\UsageBilling\\Pricing\\Fixed\\Bracket", "id", "relid");
    }

    public function pricingType()
    {
        return \WHMCS\Billing\PricingInterface::TYPE_USAGE;
    }
}
