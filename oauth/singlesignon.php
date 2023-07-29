<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";
$server = DI::make("oauth2_sso");
$response = $server->handleSingleSignOnRequest($request, $response);
$server->pruneExpiredAccessTokens();
Log::debug("oauth/singlesignon", ["request" => ["headers" => $request->server->getHeaders(), "request" => $request->request->all(), "query" => $request->query->all()], "response" => ["body" => $response->getContent()]]);
$response->send();
