<?php

namespace WHMCS\UsageBilling\Metrics\Units;

class Bytes extends FloatingPoint
{
    public function __construct($name = "Bytes", $singlePerUnitName = "Byte", $pluralPerUnitName = "Bytes", $prefix = NULL, $suffix = "B")
    {
        parent::__construct($name, $singlePerUnitName, $pluralPerUnitName, $prefix, $suffix);
    }

    public static function convert($value, $from, $to)
    {
        $result = $value;
        if ($from == "B") {
            if ($to == "KB") {
                $result = $value / 1024;
            } else {
                if ($to == "MB") {
                    $result = $value / 1024 / 1024;
                } else {
                    if ($to == "GB") {
                        $result = $value / 1024 / 1024 / 1024;
                    }
                }
            }
        } else {
            if ($from == "KB") {
                if ($to == "B") {
                    $result = $value * 1024;
                } else {
                    if ($to == "MB") {
                        $result = $value / 1024;
                    } else {
                        if ($to == "GB") {
                            $result = $value / 1024 / 1024;
                        }
                    }
                }
            } else {
                if ($from == "MB") {
                    if ($to == "B") {
                        $result = $value * 1024 * 1024;
                    } else {
                        if ($to == "KB") {
                            $result = $value * 1024;
                        } else {
                            if ($to == "GB") {
                                $result = $value / 1024;
                            }
                        }
                    }
                } else {
                    if ($from == "GB") {
                        if ($to == "B") {
                            $result = $value * 1024 * 1024 * 1024;
                        } else {
                            if ($to == "KB") {
                                $result = $value * 1024 * 1024;
                            } else {
                                if ($to == "MB") {
                                    $result = $value * 1024;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }
}
