<?php

namespace Transip\Api\Library\Entity\Domain;

class Action extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $message = NULL;
    protected $hasFailed = NULL;

    public function getName()
    {
        return $this->name;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getHasFailed()
    {
        return $this->hasFailed;
    }
}
