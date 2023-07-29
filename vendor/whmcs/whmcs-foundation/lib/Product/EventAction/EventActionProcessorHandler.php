<?php

namespace WHMCS\Product\EventAction;

class EventActionProcessorHandler implements \WHMCS\Scheduling\Contract\JobInterface
{
    use \WHMCS\Scheduling\Jobs\JobTrait;
    const ENTITY_TYPE_SERVICE = "service";
    const ENTITY_TYPE_ADDON = "addon";
    const EVENT_HANDLING_MODE_ASYNC = 0;
    const EVENT_HANDLING_MODE_INLINE = 1;
    const EVENT_HANDLING_MODE_CRON = 2;

    public function handleEventForEntity($entityType, $entityIdOrModel, $eventName = false, $dryRun)
    {
        switch ($entityType) {
            case self::ENTITY_TYPE_SERVICE:
                $entityClass = "WHMCS\\Service\\Service";
                $processorClass = "WHMCS\\Product\\EventAction\\ServiceEventActionProcessor";
                break;
            case self::ENTITY_TYPE_ADDON:
                $entityClass = "WHMCS\\Service\\Addon";
                $processorClass = "WHMCS\\Product\\EventAction\\AddonEventActionProcessor";
                if (is_numeric($entityIdOrModel)) {
                    $entity = $entityClass::find($entityIdOrModel);
                    if (!$entity) {
                        throw new \WHMCS\Exception\Module\NotServicable("Invalid entity ID");
                    }
                } else {
                    if ($entityIdOrModel instanceof $entityClass) {
                        $entity = $entityIdOrModel;
                    } else {
                        throw new \WHMCS\Exception\Module\NotServicable("Entity type must be an instance of " . $entityClass);
                    }
                }
                $processor = new $processorClass($entity);
                $scheduledActions = $processor->getScheduledEventActions($eventName);
                if (!$dryRun && 0 < count($scheduledActions)) {
                    $processor->process($scheduledActions);
                }
                return 0 < count($scheduledActions);
                break;
            default:
                throw new \WHMCS\Exception\Module\NotServicable("Invalid entity type: " . $entityType);
        }
    }

    public static function getEventHandlingMode()
    {
        return (int) \WHMCS\Config\Setting::getValue("ModuleEventHandlingMode");
    }

    public function handleModuleEvent($entityType, $entityIdOrModel, $eventName)
    {
        $hasEventActions = $this->handleEventForEntity($entityType, $entityIdOrModel, $eventName, true);
        if (!$hasEventActions) {
            return NULL;
        }
        $entityId = is_numeric($entityIdOrModel) ? $entityIdOrModel : $entityIdOrModel->id ?? NULL;
        if (is_null($entityId)) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid/null entity parameter despite validation");
        }
        $eventHandlingMode = static::getEventHandlingMode();
        if ($eventHandlingMode === static::EVENT_HANDLING_MODE_ASYNC) {
            $jobMethod = "addAsync";
        } else {
            if ($eventHandlingMode === static::EVENT_HANDLING_MODE_CRON) {
                $jobMethod = "add";
            } else {
                $jobMethod = NULL;
            }
        }
        if ($jobMethod) {
            $job = \WHMCS\Scheduling\Jobs\Queue::$jobMethod("module-event." . $eventName . "." . $entityId, static::class, "handleEventForEntity", [$entityType, $entityId, $eventName]);
            if (!$job) {
                throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("Job failed to create for " . $entityType . " ID " . $entityId . ", event: " . $eventName);
            }
            if ($job->async) {
                $job->runAsync();
            }
        } else {
            $this->handleEventForEntity($entityType, $entityId, $eventName);
        }
    }
}
