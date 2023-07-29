<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class SitelockVPN extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_SITELOCKVPN;
    protected $friendlyName = "Sitelock VPN";
    protected $primaryIcon = "assets/img/marketconnect/sitelockvpn/logo.png";
    protected $promosRequireQualifyingProducts = false;
    protected $requiresDomain = false;
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = [];
    protected $loginPanel = ["label" => "marketConnect.sitelockvpn.manageVPN", "icon" => "fa-network-wired", "image" => "assets/img/marketconnect/sitelockvpn/logo-sml.png", "color" => "pomegranate", "dropdownReplacementText" => "sitelockvpn.loginPanelText"];
    protected $defaultPromotionalContent = NULL;
    protected $planFeatures = NULL;
    const SITELOCKVPN_STANDARD = NULL;

    public function getPlanFeatures($key)
    {
        return isset($this->planFeatures[$key]) ? $this->planFeatures[$key] : [];
    }
}
