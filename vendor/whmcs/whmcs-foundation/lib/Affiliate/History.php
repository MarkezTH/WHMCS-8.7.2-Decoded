<?php

namespace WHMCS\Affiliate;

class History extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblaffiliateshistory";
    protected $columnMap = ["affiliateId" => "affiliateid", "affiliateAccountId" => "affaccid", "invoiceId" => "invoice_id"];
    protected $dates = ["date"];
    protected $fillable = ["affiliateid", "date", "invoice_id", "amount", "description"];

    public function affiliate($Relation)
    {
        return $this->belongsTo("WHMCS\\User\\Client\\Affiliate", "affiliateid");
    }

    public function account($Relation)
    {
        return $this->belongsTo("WHMCS\\Affiliate\\Accounts", "affaccid");
    }

    public function invoice($Relation)
    {
        return $this->hasOne("WHMCS\\Billing\\Invoice", "id", "invoice_id");
    }

    public function reverse(int $invoiceId = 0)
    {
        $newRecord = $this->replicate();
        $newRecord->amount *= -1;
        $newRecord->description = "Commission reversal due to refund of invoice payment.";
        if ($invoiceId && !$this->invoiceId) {
            $newRecord->invoiceId = $invoiceId;
            $this->invoiceId = $invoiceId;
            $this->save();
        }
        $newRecord->save();
        $this->affiliate->balance -= $this->amount;
        $this->affiliate->save();
    }
}
