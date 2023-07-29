<?php

namespace WHMCS\Exception;

class ServiceUnavailable extends \WHMCS\Exception
{
    public static function factory($self, $identifier = NULL, \Exception $initiatingException)
    {
        return new static($identifier, 0, $initiatingException);
    }
}
