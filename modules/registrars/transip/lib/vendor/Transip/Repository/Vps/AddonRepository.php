<?php

namespace Transip\Api\Library\Repository\Vps;

class AddonRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "addons";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName)
    {
        $addons = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $categoryArray = $this->getParameterFromResponse($response, "addons");
        foreach ($categoryArray as $category => $addonsArray) {
            foreach ($addonsArray as $addonArray) {
                $addonArray["category"] = $category;
                $addons[] = new \Transip\Api\Library\Entity\Product($addonArray);
            }
        }
        return $addons;
    }

    public function order($vpsName, $addonNames)
    {
        $parameters["addons"] = $addonNames;
        $this->httpClient->post($this->getResourceUrl($vpsName), $parameters);
    }

    public function cancel($vpsName, $addonName)
    {
        $this->httpClient->delete($this->getResourceUrl($vpsName, $addonName), []);
    }
}
