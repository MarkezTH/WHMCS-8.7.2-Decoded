<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class MegaBytes extends Bytes
{
    public function __construct($name = "Megabytes", $singlePerUnitName = NULL, $pluralPerUnitName = NULL, $prefix = NULL, $suffix = "MB")
    {
        parent::__construct($name, $singlePerUnitName, $pluralPerUnitName, $prefix, $suffix);
    }
}
