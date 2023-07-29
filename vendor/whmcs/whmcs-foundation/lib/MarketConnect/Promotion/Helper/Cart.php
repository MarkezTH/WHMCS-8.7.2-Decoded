<?php

namespace WHMCS\MarketConnect\Promotion\Helper;

class Cart
{
    protected $productTypes = NULL;
    protected $marketConnectProductKeys = NULL;

    public function getProductTypes()
    {
        if (is_null($this->productTypes)) {
            $productTypesMap = \WHMCS\Product\Product::pluck("type", "id");
            $orderFrm = new \WHMCS\OrderForm();
            $cartProducts = collect($orderFrm->getCartDataByKey("products"));
            $cartProducts = $cartProducts->pluck("pid");
            $this->productTypes = [];
            foreach ($cartProducts as $pid) {
                if (!is_null($pid)) {
                    $this->productTypes[] = $productTypesMap[$pid];
                }
            }
        }
        return $this->productTypes;
    }

    public function hasProductTypes($types)
    {
        foreach ($types as $type) {
            if (in_array($type, $this->getProductTypes())) {
                return true;
            }
        }
        return false;
    }

    public function getMarketConnectProductKeys()
    {
        if (is_null($this->marketConnectProductKeys)) {
            $orderFrm = new \WHMCS\OrderForm();
            $cartProducts = collect($orderFrm->getCartDataByKey("products"));
            $cartProductAddons = [];
            foreach ($cartProducts->pluck("addons") as $addonEntry) {
                if (is_iterable($addonEntry)) {
                    foreach ($addonEntry as $addonData) {
                        if (is_array($addonData) && isset($addonData["addonid"])) {
                            $cartProductAddons[] = $addonData["addonid"];
                        } else {
                            if (is_numeric($addonData)) {
                                $cartProductAddons[] = $addonData;
                            }
                        }
                    }
                }
            }
            $cartProductAddons = collect($cartProductAddons);
            $cartProducts = $cartProducts->pluck("pid");
            $productProductKeys = collect();
            if (0 < $cartProducts->count()) {
                $productProductKeys = \WHMCS\Product\Product::where("servertype", "marketconnect")->whereIn("id", $cartProducts)->pluck("configoption1");
            }
            $cartAddons = collect($orderFrm->getCartDataByKey("addons") ?: []);
            $cartAddons = $cartAddons->pluck("id");
            $cartAddons = $cartAddons->merge($cartProductAddons);
            $addonProductKeys = collect();
            if (0 < $cartAddons->count()) {
                $addonProductKeys = \WHMCS\Config\Module\ModuleConfiguration::where("entity_type", "addon")->whereIn("entity_id", $cartAddons)->where("setting_name", "configoption1")->pluck("value");
            }
            $this->marketConnectProductKeys = $productProductKeys->merge($addonProductKeys);
        }
        return $this->marketConnectProductKeys;
    }

    public function hasMarketConnectProductKeys($productKeys)
    {
        foreach ($this->getMarketConnectProductKeys() as $key) {
            if (in_array($key, $productKeys)) {
                return true;
            }
        }
        return false;
    }

    public function isUpSellForAddon($addonId, $newAddonId)
    {
        $addonIds = $addonProductKeys = \WHMCS\Config\Module\ModuleConfiguration::where("entity_type", "addon")->whereIn("entity_id", [$addonId, $newAddonId])->where("setting_name", "configoption1")->pluck("value");
        $firstType = explode("_", $addonIds[0]);
        $secondType = explode("_", $addonIds[1]);
        if ($firstType[0] == $secondType[0] || in_array($firstType[0], \WHMCS\MarketConnect\Services\Symantec::SSL_TYPES) && in_array($secondType[0], \WHMCS\MarketConnect\Services\Symantec::SSL_TYPES)) {
            return true;
        }
        return false;
    }

    public function getCartItemForUpsell($productKeys)
    {
        $orderFrm = new \WHMCS\OrderForm();
        $cartProducts = collect($orderFrm->getCartDataByKey("products"));
        foreach ($cartProducts as $product) {
            $productKey = \WHMCS\Product\Product::where("servertype", "marketconnect")->where("id", $product["pid"])->pluck("configoption1");
            if (in_array($productKey, $productKeys)) {
                return array_merge(["type" => "product", "productKey" => $productKey], $product);
            }
        }
        $cartProductAddons = $cartProducts->pluck("addons")->flatten(1);
        foreach ($cartProductAddons as $addon) {
            if (!empty($addon["addonid"])) {
                $productKey = \WHMCS\Config\Module\ModuleConfiguration::where("entity_type", "addon")->where("entity_id", $addon["addonid"])->where("setting_name", "configoption1")->pluck("value")->first();
                if (in_array($productKey, $productKeys)) {
                    return array_merge(["type" => "addon", "productKey" => $productKey], $addon);
                }
            }
        }
        $cartAddons = $orderFrm->getCartDataByKey("addons", []);
        foreach ($cartAddons as $addon) {
            $productKey = \WHMCS\Config\Module\ModuleConfiguration::where("entity_type", "addon")->where("entity_id", $addon["id"])->where("setting_name", "configoption1")->pluck("value")->first();
            if (in_array($productKey, $productKeys)) {
                return array_merge(["type" => "addon", "productKey" => $productKey], $addon);
            }
        }
        return NULL;
    }
}
