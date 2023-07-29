<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class SpamExperts extends AbstractService
{
    protected $name = self::PREFIX;
    protected $friendlyName = "SpamExperts";
    protected $primaryIcon = "assets/img/marketconnect/spamexperts/logo.png";
    protected $singleServices = NULL;
    protected $productKeys = self::SPAMEXPERTS_ALL;
    protected $qualifyingProductTypes = NULL;
    protected $loginPanel = ["label" => "marketConnect.emailServices.manageEmail", "icon" => "fas fa-envelope-open", "image" => "assets/img/marketconnect/spamexperts/logo.png", "color" => "teal", "dropdownReplacementText" => ""];
    protected $upsells = NULL;
    protected $upsellPromoContent = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $planFeatures = NULL;
    protected $recommendedUpgradePaths = NULL;
    const PREFIX = \WHMCS\MarketConnect\MarketConnect::SERVICE_SPAMEXPERTS;
    const PACKAGE_IN = "incoming";
    const PACKAGE_OUT = "outgoing";
    const PACKAGE_INOUT = "incomingoutgoing";
    const PACKAGE_INARCHIVING = "incomingarchiving";
    const PACKAGE_OUTARCHIVING = "outgoingarchiving";
    const PACKAGE_INOUTARCHIVING = "incomingoutgoingarchiving";
    const SPAMEXPERTS_IN = NULL;
    const SPAMEXPERTS_OUT = NULL;
    const SPAMEXPERTS_INOUT = NULL;
    const SPAMEXPERTS_INARCHIVING = NULL;
    const SPAMEXPERTS_OUTARCHIVING = NULL;
    const SPAMEXPERTS_INOUTARCHIVING = NULL;
    const SPAMEXPERTS_ALL = NULL;
    const SPAMEXPERTS_ALL_NO_PREFIX = NULL;

    public function getFeaturesForUpgrade($key)
    {
        if (in_array($key, $this->singleServices)) {
            return NULL;
        }
        return $this->planFeatures[$key];
    }
}
