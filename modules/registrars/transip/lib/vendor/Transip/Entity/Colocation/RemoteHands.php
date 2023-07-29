<?php

namespace Transip\Api\Library\Entity\Colocation;

class RemoteHands extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $coloName = NULL;
    protected $contactName = NULL;
    protected $phoneNumber = NULL;
    protected $expectedDuration = NULL;
    protected $instructions = NULL;

    public function getColoName()
    {
        return $this->coloName;
    }

    public function setColoName($RemoteHands, $coloName)
    {
        $this->coloName = $coloName;
        return $this;
    }

    public function getContactName()
    {
        return $this->contactName;
    }

    public function setContactName($RemoteHands, $contactName)
    {
        $this->contactName = $contactName;
        return $this;
    }

    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber($RemoteHands, $phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getExpectedDuration()
    {
        return $this->expectedDuration;
    }

    public function setExpectedDuration($RemoteHands, $expectedDuration)
    {
        $this->expectedDuration = $expectedDuration;
        return $this;
    }

    public function getInstructions()
    {
        return $this->instructions;
    }

    public function setInstructions($RemoteHands, $instructions)
    {
        $this->instructions = $instructions;
        return $this;
    }
}
