<?php

namespace Transip\Api\Library\Exception\HttpRequest;

class NotFoundException extends \Transip\Api\Library\Exception\HttpBadResponseException
{
    const STATUS_CODE = 404;
}
