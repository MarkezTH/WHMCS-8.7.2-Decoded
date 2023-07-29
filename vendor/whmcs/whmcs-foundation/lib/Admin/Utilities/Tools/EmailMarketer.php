<?php

namespace WHMCS\Admin\Utilities\Tools;

class EmailMarketer extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblemailmarketer";
    protected $pivotTable = "tblemailmarketer_related_pivot";
    protected $columnMap = ["disabled" => "disable"];

    public function createPivotTable($drop = false)
    {
        $schemaBuilder = \Illuminate\Database\Capsule\Manager::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->pivotTable);
        }
        if (!$schemaBuilder->hasTable($this->pivotTable)) {
            $schemaBuilder->create($this->pivotTable, function ($table) {
                $table->increments("id");
                $table->integer("task_id", false, true)->default(0);
                $table->integer("product_id", false, true)->default(0);
                $table->integer("addon_id", false, true)->default(0);
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
            });
        }
    }

    public function setSettingsAttribute($settings)
    {
        if (is_array($settings)) {
            $settings = json_encode($settings);
        }
        if (!is_string($settings) || substr($settings, 0, 1) !== "{") {
            $settings = json_encode([]);
        }
        $this->attributes["settings"] = $settings;
    }

    public function getSettingsAttribute()
    {
        $stored = json_decode($this->getRawAttribute("settings"), true);
        if (!is_array($stored)) {
            return $stored;
        }
        return array_merge($this->defaultSettings(), $stored);
    }

    public function products()
    {
        return $this->belongsToMany("WHMCS\\Product\\Product", $this->pivotTable, "task_id", "product_id")->withTimestamps();
    }

    public function addons()
    {
        return $this->belongsToMany("WHMCS\\Product\\Addon", $this->pivotTable, "task_id", "addon_id")->withTimestamps();
    }

    public function defaultSettings()
    {
        return ["clientnumdays" => "", "clientsminactive" => "", "clientsmaxactive" => "", "clientemailtpl" => "", "products" => [], "addons" => [], "prodstatus" => [], "product_cycle" => [], "prodnumdays" => "", "prodfiltertype" => "", "prodexcludepid" => [], "prodexcludeaid" => [], "prodemailtpl" => ""];
    }
}
