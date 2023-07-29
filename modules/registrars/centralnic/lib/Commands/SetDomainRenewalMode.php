<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class SetDomainRenewalMode extends AbstractCommand
{
    protected $command = "SetDomainRenewalMode";
    protected $domain = "";
    protected $mode = "";
    const DEFAULT = "DEFAULT";
    const AUTO_RENEW = "AUTORENEW";
    const AUTO_EXPIRE = "AUTOEXPIRE";
    const AUTO_DELETE = "AUTODELETE";
    const RENEW_ONCE = "RENEWONCE";
    const RENEWAL_MODES = NULL;

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain, $mode)
    {
        if (!in_array($mode, self::RENEWAL_MODES)) {
            throw new \Exception("Invalid Domain Renewal mode.");
        }
        $this->domain = $domain;
        $this->mode = $mode;
        parent::__construct($api);
    }

    public function execute()
    {
        $this->setParam("domain", $this->domain);
        $this->setParam("renewalmode", $this->mode);
        return parent::execute();
    }
}
