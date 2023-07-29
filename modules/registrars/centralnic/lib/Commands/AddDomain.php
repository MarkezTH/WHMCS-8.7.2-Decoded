<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class AddDomain extends AbstractCommand
{
    protected $command = "AddDomain";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain, int $period, $transferLock, $whoisPrivacy, $ownerContact, $adminContact, $techContact, $billingContact)
    {
        $this->setParam("domain", $domain)->setParam("period", $period)->setParam("transferlock", (int) $transferLock)->setParam("X-WHOISPRIVACY", (int) $whoisPrivacy)->setParam("ownercontact0", $ownerContact)->setParam("admincontact0", $adminContact)->setParam("techcontact0", $techContact)->setParam("billingcontact0", $billingContact);
        parent::__construct($api);
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
        $this->setParam("x-fee-amount", $amount);
        return $this;
    }

    public function setIDNLanguageTag($languageTag)
    {
        $this->setParam("X-IDN-LANGUAGE", $languageTag);
        return $this;
    }

    public function setRenewalMode($mode)
    {
        $this->setParam("RENEWALMODE", $mode);
        return $this;
    }

    public function setTransferMode($mode)
    {
        $this->setParam("TRANSFERMODE", $mode);
        return $this;
    }
}
