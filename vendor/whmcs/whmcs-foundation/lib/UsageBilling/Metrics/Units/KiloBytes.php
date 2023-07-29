<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class KiloBytes extends Bytes
{
    public function __construct($name = "Kilobytes", $singlePerUnitName = NULL, $pluralPerUnitName = NULL, $prefix = NULL, $suffix = "KB")
    {
        parent::__construct($name, $singlePerUnitName, $pluralPerUnitName, $prefix, $suffix);
    }
}
