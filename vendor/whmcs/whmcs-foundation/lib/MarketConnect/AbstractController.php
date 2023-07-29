<?php

namespace WHMCS\MarketConnect;

abstract class AbstractController
{
    protected $serviceName = "";
    protected $langPrefix = "";

    public function __construct()
    {
        $this->langPrefix = MarketConnect::getVendorSystemName($this->langPrefix);
    }

    public function isAdminPreview()
    {
        return \App::getFromRequest("preview") && \WHMCS\User\Admin::getAuthenticatedUser();
    }

    protected function getTemplateDirectory()
    {
        return MarketConnect::getVendorSystemName($this->serviceName);
    }

    public function isActiveService()
    {
        $service = Service::where("name", $this->serviceName)->first();
        return !is_null($service) && $service->status;
    }

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $isAdminPreview = $this->isAdminPreview();
        if (!$isAdminPreview && !$this->isActiveService()) {
            return \WHMCS\Http\RedirectResponse::legacyPath("index.php");
        }
        $ca = new Output\ClientArea();
        $ca->setPageTitle(\Lang::trans("store." . $this->langPrefix . ".title"));
        $ca->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $ca->addToBreadCrumb(routePath("store"), \Lang::trans("navStore"));
        $ca->addToBreadCrumb(routePath("store-product-group", MarketConnect::getServiceProductGroupSlug($this->serviceName)), \Lang::trans("store." . $this->langPrefix . ".title"));
        $ca->initPage();
        $promoHelper = MarketConnect::factoryPromotionalHelper($this->serviceName);
        $productType = MarketConnect::getVendorSystemName($this->serviceName);
        $plans = \WHMCS\Product\Product::$productType()->visible()->orderBy("order")->get();
        if ($isAdminPreview && !$plans->count()) {
            $plans = (new ServicesFeed())->getEmulationOfConfiguredProducts($this->serviceName);
        }
        $currency = \Currency::factoryForClientArea();
        $ca->assign("activeCurrency", $currency);
        foreach ($plans as $key => $plan) {
            $plan->features = $promoHelper->getPlanFeatures($plan->configoption1);
            if (!$isAdminPreview) {
                $pricing = $plan->pricing($currency);
                if (!$pricing->best()) {
                    unset($plans[$key]);
                }
            }
        }
        $ca->assign("plans", $plans);
        $ca->assign("promoHelper", $promoHelper);
        $ca->assign("inPreview", $isAdminPreview);
        $ca->assign("routePathSlug", MarketConnect::getServiceProductGroupSlug($this->serviceName));
        $ca->setTemplate("store/" . $this->getTemplateDirectory() . "/index");
        $ca->skipMainBodyContainer();
        return $ca;
    }

    public function getLangPrefix()
    {
        return $this->langPrefix;
    }
}
