<?php

namespace Transip\Api\Library\Repository;

class TrafficPoolRepository extends ApiRepository
{
    const RESOURCE_NAME = "traffic-pool";

    public function getTrafficPool()
    {
        return $this->getByVpsName("");
    }

    public function getByVpsName($vpsName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $TrafficDatasArray = $this->getParameterFromResponse($response, "trafficPoolInformation");
        $trafficPoolInformation = [];
        foreach ($TrafficDatasArray as $TrafficDataArray) {
            $trafficPoolInformation[] = new \Transip\Api\Library\Entity\TrafficPoolInformation($TrafficDataArray);
        }
        return $trafficPoolInformation;
    }
}
