<?php

namespace Transip\Api\Library\Entity;

class TrafficPoolInformation extends AbstractEntity
{
    protected $startDate = NULL;
    protected $endDate = NULL;
    protected $usedInBytes = NULL;
    protected $usedTotalBytes = NULL;
    protected $maxInBytes = NULL;
    protected $expectedBytes = NULL;

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function getUsedInBytes()
    {
        return $this->usedInBytes;
    }

    public function getUsedOutBytes()
    {
        return $this->getUsedTotalBytes() - $this->getUsedInBytes();
    }

    public function getUsedTotalBytes()
    {
        return $this->usedTotalBytes;
    }

    public function getMaxInBytes()
    {
        return $this->maxInBytes;
    }

    public function getexpectedBytes()
    {
        return $this->expectedBytes;
    }

    public function getUsedInMegabytes()
    {
        return round($this->getUsedInBytes() / 1024, 2);
    }

    public function getUsedOutMegabytes()
    {
        $usedInMegabytes = $this->getUsedInBytes() / 1024;
        $usedTotalMegabytes = $this->getUsedTotalBytes() / 1024;
        return round($usedTotalMegabytes - $usedInMegabytes, 2);
    }

    public function getUsedTotalMegabytes()
    {
        return round($this->getUsedTotalBytes() / 1024, 2);
    }

    public function getMaxInMegabytes()
    {
        return round($this->getMaxInBytes() / 1024, 2);
    }

    public function getUsedInGigabytes()
    {
        return round($this->getUsedInBytes() / 1024 / 1024, 2);
    }

    public function getUsedOutGigabytes()
    {
        $usedInGigabytes = $this->getUsedInBytes() / 1024 / 1024;
        $usedTotalGigabytes = $this->getUsedTotalBytes() / 1024 / 1024;
        return round($usedTotalGigabytes - $usedInGigabytes, 2);
    }

    public function getUsedTotalGigabytes()
    {
        return round($this->getUsedTotalBytes() / 1024 / 1024, 2);
    }

    public function getMaxInGigabytes()
    {
        return round($this->getMaxInBytes() / 1024 / 1024, 2);
    }
}
