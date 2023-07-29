<?php

namespace Transip\Api\Library\Repository\Domain;

class WhoisRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "whois";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($domainName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $whois = $this->getParameterFromResponse($response, "whois");
        return $whois;
    }
}
