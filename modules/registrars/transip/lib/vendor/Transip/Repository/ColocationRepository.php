<?php

namespace Transip\Api\Library\Repository;

class ColocationRepository extends ApiRepository
{
    const RESOURCE_NAME = "colocations";

    public function getAll()
    {
        $colocations = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $colocationsArray = $this->getParameterFromResponse($response, "colocations");
        foreach ($colocationsArray as $colocationArray) {
            $colocations[] = new \Transip\Api\Library\Entity\Colocation($colocationArray);
        }
        return $colocations;
    }

    public function getByName($Colocation, $name)
    {
        $response = $this->httpClient->get($this->getResourceUrl($name));
        $colocation = $this->getParameterFromResponse($response, "colocation");
        return new \Transip\Api\Library\Entity\Colocation($colocation);
    }
}
