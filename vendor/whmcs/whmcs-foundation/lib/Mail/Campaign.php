<?php

namespace WHMCS\Mail;

class Campaign extends \WHMCS\Model\AbstractModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $table = "tblcampaigns";
    protected $columnMap = ["adminId" => "admin_id", "messageData" => "message_data", "sendingStartAt" => "sending_start_at", "queueCompletedAt" => "queue_completed_at", "completedAt" => "completed_at"];
    protected $casts = ["configuration" => "array", "message_data" => "array"];
    protected $dates = ["queueCompletedAt", "sendingStartAt", "completedAt", "deletedAt"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->increments("id");
                $table->unsignedInteger("admin_id")->default(0);
                $table->string("name", 250)->default("");
                $table->text("configuration")->nullable();
                $table->mediumText("message_data")->nullable();
                $table->dateTime("sending_start_at")->nullable();
                $table->boolean("draft")->default(0);
                $table->boolean("started")->default(0);
                $table->boolean("paused")->default(0);
                $table->unsignedInteger("position")->default(0);
                $table->boolean("completed")->default(0);
                $table->timestamp("completed_at")->nullable();
                $table->timestamp("queue_completed_at")->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index("started");
                $table->index("paused");
                $table->index("completed");
            });
        }
    }

    public function queue()
    {
        return $this->hasMany("WHMCS\\Mail\\Queue");
    }

    public function admin()
    {
        return $this->belongsTo("WHMCS\\User\\Admin");
    }

    public function scopeDue(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where(function (\Illuminate\Database\Eloquent\Builder $query2) {
            $query2->where("sending_start_at", "<=", \WHMCS\Carbon::now()->toDateTimeString())->orWhereNull("sending_start_at");
        })->whereNull("queue_completed_at");
    }

    public function scopeComplete(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("completed", 1);
    }

    public function scopeIncomplete(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->where("draft", 0)->where("paused", 0)->where("completed", 0);
    }

    public function scopeQueueCompleted(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query->whereNotNull("queue_completed_at");
    }

    public function hasPendingEmails()
    {
        return (bool) (0 < $this->queue()->where("pending", 1)->count());
    }

    public function setCompleted()
    {
        $messageData = $this->messageData;
        $this->completedAt = \WHMCS\Carbon::now();
        $this->completed = true;
        if (0 < count($messageData["temporaryAttachments"])) {
            foreach ($messageData["temporaryAttachments"] as $parts) {
                try {
                    \Storage::emailAttachments()->deleteAllowNotPresent($parts["filename"]);
                } catch (\Exception $e) {
                    logActivity("Could not delete file: " . htmlentities($e->getMessage()));
                }
            }
        }
        $this->save();
    }
}
