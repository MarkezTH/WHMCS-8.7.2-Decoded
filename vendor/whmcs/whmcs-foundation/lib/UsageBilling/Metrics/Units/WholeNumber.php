<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class WholeNumber extends AbstractUnit
{
    public function type()
    {
        return \WHMCS\UsageBilling\Contracts\Metrics\UnitInterface::TYPE_INT;
    }
}
