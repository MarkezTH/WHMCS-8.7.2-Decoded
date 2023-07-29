<?php

namespace WHMCS\Support\Ticket;

class Status extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblticketstatuses";
    public $timestamps = false;
    const STATUS_OPEN = "Open";
    const STATUS_ANSWERED = "Answered";
    const STATUS_CUSTOMER_REPLY = "Customer-Reply";
    const STATUS_ON_HOLD = "On Hold";
    const STATUS_IN_PROGRESS = "In Progress";
    const STATUS_CLOSED = "Closed";

    public static function getAwaitingReply()
    {
        return self::where("showawaiting", "1")->pluck("title");
    }

    public static function getActive()
    {
        return self::where("showactive", "1")->pluck("title");
    }
}
