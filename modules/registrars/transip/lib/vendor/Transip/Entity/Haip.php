<?php

namespace Transip\Api\Library\Entity;

class Haip extends AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $status = NULL;
    protected $isLoadBalancingEnabled = NULL;
    protected $loadBalancingMode = NULL;
    protected $stickyCookieName = NULL;
    protected $healthCheckInterval = NULL;
    protected $httpHealthCheckPath = NULL;
    protected $httpHealthCheckPort = NULL;
    protected $httpHealthCheckSsl = NULL;
    protected $ipv4Address = NULL;
    protected $ipv6Address = NULL;
    protected $ipSetup = NULL;
    protected $ptrRecord = NULL;
    protected $ipAddresses = NULL;
    protected $tlsMode = NULL;
    protected $isLocked = NULL;
    const IPSETUP_BOTH = "both";
    const IPSETUP_NOIPV6 = "noipv6";
    const IPSETUP_IPV6TO4 = "ipv6to4";
    const IPSETUP_IPV4TO6 = "ipv4to6";
    const BALANCINGMODE_ROUNDROBIN = "roundrobin";
    const BALANCINGMODE_COOKIE = "cookie";
    const BALANCINGMODE_SOURCE = "source";
    const TLSMODE_TLS12 = "tls12";
    const TLSMODE_TLS11_12 = "tls11_12";
    const TLSMODE_TLS10_11_12 = "tls10_11_12";

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function isLoadBalancingEnabled()
    {
        return $this->isLoadBalancingEnabled;
    }

    public function getLoadBalancingMode()
    {
        return $this->loadBalancingMode;
    }

    public function getStickyCookieName()
    {
        return $this->stickyCookieName;
    }

    public function getHealthCheckInterval()
    {
        return $this->healthCheckInterval;
    }

    public function getHttpHealthCheckPath()
    {
        return $this->httpHealthCheckPath;
    }

    public function getHttpHealthCheckPort()
    {
        return $this->httpHealthCheckPort;
    }

    public function getHttpHealthCheckSsl()
    {
        return $this->httpHealthCheckSsl;
    }

    public function getIpv4Address()
    {
        return $this->ipv4Address;
    }

    public function getIpv6Address()
    {
        return $this->ipv6Address;
    }

    public function getIpSetup()
    {
        return $this->ipSetup;
    }

    public function getPtrRecord()
    {
        return $this->ptrRecord;
    }

    public function getIpAddresses()
    {
        return $this->ipAddresses;
    }

    public function setDescription($Haip, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function setLoadBalancingMode($Haip, $loadBalancingMode)
    {
        $this->loadBalancingMode = $loadBalancingMode;
        return $this;
    }

    public function setStickyCookieName($Haip, $stickyCookieName)
    {
        $this->stickyCookieName = $stickyCookieName;
        return $this;
    }

    public function setHealthCheckInterval($Haip, $healthCheckInterval)
    {
        $this->healthCheckInterval = $healthCheckInterval;
        return $this;
    }

    public function setHttpHealthCheckPath($Haip, $httpHealthCheckPath)
    {
        $this->httpHealthCheckPath = $httpHealthCheckPath;
        return $this;
    }

    public function setHttpHealthCheckPort($Haip, $httpHealthCheckPort)
    {
        $this->httpHealthCheckPort = $httpHealthCheckPort;
        return $this;
    }

    public function setHttpHealthCheckSsl($Haip, $httpHealthCheckSsl)
    {
        $this->httpHealthCheckSsl = $httpHealthCheckSsl;
        return $this;
    }

    public function setIpSetup($Haip, $ipSetup)
    {
        $this->ipSetup = $ipSetup;
        return $this;
    }

    public function setPtrRecord($Haip, $ptrRecord)
    {
        $this->ptrRecord = $ptrRecord;
        return $this;
    }

    public function getTlsMode()
    {
        return $this->tlsMode;
    }

    public function setTlsMode($tlsMode)
    {
        $this->tlsMode = $tlsMode;
    }

    public function isLocked()
    {
        return $this->isLocked;
    }
}
