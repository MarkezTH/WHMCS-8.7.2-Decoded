<?php

namespace WHMCS\MarketConnect;

class ThreeSixtyMonitoringController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_THREESIXTYMONITORING;
    protected $langPrefix = MarketConnect::SERVICE_THREESIXTYMONITORING;

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = parent::index($request);
        if ($ca instanceof \WHMCS\Http\RedirectResponse) {
            return $ca;
        }
        $plans = $ca->retrieve("plans");
        $expectedWebsitePlans = [Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_LITE, Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_PERSONAL, Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_PLUS, Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_ADVANCED];
        $expectedServerPlans = [Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_PRO, Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_BUSINESS, Promotion\Service\ThreeSixtyMonitoring::THREESIXTYMONITORING_ENTERPRISE];
        $activePlansArray = $plans->pluck("configoption1")->toArray();
        $websitePlanCount = count(array_intersect($expectedWebsitePlans, $activePlansArray));
        $serverPlanCount = count(array_intersect($expectedServerPlans, $activePlansArray));
        if ($plans->isEmpty() && $this->isAdminPreview()) {
            $websitePlanCount = 1;
            $serverPlanCount = 1;
        }
        if ($websitePlanCount + $serverPlanCount <= 0) {
            return \WHMCS\Http\RedirectResponse::legacyPath("index.php");
        }
        $plansArray = ["website" => [], "server" => []];
        foreach ($plans as $plan) {
            $sectionKey = "website";
            if (in_array($plan->configoption1, $expectedServerPlans)) {
                $sectionKey = "server";
            }
            $plansArray[$sectionKey][] = $plan;
        }
        $siteCheckProbes = [["name" => "Example City, Country", "id" => ""]];
        if (!$this->isAdminPreview()) {
            $siteCheckProbes = (new Services\ThreeSixtyMonitoring())->getSiteCheckProbes();
        }
        $ca->assign("threesixtymonitoring", ["probes" => $siteCheckProbes]);
        $ca->assign("planComparisonData", $plansArray);
        $ca->assign("websitePlanCount", $websitePlanCount);
        $ca->assign("serverPlanCount", $serverPlanCount);
        return $ca;
    }

    public function performSiteCheck(\WHMCS\Http\Message\ServerRequest $request)
    {
        $url = $request->get("url");
        $probeId = $request->get("probe_id");
        if (!filter_var($url, FILTER_VALIDATE_DOMAIN)) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => "Please specify a valid URL."], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
        if (empty($probeId)) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => "You must select a probe."], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
        if (!preg_match("/^https?:\\/\\//i", $url)) {
            $url = "https://" . $url;
        }
        try {
            $siteCheckResult = (new Api())->performThreeSixtyMonitoringSiteCheck($url, $probeId);
        } catch (\Throwable $e) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => $e->getMessage()], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
        $timeLimits = ["time_connect" => ["max_positive" => 0.3, "max_concern" => 0.6], "time_dns" => ["max_positive" => 0.2, "max_concern" => 0.5], "time_firstbyte" => ["max_positive" => 0.8, "max_concern" => 1.9], "time_total" => ["max_positive" => 1.3, "max_concern" => 3]];
        $isHostUp = !$siteCheckResult["down_now"];
        $siteCheckResult["host_status"] = ["value" => $isHostUp ? "Online" : "Offline", "icon" => $isHostUp ? "positive" : "negative"];
        foreach ($timeLimits as $metric => $limitData) {
            $value = round($siteCheckResult[$metric], 3);
            $label = "negative";
            if ($isHostUp) {
                if ($value <= $limitData["max_positive"]) {
                    $label = "positive";
                } else {
                    if ($value <= $limitData["max_concern"]) {
                        $label = "concern";
                    }
                }
            }
            $siteCheckResult[$metric] = ["value" => $value, "icon" => $label];
        }
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "url" => $url, "probe_id" => $probeId, "result" => $siteCheckResult]);
    }

    public function getServiceDashboardData(\WHMCS\Http\Message\ServerRequest $request)
    {
        $identifier = $request->get("service");
        $marketConnect = new Promotion\Service\ThreeSixtyMonitoring();
        $services = $marketConnect->getServices();
        if (strpos($identifier, "a") === 0) {
            $type = "addon";
            $id = substr($identifier, 1);
        } else {
            $type = "service";
            $id = $identifier;
        }
        if (!is_numeric($id)) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => "Invalid service or addon ID"], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
        $dashboardData = [];
        foreach ($services as $service) {
            if ($service["type"] == $type && $service["id"] == $id) {
                $dashboardData = $marketConnect->getDashboardData($service);
            }
        }
        if (empty($dashboardData)) {
            return new \WHMCS\Http\Message\JsonResponse(["message" => "Invalid service or addon ID"], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
        return new \WHMCS\Http\Message\JsonResponse($dashboardData);
    }
}
