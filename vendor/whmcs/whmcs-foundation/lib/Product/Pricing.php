<?php

namespace WHMCS\Product;

class Pricing
{
    protected $product = NULL;
    protected $pricing = NULL;
    protected $loadedQty = NULL;
    protected $qty = 1;
    protected $entityType = "product";
    protected $paymentTypeKey = "paymentType";
    protected $currency = NULL;

    public function __construct($product, $currency)
    {
        if ($product instanceof Product) {
            $this->product = $product;
        } else {
            if ($product instanceof Addon) {
                $this->product = $product;
                $this->entityType = "addon";
                $this->paymentTypeKey = "billingCycle";
            } else {
                throw new \WHMCS\Exception("Product input must be of type Product or Addon");
            }
        }
        $this->currency = $currency;
        $this->loadPricing();
    }

    protected function loadPricing()
    {
        $qty = $this->getQuantity();
        if (is_array($this->pricing) && $this->loadedQty === $qty) {
            return NULL;
        }
        $this->loadedQty = $qty;
        $paymentTypeKey = $this->paymentTypeKey;
        $currency = $this->currency;
        $entityType = $this->entityType;
        switch ($this->product->{$paymentTypeKey}) {
            case "free":
                $this->pricing = ["free" => ["cycle" => "free", "setupfee" => new \WHMCS\View\Formatter\Price(0), "price" => new \WHMCS\View\Formatter\Price(0)]];
                break;
            case "onetime":
                $productPricing = new \WHMCS\Pricing();
                $productPricing->loadPricing($entityType, $this->product->id, $currency, $qty);
                $this->pricing = ["onetime" => $productPricing->getOneTimePricing()];
                break;
            case "recurring":
            default:
                $productPricing = new \WHMCS\Pricing();
                $productPricing->loadPricing($entityType, $this->product->id, $currency, $qty);
                $this->pricing = $productPricing->getAllCycleOptionsIndexedByCycle();
                $this->consolidateToMonthlyPrice();
        }
    }

    protected function consolidateToMonthlyPrice()
    {
        if ($this->entityType != "addon" || $this->addonUsesMonthlyPriceOnly() !== true) {
            return NULL;
        }
        $normalizedCycle = (new \WHMCS\Billing\Cycles())->getNormalisedBillingCycle($this->product->billingCycle);
        $this->pricing[\WHMCS\Billing\Cycles::CYCLE_MONTHLY]["cycle"] = $normalizedCycle;
        $this->pricing = [$normalizedCycle => $this->pricing[\WHMCS\Billing\Cycles::CYCLE_MONTHLY]];
    }

    protected function addonUsesMonthlyPriceOnly()
    {
        if (empty($this->pricing[\WHMCS\Billing\Cycles::CYCLE_MONTHLY])) {
            return NULL;
        }
        $cycles = new \WHMCS\Billing\Cycles();
        return $cycles->isRecurring($cycles->getNormalisedBillingCycle($this->product->billingCycle));
    }

    public function allAvailableCycles()
    {
        $cyclesToReturn = [];
        $this->loadPricing();
        foreach ($this->pricing as $cycle => $data) {
            $cyclesToReturn[] = new Pricing\Price($data);
        }
        return $cyclesToReturn;
    }

    public function months($months)
    {
        $map = ["onetime", "monthly", "3" => "quarterly", "6" => "semiannual", "12" => "annual", "24" => "biennial", "36" => "triennial", "100" => "free"];
        $key = $map[$months];
        return $this->{$key}();
    }

    public function byCycle($cycle)
    {
        $cycle = str_replace("lly", "l", $cycle);
        $cycle = str_replace("-", "", $cycle);
        if (method_exists($this, $cycle)) {
            return $this->{$cycle}();
        }
        return NULL;
    }

    public function free()
    {
        return NULL;
    }

    public function onetime()
    {
        $this->loadPricing();
        return isset($this->pricing["onetime"]) ? new Pricing\Price($this->pricing["onetime"]) : NULL;
    }

    public function monthly()
    {
        $this->loadPricing();
        return isset($this->pricing["monthly"]) ? new Pricing\Price($this->pricing["monthly"]) : NULL;
    }

    public function quarterly()
    {
        $this->loadPricing();
        return isset($this->pricing["quarterly"]) ? new Pricing\Price($this->pricing["quarterly"]) : NULL;
    }

    public function semiannual()
    {
        $this->loadPricing();
        return isset($this->pricing["semiannually"]) ? new Pricing\Price($this->pricing["semiannually"]) : NULL;
    }

    public function semiannually()
    {
        return $this->semiannual();
    }

    public function annual()
    {
        $this->loadPricing();
        return isset($this->pricing["annually"]) ? new Pricing\Price($this->pricing["annually"]) : NULL;
    }

    public function annually()
    {
        return $this->annual();
    }

    public function biennial()
    {
        $this->loadPricing();
        return isset($this->pricing["biennially"]) ? new Pricing\Price($this->pricing["biennially"]) : NULL;
    }

    public function biennially()
    {
        $this->loadPricing();
        return $this->biennial();
    }

    public function triennial()
    {
        $this->loadPricing();
        return isset($this->pricing["triennially"]) ? new Pricing\Price($this->pricing["triennially"]) : NULL;
    }

    public function triennially()
    {
        return $this->triennial();
    }

    public function best()
    {
        $this->loadPricing();
        if (array_key_exists("onetime", $this->pricing)) {
            return $this->onetime();
        }
        $bestPrice = NULL;
        $bestPriceCycle = NULL;
        $bestPriceInfo = NULL;
        foreach ($this->pricing as $cycle => $priceinfo) {
            $monthlyBreakdown = NULL;
            if (isset($priceinfo["breakdown"]["monthly"])) {
                $monthlyBreakdown = $priceinfo["breakdown"]["monthly"];
            }
            $monthlyBreakdown = $monthlyBreakdown instanceof \WHMCS\View\Formatter\Price ? $monthlyBreakdown->toNumeric() : NULL;
            if (is_null($bestPrice) || !is_null($monthlyBreakdown) && $monthlyBreakdown < $bestPrice) {
                $bestPrice = $monthlyBreakdown;
                $bestPriceInfo = $priceinfo;
            }
            $yearlyBreakdown = NULL;
            if (isset($priceinfo["breakdown"]["yearly"])) {
                $yearlyBreakdown = $priceinfo["breakdown"]["yearly"];
            }
            $yearlyBreakdown = $yearlyBreakdown instanceof \WHMCS\View\Formatter\Price ? $yearlyBreakdown->toNumeric() : NULL;
            if (is_null($bestPrice) || !is_null($yearlyBreakdown) && $yearlyBreakdown < $bestPrice) {
                $bestPrice = $yearlyBreakdown;
                $bestPriceInfo = $priceinfo;
            }
        }
        if (is_null($bestPriceInfo)) {
            return NULL;
        }
        return new Pricing\Price($bestPriceInfo);
    }

    public function first()
    {
        return $this->allAvailableCycles()[0];
    }

    public function getHighestMonthly()
    {
        $this->loadPricing();
        if (is_null($worstPrice)) {
            foreach ($this->pricing as $cycle => $priceInfo) {
                $monthlyBreakdown = $priceInfo["breakdown"]["monthly"];
                $monthlyBreakdown = $monthlyBreakdown instanceof \WHMCS\View\Formatter\Price ? $monthlyBreakdown->toNumeric() : NULL;
                if (is_null($worstPrice) || !is_null($monthlyBreakdown) && $worstPrice < $monthlyBreakdown) {
                    $worstPrice = $monthlyBreakdown;
                }
            }
        }
        return $worstPrice;
    }

    public function setQuantity($self, $qty)
    {
        if ($qty && 1 <= $qty) {
            $this->qty = $qty;
        }
        return $this;
    }

    public function getQuantity()
    {
        return $this->qty;
    }
}
