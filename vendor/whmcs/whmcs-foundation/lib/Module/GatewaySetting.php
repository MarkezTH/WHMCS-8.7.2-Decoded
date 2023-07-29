<?php

namespace WHMCS\Module;

class GatewaySetting extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblpaymentgateways";
    public $timestamps = false;
    protected $fillable = ["gateway", "setting"];
    const CLEARTEXT_SETTINGS = ["name", "FriendlyName", "type", "visible", "convertto"];

    public function scopeGateway(\Illuminate\Database\Eloquent\Builder $query, $gatewayName)
    {
        return $query->where("gateway", $gatewayName);
    }

    public function scopeSetting(\Illuminate\Database\Eloquent\Builder $query, $settingName)
    {
        return $query->where("setting", $settingName);
    }

    public function scopeName($query)
    {
        return $query->where("setting", "name");
    }

    public function scopeGatewayType(\Illuminate\Database\Eloquent\Builder $query, $type)
    {
        if (!is_array($type)) {
            $type = [$type];
        }
        return $query->where("setting", "type")->whereIn("value", $type);
    }

    public static function getValue($gateway, $setting)
    {
        $instance = self::gateway($gateway)->setting($setting)->first();
        return $instance ? $instance->value : NULL;
    }

    public static function setValue($gateway, $setting, $value, int $order = NULL)
    {
        $setting = static::firstOrNew(["gateway" => $gateway, "setting" => $setting]);
        $setting->value = $value;
        if (!$setting->exists || !is_null($order)) {
            $setting->order = $order ?? 0;
        }
        $setting->save();
        return $setting;
    }

    public static function getForGateway($gateway)
    {
        return self::gateway($gateway)->get()->pluck("value", "setting")->toArray();
    }

    public static function getOrderForGateway($gateway)
    {
        $setting = static::gateway($gateway)->setting("name")->first();
        if ($setting) {
            return $setting->order;
        }
        return (int) static::query()->max("order") + 1;
    }

    private function getValueEncryptionKey()
    {
        if (!(isset($this->gateway) && isset($this->setting))) {
            throw new \WHMCS\Exception("Gateway name or setting name not set, cannot process value encryption/decryption");
        }
        return hash("sha256", $this->gateway . ":" . $this->setting . ":" . \DI::make("config")["cc_encryption_hash"]);
    }

    private function isClearTextSetting()
    {
        if (!isset($this->setting)) {
            throw new \WHMCS\Exception("Gateway setting name not set, cannot process value");
        }
        return in_array($this->setting, self::CLEARTEXT_SETTINGS);
    }

    private function decryptRawValue($rawValue)
    {
        $value = NULL;
        if ($this->isAesDecryptable($rawValue)) {
            try {
                $value = json_decode(trim($this->aesDecryptValue($rawValue, $this->getValueEncryptionKey()), chr(0)), true);
            } catch (\Throwable $e) {
            }
        }
        return $value;
    }

    public function encryptAndSavePlainTextValue()
    {
        if ($this->isClearTextSetting()) {
            return false;
        }
        $rawValue = $this->attributes["value"];
        if (!is_null($this->decryptRawValue($rawValue))) {
            return false;
        }
        $this->value = $rawValue;
        $this->save();
        return true;
    }

    public function getValueAttribute()
    {
        $rawValue = $this->getRawAttribute("value");
        if ($this->isClearTextSetting()) {
            return $rawValue;
        }
        if (is_null($rawValue)) {
            return NULL;
        }
        $value = $this->decryptRawValue($rawValue);
        if (is_null($value)) {
            if ($rawValue === quoted_printable_encode($rawValue)) {
                $value = $rawValue;
            } else {
                $value = "";
            }
        }
        return $value;
    }

    public function setValueAttribute($value)
    {
        if (!$this->isClearTextSetting()) {
            $value = $this->aesEncryptValue(str_pad(json_encode($value), 32, chr(0)), $this->getValueEncryptionKey());
        }
        $this->attributes["value"] = $value;
    }

    public static function getActiveGatewayModules($type)
    {
        $query = static::orderBy("gateway", "ASC")->distinct();
        if (is_null($type)) {
            $query->whereNotIn("setting", ["forcesubscriptions", "forceonetime"]);
        } else {
            $query->where("setting", "type")->where("value", $type);
        }
        return $query->pluck("gateway")->toArray();
    }

    public static function getActiveGatewayFriendlyNames()
    {
        return static::setting("name")->orderBy("order", "ASC")->get()->pluck("value", "gateway")->all();
    }

    public static function getActiveGatewayTypes()
    {
        return static::setting("type")->orderBy("order", "ASC")->get()->pluck("value", "gateway")->all();
    }

    public static function getVisibleGatewayFriendlyNames()
    {
        $allSettings = static::all();
        $visibleGateways = $allSettings->filter(function (GatewaySetting $setting) {
            if (\WHMCS\Auth::isLoggedIn() && defined("ADMINAREA")) {
                return $setting->setting === "name";
            }
            return $setting->setting === "visible" && $setting->value === "on";
        })->pluck("gateway")->unique()->toArray();
        return $allSettings->filter(function (GatewaySetting $setting) use($visibleGateways) {
            return in_array($setting->gateway, $visibleGateways, true) && $setting->setting === "name";
        })->sortBy("order")->pluck("value", "gateway")->toArray();
    }

    public static function getFriendlyNameFor($gateway)
    {
        return (string) static::getValue($gateway, "name");
    }

    public static function getTypeFor($gateway)
    {
        return (string) static::getValue($gateway, "type");
    }

    public static function getConvertToFor($gateway)
    {
        return (string) static::getValue($gateway, "convertto");
    }
}
