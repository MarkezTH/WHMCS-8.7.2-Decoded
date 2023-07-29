<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

trait PricingDecoratorTrait
{
    protected function decoratePricing($entity)
    {
        $entityData["pricing"]["is_free"] = false;
        if ($entity->isFree()) {
            $entityData["pricing"]["is_free"] = true;
        } else {
            if ($entity->isOneTime()) {
                $entityData["pricing"]["onetime"] = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($entity->pricing()->onetime());
            } else {
                foreach ($entity->pricing()->allAvailableCycles() as $cyclePrice) {
                    $entityData["pricing"]["recurring"][] = array_merge(["cycle" => $cyclePrice->cycle()], \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($cyclePrice));
                }
            }
        }
        return $entityData;
    }
}
