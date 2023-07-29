<?php

namespace WHMCS\UsageBilling\Pricing\Fixed;

class Bracket extends \WHMCS\UsageBilling\Pricing\AbstractPriceBracket
{
    use \WHMCS\Model\HasServiceEntityTrait;
    protected $table = "tblpricing_fixed_bracket";

    public function getPricingMorphClassname()
    {
        return "WHMCS\\UsageBilling\\Pricing\\Fixed\\Pricing";
    }

    public function servicePricing($service)
    {
        if ($service instanceof \WHMCS\Service\ConfigOption) {
            $service = $service->service;
        }
        $currency = $service->client->currencyrel;
        $billingCycle = $service->billingCycle;
        $pricing = $this->pricing()->where("currency", $currency->id)->where($billingCycle, ">", 0)->first();
        if ($pricing) {
            $pricing->setRelation("bracket", $this);
        }
        return $pricing;
    }
}
