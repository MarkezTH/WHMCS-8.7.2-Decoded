<?php

namespace WHMCS\Product\EventAction;

class ServiceEventActionProcessor extends AbstractEventActionProcessor
{
    public function __construct(\WHMCS\Service\Service $entity)
    {
        parent::__construct($entity);
    }

    public function getScheduledEventActions($eventName)
    {
        return EventAction::ofProduct($this->entity->product)->onEvent($eventName)->get()->all();
    }

    protected function getModuleHandler()
    {
        return new \WHMCS\Service($this->entity->id);
    }
}
