<?php

namespace WHMCS\Api\Log;

class Handler extends \Monolog\Handler\AbstractProcessingHandler
{
    protected $isHandling = false;

    public function __construct($level = \Monolog\Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->isHandling = $this->isDebugEnabled();
        $this->pushProcessor($this->getProcessor());
    }

    public function write($record)
    {
        Log::create(["action" => $record["message"], "request" => $record["extra"]["request_formatted"], "response" => $record["extra"]["response_formatted"], "response_status" => $record["extra"]["response_status"], "response_headers" => $record["extra"]["response_headers"], "level" => $record["level"]]);
    }

    protected function isDebugEnabled()
    {
        $config = \DI::make("config");
        if (!empty($config["api_enable_logging"]) || \WHMCS\Config\Setting::getValue(\WHMCS\Config\Setting::API_DEBUG_MODE)) {
            return true;
        }
        return false;
    }

    protected function getProcessor($RequestResponseProcessor)
    {
        return new RequestResponseProcessor();
    }

    public function isHandling($record)
    {
        if ($this->isHandling) {
            return parent::isHandling($record);
        }
        return false;
    }
}
