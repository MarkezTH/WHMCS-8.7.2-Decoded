<?php

namespace WHMCS\CustomField;

class CustomFieldValue extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblcustomfieldsvalues";
    protected $columnMap = ["relatedId" => "relid"];
    protected $fillable = ["fieldid", "relid"];

    public function customField()
    {
        return $this->belongsTo("WHMCS\\CustomField", "fieldid");
    }

    public function addon()
    {
        return $this->belongsTo("WHMCS\\Service\\Addon", "relid");
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "relid");
    }

    public function service()
    {
        return $this->belongsTo("WHMCS\\Service\\Service", "relid");
    }

    public function getValueAttribute($value)
    {
        if (strtolower($this->customField->fieldType) === "password") {
            $decryptedValue = $this->decrypt($value);
            if (0 < strlen($decryptedValue)) {
                return $decryptedValue;
            }
        }
        return $value;
    }

    public function setValueAttribute($value)
    {
        if (strtolower($this->customField->fieldType) === "password" && !(is_null($value) || $value === "")) {
            $this->attributes["value"] = $this->encrypt($value);
        } else {
            $this->attributes["value"] = $value;
        }
    }
}
