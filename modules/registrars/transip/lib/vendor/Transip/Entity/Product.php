<?php

namespace Transip\Api\Library\Entity;

class Product extends AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $price = NULL;
    protected $recurringPrice = NULL;
    protected $category = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getRecurringPrice()
    {
        return $this->recurringPrice;
    }

    public function getCategory()
    {
        return $this->category;
    }
}
