<?php

namespace WHMCS\Product;

use WHMCS\Application\Support\Facades\Lang;

/**
 * @property string $serverId
 * @property string $username
 * @property string $password
 *
 * @package WHMCS\Product
 */
class Product extends \WHMCS\Model\AbstractModel implements Interfaces\SlugInterface, PricedEntityInterface
{
    use MarketConnectTrait;
    use SlugTrait;

    protected $table = "tblproducts";
    protected $moduleField = "servertype";
    protected $columnMap = ["productGroupId" => "gid", "isHidden" => "hidden", "welcomeEmailTemplateId" => "welcomeemail", "stockControlEnabled" => "stockcontrol", "quantityInStock" => "qty", "proRataChargeDayOfCurrentMonth" => "proratadate", "proRataChargeNextMonthAfterDay" => "proratachargenextmonth", "paymentType" => "paytype", "allowMultipleQuantities" => "allowqty", "freeSubDomains" => "subdomain", "module" => "servertype", "serverGroupId" => "servergroup", "moduleConfigOption1" => "configoption1", "moduleConfigOption2" => "configoption2", "moduleConfigOption3" => "configoption3", "moduleConfigOption4" => "configoption4", "moduleConfigOption5" => "configoption5", "moduleConfigOption6" => "configoption6", "moduleConfigOption7" => "configoption7", "moduleConfigOption8" => "configoption8", "moduleConfigOption9" => "configoption9", "moduleConfigOption10" => "configoption10", "moduleConfigOption11" => "configoption11", "moduleConfigOption12" => "configoption12", "moduleConfigOption13" => "configoption13", "moduleConfigOption14" => "configoption14", "moduleConfigOption15" => "configoption15", "moduleConfigOption16" => "configoption16", "moduleConfigOption17" => "configoption17", "moduleConfigOption18" => "configoption18", "moduleConfigOption19" => "configoption19", "moduleConfigOption20" => "configoption20", "moduleConfigOption21" => "configoption21", "moduleConfigOption22" => "configoption22", "moduleConfigOption23" => "configoption23", "moduleConfigOption24" => "configoption24", "recurringCycleLimit" => "recurringcycles", "daysAfterSignUpUntilAutoTermination" => "autoterminatedays", "autoTerminationEmailTemplateId" => "autoterminateemail", "allowConfigOptionUpgradeDowngrade" => "configoptionsupgrade", "upgradeEmailTemplateId" => "upgradeemail", "enableOverageBillingAndUnits" => "overagesenabled", "overageDiskLimit" => "overagesdisklimit", "overageBandwidthLimit" => "overagesbwlimit", "overageDiskPrice" => "overagesdiskprice", "overageBandwidthPrice" => "overagesbwprice", "applyTax" => "tax", "affiliatePayoutOnceOnly" => "affiliateonetime", "affiliatePaymentType" => "affiliatepaytype", "affiliatePaymentAmount" => "affiliatepayamount", "isRetired" => "retired", "displayOrder" => "order"];
    protected $booleans = ["isHidden", "showDomainOptions", "stockControlEnabled", "proRataBilling", "allowConfigOptionUpgradeDowngrade", "applyTax", "affiliatePayoutOnceOnly", "isRetired", "isFeatured"];
    protected $strings = ["description", "autoSetup", "module", "moduleConfigOption1", "moduleConfigOption2", "moduleConfigOption3", "moduleConfigOption4", "moduleConfigOption5", "moduleConfigOption6", "moduleConfigOption7", "moduleConfigOption8", "moduleConfigOption9", "moduleConfigOption10", "moduleConfigOption11", "moduleConfigOption12", "moduleConfigOption13", "moduleConfigOption14", "moduleConfigOption15", "moduleConfigOption16", "moduleConfigOption17", "moduleConfigOption18", "moduleConfigOption19", "moduleConfigOption20", "moduleConfigOption21", "moduleConfigOption22", "moduleConfigOption23", "moduleConfigOption24", "tagline", "shortDescription", "color"];
    protected $ints = ["welcomeEmailTemplateId", "quantityInStock", "proRataChargeDayOfCurrentMonth", "proRataChargeNextMonthAfterDay", "serverGroupId", "displayOrder"];
    protected $commaSeparated = ["freeSubDomains", "freeDomainPaymentTerms", "freeDomainTlds", "enableOverageBillingAndUnits"];
    protected $casts = ["allowqty" => "integer"];
    protected $appends = ["formattedProductFeatures"];
    protected $pricingCache = NULL;

    const TYPE_SHARED = "hostingaccount";
    const TYPE_RESELLER = "reselleraccount";
    const TYPE_SERVERS = "server";
    const TYPE_OTHER = "other";
    const PAYMENT_FREE = "free";
    const PAYMENT_ONETIME = "onetime";
    const PAYMENT_RECURRING = "recurring";
    const AUTO_SETUP_ORDER = "order";
    const AUTO_SETUP_PAYMENT = "payment";
    const AUTO_SETUP_ACCEPT = "on";
    const AUTO_SETUP_DISABLED = "";
    const DEFAULT_EMAIL_TEMPLATES = NULL;

    public static function boot()
    {
        if (!function_exists("logAdminActivity")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "adminfunctions.php";
        }
        parent::boot();
        static::observe("WHMCS\\Product\\Observers\\ProductObserver");
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblproducts.order")->orderBy("tblproducts.id");
        });
    }

    public function productGroup()
    {
        return $this->belongsTo("WHMCS\\Product\\Group", "gid");
    }

    public function welcomeEmailTemplate()
    {
        return $this->hasOne("WHMCS\\Mail\\Template", "id", "welcomeemail");
    }

    public function autoTerminationEmailTemplate()
    {
        return $this->hasOne("WHMCS\\Mail\\Template", "id", "autoterminateemail");
    }

    public function upgradeEmailTemplate()
    {
        return $this->hasOne("WHMCS\\Mail\\Template", "id", "upgradeemail");
    }

    public function productDownloads()
    {
        return $this->belongsToMany("WHMCS\\Download\\Download", "tblproduct_downloads");
    }

    public function upgradeProducts()
    {
        return $this->belongsToMany("WHMCS\\Product\\Product", "tblproduct_upgrade_products", "product_id", "upgrade_product_id");
    }

    public function services()
    {
        return $this->hasMany("WHMCS\\Service\\Service", "packageid");
    }

    public function customFields()
    {
        return $this->hasMany("WHMCS\\CustomField", "relid")->where("type", "=", "product")->orderBy("sortorder");
    }

    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where(function ($query) {
            $query->where("hidden", "0")->orWhere("hidden", "");
        });
    }

    public function scopeSorted($query)
    {
        return $query->orderBy("order");
    }

    public function getDownloadIds()
    {
        return array_map(function ($download) {
            return $download["id"];
        }, $this->productDownloads->toArray());
    }

    public function getUpgradeProductIds()
    {
        return array_map(function ($product) {
            return $product["id"];
        }, $this->upgradeProducts->toArray());
    }

    public function getAvailableBillingCycles()
    {
        switch ($this->paymentType) {
            case "free":
                return ["free"];
                break;
            case "onetime":
                return ["onetime"];
                break;
            case "recurring":
                $validCycles = [];
                $productPricing = new \WHMCS\Pricing();
                $productPricing->loadPricing("product", $this->id);
                return $productPricing->getAvailableBillingCycles();
                break;
            default:
                return [];
        }
    }

    public function pricing($Pricing, $currency)
    {
        if (is_null($this->pricingCache)) {
            $this->pricingCache = new Pricing($this, $currency);
        }
        return $this->pricingCache;
    }

    public function getNameAttribute($name)
    {
        $translatedName = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedName = Lang::trans("product." . $this->id . ".name", [], "dynamicMessages");
        }
        return strlen($translatedName) && $translatedName != "product." . $this->id . ".name" ? $translatedName : $name;
    }

    public function getDescriptionAttribute($description)
    {
        $translatedDescription = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedDescription = Lang::trans("product." . $this->id . ".description", [], "dynamicMessages");
        }
        return strlen($translatedDescription) && $translatedDescription != "product." . $this->id . ".description" ? $translatedDescription : $description;
    }

    public function getTaglineAttribute($tagline)
    {
        $translatedTagline = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedTagline = Lang::trans("product." . $this->id . ".tagline", [], "dynamicMessages");
        }
        return strlen($translatedTagline) && $translatedTagline != "product." . $this->id . ".tagline" ? $translatedTagline : $tagline;
    }

    public function getShortDescriptionAttribute($shortDescription)
    {
        $translatedShortDescription = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            $translatedShortDescription = Lang::trans("product." . $this->id . ".short_description", [], "dynamicMessages");
        }
        return strlen($translatedShortDescription) && $translatedShortDescription != "product." . $this->id . ".short_description" ? $translatedShortDescription : $shortDescription;
    }

    public function setShortDescriptionAttribute($shortDescription)
    {
        $this->attributes["short_description"] = $shortDescription;
    }

    public function translatedNames()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "product.{id}.name")->select(["language", "translation"]);
    }

    public function translatedDescriptions()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "product.{id}.description")->select(["language", "translation"]);
    }

    public static function getProductName($productId, $fallback = "", $language = NULL)
    {
        $name = Lang::trans("product." . $productId . ".name", [], "dynamicMessages", $language);
        if ($name == "product." . $productId . ".name") {
            if ($fallback) {
                return $fallback;
            }
            $product = Product::find($productId, ["name"]);
            if (is_object($product)) {
                return $product->name;
            }
            return NULL;
        }
        return $name;
    }

    public static function getProductDescription($productId, $fallback = "", $language = NULL)
    {
        $description = Lang::trans("product." . $productId . ".description", [], "dynamicMessages", $language);
        if ($description == "product." . $productId . ".description") {
            if ($fallback) {
                return $fallback;
            }
            $product = Product::find($productId, ["description"]);
            if (is_object($product)) {
                return $product->description;
            }
            return NULL;
        }
        return $description;
    }

    public function assignMatchingMarketConnectAddons($addons)
    {
        if (!$this->exists) {
            throw new \WHMCS\Exception("Product must be saved before being auto-assigned");
        }
        foreach ($addons as $addon) {
            $myself = self::where("id", "=", $this->id);
            foreach ($addon->autoLinkCriteria as $field => $value) {
                if (is_array($value)) {
                    $myself->whereIn($field, $value);
                } else {
                    $myself->where($field, $value);
                }
            }
            if (0 < $myself->count()) {
                if (!in_array($this->id, $addon->packages)) {
                    $addon->packages = array_merge($addon->packages, [$this->id]);
                }
                $addon->save();
            }
        }
    }

    public function isFree()
    {
        return $this->paymentType == "free";
    }

    public function isOneTime()
    {
        return $this->paymentType == "onetime";
    }

    public function scopeIsNotRetired(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("retired", 0);
    }

    public function scopeIsRetired(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("retired", 1);
    }

    public function scopeOfModule($Builder, $query, $module)
    {
        return $query->where("servertype", $module);
    }

    public function getProductKeyAttribute($value)
    {
        return $this->moduleConfigOption1;
    }

    public function isMarketConnectProduct()
    {
        return $this->module == "marketconnect";
    }

    public function getServiceKeyAttribute($value)
    {
        $productKey = $this->productKey;
        $parts = explode("_", $productKey, 2);
        return !empty($parts[0]) ? $parts[0] : NULL;
    }

    public function isValidForUpgrade(Product $product)
    {
        if ($this->isMarketConnectProduct() && !empty($product->serviceKey) && $this->serviceKey == $product->serviceKey) {
            return true;
        }
        return false;
    }

    public function getFormattedProductFeaturesAttribute()
    {
        $features = [];
        $featuresDescription = "";
        $descriptionLines = explode("\n", $this->description);
        foreach ($descriptionLines as $line) {
            if (strpos($line, ":")) {
                $line = explode(":", $line, 2);
                $features[trim($line[0])] = trim($line[1]);
            } else {
                if (trim($line)) {
                    $featuresDescription .= $line . "\n";
                }
            }
        }
        return ["original" => nl2br($this->description), "features" => $features, "featuresDescription" => nl2br($featuresDescription)];
    }

    public function getBilledMetricsAttribute()
    {
        $metrics = $this->hasMany("WHMCS\\UsageBilling\\Product\\UsageItem", "rel_id", "id")->where("rel_type", \WHMCS\Contracts\ProductServiceTypes::TYPE_PRODUCT_PRODUCT)->where("module", $this->module)->where("module_type", \WHMCS\Module\AbstractModule::TYPE_SERVER)->where("is_hidden", 0)->get();
        if ($metrics->count() && $this->module) {
            $module = new \WHMCS\Module\Server();
            $module->load($this->module);
            $provider = $module->call("MetricProvider");
            $order = [];
            foreach ($provider->metrics() as $metric) {
                $order[] = $metric->systemName();
            }
            $billedOrdered = [];
            foreach ($metrics as $metric) {
                $key = array_search($metric->metric, $order);
                if ($key !== false) {
                    $billedOrdered[$key] = $metric;
                }
            }
            return new \Illuminate\Database\Eloquent\Collection($billedOrdered);
        } else {
            return new \Illuminate\Database\Eloquent\Collection([]);
        }
    }

    public function metrics()
    {
        return $this->hasMany("WHMCS\\UsageBilling\\Product\\UsageItem", "rel_id", "id")->includeDisabled()->where("rel_type", \WHMCS\Contracts\ProductServiceTypes::TYPE_PRODUCT_PRODUCT)->where("module", $this->module)->where("module_type", \WHMCS\Module\AbstractModule::TYPE_SERVER);
    }

    public function recommendations()
    {
        return $this->belongsToMany("WHMCS\\Product\\Product", "tblproduct_recommendations", "id", "product_id")->withPivot("sortorder");
    }

    public function emailMarketerRules()
    {
        return $this->belongsToMany("WHMCS\\Admin\\Utilities\\Tools\\EmailMarketer", "tblemailmarketer_related_pivot", "product_id", "task_id")->withTimestamps();
    }

    public function getClientStockLevel()
    {
        $quantity = $this->quantityInStock;
        if ($quantity < 0) {
            $quantity = 0;
        }
        return $quantity;
    }

    public function moduleConfiguration()
    {
        return $this->hasMany("WHMCS\\Config\\Module\\ModuleConfiguration", "entity_id")->where("entity_type", "=", "product");
    }

    public function getModuleConfigurationSetting($ModuleConfiguration, $settingName)
    {
        $moduleConfiguration = \WHMCS\Config\Module\ModuleConfiguration::typeProduct()->ofEntityId($this->id)->where("setting_name", $settingName)->first();
        if (!$moduleConfiguration) {
            $moduleConfiguration = new \WHMCS\Config\Module\ModuleConfiguration();
            $moduleConfiguration->entityType = "product";
            $moduleConfiguration->entityId = $this->id;
            $moduleConfiguration->settingName = $settingName;
        }
        return $moduleConfiguration;
    }

    public function eventActions()
    {
        return $this->hasMany("WHMCS\\Product\\EventAction\\EventAction", "entity_id")->where("entity_type", "=", "product");
    }

    public function duplicate($newProductName)
    {
        $newProduct = $this->replicate();
        $newProduct->name = $newProductName;
        $newProduct->save();
        \WHMCS\Billing\Pricing::where(["type" => "product", "relid" => $this->id])->each(function ($pricing) use ($newProduct) {
            $pricing->replicate()->fill(["relid" => $newProduct->id])->save();
        });
        foreach ($this->recommendations as $recommendation) {
            $newProduct->recommendations()->attach([$recommendation->id => ["sortorder" => $recommendation->pivot->sortorder]]);
        }
        $this->customFields->each(function ($customField) use ($newProduct) {
            $customField->replicate()->fill(["relid" => $newProduct->id])->save();
        });
        ConfigOptionGroupLinks::where("pid", $this->id)->each(function ($link) use ($newProduct) {
            $link->replicate()->fill(["pid" => $newProduct->id])->save();
        });
        $this->moduleConfiguration->each(function ($config) use ($newProduct) {
            $newConfig = $config->replicate();
            $newConfig->entityId = $newProduct->id;
            $newConfig->save();
        });
        $this->eventActions->each(function ($event) use ($newProduct) {
            $newEvent = $event->replicate();
            $newEvent->entityId = $newProduct->id;
            $newEvent->save();
        });
        foreach ($this->productDownloads as $download) {
            $newProduct->productDownloads()->attach($download);
        }
        foreach ($this->upgradeProducts as $upgradeProduct) {
            $newProduct->upgradeProducts()->attach($upgradeProduct);
        }
        $usageItems = $this->billedMetrics;
        foreach ($usageItems as $item) {
            $newItem = $item->replicate()->fill(["rel_id" => $newProduct->id]);
            $newItem->save();
            $schema = $item->pricingSchema;
            foreach ($schema as $bracket) {
                $newBracket = $bracket->replicate()->fill(["rel_id" => $newItem->id]);
                $newBracket->save();
                foreach ($bracket->pricing as $price) {
                    $price->replicate()->fill(["relid" => $newBracket->id])->save();
                }
            }
        }
        return $newProduct;
    }

    public function slugs()
    {
        return $this->hasMany("WHMCS\\Product\\Product\\Slug");
    }

    public function inactiveSlugs()
    {
        return $this->hasMany("WHMCS\\Product\\Product\\Slug")->where("active", 0);
    }

    public function activeSlug()
    {
        return $this->hasOne("WHMCS\\Product\\Product\\Slug")->where("active", 1);
    }

    public function validateSlugIsUnique($slug)
    {
        $activeSlug = $this->activeSlug;
        if ($activeSlug && $this->productGroupId === $activeSlug->groupId && $activeSlug->slug === $slug) {
            return true;
        }
        $exists = Product\Slug::where("group_id", $this->productGroupId)->where("slug", $slug);
        if ($this->exists) {
            $exists->where("product_id", "!=", $this->id);
        }
        if ($exists->count()) {
            throw new \WHMCS\Exception\Validation\DuplicateValue();
        }
        return true;
    }

    public function getExistingSlugCheck($Builder, $slug)
    {
        $existingCheck = Product\Slug::query()->where("slug", $slug);
        if ($this->exists) {
            $existingCheck->where("product_id", "!=", $this->id);
        }
        return $existingCheck;
    }

    public function createSlug()
    {
        return $this->slugs()->create(["group_id" => $this->productGroup->id, "group_slug" => $this->productGroup->slug, "slug" => $this->autoGenerateUniqueSlug(), "active" => true]);
    }

    public function getRoutePath()
    {
        $routeParts = $this->getRouteParts();
        return fqdnRoutePath($routeParts["route"], ...$routeParts["routeVariables"]);
    }

    public function getRouteParts()
    {
        $activeSlug = $this->activeSlug;
        if (!$activeSlug) {
            $slug = $this->autoGenerateUniqueSlug();
            $groupSlug = $this->productGroup->slug;
            $activeSlug = $this->activeSlug()->firstOrCreate(["product_id" => $this->id, "group_id" => $this->productGroupId, "group_slug" => $groupSlug, "slug" => $slug], ["active" => 1]);
            if (!$activeSlug->active) {
                $activeSlug->active = true;
                $activeSlug->save();
            }
        }
        return ["route" => "store-product-product", "routeVariables" => [$activeSlug->groupSlug, $activeSlug->slug]];
    }
}
