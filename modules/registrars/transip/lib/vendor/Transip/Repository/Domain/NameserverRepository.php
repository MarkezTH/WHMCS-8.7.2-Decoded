<?php

namespace Transip\Api\Library\Repository\Domain;

class NameserverRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "nameservers";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($domainName)
    {
        $nameservers = [];
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $nameserversArray = $this->getParameterFromResponse($response, "nameservers");
        foreach ($nameserversArray as $nameserverArray) {
            $nameservers[] = new \Transip\Api\Library\Entity\Domain\Nameserver($nameserverArray);
        }
        return $nameservers;
    }

    public function update($domainName, $nameservers)
    {
        $this->httpClient->put($this->getResourceUrl($domainName), ["nameservers" => $nameservers]);
    }
}
