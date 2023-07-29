<?php

namespace WHMCS\Module\Gateway\GoCardless\Api;

class Client
{
    protected $client = NULL;
    protected $options = [];
    const API_VERSION = "2015-07-06";

    public function __construct($options)
    {
        $this->options = $options;
        $this->client = $this->getClient();
    }

    protected function getClient()
    {
        if (is_null($this->client)) {
            return new \WHMCS\Http\Client\HttpClient($this->options);
        }
        return $this->client;
    }

    public function get($path, $params = [])
    {
        if (is_array($params) && array_key_exists("query", $params)) {
            $params["query"] = $this->castBooleanValuesToStrings($params["query"]);
        }
        $response = $this->getClient()->get($path, $params);
        $this->handleErrors($response);
        return $response->getBody();
    }

    public function put($path, $params)
    {
        $response = $this->getClient()->put($path, $params);
        $this->handleErrors($response);
        return $response->getBody();
    }

    public function post($path, $params)
    {
        $idempotencyKey = uniqid("", true);
        $paramsWithHeaders = ["headers" => ["Idempotency-Key" => $idempotencyKey]];
        $params = array_merge($params, $paramsWithHeaders);
        $response = $this->getClient()->post($path, $params);
        $this->handleErrors($response);
        return $response->getBody();
    }

    protected function handleErrors($response)
    {
        $json = json_decode($response->getBody());
        if ($json === NULL) {
            $msg = "Malformed response received from server";
            throw new \WHMCS\Module\Gateway\GoCardless\Exception\MalformedResponseException($msg, $response->getStatusCode());
        }
        if ($response->getStatusCode() < 400) {
            return NULL;
        }
        $api_response = new Response($response);
        $message = $api_response->body->error->message;
        foreach ($api_response->body->error->errors as $error) {
            $message .= " - " . $error->field . " " . $error->message;
        }
        throw new \WHMCS\Module\Gateway\GoCardless\Exception\ApiException($message);
    }

    protected function castBooleanValuesToStrings($query)
    {
        return array_map(function ($value) {
            if ($value === true) {
                return "true";
            }
            if ($value === false) {
                return "false";
            }
            if (is_array($value)) {
                return $this->castBooleanValuesToStrings($value);
            }
            return $value;
        }, $query);
    }
}
