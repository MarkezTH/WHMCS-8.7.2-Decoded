<?php

namespace WHMCS\MarketConnect;

class NordVPNController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_NORDVPN;
    protected $langPrefix = "nordvpn";

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = parent::index($request);
        if ($ca instanceof \Laminas\Diactoros\Response\RedirectResponse) {
            return $ca;
        }
        $pricingFeed = [];
        $currency = $ca->retrieve("activeCurrency");
        $plans = $ca->retrieve("plans");
        $isAdminPreview = $ca->retrieve("inPreview");
        $promoHelper = $ca->retrieve("promoHelper");
        $emulated = $isAdminPreview && $plans->contains("id", "=", 0);
        if ($emulated) {
            $feed = new ServicesFeed();
            $plans = $feed->getEmulationOfConfiguredProducts($this->serviceName);
            $pricingFeed = $feed->getTerms(Promotion\Service\NordVPN::NORDVPN_STANDARD);
        }
        $highestMonthlyPrice = 0;
        $pricing = [];
        foreach ($plans as $plan) {
            if ($emulated) {
                foreach ($pricingFeed as $feedItem) {
                    if ($feedItem["term"] == 12) {
                        $term = "1 Year";
                    } else {
                        if ($feedItem["term"] == 1) {
                            $term = $feedItem["term"] . " Month";
                        } else {
                            $term = $feedItem["term"] . " Months";
                        }
                    }
                    $pricing[0][] = ["term" => $term, "price" => "-"];
                }
            } else {
                $highestMonthlyPrice = $plan->pricing($currency)->getHighestMonthly();
                $pricing[$plan->id] = $plan->pricing($currency)->allAvailableCycles();
            }
            $plan->planFeatures = $promoHelper->getPlanFeatures($plan->productKey);
        }
        $ca->assign("plans", $plans);
        $ca->assign("pricings", $pricing);
        $ca->assign("highestMonthlyPrice", $highestMonthlyPrice);
        return $ca;
    }
}
