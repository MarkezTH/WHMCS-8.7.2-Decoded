<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class TaxTotalDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    public static function getEntityClass()
    {
        return "WHMCS\\Cart\\TaxTotal";
    }

    protected function formatToArray($entity)
    {
        return ["name" => $entity->getDescription(), "percentage" => $entity->getPercentage(), "amount" => ["value" => $entity->getAmount()->toNumeric(), "code" => $entity->getAmount()->getCurrency()->code]];
    }
}
