<?php

namespace Transip\Api\Library\Repository\Vps;

class IpAddressRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "ip-addresses";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName)
    {
        $ipAddresses = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $ipAddressesArray = $this->getParameterFromResponse($response, "ipAddresses");
        foreach ($ipAddressesArray as $ipAddressArray) {
            $ipAddresses[] = new \Transip\Api\Library\Entity\Vps\IpAddress($ipAddressArray);
        }
        return $ipAddresses;
    }

    public function getByVpsNameAddress($IpAddress, $vpsName, $ipAddress)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName, $ipAddress));
        $ipAddress = $this->getParameterFromResponse($response, "ipAddress");
        return new \Transip\Api\Library\Entity\Vps\IpAddress($ipAddress);
    }

    public function update($vpsName, \Transip\Api\Library\Entity\Vps\IpAddress $ipAddress)
    {
        $url = $this->getResourceUrl($vpsName, $ipAddress->getAddress());
        $this->httpClient->put($url, ["ipAddress" => $ipAddress]);
    }

    public function addIpv6Address($vpsName, $ipv6Address)
    {
        $url = $this->getResourceUrl($vpsName);
        $this->httpClient->post($url, ["ipAddress" => $ipv6Address]);
    }

    public function removeIpv6Address($vpsName, $ipv6Address)
    {
        $url = $this->getResourceUrl($vpsName, $ipv6Address);
        $this->httpClient->delete($url);
    }
}
