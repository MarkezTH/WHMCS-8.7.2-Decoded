<?php

namespace Transip\Api\Library\Repository\Haip;

class PortConfigurationRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "port-configurations";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\HaipRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByHaipName($haipName)
    {
        $portConfigurations = [];
        $response = $this->httpClient->get($this->getResourceUrl($haipName));
        $portConfigurationArray = $this->getParameterFromResponse($response, "portConfigurations");
        foreach ($portConfigurationArray as $portConfigurationStruct) {
            $portConfigurations[] = new \Transip\Api\Library\Entity\Haip\PortConfiguration($portConfigurationStruct);
        }
        return $portConfigurations;
    }

    public function getByPortConfigurationId($PortConfiguration, $haipName, int $portConfigurationId)
    {
        $response = $this->httpClient->get($this->getResourceUrl($haipName, $portConfigurationId));
        $portConfigurationStruct = $this->getParameterFromResponse($response, "portConfiguration");
        return new \Transip\Api\Library\Entity\Haip\PortConfiguration($portConfigurationStruct);
    }

    public function update($haipName, \Transip\Api\Library\Entity\Haip\PortConfiguration $portConfiguration)
    {
        $url = $this->getResourceUrl($haipName, $portConfiguration->getId());
        $this->httpClient->put($url, ["portConfiguration" => $portConfiguration]);
    }

    public function delete($haipName, int $portConfigurationId)
    {
        $url = $this->getResourceUrl($haipName, $portConfigurationId);
        $this->httpClient->delete($url);
    }

    public function add($haipName, \Transip\Api\Library\Entity\Haip\PortConfiguration $portConfiguration)
    {
        $url = $this->getResourceUrl($haipName);
        $requestData = ["name" => $portConfiguration->getName(), "sourcePort" => $portConfiguration->getSourcePort(), "targetPort" => $portConfiguration->getTargetPort(), "mode" => $portConfiguration->getMode(), "endpointSslMode" => $portConfiguration->getEndPointSslMode()];
        $this->httpClient->post($url, $requestData);
    }
}
