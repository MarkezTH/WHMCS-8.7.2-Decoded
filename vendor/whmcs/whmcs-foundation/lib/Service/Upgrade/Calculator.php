<?php

namespace WHMCS\Service\Upgrade;

class Calculator
{
    protected $upgradeEntity = NULL;
    protected $upgradeTarget = NULL;
    protected $upgradeBillingCycle = NULL;
    protected $upgradeOutput = NULL;
    protected $newQuantity = 1;
    protected $minimumQuantity = 1;

    public function setUpgradeTargets($upgradeEntity, $upgradeTarget = NULL, $upgradeBillingCycle = 1, int $newQuantity = 1, int $minimumQuantity)
    {
        if ($upgradeEntity instanceof \WHMCS\Service\Service) {
            $requiredUpgradeObject = "WHMCS\\Product\\Product";
        } else {
            if ($upgradeEntity instanceof \WHMCS\Service\Addon) {
                $requiredUpgradeObject = "WHMCS\\Product\\Addon";
            } else {
                throw new \InvalidArgumentException("Invalid original model");
            }
        }
        if (!$upgradeTarget instanceof $requiredUpgradeObject) {
            throw new \InvalidArgumentException("Upgrade model must be of type: " . $requiredUpgradeObject);
        }
        $this->upgradeEntity = $upgradeEntity;
        $this->upgradeTarget = $upgradeTarget;
        $this->upgradeBillingCycle = $upgradeBillingCycle;
        $this->newQuantity = $newQuantity;
        $this->minimumQuantity = $minimumQuantity;
        return $this;
    }

    public function calculate()
    {
        $billingCycle = $this->upgradeBillingCycle;
        if (!$billingCycle) {
            $billingCycle = $this->upgradeEntity->billingCycle;
        }
        $quantity = $this->upgradeEntity->qty;
        $allowMultipleQuantities = $this->upgradeTarget->allowMultipleQuantities;
        $allowMultipleQuantities = $allowMultipleQuantities === \WHMCS\Cart\CartCalculator::QUANTITY_SCALING;
        if (!$allowMultipleQuantities && $quantity !== 1) {
            $quantity = 1;
        }
        if ($allowMultipleQuantities && $this->newQuantity) {
            $quantity = $this->newQuantity;
        }
        $userId = (int) $this->upgradeEntity->clientId;
        $currency = getCurrency($userId);
        if ($this->upgradeTarget->isFree()) {
            $newRecurringAmount = 0;
        } else {
            $pricing = $this->upgradeTarget->pricing($currency)->byCycle($billingCycle);
            if (is_null($pricing)) {
                throw new \WHMCS\Exception("Invalid billing cycle for upgrade");
            }
            $newRecurringAmount = $pricing->price()->toNumeric();
        }
        $newRecurringAmount *= $quantity;
        if ($this->upgradeEntity->isRecurring()) {
            $creditCalc = $this->calculateCredit();
        } else {
            $creditCalc = ["totalDaysInCycle" => 0, "daysRemaining" => 0, "creditAmount" => "0.00"];
        }
        $amountDueToday = $newRecurringAmount - $creditCalc["creditAmount"];
        if ($amountDueToday < 0) {
            $amountDueToday = 0;
        }
        $upgrade = new Upgrade();
        $upgrade->userId = $userId;
        $upgrade->date = \WHMCS\Carbon::now();
        $upgrade->type = $this->getUpgradeType();
        $upgrade->entityId = $this->upgradeEntity->id;
        $upgrade->originalValue = $this->getUpgradeEntityProductIdValue();
        $upgrade->newValue = $this->upgradeTarget->id;
        $upgrade->newCycle = $billingCycle;
        $upgrade->localisedNewCycle = (new \WHMCS\Billing\Cycles())->translate($billingCycle);
        $upgrade->upgradeAmount = new \WHMCS\View\Formatter\Price($amountDueToday, $currency);
        $upgrade->recurringChange = $newRecurringAmount - $this->upgradeEntity->recurringFee;
        $upgrade->newRecurringAmount = new \WHMCS\View\Formatter\Price($newRecurringAmount, $currency);
        $upgrade->creditAmount = new \WHMCS\View\Formatter\Price($creditCalc["creditAmount"], $currency);
        $upgrade->daysRemaining = $creditCalc["daysRemaining"];
        $upgrade->totalDaysInCycle = $creditCalc["totalDaysInCycle"];
        $upgrade->applyTax = $this->upgradeTarget->applyTax;
        $upgrade->allowMultipleQuantities = $allowMultipleQuantities;
        $upgrade->qty = $quantity;
        $upgrade->minimumQuantity = $this->minimumQuantity;
        return $upgrade;
    }

    protected function isServiceUpgrade()
    {
        return $this->upgradeEntity instanceof \WHMCS\Service\Service;
    }

    protected function getUpgradeType()
    {
        return $this->isServiceUpgrade() ? Upgrade::TYPE_SERVICE : Upgrade::TYPE_ADDON;
    }

    protected function getUpgradeEntityProductIdValue()
    {
        return $this->isServiceUpgrade() ? $this->upgradeEntity->packageId : $this->upgradeEntity->addonId;
    }

    protected function calculateCredit()
    {
        $daysInCurrentCycle = 0;
        $nextDueDate = $this->upgradeEntity->nextDueDate;
        $recurringAmount = $this->upgradeEntity instanceof \WHMCS\Service\Addon ? $this->upgradeEntity->recurringFee : $this->upgradeEntity->recurringAmount;
        $billingCycle = $this->upgradeEntity->billingCycle;
        if (!empty($nextDueDate) && $nextDueDate != "0000-00-00") {
            $daysInCurrentCycle = $this->calculateDaysInCurrentBillingCycle($nextDueDate, $billingCycle);
        }
        if (0 < $daysInCurrentCycle) {
            $dailyRate = $recurringAmount / $daysInCurrentCycle;
        } else {
            $dailyRate = 0;
        }
        $daysRemaining = 0 < $daysInCurrentCycle ? \WHMCS\Carbon::now()->diffInDays(\WHMCS\Carbon::parse($nextDueDate)) : 0;
        $creditAmount = format_as_currency($dailyRate * $daysRemaining);
        return ["totalDaysInCycle" => $daysInCurrentCycle, "daysRemaining" => $daysRemaining, "creditAmount" => $creditAmount];
    }

    public function calculateDaysInCurrentBillingCycle($nextDueDate, $billingCycle)
    {
        $months = (new \WHMCS\Billing\Cycles())->getNumberOfMonths($billingCycle);
        $nextDueDate = \WHMCS\Carbon::parse($nextDueDate);
        $originalDate = clone $nextDueDate;
        return $nextDueDate->subMonths($months)->diffInDays($originalDate);
    }
}
