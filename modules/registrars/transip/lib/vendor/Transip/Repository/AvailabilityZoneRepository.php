<?php

namespace Transip\Api\Library\Repository;

class AvailabilityZoneRepository extends ApiRepository
{
    const RESOURCE_NAME = "availability-zones";

    public function getAll()
    {
        $availabilityZones = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $availabilityZonesArray = $this->getParameterFromResponse($response, "availabilityZones");
        foreach ($availabilityZonesArray as $availabilityZoneArray) {
            $availabilityZones[] = new \Transip\Api\Library\Entity\AvailabilityZone($availabilityZoneArray);
        }
        return $availabilityZones;
    }
}
