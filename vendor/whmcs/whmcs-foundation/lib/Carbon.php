<?php

namespace WHMCS;

class Carbon extends \Carbon\Carbon
{
    protected static $supportedLocales = ["af", "ar", "az", "bg", "bn", "ca", "cs", "da", "de", "el", "en", "eo", "es", "et", "eu", "fa", "fi", "fo", "fr", "he", "hr", "hu", "id", "it", "ja", "ko", "lt", "lv", "ms", "nl", "no", "pl", "pt", "pt_BR", "ro", "ru", "sk", "sl", "sq", "sr", "sv", "th", "tr", "uk", "uz", "vi", "zh", "zh-TW"];
    private static $cache = [];
    protected static $days = NULL;
    protected static $shortDays = NULL;
    protected static $months = NULL;
    protected static $shortMonths = NULL;
    protected static $daySuffixes = NULL;
    protected static $timeSuffixes = NULL;
    const JANUARY = 1;
    const FEBRUARY = 2;
    const MARCH = 3;
    const APRIL = 4;
    const MAY = 5;
    const JUNE = 6;
    const JULY = 7;
    const AUGUST = 8;
    const SEPTEMBER = 9;
    const OCTOBER = 10;
    const NOVEMBER = 11;
    const DECEMBER = 12;
    const JAN = 1;
    const FEB = 2;
    const MAR = 3;
    const APR = 4;
    const JUN = 6;
    const JUL = 7;
    const AUG = 8;
    const SEPT = 9;
    const OCT = 10;
    const NOV = 11;
    const DEC = 12;
    const TH = 0;
    const ND = 1;
    const RD = 2;
    const ST = 3;
    const SUN = 0;
    const MON = 1;
    const TUE = 2;
    const WED = 3;
    const THU = 4;
    const FRI = 5;
    const SAT = 6;
    const AM = 0;
    const PM = 1;
    const am = 2;
    const pm = 3;

    public function format($format)
    {
        $date = parent::format($format);
        $day = parent::format("j");
        $class = "Lang";
        if (defined("ADMINAREA")) {
            $class = "AdminLang";
        }
        if (class_exists($class)) {
            foreach (self::$daySuffixes as $daySuffix) {
                $key = "dateTime." . strtolower($daySuffix);
                $date = str_replace($day . $daySuffix, $day . $class::trans($key), $date);
            }
            foreach (self::$days as $day) {
                $key = "dateTime." . strtolower($day);
                $date = str_replace($day, $class::trans($key), $date);
            }
            foreach (self::$shortDays as $day) {
                $key = "dateTime." . strtolower($day);
                $date = str_replace($day, $class::trans($key), $date);
            }
            foreach (self::$months as $month) {
                $key = "dateTime." . strtolower($month);
                $date = str_replace($month, $class::trans($key), $date);
            }
            foreach (self::$shortMonths as $shortMonth) {
                $key = "dateTime." . strtolower($shortMonth);
                $date = str_replace([$shortMonth . " ", $shortMonth . ","], [$class::trans($key) . " ", $class::trans($key) . ","], $date);
            }
            foreach (self::$timeSuffixes as $timeSuffix) {
                $key = "dateTime." . $timeSuffix;
                $date = preg_replace("/(\\d)" . $timeSuffix . "/", "\$1" . $class::trans($key), $date);
            }
        }
        return $date;
    }

    public function translatePassedToFormat($dateTime, $format)
    {
        return self::createFromFormat("Y-m-d H:i:s", $dateTime)->format($format);
    }

    public function translateTimestampToFormat($timestamp, $format)
    {
        return self::createFromTimestamp($timestamp)->format($format);
    }

    public static function setLocale($locale)
    {
        if (!in_array($locale, self::$supportedLocales)) {
            $locale = "en";
        }
        parent::setLocale($locale);
    }

    public function getAdminDateFormat($withTime = false)
    {
        $dateFormat = Config\Setting::getValue("DateFormat");
        if (!$dateFormat) {
            $dateFormat = "DD/MM/YYYY";
        }
        $dateFormat = str_replace(["DD", "MM", "YYYY"], ["d", "m", "Y"], $dateFormat);
        if ($withTime) {
            $dateFormat .= " H:i";
        }
        return $dateFormat;
    }

    public function toAdminDateFormat()
    {
        $key = "admin.withouttime." . $this->timestamp;
        return $this->getFormattedCacheOrCalculation($key);
    }

    public function toAdminDateTimeFormat()
    {
        $key = "admin.withtime." . $this->timestamp;
        return $this->getFormattedCacheOrCalculation($key);
    }

    public static function createFromAdminDateFormat($dateString)
    {
        return self::createFromFormat((new self())->getAdminDateFormat(), $dateString)->startOfDay();
    }

    public static function createFromAdminDateTimeFormat($dateTimeString)
    {
        return self::createFromFormat((new self())->getAdminDateFormat(true), $dateTimeString);
    }

    public function getClientDateFormat($withTime = false)
    {
        $clientDateFormat = Config\Setting::getValue("ClientDateFormat");
        if ($clientDateFormat == "full") {
            $dateFormat = "jS F Y";
        } else {
            if ($clientDateFormat == "shortmonth") {
                $dateFormat = "jS M Y";
            } else {
                if ($clientDateFormat == "fullday") {
                    $dateFormat = "l, F jS, Y";
                } else {
                    $dateFormat = $this->getAdminDateFormat();
                }
            }
        }
        if ($withTime) {
            $dateFormat .= " (H:i)";
        }
        return $dateFormat;
    }

    public function toClientDateFormat()
    {
        $key = "client.withouttime." . $this->timestamp;
        return $this->getFormattedCacheOrCalculation($key);
    }

    public function toClientDateTimeFormat()
    {
        $key = "client.withtime." . $this->timestamp;
        return $this->getFormattedCacheOrCalculation($key);
    }

    private function getCacheDateTime($key)
    {
        return self::$cache[$key] ?? NULL;
    }

    private function setCacheDateTime($key, $value)
    {
        self::$cache[$key] = $value;
        return $this;
    }

    private function getFormattedCacheOrCalculation($key)
    {
        $cached = $this->getCacheDateTime($key);
        if (!$cached) {
            $formatted = "";
            $runHook = true;
            list($target, $time) = explode(".", $key);
            $time = $time === "withtime";
            $hook = $time ? "FormatDateTimeForClientAreaOutput" : "FormatDateForClientAreaOutput";
            switch ($target) {
                case "client":
                    break;
                default:
                    $runHook = false;
                    if ($runHook) {
                        $results = \WHMCS\Application\Support\Facades\HookMgr::run($hook, ["date" => $this]);
                        foreach ($results as $result) {
                            if ($result && is_string($result)) {
                                $formatted = $result;
                            }
                        }
                    }
                    if (!$formatted) {
                        $functionToCall = "get" . ucfirst($target) . "DateFormat";
                        $formatted = parent::format($this->{$functionToCall}($time));
                    }
                    $this->setCacheDateTime($key, $formatted);
                    $cached = $formatted;
            }
        }
        return $cached;
    }

    public function modify($modify)
    {
        $result = parent::modify($modify);
        if (!$result instanceof $this) {
            throw new \Exception("Invalid date format: " . var_export($modify, true));
        }
        return $result;
    }

    public static function parseDateRangeValue($value, $withTime = false)
    {
        $carbon = new self();
        $format = $carbon->getAdminDateFormat($withTime);
        if (defined("CLIENTAREA")) {
            $format = $carbon->getClientDateFormat($withTime);
        }
        $value = explode(" - ", $value);
        $firstDate = self::createFromFormat($format, $value[0]);
        if (!$withTime) {
            $firstDate->startOfDay();
        }
        if (!empty($value[1])) {
            $secondDate = self::createFromFormat($format, $value[1]);
            if (!$withTime) {
                $secondDate->endOfDay();
            }
        } else {
            $secondDate = $firstDate->copy();
            if (!$withTime) {
                $secondDate->endOfDay();
            }
        }
        $return = [];
        $return[] = $firstDate;
        $return[] = $secondDate;
        $return["from"] = $firstDate;
        $return["to"] = $secondDate;
        return $return;
    }

    public static function fromCreditCard($date)
    {
        $instance = NULL;
        $dateParts = explode("/", $date);
        if (!empty($date) && count($dateParts) && $dateParts[0] != "00") {
            try {
                $instance = self::createFromCcInput($date);
            } catch (\Exception $e) {
            }
        }
        return $instance;
    }

    public function toCreditCard()
    {
        return parent::format("m/y");
    }

    public static function optionalValueForCreditCardInput($value)
    {
        if ($value && $value instanceof static) {
            return str_replace("/", " / ", $value->toCreditCard());
        }
        return "";
    }

    public static function createFromCcInput($monthYear)
    {
        $monthYear = str_replace(" ", "", $monthYear);
        if (strlen($monthYear) < 4 || 7 < strlen($monthYear) || 4 < strlen($monthYear) && strpos($monthYear, "/") === false) {
            throw new \InvalidArgumentException("Invalid Expiry Date");
        }
        if (preg_match("/\\/[\\d]{4}\$/", $monthYear)) {
            $format = "m/Y";
        } else {
            if (preg_match("/^[\\d]{4}\$/", $monthYear)) {
                $format = "my";
            } else {
                $format = "m/y";
            }
        }
        try {
            return parent::createFromFormat("d" . $format, "01" . $monthYear)->endOfMonth()->endOfDay();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Unable to create expiry date", $e->getCode(), $e);
        }
    }

    public function whmcsTimeDiffForHumans(\DateTimeInterface $new, $absolute = false)
    {
        $durationCarbon = $this->diff($new, $absolute);
        $duration = "";
        if (0 < $durationCarbon->d) {
            $langKey = "Days";
            if ($durationCarbon->d == 1) {
                $langKey = "Day";
            }
            $lang = $this->translateKey($langKey);
            $duration .= $durationCarbon->d . " " . $lang . " ";
        }
        if (0 < $durationCarbon->h) {
            $langKey = "Hours";
            if ($durationCarbon->h == 1) {
                $langKey = "Hour";
            }
            $lang = $this->translateKey($langKey);
            $duration .= $durationCarbon->h . " " . $lang . " ";
        }
        if (0 < $durationCarbon->i) {
            $langKey = "Minutes";
            if ($durationCarbon->i == 1) {
                $langKey = "Minute";
            }
            $lang = $this->translateKey($langKey);
            $duration .= $durationCarbon->i . " " . $lang;
        }
        return $duration;
    }

    protected function translateKey($langKey)
    {
        $class = "Lang";
        if (defined("ADMINAREA")) {
            $class = "AdminLang";
        }
        $lang = $class::trans("dateTime." . strtolower($langKey));
        if (!$lang || $lang == "dateTime." . strtolower($langKey)) {
            $lang = $langKey;
        }
        return $lang;
    }

    public static function safeCreateFromMySqlDate($mysqlDate)
    {
        if (!$mysqlDate || $mysqlDate == "0000-00-00") {
            return false;
        }
        return parent::createFromFormat("Y-m-d", $mysqlDate);
    }

    public static function safeCreateFromMySqlDateTime($mysqlDateTime)
    {
        if (!$mysqlDateTime || $mysqlDateTime == "0000-00-00 00:00:00") {
            return false;
        }
        return parent::createFromFormat("Y-m-d H:i:s", $mysqlDateTime);
    }

    public function toMicroTime()
    {
        return $this->format("U.u");
    }

    public function startOfMonthMicro()
    {
        return $this->copy()->startOfMonth()->toMicroTime();
    }

    public function endOfMonthMicro()
    {
        return $this->copy()->endOfMonth()->timestamp . ".999999";
    }

    public function startOfDayMicro()
    {
        return $this->copy()->startOfDay()->toMicroTime();
    }

    public function endOfDayMicro()
    {
        return $this->copy()->endOfDay()->timestamp . ".999999";
    }

    public static function createFromTimestamp($timestamp, $tz = NULL)
    {
        $time = explode(".", $timestamp, 2);
        if (!empty($time[1])) {
            $base = parent::createFromTimestamp($timestamp, $tz);
            return new static($base->format("Y-m-d H:i:s") . "." . $time[1], $base->tz);
        }
        return parent::createFromTimestamp($timestamp, $tz);
    }

    public static function zero()
    {
        return static::createFromFormat("Y-m-d H:i:s", "0000-00-00 00:00:00");
    }

    public function isZero()
    {
        return $this->equalTo(static::zero());
    }

    public function isEpoch()
    {
        return $this->equalTo(Carbon::createFromTimestampUTC(0));
    }

    public function isEmpty()
    {
        return $this->isZero() || $this->isEpoch();
    }
}
