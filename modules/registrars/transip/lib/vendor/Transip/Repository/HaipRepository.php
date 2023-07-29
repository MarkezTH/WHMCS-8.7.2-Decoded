<?php

namespace Transip\Api\Library\Repository;

class HaipRepository extends ApiRepository
{
    const RESOURCE_NAME = "haips";

    public function getAll()
    {
        $haips = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $haipsArray = $this->getParameterFromResponse($response, "haips");
        foreach ($haipsArray as $haipArray) {
            $haips[] = new \Transip\Api\Library\Entity\Haip($haipArray);
        }
        return $haips;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $haips = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $haipsArray = $this->getParameterFromResponse($response, "haips");
        foreach ($haipsArray as $haipArray) {
            $haips[] = new \Transip\Api\Library\Entity\Haip($haipArray);
        }
        return $haips;
    }

    public function findByDescription($description)
    {
        $haips = [];
        foreach ($this->getAll() as $haip) {
            if ($haip->getDescription() === $description) {
                $haips[] = $haip;
            }
        }
        return $haips;
    }

    public function getByName($Haip, $name)
    {
        $response = $this->httpClient->get($this->getResourceUrl($name));
        $haip = $this->getParameterFromResponse($response, "haip");
        return new \Transip\Api\Library\Entity\Haip($haip);
    }

    public function order($productName, $description)
    {
        $parameters = ["productName" => $productName];
        if ($description) {
            $parameters["description"] = $description;
        }
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function update($haip)
    {
        $this->httpClient->put($this->getResourceUrl($haip->getName()), ["haip" => $haip]);
    }

    public function cancel($name, $endTime)
    {
        $this->httpClient->delete($this->getResourceUrl($name), ["endTime" => $endTime]);
    }
}
