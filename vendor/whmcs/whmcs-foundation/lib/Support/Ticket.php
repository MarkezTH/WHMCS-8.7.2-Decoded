<?php

namespace WHMCS\Support;

class Ticket extends \WHMCS\Model\AbstractModel
{
    use Traits\Message;
    use Traits\Requestor;
    protected $table = "tbltickets";
    protected $columnMap = ["clientId" => "userid", "contactId" => "contactid", "requestorId" => "requestor_id", "ticketNumber" => "tid", "accessKey" => "c", "departmentId" => "did", "subject" => "title", "flaggedAdminId" => "flag", "replyingAdminId" => "replyingadmin", "adminRead" => "adminunread", "priority" => "urgency", "createdByAdminUser" => "admin", "mergedWithTicketId" => "merged_ticket_id", "attachmentsRemoved" => "attachments_removed"];
    protected $commaSeparated = ["adminunread"];
    protected $dates = ["date", "lastreply", "replyingtime"];
    protected $booleans = ["attachmentsRemoved"];
    protected $hidden = ["flag", "adminunread", "clientunread", "replyingadmin", "replyingtime", "editor"];
    const CREATED_AT = "date";
    const PRIORITY_LOW = "low";
    const PRIORITY_MEDIUM = "medium";
    const PRIORITY_HIGH = "high";
    const SUBJECT_IDENTIFIER_FORMAT = "[Ticket ID: %s]";

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Support\\Ticket\\Observers\\TicketNotificationTicketObserver");
        static::addGlobalScope("ordered", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tbltickets.lastreply");
        });
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function contact()
    {
        return $this->belongsTo("WHMCS\\User\\Client\\Contact", "contactid");
    }

    public function department()
    {
        return $this->belongsTo("WHMCS\\Support\\Department", "did");
    }

    public function flaggedAdmin()
    {
        return $this->belongsTo("WHMCS\\User\\Admin", "flag");
    }

    public function replies()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket\\Reply", "tid");
    }

    public function notes()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket\\Note", "ticketid");
    }

    public function mergedTicket()
    {
        return $this->hasOne("WHMCS\\Support\\Ticket", "merged_ticket_id");
    }

    public function replyingAdmin()
    {
        return $this->belongsTo("WHMCS\\User\\Admin", "replyingadmin");
    }

    public function scopeUserId(\Illuminate\Database\Eloquent\Builder $query, $userId)
    {
        return $query->where("userid", $userId);
    }

    public function scopeNotMerged(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("merged_ticket_id", 0);
    }

    public function scopeAwaitingReply(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereIn("status", Ticket\Status::getAwaitingReply());
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereIn("status", Ticket\Status::getActive());
    }

    public function getOwnerName()
    {
        if ($this->client) {
            $label = $this->client->formatter()->getLabel();
        } else {
            $label = $this->name;
        }
        if (0 < strlen($label)) {
            return $label;
        }
        return "Undefined";
    }

    public function getOwnerLabel()
    {
        if ($this->client) {
            return $this->client->formatter()->markup();
        }
        return $this->getOwnerName();
    }

    public function getLink()
    {
        return \App::get_admin_folder_name() . "/supporttickets.php?action=view&id=" . $this->id;
    }

    public static function getPriorities()
    {
        return [ucfirst(self::PRIORITY_LOW) => self::PRIORITY_LOW, ucfirst(self::PRIORITY_MEDIUM) => self::PRIORITY_MEDIUM, ucfirst(self::PRIORITY_HIGH) => self::PRIORITY_HIGH];
    }

    public function getDepartmentName()
    {
        $department = Department::find($this->departmentId);
        return $department ? $department->name : "Missing department";
    }

    public function isMergedTicket()
    {
        return 0 < $this->merged_ticket_id;
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

    public function mergeOtherTicketsInToThis($ticketIds)
    {
        if (!function_exists("addTicketLog")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
        }
        $saveRequired = false;
        addTicketLog($this->id, "Merged Tickets " . implode(",", $ticketIds));
        getUsersLang($this->userId);
        $merge = \Lang::trans("ticketmerge");
        if (!$merge || $merge == "" || $merge == "ticketmerge") {
            $merge = "MERGED";
        }
        if (strpos($this->title, " [" . $merge . "]") === false) {
            $this->title = $this->title . " [" . $merge . "]";
            $saveRequired = true;
        }
        $ticketStatus = $this->status;
        $ticketLastReply = $this->lastReply;
        $ccToMerge = [];
        foreach ($ticketIds as $id) {
            if ($id != $this->id) {
                try {
                    $mergingTicketData = Ticket::findOrFail($id);
                    \WHMCS\Database\Capsule::table("tblticketlog")->where("tid", "=", $id)->update(["tid" => $this->id]);
                    \WHMCS\Database\Capsule::table("tblticketnotes")->where("ticketid", "=", $id)->update(["ticketid" => $this->id]);
                    $mergingTicketData->replies()->update(["tid" => $this->id]);
                    $newReply = new Ticket\Reply();
                    $newReply->tid = $this->id;
                    $newReply->clientId = $this->userId;
                    $newReply->name = $mergingTicketData->name;
                    $newReply->email = $mergingTicketData->email;
                    $newReply->date = $mergingTicketData->date;
                    $newReply->message = $mergingTicketData->message;
                    $newReply->admin = $mergingTicketData->admin;
                    $newReply->attachment = $mergingTicketData->attachment;
                    $newReply->editor = $mergingTicketData->editor;
                    $newReply->save();
                    if ($ticketLastReply < $mergingTicketData->lastReply) {
                        $ticketLastReply = $mergingTicketData->lastReply;
                        $ticketStatus = $mergingTicketData->status;
                    }
                    $mergingTicketData->mergedTicketId = $this->id;
                    $mergingTicketData->status = "Closed";
                    $mergingTicketData->message = "";
                    $mergingTicketData->admin = "";
                    $mergingTicketData->attachment = "";
                    $mergingTicketData->email = "";
                    $mergingTicketData->flaggedAdminId = 0;
                    $mergingTicketData->save();
                    $mergingTicketData->mergedTicket()->update(["merged_ticket_id" => $this->id]);
                    if ($mergingTicketData->cc) {
                        $ccRecipients = explode(",", $mergingTicketData->cc);
                        $ccToMerge = array_merge($ccToMerge, $ccRecipients);
                    }
                    addTicketLog($mergingTicketData, "Ticket ID: " . $mergingTicketData->id . " Merged with Ticket ID: " . $this->id);
                } catch (\Exception $e) {
                }
            }
        }
        if (!empty($ccToMerge)) {
            $originalCC = explode(",", $this->cc);
            $this->cc = implode(",", array_unique(array_filter(array_merge($originalCC, $ccToMerge))));
            $saveRequired = true;
        }
        if ($this->lastReply < $ticketLastReply) {
            $this->lastReply = $ticketLastReply;
            $this->status = $ticketStatus;
            $saveRequired = true;
        }
        if ($saveRequired) {
            $this->save();
        }
        run_hook("TicketMerge", ["mergedTicketIds" => array_merge([$this->id], $ticketIds), "masterTicketId" => $this->id]);
    }

    public function toArray()
    {
        $attachments = [];
        foreach ($this->getAttachmentsForDisplay() as $key => $filename) {
            $attachments[] = ["filename" => $filename, "index" => $key];
        }
        return ["id" => $this->id, "ticketid" => $this->id, "tid" => $this->ticketNumber, "c" => $this->accessKey, "deptid" => $this->departmentId, "deptname" => $this->getDepartmentName(), "userid" => $this->userid, "contactid" => $this->contactid, "name" => $this->getOwnerName(), "owner_name" => $this->getOwnerName(), "email" => $this->getRequestorEmail(), "requestor_name" => $this->getRequestorName(), "requestor_email" => $this->getRequestorEmail(), "requestor_type" => $this->getRequestorType(), "cc" => $this->cc, "date" => $this->date->toDateTimeString(), "subject" => $this->subject, "status" => $this->status, "priority" => $this->priority, "admin" => $this->admin, "attachment" => $this->attachment, "attachments" => $attachments, "attachments_removed" => $this->attachmentsRemoved, "lastreply" => $this->lastreply->toDateTimeString(), "flag" => $this->flag, "service" => $this->service];
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes["userid"] = $value ?: "";
    }

    public function setAdminAttribute($value)
    {
        $this->attributes["admin"] = $value ?: "";
    }

    public function setContactIdAttribute($value)
    {
        $this->attributes["contactid"] = $value ?: "";
    }

    public function setNameAttribute($value)
    {
        $this->attributes["name"] = $value ?: "";
    }

    public function setEmailAttribute($value)
    {
        $this->attributes["email"] = $value ?: "";
    }

    public static function extractIdentifier($subject)
    {
        list($leading, $trailing) = explode("%s", static::SUBJECT_IDENTIFIER_FORMAT);
        $leadingBegin = strpos($subject, $leading);
        if ($leadingBegin === false) {
            return "";
        }
        $identifier = substr($subject, $leadingBegin + strlen($leading));
        $trailingBegin = strpos($identifier, $trailing);
        if ($trailingBegin === false) {
            return "";
        }
        return substr($identifier, 0, $trailingBegin);
    }

    public function notifications($HasMany)
    {
        return $this->hasMany("WHMCS\\Support\\Ticket\\TicketImportNotification", "ticket_id", "id");
    }

    public function notificationLogs($HasManyThrough)
    {
        return $this->hasManyThrough("WHMCS\\Log\\TicketImport", "WHMCS\\Support\\Ticket\\TicketImportNotification", "ticket_id", "id", "id", "ticketmaillog_id");
    }
}
