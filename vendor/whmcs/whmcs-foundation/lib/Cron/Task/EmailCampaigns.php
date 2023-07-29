<?php

namespace WHMCS\Cron\Task;

class EmailCampaigns extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1635;
    protected $defaultFrequency = 5;
    protected $defaultDescription = "Process Scheduled Email Campaigns";
    protected $defaultName = "Process Email Campaigns";
    protected $systemName = "EmailCampaigns";
    protected $outputs = ["queued" => ["defaultValue" => 0, "identifier" => "queued", "name" => "Emails Queued"]];
    protected $icon = "fas fa-envelope";
    protected $successCountIdentifier = "queued";
    protected $successKeyword = "Emails Queued";
    private $queuedMails = 0;

    public function __invoke()
    {
        $campaignsToProcess = \WHMCS\Mail\Campaign::incomplete()->queueCompleted()->get();
        foreach ($campaignsToProcess as $campaign) {
            if (!$campaign->hasPendingEmails()) {
                $campaign->setCompleted();
            }
            if ($campaign->isDirty()) {
                $campaign->save();
            }
        }
        $campaignsToProcess = \WHMCS\Mail\Campaign::due()->incomplete()->get();
        foreach ($campaignsToProcess as $campaign) {
            $configuration = $campaign->configuration;
            if ($campaign->started && $campaign->queue()->count() == $configuration["total_recipients"]) {
                if (is_null($campaign->getRawAttribute("queue_completed_at"))) {
                    $campaign->queueCompletedAt = \WHMCS\Carbon::now();
                    $campaign->save();
                }
            } else {
                if (!$campaign->started) {
                    $campaign->started = true;
                }
                $offset = $campaign->position;
                $campaign->position += \WHMCS\Mail\MassMail\Recipients::getRecipientLimit();
                $campaign->save();
                $messageData = $campaign->messageData;
                try {
                    if (!$messageData) {
                        throw new \Exception("No message data available");
                    }
                    $recipients = \WHMCS\Mail\MassMail\Recipients::factory($configuration, $offset, \WHMCS\Mail\MassMail\Recipients::getRecipientLimit())->getRecipients();
                    foreach ($recipients as $recipient) {
                        $clientId = $recipient->id;
                        $toIds = [];
                        $toIds["toId"] = $recipient->id;
                        if (!$recipient instanceof \WHMCS\User\Client) {
                            $clientId = $recipient->clientId;
                            if ($recipient instanceof \WHMCS\Service\Addon) {
                                $toIds["addon_id"] = $recipient->id;
                                $toIds["toId"] = $recipient->serviceId;
                                if (!$clientId) {
                                    $clientId = $recipient->service->clientId;
                                }
                            }
                        }
                        $messageData["to_ids"] = $toIds;
                        $template = \WHMCS\Mail\Template::factoryFromArray($messageData);
                        if (\WHMCS\Mail\Queue::add($clientId, $template, $campaign->id)) {
                            $this->queuedMails++;
                        }
                    }
                    if (is_array($configuration["selected_ids"]) && 0 < count($configuration["selected_ids"]) || !count($recipients) || count($recipients) < \WHMCS\Mail\MassMail\Recipients::getRecipientLimit()) {
                        if (count($recipients) === 0) {
                            $campaign->position -= \WHMCS\Mail\MassMail\Recipients::getRecipientLimit();
                        } else {
                            if (0 < count($configuration["selected_ids"])) {
                                $campaign->position = count($recipients);
                            }
                        }
                        $campaign->queueCompletedAt = \WHMCS\Carbon::now();
                    }
                } catch (\Exception $e) {
                    if (is_array($configuration["selected_ids"]) && 0 < count($configuration["selected_ids"])) {
                        $campaign->position = 0;
                        $campaign->queue()->delete();
                    } else {
                        $campaign->position = $campaign->queue()->count();
                    }
                    logActivity("Email Campaign Queue Failure: " . $e->getMessage());
                }
                if ($campaign->isDirty()) {
                    $campaign->save();
                }
            }
        }
        $this->output("queued")->write($this->queuedMails);
    }
}
