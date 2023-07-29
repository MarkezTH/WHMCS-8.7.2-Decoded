<?php

namespace WHMCS\Scheduling\Jobs;

class AsyncJobRouteProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function getRoutes()
    {
        return ["/job" => [["method" => ["POST"], "name" => "run-async-job", "path" => "/run/async/{jobId:\\d+}", "handle" => ["WHMCS\\Scheduling\\Jobs\\AsyncJobController", "runJob"]]]];
    }
}
