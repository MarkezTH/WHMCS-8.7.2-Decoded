<?php

namespace WHMCS\User;

class AdminLog extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbladminlog";
    protected $columnMap = ["username" => "adminusername"];
    public $timestamps = false;
    public $unique = ["sessionid"];

    public function admin()
    {
        return $this->belongsTo("\\WHMCS\\User\\Admin", "adminusername", "username");
    }

    public function scopeOnline($query)
    {
        return $query->where("lastvisit", ">", \WHMCS\Carbon::now()->subMinutes(15))->groupBy("adminusername")->orderBy("lastvisit");
    }
}
