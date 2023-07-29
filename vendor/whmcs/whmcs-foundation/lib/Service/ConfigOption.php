<?php

namespace WHMCS\Service;

class ConfigOption extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblhostingconfigoptions";
    public $timestamps = false;

    public function scopeOfService($query, Service $service)
    {
        return $query->where("relid", $service->id);
    }

    public function productConfigOptionSelection()
    {
        return $this->hasOne("WHMCS\\Product\\ConfigOptionSelection", "id", "optionid");
    }

    public function productConfigOption()
    {
        return $this->hasOne("WHMCS\\Product\\ConfigOption", "id", "configid");
    }

    public function service()
    {
        return $this->hasOne("WHMCS\\Service\\Service", "id", "relid");
    }

    public function metricUsage()
    {
        return $this->hasMany("WHMCS\\UsageBilling\\Service\\MetricUsage", "rel_id", "id")->where("rel_type", \WHMCS\Contracts\ProductServiceTypes::TYPE_SERVICE_CONFIGOPTION);
    }
}
