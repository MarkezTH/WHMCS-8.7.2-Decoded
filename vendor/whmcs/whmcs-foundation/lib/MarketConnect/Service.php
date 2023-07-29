<?php

namespace WHMCS\MarketConnect;

class Service extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblmarketconnect_services";
    protected $booleans = ["status"];
    protected $casts = ["settings" => "array"];
    protected $commaSeparated = ["productIds"];
    protected $fillable = ["name"];
    protected $appends = ["productGroup"];
    public $timestamps = false;

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("status", 1);
    }

    public function scopeName(\Illuminate\Database\Eloquent\Builder $query, $name)
    {
        $query->where("name", $name);
    }

    public static function activate($serviceName, $productIdNames = NULL)
    {
        $service = self::firstOrNew(["name" => $serviceName]);
        $service->status = true;
        if (is_array($productIdNames) && !empty($productIdNames)) {
            $service->productIds = array_unique(array_merge($service->productIds, $productIdNames));
        }
        if (!$service->id) {
            $generalSettingDefaults = [];
            foreach ($service->getSettingDefinitions() as $setting) {
                $generalSettingDefaults[$setting["name"]] = $setting["default"];
            }
            $service->settings = ["promotion" => ["client-home" => true, "product-details" => true, "product-list" => true, "cart-view" => true, "cart-checkout" => true], "general" => $generalSettingDefaults];
        }
        $service->save();
        return $service;
    }

    public function deactivate()
    {
        foreach ($this->getAssociatedProducts() as $product) {
            $product->isHidden = true;
            $product->quantityInStock = 0;
            $product->stockControlEnabled = true;
            $product->save();
        }
        foreach ($this->getAssociatedAddons() as $addon) {
            $addon->showOnOrderForm = false;
            $addon->save();
        }
        $this->status = false;
        $this->save();
        return $this;
    }

    public function getAssociatedProducts()
    {
        return \WHMCS\Product\Product::marketConnect()->whereIn("configoption1", $this->productIds)->get();
    }

    public function getAssociatedAddons()
    {
        $addons = [];
        foreach (\WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->whereIn("value", $this->productIds)->get() as $addonModuleConfig) {
            $productAddon = $addonModuleConfig->productAddon;
            if (!is_null($productAddon)) {
                $addons[] = $productAddon;
            }
        }
        return collect($addons);
    }

    public function disassociateAddonsFromAllProducts()
    {
        foreach ($this->getAssociatedAddons() as $addon) {
            $addon->packages = [];
            $addon->save();
        }
        return $this;
    }

    public function setting($key)
    {
        $settings = $this->settings;
        $parts = explode(".", $key);
        foreach ($parts as $part) {
            $settings = isset($settings[$part]) ? $settings[$part] : NULL;
        }
        return $settings;
    }

    public function factoryPromoter()
    {
        return MarketConnect::factoryPromotionalHelper($this->name);
    }

    public function getProductGroupAttribute()
    {
        if (!array_key_exists($this->id, $productGroups) || is_null($productGroups[$this->id])) {
            $productIds = $this->productIds;
            $productGroups[$this->id] = \WHMCS\Product\Group::whereHas("products", function (\Illuminate\Database\Eloquent\Builder $query) use($productIds) {
                $query->where("servertype", "marketconnect")->whereIn("configoption1", $productIds);
            })->first();
        }
        return $productGroups[$this->id];
    }

    public static function getAutoAssignableAddons()
    {
        $mcServices = self::active()->get()->filter(function ($mcService) {
            return $mcService->setting("general.auto-assign-addons");
        });
        $addons = [];
        foreach ($mcServices as $mcService) {
            foreach ($mcService->getAssociatedAddons() as $productAddon) {
                $addons[$productAddon->id] = $productAddon;
            }
        }
        return $addons;
    }

    public function getSettingDefinitions()
    {
        $serviceSpecificSettings = $this->factoryPromoter()->getSettings();
        return array_merge(Promotion::DEFAULT_SETTINGS, $serviceSpecificSettings);
    }
}
