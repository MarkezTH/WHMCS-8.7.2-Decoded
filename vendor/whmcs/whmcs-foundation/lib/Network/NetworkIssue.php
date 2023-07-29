<?php

namespace WHMCS\Network;

class NetworkIssue extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblnetworkissues";
    protected $columnMap = ["affectedType" => "type", "affectedOther" => "affecting", "affectedServerId" => "server", "lastUpdateDate" => "lastupdate"];
    protected $dates = ["startdate", "enddate", "lastupdate"];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblnetworkissues.startdate", "DESC")->orderBy("tblnetworkissues.enddate")->orderBy("tblnetworkissues.id");
        });
    }
}
