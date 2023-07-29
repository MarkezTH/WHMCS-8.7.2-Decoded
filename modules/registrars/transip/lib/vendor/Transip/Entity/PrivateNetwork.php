<?php

namespace Transip\Api\Library\Entity;

class PrivateNetwork extends AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $isBlocked = NULL;
    protected $isLocked = NULL;
    protected $vpsNames = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function isBlocked()
    {
        return $this->isBlocked;
    }

    public function isLocked()
    {
        return $this->isLocked;
    }

    public function getVpsNames()
    {
        return $this->vpsNames;
    }

    public function setDescription($PrivateNetwork, $description)
    {
        $this->description = $description;
        return $this;
    }
}
