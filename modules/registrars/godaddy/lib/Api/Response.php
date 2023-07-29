<?php

namespace WHMCS\Module\Registrar\GoDaddy\Api;

class Response
{
    public $headers = NULL;
    public $status_code = NULL;
    public $body = NULL;

    public function __construct(\Psr\Http\Message\ResponseInterface $response)
    {
        $this->headers = $response->getHeaders();
        $this->status_code = $response->getStatusCode();
        $this->body = json_decode($response->getBody());
    }
}
