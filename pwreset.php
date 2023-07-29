<?php

require_once "init.php";
require_once ROOTDIR . "/includes" . DIRECTORY_SEPARATOR . "clientareafunctions.php";
$controller = new WHMCS\ClientArea\PasswordResetController();
$request = WHMCS\Http\Message\ServerRequest::fromGlobals();
$response = NULL;
if ($_SERVER["REQUEST_METHOD"] === "POST" && $request->has("email")) {
    $response = $controller->validateEmail($request);
}
if (!$response) {
    $response = $controller->emailPrompt($request);
}
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
