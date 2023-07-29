<?php

namespace WHMCS;

class OrderForm
{
    private $pid = "";
    private $productinfo = [];
    private $addonId = 0;
    private $addonInfo = [];
    private $validbillingcycles = NULL;
    const TYPE_DOMAIN_INCART = "incart";
    const TYPE_DOMAIN_OWN = "owndomain";
    const TYPE_DOMAIN_REGISTER = "register";
    const TYPE_DOMAIN_SUB = "subdomain";
    const TYPE_DOMAIN_TRANSFER = "transfer";
    const DOMAIN_REGISTER_OR_TRANSFER = NULL;
    const DOMAIN_ALL = NULL;

    public function getCartData()
    {
        return (array) Session::get("cart");
    }

    public function setCartData($data)
    {
        return Session::set("cart", $data);
    }

    public function getCartDataByKey($key, $keyNotFoundValue = "")
    {
        $cartSession = $this->getCartData();
        return array_key_exists($key, $cartSession) ? $cartSession[$key] : $keyNotFoundValue;
    }

    public function setCartDataByKey($key, $data)
    {
        $cartSession = $this->getCartData();
        $cartSession[$key] = $data;
        $this->setCartData($cartSession);
    }

    public function getProductGroups($asCollection = false)
    {
        $groups = [];
        $groupsToSort = Product\Group::notHidden()->sorted()->get();
        if ($asCollection) {
            return $groupsToSort;
        }
        foreach ($groupsToSort as $group) {
            $groups[] = ["gid" => $group->id, "name" => $group->name, "slug" => $group->slug, "routePath" => $group->getRoutePath()];
        }
        return $groups;
    }

    public function getProducts($productGroup, $includeConfigOptions = false, $includeBundles = false)
    {
        global $currency;
        $currency = Billing\Currency::factoryForClientArea();
        $unsortedProducts = [];
        $pricing = new Pricing();
        try {
            if (!$productGroup instanceof Product\Group) {
                $productGroup = Product\Group::findOrFail($productGroup);
            }
            if (!$productGroup instanceof Product\Group) {
                $productGroup = Product\Group::orderBy("order")->where("hidden", false)->firstOrFail();
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new Exception("NoProductGroup");
        }
        $productsCollection = $productGroup->products()->withCount(["recommendations" => function ($query) {
            $query->visible()->isNotRetired()->where(function ($query) {
                $query->where("stockcontrol", 0)->orWhere([["stockcontrol", 1], ["qty", ">", 0]]);
            });
        }])->where("hidden", false)->where("retired", false)->orderBy("order")->orderBy("name")->get();
        if (!$productsCollection) {
            $productsCollection = [];
        }
        require_once ROOTDIR . "/includes/orderfunctions.php";
        foreach ($productsCollection as $product) {
            $pricingInfo = getPricingInfo($product->id, $includeConfigOptions);
            $pricing->loadPricing("product", $product->id);
            $description = $this->formatProductDescription(Product\Product::getProductDescription($product->id, $product->description));
            $availableQty = $product->getClientStockLevel();
            if ($pricing->hasBillingCyclesAvailable() || $product->paymentType == "free") {
                $productRouteParts = $product->getRouteParts();
                $route = routePath($productRouteParts["route"], ...$productRouteParts["routeVariables"]);
                $unsortedProducts[$product->displayOrder][] = ["pid" => $product->id, "bid" => 0, "type" => $product->type, "name" => Product\Product::getProductName($product->id, $product->name), "description" => $description["original"], "features" => $description["features"], "featuresdesc" => $description["featuresdesc"], "paytype" => $product->paymentType, "pricing" => $pricingInfo, "freedomain" => $product->freeDomain, "freedomainpaymentterms" => $product->freeDomainPaymentTerms, "stockControlEnabled" => $product->stockControlEnabled, "qty" => $product->stockControlEnabled ? $availableQty : "", "isFeatured" => $product->isFeatured, "productUrl" => $route, "hasRecommendations" => Config\Setting::getValue("ProductRecommendationEnable") && Config\Setting::getValue("ProductRecommendationLocationAfterAdd") && 0 < $product->recommendations_count, "tagLine" => NULL, "proratadate" => NULL];
            }
        }
        if ($includeBundles) {
            foreach (Database\Capsule::table("tblbundles")->where("showgroup", "1")->where("gid", $productGroup->id)->get() as $bundle) {
                $description = $this->formatProductDescription($bundle->description);
                $convertedCurrency = convertCurrency($bundle->displayprice, 1, $currency["id"]);
                $price = new View\Formatter\Price($convertedCurrency, $currency);
                $displayPrice = 0 < $bundle->displayprice ? $price : "";
                $displayPriceSimple = 0 < $bundle->displayprice ? $price->toPrefixed() : "";
                $unsortedProducts[$bundle->sortorder][] = ["bid" => $bundle->id, "name" => $bundle->name, "description" => $description["original"], "features" => $description["features"], "featuresdesc" => $description["featuresdesc"], "displayprice" => $displayPrice, "displayPriceSimple" => $displayPriceSimple, "isFeatured" => (bool) $bundle->is_featured, "productUrl" => \DI::make("asset")->getWebRoot() . "/cart.php?a=add&bid=" . $bundle->id, "qty" => NULL, "hasRecommendations" => NULL, "tagLine" => NULL, "stockControlEnabled" => NULL, "proratadate" => NULL];
            }
        }
        if (empty($unsortedProducts)) {
            throw new Exception("NoProducts");
        }
        ksort($unsortedProducts);
        $products = [];
        foreach ($unsortedProducts as $items) {
            foreach ($items as $item) {
                $products[] = $item;
            }
        }
        return $products;
    }

    public function getFeaturePercentages($products)
    {
        $regex = "/[0-9]*\\.?[0-9]+/";
        $featureValues = [];
        foreach ($products as $productKey => $product) {
            foreach ($product["features"] as $featureKey => $feature) {
                $matches = [];
                if (preg_match($regex, $feature, $matches)) {
                    $featureAmount = $matches[0];
                } else {
                    $featureAmount = PHP_INT_MAX;
                }
                $featureValues[$featureKey][$productKey] = $featureAmount;
                asort($featureValues[$featureKey]);
            }
        }
        foreach ($featureValues as $featureKey => $feature) {
            if (in_array(PHP_INT_MAX, $feature)) {
                $highestValue = 1;
                foreach ($feature as $value) {
                    if ($value != PHP_INT_MAX) {
                        $highestValue = $value;
                    } else {
                        $featureValues[$featureKey] = str_replace(PHP_INT_MAX, $highestValue * 2, $feature);
                    }
                }
            }
        }
        foreach ($featureValues as $featureKey => $feature) {
            $highestValue = array_pop($feature);
            foreach ($feature as $productKey => $value) {
                $featureValues[$featureKey][$productKey] = 0;
                if (0 < $highestValue) {
                    $featureValues[$featureKey][$productKey] = (int) ($value / $highestValue * 100);
                }
            }
        }
        return $featureValues;
    }

    protected function formatProductDescription($desc)
    {
        $features = [];
        $featuresdesc = "";
        $descriptionlines = explode("\n", $desc);
        foreach ($descriptionlines as $line) {
            if (strpos($line, ":")) {
                $line = explode(":", $line, 2);
                $features[trim($line[0])] = trim($line[1]);
            } else {
                if (trim($line)) {
                    $featuresdesc .= $line . "\n";
                }
            }
        }
        return ["original" => nl2br($desc), "features" => $features, "featuresdesc" => nl2br($featuresdesc)];
    }

    public function getProductGroupInfo($gid)
    {
        $result = select_query("tblproductgroups", "", ["id" => $gid]);
        $data = mysql_fetch_assoc($result);
        if (!is_array($data) || empty($data["id"])) {
            return false;
        }
        return $data;
    }

    public function setPid($pid, Product\Product $product = NULL)
    {
        $this->pid = $pid;
        if (is_null($product)) {
            $product = Product\Product::with("productGroup")->withCount("recommendations")->where("id", $pid)->where("retired", false)->first();
        } else {
            $product->loadMissing("productGroup");
            if ($product->isRetired) {
                $product = NULL;
            }
        }
        if (!$product) {
            return [];
        }
        $data = ["pid" => $product->id, "gid" => $product->productGroupId, "type" => $product->type, "name" => $product->name, "group_name" => $product->productGroup->name, "description" => $this->formatProductDescription($product->description)["original"], "showdomainoptions" => $product->showDomainOptions, "freedomain" => $product->freeDomain, "freedomainpaymentterms" => $product->freeDomainPaymentTerms, "freedomaintlds" => $product->freeDomainTlds, "subdomain" => $product->freeSubDomains, "stockcontrol" => $product->stockControlEnabled, "qty" => $product->getClientStockLevel(), "allowqty" => $product->allowMultipleQuantities, "paytype" => $product->paymentType, "orderfrmtpl" => $product->productGroup->orderFormTemplate, "module" => $product->module, "metrics" => $product->billedMetrics, "hasRecommendations" => Config\Setting::getValue("ProductRecommendationEnable") && Config\Setting::getValue("ProductRecommendationLocationAfterAdd") && 0 < $product->recommendations_count];
        if (!$data["stockcontrol"]) {
            $data["qty"] = 0;
        }
        $this->productinfo = $data;
        return $this->productinfo;
    }

    public function setAddonId($addonId)
    {
        $addon = Product\Addon::where("id", $addonId)->where("retired", false)->first();
        if (!$addon) {
            return [];
        }
        $this->addonId = $addonId;
        $this->addonInfo = ["id" => $addon->id, "name" => $addon->name, "description" => $addon->description, "allowqty" => $addon->allowMultipleQuantities, "paytype" => $addon->billingCycle, "module" => $addon->module];
        return $this->addonInfo;
    }

    public function getProductInfo($var = "")
    {
        return $var ? $this->productinfo[$var] : $this->productinfo;
    }

    public function getAddonIndo($var = "")
    {
        return $var ? $this->addonInfo[$var] : $this->addonInfo;
    }

    public function validateBillingCycle($billingcycle)
    {
        global $currency;
        if (empty($currency)) {
            $currency = Billing\Currency::factoryForClientArea();
        }
        if ($billingcycle && in_array($billingcycle, $this->validbillingcycles)) {
            return $billingcycle;
        }
        $paytype = $this->productinfo["paytype"];
        $result = select_query("tblpricing", "", ["type" => "product", "currency" => $currency["id"], "relid" => $this->productinfo["pid"]]);
        $data = mysql_fetch_array($result);
        $monthly = $data["monthly"];
        $quarterly = $data["quarterly"];
        $semiannually = $data["semiannually"];
        $annually = $data["annually"];
        $biennially = $data["biennially"];
        $triennially = $data["triennially"];
        if ($paytype == "free") {
            $billingcycle = "free";
        } else {
            if ($paytype == "onetime") {
                $billingcycle = "onetime";
            } else {
                if ($paytype == "recurring") {
                    if (0 <= $monthly) {
                        $billingcycle = "monthly";
                    } else {
                        if (0 <= $quarterly) {
                            $billingcycle = "quarterly";
                        } else {
                            if (0 <= $semiannually) {
                                $billingcycle = "semiannually";
                            } else {
                                if (0 <= $annually) {
                                    $billingcycle = "annually";
                                } else {
                                    if (0 <= $biennially) {
                                        $billingcycle = "biennially";
                                    } else {
                                        if (0 <= $triennially) {
                                            $billingcycle = "triennially";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $billingcycle;
    }

    public function getNumItemsInCart(User\Client $client = NULL)
    {
        if (!$client) {
            $client = \Auth::client();
        }
        $products = $this->getCartDataByKey("products", []);
        $numAddons = 0;
        foreach ($products as $key => $product) {
            if (isset($product["noconfig"]) && $product["noconfig"] === true) {
                unset($products[$key]);
            }
            if (!empty($product["addons"])) {
                $numAddons += count($product["addons"]);
            }
        }
        $domains = $this->getCartDataByKey("domains", []);
        $numDomainRenewals = $numUpgrades = 0;
        if (!is_null($client)) {
            $serviceIds = NULL;
            $cartAddons = $this->getCartDataByKey("addons", []);
            if (0 < count($cartAddons)) {
                $serviceIds = $client->services()->pluck("id");
                foreach ($cartAddons as $addon) {
                    if ($serviceIds->contains($addon["productid"])) {
                        $numAddons++;
                    }
                }
            }
            $renewals = $this->getCartDataByKey("renewals", []);
            if (0 < count($renewals)) {
                $domainIds = $client->domains()->pluck("id");
                foreach ($renewals as $renewalId => $regPeriod) {
                    if ($domainIds->contains($renewalId)) {
                        $numDomainRenewals++;
                    }
                }
            }
            $upgrades = $this->getCartDataByKey("upgrades", []);
            if (0 < count($upgrades)) {
                if (is_null($serviceIds)) {
                    $serviceIds = $client->services()->pluck("id");
                }
                $addonIds = $client->addons()->pluck("id");
                foreach ($upgrades as $upgrade) {
                    $entityType = $upgrade["upgrade_entity_type"];
                    $entityId = $upgrade["upgrade_entity_id"];
                    if ($entityType == "service" && $serviceIds->contains($entityId) || $entityType == "addon" && $addonIds->contains($entityId)) {
                        $numUpgrades++;
                    }
                }
            }
        }
        return count($products) + count($domains) + $numAddons + $numDomainRenewals + $numUpgrades;
    }

    public static function addToCart($type, $parameters)
    {
        if (!in_array($type, ["product", "addon", "upgrade"])) {
            throw new Exception("Invalid product type.");
        }
        $cart = new self();
        $cartData = $cart->getCartData();
        $cartData[$type . "s"][] = $parameters;
        Session::set("cart", $cartData);
    }

    public static function addProductToCart($productId, $billingCycle, $domain, $extra = [])
    {
        $cartData = array_merge(["pid" => $productId, "billingcycle" => $billingCycle, "domain" => $domain], $extra);
        self::addToCart("product", $cartData);
    }

    public static function addAddonToCart($addonId, $serviceId, $billingCycle, $extra = [])
    {
        $cartData = array_merge(["id" => $addonId, "productid" => $serviceId, "billingcycle" => $billingCycle], $extra);
        self::addToCart("addon", $cartData);
    }

    public static function addUpgradeToCart($upgradeEntityType, int $upgradeEntityId, int $targetEntityId, $billingCycle, int $quantity = 1, int $minimumQuantity = 1)
    {
        self::addToCart("upgrade", ["upgrade_entity_type" => $upgradeEntityType, "upgrade_entity_id" => $upgradeEntityId, "target_entity_id" => $targetEntityId, "billing_cycle" => $billingCycle, "quantity" => $quantity, "minimumQuantity" => $minimumQuantity]);
    }

    public function startExpressCheckout($gateway, $metaData, $payerData)
    {
        $expressCheckout = ["gateway" => $gateway, "metaData" => $metaData];
        Session::set("expressCheckout", $expressCheckout);
        $cartData = $this->getCartData();
        $cartData["user"]["firstname"] = Input\Sanitize::encode($payerData["firstname"]);
        $cartData["user"]["lastname"] = Input\Sanitize::encode($payerData["lastname"]);
        $cartData["user"]["email"] = Input\Sanitize::encode($payerData["email"]);
        $cartData["user"]["address1"] = Input\Sanitize::encode($payerData["address1"]);
        $cartData["user"]["address2"] = Input\Sanitize::encode($payerData["address2"]);
        $cartData["user"]["city"] = Input\Sanitize::encode($payerData["city"]);
        $cartData["user"]["state"] = Input\Sanitize::encode($payerData["state"]);
        $cartData["user"]["postcode"] = Input\Sanitize::encode($payerData["postcode"]);
        $cartData["user"]["country"] = Input\Sanitize::encode($payerData["country"]);
        Session::set("cart", $cartData);
        $client = \Auth::client();
        if (is_null($client) && $payerData["email"]) {
            $emailExists = User\Client::email($payerData["email"])->first();
            if ($emailExists) {
                Session::set("expressExistingUser", true);
                return Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=checkout#login";
            }
        }
        return Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=checkout";
    }

    public function cancelExpressCheckout()
    {
        Session::delete("expressCheckout");
    }

    public function expressCheckoutCompleted()
    {
        Session::delete("expressCheckout");
    }

    public function inExpressCheckout()
    {
        return Session::exists("expressCheckout");
    }

    public function getExpressCheckoutData()
    {
        return Session::get("expressCheckout");
    }

    public function getExpressCheckoutGateway()
    {
        return $this->getExpressCheckoutData()["gateway"];
    }

    public function getRecommendationsByLocation($products, $location)
    {
        switch ($location) {
            case "viewcart":
            case "checkout":
                $recommendations = [];
                $viewedLocations = ["viewcart" => false, "checkout" => false];
                if (isset($_SESSION["cart"]["locations"]) && is_array($_SESSION["cart"]["locations"])) {
                    $viewedLocations = $_SESSION["cart"]["locations"];
                }
                $viewCartLoc = false;
                if ($location == "viewcart" && !$viewedLocations["viewcart"]) {
                    $viewCartLoc = Config\Setting::getValue("ProductRecommendationEnable") && Config\Setting::getValue("ProductRecommendationLocationViewCart");
                }
                $checkoutLoc = false;
                if ($location == "checkout" && !$viewedLocations["checkout"]) {
                    $checkoutLoc = Config\Setting::getValue("ProductRecommendationEnable") && Config\Setting::getValue("ProductRecommendationLocationCheckout");
                }
                if (!empty($products) && ($viewCartLoc || $checkoutLoc)) {
                    $recommendations = (new OrderForm())->getRecommendationsData(collect($products)->pluck("pid")->toArray(), [], true, Config\Setting::getValue("ProductRecommendationStyle"));
                }
                if ($viewCartLoc) {
                    $viewedLocations["viewcart"] = true;
                } else {
                    if ($checkoutLoc) {
                        $viewedLocations["checkout"] = true;
                    }
                }
                $_SESSION["cart"]["locations"] = $viewedLocations;
                break;
            case "complete":
                $recommendations = (new OrderForm())->getRecommendationsData($products, [], false);
                break;
            default:
                $recommendations = [];
                return $recommendations;
        }
    }

    public function getRecommendationsData($products, $cartProducts, $ignoreOwnedProducts = false, $showDuplicates)
    {
        $uniqueCartProducts = collect($products)->map(function ($item) {
            return ["id" => $item, "origin" => "order"];
        });
        $authClient = \Auth::client();
        $ownedProducts = collect();
        if (!$ignoreOwnedProducts && $authClient && Config\Setting::getValue("ProductRecommendationExisting")) {
            $uniqueCartProducts = $uniqueCartProducts->merge($authClient->services()->pluck("packageId")->map(function ($item) {
                return ["id" => $item, "origin" => "own"];
            }));
        }
        $uniqueCartProducts = $uniqueCartProducts->unique("id");
        $usedRecommendations = collect();
        $uniqueRecommendations = ["order" => [], "own" => []];
        $recommendationLimit = (int) Config\Setting::getValue("ProductRecommendationCount");
        foreach (Product\Product::query()->findMany($uniqueCartProducts->pluck("id")) as $uniqueCartProduct) {
            $ignoredProducts = $ownedProducts->merge($uniqueCartProducts->pluck("id"))->merge($cartProducts);
            if (!$showDuplicates) {
                $ignoredProducts = $ignoredProducts->merge($usedRecommendations->pluck("id"));
            }
            $recommendations = $uniqueCartProduct->recommendations()->visible()->isNotRetired()->where(function ($query) {
                $query->where("stockcontrol", 0)->orWhere([["stockcontrol", 1], ["qty", ">", 0]]);
            })->orderBy("pivot_sortorder")->get()->whereNotIn("id", $ignoredProducts->unique());
            if ($recommendations->count() != 0) {
                if ($recommendationLimit != 0) {
                    $origin = $uniqueCartProducts->where("id", $uniqueCartProduct->id)->pluck("origin")->first();
                    $uniqueRecommendations[$origin][$uniqueCartProduct->id] = ["name" => $uniqueCartProduct->name, "recommendations" => []];
                    foreach ($recommendations as $product) {
                        if ($recommendationLimit == 0) {
                        } else {
                            $uniqueRecommendations[$origin][$uniqueCartProduct->id]["recommendations"][] = $product;
                            $usedRecommendations->push(["id" => $product->id]);
                            $recommendationLimit--;
                        }
                    }
                }
                return $uniqueRecommendations;
            }
        }
    }

    public static function cartPreventDuplicateProduct($inboundProduct, $inboundDomain)
    {
        if (strlen($inboundDomain) === 0) {
            return NULL;
        }
        if (!$inboundProduct instanceof Product\Product && !is_int($inboundProduct)) {
            throw new \InvalidArgumentException("Expecting int or Product");
        }
        $ourself = new static();
        $segment = "products";
        $items = $ourself->getCartDataByKey($segment, NULL);
        if (!is_array($items)) {
            return NULL;
        }
        $pids = array_column($items, "pid");
        if (is_int($inboundProduct)) {
            $pids[] = $inboundProduct;
        }
        $pids = array_unique($pids);
        $products = $ourself->loadProducts($pids);
        unset($pids);
        if (is_int($inboundProduct)) {
            if (!$products->has($inboundProduct)) {
                return NULL;
            }
            $inboundProduct = $products->get($inboundProduct);
        }
        $items = $ourself->discardCartItemsCallback($items, function ($item) use($inboundProduct, $inboundDomain, $products) {
            if (!$products->has($item["pid"])) {
                return false;
            }
            $product = $products->get($item["pid"]);
            $shouldDedupeByDomain = function ($type) {
                return in_array($type, static::getDomainDedupeProductTypes());
            };
            if ($shouldDedupeByDomain($inboundProduct->type) && $shouldDedupeByDomain($product->type)) {
                return isset($item["domain"]) && $item["domain"] == $inboundDomain;
            }
            return false;
        });
        $ourself->setCartDataByKey($segment, $items);
    }

    public static function getDomainDedupeProductTypes()
    {
        return [Product\Product::TYPE_SHARED, Product\Product::TYPE_RESELLER, Product\Product::TYPE_SERVERS];
    }

    protected function loadProducts($Collection, $productIds)
    {
        return Product\Product::whereIn("id", $productIds)->get()->keyBy("id");
    }

    public static function cartPreventDuplicateDomain($domain)
    {
        if (strlen($domain) === 0) {
            return NULL;
        }
        $segment = "domains";
        $ourself = new static();
        $items = $ourself->getCartDataByKey($segment, NULL);
        if (!is_array($items)) {
            return NULL;
        }
        $items = $ourself->discardCartItemsCallback($items, function ($item) use($domain) {
            return isset($item["domain"]) && $item["domain"] == $domain;
        });
        $ourself->setCartDataByKey($segment, $items);
    }

    protected function discardCartItemsCallback($items, $callback)
    {
        return array_values(array_filter($items, function ($item) use($callback) {
            return !call_user_func($callback, $item);
        }));
    }
}
