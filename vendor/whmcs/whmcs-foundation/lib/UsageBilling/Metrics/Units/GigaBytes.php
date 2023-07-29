<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class GigaBytes extends Bytes
{
    public function __construct($name = "Gigabytes", $singlePerUnitName = NULL, $pluralPerUnitName = NULL, $prefix = NULL, $suffix = "GB")
    {
        parent::__construct($name, $singlePerUnitName, $pluralPerUnitName, $prefix, $suffix);
    }
}
