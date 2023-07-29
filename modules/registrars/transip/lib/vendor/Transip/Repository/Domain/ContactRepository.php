<?php

namespace Transip\Api\Library\Repository\Domain;

class ContactRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "contacts";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($domainName)
    {
        $contacts = [];
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $contactsArray = $this->getParameterFromResponse($response, "contacts");
        foreach ($contactsArray as $contactArray) {
            $contacts[] = new \Transip\Api\Library\Entity\Domain\WhoisContact($contactArray);
        }
        return $contacts;
    }

    public function update($domainName, $contacts)
    {
        $this->httpClient->put($this->getResourceUrl($domainName), ["contacts" => $contacts]);
    }
}
