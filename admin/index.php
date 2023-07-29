<?php

define("ADMINAREA", true);
require_once dirname(__DIR__) . "/init.php";
$request = WHMCS\Http\Message\ServerRequest::fromGlobals();
$response = DI::make("Frontend\\Dispatcher")->dispatch($request);
$statusCode = $response->getStatusCode();
$statusFamily = substr($statusCode, 0, 1);
if (!in_array($statusFamily, [2, 3]) && !($response instanceof WHMCS\Http\Message\JsonResponse || $response instanceof Laminas\Diactoros\Response\JsonResponse || $response instanceof WHMCS\Admin\ApplicationSupport\View\Html\Smarty\ErrorPage)) {
    $viewFactory = new WHMCS\Admin\ApplicationSupport\Http\Message\ResponseFactory();
    $response = $viewFactory->genericError($request, $statusCode);
}
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
exit;
