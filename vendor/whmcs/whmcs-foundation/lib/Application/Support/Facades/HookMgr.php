<?php

namespace WHMCS\Application\Support\Facades;

class HookMgr extends Facade
{
    protected static function getFacadeAccessor()
    {
        return "HookManager";
    }
}
