<?php

namespace WHMCS\Product\Observers;

class ProductGroupObserver
{
    public function created(\WHMCS\Product\Group $group)
    {
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            \WHMCS\Language\DynamicTranslation::whereIn("related_type", ["product_group.{id}.headline", "product_group.{id}.name", "product_group.{id}.tagline"])->where("related_id", "=", 0)->update(["related_id" => $group->id]);
        }
    }

    public function deleted(\WHMCS\Product\Group $group)
    {
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            \WHMCS\Language\DynamicTranslation::whereIn("related_type", ["product_group.{id}.headline", "product_group.{id}.name", "product_group.{id}.tagline"])->where("related_id", "=", $group->id)->delete();
        }
    }

    public function saved(\WHMCS\Product\Group $group)
    {
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "product_group.{id}.headline", "related_id" => $group->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "text"]);
            $translation->translation = $group->getRawAttribute("headline") ?: "";
            $translation->save();
            $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "product_group.{id}.name", "related_id" => $group->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "text"]);
            $translation->translation = $group->getRawAttribute("name") ?: "";
            $translation->save();
            $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "product_group.{id}.tagline", "related_id" => $group->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "text"]);
            $translation->translation = $group->getRawAttribute("tagline") ?: "";
            $translation->save();
        }
        if ($group->isDirty("slug") && !$group->wasRecentlyCreated) {
            $slugExists = $group->productSlugs()->where("group_slug", $group->slug);
            $slugCount = $slugExists->count();
            $activeProductSlugs = [];
            foreach ($group->productSlugs as $productSlug) {
                $slugCheck = clone $slugExists;
                $slugCheckCount = $slugCheck->where("slug", $productSlug->slug)->count();
                if (!$slugCount || !$slugCheckCount) {
                    $newSlug = $productSlug->replicate();
                    $newSlug->groupSlug = $group->slug;
                    $newSlug->clicks = 0;
                    $newSlug->save();
                }
                if ($productSlug->active) {
                    $activeProductSlugs[] = $productSlug->slug;
                }
                $productSlug->active = false;
                $productSlug->save();
            }
            foreach ($slugExists->get() as $existingSlug) {
                if (in_array($existingSlug->slug, $activeProductSlugs)) {
                    $existingSlug->active = true;
                    $existingSlug->save();
                }
            }
        }
    }
}
