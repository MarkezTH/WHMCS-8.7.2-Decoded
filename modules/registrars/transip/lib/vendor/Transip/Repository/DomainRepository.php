<?php

namespace Transip\Api\Library\Repository;

class DomainRepository extends ApiRepository
{
    const RESOURCE_NAME = "domains";

    public function getAll()
    {
        $domains = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $domainsArray = $this->getParameterFromResponse($response, "domains");
        foreach ($domainsArray as $domainArray) {
            $domains[] = new \Transip\Api\Library\Entity\Domain($domainArray);
        }
        return $domains;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $domains = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $domainsArray = $this->getParameterFromResponse($response, "domains");
        foreach ($domainsArray as $domainArray) {
            $domains[] = new \Transip\Api\Library\Entity\Domain($domainArray);
        }
        return $domains;
    }

    public function getByName($Domain, $name)
    {
        $response = $this->httpClient->get($this->getResourceUrl($name));
        $domain = $this->getParameterFromResponse($response, "domain");
        return new \Transip\Api\Library\Entity\Domain($domain);
    }

    public function getByTagNames($tags)
    {
        $tags = implode(",", $tags);
        $query = ["tags" => $tags];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $domainsArray = $this->getParameterFromResponse($response, "domains");
        $domains = [];
        foreach ($domainsArray as $domainArray) {
            $domains[] = new \Transip\Api\Library\Entity\Domain($domainArray);
        }
        return $domains;
    }

    public function register($domainName = [], $contacts = [], $nameservers = [], $dnsEntries)
    {
        $parameters = ["domainName" => $domainName, "contacts" => $contacts, "nameservers" => $nameservers, "dnsEntries" => $dnsEntries];
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function transfer($domainName, $authCode = [], $contacts = [], $nameservers = [], $dnsEntries)
    {
        $parameters = ["domainName" => $domainName, "authCode" => $authCode, "contacts" => $contacts, "nameservers" => $nameservers, "dnsEntries" => $dnsEntries];
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function update($domain)
    {
        $this->httpClient->put($this->getResourceUrl($domain->getName()), ["domain" => $domain]);
    }

    public function cancel($domainName, $endTime)
    {
        $this->httpClient->delete($this->getResourceUrl($domainName), ["endTime" => $endTime]);
    }
}
