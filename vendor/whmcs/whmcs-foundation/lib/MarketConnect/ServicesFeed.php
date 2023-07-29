<?php

namespace WHMCS\MarketConnect;

class ServicesFeed
{
    protected $services = NULL;

    public function __construct($remoteFetch = true)
    {
        $this->loadServices($remoteFetch)->convertRecommendedRrpPrices(1);
    }

    protected function loadServices($self, $remoteFetch)
    {
        $services = $this->getServicesCache();
        if (!$remoteFetch) {
            return $this;
        }
        if (is_null($services) || !is_array($services)) {
            try {
                $this->performRemoteFetch();
            } catch (\Exception $e) {
            }
        }
        return $this;
    }

    protected function performRemoteFetch()
    {
        if (MarketConnect::isAccountConfigured()) {
            $api = new Api();
            $services = $api->servicesOnly();
            $encodedServices = json_encode($services);
            $mcServices = array_filter(MarketConnect::SERVICES, function ($serviceData) {
                return !empty($serviceData["service_replace_prefix"]);
            });
            if ($mcServices) {
                foreach ($mcServices as $serviceName => $serviceData) {
                    $replace = $serviceData["service_replace_prefix"];
                    $encodedServices = str_replace(["\"id\":\"" . $replace . "\"", $replace . "_"], ["\"id\":\"" . $serviceName . "\"", $serviceName . "_"], $encodedServices);
                    $services = json_decode($encodedServices, true);
                }
            }
            (new \WHMCS\TransientData())->store("MarketConnectServices", $encodedServices, 604800);
            $this->services = $services;
            return $services;
        } else {
            throw new \WHMCS\Exception("Account not configured");
        }
    }

    protected function getServicesCache()
    {
        $services = (new \WHMCS\TransientData())->retrieve("MarketConnectServices");
        if ($services) {
            $services = json_decode($services, true);
            $this->services = $services;
            return $services;
        }
        return NULL;
    }

    protected function getServices()
    {
        return is_array($this->services) ? $this->services : [];
    }

    public function hasServiceData()
    {
        return 0 < count($this->getServices());
    }

    public function isGroupIdInFeed($groupIdentifier)
    {
        foreach ($this->getServices() as $group) {
            if ($group["id"] == $groupIdentifier) {
                return true;
            }
        }
        return false;
    }

    public function getServicesByGroupId($id)
    {
        foreach ($this->getServices() as $group) {
            if ($group["id"] == $id) {
                return collect($group["services"]);
            }
        }
        return collect([]);
    }

    public function getEmulationOfConfiguredProducts($groupSlug)
    {
        if ($groupSlug == MarketConnect::SERVICE_SYMANTEC) {
            $groupSlugs = Services\Symantec::SSL_TYPES;
        } else {
            $groupSlugs = [$groupSlug];
        }
        $productCollection = new \Illuminate\Support\Collection();
        foreach ($groupSlugs as $slug) {
            if (!$this->isGroupIdInFeed($slug)) {
                try {
                    $this->services = $this->performRemoteFetch();
                } catch (\Exception $e) {
                }
            }
            foreach ($this->getServicesByGroupId($slug) as $listing) {
                $product = new \WHMCS\Product\Product();
                $product->id = 0;
                $product->name = $listing["display_name"];
                $product->description = $listing["description"];
                $product->moduleConfigOption1 = $listing["id"];
                $product->isHidden = false;
                $productCollection->push($product);
            }
        }
        return $productCollection;
    }

    public function isNotAvailable()
    {
        return is_null($this->services);
    }

    public function getTerms($productKey = NULL)
    {
        $serviceTerms = [];
        foreach ($this->getServices() as $group) {
            if (isset($group["services"])) {
                foreach ($group["services"] as $serviceData) {
                    $serviceTerms[$serviceData["id"]] = $serviceData["terms"];
                }
            }
        }
        if (is_null($productKey)) {
            return $serviceTerms;
        }
        return isset($serviceTerms[$productKey]) ? $serviceTerms[$productKey] : [];
    }

    public function getPricingMatrix($products)
    {
        $availableTerms = [];
        $terms = [];
        foreach ($products as $product) {
            $termData = collect($this->getTerms($product));
            foreach ($termData->pluck("term") as $term) {
                if (!in_array($term, $availableTerms)) {
                    $availableTerms[] = $term;
                }
            }
            $terms[$product] = $termData;
        }
        sort($availableTerms);
        if (in_array(0, $availableTerms)) {
            unset($availableTerms[0]);
            $availableTerms[] = 0;
        }
        $pricingMatrix = [];
        foreach ($terms as $product => $termData) {
            $data = [];
            foreach ($availableTerms as $term) {
                $data[$term] = [];
            }
            foreach ($termData as $termDataArray) {
                $data[$termDataArray["term"]] = $termDataArray;
            }
            $pricingMatrix[$product] = $data;
        }
        return $pricingMatrix;
    }

    public function getPricing($keyToFetch = "price")
    {
        $pricing = [];
        foreach ($this->getServices() as $group) {
            if (isset($group["services"])) {
                foreach ($group["services"] as $service) {
                    foreach ($service["terms"] as $key => $term) {
                        $pricing[$service["id"]][$key] = $term[$keyToFetch];
                    }
                }
            }
        }
        return $pricing;
    }

    public function getCostPrice($productKey)
    {
        $pricing = $this->getPricing();
        return isset($pricing[$productKey][0]) ? "\$" . $pricing[$productKey][0] : "-";
    }

    public function getRecommendedRetailPrice($productKey)
    {
        $pricing = $this->getPricing("recommendedRrp");
        return isset($pricing[$productKey][0]) ? "\$" . $pricing[$productKey][0] : "-";
    }

    public function convertRecommendedRrpPrices($rate)
    {
        $pricing = [];
        foreach ($this->getServices() as $groupKey => $group) {
            if (isset($group["services"])) {
                foreach ($group["services"] as $serviceKey => $service) {
                    foreach ($service["terms"] as $termKey => $term) {
                        $this->services[$groupKey]["services"][$serviceKey]["terms"][$termKey]["recommendedRrpDefaultCurrency"] = 0 < $rate ? format_as_currency($term["recommendedRrp"] / $rate) : 0;
                    }
                }
            }
        }
    }

    public function getGroupSlug($service)
    {
        return MarketConnect::getServiceProductGroupSlug($service);
    }

    public static function removeCache()
    {
        (new \WHMCS\TransientData())->delete("MarketConnectServices");
    }
}
