<?php

namespace WHMCS\Cron\Task;

class AffiliateCommissions extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1620;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "Process Delayed Affiliate Commissions";
    protected $defaultName = "Delayed Affiliate Commissions";
    protected $systemName = "AffiliateCommissions";
    protected $outputs = ["payments" => ["defaultValue" => 0, "identifier" => "payments", "name" => "Affiliate Payments"]];
    protected $icon = "far fa-money-bill-alt";
    protected $successCountIdentifier = "payments";
    protected $successKeyword = "Cleared";

    public function __invoke()
    {
        if (!\WHMCS\Config\Setting::getValue("AffiliatesDelayCommission")) {
            return $this;
        }
        $affiliatePaymentsCleared = 0;
        $deleteIds = [];
        $pendingAffiliatePayouts = \WHMCS\Affiliate\Pending::with("account", "account.affiliate", "account.service")->where("clearingdate", "<=", \WHMCS\Carbon::today()->toDateString())->get();
        foreach ($pendingAffiliatePayouts as $pendingAffiliatePayout) {
            $deleteIds[] = $pendingAffiliatePayout->id;
            $amount = $pendingAffiliatePayout->amount;
            $affiliateAccount = $pendingAffiliatePayout->account;
            if ($affiliateAccount) {
                $affiliate = $affiliateAccount->affiliate;
                $service = $affiliateAccount->service;
                if ($service) {
                    if ($affiliate && $service->status === \WHMCS\Utility\Status::ACTIVE) {
                        $affiliate->balance += $amount;
                        $affiliate->save();
                        $affiliateAccount->lastPaid = \WHMCS\Carbon::now();
                        $affiliateAccount->save();
                        $affiliateAccount->history()->create(["date" => \WHMCS\Carbon::now(), "amount" => $amount, "invoice_id" => $pendingAffiliatePayout->invoiceId, "affiliateid" => $affiliateAccount->affiliateId]);
                        $affiliatePaymentsCleared++;
                    }
                }
            }
        }
        \WHMCS\Affiliate\Pending::whereIn("id", $deleteIds)->delete();
        $this->output("payments")->write($affiliatePaymentsCleared);
        return $this;
    }
}
