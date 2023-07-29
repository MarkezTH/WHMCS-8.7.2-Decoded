<?php

namespace WHMCS\MarketConnect;

class SiteBuilderController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_SITEBUILDER;
    protected $langPrefix = "siteBuilder";
    protected $templateDemos = ["single" => ["barber-shop", "bike-event", "childcare", "conference", "creative-portfolio", "dj", "gardener", "makeup-artist", "painters", "photographer", "rock-band", "seafood-restaurant", "sushi-restaurant", "tailor-shop", "training-courses", "travel-tours", "wedding-planner", "writer"], "multi" => ["architect", "beauty-salon", "biography", "blog-page", "burger-cafe", "car-dealer", "catering-services", "city-hotel", "cleaning-services", "coffee-house", "crossfit", "dentist-v2", "event-venue", "handyman", "life-coach", "local-cafe", "locksmith", "mobile-app", "mortgage-brokers", "landscape-photographer", "real-estate", "spa", "villa-rental", "wedding-event"], "ecom" => ["animal-groomers", "bakery", "beauty-store", "blinds", "bookstore", "furniture-collection", "grape-farm", "grocery-store", "home-decor", "toy-store", "tyre-repairs"]];

    protected function getTemplateDirectory()
    {
        return "sitebuilder";
    }

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = parent::index($request);
        if ($ca instanceof \Laminas\Diactoros\Response\RedirectResponse) {
            return $ca;
        }
        $all = $ca->retrieve("plans");
        $promoHelper = $ca->retrieve("promoHelper");
        $currency = $ca->retrieve("activeCurrency");
        $trialPlan = NULL;
        foreach ($all as $key => $product) {
            if ($product->productKey == Promotion\Service\SiteBuilder::SITEBUILDER_TRIAL) {
                $trialPlan = $product;
                unset($all[$key]);
            }
        }
        $ca->assign("trialPlan", $trialPlan);
        $ca->assign("plans", $all);
        $templates = [];
        foreach ($this->templateDemos as $category => $names) {
            if ($category === "ecom") {
                $thumbnailCat = "ecommerce";
                $previewSuffix = "-ecommerce";
            } else {
                if ($category === "multi") {
                    $thumbnailCat = "multipage";
                    $previewSuffix = "";
                } else {
                    $thumbnailCat = "singlepage";
                    $previewSuffix = "-single-page";
                }
            }
            foreach ($names as $name) {
                $templates[] = ["type" => $category, "name" => \Lang::trans("store.siteBuilder.templates." . $name), "thumbnail" => "https://thumbnails.sitebuilder.website/" . $thumbnailCat . "/" . $name . ".png", "preview" => "https://" . $name . $previewSuffix . ".sitebuilder.website/"];
            }
        }
        shuffle($templates);
        $ca->assign("templates", $templates);
        return $ca;
    }

    public function upgrade($ClientArea, $request)
    {
        $ca = new Output\ClientArea();
        $user_id = $request->query()->get("user_id");
        $site = $request->query()->get("site");
        $plan = $request->query()->get("plan");
        $upgrade_type = $request->query()->get("upgrade_type");
        $upgrade_id = $request->query()->get("upgrade_id");
        $plan_ids = $request->query()->get("plan_ids");
        $serviceId = $request->request()->get("serviceid");
        $addonId = $request->request()->get("addonId");
        $ca->assign("incorrect", $request->query()->get("incorrect", false));
        if ($serviceId && !$addonId) {
            $this->setUpgradeProductKeyByServiceId($serviceId);
        } else {
            if ($serviceId && $addonId) {
                $this->setUpgradeProductKeyByAddonIdAndServiceId($addonId, $serviceId);
            } else {
                if ($plan) {
                    $this->setUpgradeProductKeyByPlan($plan);
                }
            }
        }
        $upgradePlanProductKey = $this->getUpgradeProductKey();
        $upgradeProduct = \WHMCS\Product\Product::marketConnect()->visible()->productKey($upgradePlanProductKey)->first();
        $ca->assign("product", $upgradeProduct);
        $siteBuilderPromoHelper = MarketConnect::factoryPromotionalHelper(MarketConnect::SERVICE_SITEBUILDER);
        $ca->assign("promo", $siteBuilderPromoHelper->getUpsellPromotionalContent($upgradePlanProductKey));
        $clientPromoHelper = new Promotion\Helper\Client(\Auth::client()->id);
        $services = $clientPromoHelper->getServices(MarketConnect::SERVICE_SITEBUILDER);
        $ca->assign("siteBuilderServices", $services);
        $ca->setPageTitle(\Lang::trans("store.siteBuilder.upgrade.title"));
        $ca->setTemplate("store/sitebuilder/upgrade");
        $ca->skipMainBodyContainer();
        return $ca;
    }

    protected function setUpgradeProductKeyByPlan($plan)
    {
        if (strtolower($plan) == "shop") {
            $upgradePlanProductKey = Promotion\Service\SiteBuilder::SITEBUILDER_STORE;
        } else {
            if (strtolower($plan) == "unlimited") {
                $upgradePlanProductKey = Promotion\Service\SiteBuilder::SITEBUILDER_UNLIMITED;
            } else {
                $upgradePlanProductKey = Promotion\Service\SiteBuilder::SITEBUILDER_ONE_PAGE;
            }
        }
        \WHMCS\Session::set("siteBuilderUpgradeProductKey", $upgradePlanProductKey);
    }

    protected function setUpgradeProductKeyByServiceId($serviceId)
    {
        $currentProductKey = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->where("id", $serviceId)->first()->product->moduleConfigOption1;
        $upgradePlanProductKey = $this->getUpgradePlanProductKey($currentProductKey);
        if (!$upgradePlanProductKey) {
            \App::redirect("index.php");
        }
        \WHMCS\Session::set("siteBuilderUpgradeProductKey", $upgradePlanProductKey);
    }

    protected function setUpgradeProductKeyByAddonIdAndServiceId($addonId, int $serviceId)
    {
        $addon = \WHMCS\Service\Addon::userId(\Auth::client()->id)->ofService($serviceId)->where("id", $addonId)->first();
        $currentProductKey = $addon->productAddon->moduleConfiguration()->where("setting_name", "configoption1")->first()->value;
        $upgradePlanProductKey = $this->getUpgradePlanProductKey($currentProductKey);
        if (!$upgradePlanProductKey) {
            \App::redirect("index.php");
        }
        \WHMCS\Session::set("siteBuilderUpgradeProductKey", $upgradePlanProductKey);
    }

    protected function getUpgradePlanProductKey($currentProductKey)
    {
        switch ($currentProductKey) {
            case Promotion\Service\SiteBuilder::SITEBUILDER_TRIAL:
                return Promotion\Service\SiteBuilder::SITEBUILDER_ONE_PAGE;
                break;
            case Promotion\Service\SiteBuilder::SITEBUILDER_ONE_PAGE:
                return Promotion\Service\SiteBuilder::SITEBUILDER_UNLIMITED;
                break;
            case Promotion\Service\SiteBuilder::SITEBUILDER_UNLIMITED:
                return Promotion\Service\SiteBuilder::SITEBUILDER_STORE;
                break;
            case Promotion\Service\SiteBuilder::SITEBUILDER_STORE:
                return Promotion\Service\SiteBuilder::SITEBUILDER_STORE_PLUS;
                break;
            case Promotion\Service\SiteBuilder::SITEBUILDER_STORE_PLUS:
                return Promotion\Service\SiteBuilder::SITEBUILDER_STORE_PREMIUM;
                break;
            default:
                return "";
        }
    }

    protected function getUpgradeProductKey()
    {
        $upgradePlanProductKey = \WHMCS\Session::get("siteBuilderUpgradeProductKey");
        if (!in_array($upgradePlanProductKey, Promotion\Service\SiteBuilder::SITEBUILDER_PAID)) {
            $upgradePlanProductKey = Promotion\Service\SiteBuilder::SITEBUILDER_ONE_PAGE;
        }
        return $upgradePlanProductKey;
    }

    public function orderUpgrade($RedirectResponse, $request)
    {
        $service = $request->request()->get("service");
        $parts = explode("-", $service, 2);
        $serviceType = isset($parts[0]) ? $parts[0] : NULL;
        $serviceId = isset($parts[1]) ? $parts[1] : NULL;
        $upgradePlanProductKey = $this->getUpgradeProductKey();
        if ($serviceType == "addon") {
            $addon = \WHMCS\Service\Addon::find($serviceId);
            $addonModel = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->where("value", $upgradePlanProductKey)->get()->where("productAddon.module", "marketconnect")->first();
            if (!is_null($addonModel)) {
                $addonModel = $addonModel->productAddon;
                \WHMCS\OrderForm::addUpgradeToCart("addon", $serviceId, $addonModel->id, $addonModel->getAvailableBillingCycles()[0]);
            } else {
                throw new \Exception("Could not find an addon product configured for the Site Builder upgrade plan: " . $upgradePlanProductKey);
            }
        } else {
            $upgradeProduct = \WHMCS\Product\Product::marketConnect()->visible()->productKey($upgradePlanProductKey)->first();
            \WHMCS\OrderForm::addUpgradeToCart("service", $serviceId, $upgradeProduct->id, $upgradeProduct->getAvailableBillingCycles()[0]);
        }
        $redirectPath = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=view";
        return new \WHMCS\Http\RedirectResponse($redirectPath);
    }
}
