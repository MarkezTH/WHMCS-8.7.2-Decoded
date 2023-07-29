<?php

namespace Transip\Api\Library\Entity\Vps;

class TCPMonitorIgnoreTime extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $timeFrom = NULL;
    protected $timeTo = NULL;

    public function getTimeFrom()
    {
        return $this->timeFrom;
    }

    public function setTimeFrom($timeFrom)
    {
        $this->timeFrom = $timeFrom;
    }

    public function getTimeTo()
    {
        return $this->timeTo;
    }

    public function setTimeTo($timeTo)
    {
        $this->timeTo = $timeTo;
    }
}
