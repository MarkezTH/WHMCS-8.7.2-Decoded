<?php

namespace WHMCS\MarketConnect;

class WeeblyController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_WEEBLY;
    protected $langPrefix = "websiteBuilder";

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = parent::index($request);
        if ($ca instanceof \Laminas\Diactoros\Response\RedirectResponse) {
            return $ca;
        }
        $enabledCycles = [];
        $all = $ca->retrieve("plans");
        $promoHelper = $ca->retrieve("promoHelper");
        $currency = $ca->retrieve("activeCurrency");
        foreach ($all as $key => $product) {
            $all[$key]->idealFor = $promoHelper->getIdealFor($product->productKey);
            $all[$key]->siteFeatures = $promoHelper->getSiteFeatures($product->productKey);
            $all[$key]->ecommerceFeatures = $promoHelper->getEcommerceFeatures($product->productKey);
            foreach ($product->pricing($currency)->allAvailableCycles() as $price) {
                $cycle = $price->cycle();
                if (!in_array($cycle, $enabledCycles)) {
                    $enabledCycles[] = $cycle;
                }
            }
        }
        $billingCycles = (new \WHMCS\Billing\Cycles())->getRecurringSystemBillingCycles();
        foreach ($billingCycles as $key => $cycle) {
            if (!in_array($cycle, $enabledCycles)) {
                unset($billingCycles[$key]);
            }
        }
        $litePlan = NULL;
        foreach ($all as $key => $product) {
            if ($product->productKey == Promotion\Service\Weebly::WEEBLY_LITE) {
                unset($all[$key]);
            } else {
                if ($product->productKey == Promotion\Service\Weebly::WEEBLY_FREE) {
                    $litePlan = $product;
                    unset($all[$key]);
                }
            }
        }
        $ca->assign("litePlan", $litePlan);
        $ca->assign("products", $all);
        $ca->assign("billingCycles", $billingCycles);
        return $ca;
    }

    public function upgrade(\WHMCS\Http\Message\ServerRequest $request)
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
        $weeblyPromoHelper = MarketConnect::factoryPromotionalHelper("weebly");
        $ca->assign("promo", $weeblyPromoHelper->getUpsellPromotionalContent($upgradePlanProductKey));
        $clientPromoHelper = new Promotion\Helper\Client(\Auth::client()->id);
        $services = $clientPromoHelper->getServices("weebly");
        $ca->assign("weeblyServices", $services);
        $ca->setPageTitle(\Lang::trans("store.websiteBuilder.upgrade.title"));
        $ca->setTemplate("store/weebly/upgrade");
        $ca->skipMainBodyContainer();
        return $ca;
    }

    protected function setUpgradeProductKeyByPlan($plan)
    {
        if (strtolower($plan) == "business") {
            $upgradePlanProductKey = Promotion\Service\Weebly::WEEBLY_BUSINESS;
        } else {
            if (strtolower($plan) == "pro") {
                $upgradePlanProductKey = Promotion\Service\Weebly::WEEBLY_PRO;
            } else {
                $upgradePlanProductKey = Promotion\Service\Weebly::WEEBLY_STARTER;
            }
        }
        \WHMCS\Session::set("weeblyUpgradeProductKey", $upgradePlanProductKey);
    }

    protected function setUpgradeProductKeyByServiceId($serviceId)
    {
        $currentProductKey = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->where("id", $serviceId)->first()->product->moduleConfigOption1;
        $upgradePlanProductKey = $this->getUpgradePlanProductKey($currentProductKey);
        if (!$upgradePlanProductKey) {
            \App::redirect("index.php");
        }
        \WHMCS\Session::set("weeblyUpgradeProductKey", $upgradePlanProductKey);
    }

    protected function setUpgradeProductKeyByAddonIdAndServiceId($addonId, $serviceId)
    {
        $addon = \WHMCS\Service\Addon::userId(\Auth::client()->id)->ofService($serviceId)->where("id", $addonId)->first();
        $currentProductKey = $addon->productAddon->moduleConfiguration()->where("setting_name", "configoption1")->first()->value;
        $upgradePlanProductKey = $this->getUpgradePlanProductKey($currentProductKey);
        if (!$upgradePlanProductKey) {
            \App::redirect("index.php");
        }
        \WHMCS\Session::set("weeblyUpgradeProductKey", $upgradePlanProductKey);
    }

    protected function getUpgradePlanProductKey($currentProductKey)
    {
        switch ($currentProductKey) {
            case Promotion\Service\Weebly::WEEBLY_FREE:
            case Promotion\Service\Weebly::WEEBLY_LITE:
                return Promotion\Service\Weebly::WEEBLY_STARTER;
                break;
            case Promotion\Service\Weebly::WEEBLY_STARTER:
                return Promotion\Service\Weebly::WEEBLY_PRO;
                break;
            case Promotion\Service\Weebly::WEEBLY_PRO:
                return Promotion\Service\Weebly::WEEBLY_BUSINESS;
                break;
            default:
                return "";
        }
    }

    protected function getUpgradeProductKey()
    {
        $upgradePlanProductKey = \WHMCS\Session::get("weeblyUpgradeProductKey");
        if (!in_array($upgradePlanProductKey, Promotion\Service\Weebly::WEEBLY_PAID)) {
            $upgradePlanProductKey = Promotion\Service\Weebly::WEEBLY_STARTER;
        }
        return $upgradePlanProductKey;
    }

    public function orderUpgrade(\WHMCS\Http\Message\ServerRequest $request)
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
                throw new \Exception("Could not find addon product configured for Weebly upgrade plan: " . $upgradePlanProductKey);
            }
        } else {
            $upgradeProduct = \WHMCS\Product\Product::marketConnect()->visible()->productKey($upgradePlanProductKey)->first();
            \WHMCS\OrderForm::addUpgradeToCart("service", $serviceId, $upgradeProduct->id, $upgradeProduct->getAvailableBillingCycles()[0]);
        }
        $redirectPath = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=view";
        return new \Laminas\Diactoros\Response\RedirectResponse($redirectPath);
    }
}
