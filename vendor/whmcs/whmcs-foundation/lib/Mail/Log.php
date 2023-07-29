<?php

namespace WHMCS\Mail;

class Log extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblemails";
    protected $columnMap = ["clientId" => "userid", "sentDate" => "date"];
    protected $dates = ["sentDate"];
    protected $fillable = ["userid"];
    protected $commaSeparated = ["to", "cc", "bcc"];
    protected $hidden = ["pending", "message_data", "failure_reason", "retry_count", "campaign_id"];
    protected $casts = ["attachments" => "array"];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("sent", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->where("pending", 0);
        });
    }

    public function scopeOfClient($query, int $clientId)
    {
        $query->where("userid", $clientId);
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }
}
