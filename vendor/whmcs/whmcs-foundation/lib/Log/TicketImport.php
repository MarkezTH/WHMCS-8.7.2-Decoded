<?php

namespace WHMCS\Log;

class TicketImport extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblticketmaillog";
    protected $casts = ["date" => "datetime"];
    protected $appends = ["safeMessage", "imported"];
    public $timestamps = false;
    public $importedStatuses = NULL;
    public $rejectedStatuses = NULL;
    const SETTING_ALLOW_INSECURE_IMPORT = "SupportAllowInsecureImport";
    const STATUS_LANG_KEY_PREFIX = "mailImport.status.";
    const STATUS_SUCCESSFUL_TICKET_IMPORT = "successfulNew";
    const STATUS_SUCCESSFUL_REPLY_IMPORT = "successfulReply";
    const STATUS_FAILED_TICKET_IMPORT = "failedTicketImport";
    const STATUS_FAILED_BLOCKED_EMAIL_LOOP = "blockedEmailLoop";
    const STATUS_FAILED_DEPT_NOT_FOUND = "deptNotFound";
    const STATUS_FAILED_TICKET_NOT_FOUND = "ticketNotFound";
    const STATUS_FAILED_NOT_RECOGNISED = "unregisteredEmailAddress";
    const STATUS_FAILED_NOT_AUTHORIZED = "senderNotAuthorized";
    const STATUS_FAILED_RATE_LIMITED = "rateLimited";
    const STATUS_FAILED_UNREGISTERED_USER = "unregisteredUser";
    const STATUS_FAILED_AUTO_RESPONDER = "autoResponder";
    const STATUS_FAILED_REOPEN_VIA_EMAIL = "reopenViaEmail";
    const STATUS_FAILED_MISSING_SENDER_EMAIL = "missingSender";
    const STATUS_FAILED_ABORTED_BY_HOOK = "abortedByHook";
    const STATUS_FAILED_SPAM_PHRASE = "spamPhrase";
    const STATUS_FAILED_SPAM_SUBJECT = "spamSubject";
    const STATUS_FAILED_SPAM_SENDER = "spamSender";
    const STATUS_FAILED_ITERATION_LIMIT = "iterationLimit";
    const STATUS_REJECTED_BY_OPERATOR = "rejectedByOperator";
    const STATUS_COLOUR_FILTER = [];

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Log\\Observer\\TicketNotificationImportObserver");
    }

    public static function factory($status)
    {
        $model = new self();
        $model->status = $status;
        $model->date = \WHMCS\Carbon::now();
        return $model;
    }

    public function user()
    {
        return $this->hasOne("WHMCS\\User\\User", "email", "email");
    }

    public function getStatusLabel()
    {
        $status = $this->getRawStatus();
        $langString = $this->generateLangString($status);
        $status = \WHMCS\Input\Sanitize::makeSafeForOutput($langString);
        if (array_key_exists($this->getRawStatus(), self::STATUS_COLOUR_FILTER)) {
            $color = self::STATUS_COLOUR_FILTER[$this->getRawStatus()];
            $status = sprintf("<span style=\"color:%s\">%s</span>", $color, $status);
        }
        return $status;
    }

    public function isImported()
    {
        return $this->imported;
    }

    public function isRejected()
    {
        return in_array($this->getRawStatus(), $this->rejectedStatuses);
    }

    public function isPending()
    {
        return !$this->isImported() && !$this->isRejected();
    }

    public function getTicket($Ticket)
    {
        return \WHMCS\Support\Ticket::whereTid(\WHMCS\Support\Ticket::extractIdentifier($this->subject))->first();
    }

    public function notification($HasOne)
    {
        return $this->hasOne("WHMCS\\Support\\Ticket\\TicketImportNotification", "ticketmaillog_id", "id");
    }

    public function getStatusAttribute($value)
    {
        $langString = self::STATUS_LANG_KEY_PREFIX . $value;
        $status = \AdminLang::trans($langString);
        if ($status === $langString) {
            return $value;
        }
        return $status;
    }

    public function getRawStatus()
    {
        return $this->getRawAttribute("status");
    }

    public function getSafeMessageAttribute()
    {
        return nl2br(\WHMCS\Input\Sanitize::makeSafeForOutput($this->message));
    }

    public function scopeForTicketId(\Illuminate\Database\Eloquent\Builder $query, $tid)
    {
        $subjectTerm = sprintf(\WHMCS\Support\Ticket::SUBJECT_IDENTIFIER_FORMAT, $tid);
        return $query->where("subject", "LIKE", "%" . $subjectTerm . "%");
    }

    public function scopeRequiresReview(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereNotIn("status", $this->getImportedStatuses(true));
    }

    public function getImportedAttribute()
    {
        return in_array($this->getRawStatus(), $this->importedStatuses);
    }

    public function getImportedStatuses($includeRejected = false)
    {
        return $includeRejected ? array_merge($this->importedStatuses, [self::STATUS_REJECTED_BY_OPERATOR]) : $this->importedStatuses;
    }

    private function generateLangString($langKey)
    {
        switch ($langKey) {
            case self::STATUS_FAILED_RATE_LIMITED:
                $number = \WHMCS\Config\Setting::getValue("TicketEmailLimit") ?: 10;
                $langString = \AdminLang::trans(self::STATUS_LANG_KEY_PREFIX . $langKey, [":number" => $number]);
                break;
            default:
                $langString = \AdminLang::trans(self::STATUS_LANG_KEY_PREFIX . $langKey);
                if ($langString === self::STATUS_LANG_KEY_PREFIX . $langKey) {
                    $langString = $langKey;
                }
                return $langString;
        }
    }
}
