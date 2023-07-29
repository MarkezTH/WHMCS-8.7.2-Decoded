<?php

namespace Transip\Api\Library\Repository\Domain;

class SslRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "ssl";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\DomainRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByDomainName($domainName)
    {
        $sslCertificates = [];
        $response = $this->httpClient->get($this->getResourceUrl($domainName));
        $sslCertificatesArray = $this->getParameterFromResponse($response, "certificates");
        foreach ($sslCertificatesArray as $sslCertificateArray) {
            $sslCertificates[] = new \Transip\Api\Library\Entity\Domain\SslCertificate($sslCertificateArray);
        }
        return $sslCertificates;
    }

    public function getByDomainNameCertificateId($SslCertificate, $domainName, int $certificateId)
    {
        $response = $this->httpClient->get($this->getResourceUrl($domainName, $certificateId));
        $certificate = $this->getParameterFromResponse($response, "certificate");
        return new \Transip\Api\Library\Entity\Domain\SslCertificate($certificate);
    }
}
