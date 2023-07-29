<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class CurrencyDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    public static function getEntityClass()
    {
        return "WHMCS\\Billing\\Currency";
    }

    protected function formatToArray($entity)
    {
        return ["code" => strtoupper($entity->code), "prefix" => $entity->prefix, "suffix" => $entity->suffix, "default" => (bool) $entity->default];
    }
}
