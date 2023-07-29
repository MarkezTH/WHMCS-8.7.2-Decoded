<?php

namespace Transip\Api\Library\Repository;

class TrafficRepository extends ApiRepository
{
    const RESOURCE_NAME = "traffic";

    public function getTrafficPool($TrafficInformation)
    {
        return $this->getByVpsName("");
    }

    public function getByVpsName($TrafficInformation, $vpsName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $trafficInformation = $this->getParameterFromResponse($response, "trafficInformation");
        return new \Transip\Api\Library\Entity\TrafficInformation($trafficInformation);
    }
}
