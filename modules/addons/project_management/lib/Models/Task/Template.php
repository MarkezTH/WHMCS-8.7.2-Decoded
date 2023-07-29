<?php

namespace WHMCS\Module\Addon\ProjectManagement\Models\Task;

class Template extends \WHMCS\Model\AbstractModel
{
    protected $table = "mod_projecttasktpls";
    public $timestamps = false;
    protected $fillable = ["name", "tasks"];
    protected $casts = ["tasks" => "array"];

    public function createTable($drop = false)
    {
        $tableName = $this->table;
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($tableName);
        }
        if (!$schemaBuilder->hasTable($tableName)) {
            $schemaBuilder->create($tableName, function ($table) {
                $table->increments("id");
                $table->string("name", 256)->default("");
                $table->text("tasks");
            });
        }
    }

    public function dropTable()
    {
        $tableName = $this->table;
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        $schemaBuilder->dropIfExists($tableName);
    }
}
