<?php

require_once dirname(dirname(__DIR__)) . "/init.php";
error_reporting(32759);
$request = WHMCS\Http\Message\ServerRequest::fromGlobals();
$response = DI::make("Frontend\\Dispatcher")->dispatch($request);
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
exit;
