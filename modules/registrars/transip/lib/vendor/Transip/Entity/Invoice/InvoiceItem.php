<?php

namespace Transip\Api\Library\Entity\Invoice;

class InvoiceItem extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $product = NULL;
    protected $description = NULL;
    protected $isRecurring = NULL;
    protected $date = NULL;
    protected $quantity = NULL;
    protected $price = NULL;
    protected $priceInclVat = NULL;
    protected $vat = NULL;
    protected $vatPercentage = NULL;
    protected $discounts = NULL;

    public function __construct($valueArray = [])
    {
        parent::__construct($valueArray);
        $itemDiscounts = [];
        $itemDiscountsArray = $valueArray["discounts"] ?? [];
        foreach ($itemDiscountsArray as $itemDiscount) {
            $itemDiscounts[] = new InvoiceItemDiscount($itemDiscount);
        }
        $this->discounts = $itemDiscounts;
    }

    public function getProduct()
    {
        return $this->product;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function isRecurring()
    {
        return $this->isRecurring;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getPriceInclVat()
    {
        return $this->priceInclVat;
    }

    public function getVat()
    {
        return $this->vat;
    }

    public function getVatPercentage()
    {
        return $this->vatPercentage;
    }

    public function getDiscounts()
    {
        return $this->discounts;
    }
}
