<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class MethodNotAllowedException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 405;
}
