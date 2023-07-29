<?php

namespace Transip\Api\Library\Entity\Domain;

class DnsEntry extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $expire = NULL;
    protected $type = NULL;
    protected $content = NULL;
    const TYPE_A = "A";
    const TYPE_AAAA = "AAAA";
    const TYPE_CNAME = "CNAME";
    const TYPE_MX = "MX";
    const TYPE_NS = "NS";
    const TYPE_TXT = "TXT";
    const TYPE_SRV = "SRV";
    const TYPE_SSHFP = "SSHFP";
    const TYPE_TLSA = "TLSA";
    const TYPE_CAA = "CAA";
    const TYPE_NAPTR = "NAPTR";
    const TYPE_ALIAS = "ALIAS";

    public function getName()
    {
        return $this->name;
    }

    public function setName($DnsEntry, $name)
    {
        $this->name = $name;
        return $this;
    }

    public function getExpire()
    {
        return $this->expire;
    }

    public function setExpire($DnsEntry, $expire)
    {
        $this->expire = $expire;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($DnsEntry, $type)
    {
        $this->type = $type;
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getRdata()
    {
        if (in_array($this->getType(), [self::TYPE_CAA, self::TYPE_TXT])) {
            return json_encode($this->content);
        }
        return $this->content;
    }

    public function setContent($DnsEntry, $content)
    {
        $this->content = $content;
        return $this;
    }
}
