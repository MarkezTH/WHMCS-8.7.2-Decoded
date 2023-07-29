<?php

namespace WHMCS\MarketConnect\Promotion\Service;

abstract class AbstractService
{
    protected $name = NULL;
    protected $friendlyName = NULL;
    protected $productKeys = [];
    protected $qualifyingProductTypes = [];
    protected $settings = [];
    protected $upsells = [];
    protected $defaultPromotionalContent = [];
    protected $promotionalContent = [];
    protected $upsellPromoContent = [];
    protected $loginPanel = NULL;
    protected $supportsUpgrades = true;
    protected $promoteToNewClients = false;
    protected $promosRequireQualifyingProducts = true;
    protected $requiresDomain = true;
    protected $planFeatures = [];
    protected $noPromotionStatuses = ["Cancelled", "Terminated", "Fraud"];

    public function getProductKeys()
    {
        return $this->productKeys;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getModel()
    {
        $className = get_class($this);
        $className = substr($className, strrpos($className, "\\") + 1);
        return \WHMCS\MarketConnect\Service::where("name", $className)->first();
    }

    public function getSettings()
    {
        return (array) $this->settings;
    }

    public function supportsUpgrades()
    {
        return (bool) $this->supportsUpgrades;
    }

    public function doPromosRequireQualifyingProducts()
    {
        return (bool) $this->promosRequireQualifyingProducts;
    }

    public function collectionContains($collection, $contains)
    {
        foreach ($contains as $containedItem) {
            if ($collection->contains($containedItem)) {
                return true;
            }
        }
        return false;
    }

    public function getBestUpsell($productKey)
    {
        if (array_key_exists($productKey, $this->upsells)) {
            $upsells = $this->upsells[$productKey];
            foreach ($upsells as $upsellProductKey) {
                $product = \WHMCS\Product\Product::productKey($upsellProductKey)->visible()->first();
                if (!is_null($product)) {
                    return $product;
                }
            }
        }
        return NULL;
    }

    public function getPromotionalContent($promotionalKey)
    {
        if (isset($this->promotionalContent[$promotionalKey])) {
            $promotionalContent = $this->promotionalContent[$promotionalKey];
        } else {
            $promotionalContent = $this->defaultPromotionalContent;
        }
        return new \WHMCS\MarketConnect\Promotion\PromotionContentWrapper($this->name, $promotionalKey, $promotionalContent);
    }

    public function getUpsellPromotionalContent($promotionalKey)
    {
        if (isset($this->upsellPromoContent[$promotionalKey])) {
            $promotionalContent = $this->upsellPromoContent[$promotionalKey];
            return new \WHMCS\MarketConnect\Promotion\PromotionContentWrapper($this->name, $promotionalKey, $promotionalContent, true);
        }
        return NULL;
    }

    public function getRecommendedProductKeyForUpgrade($productKey)
    {
        return array_key_exists($productKey, $this->recommendedUpgradePaths) ? $this->recommendedUpgradePaths[$productKey] : NULL;
    }

    protected function getAddonArray($groupedAddons, $addons, $billingCycle)
    {
        $addonsArray = [];
        $excludedAddonIds = $this->getExcludedFromNewPurchaseAddonIds();
        foreach ($groupedAddons as $addonId) {
            $addonInfo = $addons->where("id", $addonId);
            if (!is_null($addonInfo) && !in_array($addonId, $excludedAddonIds)) {
                $addonInfo = $addonInfo->first();
                if (!defined("CLIENTAREA") || empty($addonInfo["hidden"])) {
                    $name = $addonInfo["name"];
                    $name = explode("-", $name, 2);
                    $name = $name[1];
                    $addonInfo["name"] = $name;
                    if (isset($addonInfo["billingCycles"][$billingCycle])) {
                        $cycle = $billingCycle;
                        $pricing = $addonInfo["billingCycles"][$billingCycle];
                    } else {
                        $cycle = $addonInfo["minCycle"];
                        $pricing = $addonInfo["minPrice"];
                    }
                    $pricing["cycle"] = $cycle;
                    if (is_null($pricing["setup"])) {
                        $pricing["setup"] = new \WHMCS\View\Formatter\Price(0);
                    }
                    if (is_null($pricing["price"])) {
                        $pricing["price"] = new \WHMCS\View\Formatter\Price(0);
                    }
                    $price = new \WHMCS\Product\Pricing\Price($pricing);
                    $addonsArray[$addonId] = ["addon" => $addonInfo, "price" => $price];
                }
            }
        }
        return $addonsArray;
    }

    public function cartViewPromotion()
    {
        return $this->cartPromo("cart-view");
    }

    public function cartCheckoutPromotion()
    {
        if (!(new \WHMCS\OrderForm())->inExpressCheckout()) {
            return $this->cartPromo("cart-checkout");
        }
    }

    protected function cartPromo($callingLocation)
    {
        $service = $this->getModel();
        if (is_null($service) || !$service->setting("promotion." . $callingLocation)) {
            return "";
        }
        $cart = new \WHMCS\MarketConnect\Promotion\Helper\Cart();
        if (!$cart->hasProductTypes($this->qualifyingProductTypes)) {
            return "";
        }
        if ($cart->hasMarketConnectProductKeys($this->productKeys)) {
            $cartItem = $cart->getCartItemForUpsell($this->productKeys);
            if ($cartItem) {
                $upsellProduct = $this->getBestUpsell($cartItem["productKey"]);
                if (!is_null($upsellProduct)) {
                    $promotion = $this->getUpsellPromotionalContent($upsellProduct->productKey);
                    if (!is_null($promotion)) {
                        return new \WHMCS\MarketConnect\Promotion\CartPromotion($promotion, $upsellProduct, NULL, $cartItem);
                    }
                }
            }
            return "";
        }
        $product = $this->getPromotedProduct();
        if (!is_null($product)) {
            $promotion = $this->getPromotionalContent($product->productKey);
            if (!is_null($promotion)) {
                return new \WHMCS\MarketConnect\Promotion\CartPromotion($promotion, $product);
            }
        }
    }

    public function clientHasActiveServices()
    {
        $productKeys = (new \WHMCS\MarketConnect\Promotion\Helper\Client(\Auth::client()->id))->getProductAndAddonProductKeys();
        return $this->collectionContains($productKeys, $this->productKeys);
    }

    public function supportsLogin()
    {
        return !is_null($this->loginPanel);
    }

    public function getServices()
    {
        return (new \WHMCS\MarketConnect\Promotion\Helper\Client(\Auth::client()->id))->getServices($this->name);
    }

    public function getLoginPanel()
    {
        $services = $this->getServices();
        return (new \WHMCS\MarketConnect\Promotion\LoginPanel())->setName(ucfirst($this->name) . "Login")->setLabel(\Lang::trans($this->loginPanel["label"]))->setIcon($this->loginPanel["icon"])->setColor($this->loginPanel["color"])->setImage($this->loginPanel["image"])->setRequiresDomain($this->requiresDomain())->setDropdownReplacementText(\Lang::trans($this->loginPanel["dropdownReplacementText"]))->setPoweredBy($this->friendlyName)->setServices($services);
    }

    public function getPromotedProduct()
    {
        return \WHMCS\Product\Product::marketConnectProducts($this->productKeys)->visible()->orderBy("order")->first();
    }

    public function clientAreaHomeOutput()
    {
        $service = $this->getModel();
        if (is_null($service) || !$service->setting("promotion.client-home")) {
            return NULL;
        }
        $client = new \WHMCS\MarketConnect\Promotion\Helper\Client(\Auth::client()->id);
        foreach ($client->getProductsAndAddons() as $service) {
            $productKey = $service->isService() ? $service->product->productKey : $service->productAddon->productKey;
            $upsellProduct = $this->getBestUpsell($productKey);
            if (!is_null($upsellProduct)) {
                $promotion = $this->getUpsellPromotionalContent($upsellProduct->productKey);
                if (!is_null($promotion)) {
                    return new \WHMCS\MarketConnect\Promotion\UpsellPromotion($promotion, $upsellProduct, $service);
                }
            }
        }
        if ($this->doPromosRequireQualifyingProducts() && ($client->hasProductTypes($this->qualifyingProductTypes) || count($client->getProductTypes()) == 0 && $this->promoteToNewClients) || !$this->doPromosRequireQualifyingProducts()) {
            if ($this->collectionContains($client->getProductAndAddonProductKeys(), $this->productKeys)) {
                return NULL;
            }
            $promoProduct = $this->getPromotedProduct();
            if (is_null($promoProduct)) {
                return NULL;
            }
            $promotion = $this->getPromotionalContent($promoProduct->productKey);
            if (!is_null($promotion)) {
                return new \WHMCS\MarketConnect\Promotion\Promotion($promotion, $promoProduct);
            }
        } else {
            return NULL;
        }
    }

    public function clientAreaSidebars()
    {
        $primarySidebar = \Menu::primarySidebar();
        $secondarySidebar = \Menu::secondarySidebar();
        $langName = $this->getLangVar($this->name);
        if (is_null($secondarySidebar->getChild("My Services Actions")) && is_null($primarySidebar->getChild("Service Details Actions"))) {
            return NULL;
        }
        $service = $this->getModel();
        if (is_null($service) || !$service->setting("promotion.product-list")) {
            return NULL;
        }
        if (!is_null($primarySidebar->getChild("Service Details Actions"))) {
            $service = \Menu::context("service");
            $serviceHelper = new \WHMCS\MarketConnect\Promotion\Helper\Service($service);
            $addons = $serviceHelper->getProductAndAddonProductKeys();
            if ($this->collectionContains($addons, $this->productKeys)) {
                return NULL;
            }
        }
        $secondarySidebar->addChild(ucfirst($this->name) . " Sidebar Promo", ["name" => $this->name . " Sidebar Promo", "label" => \Lang::trans("store." . $langName . ".promo.sidebar.title"), "order" => 100, "icon" => "", "attributes" => ["class" => "mc-panel-promo panel-promo-" . $this->name], "bodyHtml" => "<div class=\"text-center\">\n    <a href=\"" . routePath("store-product-group", \WHMCS\MarketConnect\MarketConnect::getServiceProductGroupSlug($this->name)) . "\" style=\"font-weight: 300;\">\n        <img src=\"" . \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/" . $this->primaryIcon . "\">\n        <span>" . \Lang::trans("store." . $langName . ".promo.sidebar.body") . "</span>\n    </a>\n</div>", "footerHtml" => "<i class=\"fas fa-arrow-right fa-fw\"></i> <a href=\"" . routePath("store-product-group", \WHMCS\MarketConnect\MarketConnect::getServiceProductGroupSlug($this->name)) . "\">" . \Lang::trans("learnmore") . "</a>"]);
    }

    public function productDetailsLogin(\WHMCS\Service\Service $serviceModel)
    {
        if (in_array($serviceModel->status, $this->noPromotionStatuses)) {
            return false;
        }
        if ($this->supportsLogin()) {
            $currentServiceId = $serviceModel->id;
            if (in_array($serviceModel->product->moduleConfigOption1, $this->productKeys)) {
                return $this->getLoginPanel()->setServices([["type" => "service", "id" => $currentServiceId]]);
            }
            $serviceInterface = new \WHMCS\MarketConnect\Promotion\Helper\Service($serviceModel);
            if ($this->collectionContains($serviceInterface->getAddonProductKeys(), $this->productKeys)) {
                $addon = $serviceInterface->getActiveAddonByProductKeys($this->productKeys);
                return $this->getLoginPanel()->setServices([["type" => "addon", "id" => $addon->id]]);
            }
        }
    }

    public function productDetailsOutput(\WHMCS\Service\Service $serviceModel)
    {
        if (in_array($serviceModel->status, $this->noPromotionStatuses)) {
            return NULL;
        }
        if (!in_array($serviceModel->product->type, $this->qualifyingProductTypes)) {
            return NULL;
        }
        $service = $this->getModel();
        if (is_null($service) || !$service->setting("promotion.product-details")) {
            return NULL;
        }
        $serviceInterface = new \WHMCS\MarketConnect\Promotion\Helper\Service($serviceModel);
        foreach ($serviceInterface->getAddonProducts() as $addon) {
            $productKey = $addon->productAddon->productKey;
            $upsellProduct = $this->getBestUpsell($productKey);
            if (!is_null($upsellProduct)) {
                $promotion = $this->getUpsellPromotionalContent($upsellProduct->productKey);
                if (!is_null($promotion)) {
                    return new \WHMCS\MarketConnect\Promotion\UpsellPromotion($promotion, $upsellProduct, $addon);
                }
            }
        }
        $serviceProductKeys = $serviceInterface->getProductAndAddonProductKeys();
        if ($this->collectionContains($serviceProductKeys, $this->productKeys)) {
            return NULL;
        }
        $promoProduct = $this->getPromotedProduct();
        if (is_null($promoProduct)) {
            return NULL;
        }
        $promotion = $this->getPromotionalContent($promoProduct->productKey);
        if (!is_null($promotion)) {
            return new \WHMCS\MarketConnect\Promotion\Promotion($promotion, $promoProduct, $serviceModel);
        }
    }

    public function adminCartConfigureProductAddon($addonsByGroup, $addons, $billingCycle, $orderItemId)
    {
        $defaultSelectedAddonId = $this->getAddonToSelectByDefault();
        $addonOptions = [];
        foreach ($this->getProductKeyPrefixes() as $type) {
            if (!empty($addonsByGroup[$type])) {
                $addonsArray = $this->getAddonArray($addonsByGroup[$type], $addons, $billingCycle);
                foreach ($addonsArray as $addonId => $addonData) {
                    $addonInfo = $addonData["addon"];
                    $quantityOption = "";
                    if ($addonInfo["allowsQuantity"] === 2) {
                        $quantityOption = "<input type=\"number\"\n       class=\"form-control input-inline input-75\"\n       min=\"1\"\n       onchange=\"updatesummary()\"\n       value=\"1\"\n       name=\"addons_quantity[" . $orderItemId . "][" . $this->name . "][" . $addonId . "]\"\n>";
                        $quantityOption .= " x ";
                    }
                    $checked = "";
                    if ($defaultSelectedAddonId && $defaultSelectedAddonId === $addonId) {
                        $checked = "checked=\"checked\"";
                    }
                    $addonOptions[] = $quantityOption . "\n<label class=\"radio-inline\">\n    <input type=\"radio\"\n           onchange=\"updatesummary(); return false;\"\n           name=\"addons_radio[" . $orderItemId . "][" . $this->name . "]\"\n           value=\"" . $addonId . "\"\n           class=\"addon-selector\"\n           " . $checked . "\n    >\n    " . $addonInfo["name"] . " - " . $addonData["price"]->toFullString() . "\n</label>";
                }
            }
        }
        if ($addonOptions) {
            array_unshift($addonOptions, "<strong>" . $this->friendlyName . "</strong>", "<label class=\"radio-inline\"><input type=\"radio\" onchange=\"updatesummary(); return false;\" name=\"addons_radio[" . $orderItemId . "][" . $this->name . "]\" class=\"addon-selector\" value=\"\" checked> " . \Lang::trans("none") . "</label>");
        }
        return $addonOptions;
    }

    public function getProductKeyPrefixes()
    {
        $prefixes = [];
        foreach ($this->productKeys as $productKey) {
            $parts = explode("_", $productKey, 2);
            $prefixes[] = $parts[0];
        }
        return array_values(array_unique($prefixes));
    }

    public function cartConfigureProductAddon($addonsByGroup, $addons, $billingCycle)
    {
        $defaultSelectedAddonId = $this->getAddonToSelectByDefault();
        $addonOptions = [];
        foreach ($this->getProductKeyPrefixes() as $type) {
            $addonsArray = [];
            if (isset($addonsByGroup[$type]) && 0 < count($addonsByGroup[$type])) {
                $addonsArray = $this->getAddonArray($addonsByGroup[$type], $addons, $billingCycle);
            }
            foreach ($addonsArray as $addonId => $addonData) {
                $addonInfo = $addonData["addon"];
                $addonOptions[] = "<label class=\"radio-inline\"><input type=\"radio\" name=\"addons_radio[" . $this->name . "]\" value=\"" . $addonId . "\" class=\"addon-selector\"" . ($addonInfo["status"] || $defaultSelectedAddonId && $defaultSelectedAddonId == $addonId ? " checked" : "") . "> &nbsp; " . $addonInfo["name"] . "<span class=\"pull-right float-right\">" . $addonData["price"]->toFullString() . "</span></label>";
            }
        }
        if (0 < count($addonOptions)) {
            return $this->renderCartConfigureProductAddon($addonOptions);
        }
    }

    protected function getAddonToSelectByDefault()
    {
        return NULL;
    }

    protected function getExcludedFromNewPurchaseAddonIds()
    {
        return [];
    }

    protected function renderCartConfigureProductAddon($addonOptions)
    {
        $langName = $this->getLangVar($this->name);
        return "<div class=\"addon-promo-container addon-promo-container-" . $this->name . " bg-white d-block\">\n            <div class=\"description\">\n                <div class=\"logo\">\n                    <img src=\"" . $this->primaryIcon . "\" width=\"80\">\n                </div>\n                <h3>" . \Lang::trans("store." . $langName . ".cartTitle") . "</h3>\n                <p>" . \Lang::trans("store." . $langName . ".cartShortDescription") . "<br><a href=\"" . routePath("store-product-group", \WHMCS\MarketConnect\MarketConnect::getServiceProductGroupSlug($this->name)) . "\" target=\"_blank\">" . \Lang::trans("learnmore") . "...</a></p>\n            </div>\n            <div class=\"clearfix\"></div>\n            <label class=\"radio-inline\"><input type=\"radio\" name=\"addons_radio[" . $this->name . "]\" class=\"addon-selector\" checked> &nbsp; " . \Lang::trans("none") . "<span class=\"pull-right float-right\">-</span></label><br>\n            " . implode("<br>", $addonOptions) . "\n        </div>";
    }

    protected function getLangVar($name)
    {
        if (!empty(\WHMCS\MarketConnect\MarketConnect::SERVICES[$name]["languageString"])) {
            return \WHMCS\MarketConnect\MarketConnect::SERVICES[$name]["languageString"];
        }
        return $name;
    }

    public function requiresDomain()
    {
        return $this->requiresDomain;
    }

    protected function langStringOrFallback($key, $fallbackText)
    {
        if (\Lang::trans($key) != $key) {
            return \Lang::trans($key);
        }
        return $fallbackText;
    }

    public function getPlanFeatures($key)
    {
        return isset($this->planFeatures[$key]) ? $this->planFeatures[$key] : [];
    }

    public function getFeaturesForUpgrade($key)
    {
        return $this->getPlanFeatures($key);
    }

    public function getPlanFeature($featureKey, $plan)
    {
        return $this->getPlanFeatures($plan)[$featureKey] ?? NULL;
    }
}
