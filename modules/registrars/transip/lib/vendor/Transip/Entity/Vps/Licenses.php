<?php

namespace Transip\Api\Library\Entity\Vps;

class Licenses extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $active = [];
    protected $cancellable = [];
    protected $available = [];

    public function getActive()
    {
        return $this->active;
    }

    public function getCancellable()
    {
        return $this->cancellable;
    }

    public function getAvailable()
    {
        return $this->available;
    }
}
