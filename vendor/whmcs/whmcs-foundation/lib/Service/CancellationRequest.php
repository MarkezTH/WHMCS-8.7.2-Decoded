<?php

namespace WHMCS\Service;

class CancellationRequest extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblcancelrequests";
    protected $columnMap = ["serviceId" => "relid", "whenToCancel" => "type"];
    protected $dates = ["date"];

    public function service()
    {
        return $this->belongsTo("WHMCS\\Service\\Service", "relid");
    }
}
