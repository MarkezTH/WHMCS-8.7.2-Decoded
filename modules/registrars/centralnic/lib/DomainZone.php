<?php

namespace WHMCS\Module\Registrar\CentralNic;

class DomainZone extends Domain
{
    protected $api = NULL;
    protected $zone = "";

    public function __construct(Api\ApiInterface $api, $name)
    {
        $this->api = $api;
        parent::__construct($name);
    }

    public function getZone()
    {
        if (empty($this->zone)) {
            $this->zone = (new Commands\GetZone($this->api, $this->getName()))->execute()->getDataValue("zone");
        }
        return $this->zone;
    }
}
