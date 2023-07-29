<?php

namespace Transip\Api\Library\Repository\Vps;

class VncDataRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "vnc-data";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($VncData, $vpsName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $vncDataArray = $this->getParameterFromResponse($response, "vncData");
        return new \Transip\Api\Library\Entity\Vps\VncData($vncDataArray);
    }

    public function regenerateVncCredentials($vpsName)
    {
        $this->httpClient->patch($this->getResourceUrl($vpsName), []);
    }
}
