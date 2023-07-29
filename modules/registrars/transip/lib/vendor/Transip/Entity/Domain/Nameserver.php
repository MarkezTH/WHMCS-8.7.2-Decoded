<?php

namespace Transip\Api\Library\Entity\Domain;

class Nameserver extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $hostname = NULL;
    protected $ipv4 = NULL;
    protected $ipv6 = NULL;

    public function getHostname()
    {
        return $this->hostname;
    }

    public function setHostname($Nameserver, $hostname)
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function getIpv4()
    {
        return $this->ipv4;
    }

    public function setIpv4($Nameserver, $ipv4)
    {
        $this->ipv4 = $ipv4;
        return $this;
    }

    public function getIpv6()
    {
        return $this->ipv6;
    }

    public function setIpv6($Nameserver, $ipv6)
    {
        $this->ipv6 = $ipv6;
        return $this;
    }
}
