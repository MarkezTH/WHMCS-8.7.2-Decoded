<?php

namespace Transip\Api\Library\Exception;

class HttpClientException extends \RuntimeException
{
    private $innerException = NULL;

    public function __construct($message, \Exception $innerException)
    {
        $this->innerException = $innerException;
        parent::__construct($message);
    }

    public static function genericRequestException($self, $innerException)
    {
        return new self("Generic HTTP Client Exception: " . $innerException->getMessage(), $innerException);
    }

    public function innerException($Exception)
    {
        return $this->innerException;
    }
}
