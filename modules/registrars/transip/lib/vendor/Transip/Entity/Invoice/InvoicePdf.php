<?php

namespace Transip\Api\Library\Entity\Invoice;

class InvoicePdf extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $pdf = NULL;

    public function getBase64Encoded()
    {
        return $this->pdf;
    }

    public function getPdf()
    {
        return base64_decode($this->pdf);
    }
}
