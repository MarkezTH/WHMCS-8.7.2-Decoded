<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";
$server = DI::make("oauth2_server");
$server->setConfig("issuer", WHMCS\ApplicationLink\Server\Server::getIssuer());
$server->handleUserInfoRequest($request, $response);
Log::debug("oauth/userinfo", ["request" => ["headers" => $request->server->getHeaders(), "request" => $request->request->all(), "query" => $request->query->all()], "response" => ["body" => $response->getContent()]]);
$response->send();
