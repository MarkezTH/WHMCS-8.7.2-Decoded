<?php

namespace Transip\Api\Library\Exception;

class HttpBadResponseException extends \RuntimeException
{
    private $innerException = NULL;
    private $response = NULL;
    const ACCESS_TOKEN_EXCEPTION_MESSAGES = ["Your access token has been revoked.", "Your access token has expired."];

    public function __construct($message, int $code, \Exception $innerException, \Psr\Http\Message\ResponseInterface $response)
    {
        $this->innerException = $innerException;
        $this->response = $response;
        parent::__construct($message, $code);
    }

    public static function badResponseException($self, $innerException, \Psr\Http\Message\ResponseInterface $response)
    {
        $errorMessage = $response->getBody();
        $decodedResponse = json_decode($errorMessage, true);
        $errorMessage = $decodedResponse["error"] ?? $response->getBody();
        $response->getStatusCode();
        switch ($response->getStatusCode()) {
            case HttpRequest\BadResponseException::STATUS_CODE:
                return new HttpRequest\BadResponseException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\UnauthorizedException::STATUS_CODE:
                if (in_array($errorMessage, self::ACCESS_TOKEN_EXCEPTION_MESSAGES, true)) {
                    return new HttpRequest\AccessTokenException($errorMessage, $response->getStatusCode(), $innerException, $response);
                }
                return new HttpRequest\UnauthorizedException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\ForbiddenException::STATUS_CODE:
                return new HttpRequest\ForbiddenException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\NotFoundException::STATUS_CODE:
                return new HttpRequest\NotFoundException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\MethodNotAllowedException::STATUS_CODE:
                return new HttpRequest\MethodNotAllowedException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\NotAcceptableException::STATUS_CODE:
                return new HttpRequest\NotAcceptableException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\BadResponseTimeoutException::STATUS_CODE:
                return new HttpRequest\BadResponseTimeoutException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\ConflictException::STATUS_CODE:
                return new HttpRequest\ConflictException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\UnprocessableEntityException::STATUS_CODE:
                return new HttpRequest\UnprocessableEntityException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\TooManyBadResponseException::STATUS_CODE:
                return new HttpRequest\TooManyBadResponseException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\InternalServerErrorException::STATUS_CODE:
                return new HttpRequest\InternalServerErrorException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\NotImplementedException::STATUS_CODE:
                return new HttpRequest\NotImplementedException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            case HttpRequest\RateLimitException::STATUS_CODE:
                return new HttpRequest\RateLimitException($errorMessage, $response->getStatusCode(), $innerException, $response);
                break;
            default:
                return new HttpBadResponseException($errorMessage, $response->getStatusCode(), $innerException, $response);
        }
    }
}
