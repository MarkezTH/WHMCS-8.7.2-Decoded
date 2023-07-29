<?php

namespace WHMCS\Product\Product;

class SlugTracking extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblproducts_slugs_tracking";
    protected $fillable = ["slug_id", "date"];
    protected $columnMap = ["slugId" => "slug_id"];
    protected $dates = ["date"];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("id");
        });
    }

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->unsignedInteger("slug_id");
                $table->date("date")->default("0000-00-00");
                $table->unsignedInteger("clicks")->default(0);
                $table->timestamps();
                $table->index(["slug_id"], "tblproducts_slugs_tracking_slug_id_index");
            });
        }
    }

    public function slug($Relation)
    {
        return $this->belongsTo("WHMCS\\Product\\Product\\Slug");
    }
}
