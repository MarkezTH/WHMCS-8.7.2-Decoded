<?php

namespace Transip\Api\Library\Repository\Invoice;

class PdfRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "pdf";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\InvoiceRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByInvoiceNumber($InvoicePdf, $invoiceNumber)
    {
        $response = $this->httpClient->get($this->getResourceUrl($invoiceNumber));
        $pdf = $this->getParameterFromResponse($response, self::RESOURCE_NAME);
        return new \Transip\Api\Library\Entity\Invoice\InvoicePdf([self::RESOURCE_NAME => $pdf]);
    }
}
