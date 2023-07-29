<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
try {
    $httpRequest = OAuth2\HttpFoundationBridge\Request::createFromGlobals();
    $client = NULL;
    $user = NULL;
    $clientId = (int) $httpRequest->get("client_id", 0);
    if ($clientId) {
        $client = WHMCS\User\Client::find($clientId);
    }
    $httpRequest->request->remove("client_id");
    $httpRequest->query->remove("client_id");
    $userId = (int) $httpRequest->get("user_id", 0);
    if ($userId) {
        $user = WHMCS\User\User::find($userId);
        if (!$user) {
            throw new WHMCS\Exception\Api\InvalidArgument("Invalid user_id");
        }
        if ($client && !$user->hasAccessToClient($client)) {
            throw new WHMCS\Exception\Api\InvalidArgument("Invalid user_id for client");
        }
    } else {
        if ($client) {
            $user = $client->owner();
            if (!$user) {
                throw new WHMCS\Exception\Api\InvalidArgument("No owner found for client");
            }
        }
    }
    if (!$client && !$user) {
        throw new WHMCS\Exception\Api\InvalidArgument("A valid client_id or user_id is required");
    }
    $httpRequest->request->add(["module" => "ClientAccessSsoToken", "module_type" => "api"]);
    $httpRequest->headers->remove("PHP_AUTH_USER");
    $httpRequest->headers->remove("PHP_AUTH_PW");
    $clientOtpServer = DI::make("oauth2_sso", ["request" => $httpRequest]);
    $clientOtpServer->setUserClient($user, $client);
    $httpResponse = new OAuth2\HttpFoundationBridge\Response();
    $httpResponse->prepare($httpRequest);
    $httpResponse = $clientOtpServer->handleTokenRequest($httpRequest, $httpResponse);
    if (!$httpResponse->isOk()) {
        $msg = "";
        if ($httpResponse instanceof Symfony\Component\HttpFoundation\JsonResponse) {
            $details = json_decode($httpResponse->getContent(), true);
            if (!empty($details["error"])) {
                if ($details["error"] == "invalid_scope") {
                    $msg = "Invalid destination";
                } else {
                    $error = $details["error"];
                    if (!empty($details["error_description"])) {
                        $error .= ". " . $details["error_description"];
                    }
                    $msg = "Token could not be provisioned: " . $error;
                }
            }
        }
        if (!$msg) {
            $msg = "Token could not be provisioned";
        }
        throw new WHMCS\Exception($msg);
    }
    $data = json_decode($httpResponse->getContent(), true);
    if (!$data || !is_array($data)) {
        throw new WHMCS\Exception("Unexpected internal structure");
    }
    $apiresults = ["result" => "success", "access_token" => $data["access_token"], "redirect_url" => $data["redirect_url"]];
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => $e->getMessage()];
}
