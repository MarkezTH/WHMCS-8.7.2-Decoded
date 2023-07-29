<?php

namespace Transip\Api\Library\Repository\Domain;

class DnsSecRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "dnssec";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($domainName)
    {
        $dnssecEntries = [];
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $dnssecEntriesArray = $this->getParameterFromResponse($response, "dnsSecEntries");
        foreach ($dnssecEntriesArray as $dnssecEntryArray) {
            $dnssecEntries[] = new \Transip\Api\Library\Entity\Domain\DnsSecEntry($dnssecEntryArray);
        }
        return $dnssecEntries;
    }

    public function update($domainName, $dnsSecEntries)
    {
        $this->httpClient->put($this->getResourceUrl($domainName), ["dnsSecEntries" => $dnsSecEntries]);
    }
}
