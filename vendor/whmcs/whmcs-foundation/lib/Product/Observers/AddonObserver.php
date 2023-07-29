<?php

namespace WHMCS\Product\Observers;

class AddonObserver
{
    public function created(\WHMCS\Product\Addon $addon)
    {
        logAdminActivity("Product Addon Created: '" . $addon->name . "' - Product Addon ID: " . $addon->id);
    }

    public function deleted(\WHMCS\Product\Addon $addon)
    {
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            \WHMCS\Language\DynamicTranslation::whereIn("related_type", ["product_addon.{id}.description", "product_addon.{id}.name"])->where("related_id", "=", $addon->id)->delete();
        }
    }

    public function saved(\WHMCS\Product\Addon $addon)
    {
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "product_addon.{id}.description", "related_id" => $addon->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "textarea"]);
            $translation->translation = $addon->getRawAttribute("description") ?: "";
            $translation->save();
            $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "product_addon.{id}.name", "related_id" => $addon->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "text"]);
            $translation->translation = $addon->getRawAttribute("name") ?: "";
            $translation->save();
        }
    }

    public function updated(\WHMCS\Product\Addon $addon)
    {
        $changeCount = count($addon->getChanges());
        $id = $addon->id;
        $name = $addon->name;
        if (0 < $changeCount) {
            if ($addon->wasChanged("name")) {
                $oldName = $addon->getOriginal("name");
                logAdminActivity("Product Addon Modified: Name Changed: " . "'" . $oldName . "' to '" . $name . "' - Product Addon ID: " . $id);
                $changeCount--;
            }
            if (0 < $changeCount) {
                logAdminActivity("Product Addon Modified: '" . $name . "' - Product Addon ID: " . $id);
            }
        }
    }
}
