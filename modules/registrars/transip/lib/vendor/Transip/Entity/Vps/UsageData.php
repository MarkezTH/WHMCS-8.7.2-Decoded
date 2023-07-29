<?php

namespace Transip\Api\Library\Entity\Vps;

abstract class UsageData extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $date = NULL;

    public function getDate()
    {
        return $this->date;
    }
}
