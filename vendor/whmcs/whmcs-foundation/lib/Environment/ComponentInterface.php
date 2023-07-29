<?php

namespace WHMCS\Environment;

interface ComponentInterface
{
    public function report(Report $report);

    public function addTopic($name, $closure);

    public function name();
}
