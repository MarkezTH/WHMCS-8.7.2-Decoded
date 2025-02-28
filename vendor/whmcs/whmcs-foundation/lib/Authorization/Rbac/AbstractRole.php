<?php

namespace WHMCS\Authorization\Rbac;

abstract class AbstractRole extends \WHMCS\Model\AbstractModel implements \WHMCS\Authorization\Contracts\PermissionInterface, \WHMCS\Authorization\Contracts\RoleInterface
{
    use RoleTrait;
    public $timestamps = true;
    protected $primaryKey = "id";
    protected $casts = ["permissions" => "json"];
    protected $fillable = ["permissions", "role", "description"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("role", 255)->default("");
                $table->string("description", 255)->default("");
                $table->text("permissions");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
            });
        }
    }

    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->permissions = $model->getData();
        });
    }

    public function newFromBuilder($attributes = [], $connection = NULL)
    {
        $model = parent::newFromBuilder($attributes, $connection);
        $model->setData($model->permissions);
        return $model;
    }

    public function getId()
    {
        return $this->id ?: 0;
    }
}
