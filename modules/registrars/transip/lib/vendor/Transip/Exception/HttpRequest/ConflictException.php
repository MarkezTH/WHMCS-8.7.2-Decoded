<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class ConflictException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 409;
}
