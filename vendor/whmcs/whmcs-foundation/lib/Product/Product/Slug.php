<?php

namespace WHMCS\Product\Product;

class Slug extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblproducts_slugs";
    protected $columnMap = ["productId" => "product_id", "groupId" => "group_id", "groupSlug" => "group_slug", "createdAt" => "created_at", "updatedAt" => "updated_at"];
    protected $booleans = ["active"];
    protected $fillable = ["product_id", "group_id", "group_slug", "slug", "active"];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("active", "desc")->orderBy("product_id")->orderBy("group_id");
        });
        static::deleted(function (Slug $slug) {
            $slug->tracking()->delete();
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
                $table->unsignedInteger("product_id");
                $table->unsignedInteger("group_id");
                $table->string("group_slug")->default("");
                $table->string("slug")->default("");
                $table->unsignedTinyInteger("active")->default(0);
                $table->unsignedInteger("clicks")->default(0);
                $table->timestamps();
                $table->index(["product_id"], "tblproducts_slugs_product_id_index");
                $table->index(["group_id"], "tblproducts_slugs_group_id_index");
            });
        }
    }

    public function product($Relation)
    {
        return $this->belongsTo("WHMCS\\Product\\Product");
    }

    public function group($Relation)
    {
        return $this->belongsTo("WHMCS\\Product\\Group");
    }

    public function tracking($Relation)
    {
        return $this->hasMany("WHMCS\\Product\\Product\\SlugTracking");
    }
}
