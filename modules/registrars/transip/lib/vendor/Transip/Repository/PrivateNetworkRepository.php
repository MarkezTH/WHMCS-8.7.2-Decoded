<?php

namespace Transip\Api\Library\Repository;

class PrivateNetworkRepository extends ApiRepository
{
    const RESOURCE_NAME = "private-networks";

    public function getAll()
    {
        $privateNetworks = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $privateNetworksArray = $this->getParameterFromResponse($response, "privateNetworks");
        foreach ($privateNetworksArray as $privateNetworkArray) {
            $privateNetworks[] = new \Transip\Api\Library\Entity\PrivateNetwork($privateNetworkArray);
        }
        return $privateNetworks;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $privateNetworks = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $privateNetworksArray = $this->getParameterFromResponse($response, "privateNetworks");
        foreach ($privateNetworksArray as $privateNetworkArray) {
            $privateNetworks[] = new \Transip\Api\Library\Entity\PrivateNetwork($privateNetworkArray);
        }
        return $privateNetworks;
    }

    public function findByDescription($description)
    {
        $privateNetworks = [];
        foreach ($this->getAll() as $privateNetwork) {
            if ($privateNetwork->getDescription() === $description) {
                $privateNetworks[] = $privateNetwork;
            }
        }
        return $privateNetworks;
    }

    public function getByName($PrivateNetwork, $privateNetworkName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($privateNetworkName));
        $privateNetwork = $this->getParameterFromResponse($response, "privateNetwork");
        return new \Transip\Api\Library\Entity\PrivateNetwork($privateNetwork);
    }

    public function order($description)
    {
        $parameters = [];
        if ($description) {
            $parameters["description"] = $description;
        }
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function update($privateNetwork)
    {
        $this->httpClient->put($this->getResourceUrl($privateNetwork->getName()), ["privateNetwork" => $privateNetwork]);
    }

    public function attachVps($privateNetworkName, $vpsName)
    {
        $parameters["action"] = "attachvps";
        $parameters["vpsName"] = $vpsName;
        $this->httpClient->patch($this->getResourceUrl($privateNetworkName), $parameters);
    }

    public function detachVps($privateNetworkName, $vpsName)
    {
        $parameters["action"] = "detachvps";
        $parameters["vpsName"] = $vpsName;
        $this->httpClient->patch($this->getResourceUrl($privateNetworkName), $parameters);
    }

    public function cancel($vpsName, $endTime)
    {
        $this->httpClient->delete($this->getResourceUrl($vpsName), ["endTime" => $endTime]);
    }
}
