<?php

namespace WHMCS\ApplicationLink\Server;

class Server extends \OAuth2\Server implements ApplicationLinkServerInterface
{
    public function getAccessToken(\OAuth2\HttpFoundationBridge\Request $request)
    {
        $accessToken = $request->request->get("access_token", "");
        if (!$accessToken) {
            $accessToken = $request->query->get("access_token", "");
        }
        return $this->findAccessToken($accessToken);
    }

    protected function findAccessToken($accessToken)
    {
        return \WHMCS\ApplicationLink\AccessToken::where("access_token", "=", $accessToken)->first();
    }

    public function postAccessTokenResponseAction(\OAuth2\RequestInterface $request, \OAuth2\ResponseInterface $response)
    {
    }

    public function getModuleApplicationLinkServer(\OAuth2\HttpFoundationBridge\Request $request)
    {
        $module = $class = NULL;
        $moduleName = $request->request->get("module", "");
        if (!$moduleName) {
            $moduleName = $request->query->get("module", "");
        }
        if ($moduleName) {
            $moduleType = $request->request->get("module_type", "");
            if (!$moduleType) {
                $moduleType = $request->query->get("module_type", "");
            }
            $config = [];
            switch ($moduleType) {
                case "server":
                    $module = new \WHMCS\Module\Server();
                    if ($module->load($moduleName)) {
                        $class = ucfirst(strtolower($moduleName)) . "\\ApplicationLink\\Server";
                    }
                    break;
                case "api":
                    $config["access_lifetime"] = 60;
                    if ($moduleName == "ClientAccessSsoToken") {
                        $class = "WHMCS\\Api\\ApplicationSupport\\ApplicationLinks\\Server\\ClientAccessSsoToken";
                    }
                    $config = array_merge($this->config, $config);
                    if ($class && class_exists($class)) {
                        try {
                            $shimServer = new $class($this->getStorages(), $config, $this->getGrantTypes(), $this->getResponseTypes() ? $this->getResponseTypes() : [], $this->getTokenType(), $this->getScopeUtil(), $this->getClientAssertionType());
                            $interface = "\\WHMCS\\ApplicationLink\\Server\\ApplicationLinkServerInterface";
                            if (!$shimServer instanceof $interface) {
                                throw new \WHMCS\Exception\Information(sprintf("%s must implement %s", $class, $interface));
                            }
                            return $shimServer;
                        } catch (\Exception $e) {
                            logActivity($e->getMessage());
                            return NULL;
                        }
                    }
                    break;
            }
        }
    }

    public function handleSingleSignOnRequest(\OAuth2\RequestInterface $request, \OAuth2\ResponseInterface $response, $requestedScope = "")
    {
        $request->setMethod(\OAuth2\HttpFoundationBridge\Request::METHOD_POST);
        if (strpos($requestedScope, "clientarea:sso") === false) {
            $requestedScope .= " clientarea:sso";
        }
        if ($this->verifyResourceRequest($request, $response, $requestedScope)) {
            $token = $this->getAccessToken($request);
            $storage = $this->getStorage("client_credentials");
            $sso = new \WHMCS\ApplicationLink\GrantType\SingleSignOn($storage);
            if (!$storage->checkRestrictedGrantType($token->clientId, $sso->getQuerystringIdentifier())) {
                $response->setError(400, "invalid_token", sprintf("token's client must have %s grant.", $sso->getQuerystringIdentifier()));
                return $response;
            }
            $tokenUser = $token->getUser();
            $tokenClient = $token->getClient();
            if ($tokenUser && $tokenClient && !$this->validateClient($tokenUser, $tokenClient)) {
                $response->setError(401, "access_denied", "Single Sign-On is not available for client ID.");
                return $response;
            }
            $response = new SingleSignOn\RedirectResponse();
            $response->prepare($request);
            if (!$tokenUser) {
                return $response;
            }
            $context = $request->query->get("context", "");
            if (!$context) {
                $context = $request->request->get("context", "");
            }
            if ($context && $response::isValidRedirectContext($context)) {
                $response->setRedirectContext($context);
            }
            $response->setTargetUrlFromToken($token);
            $dataForSession = $response->getTargetSessionVariables($token);
            if (!empty($dataForSession)) {
                \WHMCS\Session::set("ssoContextData", $dataForSession);
            }
            \Auth::login($tokenUser);
            if ($tokenClient) {
                \Auth::setClientId($tokenClient->id);
            }
            $token->delete();
        }
        return $response;
    }

    protected function validateClient($user, \WHMCS\User\Client $client)
    {
        if (!$client->isAllowedToAuthenticate()) {
            $msg = "Single Sign-On authentication denied for \"Closed\" User ID: %s";
            logActivity(sprintf($msg, $client->id), $client->id);
            return false;
        }
        if (!$client->hasSingleSignOnPermission()) {
            $msg = "Single Sign-On authentication denied per configuration for User ID: %s";
            logActivity(sprintf($msg, $client->id), $client->id);
            return false;
        }
        if (!$user->hasAccessToClient($client)) {
            $msg = "Single Sign-On authentication denied for UserID: %s";
            logActivity(sprintf($msg, $user->id), $client->id);
            return false;
        }
        return true;
    }

    public function getDiscoveryDocument()
    {
        $issuer = static::getIssuer();
        $doc = ["issuer" => $issuer, "authorization_endpoint" => $issuer . "/oauth/authorize.php", "token_endpoint" => $issuer . "/oauth/token.php", "userinfo_endpoint" => $issuer . "/oauth/userinfo.php", "jwks_uri" => $issuer . "/oauth/certs.php", "response_types_supported" => $this->getResponseTypes(), "subject_types_supported" => ["public"], "id_token_signing_alg_values_supported" => ["RS256"], "scopes_supported" => ["openid", "email", "profile"], "claims_supported" => ["iss", "aud", "exp", "sub"]];
        return json_encode($doc);
    }

    public function getJwks()
    {
        $clientsWithCerts = \WHMCS\ApplicationLink\Client::where("user_id", "=", "")->where("service_id", "<", 1)->where("rsa_key_pair_id", ">", 0)->with("rsaKeyPair")->get();
        $jwks = ["keys" => []];
        foreach ($clientsWithCerts as $client) {
            $keypair = $client->rsaKeyPair;
            if ($keypair) {
                $rsa = $keypair->publicRsa;
                $jwks["keys"][] = ["kty" => "RSA", "alg" => "RS256", "kid" => $keypair->identifier, "n" => $this->base64urlEncode($rsa->modulus->toBytes()), "e" => $this->base64urlEncode($rsa->exponent->toBytes())];
            }
        }
        return $jwks;
    }

    public function base64urlEncode($data)
    {
        $data = base64_encode($data);
        $data = strtr($data, "+/", "-_");
        $data = rtrim($data, "=");
        return $data;
    }

    public function base64urlDecode($data)
    {
        $data = strtr($data, "-_", "+/");
        $data = str_pad($data, strlen($data) % 4, "=", STR_PAD_RIGHT);
        $data = base64_decode($data);
        return $data;
    }

    public static function getIssuer()
    {
        $issuer = \App::getSystemSSLURLOrFail();
        if (substr($issuer, -1) == "/") {
            $issuer = substr($issuer, 0, -1);
        }
        return $issuer;
    }

    protected function createDefaultIdTokenResponseType()
    {
        if (!isset($this->storages["user_claims"])) {
            throw new \LogicException("You must supply a storage object implementing OAuth2\\OpenID\\Storage\\UserClaimsInterface to use openid connect");
        }
        if (!isset($this->storages["public_key"])) {
            throw new \LogicException("You must supply a storage object implementing OAuth2\\Storage\\PublicKeyInterface to use openid connect");
        }
        $config = array_intersect_key($this->config, array_flip(explode(" ", "issuer id_lifetime")));
        return new \WHMCS\ApplicationLink\OpenID\ResponseType\IdToken($this->storages["user_claims"], $this->storages["public_key"], $config, new \OAuth2\Encryption\FirebaseJwt());
    }

    public function hasUserAuthorizedRequestedScopes(\WHMCS\User\Client $user)
    {
        $controller = $this->getAuthorizeController();
        $clientId = $controller->getClientId();
        if ($clientId) {
            $authorizedScopes = $this->getStorage("authorization_code")->getUserAuthorizedScopes($clientId, $user);
            if ($authorizedScopes->count()) {
                $requestedScopes = $this->getAuthorizeController()->getScope();
                if ($requestedScopes) {
                    $requestedScopes = explode(" ", $requestedScopes);
                    $requestedScopeCollection = \WHMCS\ApplicationLink\Scope::whereIn("scope", $requestedScopes)->get(["id"]);
                } else {
                    $requestedScopeCollection = new \Illuminate\Database\Eloquent\Collection();
                }
                foreach ($requestedScopeCollection as $scope) {
                    if (!$authorizedScopes->contains($scope->id)) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function updateUserAuthorizedScopes(\WHMCS\User\Client $user, $scopes = [])
    {
        $clientId = $this->getAuthorizeController()->getClientId();
        if ($scopes) {
            $authorizedScopeCollection = \WHMCS\ApplicationLink\Scope::whereIn("scope", $scopes)->get(["id"]);
        } else {
            $authorizedScopeCollection = new \Illuminate\Database\Eloquent\Collection();
        }
        $this->getStorage("authorization_code")->setUserAuthorizedScopes($clientId, $user, $authorizedScopeCollection);
        return $this;
    }

    public static function storeRequest(\OAuth2\HttpFoundationBridge\Request $request)
    {
        $validRequestKeys = ["client_id", "nonce", "redirect_uri", "response_type", "scope", "state"];
        $requestData = ["post" => [], "get" => [], "headers" => [], "method" => $request->getMethod()];
        foreach ($validRequestKeys as $key) {
            $value = $request->request->get($key);
            if (!is_null($value)) {
                $requestData["post"][$key] = $value;
            } else {
                $value = $request->query->get($key);
                if (!is_null($value)) {
                    $requestData["get"][$key] = $value;
                }
            }
        }
        $requestData["headers"] = $request->headers->all();
        $requestHash = base64_encode(\phpseclib\Crypt\Random::string(8));
        $transientData = new \WHMCS\TransientData();
        $transientData->purgeExpired();
        $transientData->store($requestHash, json_encode($requestData), 60);
        return $requestHash;
    }

    public static function retrieveRequest($requestHash, $deleteAfterFetch = true)
    {
        $request = new \OAuth2\HttpFoundationBridge\Request();
        $transientData = new \WHMCS\TransientData();
        $requestData = $transientData->retrieve($requestHash);
        if ($requestData) {
            $requestData = json_decode($requestData, true);
            if (!empty($requestData["post"])) {
                $request->request->add($requestData["post"]);
            }
            if (!empty($requestData["get"])) {
                $request->query->add($requestData["get"]);
            }
            if (!empty($requestData["headers"])) {
                $request->headers->add($requestData["headers"]);
            }
            $request->setMethod($requestData["method"]);
            if ($deleteAfterFetch) {
                $transientData->delete($requestHash);
            }
        }
        return $request;
    }

    public function pruneExpiredAccessTokens()
    {
        $stdDuration = $this->config["access_lifetime"];
        $tokens = \WHMCS\ApplicationLink\AccessToken::deleteExpired(\WHMCS\Carbon::now()->subSeconds($stdDuration * 2));
    }
}
