<?php

require_once "init.php";
$rss = new WHMCS\Announcement\Rss();
$request = Laminas\Diactoros\ServerRequestFactory::fromGlobals();
$response = $rss->toXml($request);
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
