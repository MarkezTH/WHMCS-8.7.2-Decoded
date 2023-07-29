<?php

namespace WHMCS\Affiliate;

class Referrer extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblaffiliates_referrers";
    protected $fillable = ["affiliate_id", "referrer"];

    public function affiliate($Relation)
    {
        return $this->belongsTo("WHMCS\\User\\Client\\Affiliate");
    }

    public function hits()
    {
        return $this->hasMany("WHMCS\\Affiliate\\Hit", "referrer_id");
    }
}
