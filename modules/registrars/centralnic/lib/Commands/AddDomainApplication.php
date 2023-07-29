<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class AddDomainApplication extends AbstractCommand
{
    protected $command = "AddDomainApplication";

    public function sslRequirementAccepted($self, $accept)
    {
        $this->setParam("X-ACCEPT-SSL-REQUIREMENT", (int) $accept);
        return $this;
    }
}
