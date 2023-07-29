<?php

namespace WHMCS\Module\Registrar\CentralNic;

class Domain
{
    protected $name = "";
    protected $sld = "";
    protected $tld = "";
    protected $lastSegment = "";
    protected $nameservers = NULL;

    public function __construct($name)
    {
        $this->name = $name;
        $parts = explode(".", $name, 2);
        $this->sld = $parts[0] ?? "";
        $this->tld = $parts[1] ?? "";
        $this->lastSegment = self::parseLastSegment($this->tld);
        $this->nameservers = [];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSld()
    {
        return $this->sld;
    }

    public function getTld()
    {
        return $this->tld;
    }

    public function getLastSegment()
    {
        return $this->lastSegment;
    }

    public function isAfnic()
    {
        return in_array($this->getTld(), ["fr", "pm", "re", "tf", "wf", " yt"]);
    }

    public function isTld($tld)
    {
        return $this->getTld() == $tld;
    }

    public function supportContactOnTransfer($zone)
    {
        if ($this->isAfnic() || $this->isTld("au") || !($this->isTld("us") || $this->isTld("ca")) && !$zone->needsTrade()) {
            return true;
        }
        return false;
    }

    public function setNameServers($self, ...$nameservers)
    {
        foreach ($nameservers as $ns) {
            $this->nameservers[] = $ns;
        }
        return $this;
    }

    public function getNameServers()
    {
        return $this->nameservers;
    }

    public static function parseLastSegment($tld)
    {
        $tldParts = explode(".", $tld);
        return $tldParts[count($tldParts) - 1];
    }
}
