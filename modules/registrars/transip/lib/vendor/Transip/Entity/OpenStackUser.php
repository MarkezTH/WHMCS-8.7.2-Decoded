<?php

namespace Transip\Api\Library\Entity;

class OpenStackUser extends AbstractEntity
{
    protected $id = NULL;
    protected $username = NULL;
    protected $description = NULL;
    protected $email = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }
}
