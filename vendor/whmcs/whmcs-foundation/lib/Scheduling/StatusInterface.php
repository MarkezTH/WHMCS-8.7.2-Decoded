<?php

namespace WHMCS\Scheduling;

interface StatusInterface
{
    public function isInProgress();

    public function isDueNow();

    public function calculateAndSetNextDue();

    public function setNextDue(\WHMCS\Carbon $nextDue);

    public function setInProgress($state);

    public function getLastRuntime();

    public function setLastRuntime(\WHMCS\Carbon $date);

    public function getNextDue();
}
