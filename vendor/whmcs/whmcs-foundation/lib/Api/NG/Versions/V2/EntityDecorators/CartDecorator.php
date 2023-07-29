<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class CartDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    public static function getEntityClass()
    {
        return "WHMCS\\Cart\\Models\\Cart";
    }

    protected function formatToArray($entity)
    {
        return ["id" => $entity->tag];
    }
}
