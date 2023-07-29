<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$healthChecks = new WHMCS\View\Admin\HealthCheck\HealthCheckRepository();
$keyChecks = $healthChecks->keyChecks();
$nonKeyChecks = $healthChecks->nonKeyChecks();
$allChecks = $keyChecks->merge($nonKeyChecks);
$healthChecks = $allChecks->reduce(function ($results, WHMCS\View\Admin\HealthCheck\HealthCheckResult $result) {
    $results = is_null($results) ? [] : $results;
    $result->getSeverityLevel();
    switch ($result->getSeverityLevel()) {
        case PSR\Log\LogLevel::INFO:
        case PSR\Log\LogLevel::NOTICE:
            $results["success"][] = $result->toArray();
            break;
        case PSR\Log\LogLevel::WARNING:
            $results["warning"][] = $result->toArray();
            break;
        case PSR\Log\LogLevel::ERROR:
        case PSR\Log\LogLevel::CRITICAL:
        case PSR\Log\LogLevel::ALERT:
        case PSR\Log\LogLevel::EMERGENCY:
            $results["danger"][] = $result->toArray();
            break;
        default:
            return $results;
    }
});
$apiresults = ["result" => "success", "checks" => ["success" => $healthChecks["success"], "warning" => $healthChecks["warning"], "danger" => $healthChecks["danger"]]];
