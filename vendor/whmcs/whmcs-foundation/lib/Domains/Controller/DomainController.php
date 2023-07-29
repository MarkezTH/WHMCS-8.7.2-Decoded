<?php

namespace WHMCS\Domains\Controller;

class DomainController
{
    public function pricing(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $view = new \WHMCS\ClientArea();
        $view->setTemplate("domain-pricing");
        $view->setPageTitle(\Lang::trans("domainspricing"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"))->addToBreadCrumb(routePath("domain-pricing"), \Lang::trans("domainspricing"));
        $templateVariables = [];
        $currency = \Currency::factoryForClientArea();
        $templateVariables["activeCurrency"] = $currency;
        $pricing = localAPI("GetTldPricing", ["clientid" => \Auth::user()->id, "currencyid" => $currency["id"]]);
        $templateVariables["pricing"] = $pricing["pricing"];
        foreach ($templateVariables["pricing"] as $tld => &$priceData) {
            foreach (["register", "transfer", "renew"] as $action) {
                if (isset($priceData[$action]) && is_array($priceData[$action])) {
                    foreach ($priceData[$action] as $term => &$price) {
                        $price = new \WHMCS\View\Formatter\Price($price, $currency);
                    }
                }
            }
        }
        unset($price);
        unset($priceData);
        $extensions = array_keys($pricing["pricing"]) ?: [];
        $featuredTlds = [];
        $spotlights = getSpotlightTldsWithPricing();
        foreach ($spotlights as $spotlight) {
            if (file_exists(ROOTDIR . "/assets/img/tld_logos/" . $spotlight["tldNoDots"] . ".png")) {
                $featuredTlds[] = $spotlight;
            }
        }
        $templateVariables["featuredTlds"] = $featuredTlds;
        $tldCategories = new \WHMCS\Domain\TopLevel\Categories();
        $categories = $tldCategories->getCategoriesByTlds($extensions);
        $categoriesWithCounts = [];
        foreach ($categories as $category => $tlds) {
            $categoriesWithCounts[$category] = count($tlds);
        }
        $templateVariables["tldCategories"] = $categoriesWithCounts;
        $view->setTemplateVariables($templateVariables);
        return $view;
    }

    public function sslCheck(\WHMCS\Http\Message\ServerRequest $request)
    {
        $domain = trim($request->get("domain"));
        $userId = \Auth::client() ? \Auth::client()->id : 0;
        \WHMCS\Session::release();
        $type = $request->get("type", "service");
        if (!in_array($type, ["domain", "service"])) {
            $type = "service";
        }
        $table = "tblhosting";
        $statusField = "domainstatus";
        if ($type == "domain") {
            $table = "tbldomains";
            $statusField = "status";
        }
        $activeDomain = \WHMCS\Database\Capsule::table($table)->where("domain", $domain)->where("userid", $userId)->whereIn($statusField, ["Active", "Completed", "Grace"])->pluck("id")->all();
        if ($activeDomain) {
            $sslStatus = \WHMCS\Domain\Ssl\Status::factory($userId, $domain)->syncAndSave();
            $response = ["image" => $sslStatus->getImagePath(), "tooltip" => $sslStatus->getTooltipContent(), "class" => $sslStatus->getClass(), "ssl" => ["status" => $sslStatus->getStatus(), "startDate" => $sslStatus->startDate ? $sslStatus->startDate->toClientDateFormat() : NULL, "expiryDate" => $sslStatus->expiryDate ? $sslStatus->expiryDate->toClientDateFormat() : NULL, "issuer" => $sslStatus->issuerName ? $sslStatus->issuerName : NULL], "statusDisplayLabel" => $sslStatus->getStatusDisplayLabel()];
        } else {
            $response = ["invalid" => true];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
