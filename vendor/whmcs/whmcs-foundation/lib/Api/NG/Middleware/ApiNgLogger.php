<?php

namespace WHMCS\Api\NG\Middleware;

class ApiNgLogger implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\AssumingMiddlewareTrait;
    const ACTIVITY_LOG_INTERVAL = 300;
    const API_NG_LOG_ERROR = "ApiNgLogError";

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        $response = $delegate->process($request);
        try {
            $logger = \DI::make("ApiNgLog");
            $logger->info($request->getRequestTarget(), ["request" => $request, "response" => $response]);
        } catch (\Exception $e) {
            if ($this->isTimeToLog()) {
                logActivity("API NG Log Error: " . $e->getMessage());
            }
        }
        return $response;
    }

    protected function isTimeToLog()
    {
        $transientData = \WHMCS\TransientData::getInstance();
        if (!$transientData->retrieve(self::API_NG_LOG_ERROR)) {
            $transientData->store(self::API_NG_LOG_ERROR, 1, self::ACTIVITY_LOG_INTERVAL);
            return true;
        }
        return false;
    }
}
