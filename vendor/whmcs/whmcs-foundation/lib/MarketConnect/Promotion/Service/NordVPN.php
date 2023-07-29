<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class NordVPN extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_NORDVPN;
    protected $friendlyName = "NordVPN";
    protected $primaryIcon = "assets/img/marketconnect/nordvpn/logo.png";
    protected $promosRequireQualifyingProducts = false;
    protected $requiresDomain = false;
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $promotionalContent = NULL;
    protected $planFeatures = NULL;
    const NORDVPN_STANDARD = NULL;

    public function getPlanFeatures($key)
    {
        return isset($this->planFeatures[$key]) ? $this->planFeatures[$key] : [];
    }
}
