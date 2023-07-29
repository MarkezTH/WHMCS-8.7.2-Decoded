<?php

namespace Transip\Api\Library\Entity;

class Vps extends AbstractEntity
{
    protected $name = NULL;
    protected $uuid = NULL;
    protected $description = NULL;
    protected $productName = NULL;
    protected $operatingSystem = NULL;
    protected $diskSize = NULL;
    protected $memorySize = NULL;
    protected $cpus = NULL;
    protected $status = NULL;
    protected $ipAddress = NULL;
    protected $macAddress = NULL;
    protected $currentSnapshots = NULL;
    protected $maxSnapshots = NULL;
    protected $isLocked = NULL;
    protected $isBlocked = NULL;
    protected $isCustomerLocked = NULL;
    protected $availabilityZone = NULL;
    protected $tags = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getProductName()
    {
        return $this->productName;
    }

    public function getOperatingSystem()
    {
        return $this->operatingSystem;
    }

    public function getDiskSize()
    {
        return $this->diskSize;
    }

    public function getMemorySize()
    {
        return $this->memorySize;
    }

    public function getCpus()
    {
        return $this->cpus;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    public function getMacAddress()
    {
        return $this->macAddress;
    }

    public function getCurrentSnapshots()
    {
        return $this->currentSnapshots;
    }

    public function getMaxSnapshots()
    {
        return $this->maxSnapshots;
    }

    public function isLocked()
    {
        return $this->isLocked;
    }

    public function isBlocked()
    {
        return $this->isBlocked;
    }

    public function isCustomerLocked()
    {
        return $this->isCustomerLocked;
    }

    public function getAvailabilityZone()
    {
        return $this->availabilityZone;
    }

    public function setDescription($Vps, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function setIsCustomerLocked($isCustomerLocked)
    {
        $this->isCustomerLocked = $isCustomerLocked;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function addTag($Vps, $tag)
    {
        $this->tags[] = $tag;
        $this->tags = array_unique($this->tags);
        return $this;
    }

    public function removeTag($Vps, $tag)
    {
        $this->tags = array_diff($this->getTags(), [$tag]);
        return $this;
    }
}
