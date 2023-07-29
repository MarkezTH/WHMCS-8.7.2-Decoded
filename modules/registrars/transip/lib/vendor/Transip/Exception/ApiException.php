<?php

namespace Transip\Api\Library\Exception;

class ApiException extends \RuntimeException
{
    private $response = NULL;
    const CODE_API_EMPTY_RESPONSE = 1001;
    const CODE_API_UNEXPECTED_STATUS_CODE = 1002;
    const CODE_API_MALFORMED_JSON_RESPONSE = 1003;

    public function __construct($message, int $code, \Psr\Http\Message\ResponseInterface $response)
    {
        $this->response = $response;
        parent::__construct($message, $code);
    }

    public static function emptyResponse($self, $response)
    {
        return new self("Api returned statuscode " . $response->getStatusCode() . ", but the response was empty", self::CODE_API_EMPTY_RESPONSE, $response);
    }

    public static function malformedJsonResponse($self, $response)
    {
        return new self("Api returned statuscode " . $response->getStatusCode() . ", but the response was not json decodable" . PHP_EOL . $response->getBody(), self::CODE_API_MALFORMED_JSON_RESPONSE, $response);
    }

    public static function unexpectedStatusCode($self, $response)
    {
        $responseBody = $response->getBody();
        if (json_decode($response->getBody(), true) !== NULL) {
            $responseBody = json_decode($response->getBody(), true);
        }
        return new self("Api returned unexpected statuscode " . $response->getStatusCode() . ":  " . $responseBody, self::CODE_API_UNEXPECTED_STATUS_CODE, $response);
    }

    public function response($ResponseInterface)
    {
        return $this->response;
    }
}
