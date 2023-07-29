<?php

namespace WHMCS\Mail;

class Template extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblemailtemplates";
    protected $guarded = ["id"];
    protected $booleans = ["custom", "disabled", "plaintext"];
    protected $commaSeparated = ["attachments", "copyTo", "blindCopyTo"];
    public $unique = [];
    public $temporaryAttachments = [];

    public function __toString()
    {
        return $this->name;
    }

    public function scopeMaster($query)
    {
        return $query->where("language", "=", "");
    }

    public function scopeDomain($query)
    {
        $query->where("type", "domain");
    }

    public function scopeName(\Illuminate\Database\Eloquent\Builder $query, $name)
    {
        $query->where("name", $name);
    }

    public static function getActiveLanguages()
    {
        return array_unique(self::where("language", "!=", "")->orderBy("language")->pluck("language")->all());
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function (Template $template) {
            $existingLanguages = Template::where("name", "=", $template->name)->pluck("language")->all();
            if (is_null($existingLanguages)) {
                return true;
            }
            if (!in_array($template->language, $existingLanguages)) {
                return true;
            }
            throw new \WHMCS\Exception\Model\UniqueConstraint("Email template not unique.");
        });
    }

    public function toArray()
    {
        return ["name" => $this->name, "type" => $this->type, "subject" => $this->subject, "message" => $this->message, "fromName" => $this->fromName, "fromEmail" => $this->fromEmail, "copyTo" => $this->copyTo, "blindCopyTo" => $this->blindCopyTo, "attachments" => $this->attachments, "temporaryAttachments" => $this->temporaryAttachments, "language" => $this->language, "plaintext" => $this->plaintext, "disabled" => $this->disabled, "custom" => $this->custom, "to_ids" => $this->to_ids];
    }

    public static function factoryFromArray($Template, $templateData)
    {
        $template = new self();
        $template->type = $templateData["type"];
        $template->subject = \WHMCS\Input\Sanitize::decode($templateData["subject"]);
        $template->message = \WHMCS\Input\Sanitize::decode($templateData["message"]);
        $template->fromName = $templateData["fromName"];
        $template->fromEmail = $templateData["fromEmail"];
        if (!empty($templateData["copyTo"])) {
            $template->copyTo = $templateData["copyTo"];
        }
        if (!empty($templateData["blindCopyTo"])) {
            $template->blindCopyTo = $templateData["blindCopyTo"];
        }
        if (!empty($templateData["attachments"])) {
            $attachments = [];
            foreach ($templateData["attachments"] as $attachment) {
                if (is_string($attachment)) {
                    $attachments[] = $attachment;
                }
            }
            $template->attachments = $attachments;
        }
        if (!empty($templateData["temporaryAttachments"]) && is_array($templateData["temporaryAttachments"])) {
            $template->temporaryAttachments = $templateData["temporaryAttachments"];
        }
        $possibleEmptyValues = ["name", "language", "plaintext", "disabled", "custom", "to_ids"];
        foreach ($possibleEmptyValues as $possibleEmptyValue) {
            if (!empty($templateData[$possibleEmptyValue])) {
                $template->{$possibleEmptyValue} = $templateData[$possibleEmptyValue];
            }
        }
        return $template;
    }
}
