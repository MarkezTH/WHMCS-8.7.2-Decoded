<?php

namespace Transip\Api\Library\Entity\Vps;

class Contact extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $name = NULL;
    protected $telephone = NULL;
    protected $email = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getTelephone()
    {
        return $this->telephone;
    }

    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }
}
