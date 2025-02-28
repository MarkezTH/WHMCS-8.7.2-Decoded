<?php

namespace WHMCS\UsageBilling;

class MetricUsageSettings
{
    const NAME_INVOICING = "MetricUsageInvoicing";
    const NAME_COLLECTION = "MetricUsageCollection";

    public static function isCollectionEnable()
    {
        $value = \WHMCS\Config\Setting::getValue(static::NAME_COLLECTION);
        if ($value == "on" || is_numeric($value) && $value == 1) {
            return true;
        }
        return false;
    }

    public static function isInvoicingEnabled()
    {
        $value = \WHMCS\Config\Setting::getValue(static::NAME_INVOICING);
        if ($value == "on" || is_numeric($value) && $value == 1) {
            return true;
        }
        return false;
    }

    public static function enableCollection()
    {
        \WHMCS\Config\Setting::setValue(static::NAME_COLLECTION, 1);
    }

    public static function disableCollection()
    {
        \WHMCS\Config\Setting::setValue(static::NAME_COLLECTION, 0);
    }

    public static function enableInvoicing()
    {
        \WHMCS\Config\Setting::setValue(static::NAME_INVOICING, 1);
    }

    public static function disableInvoicing()
    {
        \WHMCS\Config\Setting::setValue(static::NAME_INVOICING, 0);
    }
}
