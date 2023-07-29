<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class RenewDomain extends AbstractCommand
{
    protected $command = "RenewDomain";
    protected $domain = "";
    protected $period = 0;
    protected $expireYear = 0;

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain, int $period, int $expireYear)
    {
        $this->domain = $domain;
        $this->period = $period;
        $this->expireYear = $expireYear;
        parent::__construct($api);
    }

    public function setPremiumAmount($amount)
    {
        $this->setParam("X-FEE-AMOUNT", $amount);
        return $this;
    }

    public function execute()
    {
        $this->setParam("domain", $this->domain);
        $this->setParam("period", $this->period);
        $this->setParam("expiration", $this->expireYear);
        return parent::execute();
    }
}
