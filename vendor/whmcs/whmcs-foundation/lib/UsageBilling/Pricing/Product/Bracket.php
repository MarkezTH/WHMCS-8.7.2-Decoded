<?php

namespace WHMCS\UsageBilling\Pricing\Product;

class Bracket extends \WHMCS\UsageBilling\Pricing\AbstractPriceBracket
{
    use \WHMCS\Model\HasProductEntityTrait;
    protected $table = "tblpricing_bracket";

    public function getPricingMorphClassname()
    {
        return "WHMCS\\UsageBilling\\Pricing\\Product\\Pricing";
    }

    public function createMetricUsageFixedBracket(\WHMCS\UsageBilling\Service\MetricUsage $metricUsage)
    {
        $metricUsageBracket = \WHMCS\UsageBilling\Pricing\Fixed\Bracket::create(["floor" => (int) $this->floor, "ceiling" => (int) $this->ceiling, "rel_type" => \WHMCS\Contracts\ProductServiceTypes::TYPE_SERVICE_METRICUSAGE, "rel_id" => $metricUsage->id]);
        foreach ($this->pricing as $pricing) {
            $pricing->createFixedPricing($metricUsageBracket);
        }
    }
}
