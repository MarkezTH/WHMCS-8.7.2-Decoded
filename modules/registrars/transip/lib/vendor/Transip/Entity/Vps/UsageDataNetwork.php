<?php

namespace Transip\Api\Library\Entity\Vps;

class UsageDataNetwork extends UsageData
{
    protected $mbitIn = NULL;
    protected $mbitOut = NULL;

    public function getMbitIn()
    {
        return $this->mbitIn;
    }

    public function getMbitOut()
    {
        return $this->mbitOut;
    }
}
