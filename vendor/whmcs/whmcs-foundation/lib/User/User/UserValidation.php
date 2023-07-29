<?php

namespace WHMCS\User\User;

class UserValidation extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbluser_validation";
    protected $columnMap = ["requestorId" => "requestor_id", "submittedAt" => "submitted_at", "reviewedAt" => "reviewed_at"];
    protected $dates = ["submitted_at", "reviewed_at"];
    protected $fillable = ["requestor_id", "token", "status", "submitted_at", "reviewed_at"];

    public function createTable($dropTable = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($dropTable) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->unsignedInteger("requestor_id")->nullable();
                $table->string("token", 100)->nullable();
                $table->string("status", 255)->nullable();
                $table->timestamp("submitted_at")->nullable();
                $table->timestamp("reviewed_at")->nullable();
                $table->timestamps();
            });
        }
    }

    public function requestor()
    {
        return $this->belongsTo("WHMCS\\User\\User", "requestor_id");
    }
}
