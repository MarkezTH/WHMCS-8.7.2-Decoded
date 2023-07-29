<?php

namespace Transip\Api\Library\HttpClient;

class GuzzleClient extends HttpClient
{
    private $client = NULL;

    public function __construct($endpoint, \GuzzleHttp\Client $client = NULL)
    {
        $this->client = $client ?? new \GuzzleHttp\Client();
        parent::__construct($endpoint);
    }

    public function setToken($token)
    {
        $stack = $this->client->getConfig("handler") ?? \GuzzleHttp\HandlerStack::create();
        $stack->remove(Middleware\TokenAuthorization::HANDLER_NAME);
        $stack->push(new Middleware\TokenAuthorization($token, self::USER_AGENT), Middleware\TokenAuthorization::HANDLER_NAME);
        $this->token = $token;
    }

    private function sendRequest($ResponseInterface, $method, $uri = [], $options)
    {
        try {
            return $this->request($method, $uri, $options);
        } catch (\Transip\Api\Library\Exception\HttpRequest\AccessTokenException $exception) {
            $this->clearCache();
            $this->setToken("");
            return $this->request($method, $uri, $options);
        }
    }

    private function request($ResponseInterface, $method, $uri = [], $options)
    {
        $this->checkAndRenewToken();
        $options = $this->checkAndSetTestModeToOptions($options);
        try {
            return $this->client->request($method, $this->endpoint . $uri, $options);
        } catch (\Exception $exception) {
            $this->handleException($exception);
        }
    }

    public function get($uri = [], $query)
    {
        $response = $this->sendRequest("GET", $uri, ["query" => $query]);
        if ($response->getStatusCode() !== 200) {
            throw \Transip\Api\Library\Exception\ApiException::unexpectedStatusCode($response);
        }
        if ($response->getBody() == NULL) {
            throw \Transip\Api\Library\Exception\ApiException::emptyResponse($response);
        }
        $responseBody = json_decode($response->getBody(), true);
        if ($responseBody === NULL) {
            throw \Transip\Api\Library\Exception\ApiException::malformedJsonResponse($response);
        }
        $this->parseResponseHeaders($response);
        return $responseBody;
    }

    public function post($uri = [], $body)
    {
        $options["body"] = json_encode($body);
        $response = $this->sendRequest("POST", $uri, $options);
        if ($response->getStatusCode() !== 201) {
            throw \Transip\Api\Library\Exception\ApiException::unexpectedStatusCode($response);
        }
        $this->parseResponseHeaders($response);
    }

    public function postAuthentication($uri, $signature, $body)
    {
        $options["headers"] = ["Signature" => $signature];
        $options["body"] = json_encode($body);
        try {
            $response = $this->client->post($this->endpoint . $uri, $options);
        } catch (\Exception $exception) {
            $this->handleException($exception);
        }
        if ($response->getStatusCode() !== 201) {
            throw \Transip\Api\Library\Exception\ApiException::unexpectedStatusCode($response);
        }
        if ($response->getBody() == NULL) {
            throw \Transip\Api\Library\Exception\ApiException::emptyResponse($response);
        }
        $responseBody = json_decode($response->getBody(), true);
        if ($responseBody === NULL) {
            throw \Transip\Api\Library\Exception\ApiException::malformedJsonResponse($response);
        }
        return $responseBody;
    }

    public function put($uri, $body)
    {
        $options["body"] = json_encode($body);
        $response = $this->sendRequest("PUT", $uri, $options);
        if ($response->getStatusCode() !== 204) {
            throw \Transip\Api\Library\Exception\ApiException::unexpectedStatusCode($response);
        }
        $this->parseResponseHeaders($response);
    }

    public function patch($uri, $body)
    {
        $options["body"] = json_encode($body);
        $response = $this->sendRequest("PATCH", $uri, $options);
        if ($response->getStatusCode() !== 204) {
            throw \Transip\Api\Library\Exception\ApiException::unexpectedStatusCode($response);
        }
        $this->parseResponseHeaders($response);
    }

    public function delete($uri = [], $body)
    {
        $options["body"] = json_encode($body);
        $response = $this->sendRequest("DELETE", $uri, $options);
        if ($response->getStatusCode() !== 204) {
            throw \Transip\Api\Library\Exception\ApiException::unexpectedStatusCode($response);
        }
        $this->parseResponseHeaders($response);
    }

    private function handleException($exception)
    {
        if ($exception instanceof \GuzzleHttp\Exception\BadResponseException) {
            if ($exception->hasResponse()) {
                throw \Transip\Api\Library\Exception\HttpBadResponseException::badResponseException($exception, $exception->getResponse());
            }
            throw \Transip\Api\Library\Exception\HttpClientException::genericRequestException($exception);
        }
        if ($exception instanceof \GuzzleHttp\Exception\RequestException) {
            throw \Transip\Api\Library\Exception\HttpRequestException::requestException($exception);
        }
        throw \Transip\Api\Library\Exception\HttpClientException::genericRequestException($exception);
    }

    private function checkAndSetTestModeToOptions($options)
    {
        if (!$this->testMode) {
            return $options;
        }
        if (!array_key_exists("query", $options)) {
            $options["query"] = [];
        }
        $options["query"]["test"] = 1;
        return $options;
    }
}
