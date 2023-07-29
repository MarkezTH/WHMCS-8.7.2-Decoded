<?php

namespace WHMCS\Billing\Invoice;

class Snapshot extends \WHMCS\Model\AbstractModel
{
    protected $table = "mod_invoicedata";
    public $timestamps = false;
    protected $primaryKey = "id";
    public $unique = ["invoiceid"];
    protected $columnMap = ["invoiceId" => "invoiceid", "clientsDetails" => "clientsdetails", "customFields" => "customfields"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->integer("id")->autoIncrement();
                $table->integer("invoiceid");
                $table->text("clientsdetails")->collation("utf8_unicode_ci");
                $table->text("customfields")->collation("utf8_unicode_ci");
                $table->string("version", 255)->nullable()->collation("utf8_unicode_ci");
                $table->charset = "utf8mb3";
                $table->collation = "utf8_unicode_ci";
            });
        }
    }

    public function invoice()
    {
        return $this->belongsTo("WHMCS\\Billing\\Invoice", "invoiceid");
    }

    public function getClientsDetailsAttribute()
    {
        $rawClientsDetails = $this->getRawAttribute("clientsdetails");
        if (is_null($rawClientsDetails)) {
            return [];
        }
        $clientsDetails = json_decode($rawClientsDetails, true);
        if (!is_null($clientsDetails) && json_last_error() === JSON_ERROR_NONE) {
            return $clientsDetails;
        }
        return safe_unserialize($rawClientsDetails);
    }

    public function getCustomFieldsAttribute()
    {
        $rawCustomFields = $this->getRawAttribute("customfields");
        if (is_null($rawCustomFields)) {
            return [];
        }
        $customFields = json_decode($rawCustomFields, true);
        if (!is_null($customFields) && json_last_error() === JSON_ERROR_NONE) {
            return $customFields;
        }
        return safe_unserialize($rawCustomFields);
    }

    public function getVersion($SemanticVersion)
    {
        $version = !empty($this->version) ? $this->version : "0.0.0";
        return new \WHMCS\Version\SemanticVersion($version);
    }

    public function setClientsDetailsAttribute($clientsDetails)
    {
        $this->attributes["clientsDetails"] = self::setClientsDetailsMutator($clientsDetails);
    }

    protected static function setClientsDetailsMutator($clientsDetails)
    {
        return json_encode($clientsDetails);
    }

    public function setCustomFieldsAttribute($customFields)
    {
        $this->attributes["customFields"] = self::setCustomFieldsMutator($customFields);
    }

    protected static function setCustomFieldsMutator($customFields)
    {
        return json_encode($customFields);
    }
}
