<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class DiscountDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    public static function getEntityClass()
    {
        return "WHMCS\\Cart\\Discount";
    }

    protected function formatToArray($entity)
    {
        return ["name" => $entity->getName(), "amount" => ["value" => $entity->getAmount()->toNumeric(), "code" => $entity->getAmount()->getCurrency()->code]];
    }
}
