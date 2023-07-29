<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class RateLimitException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 429;
}
