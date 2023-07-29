<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class UnauthorizedException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 401;
}
