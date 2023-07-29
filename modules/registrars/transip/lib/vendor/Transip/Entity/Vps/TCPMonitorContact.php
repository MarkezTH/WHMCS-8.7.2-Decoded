<?php

namespace Transip\Api\Library\Entity\Vps;

class TCPMonitorContact extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $enableEmail = NULL;
    protected $enableSMS = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function isEnableEmail()
    {
        return $this->enableEmail;
    }

    public function setEnableEmail($enableEmail)
    {
        $this->enableEmail = $enableEmail;
    }

    public function isEnableSMS()
    {
        return $this->enableSMS;
    }

    public function setEnableSMS($enableSMS)
    {
        $this->enableSMS = $enableSMS;
    }
}
