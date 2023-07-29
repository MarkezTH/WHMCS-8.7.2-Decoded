<?php

namespace WHMCS\Support\Ticket;

class Note extends \WHMCS\Model\AbstractModel
{
    use \WHMCS\Support\Traits\Requestor;
    protected $table = "tblticketnotes";
    protected $columnMap = ["ticketId" => "tid", "attachmentsRemoved" => "attachments_removed"];
    protected $dates = ["date"];
    protected $hidden = ["editor"];
    protected $booleans = ["attachmentsRemoved"];
    public $timestamps = false;
    const CREATED_AT = "date";

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("ordered", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblticketnotes.id");
        });
    }

    public function ticket()
    {
        return $this->belongsTo("WHMCS\\Support\\Ticket", "tid");
    }

    public function getSafeMessage()
    {
        return strip_tags($this->message);
    }

    public function getAttachmentsForDisplay()
    {
        $attachments = [];
        if ($this->attachment) {
            $attachment = explode("|", $this->attachment);
            foreach ($attachment as $filename) {
                $filename = substr($filename, 7);
                $attachments[] = $filename;
            }
        }
        return $attachments;
    }

    public function getRequestorName()
    {
        return $this->admin;
    }

    public function getRequestorEmail()
    {
        return "";
    }

    public function getRequestorType()
    {
        return "Operator";
    }
}
