<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class Accounts extends WholeNumber
{
    public function __construct($name = "Accounts", $singlePerUnitName = "Account", $pluralPerUnitName = "Accounts", $prefix = NULL, $suffix = "")
    {
        parent::__construct($name, $singlePerUnitName, $pluralPerUnitName, $prefix, $suffix);
    }
}
