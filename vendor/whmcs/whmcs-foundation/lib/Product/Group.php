<?php

namespace WHMCS\Product;

class Group extends \WHMCS\Model\AbstractModel implements Interfaces\SlugInterface
{
    use SlugTrait;
    protected $table = "tblproductgroups";
    protected $columnMap = ["orderFormTemplate" => "orderfrmtpl", "disabledPaymentGateways" => "disabledgateways", "isHidden" => "hidden", "displayOrder" => "order"];
    protected $booleans = ["isHidden"];
    protected $commaSeparated = ["disabledPaymentGateways"];

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Product\\Observers\\ProductGroupObserver");
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblproductgroups.order")->orderBy("tblproductgroups.id");
        });
    }

    public function products()
    {
        return $this->hasMany("WHMCS\\Product\\Product", "gid");
    }

    public function features()
    {
        return $this->hasMany("WHMCS\\Product\\Group\\Feature", "product_group_id")->orderBy("order");
    }

    public function scopeSlug($query, $slug)
    {
        return $query->where("slug", $slug);
    }

    public function scopeNotHidden($query)
    {
        return $query->where("hidden", "0")->orWhere("hidden", "");
    }

    public function scopeSorted($query)
    {
        return $query->orderBy("order");
    }

    public function getRoutePath($fullUrl = false, int $pid = NULL)
    {
        $function = $fullUrl ? "fqdnRoutePath" : "routePath";
        $pid = $pid ?: NULL;
        try {
            $isValidFormat = $this->validateSlugFormat($this->slug);
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            $isValidFormat = false;
        }
        if ($this->slug && $isValidFormat === true) {
            return $function("store-product-group", $this->slug, $pid);
        }
        return $function("store-product-group", $this->id, $pid);
    }

    public function validateSlugIsUnique($slug)
    {
        if ($this->slug === $slug) {
            return true;
        }
        $existingCheck = $this->getExistingSlugCheck($slug);
        if ($existingCheck->exists() && $existingCheck->first()->id !== $this->id) {
            throw new \WHMCS\Exception\Validation\DuplicateValue();
        }
        if (Product\Slug::where("group_id", "!=", $this->id)->where("group_slug", $slug)->count()) {
            throw new \WHMCS\Exception\Validation\DuplicateValue();
        }
        return true;
    }

    public function autoGenerateUniqueSlug()
    {
        $name = \WHMCS\Input\Sanitize::decode($this->name);
        $name = preg_replace("/\\s*&\\s*/", " and ", $name);
        $slug = \Illuminate\Support\Str::slug(\voku\helper\ASCII::to_transliterate($name));
        try {
            $isValidFormat = $this->validateSlugFormat($slug);
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            $isValidFormat = false;
        }
        if (empty($this->name) || $isValidFormat !== true) {
            return "";
        }
        $count = 0;
        $currentSuffix = "";
        $maxLoops = 1000;
        while (0 < $maxLoops-- && self::slug($slug)->where("id", "!=", $this->id)->exists()) {
            if ($currentSuffix) {
                $slug = substr($slug, 0, strlen($currentSuffix) * -1);
            }
            $count++;
            $currentSuffix = "-" . $count;
            if ($slug) {
                $slug .= $currentSuffix;
            }
        }
        return $slug;
    }

    public function getExistingSlugCheck($Builder, $slug)
    {
        $existingCheck = self::slug($slug);
        if ($this->exists) {
            $existingCheck->where("id", "!=", $this->id);
        }
        return $existingCheck;
    }

    public function orderFormTemplate()
    {
        return $this->orderFormTemplate == "" ? \WHMCS\View\Template\OrderForm::getDefault() : \WHMCS\View\Template\OrderForm::find($this->orderFormTemplate);
    }

    public function translatedNames()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "product_group.{id}.name")->select(["language", "translation"]);
    }

    public function translatedHeadlines()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "product_group.{id}.headline")->select(["language", "translation"]);
    }

    public function translatedTaglines()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "product_group.{id}.tagline")->select(["language", "translation"]);
    }

    public function getNameAttribute($name)
    {
        $translatedName = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedName = \Lang::trans("product_group." . $this->id . ".name", [], "dynamicMessages");
        }
        return strlen($translatedName) && $translatedName != "product_group." . $this->id . ".name" ? $translatedName : $name;
    }

    public function getHeadlineAttribute($headline)
    {
        $translatedHeadline = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedHeadline = \Lang::trans("product_group." . $this->id . ".headline", [], "dynamicMessages");
        }
        return strlen($translatedHeadline) && $translatedHeadline != "product_group." . $this->id . ".headline" ? $translatedHeadline : $headline;
    }

    public function getTaglineAttribute($tagline)
    {
        $translatedTagline = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedTagline = \Lang::trans("product_group." . $this->id . ".tagline", [], "dynamicMessages");
        }
        return strlen($translatedTagline) && $translatedTagline != "product_group." . $this->id . ".tagline" ? $translatedTagline : $tagline;
    }

    public static function getGroupName($groupId, $fallback = "", $language = NULL)
    {
        $name = \Lang::trans("product_group." . $groupId . ".name", [], "dynamicMessages", $language);
        if ($name == "product_group." . $groupId . ".name") {
            if ($fallback) {
                return $fallback;
            }
            return Group::find($groupId, ["name"])->name;
        }
        return $name;
    }

    public static function getHeadline($groupId, $fallback = "", $language = NULL)
    {
        $headline = \Lang::trans("product_group." . $groupId . ".headline", [], "dynamicMessages", $language);
        if ($headline == "product_group." . $groupId . ".headline") {
            if ($fallback) {
                return $fallback;
            }
            return Group::find($groupId, ["headline"])->headline;
        }
        return $headline;
    }

    public static function getTagline($groupId, $fallback = "", $language = NULL)
    {
        $tagline = \Lang::trans("product_group." . $groupId . ".tagline", [], "dynamicMessages", $language);
        if ($tagline == "product_group." . $groupId . ".tagline") {
            if ($fallback) {
                return $fallback;
            }
            return Group::find($groupId, ["tagline"])->tagline;
        }
        return $tagline;
    }

    public function isMarketConnectGroup()
    {
        return 0 < $this->products()->where("servertype", \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT)->count();
    }

    public function getMarketConnectControllerClass()
    {
        if (!$this->isMarketConnectGroup()) {
            throw new \WHMCS\Exception\Validation\InvalidValue();
        }
        $firstProduct = $this->products()->where("servertype", \WHMCS\MarketConnect\MarketConnect::MARKETCONNECT)->first();
        if (!$firstProduct) {
            throw new \WHMCS\Exception\Validation\InvalidValue();
        }
        return \WHMCS\MarketConnect\MarketConnect::getControllerClassByService($firstProduct->moduleConfigOption1);
    }

    public function productSlugs()
    {
        return $this->hasMany("WHMCS\\Product\\Product\\Slug");
    }
}
