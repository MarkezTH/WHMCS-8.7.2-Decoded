<?php

namespace Transip\Api\Library\HttpClient;

interface HttpClientInterface
{
    public function setToken($token);

    public function get($url, $query);

    public function post($url, $body);

    public function postAuthentication($url, $signature, $body);

    public function put($url, $body);

    public function patch($url, $body);

    public function delete($url, $body);
}
