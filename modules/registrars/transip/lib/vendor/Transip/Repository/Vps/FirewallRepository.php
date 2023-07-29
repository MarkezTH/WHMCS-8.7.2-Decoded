<?php

namespace Transip\Api\Library\Repository\Vps;

class FirewallRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "firewall";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($Firewall, $vpsName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $firewallArray = $this->getParameterFromResponse($response, "vpsFirewall");
        return new \Transip\Api\Library\Entity\Vps\Firewall($firewallArray);
    }

    public function update($vpsName, \Transip\Api\Library\Entity\Vps\Firewall $firewall)
    {
        $this->httpClient->put($this->getResourceUrl($vpsName), ["vpsFirewall" => $firewall]);
    }

    public function reset($vpsName)
    {
        $this->httpClient->patch($this->getResourceUrl($vpsName), ["action" => "reset"]);
    }
}
