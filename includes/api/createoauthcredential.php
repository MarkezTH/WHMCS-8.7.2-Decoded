<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$name = $whmcs->getFromRequest("name");
$description = $whmcs->getFromRequest("description");
$logoUri = $whmcs->getFromRequest("logoUri");
$redirectUri = $whmcs->getFromRequest("redirectUri");
$scope = $whmcs->getFromRequest("scope");
$grantType = $whmcs->getFromRequest("grantType");
$serviceId = (int) $whmcs->getFromRequest("serviceId");
$serviceObj = WHMCS\Service\Service::find($serviceId);
$clientObj = $serviceObj->client ?? NULL;
$validGrantTypes = ["authorization_code", "single_sign_on"];
if (!trim($grantType)) {
    $apiresults = ["result" => "error", "message" => "A valid grant type is required."];
} else {
    if (!in_array($grantType, $validGrantTypes)) {
        $apiresults = ["result" => "error", "message" => "The requested grant type \"" . $grantType . "\" is invalid."];
    } else {
        if ($grantType == "authorization_code" && !trim($name)) {
            $apiresults = ["result" => "error", "message" => "A name for the Client Credential is required."];
        } else {
            if ($grantType == "single_sign_on") {
                if (!$serviceId) {
                    $apiresults = ["result" => "error", "message" => "A service ID is required for the single sign-on grant type."];
                    return NULL;
                }
                if (!$serviceObj) {
                    $apiresults = ["result" => "error", "message" => "A valid Service ID is required."];
                    return NULL;
                }
                if (!$clientObj) {
                    $apiresults = ["result" => "error", "message" => "Service ID not associated with valid Client."];
                    return NULL;
                }
            }
            if (!trim($scope)) {
                $apiresults = ["result" => "error", "message" => "At least one valid scope is required."];
            } else {
                $validScopes = WHMCS\ApplicationLink\Scope::pluck("scope")->all();
                $scopes = explode(" ", $scope);
                foreach ($scopes as $scopeToValidate) {
                    if (!in_array($scopeToValidate, $validScopes)) {
                        $apiresults = ["result" => "error", "message" => "The requested scope \"" . $scopeToValidate . "\" is invalid."];
                        return NULL;
                    }
                }
                $server = DI::make("oauth2_server");
                $storage = $server->getStorage("client_credentials");
                $clientIdentifier = WHMCS\ApplicationLink\Client::generateClientId();
                $secret = WHMCS\ApplicationLink\Client::generateSecret();
                $rsaId = 0;
                if ($grantType == "authorization_code") {
                    $rsa = WHMCS\Security\Encryption\RsaKeyPair::factoryKeyPair();
                    $rsa->description = "Provisioned for client credential " . $clientIdentifier;
                    $rsa->save();
                    $rsaId = $rsa->id;
                }
                $userUuid = $serviceId ? $clientObj->uuid : "";
                $storage->setClientDetails($clientIdentifier, $secret, $redirectUri, $grantType, $scope, $userUuid, $serviceId, $rsaId, $name, $description, $logoUri);
                $client = WHMCS\ApplicationLink\Client::whereIdentifier($clientIdentifier)->first();
                $apiresults = ["result" => "success", "credentialId" => $client->id, "clientIdentifier" => $client->identifier, "clientSecret" => $client->decryptedSecret];
            }
        }
    }
}
