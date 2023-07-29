<?php

namespace WHMCS\UsageBilling\Contracts\Invoice;

interface UsageItemInterface
{
    public function getUsageCalculations(\WHMCS\UsageBilling\Service\ServiceMetric $serviceMetric, \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface $pricingSchema);
}
