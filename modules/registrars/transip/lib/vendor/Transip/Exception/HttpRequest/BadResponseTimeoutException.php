<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class BadResponseTimeoutException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 408;
}
