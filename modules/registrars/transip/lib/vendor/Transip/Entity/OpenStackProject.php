<?php

namespace Transip\Api\Library\Entity;

class OpenStackProject extends AbstractEntity
{
    protected $id = NULL;
    protected $name = NULL;
    protected $description = NULL;
    protected $isLocked = NULL;
    protected $isBlocked = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function isLocked()
    {
        return $this->isLocked;
    }

    public function isBlocked()
    {
        return $this->isBlocked;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }
}
