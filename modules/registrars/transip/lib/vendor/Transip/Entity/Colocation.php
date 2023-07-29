<?php

namespace Transip\Api\Library\Entity;

class Colocation extends AbstractEntity
{
    protected $name = NULL;
    protected $ipRanges = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getIpRanges()
    {
        return $this->ipRanges;
    }
}
