<?php

namespace WHMCS\Product\EventAction;

class EventAction extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblproducteventactions";
    protected $fillable = ["name", "event_name", "action", "params"];
    protected $casts = ["params" => "array"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->increments("id");
                $table->string("entity_type", 16);
                $table->integer("entity_id");
                $table->string("name", 64);
                $table->string("event_name", 32);
                $table->string("action", 64);
                $table->text("params")->nullable();
                $table->timestamps();
            });
        }
    }

    public function product()
    {
        return $this->belongsTo("WHMCS\\Product\\Product", "entity_id");
    }

    public function productAddon()
    {
        return $this->belongsTo("WHMCS\\Product\\Addon", "entity_id");
    }

    public function scopeOfProduct(\Illuminate\Database\Eloquent\Builder $query, \WHMCS\Product\Product $product)
    {
        return $query->where("entity_type", "product")->ofEntityId($product->id);
    }

    public function scopeOfAddon(\Illuminate\Database\Eloquent\Builder $query, \WHMCS\Product\Addon $addon)
    {
        return $query->where("entity_type", "addon")->ofEntityId($addon->id);
    }

    public function scopeOfEntityId(\Illuminate\Database\Eloquent\Builder $query, int $entityId)
    {
        return $query->where("entity_id", $entityId);
    }

    public function scopeByName(\Illuminate\Database\Eloquent\Builder $query, $name)
    {
        return $query->where("name", $name);
    }

    public function scopeOnEvent(\Illuminate\Database\Eloquent\Builder $query, $eventName)
    {
        return $query->where("event_name", $eventName);
    }
}
