<?php

namespace Transip\Api\Library\Entity\Haip;

class Certificate extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $commonName = NULL;
    protected $expirationDate = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function getCommonName()
    {
        return $this->commonName;
    }

    public function getExpirationDate()
    {
        return $this->expirationDate;
    }
}
