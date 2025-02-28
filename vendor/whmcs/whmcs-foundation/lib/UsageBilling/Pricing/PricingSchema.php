<?php

namespace WHMCS\UsageBilling\Pricing;

class PricingSchema extends \Illuminate\Database\Eloquent\Collection implements \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface
{
    private $isFree = NULL;
    private $firstCostBracket = NULL;

    public static function getSchemaTypes()
    {
        return [\WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_SIMPLE, \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_FLAT, \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_GRADUATED];
    }

    public function schemaType()
    {
        if ($this->count()) {
            $type = $this->first()->schemaType();
            if (!$type || !in_array($type, static::getSchemaTypes())) {
                $type = \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_FLAT;
            }
        } else {
            $type = \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_SIMPLE;
        }
        return $type;
    }

    public function isFree()
    {
        if (is_null($this->isFree)) {
            foreach ($this as $bracket) {
                if (!$bracket->isFree()) {
                    $this->firstCostBracket = $bracket;
                    $this->isFree = false;
                    return false;
                }
            }
            $this->isFree = true;
        }
        return $this->isFree;
    }

    public function freeLimit()
    {
        if ($this->isFree()) {
            return NULL;
        }
        $bracket = $this->firstCostBracket;
        return $bracket->floor;
    }

    public function firstCostBracket()
    {
        if (!is_null($this->freeLimit())) {
            return $this->firstCostBracket;
        }
        return NULL;
    }

    public function getStubInclusiveBracket()
    {
        $firstBracket = $this->first();
        if ($firstBracket && !valueIsZero($firstBracket->floor)) {
            $stub = new Product\Bracket();
            $stub->floor = 0;
            $stub->ceiling = $firstBracket->floor;
            $stubPricings = [];
            foreach (\WHMCS\Billing\Currency::all() as $currency) {
                $origPricing = $firstBracket->pricingForCurrencyId($currency->id);
                if ($origPricing) {
                    $stubPrice = new Product\Pricing();
                    $stubPrice->id = 0;
                    $stubPrice->exists = false;
                    $stubPrice->type = $origPricing->type;
                    $stubPrice->currencyId = $origPricing->currencyId;
                    $stubPrice->relid = $origPricing->relid;
                    foreach ($origPricing->priceFields() as $field) {
                        $stubPrice->{$field} = 0;
                    }
                    foreach ($stubPrice->setupFields() as $field) {
                        $stubPrice->{$field} = 0;
                    }
                    $stubPricings[] = $stubPrice;
                }
            }
            $stub->setRelation("pricing", new \Illuminate\Database\Eloquent\Collection($stubPricings));
            return $stub;
        } else {
            return NULL;
        }
    }

    public function fixedUsagePricing()
    {
        $firstBracket = $this->first();
        if (!$firstBracket) {
            return NULL;
        }
        $metricUsage = $firstBracket->relationEntity;
        if (!$metricUsage instanceof \WHMCS\UsageBilling\Service\MetricUsage) {
            return NULL;
        }
        $parentService = $metricUsage->relationEntity;
        $units = $metricUsage->value;
        $brackets = $this->filter(function (\WHMCS\UsageBilling\Contracts\Pricing\PriceBracketInterface $model) use($units) {
            return $model->withinRange($units);
        });
        if ($brackets->isEmpty()) {
            return NULL;
        }
        if (1 < $brackets->count()) {
            $bracket = $brackets->where("floor", $brackets->max("floor"))->first();
        } else {
            $bracket = $brackets->first();
        }
        return $bracket->servicePricing($parentService);
    }
}
