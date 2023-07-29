<?php

namespace WHMCS\Application\Support\Facades;

class Storage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return "storage";
    }
}
