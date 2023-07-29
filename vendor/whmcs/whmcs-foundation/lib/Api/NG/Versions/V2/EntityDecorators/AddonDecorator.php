<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class AddonDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    use PricingDecoratorTrait;

    public static function getEntityClass()
    {
        return "WHMCS\\Product\\Addon";
    }

    protected function formatToArray($entity)
    {
        $productData = $entity->only(["id", "name", "description"]);
        if (trim($productData["description"]) === "") {
            unset($productData["description"]);
        }
        if ($entity instanceof \WHMCS\Product\PricedEntityInterface) {
            $productData = array_merge($productData, $this->decoratePricing($entity));
        }
        return $productData;
    }
}
