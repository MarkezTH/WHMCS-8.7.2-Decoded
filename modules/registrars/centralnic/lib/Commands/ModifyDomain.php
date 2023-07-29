<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class ModifyDomain extends AbstractCommand
{
    protected $command = "ModifyDomain";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("domain", $domain);
        parent::__construct($api);
    }

    public function setOwnerContact($contactHandle)
    {
        $this->setParam("ownercontact0", $contactHandle);
        return $this;
    }

    public function setAdminContact($contactHandle)
    {
        $this->setParam("admincontact0", $contactHandle);
        return $this;
    }

    public function setBillingContact($contactHandle)
    {
        $this->setParam("billingcontact0", $contactHandle);
        return $this;
    }

    public function setTechContact($contactHandle)
    {
        $this->setParam("techcontact0", $contactHandle);
        return $this;
    }

    public function setTransferLock($lock)
    {
        $this->setParam("transferlock", (int) $lock);
        return $this;
    }
}
