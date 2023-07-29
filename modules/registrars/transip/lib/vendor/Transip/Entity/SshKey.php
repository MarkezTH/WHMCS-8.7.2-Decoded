<?php

namespace Transip\Api\Library\Entity;

class SshKey extends AbstractEntity
{
    protected $id = NULL;
    protected $key = NULL;
    protected $description = NULL;
    protected $creationDate = NULL;
    protected $fingerprint = NULL;
    protected $isDefault = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getCreationDate()
    {
        return $this->creationDate;
    }

    public function getFingerprint()
    {
        return $this->fingerprint;
    }

    public function getIsDefault()
    {
        return $this->isDefault;
    }
}
