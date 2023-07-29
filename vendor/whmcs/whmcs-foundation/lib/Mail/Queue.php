<?php

namespace WHMCS\Mail;

class Queue extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblemails";
    protected $columnMap = ["clientId" => "userid", "messageData" => "message_data", "failureReason" => "failure_reason", "retryCount" => "retry_count", "campaignId" => "campaign_id"];
    protected $fillable = ["userid"];
    protected $commaSeparated = ["to", "cc", "bcc"];
    protected $casts = ["attachments" => "array", "message_data" => "array"];
    const DEFAULT_SENDING_AMOUNT = 25;

    public function scopeFailed(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("failed", 1);
    }

    public function scopePending(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("pending", 1);
    }

    public function scopeSent(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("pending", 0)->where("failed", 0);
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public static function add($Queue, $clientId, Template $template = 0, int $campaignId)
    {
        $additionalMergeFields = [];
        if ($template->type == "addon") {
            $template->type = "product";
            $additionalMergeFields["addonemail"] = true;
            $additionalMergeFields["addonid"] = $template->to_ids["addon_id"];
            $additionalMergeFields["addon_id"] = $template->to_ids["addon_id"];
        }
        try {
            $emailer = Emailer::factoryByTemplate($template, $template->to_ids["toId"], $additionalMergeFields);
            $message = $emailer->preview();
        } catch (\Exception $e) {
            $queue = new self();
            $queue->clientId = $clientId;
            $queue->subject = $template->subject;
            $queue->message = "Unavailable";
            $queue->pending = false;
            $queue->failed = true;
            $queue->failureReason = $e->getMessage();
            $queue->messageData = [];
            $queue->campaignId = $campaignId;
            $queue->save();
            logActivity(sprintf("Failed to queue an email: %s", $e->getMessage()));
            return NULL;
        }
        $template->subject = \WHMCS\Input\Sanitize::encode($message->getSubject());
        $template->message = \WHMCS\Input\Sanitize::encode($message->getBodyWithoutCSS());
        $template->copyTo = $message->getRecipientsEmailAddress("cc");
        $template->blindCopyTo = $message->getRecipientsEmailAddress("bcc");
        $queue = new self();
        $queue->clientId = $clientId;
        $queue->subject = $message->getSubject();
        $queue->message = $message->getBody();
        $queue->pending = true;
        $queue->messageData = $template->toArray();
        $queue->campaignId = $campaignId;
        $queue->save();
        return $queue;
    }

    public static function getSendingAmount()
    {
        return self::DEFAULT_SENDING_AMOUNT;
    }

    public function campaign()
    {
        return $this->belongsTo("WHMCS\\Mail\\Campaign", "campaign_id");
    }

    public function send()
    {
        $additionalMergeFields = [];
        $templateData = $this->messageData;
        if (empty($templateData)) {
            $this->failed = true;
            $this->pending = false;
            $this->failureReason = "No message data present to send.";
            $this->save();
            throw new \WHMCS\Exception\Mail\InvalidTemplate("No message data available");
        }
        $template = Template::factoryFromArray($templateData);
        if (!empty($template->to_ids["addon_id"])) {
            $additionalMergeFields["addonemail"] = true;
            $additionalMergeFields["addonid"] = $template->to_ids["addon_id"];
            $additionalMergeFields["addon_id"] = $template->to_ids["addon_id"];
        }
        $emailer = Emailer::factoryByTemplate($template, $template->to_ids["toId"], $additionalMergeFields);
        $emailer->setEmailLogId($this->id);
        $attachments = array_merge($templateData["attachments"] ?? [], $templateData["temporaryAttachments"] ?? []);
        foreach ($attachments as $attachment) {
            $message = $emailer->getMessage();
            if (is_array($attachment)) {
                if (!empty($attachment["filename"])) {
                    $storage = \Storage::emailAttachments();
                    $message->addStringAttachment($attachment["displayname"], $storage->read($attachment["filename"]));
                } else {
                    if (!empty($attachment["data"])) {
                        $message->addStringAttachment($attachment["displayname"], $attachment["data"]);
                    }
                }
            } else {
                if (is_string($attachment) && $attachment !== "") {
                    $storage = \Storage::emailTemplateAttachments();
                    $displayName = substr($attachment, 7);
                    try {
                        $message->addStringAttachment($displayName, $storage->read($attachment));
                    } catch (\League\Flysystem\FileNotFoundException $e) {
                        $error = "Could not access file: " . $attachment;
                        logActivity("Email Sending Failed - " . $error . " (Subject: " . $template->subject . ")", "none");
                        throw new \WHMCS\Exception\Mail\InvalidTemplate("Could not access file: " . $attachment);
                    }
                }
            }
        }
        $emailer->send();
        return $this;
    }
}
