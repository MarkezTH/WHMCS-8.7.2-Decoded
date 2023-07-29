<?php

namespace WHMCS\Support\Ticket;

class Reply extends \WHMCS\Model\AbstractModel
{
    use \WHMCS\Support\Traits\Message;
    use \WHMCS\Support\Traits\Requestor;
    protected $table = "tblticketreplies";
    protected $columnMap = ["ticketId" => "tid", "clientId" => "userid", "contactId" => "contactid", "requestorId" => "requestor_id", "attachmentsRemoved" => "attachments_removed"];
    protected $dates = ["date"];
    protected $hidden = ["editor"];
    protected $booleans = ["attachmentsRemoved"];
    public $timestamps = false;
    const CREATED_AT = "date";

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("ordered", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblticketreplies.id");
        });
    }

    public function ticket()
    {
        return $this->belongsTo("WHMCS\\Support\\Ticket", "tid");
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

    public function setContactIdAttribute($value)
    {
        $this->attributes["contactid"] = $value ?: "";
    }

    public function setTidAttribute($value)
    {
        $this->attributes["tid"] = $value ?: "";
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes["userid"] = $value ?: "";
    }

    public function setNameAttribute($value)
    {
        $this->attributes["name"] = $value ?: "";
    }

    public function setEmailAttribute($value)
    {
        $this->attributes["email"] = $value ?: "";
    }

    public function setAdminAttribute($value)
    {
        $this->attributes["admin"] = $value ?: "";
    }

    public function setAttachmentAttribute($value)
    {
        $this->attributes["attachment"] = $value ?: "";
    }

    public function setRatingAttribute($value)
    {
        $this->attributes["rating"] = $value ?: "";
    }
}
