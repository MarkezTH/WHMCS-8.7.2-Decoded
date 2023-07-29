<?php

namespace Transip\Api\Library\Entity;

class Invoice extends AbstractEntity
{
    protected $invoiceNumber = NULL;
    protected $creationDate = NULL;
    protected $payDate = NULL;
    protected $dueDate = NULL;
    protected $invoiceStatus = NULL;
    protected $currency = NULL;
    protected $totalAmount = NULL;
    protected $totalAmountInclVat = NULL;

    public function getInvoiceNumber()
    {
        return $this->invoiceNumber;
    }

    public function getCreationDate()
    {
        return $this->creationDate;
    }

    public function getPayDate()
    {
        return $this->payDate;
    }

    public function getDueDate()
    {
        return $this->dueDate;
    }

    public function getInvoiceStatus()
    {
        return $this->invoiceStatus;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    public function getTotalAmountInclVat()
    {
        return $this->totalAmountInclVat;
    }
}
