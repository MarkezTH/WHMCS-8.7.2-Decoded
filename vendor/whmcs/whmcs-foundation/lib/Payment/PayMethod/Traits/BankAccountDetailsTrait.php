<?php

namespace WHMCS\Payment\PayMethod\Traits;

trait BankAccountDetailsTrait
{
    use SensitiveDataTrait;

    public function getSensitiveDataAttributeName()
    {
        return "bank_data";
    }

    public function getRoutingNumber()
    {
        return (string) $this->getSensitiveProperty("routingNumber");
    }

    public function setRoutingNumber($value)
    {
        $this->setSensitiveProperty("routingNumber", $value);
        return $this;
    }

    public function getAccountNumber()
    {
        return (string) $this->getSensitiveProperty("accountNumber");
    }

    public function getMaskedAccountNumber()
    {
        $masked = "";
        $accountNumber = $this->getAccountNumber();
        if ($accountNumber) {
            $accountLength = strlen($accountNumber);
            $accountLastFour = substr($accountNumber, -4);
            $masked = str_pad($accountLastFour, $accountLength, "*", STR_PAD_LEFT);
        }
        return $masked;
    }

    public function setAccountNumber($value)
    {
        $this->setSensitiveProperty("accountNumber", $value);
        return $this;
    }

    public function getAccountType()
    {
        return (string) $this->acct_type;
    }

    public function setAccountType($value)
    {
        $this->acct_type = $value;
        return $this;
    }

    public function getBankName()
    {
        return (string) $this->bank_name;
    }

    public function setBankName($value)
    {
        $this->bank_name = $value;
        return $this;
    }

    public function getAccountHolderName()
    {
        return (string) $this->getSensitiveProperty("accountHolderName");
    }

    public function setAccountHolderName($value)
    {
        $this->setSensitiveProperty("accountHolderName", $value);
        return $this;
    }

    public function validateRequiredValuesPreSave()
    {
        if (!$this->getAccountHolderName()) {
            throw new \WHMCS\Exception("Account holder name is required");
        }
        if (!$this->getRoutingNumber()) {
            throw new \WHMCS\Exception("Routing number is required");
        }
        if (!$this->getAccountNumber()) {
            throw new \WHMCS\Exception("Account number is required");
        }
        return $this;
    }

    public function getDisplayName()
    {
        $bankName = $this->getBankName();
        if (!$bankName) {
            $bankName = "Bank Account";
        }
        return implode("-", [$bankName, substr($this->getAccountNumber(), -4)]);
    }

    public function setMigrated()
    {
        $this->setSensitiveProperty("migrated", 1);
        return $this;
    }

    public function isMigrated()
    {
        return (bool) (int) $this->getSensitiveProperty("migrated");
    }
}
