<?php

namespace WHMCS\Api\NG\Middleware;

class ApiNgAccessControlHandler implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\AssumingMiddlewareTrait;

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        try {
            $this->assertVisitorIPNotBanned();
            $this->assertIpAllowed(\WHMCS\Utility\Environment\CurrentRequest::getIP());
            return $delegate->process($request);
        } catch (\WHMCS\Exception\Authorization\AccessDenied $e) {
        } catch (\WHMCS\Exception\IPBanned $e) {
        }
        return new \WHMCS\Http\Message\JsonResponse(["message" => $e->getMessage()], \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
    }

    protected function assertIpAllowed($ip)
    {
        if (\WHMCS\Config\Setting::getValue(\WHMCS\Config\Setting::API_NG_API_WHITELIST_APPLY)) {
            $allowedIps = safe_unserialize(\WHMCS\Config\Setting::getValue(\WHMCS\Config\Setting::API_NG_API_WHITELIST));
            $allowedIps = is_array($allowedIps) ? $allowedIps : [];
            $allowedIps = array_filter(array_map(function ($ip) {
                return trim($ip["ip"] ?? NULL);
            }, $allowedIps));
            if (!in_array(trim($ip), $allowedIps, true)) {
                throw new \WHMCS\Exception\Authorization\AccessDenied("The IP address is not whitelisted.");
            }
        }
    }

    protected function assertVisitorIPNotBanned()
    {
        if (\App::isVisitorIPBanned()) {
            throw new \WHMCS\Exception\IPBanned("The IP address is banned.");
        }
    }
}
