<?php

namespace Transip\Api\Library\Repository\Haip;

class IpAddressRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "ip-addresses";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\HaipRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByHaipName($haipName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($haipName));
        $ipAddressesArray = $this->getParameterFromResponse($response, "ipAddresses");
        return $ipAddressesArray;
    }

    public function update($haipName, $ipAddresses)
    {
        $url = $this->getResourceUrl($haipName);
        $this->httpClient->put($url, ["ipAddresses" => $ipAddresses]);
    }

    public function delete($haipName)
    {
        $url = $this->getResourceUrl($haipName);
        $this->httpClient->delete($url);
    }
}
