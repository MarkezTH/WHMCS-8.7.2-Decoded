<?php

namespace WHMCS\Affiliate;

class Accounts extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblaffiliatesaccounts";
    protected $columnMap = ["affiliateId" => "affiliateid", "serviceId" => "relid", "lastPaid" => "lastpaid"];
    protected $dates = ["lastpaid"];

    public function affiliate($Relation)
    {
        return $this->belongsTo("WHMCS\\User\\Client\\Affiliate", "affiliateid");
    }

    public function history($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\History", "affaccid");
    }

    public function pending($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\Pending", "affaccid");
    }

    public function service($Relation)
    {
        return $this->belongsTo("WHMCS\\Service\\Service", "relid");
    }
}
