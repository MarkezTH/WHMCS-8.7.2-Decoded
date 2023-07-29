<?php

namespace WHMCS\Cart;

class Discount
{
    private $name = NULL;
    private $amount = NULL;

    public function __construct($name, \WHMCS\View\Formatter\Price $price)
    {
        $this->name = $name;
        $this->amount = $price;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($self, $name)
    {
        $this->name = $name;
        return $this;
    }

    public function getAmount($Price)
    {
        return $this->amount;
    }

    public function setAmount($self, $amount)
    {
        $this->amount = $amount;
        return $this;
    }
}
