<?php

namespace WHMCS\Cron\Task;

class AutoTerminations extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1590;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "Process Overdue Terminations";
    protected $defaultName = "Overdue Terminations";
    protected $systemName = "AutoTerminations";
    protected $outputs = ["terminations" => ["defaultValue" => 0, "identifier" => "terminations", "name" => "Terminations"], "manual" => ["defaultValue" => 0, "identifier" => "manual", "name" => "Manual Termination Required"], "action.detail" => ["defaultValue" => "", "identifier" => "action.detail", "name" => "Action Detail"]];
    protected $icon = "far fa-calendar-times";
    protected $successCountIdentifier = "terminations";
    protected $failureCountIdentifier = "manual";
    protected $successKeyword = "Terminated";
    protected $hasDetail = true;

    public function __invoke()
    {
        if (!\WHMCS\Config\Setting::getValue("AutoTermination")) {
            $this->output("terminations")->write(0);
            $this->output("success.detail")->write("{}");
            $this->output("manual")->write(0);
            $this->output("failure.detail")->write("{}");
            return $this;
        }
        $clientGroups = \WHMCS\Database\Capsule::table("tblclientgroups")->pluck("susptermexempt", "id")->all();
        $clients = [];
        $terminatedate = \WHMCS\Carbon::today()->subDays(\WHMCS\Config\Setting::getValue("AutoTerminationDays"))->toDateString();
        $query = "SELECT * FROM tblhosting WHERE (domainstatus = 'Active' OR domainstatus = 'Suspended') AND billingcycle != 'Free Account' AND billingcycle != 'One Time' AND billingcycle != 'onetime'" . " AND nextduedate <= '" . $terminatedate . "'" . " AND tblhosting.nextduedate != '0000-00-00'" . " AND overideautosuspend != '1'" . " ORDER BY domain ASC";
        $result = full_query($query);
        while ($data = mysql_fetch_array($result)) {
            $serviceid = $data["id"];
            $userid = $data["userid"];
            $domain = $data["domain"];
            $packageid = $data["packageid"];
            $nextDueDate = $data["nextduedate"];
            if (!array_key_exists($userid, $clients)) {
                $client = \WHMCS\Database\Capsule::table("tblclients")->where("id", $userid)->first(["firstname", "lastname", "groupid"]);
                if ($client) {
                    $clients[$userid] = ["firstname" => $client->firstname, "lastname" => $client->lastname, "groupid" => $client->groupid];
                }
            }
            $firstname = $clients[$userid]["firstname"];
            $lastname = $clients[$userid]["lastname"];
            $groupid = $clients[$userid]["groupid"];
            $result2 = select_query("tblproducts", "tblproducts.name, tblproducts.servertype, tblhosting.nextduedate", ["tblproducts.id" => $packageid, "tblhosting.id" => $serviceid], "", "", "", "tblhosting on tblproducts.id = tblhosting.packageid");
            $data2 = mysql_fetch_array($result2);
            $prodname = $data2["name"];
            $module = $data2["servertype"];
            $nextDueDate2 = $data2["nextduedate"];
            $susptermexempt = 0;
            if ($groupid) {
                $susptermexempt = $clientGroups[$groupid];
            }
            if (!$susptermexempt) {
                $invoiceItem = \WHMCS\Billing\Invoice\Item::with(["invoice" => function ($query) {
                    $query->where("status", \WHMCS\Billing\Invoice::STATUS_PAYMENT_PENDING);
                }])->where("type", \WHMCS\Billing\Invoice\Item::TYPE_SERVICE)->where("relid", $id)->orderBy("id", "DESC")->first();
                if (!$invoiceItem->invoice) {
                    $serverresult = "No Module";
                    logActivity("Cron Job: Terminating Service - Service ID: " . $serviceid);
                    if ($module) {
                        if ($nextDueDate == $nextDueDate2) {
                            $serverresult = ServerTerminateAccount($serviceid);
                        }
                    }
                    if ($domain) {
                        $domain = " - " . $domain;
                    }
                    $loginfo = sprintf("%s%s - %s %s (Service ID: %s - User ID: %s)", $prodname, $domain, $firstname, $lastname, $serviceid, $userid);
                    if ($serverresult == "success") {
                        $this->addSuccess(["service", $serviceid]);
                    } else {
                        $this->addFailure(["service", $serviceid, $serverresult]);
                        logActivity(sprintf("ERROR: Manual Terminate Required - %s - %s", $serverresult, $loginfo));
                    }
                }
            }
        }
        $addons = \WHMCS\Service\Addon::whereHas("service", function ($query) {
            $query->where("overideautosuspend", "!=", 1);
        })->with("client", "productAddon", "service")->whereIn("status", ["Active", "Suspended"])->whereNotIn("billingcycle", ["Free", "Free Account", "One Time"])->where("nextduedate", "<=", $terminatedate)->where("nextduedate", "!=", "0000-00-00")->get();
        foreach ($addons as $addon) {
            if ($addon->service) {
                $suspendTerminateExempt = 0;
                if ($addon->client->groupId) {
                    $suspendTerminateExempt = $clientGroups[$addon->client->groupId];
                }
                if (!$suspendTerminateExempt) {
                    $invoiceItem = \WHMCS\Billing\Invoice\Item::with(["invoice" => function ($query) {
                        $query->where("status", \WHMCS\Billing\Invoice::STATUS_PAYMENT_PENDING);
                    }])->where("type", \WHMCS\Billing\Invoice\Item::TYPE_SERVICE_ADDON)->where("relid", $id)->orderBy("id", "DESC")->first();
                    if (!$invoiceItem->invoice) {
                        if ($addon->productAddon->module) {
                            $automation = \WHMCS\Service\Automation\AddonAutomation::factory($addon);
                            if ($addon->provisioningType !== \WHMCS\Product\Addon::PROVISIONING_TYPE_FEATURE) {
                                $automationResult = $automation->runAction("TerminateAccount");
                                if ($automationResult) {
                                    $addon->status = \WHMCS\Utility\Status::TERMINATED;
                                    $addon->terminationDate = \WHMCS\Carbon::now();
                                    $addon->save();
                                }
                            } else {
                                $automationResult = $automation->deprovisionAddOnFeature();
                            }
                            if ($automationResult) {
                                $this->addSuccess(["addon", $addon->id]);
                            } else {
                                $this->addFailure(["addon", $addon->id, $automation->getError()]);
                                $logInfo = sprintf("%s - %s %s (Service ID: %d - Addon ID: %d - User ID: %d)", $addon->name ? $addon->name : $addon->productAddon->name, $addon->client->firstName, $addon->client->lastName, $addon->serviceId, $addon->id, $addon->clientId);
                                logActivity(sprintf("ERROR: Manual Terminate Required - %s - %s", $automation->getError(), $logInfo));
                            }
                        } else {
                            $addon->status = "Terminated";
                            $addon->save();
                            run_hook("AddonTerminated", ["id" => $addon->id, "userid" => $addon->clientId, "serviceid" => $addon->serviceId, "addonid" => $addon->addonId]);
                        }
                    }
                }
            }
        }
        $this->output("terminations")->write(count($this->getSuccesses()));
        $this->output("manual")->write(count($this->getFailures()));
        $this->output("action.detail")->write(json_encode($this->getDetail()));
        return true;
    }
}
