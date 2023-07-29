<?php

namespace Transip\Api\Library\Repository;

class DomainAvailabilityRepository extends ApiRepository
{
    const RESOURCE_NAME = "domain-availability";

    public function checkDomainName($DomainCheckResult, $domainName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $domainCheckResult = $this->getParameterFromResponse($response, "availability");
        return new \Transip\Api\Library\Entity\DomainCheckResult($domainCheckResult);
    }

    public function checkMultipleDomainNames($domainNames)
    {
        $domainCheckResults = [];
        $response = $this->httpClient->get($this->getResourceUrl(), ["domainNames" => $domainNames]);
        $domainCheckArray = $this->getParameterFromResponse($response, "availability");
        foreach ($domainCheckArray as $domainArray) {
            $domainCheckResults[] = new \Transip\Api\Library\Entity\DomainCheckResult($domainArray);
        }
        return $domainCheckResults;
    }
}
