<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class PriceDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    public static function getEntityClass()
    {
        return "WHMCS\\Product\\Pricing\\Price";
    }

    protected function formatToArray($entity)
    {
        if ($entity->isFree()) {
            return [];
        }
        $currencyCode = $entity->price()->getCurrency()["code"];
        return ["setup" => ["value" => $entity->setup()->toNumeric(), "code" => $currencyCode], "amount" => ["value" => $entity->price()->toNumeric(), "code" => $currencyCode]];
    }
}
