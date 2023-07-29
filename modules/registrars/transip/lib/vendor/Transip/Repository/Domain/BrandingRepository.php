<?php

namespace Transip\Api\Library\Repository\Domain;

class BrandingRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "branding";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($Branding, $domainName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $branding = $this->getParameterFromResponse($response, "branding");
        return new \Transip\Api\Library\Entity\Domain\Branding($branding);
    }

    public function update($domainName, \Transip\Api\Library\Entity\Domain\Branding $branding)
    {
        $this->httpClient->put($this->getResourceUrl($domainName), ["branding" => $branding]);
    }
}
