<?php

namespace WHMCS\Config\Module;

class ModuleConfiguration extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblmodule_configuration";
    protected $fillable = ["entity_type", "setting_name", "friendly_name", "value"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->table);
        }
        if (!$schemaBuilder->hasTable($this->table)) {
            $schemaBuilder->create($this->table, function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->increments("id");
                $table->string("entity_type", 8)->default("");
                $table->unsignedInteger("entity_id")->default(0);
                $table->string("setting_name", 16)->default("");
                $table->string("friendly_name", 64)->default("");
                $table->string("value", 255)->default("");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
                $table->index("entity_type");
                $table->unique(["entity_type", "entity_id", "setting_name"], "unique_constraint");
            });
        }
    }

    public function productAddon()
    {
        return $this->belongsTo("WHMCS\\Product\\Addon", "entity_id");
    }

    public function product()
    {
        return $this->belongsTo("WHMCS\\Product\\Product", "entity_id");
    }

    public function scopeTypeAddon(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("entity_type", "addon");
    }

    public function scopeTypeProduct(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("entity_type", "product");
    }

    public function scopeOfEntityId(\Illuminate\Database\Eloquent\Builder $query, int $entityId)
    {
        $query->where("entity_id", $entityId);
    }

    public function scopeProvisioningType(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("setting_name", "provisioningType");
    }

    public function saveValue($value)
    {
        $this->value = $value;
        $this->save();
    }
}
