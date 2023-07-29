<?php

namespace WHMCS\Cron\Task;

class DomainTransferSync extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 2100;
    protected $defaultFrequency = 240;
    protected $defaultDescription = "Syncing Domain Pending Transfer Status";
    protected $defaultName = "Domain Transfer Status Synchronisation";
    protected $systemName = "DomainTransferSync";
    protected $outputs = ["synced" => ["defaultValue" => 0, "identifier" => "synced", "name" => "Synced"]];
    protected $icon = "fas fa-exchange-alt";
    protected $successCountIdentifier = "synced";
    protected $successKeyword = "Transfers Checked";
    protected $skipDailyCron = true;

    public function __invoke()
    {
        if (!\WHMCS\Config\Setting::getValue("DomainSyncEnabled")) {
            logActivity("Domain Transfer Status Cron: Disabled. Run Aborted.");
        } else {
            $syncCount = 0;
            try {
                $cronreport = sprintf("Domain Transfer Status Checks for  %s <br />\n<br />\n", \WHMCS\Carbon::now()->toAdminDateTimeFormat());
                $registrarConfiguration = $curlErrorRegistrars = [];
                $transfersreport = "";
                $domainsToSync = \WHMCS\Domain\Domain::where("registrar", "!=", "")->where("status", "=", \WHMCS\Domain\Status::PENDING_TRANSFER)->orderBy("id", "ASC")->get();
                foreach ($domainsToSync as $domainModel) {
                    $domainid = $domainModel->id;
                    $domain = $domainModel->domain;
                    $registrar = $domainModel->registrar;
                    $regperiod = $domainModel->registrationperiod;
                    $expirydate = $domainModel->expirydate;
                    $status = $domainModel->status;
                    $module = \WHMCS\Module\Registrar::factoryFromDomain($domainModel);
                    if ($module->functionExists("TransferSync") && !in_array($registrar, $curlErrorRegistrars)) {
                        $transfersreport .= " - " . $domain . ": ";
                        $updateqry = [];
                        try {
                            $response = $module->call("TransferSync");
                        } catch (\Exception $e) {
                            $response = ["error" => $e->getMessage()];
                        }
                        if (empty($response["error"])) {
                            if ($response["active"] || $response["completed"]) {
                                $transfersreport .= "Transfer Completed";
                                $updateqry["status"] = "Active";
                                if (!$response["expirydate"] && $module->functionExists("Sync") && !in_array($registrar, $curlErrorRegistrars)) {
                                    $response = $module->call("Sync");
                                }
                                if ($response["expirydate"]) {
                                    $updateqry["expirydate"] = $response["expirydate"];
                                    $updateqry["reminders"] = "";
                                    $expirydate = $updateqry["expirydate"];
                                    $transfersreport .= " - In Sync";
                                }
                                if (\WHMCS\Config\Setting::getValue("DomainSyncNextDueDate") && $response["expirydate"]) {
                                    $newexpirydate = $response["expirydate"];
                                    $expirydate = $updateqry["expirydate"];
                                    if ($syncDueDateDays = \WHMCS\Config\Setting::getValue("DomainSyncNextDueDateDays")) {
                                        $newexpirydate = explode("-", $newexpirydate);
                                        $newexpirydate = date("Y-m-d", mktime(0, 0, 0, $newexpirydate[1], $newexpirydate[2] - $syncDueDateDays, $newexpirydate[0]));
                                    }
                                    $updateqry["nextduedate"] = $newexpirydate;
                                    $updateqry["nextinvoicedate"] = $newexpirydate;
                                }
                            } else {
                                if ($response["failed"]) {
                                    $transfersreport .= "Transfer Failed";
                                    $updateqry["status"] = "Cancelled";
                                    $failurereason = $response["reason"];
                                    if (!$failurereason) {
                                        $failurereason = \Lang::trans("domaintrffailreasonunavailable");
                                    }
                                    sendMessage("Domain Transfer Failed", $domainid, ["domain_transfer_failure_reason" => $failurereason]);
                                } else {
                                    $transfersreport .= "Transfer Still In Progress";
                                }
                            }
                            if (!\WHMCS\Config\Setting::getValue("DomainSyncNotifyOnly") && count($updateqry)) {
                                update_query("tbldomains", $updateqry, ["id" => $domainid]);
                                if ($updateqry["status"] == "Active") {
                                    sendMessage("Domain Transfer Completed", $domainid);
                                    run_hook("DomainTransferCompleted", ["domainId" => $domainid, "domain" => $domain, "registrationPeriod" => $regperiod, "expiryDate" => $expirydate, "registrar" => $registrar]);
                                } else {
                                    if ($updateqry["status"] == "Cancelled") {
                                        run_hook("DomainTransferFailed", ["domainId" => $domainid, "domain" => $domain, "registrationPeriod" => $regperiod, "expiryDate" => $expirydate, "registrar" => $registrar]);
                                    }
                                }
                            }
                        } else {
                            if ($response["error"] && strtolower(substr($response["error"], 0, 4)) == "curl") {
                                if (!in_array($registrar, $curlErrorRegistrars)) {
                                    $curlErrorRegistrars[] = $registrar;
                                }
                                $transfersreport .= "Error: " . $response["error"];
                            } else {
                                if ($response["error"]) {
                                    $transfersreport .= "Error: " . $response["error"];
                                }
                            }
                        }
                        $transfersreport .= "<br />\n";
                    }
                    $syncCount++;
                }
                if ($transfersreport) {
                    $cronreport .= $transfersreport . "<br />\n";
                    logActivity("Domain Transfer Status Cron: Completed");
                    sendAdminNotification("system", "WHMCS Domain Transfer Status Cron Report", $cronreport);
                }
                $this->output("synced")->write($syncCount);
            } catch (\Exception $e) {
                logActivity("Domain Transfer Status Cron Error: " . $e->getMessage());
                $this->output("synced")->write($syncCount);
            }
        }
        return $this;
    }

    public function getFrequencyMinutes()
    {
        $frequency = (int) \WHMCS\Config\Setting::getValue("DomainTransferStatusCheckFrequency") * 60;
        if (!$frequency || $frequency < 0) {
            $frequency = $this->defaultFrequency;
        }
        return $frequency;
    }
}
