<?php

namespace WHMCS\Billing;

interface PricingInterface
{
    const TYPE_PRODUCT = "product";
    const TYPE_ADDON = "addon";
    const TYPE_CONFIGOPTION = "configoptions";
    const TYPE_DOMAIN_REGISTER = "domainregister";
    const TYPE_DOMAIN_TRANSFER = "domaintransfer";
    const TYPE_DOMAIN_RENEW = "domainrenew";
    const TYPE_DOMAIN_ADDON = "domainaddons";
    const TYPE_USAGE = "usage";

    public function pricingType();
}
