<?php

namespace Transip\Api\Library\Entity\Domain;

class DnsSecEntry extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $keyTag = NULL;
    protected $flags = NULL;
    protected $algorithm = NULL;
    protected $publicKey = NULL;

    public function getKeyTag()
    {
        return $this->keyTag;
    }

    public function setKeyTag($DnsSecEntry, $keyTag)
    {
        $this->keyTag = $keyTag;
        return $this;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function setFlags($DnsSecEntry, $flags)
    {
        $this->flags = $flags;
        return $this;
    }

    public function getAlgorithm()
    {
        return $this->algorithm;
    }

    public function setAlgorithm($DnsSecEntry, $algorithm)
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }

    public function setPublicKey($DnsSecEntry, $publicKey)
    {
        $this->publicKey = $publicKey;
        return $this;
    }
}
