<?php

namespace WHMCS\Service\Observers;

class SslOrderServiceObserver
{
    public function deleting(\WHMCS\Service\Service $service)
    {
        $service->ssl()->delete();
    }
}
