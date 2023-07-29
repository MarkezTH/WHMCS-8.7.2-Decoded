<?php

namespace WHMCS\Exception\Handler\Log;

class BaseExceptionLoggerHandler extends \WHMCS\Log\ActivityLogHandler
{
    public function isHandling($record)
    {
        if (parent::isHandling($record)) {
            return \WHMCS\Utility\ErrorManagement::isAllowedToLogErrors();
        }
        return false;
    }

    protected function write($record)
    {
        $exception = $record["context"]["exception"];
        if ($exception instanceof \Exception && !$exception instanceof \PDOException && !$exception instanceof \ErrorException) {
            parent::write($record);
        }
    }

    protected function getDefaultFormatter($FormatterInterface)
    {
        return new \Monolog\Formatter\LineFormatter("Exception: %message%");
    }
}
