<?php

namespace Transip\Api\Library\HttpClient\Middleware;

class TokenAuthorization
{
    private $token = NULL;
    private $userAgent = NULL;
    const HANDLER_NAME = "transip_token_authentication";

    public function __construct($token, $userAgent)
    {
        $this->token = $token;
        $this->userAgent = $userAgent;
    }

    public function __invoke($Closure, $handler)
    {
        return function (\Psr\Http\Message\RequestInterface $request, $options) {
            return $handler($request->withAddedHeader("Authorization", sprintf("Bearer %s", $this->token))->withAddedHeader("User-Agent", $this->userAgent), $options);
        };
    }
}
