<?php

namespace WHMCS\Billing\Payment\Transaction;

class Information implements \Illuminate\Contracts\Support\Arrayable
{
    protected $transactionId = "";
    protected $amount = 0;
    protected $type = NULL;
    protected $currency = NULL;
    protected $description = NULL;
    protected $fee = 0;
    protected $status = NULL;
    protected $exchangeRate = NULL;
    protected $created = NULL;
    protected $availableOn = NULL;
    protected $additionalData = [];

    public function setTransactionId($self, $transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setAmount($self, $amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function setType($self, $type)
    {
        $this->type = $type;
        return $this;
    }

    public function setCurrency($self, $currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function setDescription($self, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function setFee($self, $fee)
    {
        $this->fee = $fee;
        return $this;
    }

    public function setStatus($self, $status)
    {
        $this->status = $status;
        return $this;
    }

    public function setExchangeRate($Information, $exchangeRate)
    {
        $this->exchangeRate = $exchangeRate;
        return $this;
    }

    public function setCreated($self, $created)
    {
        $this->created = $created;
        return $this;
    }

    public function setAvailableOn($self, $availableOn)
    {
        $this->availableOn = $availableOn;
        return $this;
    }

    public function setAdditionalData($self, $additionalData)
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    public function setAdditionalDatum($self, $key, $value)
    {
        $this->additionalData[$key] = $value;
        return $this;
    }

    public function toArray()
    {
        $return = ["transactionId" => $this->getTransactionId(), "amount" => formatCurrency($this->getAmount())->toNumeric(), "type" => $this->getType(), "currency" => $this->getCurrency(), "description" => $this->getDescription(), "fee" => formatCurrency($this->getFee())->toNumeric(), "status" => $this->getStatus(), "exchangeRate" => $this->getExchangeRate(), "created" => $this->getCreated(), "availableOn" => $this->getAvailableOn()];
        return array_merge($return, $this->getAdditionalData());
    }

    protected function getTransactionId()
    {
        return $this->transactionId;
    }

    protected function getAmount()
    {
        return $this->amount;
    }

    protected function getType()
    {
        return $this->type;
    }

    protected function getCurrency()
    {
        return $this->currency;
    }

    protected function getDescription()
    {
        return $this->description;
    }

    protected function getStatus()
    {
        return $this->status;
    }

    protected function getExchangeRate()
    {
        return $this->exchangeRate;
    }

    protected function getCreated()
    {
        return $this->created ? $this->created->toAdminDateTimeFormat() : NULL;
    }

    protected function getAvailableOn()
    {
        return $this->availableOn ? $this->availableOn->toAdminDateTimeFormat() : NULL;
    }

    protected function getFee()
    {
        return $this->fee;
    }

    protected function getAdditionalData()
    {
        return $this->additionalData;
    }
}
