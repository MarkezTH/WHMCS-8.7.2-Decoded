<?php

namespace Transip\Api\Library\Entity\Vps;

class LicenseKey extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $key = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getKey()
    {
        return $this->key;
    }
}
