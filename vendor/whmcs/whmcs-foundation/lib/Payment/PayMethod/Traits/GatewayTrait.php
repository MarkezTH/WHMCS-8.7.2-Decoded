<?php

namespace WHMCS\Payment\PayMethod\Traits;

trait GatewayTrait
{
    public function loadGateway($gatewayName)
    {
        $gateway = new \WHMCS\Module\Gateway();
        if ($gateway->load($gatewayName)) {
            return $gateway;
        }
        return NULL;
    }
}
