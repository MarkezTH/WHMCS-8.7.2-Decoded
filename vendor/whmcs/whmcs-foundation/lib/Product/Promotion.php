<?php

namespace WHMCS\Product;

class Promotion extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblpromotions";
    public $timestamps = false;
    const TYPE_PERCENTAGE = "Percentage";
    const TYPE_FREE_SETUP = "Free Setup";
    const TYPE_PRICE_OVERRIDE = "Price Override";
    const TYPE_FIXED_AMOUNT = "Fixed Amount";

    public static function getApplicableToObject($object)
    {
        $allowAnyCode = checkPermission("Use Any Promotion Code on Order", true);
        $promos = self::all()->sortBy("code", SORT_NATURAL);
        $result = ["promos.activepromos" => [], "promos.expiredpromos" => [], "promos.allpromos" => []];
        if ($object instanceof \WHMCS\Service\Addon) {
            $lookupTarget = $object->productAddon->id;
            $preferredLookupMethod = "appliesToAddon";
        } else {
            if ($object instanceof \WHMCS\Domain\Domain) {
                $lookupTarget = $object->tld;
                $preferredLookupMethod = "appliesToDomain";
            } else {
                if ($object instanceof \WHMCS\Service\Service) {
                    $lookupTarget = $object->product->id;
                    $preferredLookupMethod = "appliesToService";
                } else {
                    return $result;
                }
            }
        }
        foreach ($promos as $promo) {
            if ($allowAnyCode || $promo->{$preferredLookupMethod}($lookupTarget)) {
                if ($promo->{$preferredLookupMethod}($lookupTarget)) {
                    if (!$promo->isExpired()) {
                        $result["promos.activepromos"][$promo->id] = $promo;
                    } else {
                        $result["promos.expiredpromos"][$promo->id] = $promo;
                    }
                } else {
                    $result["promos.allpromos"][$promo->id] = $promo;
                }
            }
        }
        return $result;
    }

    public static function getAllForSelect()
    {
        $promos = self::all()->sortBy("code", SORT_NATURAL);
        $result = ["promos.activepromos" => [], "promos.expiredpromos" => [], "promos.allpromos" => []];
        foreach ($promos as $promo) {
            if (!$promo->isExpired()) {
                $result["promos.activepromos"][$promo->id] = $promo;
            } else {
                $result["promos.expiredpromos"][$promo->id] = $promo;
            }
        }
        return $result;
    }

    public function appliesToService($serviceId)
    {
        $appliesto = explode(",", $this->appliesto);
        return in_array($serviceId, $appliesto);
    }

    public function appliesToAddon($addonId)
    {
        $appliesto = explode(",", $this->appliesto);
        return in_array("A" . $addonId, $appliesto);
    }

    public function appliesToDomain($domainTld)
    {
        $appliesTo = explode(",", $this->appliesto);
        return in_array("D." . $domainTld, $appliesTo);
    }

    public function isExpired()
    {
        if ($this->expirationdate === "0000-00-00") {
            return false;
        }
        try {
            $expiry = \WHMCS\Carbon::createFromFormat("Y-m-d", $this->expirationdate);
            if (!$expiry && !$expiry->isPast() && 0 < $this->maxuses && $this->uses < $this->maxuses) {
                return false;
            }
        } catch (\Exception $e) {
        }
        return true;
    }

    public function isRecurring()
    {
        return (bool) $this->recurring;
    }

    public function scopeByCode($Builder, $query, $code)
    {
        return $query->where("code", $code);
    }
}
