<?php

namespace WHMCS\Module\Registrar\GoDaddy\Api;

class Client
{
    protected $client = NULL;
    protected $options = [];

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
        $this->handleErrors($response, true);
        return $response->getBody();
    }

    public function post($path, $params)
    {
        $response = $this->getClient()->post($path, $params);
        $this->handleErrors($response);
        return $response->getBody();
    }

    public function patch($path, $params)
    {
        $response = $this->getClient()->patch($path, $params);
        $this->handleErrors($response, true);
        return $response->getBody();
    }

    public function delete($path, $params)
    {
        $response = $this->getClient()->patch($path, $params);
        $this->handleErrors($response, true);
        return $response->getBody();
    }

    protected function handleErrors($response, $emptyJsonOk = false)
    {
        $json = json_decode($response->getBody());
        $statusCode = $response->getStatusCode();
        if ($json === NULL && (!$emptyJsonOk || $emptyJsonOk && 400 <= $statusCode)) {
            $msg = ": Malformed response received from server";
            throw new \WHMCS\Module\Registrar\GoDaddy\Exception\MalformedResponseException($response->getStatusCode() . $msg);
        }
        if ($statusCode < 400) {
            return NULL;
        }
        $api_response = new Response($response);
        $message = $api_response->body->message;
        $message .= "<ul>";
        if (isset($api_response->body->fields) && is_array($api_response->body->fields)) {
            foreach ($api_response->body->fields as $errors) {
                $message .= "<li>" . $errors->path . " " . $errors->message . "</li>";
            }
        }
        $message .= "</ul>";
        throw new \WHMCS\Module\Registrar\GoDaddy\Exception\ApiException($message);
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
