<?php

namespace WHMCS\Updater\Version;

class Version730release1 extends IncrementalVersion
{
    protected $updateActions = ["removeArchivingOnlyMarketConnectProduct", "removeArchivingOnlyMarketConnectAddon"];

    protected function removeArchivingOnlyMarketConnectProduct()
    {
        $products = \WHMCS\Product\Product::withCount("services")->where("servertype", "marketconnect")->where("configoption1", "spamexperts_archiving")->get();
        foreach ($products as $product) {
            if (0 < $product->services_count) {
                $product->stockControlEnabled = true;
                $product->quantityInStock = 0;
                $product->isRetired = true;
                $product->isHidden = true;
                $product->save();
            } else {
                $product->delete();
            }
        }
        return $this;
    }

    protected function removeArchivingOnlyMarketConnectAddon()
    {
        $addons = \WHMCS\Product\Addon::with("serviceAddons")->whereHas("moduleConfiguration", function ($query) {
            $query->where("setting_name", "=", "configoption1")->where("value", "spamexperts_archiving");
        })->where("module", "marketconnect")->get();
        foreach ($addons as $addon) {
            if (0 < $addon->serviceAddons->count()) {
                $addon->serviceAddons()->update(["addonid" => 0, "name" => $addon->getRawAttribute("name")]);
            }
            $addon->delete();
        }
        return $this;
    }
}
