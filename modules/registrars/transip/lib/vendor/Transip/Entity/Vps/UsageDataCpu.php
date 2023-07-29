<?php

namespace Transip\Api\Library\Entity\Vps;

class UsageDataCpu extends UsageData
{
    protected $percentage = NULL;

    public function getPercentage()
    {
        return $this->percentage;
    }
}
