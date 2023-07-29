<?php

namespace WHMCS\Cron\Task;

class ProcessEmailQueue extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1636;
    protected $defaultFrequency = 1;
    protected $defaultQueueCount = 25;
    protected $defaultDescription = "Process Queued Emails";
    protected $defaultName = "Process Email Queue";
    protected $systemName = "ProcessEmailQueue";
    protected $outputs = ["sent" => ["defaultValue" => 0, "identifier" => "sent", "name" => "Emails Sent"], "failed" => ["defaultValue" => 0, "identifier" => "failed", "name" => "Failed Emails"]];
    protected $icon = "fas fa-envelope";
    protected $successCountIdentifier = "sent";
    protected $successKeyword = "Emails Sent";

    public function __invoke()
    {
        $sentEmails = $failedEmails = 0;
        $queuedEmails = \WHMCS\Mail\Queue::pending()->limit(\WHMCS\Mail\Queue::getSendingAmount())->get();
        foreach ($queuedEmails as $queuedEmail) {
            try {
                $queuedEmail->send();
                $queuedEmail->refresh();
                $sentEmails++;
            } catch (\WHMCS\Exception\Mail\NoRecipients $e) {
                $queuedEmail->failed = true;
                $queuedEmail->failureReason = $e->getMessage();
                $queuedEmail->pending = false;
            } catch (\WHMCS\Exception\Mail\SendHookAbort $e) {
                $queuedEmail->failed = true;
                $queuedEmail->failureReason = "Email Send Aborted By Hook";
                $queuedEmail->pending = false;
                $failedEmails++;
            } catch (\WHMCS\Exception\Mail\EmailSendingDisabled $e) {
                $queuedEmail->failed = true;
                $queuedEmail->failureReason = "Email Send Aborted By Configuration";
                $queuedEmail->pending = false;
                $failedEmails++;
            } catch (\WHMCS\Exception\Mail\InvalidAddress $e) {
                $queuedEmail->failed = true;
                $queuedEmail->failureReason = "Invalid Address Specified";
                $queuedEmail->pending = false;
                $failedEmails++;
            } catch (\Exception $e) {
                $queuedEmail->refresh();
                $queuedEmail->failed = true;
                $queuedEmail->retryCount++;
                if (3 <= $queuedEmail->retryCount) {
                    $queuedEmail->pending = false;
                }
                $failedEmails++;
            }
            if ($queuedEmail->isDirty()) {
                $queuedEmail->save();
            }
        }
        $this->output("sent")->write($sentEmails);
        $this->output("failed")->write($failedEmails);
        return $this;
    }
}
