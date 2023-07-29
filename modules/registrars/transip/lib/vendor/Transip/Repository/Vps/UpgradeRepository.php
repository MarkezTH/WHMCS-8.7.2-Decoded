<?php

namespace Transip\Api\Library\Repository\Vps;

class UpgradeRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "upgrades";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName)
    {
        $products = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $upgradesArray = $this->getParameterFromResponse($response, "upgrades");
        foreach ($upgradesArray as $upgradeArray) {
            $upgradeArray["category"] = "vps";
            $products[] = new \Transip\Api\Library\Entity\Product($upgradeArray);
        }
        return $products;
    }

    public function upgrade($vpsName, $productName)
    {
        $parameters["productName"] = $productName;
        $this->httpClient->post($this->getResourceUrl($vpsName), $parameters);
    }
}
