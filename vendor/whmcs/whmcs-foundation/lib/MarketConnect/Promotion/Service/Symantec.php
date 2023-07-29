<?php

namespace WHMCS\MarketConnect\Promotion\Service;

class Symantec extends AbstractService
{
    protected $name = \WHMCS\MarketConnect\MarketConnect::SERVICE_SYMANTEC;
    protected $friendlyName = "SSL";
    protected $primaryIcon = "assets/img/marketconnect/symantec/ssl.png";
    protected $supportsUpgrades = false;
    protected $qualifyingProductTypes = NULL;
    protected $productKeys = NULL;
    protected $sslTypes = NULL;
    protected $typesByBrand = NULL;
    protected $certificateFeatures = NULL;
    protected $upsells = NULL;
    protected $defaultPromotionalContent = NULL;
    protected $promotionalContent = NULL;
    protected $upsellPromoContent = NULL;
    const SSL_RAPIDSSL = "rapidssl_rapidssl";
    const SSL_WILDCARD = "rapidssl_wildcard";
    const SSL_QUICKSSL = "geotrust_quickssl";
    const SSL_QUICKSSLPREMIUM = "geotrust_quicksslpremium";
    const SSL_TRUEBIZ = "geotrust_truebizid";
    const SSL_TRUEBIZEV = "geotrust_truebizidev";
    const SSL_QUICKSSLWILDCARD = "geotrust_quicksslpremiumwildcard";
    const SSL_TRUEBIZWILDCARD = "geotrust_truebizidwildcard";
    const SSL_SECURESITE = "digicert_securesite";
    const SSL_SECURESITEPRO = "digicert_securesitepro";
    const SSL_SECURESITEEV = "digicert_securesiteev";
    const SSL_SECURESITEPROEV = "digicert_securesiteproev";
    const SSL_TYPE_DV = "dv";
    const SSL_TYPE_EV = "ev";
    const SSL_TYPE_OV = "ov";
    const SSL_TYPE_WILDCARD = "wildcard";
    const SSL_TYPE_RAPIDSSL = "rapidssl";
    const SSL_TYPE_GEOTRUST = "geotrust";
    const SSL_TYPE_DIGICERT = "digicert";

    public function getSslTypes($byBrand = false)
    {
        return $byBrand ? $this->typesByBrand : $this->sslTypes;
    }

    public function getCertificateFeatures()
    {
        $returnData = $this->certificateFeatures;
        $langKey = "store.ssl.features.";
        $translatableStrings = ["displayName", "validation", "issuance", "for", "seal"];
        foreach ($returnData as $systemName => $attributes) {
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, $translatableStrings)) {
                    $key = $langKey . $systemName . "." . $attribute;
                    $returnData[$systemName][$attribute] = $this->langStringOrFallback($key, $value);
                }
            }
        }
        return $returnData;
    }
}
