<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class NotImplementedException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 501;
}
