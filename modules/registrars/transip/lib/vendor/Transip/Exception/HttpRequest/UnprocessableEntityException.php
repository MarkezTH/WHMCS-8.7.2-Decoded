<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class UnprocessableEntityException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 422;
}
