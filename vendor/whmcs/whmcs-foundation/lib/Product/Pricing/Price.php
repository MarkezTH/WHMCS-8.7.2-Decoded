<?php

namespace WHMCS\Product\Pricing;

class Price
{
    protected $price = NULL;

    public function __construct($price)
    {
        $this->price = $price;
        if (!isset($price["breakdown"]) && !is_null($price["price"])) {
            $this->price["breakdown"] = [];
            if ($this->isYearly()) {
                $cycleMonths = (new \WHMCS\Billing\Cycles())->getNumberOfMonths($this->cycle());
                $yearlyPrice = $price["price"]->toNumeric() / ($cycleMonths / 12);
                $this->price["breakdown"]["yearly"] = new \WHMCS\View\Formatter\Price($yearlyPrice, $this->price()->getCurrency());
            } else {
                if ($this->isMonthly()) {
                    $cycleMonths = (new \WHMCS\Billing\Cycles())->getNumberOfMonths($this->cycle());
                    $monthlyPrice = $price["price"]->toNumeric() / $cycleMonths;
                    $this->price["breakdown"]["monthly"] = new \WHMCS\View\Formatter\Price($monthlyPrice, $this->price()->getCurrency());
                } else {
                    $this->price["breakdown"]["monthly"] = new \WHMCS\View\Formatter\Price($price["price"]->toNumeric(), $this->price()->getCurrency());
                }
            }
        }
    }

    public function cycle()
    {
        return $this->price["cycle"];
    }

    public function isFree()
    {
        return $this->cycle() == "free";
    }

    public function isOneTime()
    {
        return $this->cycle() == "onetime";
    }

    public function isRecurring()
    {
        return in_array($this->cycle(), (new \WHMCS\Billing\Cycles())->getRecurringSystemBillingCycles());
    }

    public function setup()
    {
        return $this->price["setupfee"] ?? NULL;
    }

    public function price()
    {
        return $this->price["price"];
    }

    public function breakdown()
    {
        return $this->price["breakdown"];
    }

    public function toPrefixedString()
    {
        $priceString = "";
        $price = $this->price();
        if (!is_null($price)) {
            $priceString .= $price->toPrefixed();
            if ($this->isRecurring()) {
                $priceString .= "/" . $this->getShortCycle();
            }
        }
        $setup = $this->setup();
        if (!is_null($setup) && 0 < $setup->toNumeric()) {
            $priceString .= " + " . $setup->toPrefixed() . " " . \Lang::trans("ordersetupfee");
        }
        return $priceString;
    }

    public function toSuffixedString()
    {
        $priceString = "";
        $price = $this->price();
        if (!is_null($price)) {
            $priceString .= $price->toSuffixed();
            if ($this->isRecurring()) {
                $priceString .= "/" . $this->getShortCycle();
            }
        }
        $setup = $this->setup();
        if (!is_null($setup) && 0 < $setup->toNumeric()) {
            $priceString .= " + " . $setup->toSuffixed() . " " . \Lang::trans("ordersetupfee");
        }
        return $priceString;
    }

    public function toFullString()
    {
        $priceString = "";
        if ($this->isFree()) {
            return \Lang::trans("orderfree");
        }
        $price = $this->price();
        if (!is_null($price)) {
            $priceString .= $price->toFull();
            if ($this->isRecurring()) {
                $priceString .= "/" . $this->getShortCycle();
            } else {
                if ($this->isOneTime()) {
                    $priceString .= " " . \Lang::trans("orderpaymenttermonetime");
                }
            }
        }
        $setup = $this->setup();
        if (!is_null($setup) && 0 < $setup->toNumeric()) {
            $priceString .= " + " . $setup->toFull() . " " . \Lang::trans("ordersetupfee");
        }
        return $priceString;
    }

    public function getShortCycle()
    {
        $this->cycle();
        switch ($this->cycle()) {
            case "monthly":
                return \Lang::trans("pricingCycleShort.monthly");
                break;
            case "quarterly":
                return \Lang::trans("pricingCycleShort.quarterly");
                break;
            case "semiannually":
                return \Lang::trans("pricingCycleShort.semiannually");
                break;
            case "annually":
                return \Lang::trans("pricingCycleShort.annually");
                break;
            case "biennially":
                return \Lang::trans("pricingCycleShort.biennially");
                break;
            case "triennially":
                return \Lang::trans("pricingCycleShort.triennially");
                break;
        }
    }

    public function isYearly()
    {
        return in_array($this->cycle(), ["annually", "biennially", "triennially"]);
    }

    public function isMonthly()
    {
        return in_array($this->cycle(), ["monthly", "quarterly", "semiannually"]);
    }

    public function cycleInYears()
    {
        $this->cycle();
        switch ($this->cycle()) {
            case "annually":
                return \Lang::trans("pricingCycleLong.annually");
                break;
            case "biennially":
                return \Lang::trans("pricingCycleLong.biennially");
                break;
            case "triennially":
                return \Lang::trans("pricingCycleLong.triennially");
                break;
        }
    }

    public function yearlyPrice()
    {
        return $this->breakdown()["yearly"]->toFull() . "/" . \Lang::trans("pricingCycleShort.annually");
    }

    public function cycleInMonths()
    {
        $this->cycle();
        switch ($this->cycle()) {
            case "monthly":
                return \Lang::trans("pricingCycleLong.monthly");
                break;
            case "quarterly":
                return \Lang::trans("pricingCycleLong.quarterly");
                break;
            case "semiannually":
                return \Lang::trans("pricingCycleLong.semiannually");
                break;
        }
    }

    public function monthlyPrice()
    {
        return $this->breakdown()["monthly"]->toFull() . "/" . \Lang::trans("pricingCycleShort.monthly");
    }

    public function oneTimePrice()
    {
        return $this->price()->toFull() . " " . \Lang::trans("orderpaymenttermonetime");
    }

    public function breakdownPrice()
    {
        if ($this->isYearly()) {
            return $this->yearlyPrice();
        }
        return $this->monthlyPrice();
    }

    public function breakdownPriceNumeric()
    {
        if ($this->isYearly()) {
            return (double) $this->breakdown()["yearly"]->toNumeric();
        }
        return (double) $this->breakdown()["monthly"]->toNumeric();
    }

    public function calculatePercentageDifference($price, $decimalPlaces = 0)
    {
        return -1 * (new \WHMCS\Billing\Pricing\Markup())->amount($price)->rounding(5)->decimalPlaces($decimalPlaces)->percentageDifference($this->breakdown()["monthly"]->toNumeric());
    }
}
