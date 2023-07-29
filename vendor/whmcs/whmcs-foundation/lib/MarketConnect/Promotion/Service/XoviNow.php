<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class XoviNow extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW;
    protected $friendlyName = "XOVI NOW";
    protected $primaryIcon = "assets/img/marketconnect/xovinow/logo-sml.png";
    protected $promoteToNewClients = true;
    protected $productKeys = NULL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = ["label" => "marketConnect.xoviNow.manage", "icon" => "fa-search", "image" => "assets/img/marketconnect/xovinow/logo-sml.png", "color" => "brand-xovinow-blue", "dropdownReplacementText" => ""];
    protected $recommendedUpgradePaths = NULL;
    protected $upsells = NULL;
    protected $features = ["Projects", "Full-Access Accounts", "Read-Only Accounts", "Competitor Benchmarking", "Competitors per project", "Keyword Research", "Rank Tracker", "Keyword crawls", "Keyword check", "Site Audit", "Pages to crawl", "SEO Advisor", "SEO Text Optimizer"];
    protected $planFeatures = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $promotionalContent = NULL;
    protected $upsellPromoContent = NULL;
    const XOVINOW_STARTER = NULL;
    const XOVINOW_PROFESSIONAL = NULL;

    public function getLoginPanel()
    {
        $services = (new \WHMCS\MarketConnect\Promotion\Helper\Client(\Auth::client()->id))->getServices($this->name);
        return (new \WHMCS\MarketConnect\Promotion\LoginPanel())->setName(ucfirst($this->name) . "Login")->setLabel(\Lang::trans($this->loginPanel["label"]))->setIcon($this->loginPanel["icon"])->setColor($this->loginPanel["color"])->setImage($this->loginPanel["image"])->setRequiresDomain($this->requiresDomain())->setDropdownReplacementText(\Lang::trans($this->loginPanel["dropdownReplacementText"]))->setPoweredBy("WebPros")->setServices($services);
    }
}
