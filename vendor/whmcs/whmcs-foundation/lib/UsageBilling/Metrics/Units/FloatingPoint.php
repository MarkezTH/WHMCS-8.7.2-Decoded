<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class FloatingPoint extends AbstractUnit
{
    public function type()
    {
        return \WHMCS\UsageBilling\Contracts\Metrics\UnitInterface::TYPE_FLOAT_PRECISION_LOW;
    }
}
