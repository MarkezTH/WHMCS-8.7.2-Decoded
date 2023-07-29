<?php

namespace WHMCS\User\Client;

class Affiliate extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblaffiliates";
    protected $columnMap = ["visitorCount" => "visitors", "commissionType" => "paytype", "paymentAmount" => "payamount", "isPaidOneTimeCommission" => "onetime", "amountWithdrawn" => "withdrawn"];
    protected $dates = ["date"];
    protected $appends = ["pendingCommissionAmount"];

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\User\\Observers\\AffiliateObserver");
    }

    public function accounts($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\Accounts", "affiliateid");
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "clientid");
    }

    public function history($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\History", "affiliateid");
    }

    public function hits($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\Hit");
    }

    public function referrers($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\Referrer");
    }

    public function withdrawals($Relation)
    {
        return $this->hasMany("WHMCS\\Affiliate\\Withdrawals", "affiliateid");
    }

    public function pending($Relation)
    {
        return $this->hasManyThrough("WHMCS\\Affiliate\\Pending", "WHMCS\\Affiliate\\Accounts", "affiliateid", "affaccid");
    }

    public function getReferralLink()
    {
        return \App::getSystemURL() . "aff.php?aff=" . $this->id;
    }

    public function getAdminLink()
    {
        return \App::get_admin_folder_name() . "/affiliates.php?action=edit&id=" . $this->id;
    }

    public function getFullAdminUrl()
    {
        return \App::getSystemURL() . $this->getAdminLink();
    }

    public function getPendingCommissionAmountAttribute()
    {
        return $this->pending()->sum("amount");
    }
}
