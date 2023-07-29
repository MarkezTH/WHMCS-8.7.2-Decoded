<?php

namespace WHMCS\ClientArea\User;

class InviteRouteProvider implements \WHMCS\Route\Contracts\ProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    public function getRoutes()
    {
        $userRoutes = ["/invite" => ["attributes" => ["authorization" => function () {
            return new \WHMCS\ClientArea\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["GET"], "name" => "invite-redeem", "path" => "/{token:[0-9a-z]{64}}", "handle" => ["WHMCS\\ClientArea\\User\\InviteController", "redeem"]], ["method" => ["POST"], "name" => "invite-validate", "path" => "/{token:[0-9a-z]{64}}", "handle" => ["WHMCS\\ClientArea\\User\\InviteController", "validate"]]]];
        return $userRoutes;
    }

    public function registerRoutes(\FastRoute\RouteCollector $routeCollector)
    {
        $this->addRouteGroups($routeCollector, $this->getRoutes());
    }
}
