<?php

namespace Transip\Api\Library\Entity\Vps;

class IpAddress extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $address = NULL;
    protected $subnetMask = NULL;
    protected $gateway = NULL;
    protected $dnsResolvers = NULL;
    protected $reverseDns = NULL;

    public function getAddress()
    {
        return $this->address;
    }

    public function getSubnetMask()
    {
        return $this->subnetMask;
    }

    public function getGateway()
    {
        return $this->gateway;
    }

    public function getDnsResolvers()
    {
        return $this->dnsResolvers;
    }

    public function getReverseDns()
    {
        return $this->reverseDns;
    }

    public function setReverseDns($IpAddress, $reverseDns)
    {
        $this->reverseDns = $reverseDns;
        return $this;
    }
}
