<?php

namespace WHMCS\Environment\WHMCS;

class UsageBilling extends \WHMCS\Environment\Component
{
    const NAME = "UsageBilling";

    public function __construct()
    {
        parent::__construct(static::NAME);
        $this->addTopic("MetricSettings", [$this, "topicSettings"])->addTopic("ProductMetrics", [$this, "topicProductMetrics"]);
    }

    protected function topicSettings()
    {
        return [["key" => \WHMCS\UsageBilling\MetricUsageSettings::NAME_COLLECTION, "value" => \WHMCS\UsageBilling\MetricUsageSettings::isCollectionEnable()], ["key" => \WHMCS\UsageBilling\MetricUsageSettings::NAME_INVOICING, "value" => \WHMCS\UsageBilling\MetricUsageSettings::isInvoicingEnabled()]];
    }

    protected function topicProductMetrics()
    {
        $productCache = [];
        $metrics = [];
        $usageItems = \WHMCS\UsageBilling\Product\UsageItem::all();
        foreach ($usageItems as $usageItem) {
            $hasFree = $hasOnetime = $hasNonMonthlyRecurring = false;
            if (!($usageItem->isHidden || $usageItem->rel_type !== \WHMCS\Contracts\ProductServiceTypes::TYPE_PRODUCT_PRODUCT)) {
                if (!isset($productCache[$usageItem->rel_id])) {
                    $product = $usageItem->relationEntity;
                    $productCache[$usageItem->rel_id] = $product;
                } else {
                    $product = $productCache[$usageItem->rel_id];
                }
                if ($product) {
                    if (!$product->isHidden && !$product->isRetired) {
                        $pricing = $usageItem->pricingSchema;
                        $schemaType = $pricing->schemaType();
                        $included = $usageItem->included;
                        $cycleType = $product->paymentType;
                        $cycles = $product->getAvailableBillingCycles();
                        if ($cycleType == "free") {
                            $hasFree = true;
                        } else {
                            if ($cycleType == "onetime") {
                                $hasOnetime = true;
                            } else {
                                if ($cycleType == "recurring") {
                                    foreach ($cycles as $cycle) {
                                        if ($cycle !== "monthly") {
                                            $hasNonMonthlyRecurring = true;
                                        }
                                    }
                                }
                            }
                        }
                        $metrics[] = ["key" => $usageItem->metric, "value" => ["hasIncluded" => !valueIsZero($included), "schemaType" => $schemaType, "hasMultipleBrackets" => 1 < $pricing->count(), "hasFreeCycle" => $hasFree, "hasOnetimeCycle" => $hasOnetime, "hasNonMonthlyRecurringCycle" => $hasNonMonthlyRecurring, "module" => $usageItem->moduleName, "productType" => $product->type]];
                    }
                }
            }
        }
        return $metrics;
    }
}
