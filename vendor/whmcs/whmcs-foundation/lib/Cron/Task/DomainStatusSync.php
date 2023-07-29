<?php

namespace WHMCS\Cron\Task;

class DomainStatusSync extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 2150;
    protected $defaultFrequency = 240;
    protected $defaultDescription = "Domain Status Syncing";
    protected $defaultName = "Domain Status Synchronisation";
    protected $systemName = "DomainStatusSync";
    protected $outputs = ["synced" => ["defaultValue" => 0, "identifier" => "synced", "name" => "Synced"]];
    protected $icon = "fas fa-history";
    protected $successCountIdentifier = "synced";
    protected $successKeyword = "Domains Synced";
    protected $skipDailyCron = true;

    public function __invoke()
    {
        if (!\WHMCS\Config\Setting::getValue("DomainSyncEnabled")) {
            logActivity("Domain Sync Cron: Disabled. Run Aborted.");
        } else {
            $syncCount = 0;
            try {
                $cronreport = sprintf("Domain Synchronisation Cron Report for %s <br />\n<br />\n", \WHMCS\Carbon::now()->toAdminDateTimeFormat());
                $registrarConfiguration = $curlErrorRegistrars = [];
                $cronreport .= "Active Domain Syncs<br />\n";
                $domainsSyncCount = \WHMCS\Domain\Domain::dueForSync()->count();
                if ($domainsSyncCount <= 0) {
                    \WHMCS\Database\Capsule::table("tbldomains")->update(["synced" => "0"]);
                }
                $domainsToSync = \WHMCS\Domain\Domain::dueForSync()->orderBy("status", "DESC")->orderBy("id")->limit(50)->get();
                foreach ($domainsToSync as $domainToSync) {
                    $domain = NULL;
                    $registrar = NULL;
                    try {
                        $domainid = $domainToSync->id;
                        $domain = $domainToSync->domain;
                        $registrar = $domainToSync->registrar;
                        $expirydate = $domainToSync->expirydate;
                        $nextduedate = $domainToSync->nextduedate;
                        $status = $domainToSync->status;
                        $module = \WHMCS\Module\Registrar::factoryFromDomain($domainToSync);
                        $updateqry = [];
                        $updateqry["synced"] = "1";
                        $response = $synceditems = [];
                        if ($module->functionExists("Sync") && !in_array($registrar, $curlErrorRegistrars)) {
                            $response = $module->call("Sync");
                            if (empty($response["error"])) {
                                if (!empty($response["active"]) && $status != "Active") {
                                    $updateqry["status"] = "Active";
                                    $synceditems[] = "Status Changed to Active";
                                }
                                if (!empty($response["cancelled"]) && $status == "Active") {
                                    $updateqry["status"] = "Cancelled";
                                    $synceditems[] = "Status Changed to Cancelled";
                                }
                                if (!empty($response["expirydate"]) && $expirydate->toDateString() != $response["expirydate"]) {
                                    $updateqry["expirydate"] = $response["expirydate"];
                                    $updateqry["reminders"] = "";
                                    $synceditems[] = "Expiry Date updated to " . fromMySQLDate($response["expirydate"]);
                                }
                                if (array_key_exists("transferredAway", $response) && $response["transferredAway"] && !in_array($status, [\WHMCS\Domain\Status::TRANSFERRED_AWAY, \WHMCS\Domain\Status::PENDING_REGISTRATION])) {
                                    $updateqry["status"] = "Transferred Away";
                                    $synceditems[] = "Status Changed to Transferred Away";
                                }
                                if (\WHMCS\Config\Setting::getValue("DomainSyncNextDueDate") && $response["expirydate"]) {
                                    $newexpirydate = $response["expirydate"];
                                    if ($syncDueDateDays = \WHMCS\Config\Setting::getValue("DomainSyncNextDueDateDays")) {
                                        $newexpirydate = explode("-", $newexpirydate);
                                        $newexpirydate = date("Y-m-d", mktime(0, 0, 0, $newexpirydate[1], $newexpirydate[2] - $syncDueDateDays, $newexpirydate[0]));
                                    }
                                    if ($newexpirydate != $nextduedate->toDateString()) {
                                        $updateqry["nextduedate"] = $newexpirydate;
                                        $updateqry["nextinvoicedate"] = $newexpirydate;
                                        $synceditems[] = "Next Due Date updated to " . fromMySQLDate($newexpirydate);
                                    }
                                }
                            }
                        }
                        if (\WHMCS\Config\Setting::getValue("DomainSyncNotifyOnly")) {
                            $updateqry = ["synced" => "1"];
                        }
                        update_query("tbldomains", $updateqry, ["id" => $domainid]);
                        $syncCount++;
                        $cronreport .= " - " . $domain . ": ";
                        if (!count($response)) {
                            if (in_array($registrar, $curlErrorRegistrars)) {
                                $cronreport .= "Sync Skipped Due to cURL Error";
                            } else {
                                $cronreport .= "Sync Not Supported by Registrar Module";
                            }
                        } else {
                            if (!empty($response["error"]) && strtolower(substr($response["error"], 0, 4)) == "curl") {
                                if (!in_array($registrar, $curlErrorRegistrars)) {
                                    $curlErrorRegistrars[] = $registrar;
                                }
                                $cronreport .= "Error: " . $response["error"];
                            } else {
                                if (!empty($response["error"])) {
                                    $cronreport .= "Error: " . $response["error"];
                                } else {
                                    if (!function_exists($registrar . "_TransfersSync") && $status == "Pending Transfer" && $response["active"]) {
                                        sendMessage("Domain Transfer Completed", $domainid);
                                    }
                                    $suffix = "In Sync";
                                    if (count($synceditems) && \WHMCS\Config\Setting::getValue("DomainSyncNotifyOnly")) {
                                        $suffix = "Out of Sync " . implode(", ", $synceditems);
                                    } else {
                                        if (count($synceditems)) {
                                            $suffix = implode(", ", $synceditems);
                                        }
                                    }
                                    $cronreport .= $suffix;
                                }
                            }
                        }
                        $cronreport .= "<br />\n";
                    } catch (\Throwable $e) {
                        logActivity("Domain Sync Error. Domain: '" . ($domain ?: "unknown") . "', registrar: '" . ($registrar ?: "unknown") . "'");
                    }
                }
                logActivity("Domain Sync Cron: Completed");
                $this->output("synced")->write($syncCount);
                sendAdminNotification("system", "WHMCS Domain Synchronisation Cron Report", $cronreport);
            } catch (\Throwable $e) {
                logActivity("Domain Sync Cron Error: " . $e->getMessage());
                $this->output("synced")->write($syncCount);
            }
        }
        return $this;
    }

    public function getFrequencyMinutes()
    {
        $frequency = (int) \WHMCS\Config\Setting::getValue("DomainStatusSyncFrequency") * 60;
        if (!$frequency || $frequency < 0) {
            $frequency = $this->defaultFrequency;
        }
        return $frequency;
    }
}
