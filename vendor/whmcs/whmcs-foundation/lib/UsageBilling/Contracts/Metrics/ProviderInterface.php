<?php

namespace WHMCS\UsageBilling\Contracts\Metrics;

interface ProviderInterface
{
    public function metrics();

    public function tenantUsage($tenant);

    public function usage();
}
