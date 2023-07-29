<?php

namespace WHMCS\Cron\Task;

class DataRetentionPruning extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1800;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "Perform data retention pruning operations.";
    protected $defaultName = "Data Retention Pruning";
    protected $systemName = "DataRetentionPruning";
    protected $outputs = ["clients.deleted" => ["defaultValue" => 0, "identifier" => "deleted", "name" => "Clients Deleted"]];
    protected $icon = "fas fa-trash-alt";
    protected $successCountIdentifier = "clients.deleted";
    protected $successKeyword = "Deleted";

    public function __invoke()
    {
        $this->output("clients.deleted")->write($this->deleteInactiveClients());
        $this->pruneUserInvites();
        return $this;
    }

    protected function deleteInactiveClients()
    {
        if (!\WHMCS\Config\Setting::getValue("DRAutoDeleteInactiveClients")) {
            return 0;
        }
        $requiredInactiveMonths = (int) \WHMCS\Config\Setting::getValue("DRAutoDeleteInactiveClientsMonths");
        if ($requiredInactiveMonths <= 0) {
            return 0;
        }
        $deletedCount = 0;
        $query = \WHMCS\User\Client::whereIn("status", ["Inactive", "Closed"]);
        $oldestViableInactivity = \WHMCS\Carbon::now()->subMonths($requiredInactiveMonths)->format("Y-m-d");
        $query->where("datecreated", "<", $oldestViableInactivity)->where("datecreated", "!=", "0000-00-00");
        $usersToDelete = [];
        foreach ($query->get() as $client) {
            $latestInvoice = $client->invoices()->paid()->orderBy("datepaid", "desc")->first();
            if (is_null($latestInvoice) || \WHMCS\Carbon::now()->diffInMonths($latestInvoice->datepaid) >= $requiredInactiveMonths) {
                $latestTransaction = $client->transactions()->orderBy("date", "desc")->first();
                if (is_null($latestTransaction) || \WHMCS\Carbon::now()->diffInMonths($latestTransaction->date) >= $requiredInactiveMonths) {
                    if (0 >= $client->services()->isConsideredActive()->isNotRecurring()->count()) {
                        if (0 >= $client->addons()->isConsideredActive()->isNotRecurring()->count()) {
                            if (0 >= $client->domains()->isConsideredActive()->count()) {
                                if ($client->affiliate) {
                                    if (0 >= $client->affiliate->balance) {
                                        $latestAffiliateHistory = \WHMCS\Database\Capsule::table("tblaffiliateshistory")->where("affiliateid", $client->affiliate->id)->orderBy("date", "desc")->first();
                                        if ($latestAffiliateHistory) {
                                            try {
                                                $latestHistoryDate = \WHMCS\Carbon::parse($latestAffiliateHistory->date);
                                                $monthsDifferent = \WHMCS\Carbon::now()->diffInMonths($latestHistoryDate);
                                                if ($monthsDifferent >= $requiredInactiveMonths) {
                                                }
                                            } catch (\Exception $e) {
                                            }
                                        }
                                        $latestAffiliateReferral = \WHMCS\Database\Capsule::table("tblaffiliates_referrers")->where("affiliate_id", $client->affiliate->id)->orderBy("created_at", "desc")->first();
                                        if ($latestAffiliateReferral) {
                                            try {
                                                $latestReferralDate = \WHMCS\Carbon::parse($latestAffiliateReferral->created_at);
                                                $monthsDifferent = \WHMCS\Carbon::now()->diffInMonths($latestReferralDate);
                                                if ($monthsDifferent >= $requiredInactiveMonths) {
                                                }
                                            } catch (\Exception $e) {
                                            }
                                        }
                                    }
                                }
                                try {
                                    $usersToDelete = $client->users;
                                    $client->deleteEntireClient();
                                    if (\WHMCS\Config\Setting::getValue("DRAutoDeleteInactiveClientsUsers")) {
                                        foreach ($usersToDelete as $user) {
                                            if (count($user->clients) === 0) {
                                                $user->delete();
                                            }
                                        }
                                    }
                                    $deletedCount++;
                                } catch (\Exception $e) {
                                }
                            }
                        }
                    }
                }
            }
        }
        return $deletedCount;
    }

    protected function pruneUserInvites(int $daysToRetain = 14)
    {
        $query = \WHMCS\Database\Capsule::table("tbluser_invites")->where("created_at", "<", \WHMCS\Carbon::now()->subDays($daysToRetain)->endOfDay()->toDateTimeString());
        $affectedRows = $query->delete();
        return $affectedRows;
    }
}
