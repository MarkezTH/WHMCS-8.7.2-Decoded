<?php

namespace WHMCS\UsageBilling\Contracts\Invoice;

interface UsageCalculationInterface
{
    public function consumed();

    public function bracket();

    public function price();

    public function isIncluded();
}
