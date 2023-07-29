<?php

namespace Transip\Api\Library\Entity\Vps;

class UsageDataDisk extends UsageData
{
    protected $iopsRead = NULL;
    protected $iopsWrite = NULL;

    public function getIopsRead()
    {
        return $this->iopsRead;
    }

    public function getIopsWrite()
    {
        return $this->iopsWrite;
    }
}
