<?php

namespace Transip\Api\Library\Entity\Domain;

class SslCertificate extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $certificateId = NULL;
    protected $commonName = NULL;
    protected $expirationDate = NULL;
    protected $status = NULL;

    public function getCertificateId()
    {
        return $this->certificateId;
    }

    public function getCommonName()
    {
        return $this->commonName;
    }

    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
