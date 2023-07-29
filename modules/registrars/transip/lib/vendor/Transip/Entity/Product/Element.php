<?php

namespace Transip\Api\Library\Entity\Product;

class Element extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $amount = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getAmount()
    {
        return $this->amount;
    }
}
