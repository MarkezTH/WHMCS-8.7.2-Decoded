<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class TransferDomain extends AbstractCommand
{
    protected $command = "TransferDomain";
    const TRANSFER_REQUEST = "REQUEST";
    const TRANSFER_APPROVE = "APPROVE";
    const TRANSFER_DENY = "DENY";
    const TRANSFER_CANCEL = "CANCEL";
    const TRANSFER_USERTRANSFER = "USERTRANSFER";
    const TRANSFER_PUSH = "PUSH";
    const TRANSFER_TRADE = "TRADE";
    const TRANSFER_STATUSES = NULL;

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $type, $domain)
    {
        if (!in_array($type, self::TRANSFER_STATUSES)) {
            throw new \Exception("Invalid Domain Transfer Request type.");
        }
        $this->setParam("action", $type);
        $this->setParam("domain", $domain);
        parent::__construct($api);
    }

    public function setPeriod($years)
    {
        $this->setParam("period", $years);
        return $this;
    }

    public function setEppCode($epp)
    {
        $this->setParam("auth", $epp);
        return $this;
    }

    public function suppressContactTransferError($suppress)
    {
        $this->setParam("FORCEREQUEST", (int) $suppress);
        return $this;
    }

    public function transferLock($lock)
    {
        $this->setParam("TRANSFERLOCK", (int) $lock);
        return $this;
    }

    public function setOwnerContact($handle)
    {
        $this->setParam("ownercontact0", $handle);
        return $this;
    }

    public function setAdminContact($handle)
    {
        $this->setParam("admincontact0", $handle);
        return $this;
    }

    public function setBillingContact($handle)
    {
        $this->setParam("billingcontact0", $handle);
        return $this;
    }

    public function setTechContact($handle)
    {
        $this->setParam("techcontact0", $handle);
        return $this;
    }

    public function setNameServer($nameServer, int $index)
    {
        $this->setParam("nameserver" . $index, $nameServer);
        return $this;
    }

    public function setNameServers(...$nameservers)
    {
        foreach ($nameservers as $index => $ns) {
            $this->setNameServer($ns, $index);
        }
        return $this;
    }

    public function setPremiumAmount($amount)
    {
        $this->setParam("x-fee-amount0", $amount);
        return $this;
    }
}
