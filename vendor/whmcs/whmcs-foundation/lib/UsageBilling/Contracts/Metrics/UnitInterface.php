<?php

namespace WHMCS\UsageBilling\Contracts\Metrics;

interface UnitInterface
{
    const TYPE_CURRENCY = "currency";
    const TYPE_FLOAT_PRECISION_LOW = "low";
    const TYPE_FLOAT_PRECISION_HIGH = "high";
    const TYPE_INT = "int";
    const TYPE_MICROTIME = "microtime";

    public function name();

    public function perUnitName($value);

    public function prefix();

    public function suffix();

    public function type();

    public function decorate($value);

    public function formatForType($value);

    public function roundForType($value);
}
