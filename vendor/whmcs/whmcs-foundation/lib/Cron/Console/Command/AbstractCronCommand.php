<?php

namespace WHMCS\Cron\Console\Command;

abstract class AbstractCronCommand extends \Symfony\Component\Console\Command\Command
{
    protected $io = NULL;
    protected $incompleteTasks = 0;

    protected function initialize(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->io = new \WHMCS\Cron\Console\Style\TaskStyle($input, $output);
    }
    public abstract function getInputBasedCollection(\Symfony\Component\Console\Input\InputInterface $input);

    protected function getPreparedTaskCollection()
    {
        $this->io->preparingQueue();
        $taskCollector = $this->getHelper("task-collection");
        $tasks = $this->getInputBasedCollection($this->io->getInput());
        if ($this->io->hasForceOption()) {
            $taskCollector->updateStatusPreRun($tasks, false);
        } else {
            $tasks = $taskCollector->filterTasksForToday($tasks);
            if ($this->getName() == "all" || $this->getName() == "skip") {
                $dailyCronHelper = $this->getHelper("daily-cron");
                if ($dailyCronHelper->isDailyCronInvocation()) {
                    $tasks = $taskCollector->filterTasksForDailyCron($tasks);
                } else {
                    $tasks = $taskCollector->filterShouldRun($tasks);
                }
            } else {
                $tasks = $taskCollector->filterShouldRun($tasks);
            }
            $taskCollector->updateStatusPreRun($tasks);
        }
        $this->io->queueReady();
        return $tasks;
    }

    protected function startUp()
    {
        if ($this->io->isVerbose()) {
            $this->io->title($this->getApplication()->getName() . ": " . $this->getName());
        }
        return $this;
    }

    protected function tearDown()
    {
        run_hook("AfterCronJob", []);
        if ($this->io->isVerbose()) {
            if ($this->incompleteTasks) {
                $this->io->warning($this->incompleteTasks . " tasks failed to complete");
            } else {
                $this->io->success("Completed");
            }
        }
        return $this;
    }

    protected function beforeExecution()
    {
        $cronStatus = new \WHMCS\Cron\Status();
        $cronStatus->setCronInvocationTime();
        if ($this->io->isDebug() && \WHMCS\Config\Setting::getValue("DisableEmailSending")) {
            $this->io->warning("Email sending is disabled which means no outgoing mail will be delivered. You can re-enable email in Configuration > General Settings > Mail.");
        }
        return $this;
    }

    protected function afterExecution()
    {
        $this->executeSystemQueue();
        if ($this->io->isDebug() && \WHMCS\Config\Setting::getValue("DisableEmailSending")) {
            $this->io->warning("Email sending is disabled which means no outgoing mail will be delivered. You can re-enable email in Configuration > General Settings > Mail.");
        }
        return $this;
    }

    protected function getSystemQueue()
    {
        return new \WHMCS\Scheduling\Task\Collection([]);
    }

    protected function executeSystemQueue()
    {
        $tasks = $this->getSystemQueue();
        $totalQueuedTasks = $tasks->count();
        $this->io->startQueue($totalQueuedTasks, "System");
        if ($totalQueuedTasks) {
            $tasks = $tasks->sortBy(function ($task) {
                return $task->getPriority();
            });
            foreach ($tasks as $task) {
                try {
                    $task->run();
                } catch (\Exception $e) {
                    $this->io->errorException($e);
                } finally {
                    $this->io->advanceQueue();
                }
            }
        }
        \WHMCS\Log\ErrorLog::prune();
        $helper = $this->getHelper("daily-cron");
        if (!$helper->isDailyCronRunningOnTime()) {
            $helper->sendDailyNotificationDailyCronNotExecuting();
        }
        if ($totalQueuedTasks) {
            $this->io->endQueue();
        }
        return $this;
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        $this->startUp()->beforeExecution()->executeCollection($this->getPreparedTaskCollection())->afterExecution()->tearDown();
        return 0;
    }

    protected function executeCollection(\WHMCS\Scheduling\Task\Collection $tasks)
    {
        $totalQueuedTasks = $tasks->count();
        $this->io->startQueue($totalQueuedTasks, "Application");
        if ($totalQueuedTasks) {
            $tasks = $tasks->sortBy(function ($task) {
                return $task->getPriority();
            });
            foreach ($tasks as $task) {
                $completed = true;
                if (60 < $task->getFrequencyMinutes() || $this->io->isDebug()) {
                    logActivity("Automated Task: Starting " . $task->getName());
                }
                try {
                    run_hook("PreAutomationTask", ["task" => $task], true);
                    $this->io->describeTask($task);
                    $task->run();
                } catch (\Exception $e) {
                    $this->incompleteTasks++;
                    $this->io->errorException($e);
                } finally {
                    $this->io->advanceQueue();
                    $task->getStatus()->setInProgress(false);
                    try {
                        run_hook("PostAutomationTask", ["task" => $task, "completed" => $completed], true);
                    } catch (\Exception $e) {
                    }
                }
            }
            $this->io->endQueue();
        }
        return $this;
    }
}
