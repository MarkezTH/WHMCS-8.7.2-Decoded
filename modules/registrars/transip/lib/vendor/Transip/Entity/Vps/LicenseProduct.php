<?php

namespace Transip\Api\Library\Entity\Vps;

class LicenseProduct extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $price = NULL;
    protected $recurringPrice = NULL;
    protected $type = NULL;
    protected $minQuantity = NULL;
    protected $maxQuantity = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getRecurringPrice()
    {
        return $this->recurringPrice;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getMinQuantity()
    {
        return $this->minQuantity;
    }

    public function getMaxQuantity()
    {
        return $this->maxQuantity;
    }
}
