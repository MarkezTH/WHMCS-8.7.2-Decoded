<?php

namespace WHMCS\Module\Registrar\CentralNic;

class TldPricing
{
    protected $tld = "";
    protected $zone = "";
    protected $setup = 0;
    protected $annual = 0;
    protected $transfer = 0;
    protected $trade = 0;
    protected $restore = 0;
    protected $application = 0;
    protected $currency = "";
    protected $domainCount = 0;

    public function __construct($tld, $zone, $setup, $annual, $transfer, $trade, $restore, $application, $currency, int $domainCount)
    {
        $this->tld = $tld;
        $this->zone = $zone;
        $this->setup = $setup;
        $this->annual = $annual;
        $this->transfer = $transfer;
        $this->trade = $trade;
        $this->restore = $restore;
        $this->application = $application;
        $this->currency = $currency;
        $this->domainCount = $domainCount;
    }

    public function tld()
    {
        return $this->tld;
    }

    public function zone()
    {
        return $this->zone;
    }

    public function setup()
    {
        return $this->setup;
    }

    public function annual()
    {
        return $this->annual;
    }

    public function transfer()
    {
        return $this->transfer;
    }

    public function trade()
    {
        return $this->trade;
    }

    public function restore()
    {
        return $this->restore;
    }

    public function application()
    {
        return $this->application;
    }

    public function currency()
    {
        return $this->currency;
    }

    public function domainCount()
    {
        return $this->domainCount;
    }
}
