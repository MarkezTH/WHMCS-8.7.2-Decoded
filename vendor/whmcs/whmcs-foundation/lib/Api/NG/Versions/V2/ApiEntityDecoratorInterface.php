<?php

namespace WHMCS\Api\NG\Versions\V2;

interface ApiEntityDecoratorInterface
{
    public static function getEntityClass();

    public function decorate($entity);
}
