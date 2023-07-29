<?php

namespace WHMCS\Api\Log;

class Log extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblapilog";
    protected $guarded = [];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("action", 255)->default("");
                $table->string("endpoint", 255)->nullable();
                $table->enum("method", ["GET", "POST", "PUT", "PATCH", "DELETE"])->nullable();
                $table->text("request")->default("");
                $table->text("request_headers")->nullable();
                $table->text("response")->default("");
                $table->integer("response_status")->default(0);
                $table->text("response_headers")->default("");
                $table->integer("level")->default(0);
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
            });
        }
    }
}
