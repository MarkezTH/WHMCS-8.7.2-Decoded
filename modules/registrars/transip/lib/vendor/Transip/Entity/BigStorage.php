<?php

namespace Transip\Api\Library\Entity;

class BigStorage extends AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $diskSize = NULL;
    protected $vpsName = NULL;
    protected $status = NULL;
    protected $isLocked = NULL;
    protected $availabilityZone = NULL;
    protected $serial = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getDiskSize()
    {
        return $this->diskSize;
    }

    public function getVpsName()
    {
        return $this->vpsName;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function isLocked()
    {
        return $this->isLocked;
    }

    public function getAvailabilityZone()
    {
        return $this->availabilityZone;
    }

    public function setDescription($BigStorage, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function setVpsName($BigStorage, $vpsName)
    {
        $this->vpsName = $vpsName;
        return $this;
    }

    public function getSerial()
    {
        return $this->serial;
    }
}
