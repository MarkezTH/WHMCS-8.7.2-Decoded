<?php

namespace WHMCS\Cron\Task;

class DomainRenewalNotices extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1560;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "Processing Domain Renewal Notices";
    protected $defaultName = "Domain Renewal Notices";
    protected $systemName = "DomainRenewalNotices";
    protected $outputs = ["sent" => ["defaultValue" => 0, "identifier" => "sent", "name" => "Renewal Notices"], "action.detail" => ["defaultValue" => "", "identifier" => "action.detail", "name" => "Action Detail"]];
    protected $icon = "fas fa-globe";
    protected $successCountIdentifier = "sent";
    protected $successKeyword = "Sent";
    protected $hasDetail = true;

    public function __invoke()
    {
        $renewalTypes = ["first", "second", "third", "fourth", "fifth"];
        $this->setDetails(["first" => [], "second" => [], "third" => [], "fourth" => [], "fifth" => [], "failed" => []]);
        if (!function_exists("RegGetRegistrantContactEmailAddress")) {
            include_once ROOTDIR . "/includes/registrarfunctions.php";
        }
        $renewalsNoticesCount = 0;
        $renewals = explode(",", \WHMCS\Config\Setting::getValue("DomainRenewalNotices"));
        $freeDomainReminders = (bool) (int) \WHMCS\Config\Setting::getValue("FreeDomainReminders");
        foreach ($renewals as $count => $renewal) {
            if ((int) $renewal != 0) {
                $renewalDate = \WHMCS\Carbon::now()->addDays($renewal);
                if ($renewal < -1) {
                    $status = [\WHMCS\Utility\Status::EXPIRED, \WHMCS\Utility\Status::GRACE, \WHMCS\Utility\Status::REDEMPTION];
                    $renewalEmailTemplate = "Expired Domain Notice";
                } else {
                    if ($renewal == -1) {
                        $status = [\WHMCS\Utility\Status::ACTIVE];
                        $renewalEmailTemplate = "Expired Domain Notice";
                    } else {
                        $status = [\WHMCS\Utility\Status::ACTIVE];
                        $renewalEmailTemplate = "Upcoming Domain Renewal Notice";
                    }
                }
                $domains = \WHMCS\Domain\Domain::with("client")->whereIn("status", $status)->where("nextduedate", $renewalDate->toDateString())->where("reminders", "not like", "%|" . (int) $renewal . "|%");
                if (!$freeDomainReminders) {
                    $domains->notFree();
                }
                foreach ($domains->get() as $domain) {
                    $emailToSend = $renewalEmailTemplate;
                    $params = [];
                    $params["domainid"] = $domain->id;
                    $domainParts = explode(".", $domain->domain);
                    list($params["sld"], $params["tld"]) = $domainParts;
                    $params["registrar"] = $domain->registrarModuleName;
                    $extra = RegGetRegistrantContactEmailAddress($params);
                    $extra["autoRenewalDisabled"] = $domain->hasAutoInvoiceOnNextDueDisabled;
                    $extra["freeDomainAutoRenewRequiresProduct"] = \WHMCS\Config\Setting::getValue("FreeDomainAutoRenewRequiresProduct");
                    $extra["freeDomainWithService"] = false;
                    if (valueIsZero($domain->recurringAmount) && -1 < $renewal) {
                        $emailToSend = "Upcoming Free Domain Renewal Notice";
                        $serviceCount = \WHMCS\Service\Service::where("domain", $domain->domain)->where("userid", $domain->clientId)->count();
                        $extra["freeDomainWithService"] = 0 < $serviceCount;
                    }
                    $client = new \WHMCS\Client($domain->client);
                    $details = $client->getDetails();
                    $recipients = [];
                    $recipients[] = $details["email"];
                    if (isset($extra["registrantEmail"])) {
                        $recipients[] = $extra["registrantEmail"];
                    }
                    $emailSent = sendMessage($emailToSend, $domain->id, $extra);
                    if ($emailSent === true) {
                        $reminders = $domain->reminders;
                        $reminders[] = $renewal;
                        $domain->reminders = $reminders;
                        $domain->save();
                        insert_query("tbldomainreminders", ["domain_id" => $domain->id, "date" => date("Y-m-d"), "recipients" => implode(",", $recipients), "type" => $count + 1, "days_before_expiry" => $renewal]);
                        $this->addCustom($renewalTypes[$count], ["domain", $domain->id, ""]);
                    } else {
                        if (is_string($emailSent)) {
                            $this->addCustom("failed", ["domain", $domain->id, $emailSent]);
                        }
                    }
                    $renewalsNoticesCount++;
                }
            }
        }
        $this->output("sent")->write($renewalsNoticesCount);
        $this->output("action.detail")->write(json_encode($this->getDetail()));
        return $this;
    }
}
