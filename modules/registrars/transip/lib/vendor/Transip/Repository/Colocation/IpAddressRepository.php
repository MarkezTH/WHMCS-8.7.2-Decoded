<?php

namespace Transip\Api\Library\Repository\Colocation;

class IpAddressRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "ip-addresses";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\ColocationRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByColoName($coloName)
    {
        $ipAddresses = [];
        $response = $this->httpClient->get($this->getResourceUrl($coloName));
        $ipAddressesArray = $this->getParameterFromResponse($response, "ipAddresses");
        foreach ($ipAddressesArray as $ipAddressArray) {
            $ipAddresses[] = new \Transip\Api\Library\Entity\Vps\IpAddress($ipAddressArray);
        }
        return $ipAddresses;
    }

    public function getByColoNameAddress($IpAddress, $coloName, $ipAddress)
    {
        $response = $this->httpClient->get($this->getResourceUrl($coloName, $ipAddress));
        $ipAddress = $this->getParameterFromResponse($response, "ipAddress");
        return new \Transip\Api\Library\Entity\Vps\IpAddress($ipAddress);
    }

    public function update($coloName, \Transip\Api\Library\Entity\Vps\IpAddress $ipAddress)
    {
        $url = $this->getResourceUrl($coloName, $ipAddress->getAddress());
        $this->httpClient->put($url, ["ipAddress" => $ipAddress]);
    }

    public function addIpAddress($coloName, $ipAddress = "", $reverseDns)
    {
        $url = $this->getResourceUrl($coloName, $ipAddress);
        $parameters = [];
        if ($reverseDns !== "") {
            $parameters = ["ipAddress" => ["reverseDns" => $reverseDns]];
        }
        $this->httpClient->post($url, $parameters);
    }

    public function removeAddress($coloName, $ipAddress)
    {
        $url = $this->getResourceUrl($coloName, $ipAddress);
        $this->httpClient->delete($url);
    }
}
