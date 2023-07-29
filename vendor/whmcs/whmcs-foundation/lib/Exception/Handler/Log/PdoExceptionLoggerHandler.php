<?php

namespace WHMCS\Exception\Handler\Log;

class PdoExceptionLoggerHandler extends \WHMCS\Log\ActivityLogHandler
{
    public function isHandling($record)
    {
        if (parent::isHandling($record)) {
            return \WHMCS\Utility\ErrorManagement::isAllowedToLogSqlErrors();
        }
        return false;
    }

    protected function write($record)
    {
        if ($record["context"]["exception"] instanceof \PDOException) {
            parent::write($record);
        }
    }

    protected function getDefaultFormatter($FormatterInterface)
    {
        return new \Monolog\Formatter\LineFormatter("PDO Exception: %message%");
    }
}
