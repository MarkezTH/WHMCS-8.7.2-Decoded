<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class InternalServerErrorException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 500;
}
