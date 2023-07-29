<?php

namespace WHMCS\UsageBilling\Metrics\Providers;

class CallbackUsage extends AbstractProvider
{
    private $usageCallable = NULL;
    private $tenantUsageCallable = NULL;

    public function __construct($items = [], $usageCallable = NULL, $tenantUsageCallable = NULL)
    {
        parent::__construct($items);
        if (is_callable($usageCallable)) {
            $this->usageCallable = $usageCallable;
        }
        if (is_callable($tenantUsageCallable)) {
            $this->tenantUsageCallable = $tenantUsageCallable;
        }
    }

    public function tenantUsage($tenant)
    {
        $cache = $this->getStorage();
        if (isset($cache[$tenant])) {
            return $cache[$tenant];
        }
        if ($this->tenantUsageCallable) {
            $usage = call_user_func($this->tenantUsageCallable, $tenant, $this);
            if (is_array($usage)) {
                $cache[$tenant] = $usage;
                $this->setStorage($cache);
                return $usage;
            }
        } else {
            $cache = $this->usage(false);
            if (isset($cache[$tenant])) {
                return $cache[$tenant];
            }
        }
    }

    public function usage($useCache = true)
    {
        if ($useCache) {
            $cache = $this->getStorage();
            if (count($cache)) {
                return $cache;
            }
        }
        if ($this->usageCallable) {
            $usage = call_user_func($this->usageCallable, $this);
            if (is_array($usage)) {
                $this->setStorage($usage);
                return $usage;
            }
        }
        return [];
    }
}
