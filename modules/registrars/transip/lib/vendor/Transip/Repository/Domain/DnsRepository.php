<?php

namespace Transip\Api\Library\Repository\Domain;

class DnsRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "dns";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($domainName)
    {
        $dnsEntries = [];
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $dnsEntriesArray = $this->getParameterFromResponse($response, "dnsEntries");
        foreach ($dnsEntriesArray as $dnsEntryArray) {
            $dnsEntries[] = new \Transip\Api\Library\Entity\Domain\DnsEntry($dnsEntryArray);
        }
        return $dnsEntries;
    }

    public function addDnsEntryToDomain($domainName, \Transip\Api\Library\Entity\Domain\DnsEntry $dnsEntry)
    {
        $this->httpClient->post($this->getResourceUrl($domainName), ["dnsEntry" => $dnsEntry]);
    }

    public function updateEntry($domainName, \Transip\Api\Library\Entity\Domain\DnsEntry $dnsEntry)
    {
        $this->httpClient->patch($this->getResourceUrl($domainName), ["dnsEntry" => $dnsEntry]);
    }

    public function update($domainName, $dnsEntries)
    {
        $this->httpClient->put($this->getResourceUrl($domainName), ["dnsEntries" => $dnsEntries]);
    }

    public function removeDnsEntry($domainName, \Transip\Api\Library\Entity\Domain\DnsEntry $dnsEntry)
    {
        $this->httpClient->delete($this->getResourceUrl($domainName), ["dnsEntry" => $dnsEntry]);
    }
}
