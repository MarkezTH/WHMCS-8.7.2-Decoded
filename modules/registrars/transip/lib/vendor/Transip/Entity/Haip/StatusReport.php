<?php

namespace Transip\Api\Library\Entity\Haip;

class StatusReport extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $port = NULL;
    protected $ipVersion = NULL;
    protected $ipAddress = NULL;
    protected $loadBalancerName = NULL;
    protected $loadBalancerIp = NULL;
    protected $state = NULL;
    protected $lastChange = NULL;

    public function getPort()
    {
        return $this->port;
    }

    public function getIpVersion()
    {
        return $this->ipVersion;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    public function getLoadBalancerName()
    {
        return $this->loadBalancerName;
    }

    public function getLoadBalancerIp()
    {
        return $this->loadBalancerIp;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getLastChange()
    {
        return $this->lastChange;
    }
}
