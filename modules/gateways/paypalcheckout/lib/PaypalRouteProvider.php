<?php

namespace WHMCS\Module\Gateway\Paypalcheckout;

class PaypalRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function getRoutes()
    {
        return ["/paypal/checkout" => [["name" => $this->getDeferredRoutePathNameAttribute() . "create-order", "method" => ["POST"], "path" => "/order/create", "handle" => ["WHMCS\\Module\\Gateway\\Paypalcheckout\\PaypalController", "createOrder"]], ["name" => $this->getDeferredRoutePathNameAttribute() . "validate-order", "method" => ["POST"], "path" => "/order/validate", "handle" => ["WHMCS\\Module\\Gateway\\Paypalcheckout\\PaypalController", "validateOrder"]], ["name" => $this->getDeferredRoutePathNameAttribute() . "verify-payment", "method" => ["POST"], "path" => "/payment/verify", "handle" => ["WHMCS\\Module\\Gateway\\Paypalcheckout\\PaypalController", "verifyPayment"]], ["name" => $this->getDeferredRoutePathNameAttribute() . "verify-subscription-setup", "method" => ["GET"], "path" => "/subscription/verify/{invoice_id:\\d+}", "handle" => ["WHMCS\\Module\\Gateway\\Paypalcheckout\\PaypalController", "verifySubscriptionSetup"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "paypal-checkout-";
    }
}
