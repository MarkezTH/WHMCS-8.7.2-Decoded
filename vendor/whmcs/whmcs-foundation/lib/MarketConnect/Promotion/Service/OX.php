<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class OX extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_OX;
    protected $friendlyName = "Open-Xchange";
    protected $primaryIcon = "assets/img/marketconnect/ox/logo.png";
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $recommendedUpgradePaths = NULL;
    protected $upsells = NULL;
    protected $loginPanel = ["label" => "store.ox.appSuite", "icon" => "fa-envelope", "image" => "assets/img/marketconnect/ox/display-email.png", "color" => "blue", "dropdownReplacementText" => ""];
    protected $upsellPromoContent = NULL;
    protected $promotionalContent = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $planFeatures = NULL;
    const OX_EMAIL = NULL;
    const OX_PRODUCTIVITY = NULL;

    public function getPlanFeatures($key)
    {
        $return = [];
        if (isset($this->planFeatures[$key])) {
            foreach ($this->planFeatures[$key] as $stringToTranslate => $value) {
                $return[\Lang::trans("store.ox.pricing.features." . $stringToTranslate)] = $value;
            }
            return $return;
        } else {
            return $return;
        }
    }

    public function getLoginPanel()
    {
        $services = (new \WHMCS\MarketConnect\Promotion\Helper\Client(\Auth::client()->id))->getServices($this->name);
        return (new \WHMCS\MarketConnect\Promotion\ManagePanel())->setName(ucfirst($this->name) . "Login")->setLabel(\Lang::trans($this->loginPanel["label"]))->setIcon($this->loginPanel["icon"])->setColor($this->loginPanel["color"])->setImage($this->loginPanel["image"])->setRequiresDomain($this->requiresDomain())->setDropdownReplacementText(\Lang::trans($this->loginPanel["dropdownReplacementText"]))->setPoweredBy($this->friendlyName)->setServices($services);
    }
}
