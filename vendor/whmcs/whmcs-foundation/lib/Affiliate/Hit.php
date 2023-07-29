<?php

namespace WHMCS\Affiliate;

class Hit extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblaffiliates_hits";
    public $timestamps = false;
    public $dates = ["created_at"];
    protected $fillable = ["affiliate_id", "referrer_id", "created_at"];

    public function referrer()
    {
        return $this->belongsTo("WHMCS\\Affiliate\\Referrer", "referrer_id", "id");
    }

    public function affiliate($Relation)
    {
        return $this->belongsTo("WHMCS\\User\\Client\\Affiliate");
    }
}
