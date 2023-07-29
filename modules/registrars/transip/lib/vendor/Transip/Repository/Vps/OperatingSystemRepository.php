<?php

namespace Transip\Api\Library\Repository\Vps;

class OperatingSystemRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "operating-systems";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getAll()
    {
        $operatingSystems = [];
        $response = $this->httpClient->get($this->getResourceUrl("placeholder"));
        $operatingSystemsArray = $this->getParameterFromResponse($response, "operatingSystems");
        foreach ($operatingSystemsArray as $operatingSystemArray) {
            $operatingSystems[] = new \Transip\Api\Library\Entity\Vps\OperatingSystem($operatingSystemArray);
        }
        return $operatingSystems;
    }

    public function getByVpsName($vpsName)
    {
        $operatingSystems = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $operatingSystemsArray = $this->getParameterFromResponse($response, "operatingSystems");
        foreach ($operatingSystemsArray as $operatingSystemArray) {
            $operatingSystems[] = new \Transip\Api\Library\Entity\Vps\OperatingSystem($operatingSystemArray);
        }
        return $operatingSystems;
    }

    public function install($vpsName, $operatingSystemName = "", $hostname = "", $base64InstallText = "", $installFlavour = "", $username = [], $sshKeys)
    {
        $parameters["operatingSystemName"] = $operatingSystemName;
        $parameters["hostname"] = $hostname;
        $parameters["base64InstallText"] = $base64InstallText;
        $parameters["installFlavour"] = $installFlavour;
        $parameters["username"] = $username;
        $parameters["sshKeys"] = $sshKeys;
        $this->httpClient->post($this->getResourceUrl($vpsName), $parameters);
    }
}
