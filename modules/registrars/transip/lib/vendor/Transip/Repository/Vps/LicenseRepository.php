<?php

namespace Transip\Api\Library\Repository\Vps;

class LicenseRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "licenses";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($Licenses, $vpsName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $licencesArray = $this->getParameterFromResponse($response, "licenses");
        $struct = [];
        foreach ($licencesArray as $licenseType => $licenses) {
            foreach ($licenses as $license) {
                if ($licenseType === "available") {
                    $struct[$licenseType][] = new \Transip\Api\Library\Entity\Vps\LicenseProduct($license);
                } else {
                    $struct[$licenseType][] = new \Transip\Api\Library\Entity\Vps\License($license);
                }
            }
        }
        return new \Transip\Api\Library\Entity\Vps\Licenses($struct);
    }

    public function order($vpsName, $licenseName, int $quantity)
    {
        $parameters = ["licenseName" => $licenseName, "quantity" => $quantity];
        $this->httpClient->post($this->getResourceUrl($vpsName), $parameters);
    }

    public function update($vpsName, int $licenseId, $newLicenseName)
    {
        $this->httpClient->put($this->getResourceUrl($vpsName, $licenseId), ["newLicenseName" => $newLicenseName]);
    }

    public function cancel($vpsName, int $licenseId)
    {
        $this->httpClient->delete($this->getResourceUrl($vpsName, $licenseId));
    }
}
