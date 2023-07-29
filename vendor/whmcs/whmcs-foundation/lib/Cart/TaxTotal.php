<?php

namespace WHMCS\Cart;

class TaxTotal
{
    private $amount = NULL;
    private $description = NULL;
    private $percentage = 0;

    public function __construct($description, $percentage, \WHMCS\View\Formatter\Price $taxTotalAmount)
    {
        $this->setDescription($description ?: "Tax")->setPercentage($percentage)->setAmount($taxTotalAmount);
    }

    public function getAmount($Price)
    {
        if (!is_null($this->amount)) {
            return $this->amount;
        }
        return new \WHMCS\View\Formatter\Price(0);
    }

    public function setAmount($self, $amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($self, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function getPercentage()
    {
        return $this->percentage;
    }

    public function setPercentage($self, $percent)
    {
        $this->percentage = $percent;
        return $this;
    }
}
