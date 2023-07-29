<?php

namespace Transip\Api\Library\Entity;

class DomainCheckResult extends AbstractEntity
{
    protected $domainName = NULL;
    protected $status = NULL;
    protected $actions = NULL;
    const STATUS_INYOURACCOUNT = "inyouraccount";
    const STATUS_UNAVAILABLE = "unavailable";
    const STATUS_NOTFREE = "notfree";
    const STATUS_FREE = "free";
    const STATUS_INTERNALPULL = "internalpull";
    const STATUS_INTERNALPUSH = "internalpush";

    public function getDomainName()
    {
        return $this->domainName;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getActions()
    {
        return $this->actions;
    }
}
