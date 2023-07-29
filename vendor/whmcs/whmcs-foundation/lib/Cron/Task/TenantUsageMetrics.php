<?php

namespace WHMCS\Cron\Task;

class TenantUsageMetrics extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1519;
    protected $defaultFrequency = 720;
    protected $defaultDescription = "Collect tenant usage metrics from servers";
    protected $defaultName = "Tenant Usage Metrics";
    protected $systemName = "TenantUsageMetrics";
    protected $outputs = ["tenants" => ["defaultValue" => 0, "identifier" => "tenants", "name" => "Tenants"], "servers" => ["defaultValue" => 0, "identifier" => "servers", "name" => "Servers"]];
    protected $icon = "fas fa-chart-bar";
    protected $successCountIdentifier = "servers";
    protected $successKeyword = "Updated";
    protected $skipDailyCron = true;

    public function __invoke()
    {
        if (!\WHMCS\UsageBilling\MetricUsageSettings::isCollectionEnable()) {
            return $this;
        }
        $serversSynced = 0;
        $tenantsSynced = 0;
        $servers = \WHMCS\Product\Server::enabled()->get();
        foreach ($servers as $server) {
            if ($server->getMetricProvider()) {
                $serversSynced++;
                $usage = $server->syncAllUsage();
                $tenantsSynced += count($usage);
            }
        }
        $this->output("tenants")->write($tenantsSynced);
        $this->output("servers")->write($serversSynced);
        return $this;
    }
}
