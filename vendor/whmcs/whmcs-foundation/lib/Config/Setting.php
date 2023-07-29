<?php

namespace WHMCS\Config;

class Setting extends \WHMCS\Model\AbstractKeyValuePair
{
    public $incrementing = false;
    protected $table = "tblconfiguration";
    protected $primaryKey = "setting";
    public $unique = ["setting"];
    public $guardedForUpdate = ["setting"];
    protected $fillable = ["value"];
    protected $booleanValues = ["EnableProformaInvoicing"];
    protected $nonEmptyValues = [];
    protected $commaSeparatedValues = ["BulkCheckTLDs"];
    protected static $defaultKeyValuePairs = [];
    const API_NG_API_WHITELIST_APPLY = "ApplyCartApi";
    const API_NG_API_WHITELIST = "APINgAllowedIPs";
    const API_DEBUG_MODE = "ApiDebugMode";

    public static function boot()
    {
        parent::boot();
        self::saved(function (Setting $setting) {
            static::updateRuntimeConfigCache($setting->setting, $setting->value);
        });
        self::deleted(function (Setting $setting) {
            global $CONFIG;
            if (is_array($CONFIG) && array_key_exists($setting->setting, $CONFIG)) {
                unset($CONFIG[$setting->setting]);
            }
        });
    }

    public static function allDefaults()
    {
        $defaultModels = [];
        foreach (static::$defaultKeyValuePairs as $key => $value) {
            $model = static::find($key);
            if (is_null($model)) {
                $model = new static();
                $model->setting = $key;
            }
            $model->value = $value;
            $defaultModels[] = $model;
        }
        $model = new static();
        return $model->newCollection($defaultModels);
    }

    public function scopeUpdater($query)
    {
        return $query->where("setting", "like", "updater%");
    }

    public static function updateRuntimeConfigCache($key, $value)
    {
        global $CONFIG;
        $CONFIG[$key] = $value;
    }

    public static function getValue($setting)
    {
        global $CONFIG;
        if (isset($CONFIG[$setting])) {
            return $CONFIG[$setting];
        }
        if ($setting === "EnableTranslations" && !class_exists("\\Lang")) {
            return "";
        }
        $setting = self::find($setting);
        if (is_null($setting)) {
            return NULL;
        }
        $CONFIG[$setting->setting] = $setting->value;
        return $setting->value;
    }

    public static function setValue($key, $value)
    {
        $value = trim((string) $value);
        $setting = Setting::findOrNew($key);
        $setting->setting = $key;
        $setting->value = $value;
        $setting->save();
        return $setting;
    }

    public static function deleteValue($key)
    {
        $setting = self::find($key);
        if (!is_null($setting)) {
            $setting->delete();
        }
    }

    public static function allAsArray()
    {
        $result = [];
        $allSettings = \Illuminate\Database\Capsule\Manager::table("tblconfiguration")->get()->all();
        $model = new static();
        $csv = $model->getCommaSeparatedValues();
        $bool = $model->getBooleanValues();
        foreach ($allSettings as $setting) {
            $key = $setting->setting;
            if (in_array($key, $bool)) {
                $setting->value = $model::convertBoolean($setting->value);
            } else {
                if (in_array($key, $csv)) {
                    $setting->value = explode(",", $setting->value);
                }
            }
            $result[$setting->setting] = $setting->value;
        }
        return $result;
    }

    public function getBooleanValues()
    {
        return $this->booleanValues;
    }

    public function getCommaSeparatedValues()
    {
        return $this->commaSeparatedValues;
    }

    public function newCollection($models = [])
    {
        $prefix = defined("static::SETTING_PREFIX") ? static::SETTING_PREFIX : "";
        return new SettingCollection($models, get_called_class(), $prefix);
    }
}
