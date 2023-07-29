<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class BadResponseException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 400;
}
