<?php

namespace Transip\Api\Library\Repository;

class VpsRepository extends ApiRepository
{
    const RESOURCE_NAME = "vps";

    public function getAll()
    {
        $vpss = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $vpssArray = $this->getParameterFromResponse($response, "vpss");
        foreach ($vpssArray as $vpsArray) {
            $vpss[] = new \Transip\Api\Library\Entity\Vps($vpsArray);
        }
        return $vpss;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $vpss = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $vpssArray = $this->getParameterFromResponse($response, "vpss");
        foreach ($vpssArray as $vpsArray) {
            $vpss[] = new \Transip\Api\Library\Entity\Vps($vpsArray);
        }
        return $vpss;
    }

    public function getByName($Vps, $name)
    {
        $response = $this->httpClient->get($this->getResourceUrl($name));
        $vps = $this->getParameterFromResponse($response, "vps");
        return new \Transip\Api\Library\Entity\Vps($vps);
    }

    public function getByTagNames($tags)
    {
        $tags = implode(",", $tags);
        $query = ["tags" => $tags];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $vpssArray = $this->getParameterFromResponse($response, "vpss");
        $vpss = [];
        foreach ($vpssArray as $vpsArray) {
            $vpss[] = new \Transip\Api\Library\Entity\Vps($vpsArray);
        }
        return $vpss;
    }

    public function order($productName, $operatingSystemName = [], $addons = "", $hostname = "", $availabilityZone = "", $description = "", $base64InstallText = "", $installFlavour = "", $username = [], $sshKeys)
    {
        $parameters["productName"] = $productName;
        $parameters["operatingSystem"] = $operatingSystemName;
        if (!empty($addons)) {
            $parameters["addons"] = $addons;
        }
        if ($hostname !== "") {
            $parameters["hostname"] = $hostname;
        }
        if ($availabilityZone !== "") {
            $parameters["availabilityZone"] = $availabilityZone;
        }
        if ($description !== "") {
            $parameters["description"] = $description;
        }
        if ($base64InstallText !== "") {
            $parameters["base64InstallText"] = $base64InstallText;
        }
        if ($installFlavour !== "") {
            $parameters["installFlavour"] = $installFlavour;
        }
        if ($username !== "") {
            $parameters["username"] = $username;
        }
        if ($sshKeys !== "") {
            $parameters["sshKeys"] = $sshKeys;
        }
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function orderMultiple($vpss)
    {
        $this->httpClient->post($this->getResourceUrl(), ["vpss" => $vpss]);
    }

    public function cloneVps($vpsName = "", $availabilityZone)
    {
        $parameters["vpsName"] = $vpsName;
        if ($availabilityZone !== "") {
            $parameters["availabilityZone"] = $availabilityZone;
        }
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function update($vps)
    {
        $this->httpClient->put($this->getResourceUrl($vps->getName()), ["vps" => $vps]);
    }

    public function start($vpsName)
    {
        $this->httpClient->patch($this->getResourceUrl($vpsName), ["action" => "start"]);
    }

    public function stop($vpsName)
    {
        $this->httpClient->patch($this->getResourceUrl($vpsName), ["action" => "stop"]);
    }

    public function reset($vpsName)
    {
        $this->httpClient->patch($this->getResourceUrl($vpsName), ["action" => "reset"]);
    }

    public function handover($vpsName, $targetCustomerName)
    {
        $parameters = ["action" => "handover", "targetCustomerName" => $targetCustomerName];
        $this->httpClient->patch($this->getResourceUrl($vpsName), $parameters);
    }

    public function cancel($vpsName, $endTime)
    {
        $this->httpClient->delete($this->getResourceUrl($vpsName), ["endTime" => $endTime]);
    }
}
