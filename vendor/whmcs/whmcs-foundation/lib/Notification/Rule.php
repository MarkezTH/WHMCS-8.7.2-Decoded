<?php

namespace WHMCS\Notification;

class Rule extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblnotificationrules";
    protected $casts = ["conditions" => "array", "provider_config" => "array", "active" => "boolean", "can_delete" => "boolean"];
    protected $commaSeparated = ["events"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("description", 255)->default("");
                $table->string("event_type", 255)->default("");
                $table->string("events", 255)->default("");
                $table->text("conditions");
                $table->string("provider", 255)->default("");
                $table->text("provider_config");
                $table->tinyInteger("active")->default(0);
                $table->tinyInteger("can_delete")->default(0);
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
            });
        }
    }

    public function triggerNotification(Notification $notification)
    {
        $provider = Provider::active()->where("name", "=", $this->provider)->first();
        if (is_null($provider)) {
            return false;
        }
        try {
            $provider->initObject()->sendNotification($notification, $provider->settings, $this->provider_config);
        } catch (\WHMCS\Exception $e) {
            logActivity("Notification sending failed for Rule ID " . $this->id . " - Error: " . strip_tags($e->getMessage()));
        }
    }

    public static function rebuildCache()
    {
        $rulesCache = [];
        foreach (self::all() as $rule) {
            foreach ($rule->events as $event) {
                $rulesCache[$rule->event_type][$event][] = $rule->id;
            }
        }
        \WHMCS\Config\Setting::setValue("NotificationRules", json_encode($rulesCache));
    }

    public static function getCache()
    {
        $cache = \WHMCS\Config\Setting::getValue("NotificationRules");
        if (is_string($cache) && 0 < strlen($cache)) {
            $cache = json_decode($cache, true);
        }
        if (!is_array($cache)) {
            $cache = [];
        }
        return $cache;
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("active", "=", "1");
    }
}
