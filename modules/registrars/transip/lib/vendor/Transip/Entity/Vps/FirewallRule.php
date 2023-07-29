<?php

namespace Transip\Api\Library\Entity\Vps;

class FirewallRule extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $description = NULL;
    protected $startPort = NULL;
    protected $endPort = NULL;
    protected $protocol = NULL;
    protected $whitelist = NULL;
    const PROTOCOL_TCP = "tcp";
    const PROTOCOL_UDP = "udp";
    const PROTOCOL_TCP_UDP = "tcp_udp";

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($FirewallRule, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function getStartPort()
    {
        return $this->startPort;
    }

    public function setStartPort($FirewallRule, $startPort)
    {
        $this->startPort = $startPort;
        return $this;
    }

    public function getEndPort()
    {
        return $this->endPort;
    }

    public function setEndPort($FirewallRule, $endPort)
    {
        $this->endPort = $endPort;
        return $this;
    }

    public function getProtocol()
    {
        return $this->protocol;
    }

    public function setProtocol($FirewallRule, $protocol)
    {
        $this->protocol = $protocol;
        return $this;
    }

    public function getWhitelist()
    {
        return $this->whitelist;
    }

    public function setWhitelist($FirewallRule, $whitelist)
    {
        $this->whitelist = $whitelist;
        return $this;
    }

    public function addRangeToWhitelist($FirewallRule, $ipRange)
    {
        $this->whitelist[] = $ipRange;
        return $this;
    }

    public function removeRangeToWhitelist($FirewallRule, $ipRange)
    {
        $whitelist = [];
        foreach ($whitelist as $whitelistEntry) {
            if ($whitelistEntry !== $ipRange) {
                $whitelist[] = $whitelistEntry;
            }
        }
        $this->setWhitelist($whitelist);
        return $this;
    }

    public function equalsRule($rule)
    {
        return $rule->protocol == $this->protocol && $rule->startPort == $this->startPort && $rule->endPort == $this->endPort;
    }
}
