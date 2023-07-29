<?php

namespace WHMCS\Cron;

class Status
{
    const LAST_MEMORY_LIMIT_TRANSIENT_KEY = "lastCronMemoryLimit";

    public function setCronTimeZone($tz)
    {
        if (!$tz instanceof \DateTimeZone) {
            $tz = \WHMCS\Carbon::now()->timezone;
        }
        \WHMCS\Config\Setting::setValue("cronTimeZone", $tz->getName());
    }

    public function getCronTimeZone($CarbonTimeZone)
    {
        $appConf = \DI::make("config");
        if ($appConf->automationStatus && isset($appConf->automationStatus["cronTimeZone"])) {
            $tz = $appConf->automationStatus["cronTimeZone"];
        } else {
            $tz = \WHMCS\Config\Setting::getValue("cronTimeZone");
        }
        if ($tz) {
            return \Carbon\CarbonTimeZone::create($tz) ?: NULL;
        }
        return NULL;
    }

    public function setLastDailyCronInvocationTime(\WHMCS\Carbon $datetime = NULL)
    {
        if (!$datetime instanceof \WHMCS\Carbon) {
            $datetime = \WHMCS\Carbon::now();
        }
        \WHMCS\Config\Setting::setValue("lastDailyCronInvocationTime", $datetime->toDateTimeString());
    }

    public function getLastDailyCronInvocationTime()
    {
        $datetime = NULL;
        $appConf = \DI::make("config");
        if ($appConf->automationStatus && isset($appConf->automationStatus["lastDailyCronInvocationTime"])) {
            $lastDailyTime = $appConf->automationStatus["lastDailyCronInvocationTime"];
        } else {
            $lastDailyTime = \WHMCS\Config\Setting::getValue("lastDailyCronInvocationTime");
        }
        if (!empty($lastDailyTime)) {
            try {
                $datetime = new \WHMCS\Carbon($lastDailyTime, $this->getCronTimeZone());
            } catch (\Exception $e) {
            }
        }
        return $datetime;
    }

    public function setLastDailyCronEndTime(\WHMCS\Carbon $datetime = NULL)
    {
        if (!$datetime instanceof \WHMCS\Carbon) {
            $datetime = \WHMCS\Carbon::now();
        }
        \WHMCS\Config\Setting::setValue("lastDailyCronEndTime", $datetime->toDateTimeString());
    }

    public function getLastDailyCronEndTime()
    {
        $datetime = NULL;
        $appConf = \DI::make("config");
        if ($appConf->automationStatus && isset($appConf->automationStatus["lastDailyCronEndTime"])) {
            $lastDailyTime = $appConf->automationStatus["lastDailyCronEndTime"];
        } else {
            $lastDailyTime = \WHMCS\Config\Setting::getValue("lastDailyCronEndTime");
        }
        if (!empty($lastDailyTime)) {
            try {
                $datetime = new \WHMCS\Carbon($lastDailyTime, $this->getCronTimeZone());
            } catch (\Exception $e) {
            }
        }
        return $datetime;
    }

    public function hasDailyCronCompletedSuccessfullyRecently()
    {
        $lastDailyCronStartTime = $this->getLastDailyCronInvocationTime();
        $lastDailyCronEndTime = $this->getLastDailyCronEndTime();
        if ($lastDailyCronStartTime && $lastDailyCronEndTime) {
            return $lastDailyCronEndTime->gt($lastDailyCronStartTime) && $lastDailyCronEndTime->gt(\WHMCS\Carbon::now()->subHours(24));
        }
        return false;
    }

    public function hasDailyCronRunInLast24Hours()
    {
        return $this->hasDailyCronRunSince(24);
    }

    public function hasDailyCronRunSince($hours)
    {
        $lastCronInvocationTime = $this->getLastDailyCronInvocationTime();
        if (!empty($lastCronInvocationTime)) {
            $lastCronInvocationTime = new \WHMCS\Carbon($lastCronInvocationTime);
            $minTime = \WHMCS\Carbon::now()->subHours((int) $hours);
            if ($lastCronInvocationTime->gt($minTime)) {
                return true;
            }
        }
        return false;
    }

    public function hasDailyCronEverRun()
    {
        $lastCronInvocationTime = $this->getLastDailyCronInvocationTime();
        return !empty($lastCronInvocationTime);
    }

    public function hasCronEverBeenInvoked()
    {
        return $this->getLastCronInvocationTime();
    }

    public static function getDailyCronExecutionHour()
    {
        $hour = \WHMCS\Config\Setting::getValue("DailyCronExecutionHour");
        $datetime = new \WHMCS\Carbon("January 2, 1970 00:00:00");
        if (!$hour) {
            $datetime->hour("09");
        } else {
            $datetime->hour($hour);
        }
        return $datetime;
    }

    public static function setDailyCronExecutionHour($time = "09")
    {
        try {
            if (is_numeric($time)) {
                $time = (string) $time;
                if (strlen($time) != 2) {
                    $time = "0" . $time;
                }
                $time .= ":00:00";
            }
            $datetime = new \WHMCS\Carbon("January 2, 1970 " . $time);
        } catch (\Exception $e) {
            $datetime = new \WHMCS\Carbon("January 2, 1970 09:00:00");
        }
        \WHMCS\Config\Setting::setValue("DailyCronExecutionHour", $datetime->format("H"));
    }

    public function isOkayToRunDailyCronNow()
    {
        $lastDailyRunTime = $this->getLastDailyCronInvocationTime();
        $now = \WHMCS\Carbon::now();
        $dailyCronHourWindowStart = self::getDailyCronExecutionHour();
        if ($now->format("H") == $dailyCronHourWindowStart->format("H")) {
            if (!$lastDailyRunTime) {
                return true;
            }
            if (!$now->isSameDay($lastDailyRunTime)) {
                return true;
            }
        }
        return false;
    }

    public function hasCronBeenInvokedInLastHour()
    {
        $invokeTime = $this->getLastCronInvocationTime();
        if (!empty($invokeTime)) {
            return $invokeTime->gt(\WHMCS\Carbon::now()->subHour());
        }
        return false;
    }

    public function hasCronBeenInvokedIn24Hours()
    {
        if ($this->hasDailyCronRunInLast24Hours()) {
            return true;
        }
        $invokeTime = $this->getLastCronInvocationTime();
        if (!empty($invokeTime)) {
            $now = \WHMCS\Carbon::now();
            $minimumDateTimeForNextInvocation = $invokeTime->addDay()->second(0)->subMinute();
            if ($now->lt($minimumDateTimeForNextInvocation)) {
                return true;
            }
        }
        return false;
    }

    public function getLastCronInvocationTime()
    {
        $appConf = \DI::make("config");
        if ($appConf->automationStatus && isset($appConf->automationStatus["lastCronInvocationTime"])) {
            $anyInvocation = $appConf->automationStatus["lastCronInvocationTime"];
        } else {
            $anyInvocation = \WHMCS\Config\Setting::getValue("lastCronInvocationTime");
        }
        if ($anyInvocation) {
            try {
                return new \WHMCS\Carbon($anyInvocation, $this->getCronTimeZone());
            } catch (\Exception $e) {
                return NULL;
            }
        }
        return $this->getLastDailyCronInvocationTime();
    }

    public function setCronInvocationTime(\WHMCS\Carbon $now = NULL)
    {
        if (is_null($now)) {
            $now = \WHMCS\Carbon::now();
        }
        \WHMCS\Config\Setting::setValue("lastCronInvocationTime", $now->toDateTimeString());
        $this->setCronTimeZone($now->timezone);
    }

    public function getLastCronMemoryLimit()
    {
        return \WHMCS\TransientData::getInstance()->retrieve(self::LAST_MEMORY_LIMIT_TRANSIENT_KEY);
    }

    public function hasError()
    {
        if (!$this->hasCronEverBeenInvoked() || !$this->hasDailyCronRunInLast24Hours()) {
            return true;
        }
        return false;
    }

    public function hasWarning()
    {
        if (!$this->hasCronBeenInvokedInLastHour()) {
            return true;
        }
        $lastEndTime = $this->getLastDailyCronEndTime();
        if (\App::isRecentlyUpgraded() && !$lastEndTime) {
            return false;
        }
        $lastDailyInvocationTime = $this->getLastDailyCronInvocationTime();
        if ($lastEndTime && $lastDailyInvocationTime && $lastEndTime->gt($lastDailyInvocationTime)) {
            return false;
        }
        if ($lastDailyInvocationTime && $lastDailyInvocationTime->gt(\WHMCS\Carbon::now()->subHours(2))) {
            return false;
        }
        return true;
    }
}
