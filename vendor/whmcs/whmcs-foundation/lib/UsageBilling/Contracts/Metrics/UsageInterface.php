<?php

namespace WHMCS\UsageBilling\Contracts\Metrics;

interface UsageInterface
{
    public function collectedAt();

    public function startAt();

    public function endAt();

    public function value();
}
