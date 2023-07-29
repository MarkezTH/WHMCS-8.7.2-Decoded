<?php

namespace Transip\Api\Library\Entity\Vps;

class Backup extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $status = NULL;
    protected $dateTimeCreate = NULL;
    protected $diskSize = NULL;
    protected $operatingSystem = NULL;
    protected $availabilityZone = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function getDateTimeCreate()
    {
        return $this->dateTimeCreate;
    }

    public function getDiskSize()
    {
        return $this->diskSize;
    }

    public function getOperatingSystem()
    {
        return $this->operatingSystem;
    }

    public function getAvailabilityZone()
    {
        return $this->availabilityZone;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
