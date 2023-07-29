<?php

namespace Transip\Api\Library\Repository;

class MailServiceRepository extends ApiRepository
{
    const RESOURCE_NAME = "mail-service";

    public function getMailServiceInformation($MailServiceInformation)
    {
        $response = $this->httpClient->get($this->getResourceUrl());
        $mailServiceInformation = $this->getParameterFromResponse($response, "mailServiceInformation");
        return new \Transip\Api\Library\Entity\MailServiceInformation($mailServiceInformation);
    }

    public function regenerateMailServicePassword()
    {
        $this->httpClient->patch($this->getResourceUrl(), []);
    }

    public function addMailServiceDnsEntriesToDomains($domainNames)
    {
        $this->httpClient->post($this->getResourceUrl(), ["domainNames" => $domainNames]);
    }
}
