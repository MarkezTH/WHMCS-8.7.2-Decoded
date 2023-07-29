<?php

namespace WHMCS\Module\Fraud;

class AbstractRequest
{
    protected $licenseKey = NULL;

    protected function log($action, $request, $response, $processedResponse)
    {
        $namespace = explode("\\", "WHMCS\\Module\\Fraud");
        $moduleName = end($namespace);
        return logModuleCall(strtolower($moduleName), $action, $request, $response, $processedResponse);
    }

    protected function getClient()
    {
        return new \WHMCS\Http\Client\HttpClient();
    }
}
