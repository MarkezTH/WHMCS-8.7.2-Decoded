<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class NotAcceptableException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 406;
}
