<?php

namespace Transip\Api\Library\Repository\Haip;

class CertificateRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "certificates";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\HaipRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByHaipName($haipName)
    {
        $certificates = [];
        $response = $this->httpClient->get($this->getResourceUrl($haipName));
        $certificateArray = $this->getParameterFromResponse($response, "certificates");
        foreach ($certificateArray as $certificateStruct) {
            $certificates[] = new \Transip\Api\Library\Entity\Haip\Certificate($certificateStruct);
        }
        return $certificates;
    }

    public function addBySslCertificateId($haipName, int $sslCertificateId)
    {
        $url = $this->getResourceUrl($haipName);
        $this->httpClient->post($url, ["sslCertificateId" => $sslCertificateId]);
    }

    public function addByCommonName($haipName, $commonName)
    {
        $url = $this->getResourceUrl($haipName);
        $this->httpClient->post($url, ["commonName" => $commonName]);
    }

    public function delete($haipName, int $haipCertificateId)
    {
        $url = $this->getResourceUrl($haipName, $haipCertificateId);
        $this->httpClient->delete($url);
    }
}
