<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class CollectionDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    public static function getEntityClass()
    {
        return "Illuminate\\Support\\Collection";
    }

    protected function formatToArray($entity)
    {
        return $entity->map(function ($item) {
            return \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($item);
        })->toArray();
    }
}
