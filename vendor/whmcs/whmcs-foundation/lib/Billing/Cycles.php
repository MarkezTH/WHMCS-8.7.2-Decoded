<?php

namespace WHMCS\Billing;

class Cycles
{
    protected $nonRecurringCycles = NULL;
    protected $recurringCycles = ["monthly" => "Monthly", "quarterly" => "Quarterly", "semiannually" => "Semi-Annually", "annually" => "Annually", "biennially" => "Biennially", "triennially" => "Triennially"];
    protected $monthsToCyclesMap = NULL;
    protected $storedFreeCycles = NULL;
    const CYCLE_FREE = "free";
    const CYCLE_ONETIME = "onetime";
    const CYCLE_MONTHLY = "monthly";
    const CYCLE_QUARTERLY = "quarterly";
    const CYCLE_SEMI_ANNUALLY = "semiannually";
    const CYCLE_ANNUALLY = "annually";
    const CYCLE_BIENNIALLY = "biennially";
    const CYCLE_TRIENNIALLY = "triennially";
    const DISPLAY_FREE = "Free Account";
    const DISPLAY_ONETIME = "One Time";

    public function getSystemBillingCycles($excludeNonRecurring = false)
    {
        if ($excludeNonRecurring) {
            $allCycles = $this->getRecurringCycles();
        } else {
            $allCycles = array_merge($this->nonRecurringCycles, $this->getRecurringCycles());
        }
        $cycles = [];
        foreach ($allCycles as $k => $v) {
            $cycles[] = $k;
        }
        return $cycles;
    }

    public function getRecurringSystemBillingCycles()
    {
        return $this->getSystemBillingCycles(true);
    }

    public function isValidSystemBillingCycle($cycle)
    {
        return in_array($cycle, $this->getSystemBillingCycles());
    }

    public function isValidPublicBillingCycle($cycle)
    {
        return in_array($cycle, $this->getPublicBillingCycles());
    }

    public function getPublicBillingCycles()
    {
        $allCycles = array_merge($this->nonRecurringCycles, $this->getRecurringCycles());
        $cycles = [];
        foreach ($allCycles as $k => $v) {
            $cycles[] = $v;
        }
        return $cycles;
    }

    public function getRecurringCycles()
    {
        return $this->recurringCycles;
    }

    public function getPublicBillingCycle($cycle)
    {
        $allCycles = array_merge($this->nonRecurringCycles, $this->getRecurringCycles());
        return array_key_exists($cycle, $allCycles) ? $allCycles[$cycle] : "";
    }

    public function getNormalisedBillingCycle($cycle)
    {
        if (is_null($cycle)) {
            return "";
        }
        $cycle = strtolower($cycle);
        $cycle = preg_replace("/[^a-z]/i", "", $cycle);
        if ($cycle === "freeaccount" || $this->isFree($cycle)) {
            $cycle = self::CYCLE_FREE;
        }
        return $this->isValidSystemBillingCycle($cycle) ? $cycle : "";
    }

    public function getNameByMonths($months)
    {
        return isset($this->monthsToCyclesMap[$months]) ? $this->monthsToCyclesMap[$months] : "";
    }

    public function getNormalisedByMonths($months)
    {
        $humanName = $this->getNameByMonths($months);
        if ($humanName == "") {
            return "";
        }
        $cycleKey = array_search($humanName, $this->recurringCycles);
        if ($cycleKey === false) {
            return "";
        }
        return $cycleKey;
    }

    public function getNumberOfMonths($cycle)
    {
        $cycles = array_flip($this->monthsToCyclesMap);
        if (array_key_exists($cycle, $cycles)) {
            return $cycles[$cycle];
        }
        $normalisedCycle = $this->getNormalisedBillingCycle($cycle);
        $cycle = $this->getPublicBillingCycle($normalisedCycle);
        if (array_key_exists($cycle, $cycles)) {
            return $cycles[$cycle];
        }
        throw new \WHMCS\Exception("Invalid billing cycle provided");
    }

    public function isRecurring($cycle)
    {
        $recurringCycles = $this->getRecurringCycles();
        if (in_array($cycle, $recurringCycles) || array_key_exists($cycle, $recurringCycles)) {
            return true;
        }
        return false;
    }

    public function isFree($cycle)
    {
        return in_array(strtolower($cycle), array_map("strtolower", $this->getStoredFreeCycles()));
    }

    public function getStoredFreeCycles()
    {
        return $this->storedFreeCycles;
    }

    public function translate($cycle)
    {
        return \Lang::trans("orderpaymentterm" . $this->getNormalisedBillingCycle($cycle));
    }

    public function getGreaterCycles($cycle)
    {
        $currentCycleMonths = $this->getNumberOfMonths($cycle);
        $cyclesToReturn = [];
        foreach ($this->monthsToCyclesMap as $numMonths => $displayLabel) {
            if ($currentCycleMonths <= $numMonths && $numMonths != 100) {
                $cyclesToReturn[] = $this->getNormalisedBillingCycle($displayLabel);
            }
        }
        return $cyclesToReturn;
    }
}
