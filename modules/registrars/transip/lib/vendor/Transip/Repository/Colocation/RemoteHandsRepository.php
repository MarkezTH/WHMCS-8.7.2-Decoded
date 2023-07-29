<?php

namespace Transip\Api\Library\Repository\Colocation;

class RemoteHandsRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "remote-hands";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\ColocationRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function create($remoteHands)
    {
        $url = $this->getResourceUrl($remoteHands->getColoName());
        $parameters = ["remoteHands" => $remoteHands];
        $this->httpClient->post($url, $parameters);
    }
}
