<?php

namespace WHMCS\MarketConnect;

class SpamExpertsController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_SPAMEXPERTS;
    protected $langPrefix = "emailServices";

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = parent::index($request);
        if ($ca instanceof \Laminas\Diactoros\Response\RedirectResponse) {
            return $ca;
        }
        $currency = $ca->retrieve("activeCurrency");
        $all = $ca->retrieve("plans");
        $isAdminPreview = $ca->retrieve("inPreview");
        $products = [];
        foreach (Promotion\Service\SpamExperts::SPAMEXPERTS_ALL_NO_PREFIX as $key => $productKey) {
            $products[$productKey] = $all->where("configoption1", Promotion\Service\SpamExperts::SPAMEXPERTS_ALL[$key])->first();
            if (!is_null($products[$productKey])) {
                if (!$isAdminPreview) {
                    $pricing = $products[$productKey]->pricing($currency);
                    if (!$pricing->best()) {
                        unset($products[$productKey]);
                    }
                }
            }
        }
        $upgradeOptions = [Promotion\Service\SpamExperts::PACKAGE_IN => [["product" => Promotion\Service\SpamExperts::PACKAGE_INOUT, "label" => \Lang::trans("store.emailServices.options.outgoingFilter")], ["product" => Promotion\Service\SpamExperts::PACKAGE_INARCHIVING, "label" => \Lang::trans("store.emailServices.options.incomingArchive")], ["product" => Promotion\Service\SpamExperts::PACKAGE_INOUTARCHIVING, "label" => \Lang::trans("store.emailServices.options.outgoingFilterArchive")]], Promotion\Service\SpamExperts::PACKAGE_OUT => [["product" => Promotion\Service\SpamExperts::PACKAGE_INOUT, "label" => \Lang::trans("store.emailServices.options.incomingFilter")], ["product" => Promotion\Service\SpamExperts::PACKAGE_OUTARCHIVING, "label" => \Lang::trans("store.emailServices.options.outgoingArchive")], ["product" => Promotion\Service\SpamExperts::PACKAGE_INOUTARCHIVING, "label" => \Lang::trans("store.emailServices.options.incomingFilterArchive")]]];
        $options = [Promotion\Service\SpamExperts::PACKAGE_IN => [], Promotion\Service\SpamExperts::PACKAGE_OUT => []];
        foreach ($upgradeOptions as $type => $upgrades) {
            foreach ($upgrades as $upgrade) {
                if ($products[$type] && $products[$upgrade["product"]]) {
                    $bundlePricing = $products[$upgrade["product"]]->pricing($currency);
                    $singlePricing = $products[$type]->pricing($currency);
                    if ($bundlePricing->monthly() && $singlePricing->monthly()) {
                        $bundlePriceNum = (double) $bundlePricing->monthly()->price()->toNumeric();
                        $singlePriceNum = (double) $singlePricing->monthly()->price()->toNumeric();
                        $pricing = new \WHMCS\Product\Pricing\Price(["price" => new \WHMCS\View\Formatter\Price($bundlePriceNum - $singlePriceNum, $currency), "cycle" => "monthly", "currency" => $currency]);
                    } else {
                        if ($bundlePricing->annually() && $singlePricing->annually()) {
                            $bundlePriceNum = (double) $bundlePricing->annually()->price()->toNumeric();
                            $singlePriceNum = (double) $singlePricing->annually()->price()->toNumeric();
                            $pricing = new \WHMCS\Product\Pricing\Price(["price" => new \WHMCS\View\Formatter\Price($bundlePriceNum - $singlePriceNum, $currency), "cycle" => "annually", "currency" => $currency]);
                        } else {
                            $pricing = NULL;
                        }
                    }
                    if ($pricing) {
                        $options[$type][] = ["product" => $upgrade["product"], "description" => $upgrade["label"], "pricing" => $pricing];
                    }
                }
            }
        }
        $numberOfFeaturedProducts = 0;
        foreach ([Promotion\Service\SpamExperts::PACKAGE_IN, Promotion\Service\SpamExperts::PACKAGE_OUT] as $productKey) {
            if (!is_null($products[$productKey])) {
                $numberOfFeaturedProducts++;
            }
        }
        if (!is_null($products[Promotion\Service\SpamExperts::PACKAGE_INARCHIVING]) || !is_null($products[Promotion\Service\SpamExperts::PACKAGE_OUTARCHIVING]) || !is_null($products[Promotion\Service\SpamExperts::PACKAGE_INOUTARCHIVING])) {
            $numberOfFeaturedProducts++;
        }
        $domains = $domainRegistrations = new \Illuminate\Support\Collection();
        if (\Auth::client()) {
            $domains = \WHMCS\Service\Service::where("userid", \Auth::client()->id)->where("domain", "!=", "")->where("domainstatus", "Active")->pluck("domain");
            $domainRegistrations = \WHMCS\Domain\Domain::where("userid", \Auth::client()->id)->where("domain", "!=", "")->where("status", "Active")->pluck("domain");
        }
        $ca->assign("products", $products);
        $ca->assign("productOptions", $options);
        $ca->assign("numberOfFeaturedProducts", $numberOfFeaturedProducts);
        $ca->assign("domains", $domains->merge($domainRegistrations)->unique());
        return $ca;
    }
}
