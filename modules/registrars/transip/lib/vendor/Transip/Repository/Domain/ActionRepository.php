<?php

namespace Transip\Api\Library\Repository\Domain;

class ActionRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "actions";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($Action, $domainName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $action = $this->getParameterFromResponse($response, "action");
        return new \Transip\Api\Library\Entity\Domain\Action($action);
    }

    public function retryDomainAction($domainName = "", $authCode = [], $dnsEntries = [], $nameservers = [], $contacts)
    {
        $parameters = ["authCode" => $authCode, "dnsEntries" => $dnsEntries, "nameservers" => $nameservers, "contacts" => $contacts];
        $this->httpClient->patch($this->getResourceUrl($domainName), $parameters);
    }

    public function cancelAction($domainName)
    {
        $this->httpClient->delete($this->getResourceUrl($domainName), []);
    }
}
