<?php

namespace WHMCS\UsageBilling\Contracts\Metrics;

interface MetricInterface
{
    const TYPE_SNAPSHOT = "snapshot";
    const TYPE_PERIOD_DAY = "day";
    const TYPE_PERIOD_MONTH = "month";

    public function usage();

    public function withUsage(UsageInterface $usage);

    public function units();

    public function systemName();

    public function displayName();

    public function type();
}
