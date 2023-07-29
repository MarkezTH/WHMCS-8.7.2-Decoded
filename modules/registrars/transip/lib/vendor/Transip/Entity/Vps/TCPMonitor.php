<?php

namespace Transip\Api\Library\Entity\Vps;

class TCPMonitor extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $ipAddress = NULL;
    protected $label = NULL;
    protected $ports = NULL;
    protected $interval = NULL;
    protected $allowedTimeouts = NULL;
    protected $contacts = NULL;
    protected $ignoreTimes = NULL;

    public function __construct($valueArray = [])
    {
        parent::__construct($valueArray);
        $contactsArray = $valueArray["contacts"] ?? [];
        $ignoreTimesArray = $valueArray["ignoreTimes"] ?? [];
        $contacts = [];
        foreach ($contactsArray as $contact) {
            $contacts[] = new TCPMonitorContact($contact);
        }
        $this->contacts = $contacts;
        $ignoreTimes = [];
        foreach ($ignoreTimesArray as $ignoreTime) {
            $ignoreTimes[] = new TCPMonitorIgnoreTime($ignoreTime);
        }
        $this->ignoreTimes = $ignoreTimes;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function getPorts()
    {
        return $this->ports;
    }

    public function setPorts($ports)
    {
        $this->ports = $ports;
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function setInterval($interval)
    {
        $this->interval = $interval;
    }

    public function getAllowedTimeouts()
    {
        return $this->allowedTimeouts;
    }

    public function setAllowedTimeouts($allowedTimeouts)
    {
        $this->allowedTimeouts = $allowedTimeouts;
    }

    public function getContacts()
    {
        return $this->contacts;
    }

    public function setContacts($contacts)
    {
        $this->contacts = $contacts;
    }

    public function getIgnoreTimes()
    {
        return $this->ignoreTimes;
    }

    public function setIgnoreTimes($ignoreTimes)
    {
        $this->ignoreTimes = $ignoreTimes;
    }
}
