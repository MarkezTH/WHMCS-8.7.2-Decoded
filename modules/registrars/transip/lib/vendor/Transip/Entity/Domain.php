<?php

namespace Transip\Api\Library\Entity;

class Domain extends AbstractEntity
{
    protected $name = NULL;
    protected $authCode = NULL;
    protected $isTransferLocked = NULL;
    protected $registrationDate = NULL;
    protected $renewalDate = NULL;
    protected $isWhitelabel = NULL;
    protected $cancellationDate = NULL;
    protected $cancellationStatus = NULL;
    protected $isDnsOnly = NULL;
    protected $hasAutoDns = NULL;
    protected $tags = [];

    public function getName()
    {
        return $this->name;
    }

    public function getAuthCode()
    {
        return $this->authCode;
    }

    public function isTransferLocked()
    {
        return $this->isTransferLocked;
    }

    public function getRegistrationDate()
    {
        return $this->registrationDate;
    }

    public function getRenewalDate()
    {
        return $this->renewalDate;
    }

    public function isWhitelabel()
    {
        return $this->isWhitelabel;
    }

    public function getCancellationDate()
    {
        return $this->cancellationDate;
    }

    public function getCancellationStatus()
    {
        return $this->cancellationStatus;
    }

    public function isDnsOnly()
    {
        return $this->isDnsOnly;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function setIsTransferLocked($Domain, $isTransferLocked)
    {
        $this->isTransferLocked = $isTransferLocked;
        return $this;
    }

    public function setIsWhitelabel($Domain, $isWhitelabel)
    {
        $this->isWhitelabel = $isWhitelabel;
        return $this;
    }

    public function setTags($Domain, $tags)
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag($Domain, $tag)
    {
        $this->tags[] = $tag;
        $this->tags = array_unique($this->tags);
        return $this;
    }

    public function removeTag($Domain, $tag)
    {
        $this->tags = array_diff($this->getTags(), [$tag]);
        return $this;
    }

    public function getHasAutoDns()
    {
        return $this->hasAutoDns;
    }

    public function setHasAutoDns($hasAutoDns)
    {
        $this->hasAutoDns = $hasAutoDns;
    }
}
