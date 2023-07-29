<?php

namespace WHMCS\Application\Support\Facades;

class Hook extends Facade
{
    protected static function getFacadeAccessor()
    {
        return "HookPublicRegistry";
    }
}
