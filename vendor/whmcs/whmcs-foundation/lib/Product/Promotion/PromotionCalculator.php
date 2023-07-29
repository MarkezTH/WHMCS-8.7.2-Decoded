<?php

namespace WHMCS\Product\Promotion;

class PromotionCalculator
{
    private $promotion = NULL;
    private $defaultCurrency = NULL;
    private $currency = NULL;
    private $firstPayAmount = NULL;
    private $recurringAmount = NULL;
    private $setupFee = NULL;

    public function __construct(\WHMCS\Product\Promotion $promotion, \WHMCS\Billing\Currency $currency, $firstPayAmount, $recurringAmount, $setupFee)
    {
        $this->promotion = $promotion;
        $this->currency = $currency;
        $this->firstPayAmount = $firstPayAmount;
        $this->recurringAmount = $recurringAmount;
        $this->setupFee = $setupFee;
        $this->defaultCurrency = \WHMCS\Billing\Currency::defaultCurrency()->first();
    }

    public function calculate()
    {
        return ["onetimediscount" => $this->promotion->type == \WHMCS\Product\Promotion::TYPE_FREE_SETUP ? $this->setupFee : $this->calculateDiscountMath($this->firstPayAmount), "recurringdiscount" => empty($this->promotion->recurring) ? 0 : $this->calculateDiscountMath($this->recurringAmount)];
    }

    private function calculateDiscountMath($payAmount)
    {
        $discountAmount = 0;
        $amount = $this->convertPromoAmount((double) $this->promotion->value);
        if ($this->promotion->type == \WHMCS\Product\Promotion::TYPE_PERCENTAGE) {
            $discountAmount = $payAmount * $amount / 100;
        } else {
            if ($this->promotion->type == \WHMCS\Product\Promotion::TYPE_FIXED_AMOUNT) {
                if ($payAmount < $amount) {
                    $discountAmount = $payAmount;
                } else {
                    $discountAmount = $amount;
                }
            } else {
                if ($this->promotion->type == \WHMCS\Product\Promotion::TYPE_PRICE_OVERRIDE) {
                    $discountAmount = $payAmount - $amount;
                }
            }
        }
        return round($discountAmount, 2);
    }

    private function convertPromoAmount($amount)
    {
        if ($this->promotion->type != \WHMCS\Product\Promotion::TYPE_PERCENTAGE) {
            $amount = $this->defaultCurrency->convertTo($amount, $this->currency);
        }
        return $amount;
    }
}
