<?php

namespace WHMCS\Scheduling\Contract;

interface JobInterface
{
    public function jobName($name = "");

    public function jobClassName($className = "");

    public function jobMethodName($methodName = "");

    public function jobMethodArguments($arguments = []);

    public function jobAvailableAt(\WHMCS\Carbon $date = NULL);

    public function jobDigestHash($hash = "");
}
