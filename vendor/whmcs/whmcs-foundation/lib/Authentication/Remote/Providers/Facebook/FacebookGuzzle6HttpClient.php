<?php

namespace WHMCS\Authentication\Remote\Providers\Facebook;

class FacebookGuzzle6HttpClient implements \Facebook\HttpClients\FacebookHttpClientInterface
{
    protected $client = NULL;

    public function __construct()
    {
        $this->client = new \WHMCS\Http\Client\HttpClient();
    }

    public function send($url, $method, $body, $headers, $timeOut)
    {
        $request = new \GuzzleHttp\Psr7\Request($method, $url, $headers, $body);
        try {
            $response = $this->client->send($request, ["timeout" => $timeOut, \GuzzleHttp\RequestOptions::HTTP_ERRORS => false]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \Facebook\Exceptions\FacebookSDKException($e->getMessage(), $e->getCode());
        }
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(",", $values);
        }
        return new \Facebook\Http\GraphRawResponse($headers, $response->getBody()->getContents(), $response->getStatusCode());
    }
}
