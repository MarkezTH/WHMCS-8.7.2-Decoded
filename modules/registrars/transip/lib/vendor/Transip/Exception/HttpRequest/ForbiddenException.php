<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class ForbiddenException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 403;
}
