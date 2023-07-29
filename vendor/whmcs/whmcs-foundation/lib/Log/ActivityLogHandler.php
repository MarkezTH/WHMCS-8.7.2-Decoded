<?php

namespace WHMCS\Log;

class ActivityLogHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    protected function write($record)
    {
        if ($record["formatted"]) {
            try {
                $event = ["date" => (string) \WHMCS\Carbon::now()->format("YmdHis"), "description" => $record["formatted"], "user" => "", "userid" => "", "ipaddr" => ""];
                \WHMCS\Database\Capsule::table("tblactivitylog")->insertGetId($event);
            } catch (\Exception $e) {
            }
        }
    }
}
