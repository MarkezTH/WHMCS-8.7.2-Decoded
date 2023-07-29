<?php

namespace Transip\Api\Library\Entity\Invoice;

class InvoiceItemDiscount extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $description = NULL;
    protected $amount = NULL;

    public function getDescription()
    {
        return $this->description;
    }

    public function getAmount()
    {
        return $this->amount;
    }
}
