<?php

namespace WHMCS\UsageBilling\Metrics;

class Usage implements \WHMCS\UsageBilling\Contracts\Metrics\UsageInterface
{
    private $startAt = NULL;
    private $endAt = NULL;
    private $collectedAt = NULL;
    private $value = 0;

    public function __construct($value, $collectedAt = NULL, $startAt = NULL, $endAt = NULL)
    {
        if (!is_numeric($collectedAt) && !$collectedAt instanceof \WHMCS\Carbon) {
            $collectedAt = \WHMCS\Carbon::now()->toMicroTime();
        }
        if (!is_numeric($collectedAt)) {
            if (!$collectedAt instanceof \WHMCS\Carbon) {
                $collectedAt = \WHMCS\Carbon::now()->toMicroTime();
            }
        } else {
            try {
                $collectedAt = \WHMCS\Carbon::createFromTimestamp($collectedAt);
            } catch (\Exception $e) {
                $collectedAt = NULL;
            }
        }
        $this->collectedAt = $collectedAt;
        if (!is_numeric($startAt)) {
            if (!$startAt instanceof \WHMCS\Carbon) {
                $startAt = $collectedAt;
            }
        } else {
            try {
                $startAt = \WHMCS\Carbon::createFromTimestamp($startAt);
            } catch (\Exception $e) {
                $startAt = NULL;
            }
        }
        $this->startAt = $startAt;
        if (!is_numeric($endAt)) {
            if (!$endAt instanceof \WHMCS\Carbon) {
                $endAt = $collectedAt;
            }
        } else {
            try {
                $endAt = \WHMCS\Carbon::createFromTimestamp($endAt);
            } catch (\Exception $e) {
                $endAt = NULL;
            }
        }
        $this->endAt = $endAt;
        $this->value = (double) $value;
    }

    public function collectedAt()
    {
        return $this->collectedAt;
    }

    public function startAt()
    {
        return $this->startAt;
    }

    public function endAt()
    {
        return $this->endAt;
    }

    public function value()
    {
        return $this->value;
    }
}
