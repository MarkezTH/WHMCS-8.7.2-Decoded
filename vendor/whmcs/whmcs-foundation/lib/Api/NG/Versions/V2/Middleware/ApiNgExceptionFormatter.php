<?php

namespace WHMCS\Api\NG\Versions\V2\Middleware;

class ApiNgExceptionFormatter implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\AssumingMiddlewareTrait;

    protected function isErrorDisplayAllowed($request)
    {
        return (bool) \App::getApplicationConfig()->display_errors;
    }

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        try {
            $response = $delegate->process($request);
            if ($response->getStatusCode() === \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND && !$response instanceof \WHMCS\Http\Message\JsonResponse) {
                throw new \WHMCS\Exception\Api\NG\ApiNgException("Invalid route definition");
            }
        } catch (\WHMCS\Exception\Api\NG\ApiNgInvalidArgument $e) {
            $response = new \WHMCS\Http\Message\JsonResponse(["message" => $e->getMessage()], \Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = new \WHMCS\Http\Message\JsonResponse(["message" => "Entity not found"], \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND);
        } catch (\WHMCS\Exception\Authorization\AbstractAuthorizationException $e) {
            $response = new \WHMCS\Http\Message\JsonResponse(["message" => "Unauthorized"], \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
        } catch (\WHMCS\Exception\Authentication\AbstractAuthenticationException $e) {
            $response = new \WHMCS\Http\Message\JsonResponse(["message" => "Unauthorized"], \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            if ($this->isErrorDisplayAllowed($request)) {
                $message = $e->getMessage();
            } else {
                $message = "Internal error. Try again later.";
            }
            $response = new \WHMCS\Http\Message\JsonResponse(["message" => $message], \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $response;
    }
}
