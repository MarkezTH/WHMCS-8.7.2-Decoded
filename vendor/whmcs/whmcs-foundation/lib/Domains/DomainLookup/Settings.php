<?php

namespace WHMCS\Domains\DomainLookup;

class Settings extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbldomain_lookup_configuration";
    protected $fillable = ["registrar", "setting"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("registrar", 32)->default("");
                $table->string("setting", 128)->default("");
                $table->text("value");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
                $table->index(["registrar", "setting"], "registrar_setting_index");
            });
        }
    }

    public function scopeOfRegistrar(\Illuminate\Database\Eloquent\Builder $query, $registrar)
    {
        return $query->whereRegistrar($registrar);
    }
}
