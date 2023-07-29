<?php

namespace Transip\Api\Library\Entity\Vps;

class License extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $name = NULL;
    protected $price = NULL;
    protected $recurringPrice = NULL;
    protected $type = NULL;
    protected $quantity = NULL;
    protected $maxQuantity = NULL;
    protected $keys = NULL;
    const TYPE_ADDON = "addon";
    const TYPE_OPERATING_SYSTEM = "operating-system";

    public function __construct($valueArray = [])
    {
        parent::__construct($valueArray);
        $licenseKeysArray = $valueArray["keys"] ?? [];
        $licenseKeys = [];
        foreach ($licenseKeysArray as $licenseKey) {
            $licenseKeys[] = new LicenseKey($licenseKey);
        }
        $this->keys = $licenseKeys;
    }

    public function getId()
    {
        return $this->id;
    }

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

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function getMaxQuantity()
    {
        return $this->maxQuantity;
    }

    public function getKeys()
    {
        return $this->keys;
    }
}
