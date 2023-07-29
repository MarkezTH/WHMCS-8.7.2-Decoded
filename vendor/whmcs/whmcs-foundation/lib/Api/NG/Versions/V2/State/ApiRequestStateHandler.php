<?php

namespace WHMCS\Api\NG\Versions\V2\State;

class ApiRequestStateHandler implements \ArrayAccess
{
    private $tokenData = [];
    private $request = NULL;
    const TOKEN_ENC_ALGORITHM = "HS256";
    const TOKEN_LEEWAY_SECONDS = 60;
    const TOKEN_HEADER_NAME = "X-Api-State";
    const TOKEN_EXPIRATION_SECONDS = 330;
    const TOKEN_MAX_LENGTH = 4096;
    const REQUEST_ATTRIBUTE_NAME = "ApiStateData";

    public function __construct()
    {
        \Firebase\JWT\JWT::$leeway = static::TOKEN_LEEWAY_SECONDS;
    }

    protected function getTokenKey()
    {
        return hash("sha256", \App::getApplicationConfig()->cc_encryption_hash, true);
    }

    protected function getDataEncryptionKey()
    {
        return hash("sha256", \App::getApplicationConfig()->cc_encryption_hash, true);
    }

    protected function encryptData($data)
    {
        $encryption = new \WHMCS\Security\Encryption\Aes();
        $encryption->setKey($this->getDataEncryptionKey());
        return $encryption->encrypt($data);
    }

    protected function decryptData($encryptedData)
    {
        $encryption = new \WHMCS\Security\Encryption\Aes();
        $encryption->setKey($this->getDataEncryptionKey());
        return $encryption->decrypt($encryptedData);
    }

    public function createForRequest($ServerRequest, $request)
    {
        $this->request = $request;
        $request = $request->withAttribute(static::REQUEST_ATTRIBUTE_NAME, $this);
        $jwt = implode("", $request->getHeader(static::TOKEN_HEADER_NAME));
        if (!$jwt) {
            return $request;
        }
        try {
            $payload = (array) \Firebase\JWT\JWT::decode($jwt, $this->getTokenKey(), [static::TOKEN_ENC_ALGORITHM]);
            $tokenData = $payload["sub"] ?? NULL;
            if ($tokenData) {
                $tokenData = json_decode($this->decryptData($tokenData), true);
            } else {
                $tokenData = [];
            }
            if (isset($payload["whmcs_user"])) {
                $userData = json_decode($this->decryptData($payload["whmcs_user"]), true);
                if (is_array($userData) && isset($userData["id"]) && is_numeric($userData["id"])) {
                    $user = \WHMCS\User\User::find($userData["id"]);
                    if ($user) {
                        \Auth::setUser($user);
                        if (isset($userData["client_id"]) && is_numeric($userData["client_id"])) {
                            $client = \WHMCS\User\Client::find($userData["client_id"]);
                            if ($client) {
                                \Auth::setClientId($client->id);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $tokenData = [];
        }
        $this->tokenData = $tokenData;
        return $request;
    }

    public function addToResponse($ResponseInterface, $response)
    {
        $urlParts = parse_url($this->request->getUri());
        $issuer = $urlParts["scheme"] . "://" . ($urlParts["host"] ?? NULL) . "/";
        $payload = ["iss" => $issuer, "aud" => $issuer, "exp" => \WHMCS\Carbon::now()->addSeconds(static::TOKEN_EXPIRATION_SECONDS)->getTimestamp(), "sub" => $this->tokenData];
        if (!empty($this->tokenData)) {
            $payload["sub"] = $this->encryptData(json_encode($this->tokenData));
        }
        if (\Auth::user()) {
            $userData = ["id" => \Auth::user()->id];
            if (\Auth::client()) {
                $userData["client_id"] = \Auth::client()->id;
            }
            $payload["whmcs_user"] = $this->encryptData(json_encode($userData));
        }
        $jwt = \Firebase\JWT\JWT::encode($payload, $this->getTokenKey(), static::TOKEN_ENC_ALGORITHM);
        if (static::TOKEN_MAX_LENGTH < strlen($jwt)) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("JWT token is too long");
        }
        return $response->withHeader(static::TOKEN_HEADER_NAME, $jwt);
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->tokenData);
    }

    public function offsetGet($offset)
    {
        return $this->tokenData[$offset] ?? NULL;
    }

    public function offsetSet($offset, $value)
    {
        $this->tokenData[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->tokenData[$offset]);
    }
}
