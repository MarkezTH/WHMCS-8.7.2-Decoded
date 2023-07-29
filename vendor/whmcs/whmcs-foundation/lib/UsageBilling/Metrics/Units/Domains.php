<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class Domains extends WholeNumber
{
    public function __construct($name = "Domains", $singlePerUnitName = "Domain", $pluralPerUnitName = "Domains", $prefix = NULL, $suffix = "")
    {
        parent::__construct($name, $singlePerUnitName, $pluralPerUnitName, $prefix, $suffix);
    }
}
