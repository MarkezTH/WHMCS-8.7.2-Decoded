<?php

namespace WHMCS\Installer\Update;

class UpdateLogHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    protected function write($record)
    {
        $instanceId = "not defined";
        if (isset($record["context"]["instance_id"])) {
            $instanceId = $record["context"]["instance_id"];
        } else {
            if ($storedId = \WHMCS\Config\Setting::getValue("UpdaterUpdateToken")) {
                $instanceId = $storedId;
            }
        }
        if (!isset($record["extra"])) {
            $record["extra"] = [];
        }
        if (trim($record["formatted"])) {
            $logEntry = new UpdateLog();
            $logEntry->message = $record["formatted"];
            $logEntry->instance_id = $instanceId;
            $logEntry->level = $record["level"];
            $logEntry->extra = json_encode($record["extra"]);
            $logEntry->save();
        }
    }

    protected function getDefaultFormatter($FormatterInterface)
    {
        return new \Monolog\Formatter\LineFormatter("%message%");
    }
}
