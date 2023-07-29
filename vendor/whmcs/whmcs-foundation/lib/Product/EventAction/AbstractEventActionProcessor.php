<?php

namespace WHMCS\Product\EventAction;

abstract class AbstractEventActionProcessor
{
    protected $entity = NULL;
    public abstract function getScheduledEventActions($eventName);
    protected abstract function getModuleHandler();

    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    public function process($scheduledActions)
    {
        if (empty($scheduledActions)) {
            return NULL;
        }
        $server = $this->entity->serverModel->getModuleInterface();
        $params = $server->getServerParams($this->entity->serverModel);
        $moduleHandler = $this->getModuleHandler();
        $serviceCustomFields = $this->entity->customFieldValues->pluck("value", "customField.fieldName")->toArray();
        $moduleEventActionConfigs = $server->callIfExists("EventActions", []);
        $validActionParams = [];
        foreach ($moduleEventActionConfigs as $actionName => $actionConfig) {
            $validActionParams[$actionName] = array_flip(array_map(function ($paramConfig) {
                return $paramConfig["Description"];
            }, $actionConfig["Params"] ?? []));
        }
        foreach ($scheduledActions as $eventAction) {
            $actionParamKeyMap = $validActionParams[$eventAction->name] ?? [];
            $eventActionParams = $eventAction->params;
            foreach ($actionParamKeyMap as $friendlyName => $fieldName) {
                if (isset($serviceCustomFields[$friendlyName]) && $serviceCustomFields[$friendlyName] !== "") {
                    $eventActionParams[$fieldName] = \WHMCS\Input\Sanitize::decode($serviceCustomFields[$friendlyName]);
                }
            }
            $callParams = array_merge($params, $eventActionParams);
            $success = $moduleHandler->moduleCall($eventAction->action, $callParams);
            $moduleData = $moduleHandler->getModuleReturn("data") ?: [];
            if (!$success || isset($moduleData["error"])) {
                logModuleCall($this->entity->moduleInterface()->getLoadedModule(), $eventAction->action, $callParams, $moduleData["error"] ?? json_encode($moduleData));
            }
        }
    }
}
