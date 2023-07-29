<?php

namespace WHMCS\Service\Observers;

class ServiceHookObserver
{
    public function deleted($service)
    {
        \HookMgr::run("ServiceDelete", ["userid" => $service->clientId, "clientId" => $service->clientId, "serviceid" => $service->id]);
    }
}
