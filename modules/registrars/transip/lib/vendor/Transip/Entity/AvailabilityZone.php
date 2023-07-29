<?php

namespace Transip\Api\Library\Entity;

class AvailabilityZone extends AbstractEntity
{
    protected $name = NULL;
    protected $country = NULL;
    protected $isDefault = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function isDefault()
    {
        return $this->isDefault;
    }
}
