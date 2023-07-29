<?php

namespace Transip\Api\Library\Exception;

class ApiClientException extends \RuntimeException
{
    const CODE_API_RESPONSE_MISSING_PARAMETER = 1101;

    public function __construct($message, int $code)
    {
        parent::__construct($message, $code);
    }

    public static function parameterMissingInResponse($self, $response, $parameterName)
    {
        $parameters = implode(", ", array_keys($response));
        return new self("Required parameter '" . $parameterName . "' missing from response, got " . $parameters, self::CODE_API_RESPONSE_MISSING_PARAMETER);
    }
}
