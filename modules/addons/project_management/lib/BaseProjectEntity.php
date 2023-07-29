<?php

namespace WHMCS\Module\Addon\ProjectManagement;

abstract class BaseProjectEntity
{
    /**
     * @var Project $project
     */
    public $project = NULL;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function project()
    {
        return $this->project;
    }
}
