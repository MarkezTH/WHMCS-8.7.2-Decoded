<?php

namespace Transip\Api\Library\Repository;

class DomainTldRepository extends ApiRepository
{
    const RESOURCE_NAME = "tlds";

    public function getAll()
    {
        $tlds = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $tldsArray = $this->getParameterFromResponse($response, "tlds");
        foreach ($tldsArray as $tldArray) {
            $tlds[] = new \Transip\Api\Library\Entity\Tld($tldArray);
        }
        return $tlds;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $tlds = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $tldList = $this->getParameterFromResponse($response, "tlds");
        foreach ($tldList as $tld) {
            $tlds[] = new \Transip\Api\Library\Entity\Tld($tld);
        }
        return $tlds;
    }

    public function getByTld($Tld, $tld)
    {
        $response = $this->httpClient->get($this->getResourceUrl($tld));
        $tldArray = $this->getParameterFromResponse($response, "tld");
        return new \Transip\Api\Library\Entity\Tld($tldArray);
    }
}
