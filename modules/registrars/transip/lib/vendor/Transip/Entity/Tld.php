<?php

namespace Transip\Api\Library\Entity;

class Tld extends AbstractEntity
{
    protected $name = NULL;
    protected $price = NULL;
    protected $recurringPrice = NULL;
    protected $capabilities = NULL;
    protected $minLength = NULL;
    protected $maxLength = NULL;
    protected $registrationPeriodLength = NULL;
    protected $cancelTimeFrame = NULL;
    const CAPABILITY_REQUIRESAUTHCODE = "requiresAuthCode";
    const CAPABILITY_CANREGISTER = "canRegister";
    const CAPABILITY_CANTRANSFERWITHOWNERCHANGE = "canTransferWithOwnerChange";
    const CAPABILITY_CANTRANSFERWITHOUTOWNERCHANGE = "canTransferWithoutOwnerChange";
    const CAPABILITY_CANSETLOCK = "canSetLock";
    const CAPABILITY_CANSETOWNER = "canSetOwner";
    const CAPABILITY_CANSETCONTACTS = "canSetContacts";
    const CAPABILITY_CANSETNAMESERVERS = "canSetNameservers";
    const CAPABILITY_SUPPORTSDNSSEC = "supportsDnsSec";

    public function getName()
    {
        return $this->name;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getRecurringPrice()
    {
        return $this->recurringPrice;
    }

    public function getCapabilities()
    {
        return $this->capabilities;
    }

    public function getMinLength()
    {
        return $this->minLength;
    }

    public function getMaxLength()
    {
        return $this->maxLength;
    }

    public function getRegistrationPeriodLength()
    {
        return $this->registrationPeriodLength;
    }

    public function getCancelTimeFrame()
    {
        return $this->cancelTimeFrame;
    }
}
