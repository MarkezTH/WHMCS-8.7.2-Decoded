<?php

namespace WHMCS\Model\Traits;

trait DateTimeTrait
{
    protected function serializeDate(\DateTimeInterface $date)
    {
        if ((int) (string) $date < 0) {
            return "0000-00-00 00:00:00";
        }
        return $date->format($this->getDateFormat());
    }

    protected function asDateTime($CarbonInterface, $value)
    {
        if ($value instanceof \WHMCS\Carbon) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return new \WHMCS\Carbon($value->format("Y-m-d H:i:s.u"), $value->getTimeZone());
        }
        if (is_numeric($value)) {
            return \WHMCS\Carbon::createFromTimestamp($value);
        }
        if (preg_match("/^(\\d{4})-(\\d{1,2})-(\\d{1,2})\$/", $value)) {
            return \WHMCS\Carbon::createFromFormat("Y-m-d", $value)->startOfDay();
        }
        return \WHMCS\Carbon::createFromFormat($this->getDateFormat(), $value);
    }

    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }
        $format = $this->getDateFormat();
        $value = parent::asDateTime($value);
        return $value->format($format);
    }
}
