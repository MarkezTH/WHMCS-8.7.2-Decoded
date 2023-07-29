<?php

namespace WHMCS\Hook;

class PublicRegistry
{
    private $manager = NULL;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function add($hookName, $priority, $hookFunction = "", $rollbackFunction)
    {
        $this->manager->add($hookName, $priority, $hookFunction, $rollbackFunction);
    }

    public function log($hookName, $msg, ...$inputs)
    {
        $this->manager->log($hookName, $msg, ...$inputs);
    }
}
