<?php

namespace WHMCS\Api\NG\Log;

class ApiNgLogHandler extends \WHMCS\Api\Log\Handler
{
    public function __construct($level = \Monolog\Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    public function write($record)
    {
        \WHMCS\Api\Log\Log::create(["endpoint" => $record["message"], "method" => $record["method"], "request" => $record["extra"]["request_formatted"], "request_headers" => $record["extra"]["request_headers"], "response" => $record["extra"]["response_formatted"], "response_headers" => $record["extra"]["response_headers"], "response_status" => $record["extra"]["response_status"], "level" => $record["level"]]);
    }

    protected function getProcessor($RequestResponseProcessor)
    {
        return new ApiNgRequestResponseProcessor();
    }
}
