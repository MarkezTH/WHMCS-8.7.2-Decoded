<?php

namespace WHMCS\Application\Support\Facades;

class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return "auth";
    }
}
