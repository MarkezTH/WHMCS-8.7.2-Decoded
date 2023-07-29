<?php

namespace Transip\Api\Library\Entity;

class MailServiceInformation extends AbstractEntity
{
    protected $username = NULL;
    protected $password = NULL;
    protected $usage = NULL;
    protected $quota = NULL;
    protected $dnsTxt = NULL;

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getUsage()
    {
        return $this->usage;
    }

    public function getQuota()
    {
        return $this->quota;
    }

    public function getDnsTxt()
    {
        return $this->dnsTxt;
    }
}
