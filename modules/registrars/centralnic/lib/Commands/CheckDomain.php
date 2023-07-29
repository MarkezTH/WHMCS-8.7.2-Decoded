<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class CheckDomain extends AbstractCommand
{
    protected $command = "CheckDomain";
    const MAX_TLD_COUNT = 32;

    public function handleResponse($Response, $response)
    {
        if (200 <= $response->getCode() && $response->getCode() <= 300) {
            return $response;
        }
        throw new \Exception($response->getDescription(), $response->getCode());
    }
}
