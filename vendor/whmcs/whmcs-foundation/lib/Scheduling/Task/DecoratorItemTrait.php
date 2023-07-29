<?php

namespace WHMCS\Scheduling\Task;

trait DecoratorItemTrait
{
    protected $icon = "fas fa-cube";
    protected $successCountIdentifier = 0;
    protected $successKeyword = "Completed";
    protected $failureCountIdentifier = 0;
    protected $failureKeyword = "Failed";
    protected $isBooleanStatus = false;
    protected $hasDetail = false;

    public function getIcon()
    {
        return $this->icon;
    }

    public function getSuccessCountIdentifier()
    {
        return $this->successCountIdentifier;
    }

    public function getFailureCountIdentifier()
    {
        return $this->failureCountIdentifier;
    }

    public function getSuccessKeyword()
    {
        return $this->successKeyword;
    }

    public function getFailureKeyword()
    {
        return $this->failureKeyword;
    }

    public function isBooleanStatusItem()
    {
        return (bool) $this->isBooleanStatus;
    }

    public function hasDetail()
    {
        return (bool) $this->hasDetail;
    }
}
