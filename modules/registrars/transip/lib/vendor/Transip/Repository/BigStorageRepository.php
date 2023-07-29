<?php

namespace Transip\Api\Library\Repository;

class BigStorageRepository extends ApiRepository
{
    const RESOURCE_NAME = "big-storages";

    public function getAll()
    {
        $bigStorages = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $bigStoragesArray = $this->getParameterFromResponse($response, "bigStorages");
        foreach ($bigStoragesArray as $bigstorageArray) {
            $bigStorages[] = new \Transip\Api\Library\Entity\BigStorage($bigstorageArray);
        }
        return $bigStorages;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $bigStorages = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $bigStoragesArray = $this->getParameterFromResponse($response, "bigStorages");
        foreach ($bigStoragesArray as $bigstorageArray) {
            $bigStorages[] = new \Transip\Api\Library\Entity\BigStorage($bigstorageArray);
        }
        return $bigStorages;
    }

    public function getByName($BigStorage, $privateNetworkName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($privateNetworkName));
        $bigStorageArray = $this->getParameterFromResponse($response, "bigStorage");
        return new \Transip\Api\Library\Entity\BigStorage($bigStorageArray);
    }

    public function order($size = true, $offsiteBackup = "", $availabilityZone = "", $vpsName = "", $description)
    {
        $parameters = ["size" => $size, "offsiteBackups" => $offsiteBackup, "availabilityZone" => $availabilityZone, "vpsName" => $vpsName, "description" => $description];
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function upgrade($bigStorageName, int $size, $offsiteBackups = NULL)
    {
        $parameters = ["bigStorageName" => $bigStorageName, "size" => $size];
        if ($offsiteBackups !== NULL) {
            $parameters["offsiteBackups"] = $offsiteBackups;
        }
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function update($bigStorage)
    {
        $this->httpClient->put($this->getResourceUrl($bigStorage->getName()), ["bigStorage" => $bigStorage]);
    }

    public function cancel($vpsName, $endTime)
    {
        $this->httpClient->delete($this->getResourceUrl($vpsName), ["endTime" => $endTime]);
    }
}
