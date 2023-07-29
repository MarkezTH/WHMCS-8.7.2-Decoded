<?php

namespace WHMCS\User\Traits;

trait User
{
    public function getFullNameAttribute()
    {
        return $this->firstName . " " . $this->lastName;
    }

    public function currentIp()
    {
        return \WHMCS\Utility\Environment\CurrentRequest::getIP();
    }

    public function currentHostname()
    {
        return gethostbyaddr($this->currentIp());
    }
}
