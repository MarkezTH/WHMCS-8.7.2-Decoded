<?php

namespace Transip\Api\Library\HttpClient;

abstract class HttpClient implements HttpClientInterface
{
    protected $authRepository = NULL;
    protected $endpoint = NULL;
    protected $token = "";
    protected $login = "";
    protected $privateKey = "";
    protected $generateWhitelistOnlyTokens = false;
    protected $cache = NULL;
    protected $readOnlyMode = false;
    protected $testMode = false;
    private $rateLimitLimit = -1;
    private $rateLimitRemaining = -1;
    private $rateLimitReset = -1;
    private $chosenTokenExpiry = "1 day";
    const TOKEN_CACHE_KEY = "token";
    const KEY_FINGERPRINT_CACHE_KEY = "key-fingerprint";
    const USER_AGENT = "TransIP ApiClient";

    public function __construct($endpoint)
    {
        $endpoint = rtrim($endpoint, "/");
        $this->endpoint = $endpoint;
        $this->authRepository = new \Transip\Api\Library\Repository\AuthRepository($this);
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    public function checkAndRenewToken()
    {
        if ($this->authRepository->tokenHasExpired($this->token)) {
            $token = $this->authRepository->createToken($this->login, $this->privateKey, $this->generateWhitelistOnlyTokens, $this->readOnlyMode, "", $this->getChosenTokenExpiry());
            $this->setToken($token);
            $tokenExpiryTime = $this->authRepository->getExpirationTimeFromToken($this->token);
            $cacheExpiryTime = new \DateTime("@" . $tokenExpiryTime);
            $cacheItem = $this->cache->getItem(self::TOKEN_CACHE_KEY);
            $cacheItem->set($token);
            $cacheItem->expiresAt($cacheExpiryTime);
            $this->cache->save($cacheItem);
            $cacheItem = $this->cache->getItem(self::KEY_FINGERPRINT_CACHE_KEY);
            $cacheItem->set($this->getFingerPrintFromKey($this->privateKey));
            $cacheItem->expiresAt($cacheExpiryTime);
            $this->cache->save($cacheItem);
        }
    }

    public function getTokenFromCache()
    {
        $cachedToken = $this->cache->getItem(self::TOKEN_CACHE_KEY);
        $cachedKeyFP = $this->cache->getItem(self::KEY_FINGERPRINT_CACHE_KEY);
        if ($cachedToken->isHit() && $cachedKeyFP->isHit()) {
            $storedKeyFP = $cachedKeyFP->get();
            $storedToken = $cachedToken->get();
            if ($this->getFingerPrintFromKey($this->privateKey) === $storedKeyFP) {
                $this->setToken($storedToken);
            } else {
                $this->clearCache();
            }
        }
    }

    protected function parseResponseHeaders($response)
    {
        $this->rateLimitLimit = $response->getHeader("X-Rate-Limit-Limit")[0] ?? -1;
        $this->rateLimitRemaining = $response->getHeader("X-Rate-Limit-Remaining")[0] ?? -1;
        $this->rateLimitReset = $response->getHeader("X-Rate-Limit-Reset")[0] ?? -1;
    }

    private function getFingerPrintFromKey($privateKey)
    {
        return hash("SHA512", $privateKey);
    }

    public function clearCache()
    {
        $this->cache->deleteItem(self::TOKEN_CACHE_KEY);
        $this->cache->deleteItem(self::KEY_FINGERPRINT_CACHE_KEY);
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setLogin($login)
    {
        $this->login = $login;
    }

    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;
    }

    public function getGenerateWhitelistOnlyTokens()
    {
        return $this->generateWhitelistOnlyTokens;
    }

    public function setGenerateWhitelistOnlyTokens($generateWhitelistOnlyTokens)
    {
        $this->generateWhitelistOnlyTokens = $generateWhitelistOnlyTokens;
    }

    public function getUserAgent()
    {
        return self::USER_AGENT . " v" . \Transip\Api\Library\TransipAPI::TRANSIP_API_LIBRARY_VERSION;
    }

    public function setReadOnlyMode($mode)
    {
        $this->readOnlyMode = $mode;
    }

    public function getReadOnlyMode()
    {
        return $this->readOnlyMode;
    }

    public function setTokenLabelPrefix($labelPrefix)
    {
        $this->authRepository->setLabelPrefix($labelPrefix);
    }

    public function getTestMode()
    {
        return $this->testMode;
    }

    public function setTestMode($testMode)
    {
        $this->testMode = $testMode;
    }

    public function getRateLimitLimit()
    {
        return $this->rateLimitLimit;
    }

    public function getRateLimitRemaining()
    {
        return $this->rateLimitRemaining;
    }

    public function getRateLimitReset()
    {
        return $this->rateLimitReset;
    }

    public function getChosenTokenExpiry()
    {
        return $this->chosenTokenExpiry;
    }

    public function setChosenTokenExpiry($chosenTokenExpiry)
    {
        $this->chosenTokenExpiry = $chosenTokenExpiry;
    }
    public abstract function setToken($token);
    public abstract function get($url, $query);
    public abstract function post($url, $body);
    public abstract function postAuthentication($url, $signature, $body);
    public abstract function put($url, $body);
    public abstract function patch($url, $body);
    public abstract function delete($url, $body);
}
