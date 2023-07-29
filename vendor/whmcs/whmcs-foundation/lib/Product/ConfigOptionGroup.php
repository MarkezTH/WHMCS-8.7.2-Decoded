<?php

namespace WHMCS\Product;

class ConfigOptionGroup extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblproductconfiggroups";
    protected $primaryKey = "id";
    public $timestamps = false;
    protected $fillable = ["name", "description"];
    protected $casts = ["name" => "string", "description" => "string"];
    protected $configOptionClass = "WHMCS\\Product\\ConfigOption";

    public function configOptions()
    {
        return $this->hasMany($this->configOptionClass, "gid", "id");
    }
}
