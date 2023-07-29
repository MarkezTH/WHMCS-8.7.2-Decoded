<?php

namespace WHMCS\UsageBilling\Metrics;

class NoUsage implements \WHMCS\UsageBilling\Contracts\Metrics\UsageStubInterface
{
    private $now = NULL;

    public function __construct()
    {
        $this->now = \WHMCS\Carbon::now();
    }

    public function collectedAt()
    {
        return $this->now->copy();
    }

    public function startAt()
    {
        return $this->now->copy();
    }

    public function endAt()
    {
        return $this->now->copy();
    }

    public function value()
    {
        return 0;
    }
}
