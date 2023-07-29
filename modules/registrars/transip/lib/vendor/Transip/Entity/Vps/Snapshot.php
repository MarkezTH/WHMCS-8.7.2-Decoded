<?php

namespace Transip\Api\Library\Entity\Vps;

class Snapshot extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $dateTimeCreate = NULL;
    protected $diskSize = NULL;
    protected $status = NULL;
    protected $operatingSystem = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getDateTimeCreate()
    {
        return $this->dateTimeCreate;
    }

    public function getDiskSize()
    {
        return $this->diskSize;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getOperatingSystem()
    {
        return $this->operatingSystem;
    }
}
