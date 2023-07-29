<?php

namespace WHMCS\Cart\Item;

interface ItemInterface
{
    public function getUuid();

    public function setId($id);

    public function getId();

    public function setName($name);

    public function getName();

    public function setBillingCycle($billingCycle);

    public function getBillingCycle();

    public function setBillingPeriod($billingPeriod);

    public function getBillingPeriod();

    public function setQuantity(int $qty);

    public function getQuantity();

    public function setAmount(\WHMCS\View\Formatter\Price $amount);

    public function getAmount();

    public function setRecurringAmount(\WHMCS\View\Formatter\Price $recurring);

    public function getRecurringAmount();

    public function setTaxed($taxed);

    public function isTaxed();

    public function setInitialPeriod($period, $cycle);

    public function hasInitialPeriod();

    public function isRecurring();

    public function getType();
}
