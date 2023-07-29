<?php

namespace Transip\Api\Library\Repository;

class AuthRepository extends ApiRepository
{
    protected $labelPrefix = "api.lib-";
    const RESOURCE_NAME = "auth";

    protected function getRepositoryResourceNames()
    {
        return [self::RESOURCE_NAME];
    }

    public function createToken($customerLoginName, $privateKey = false, $generateWhitelistOnlyTokens = false, $readOnly = "", $label = "1 day", $expirationTime)
    {
        if ($label === "") {
            $label = $this->getLabelPrefix() . time();
        }
        $requestBody = ["login" => $customerLoginName, "nonce" => bin2hex(random_bytes(16)), "read_only" => $readOnly, "expiration_time" => $expirationTime, "label" => $label, "global_key" => !$generateWhitelistOnlyTokens];
        $signature = $this->createSignature($privateKey, $requestBody);
        $response = $this->httpClient->postAuthentication($this->getResourceUrl(), $signature, $requestBody);
        $token = $this->getParameterFromResponse($response, "token");
        return $token;
    }

    public function tokenHasExpired($token)
    {
        if ($token === "") {
            return true;
        }
        $expirationTime = $this->getExpirationTimeFromToken($token);
        $currentTime = time();
        $diff = $expirationTime - $currentTime;
        return $diff < 60;
    }

    public function getExpirationTimeFromToken($token)
    {
        if ($token === "") {
            return 0;
        }
        try {
            $data = explode(".", $token);
            $body = json_decode(base64_decode($data[1]), true);
            $expirationTime = $body["exp"] ?? 0;
        } catch (\Exception $exception) {
            $expirationTime = 0;
        }
        return intval($expirationTime);
    }

    private function createSignature($privateKey, $parameters)
    {
        if (!preg_match("/-----BEGIN (RSA )?PRIVATE KEY-----(.*)-----END (RSA )?PRIVATE KEY-----/si", $privateKey, $matches)) {
            throw new \RuntimeException("Could not find a valid private key");
        }
        $key = $matches[2];
        $key = preg_replace("/\\s*/s", "", $key);
        $key = chunk_split($key, 64, "\n");
        $key = "-----BEGIN PRIVATE KEY-----\n" . $key . "-----END PRIVATE KEY-----";
        if (!@openssl_sign(@json_encode($parameters), $signature, $key, OPENSSL_ALGO_SHA512)) {
            throw new \RuntimeException("The provided private key is invalid");
        }
        return base64_encode($signature);
    }

    public function getLabelPrefix()
    {
        return $this->labelPrefix;
    }

    public function setLabelPrefix($labelPrefix)
    {
        $this->labelPrefix = $labelPrefix;
    }
}
