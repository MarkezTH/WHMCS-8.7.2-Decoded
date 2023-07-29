<?php

namespace Transip\Api\Library\Entity\BigStorage;

class Backup extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $status = NULL;
    protected $diskSize = NULL;
    protected $dateTimeCreate = NULL;
    protected $availabilityZone = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getDiskSize()
    {
        return $this->diskSize;
    }

    public function getDateTimeCreate()
    {
        return $this->dateTimeCreate;
    }

    public function getAvailabilityZone()
    {
        return $this->availabilityZone;
    }
}
