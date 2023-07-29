<?php

namespace WHMCS\Updater\Version;

class Version832release1 extends IncrementalVersion
{
    protected $runUpdateCodeBeforeDatabase = true;
    protected $updateActions = ["rebuildPendingCommissions"];

    public function rebuildPendingCommissions()
    {
        $startVersionIs831 = \WHMCS\Version\SemanticVersion::compare(static::$startVersion, new \WHMCS\Version\SemanticVersion("8.3.1-release.1"), "=");
        if (!$startVersionIs831) {
            return $this;
        }
        $commissionDelay = \WHMCS\Config\Setting::getValue("AffiliatesDelayCommission");
        if (!$commissionDelay || !\WHMCS\Config\Setting::getValue("AffiliateEnabled")) {
            return $this;
        }
        $upgradeDate = \WHMCS\Config\Setting::where("setting", "Version")->value("updated_at");
        $v831ReleaseDate = \WHMCS\Carbon::createMidnightDate(2021, 10, 28);
        if ($upgradeDate->isZero() || $upgradeDate->lte($v831ReleaseDate)) {
            $invoiceStartDate = $v831ReleaseDate->clone();
        } else {
            $invoiceStartDate = $upgradeDate->clone();
        }
        $invoiceStartDate->subDays($commissionDelay);
        $accounts = \WHMCS\Affiliate\Accounts::whereExists(function (\Illuminate\Database\Query\Builder $query) {
            $query->select(["id"])->from("tblhosting")->whereIn("domainstatus", [\WHMCS\Utility\Status::ACTIVE, \WHMCS\Utility\Status::PENDING])->whereColumn("tblaffiliatesaccounts.relid", "=", "tblhosting.id");
        })->get();
        $serviceIds = $accounts->pluck("relid")->toArray();
        $affiliateToService = $accounts->pluck("affiliateid", "relid")->toArray();
        $invoiceItemsQuery = \WHMCS\Database\Capsule::table("tblinvoiceitems")->join("tblinvoices", "tblinvoices.id", "=", "tblinvoiceitems.invoiceid")->join("tblhosting", "tblhosting.id", "=", "tblinvoiceitems.relid")->whereIn("tblinvoiceitems.relid", $serviceIds)->where("tblinvoiceitems.type", \WHMCS\Billing\InvoiceItemInterface::TYPE_SERVICE)->where("tblinvoices.status", \WHMCS\Billing\Invoice::STATUS_PAID)->orderBy("tblinvoiceitems.id", "desc");
        if (!$upgradeDate->isZero() && $upgradeDate->gte($v831ReleaseDate)) {
            $invoiceItemsQuery->whereBetween("tblinvoices.datepaid", [$invoiceStartDate->startOfDay(), $upgradeDate->endOfDay()]);
        } else {
            $invoiceItemsQuery->whereDate("tblinvoices.datepaid", ">=", $invoiceStartDate->startOfDay());
        }
        $invoiceItems = $invoiceItemsQuery->get(["tblinvoices.id", "tblinvoices.datepaid", "tblinvoices.duedate", "tblinvoiceitems.relid"]);
        $transientLog = [];
        foreach ($invoiceItems as $invoiceItem) {
            $datePaid = $invoiceItem->datepaid;
            $dateDue = $invoiceItem->duedate;
            $serviceId = $invoiceItem->relid;
            $invoiceId = $invoiceItem->id;
            if (!empty($affiliateToService[$serviceId])) {
                $affiliateAccount = $accounts->firstWhere("relid", $serviceId);
                if ($affiliateAccount) {
                    $affiliateAccountId = $affiliateAccount->id;
                    unset($affiliateAccount);
                    if ($dateDue && $affiliateAccountId) {
                        $datePaid = \WHMCS\Carbon::createFromFormat("Y-m-d H:i:s", $datePaid);
                        $commissionDate = $datePaid->clone()->addDays($commissionDelay);
                        $historyItem = \WHMCS\Affiliate\History::where("affiliateid", $affiliateToService[$serviceId])->whereDate("date", $commissionDate)->count();
                        if (!$historyItem) {
                            $pendingItem = \WHMCS\Affiliate\Pending::where("affaccid", $affiliateAccountId)->whereDate("clearingdate", $commissionDate)->count();
                            if (!$pendingItem) {
                                $newPending = new \WHMCS\Affiliate\Pending();
                                $newPending->affiliateAccountId = $affiliateAccountId;
                                $newPending->invoiceId = $invoiceId;
                                $newPending->amount = calculateAffiliateCommission($affiliateToService[$serviceId], $serviceId);
                                $newPending->clearingDate = $commissionDate;
                                $newPending->save();
                                $transientLog[] = ["commissionDate" => $commissionDate->format("Y-m-d H:i:s"), "invoiceId" => $invoiceId, "affiliateId" => $affiliateToService[$serviceId], "affiliateAccountId" => $affiliateAccountId, "serviceId" => $serviceId, "amount" => $newPending->amount];
                                unset($newPending);
                            }
                        }
                    }
                }
            }
        }
        if (0 < count($transientLog)) {
            $transient = \WHMCS\TransientData::getInstance();
            $transientWarnings = $transient->retrieve("transientWarnings");
            if ($transientWarnings) {
                $transientWarnings = json_decode($transientWarnings, true);
            } else {
                $transientWarnings = [];
            }
            array_unshift($transientWarnings, ["title" => "Attention", "description" => "Missing pending affiliate commissions have been restored.", "learnMore" => ["href" => "https://go.whmcs.com/1641/restore-pending-affiliate-commission-v8.3.1", "text" => "Learn More"]]);
            $transient->store("transientWarnings", json_encode($transientWarnings), \Carbon\CarbonInterval::days(30)->totalSeconds);
            $transient->store("832AffiliateDataRecovery", json_encode($transientLog), \Carbon\CarbonInterval::years(2)->totalSeconds);
            unset($transientLog);
            unset($transient);
            unset($transientWarnings);
        }
        return $this;
    }
}
