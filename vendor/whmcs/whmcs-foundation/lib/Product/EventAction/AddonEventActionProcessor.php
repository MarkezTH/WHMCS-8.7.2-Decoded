<?php

namespace WHMCS\Product\EventAction;

class AddonEventActionProcessor extends AbstractEventActionProcessor
{
    public function __construct(\WHMCS\Service\Addon $entity)
    {
        parent::__construct($entity);
    }

    public function getScheduledEventActions($eventName)
    {
        return EventAction::ofAddon($this->entity->productAddon)->onEvent($eventName)->get()->all();
    }

    protected function getModuleHandler()
    {
        return new \WHMCS\Addon($this->entity->id);
    }
}
